<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use function Amp\call;
use Amp\Delayed;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Util\Json;
use Prooph\EventStoreClient\Exception\ClusterException;
use Prooph\EventStoreClient\GossipSeed;
use Prooph\EventStoreClient\Messages\ClusterMessages\ClusterInfoDto;
use Prooph\EventStoreClient\Messages\ClusterMessages\MemberInfoDto;
use Prooph\EventStoreClient\Messages\ClusterMessages\VNodeState;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/** @internal */
final class ClusterDnsEndPointDiscoverer implements EndPointDiscoverer
{
    private Logger $log;
    private string $clusterDns;
    private int $maxDiscoverAttempts;
    private int $managerExternalHttpPort;
    /** @var GossipSeed[] */
    private array $gossipSeeds = [];
    private HttpClient $httpClient;
    /** @var MemberInfoDto[] */
    private array $oldGossip = [];
    private int $gossipTimeout;
    private bool $preferRandomNode;

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
        $this->log = $logger;
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

        $builder = new HttpClientBuilder();
        $builder->intercept(new SetRequestTimeout($gossipTimeout, $gossipTimeout, $gossipTimeout));
        $this->httpClient = $builder->build();
    }

    /** {@inheritdoc} */
    public function discoverAsync(?EndPoint $failedTcpEndPoint): Promise
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

    /**
     * @param EndPoint|null $failedTcpEndPoint
     * @return Promise<NodeEndPoints|null>
     */
    private function discoverEndPoint(?EndPoint $failedTcpEndPoint): Promise
    {
        return call(function () use ($failedTcpEndPoint): Generator {
            $oldGossip = $this->oldGossip;

            $gossipCandidates = ! empty($oldGossip)
                ? $this->getGossipCandidatesFromOldGossip($oldGossip, $failedTcpEndPoint)
                : $this->getGossipCandidatesFromDns();

            foreach ($gossipCandidates as $candidate) {
                $gossip = yield $this->tryGetGossipFrom($candidate);
                \assert(null === $gossip || $gossip instanceof ClusterInfoDto);

                if (null === $gossip || empty($gossip->members())) {
                    continue;
                }

                $bestNode = $this->tryDetermineBestNode($gossip->members(), $this->preferRandomNode);

                if (null !== $bestNode) {
                    $this->oldGossip = $gossip->members();

                    return $bestNode;
                }
            }
        });
    }

    /** @return GossipSeed[] */
    private function getGossipCandidatesFromDns(): array
    {
        if (\count($this->gossipSeeds) > 0) {
            $endPoints = $this->gossipSeeds;
        } else {
            $endPoints = [new GossipSeed(new EndPoint($this->clusterDns, $this->managerExternalHttpPort))];
        }

        \shuffle($endPoints);

        return $endPoints;
    }

    /**
     * @param MemberInfoDto[] $oldGossip
     * @param EndPoint|null $failedTcpEndPoint
     * @return GossipSeed[]
     */
    private function getGossipCandidatesFromOldGossip(array $oldGossip, ?EndPoint $failedTcpEndPoint): array
    {
        $filter = function () use ($oldGossip, $failedTcpEndPoint): array {
            $result = [];
            foreach ($oldGossip as $dto) {
                if ($failedTcpEndPoint && $dto->externalTcpIp() === $failedTcpEndPoint->host()) {
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
     * @return GossipSeed[]
     */
    private function arrangeGossipCandidates(array $members): array
    {
        $result = [];
        $i = -1;
        $j = \count($members);

        foreach ($members as $k => $member) {
            if ($members[$k]->state()->equals(VNodeState::manager())) {
                $result[--$j] = new GossipSeed(new EndPoint($members[$k]->externalHttpIp(), $members[$k]->externalHttpPort()));
            } else {
                $result[++$i] = new GossipSeed(new EndPoint($members[$k]->externalHttpIp(), $members[$k]->externalHttpPort()));
            }
        }

        \shuffle($result);

        return $result;
    }

    /**
     * @param GossipSeed $endPoint
     * @return Promise<ClusterInfoDto|null>
     */
    private function tryGetGossipFrom(GossipSeed $endPoint): Promise
    {
        return call(function () use ($endPoint): Generator {
            $uri = 'http://' . $endPoint->endPoint()->host() . ':' . $endPoint->endPoint()->port() . '/gossip?format=json';

            try {
                $request = new Request($uri);

                $header = $endPoint->hostHeader();

                if (! empty($header)) {
                    $headerData = \explode(':', $header);
                    $request->setHeader($headerData[0], $headerData[1]);
                }

                $response = yield $this->httpClient->request($request);
                \assert($response instanceof Response);
            } catch (Throwable $e) {
                return;
            }

            if ($response->getStatus() !== 200) {
                return;
            }

            $json = yield $response->getBody()->read();

            if (null === $json) {
                return;
            }

            $data = Json::decode($json);

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
     * @return NodeEndPoints|null
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

        $key = 0;

        if ($preferRandomNode) {
            $key = \rand(0, \count($nodes) - 1);
        }

        $node = $nodes[$key];

        $normTcp = new EndPoint($node->externalTcpIp(), $node->externalTcpPort());
        $secTcp = $node->externalSecureTcpPort() > 0
            ? new EndPoint($node->externalTcpIp(), $node->externalSecureTcpPort())
            : null;

        $this->log->info(\sprintf(
            'Discovering: found best choice [%s, %s] (%s)',
            (string) $normTcp,
            null === $secTcp ? 'n/a' : (string) $secTcp,
            (string) $node->state()
        ));

        return new NodeEndPoints($normTcp, $secTcp);
    }
}
