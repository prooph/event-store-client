<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\Util\Guid;

final class when_a_partitioned_projection_is_running extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $projectionName;

    private string $streamName;

    public function given(): void
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_getting_partition_information-' . $id;
        $this->streamName = 'test-stream-' . $id;

        $this->postEvent($this->streamName, 'testEvent', '{"A": 1}', '{"username": "tesla"}');
        $this->postEvent($this->streamName, 'testEvent', '{"A": 2}', '{"username": "nicola"}');
    }

    protected function when(): void
    {
        $query = $this->createPartitionedQuery($this->streamName);

        $this->projectionsManager->createContinuous(
            $this->projectionName,
            $query,
            false,
            'JS',
            $this->credentials
        );
    }

    /** @test */
    public function should_be_able_to_get_the_partition_state(): void
    {
        $this->execute(function (): void {
            $state = $this->projectionsManager->getPartitionState(
                $this->projectionName,
                'nicola',
                $this->credentials
            );

            $this->assertNotEmpty($state->payload());
        });
    }

    /** @test */
    public function should_be_able_to_get_the_partition_result(): void
    {
        $this->execute(function (): void {
            $result = $this->projectionsManager->getPartitionResult(
                $this->projectionName,
                'nicola',
                $this->credentials
            );

            $this->assertNotEmpty($result->payload());
        });
    }
}
