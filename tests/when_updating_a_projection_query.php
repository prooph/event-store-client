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

class when_updating_a_projection_query extends TestCase
{
    use ProjectionSpecification;

    private string $projectionName;
    private string $streamName;
    private string $newQuery;

    protected function given(): Generator
    {
        $this->projectionName = 'when_updating_a_projection_query';
        $this->streamName = 'test-stream-' . Guid::generateAsHex();

        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $originalQuery = $this->createStandardQuery($this->streamName);
        $this->newQuery = $this->createStandardQuery('DifferentStream');

        yield $this->projectionsManager->createContinuousAsync(
            $this->projectionName,
            $originalQuery,
            false,
            'JS',
            $this->credentials
        );
    }

    protected function when(): Generator
    {
        yield $this->projectionsManager->updateQueryAsync(
            $this->projectionName,
            $this->newQuery,
            false,
            $this->credentials
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_update_the_projection_query(): void
    {
        $this->execute(function () {
            $query = yield $this->projectionsManager->getQueryAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertEquals($this->newQuery, $query);
        });
    }
}
