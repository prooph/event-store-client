<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Artax\Client;
use Amp\Artax\DefaultClient;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStoreClient\Exception\ClusterException;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\GossipSeed;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\Messages\ClusterMessages\ClusterInfoDto;
use Prooph\EventStoreClient\Messages\ClusterMessages\MemberInfoDto;
use Prooph\EventStoreClient\Messages\ClusterMessages\VNodeState;
use Psr\Log\LoggerInterface as Logger;
use Throwable;
use function Amp\call;

/** @internal */
final class ClusterDnsEndPointDiscoverer implements EndPointDiscoverer
{
    /** @var Logger */
    private $log;
    /** @var string */
    private $clusterDns;
    /** @var int */
    private $maxDiscoverAttempts;
    /** @var int */
    private $managerExternalHttpPort;
    /** @var GossipSeed[] */
    private $gossipSeeds = [];

    /** @var Client */
    private $client;
    /** @var MemberInfoDto[] */
    private $oldGossip = [];
    /** @var int */
    private $gossipTimeout;
    /** @var bool */
    private $preferRandomNode;

    /**
     * @param Logger $logger
     * @param string $clusterDns
     * @param int $maxDiscoverAttempts
     * @param int $managerExternalHttpPort
     * @param GossipSeed[] $gossipSeeds
     * @param int $gossipTimeout
     * @param bool $preferRandomNode
     */
    public function __construct(
        Logger $logger,
        string $clusterDns,
        int $maxDiscoverAttempts,
        int $managerExternalHttpPort,
        array $gossipSeeds,
        int $gossipTimeout,
        bool $preferRandomNode
    ) {
        $this->logger = $logger;
        $this->clusterDns = $clusterDns;
        $this->maxDiscoverAttempts = $maxDiscoverAttempts;
        $this->managerExternalHttpPort = $managerExternalHttpPort;

        foreach ($gossipSeeds as $gossipSeed) {
            if (! $gossipSeed instanceof GossipSeed) {
                throw new InvalidArgumentException('Expected an array of ' . GossipSeed::class);
            }

            $this->gossipSeeds[] = $gossipSeed;
        }

        $this->gossipTimeout = $gossipTimeout;
        $this->preferRandomNode = $preferRandomNode;

        $this->client = new DefaultClient();
        $this->client->setOption(Client::OP_TRANSFER_TIMEOUT, $gossipTimeout);
    }

    /** {@inheritdoc} */
    public function discoverAsync(?IpEndPoint $failedTcpEndPoint): Promise
    {
        return call(function () use ($failedTcpEndPoint): Generator {
            for ($attempt = 1; $attempt <= $this->maxDiscoverAttempts; ++$attempt) {
                try {
                    $endPoints = yield $this->discoverEndPoint($failedTcpEndPoint);

                    if (null !== $endPoints) {
                        $this->log->info(\sprintf(
                            'Discovering attempt %d/%d successful: best candidate is %s',
                            $attempt,
                            $this->maxDiscoverAttempts,
                            $endPoints
                        ));

                        return new Success($endPoints);
                    }
                } catch (Throwable $e) {
                    $this->log->info(\sprintf(
                        'Discovering attempt %d/%d failed with error: %s',
                        $attempt,
                        $this->maxDiscoverAttempts,
                        $e->getMessage()
                    ));
                }

                yield new Delayed(100);
            }

            throw new ClusterException(\sprintf(
                'Failed to discover candidate in %d attempts',
                $this->maxDiscoverAttempts
            ));
        });
    }

    /** @return Promise<NodeEndPoints|null> */
    private function discoverEndPoint(?IpEndPoint $failedTcpEndPoint): Promise
    {
        return call(function () use ($failedTcpEndPoint): Generator {
            $oldGossip = $this->oldGossip;

            $gossipCandidates = ! empty($oldGossip)
                ? $this->getGossipCandidatesFromOldGossip($oldGossip, $failedTcpEndPoint)
                : $this->getGossipCandidatesFromDns();

            foreach ($gossipCandidates as $candidate) {

                /** @var ClusterInfoDto|null $gossip */
                $gossip = yield $this->tryGetGossipFrom($candidate);

                if (null === $gossip || empty($gossip->members())) {
                    continue;
                }

                $bestNode = $this->tryDetermineBestNode($gossip->members(), $this->preferRandomNode);

                if (null !== $bestNode) {
                    $this->oldGossip = $gossip->members();

                    return $bestNode;
                }
            }

            return null;
        });
    }

