<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use function Amp\delay;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\Http\Client\Request;
use Exception;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Util\Json;
use Prooph\EventStoreClient\Exception\ClusterException;
use Prooph\EventStoreClient\GossipSeed;
use Prooph\EventStoreClient\Messages\ClusterMessages\ClusterInfoDto;
use Prooph\EventStoreClient\Messages\ClusterMessages\MemberInfoDto;
use Prooph\EventStoreClient\Messages\ClusterMessages\VNodeState;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
final class ClusterDnsEndPointDiscoverer implements EndPointDiscoverer
{
    private HttpClient $httpClient;

    /** @var list<MemberInfoDto> */
    private array $oldGossip = [];

    /**
     * @param list<GossipSeed> $gossipSeeds
     */
    public function __construct(
        private readonly Logger $log,
        private readonly string $clusterDns,
        private readonly int $maxDiscoverAttempts,
        private readonly int $managerExternalHttpPort,
        private readonly array $gossipSeeds,
        float $gossipTimeout,
        private readonly bool $preferRandomNode
    ) {
        $builder = new HttpClientBuilder();
        $builder->intercept(new SetRequestTimeout($gossipTimeout, $gossipTimeout, $gossipTimeout));
        $this->httpClient = $builder->build();
    }

    public function discover(?EndPoint $failedTcpEndPoint): NodeEndPoints
    {
        for ($attempt = 1; $attempt <= $this->maxDiscoverAttempts; ++$attempt) {
            try {
                $endPoints = $this->discoverEndPoint($failedTcpEndPoint);

                if (null !== $endPoints) {
                    $this->log->info(\sprintf(
                        'Discovering attempt %d/%d successful: best candidate is %s',
                        $attempt,
                        $this->maxDiscoverAttempts,
                        $endPoints
                    ));

                    return $endPoints;
                }
            } catch (Exception $e) {
                $this->log->info(\sprintf(
                    'Discovering attempt %d/%d failed with error: %s',
                    $attempt,
                    $this->maxDiscoverAttempts,
                    $e->getMessage()
                ));
            }

            delay(0.5);
        }

        throw new ClusterException(\sprintf(
            'Failed to discover candidate in %d attempts',
            $this->maxDiscoverAttempts
        ));
    }

    private function discoverEndPoint(?EndPoint $failedTcpEndPoint): ?NodeEndPoints
    {
        $oldGossip = $this->oldGossip;

        $gossipCandidates = ! empty($oldGossip)
            ? $this->getGossipCandidatesFromOldGossip($oldGossip, $failedTcpEndPoint)
            : $this->getGossipCandidatesFromDns();

        foreach ($gossipCandidates as $candidate) {
            $gossip = $this->tryGetGossipFrom($candidate);
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


        return null;
    }

    /** @return list<GossipSeed> */
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
     * @param list<MemberInfoDto> $oldGossip
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
     * @param list<MemberInfoDto> $members
     * @return GossipSeed[]
     */
    private function arrangeGossipCandidates(array $members): array
    {
        $result = [];
        $i = -1;
        $j = \count($members);

        foreach ($members as $k => $member) {
            if ($members[$k]->state()->value === VNodeState::Manager) {
                $result[--$j] = new GossipSeed(new EndPoint($members[$k]->httpAddress(), $members[$k]->httpPort()));
            } else {
                $result[++$i] = new GossipSeed(new EndPoint($members[$k]->httpAddress(), $members[$k]->httpPort()));
            }
        }

        \shuffle($result);

        return $result;
    }

    private function tryGetGossipFrom(GossipSeed $endPoint): ?ClusterInfoDto
    {
        $schema = $endPoint->seedOverTls() ? 'https://' : 'http://';
        $uri = $schema . $endPoint->endPoint()->host() . ':' . $endPoint->endPoint()->port() . '/gossip?format=json';
        $this->log->info($uri);

        try {
            $request = new Request($uri);

            $header = $endPoint->hostHeader();

            if (! empty($header)) {
                $headerData = \explode(':', $header);
                $request->setHeader($headerData[0], $headerData[1]);
            }

            $response = $this->httpClient->request($request);
        } catch (Exception $e) {
            $this->log->error($e->getMessage());

            return null;
        }

        if ($response->getStatus() !== 200) {
            return null;
        }

        $json = $response->getBody()->read();

        if ('' === $json) {
            return null;
        }

        $data = Json::decode($json);

        return new ClusterInfoDto(\array_map(
            fn (array $member) => new MemberInfoDto($member),
            $data['members']
        ));
    }

    /**
     * @param list<MemberInfoDto> $members
     * @param bool $preferRandomNode
     * @return NodeEndPoints|null
     */
    private function tryDetermineBestNode(array $members, bool $preferRandomNode): ?NodeEndPoints
    {
        $nodes = [];

        foreach ($members as $member) {
            if (\in_array(
                $member->state(),
                [
                    VNodeState::Manager,
                    VNodeState::ShuttingDown,
                    VNodeState::Shutdown,
                ],
                true
            )) {
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
            $normTcp,
            null === $secTcp ? 'n/a' : $secTcp,
            $node->state()->name
        ));

        return new NodeEndPoints($normTcp, $secTcp);
    }
}
