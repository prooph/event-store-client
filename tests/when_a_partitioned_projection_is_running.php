<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Prooph\EventStore\Util\Guid;

final class when_a_partitioned_projection_is_running extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $projectionName;
    private string $streamName;

    public function given(): Generator
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_getting_partition_information-' . $id;
        $this->streamName = 'test-stream-' . $id;

        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 1}', '{"username": "tesla"}');
        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 2}', '{"username": "nicola"}');
    }

    protected function when(): Generator
    {
        $query = $this->createPartitionedQuery($this->streamName);

        yield $this->projectionsManager->createContinuousAsync(
            $this->projectionName,
            $query,
            false,
            'JS',
            $this->credentials
        );
    }

    /** @test */
    public function should_be_able_to_get_the_partition_state(): Generator
    {
        yield $this->execute(function (): Generator {
            $state = yield $this->projectionsManager->getPartitionStateAsync(
                $this->projectionName,
                'nicola',
                $this->credentials
            );

            $this->assertNotEmpty($state->payload());
        });
    }

    /** @test */
    public function should_be_able_to_get_the_partition_result(): Generator
    {
        yield $this->execute(function (): Generator {
            $result = yield $this->projectionsManager->getPartitionResultAsync(
                $this->projectionName,
                'nicola',
                $this->credentials
            );

            $this->assertNotEmpty($result->payload());
        });
    }
}
