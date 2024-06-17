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
use Prooph\EventStore\Projections\Query;
use Prooph\EventStore\Util\Guid;

class when_updating_a_projection_query extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $projectionName;

    private string $streamName;

    private string $newQuery;

    protected function given(): void
    {
        $this->projectionName = 'when_updating_a_projection_query';
        $this->streamName = 'test-stream-' . Guid::generateAsHex();

        $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $originalQuery = $this->createStandardQuery($this->streamName);
        $this->newQuery = $this->createStandardQuery('DifferentStream');

        $this->projectionsManager->createContinuous(
            $this->projectionName,
            $originalQuery,
            false,
            'JS',
            $this->credentials
        );
    }

    protected function when(): void
    {
        $this->projectionsManager->updateQuery(
            $this->projectionName,
            $this->newQuery,
            false,
            $this->credentials
        );
    }

    /** @test */
    public function should_update_the_projection_query(): void
    {
        $this->execute(function (): void {
            $query = $this->projectionsManager->getQuery(
                $this->projectionName,
                $this->credentials
            );

            $this->assertEquals(new Query($this->newQuery), $query);
        });
    }
}
