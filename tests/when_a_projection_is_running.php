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

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\Projections\Query;
use Prooph\EventStore\Projections\State;
use Prooph\EventStore\Util\Guid;

class when_a_projection_is_running extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $projectionName;

    private string $streamName;

    private string $query;

    public function given(): void
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_getting_projection_information-' . $id;
        $this->streamName = 'test-stream-' . $id;

        $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');
    }

    protected function when(): void
    {
        $this->query = $this->createStandardQuery($this->streamName);

        $this->projectionsManager->createContinuous(
            $this->projectionName,
            $this->query,
            false,
            'JS',
            $this->credentials
        );
    }

    /** @test */
    public function should_be_able_to_get_the_projection_state(): void
    {
        $this->execute(function (): void {
            $state = $this->projectionsManager->getState(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($state->payload());
        });
    }

    /** @test */
    public function should_be_able_to_get_the_projection_status(): void
    {
        $this->execute(function (): void {
            $status = $this->projectionsManager->getStatus(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($status);
        });
    }

    /** @test */
    public function should_be_able_to_get_the_projection_result(): void
    {
        $this->execute(function (): void {
            $result = $this->projectionsManager->getResult(
                $this->projectionName,
                $this->credentials
            );

            $expectedResult = new State(['count' => 1]);

            $this->assertEquals($expectedResult, $result);
        });
    }

    /** @test */
    public function should_be_able_to_get_the_projection_query(): void
    {
        $this->execute(function (): void {
            $query = $this->projectionsManager->getQuery(
                $this->projectionName,
                $this->credentials
            );

            $this->assertEquals(new Query($this->query), $query);
        });
    }

    /** @test */
    public function should_be_able_to_get_the_projection_statistics(): void
    {
        $this->execute(function (): void {
            $statistics = $this->projectionsManager->getStatistics(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($statistics);
        });
    }
}