    /** @return GossipSeed[] */
    private function getGossipCandidatesFromDns(): array
    {
        if (\count($this->gossipSeeds) > 0) {
            $endPoints = $this->gossipSeeds;
        } else {
            $endPoints = [new GossipSeed(new IpEndPoint($this->clusterDns, $this->managerExternalHttpPort))];
        }

        \shuffle($endPoints);

        return $endPoints;
    }

    /**
     * @param MemberInfoDto[] $oldGossip
     * @param IpEndPoint|null $failedTcpEndPoint
     * @return GossipSeed[]
     */
    private function getGossipCandidatesFromOldGossip(array $oldGossip, ?IpEndPoint $failedTcpEndPoint): array
    {
        $filter = function () use ($oldGossip, $failedTcpEndPoint): array {
            $result = [];
            foreach ($oldGossip as $dto) {
                if ($dto->externalTcpIp() === $failedTcpEndPoint->host()) {
                    continue;
                }

                $result[] = $dto;
            }

            return $result;
        };

        $gossipCandidates = null === $failedTcpEndPoint
            ? $oldGossip
            : $filter();

        return $this->arrangeGossipCandidates($gossipCandidates);
    }

    /**
     * @param MemberInfoDto[] $members
     * @return MemberInfoDto[]
     */
    private function arrangeGossipCandidates(array $members): array
    {
        $result = [];
        $i = -1;
        $j = \count($members);

        for ($k = 0; $k < \count($members); ++$k) {
            if ($members[$k]->state()->equals(VNodeState::manager())) {
                $result[--$j] = new GossipSeed(new IpEndPoint($members[$k]->externalHttpIp(), $members[$k]->externalHttpPort()));
            } else {
                $result[++$i] = new GossipSeed(new IpEndPoint($members[$k]->externalHttpIp(), $members[$k]->externalHttpPort()));
            }
        }

        \shuffle($result);

        return $result;
    }

    /** @return Promise<ClusterInfoDto|null> */
    private function tryGetGossipFrom(GossipSeed $endPoint): Promise
    {
        return call(function () use ($endPoint): Generator {
            $uri = 'http://' . $endPoint->endPoint()->host() . ':' . $endPoint->endPoint()->port() . '/gossip?format=json';

            try {
                $request = new Request($uri);

                $header = $endPoint->hostHeader();

                if (! empty($header)) {
                    $headerData = \explode(':', $header);
                    $request = $request->withHeader($headerData[0], $headerData[1]);
                }

                /** @var Response $response */
                $response = yield $this->client->request($request);
            } catch (Throwable $e) {
                return null;
            }

            if ($response->getStatus() !== 200) {
                return null;
            }

            $data = yield $response->getBody()->getInputStream()->read();
            $data = \json_decode($data, true);

            if (\JSON_ERROR_NONE !== \json_last_error()) {
                return null;
            }

            $members = [];

            foreach ($data['members'] as $member) {
                $members[] = new MemberInfoDto($member);
            }

            return new ClusterInfoDto($members);
        });
    }

    /**
     * @param MemberInfoDto[] $members
     * @param bool $preferRandomNode
     * @return null|NodeEndPoints
     */
    private function tryDetermineBestNode(array $members, bool $preferRandomNode): ?NodeEndPoints
    {
        /** @var MemberInfoDto[] $nodes */
        $nodes = [];

        foreach ($members as $member) {
            if ($member->state()->equals(VNodeState::manager())
                || $member->state()->equals(VNodeState::shuttingDown())
                || $member->state()->equals(VNodeState::shutdown())
            ) {
                continue;
            }

            $nodes[] = $member;
        }

        if (empty($nodes)) {
            return null;
        }

        $key = \rand(0, \count($nodes) - 1);
        $node = $nodes[$key];

        $normTcp = new IpEndPoint($node->externalTcpIp(), $node->externalTcpPort());
        $secTcp = $node->externalSecureTcpPort() > 0
            ? new IpEndPoint($node->externalTcpIp(), $node->externalSecureTcpPort())
            : null;

        $this->log->info(\sprintf(
            'Discovering: found best choice [%s, %s] (%s)',
            $normTcp,
            null === $secTcp ? 'n/a' : $secTcp,
            $node->state()
        ));

        return new NodeEndPoints($normTcp, $secTcp);
    }
}
