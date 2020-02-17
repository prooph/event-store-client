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

namespace ProophTest\EventStoreClient;

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Util\Guid;
use Throwable;

class when_disabling_projections extends TestCase
{
    use ProjectionSpecification;

    private string $projectionName;
    private string $streamName;
    private string $query;

    public function given(): Generator
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_disabling_projection-' . $id;
        $this->streamName = 'test-stream-' . $id;

        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $this->query = $this->createStandardQuery($this->streamName);

        yield $this->projectionsManager->createContinuousAsync(
            $this->projectionName,
            $this->query,
            false,
            'JS',
            $this->credentials
        );
    }

    protected function when(): Generator
    {
        yield $this->projectionsManager->disableAsync(
            $this->projectionName,
            $this->credentials
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_stop_the_projection(): void
    {
        $this->execute(function () {
            $projectionStatus = \json_decode(
                yield $this->projectionsManager->getStatusAsync(
                    $this->projectionName,
                    $this->credentials
                ),
                true
            );
            $status = $projectionStatus['status'];

            $this->assertStringStartsWith('Stopped', $status);
        });
    }
}
