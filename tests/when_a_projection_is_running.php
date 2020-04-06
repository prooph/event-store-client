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

use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Prooph\EventStore\Util\Guid;

class when_a_projection_is_running extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $projectionName;
    private string $streamName;
    private string $query;

    public function given(): Generator
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_getting_projection_information-' . $id;
        $this->streamName = 'test-stream-' . $id;

        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');
    }

    protected function when(): Generator
    {
        $this->query = $this->createStandardQuery($this->streamName);

        yield $this->projectionsManager->createContinuousAsync(
            $this->projectionName,
            $this->query,
            false,
            'JS',
            $this->credentials
        );
    }

    /**
     * @test
     */
    public function should_be_able_to_get_the_projection_state(): Generator
    {
        yield $this->execute(function (): Generator {
            $state = yield $this->projectionsManager->getStateAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($state);
        });
    }

    /**
     * @test
     */
    public function should_be_able_to_get_the_projection_status(): Generator
    {
        yield $this->execute(function (): Generator {
            $status = yield $this->projectionsManager->getStatusAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($status);
        });
    }

    /**
     * @test
     */
    public function should_be_able_to_get_the_projection_result(): Generator
    {
        yield $this->execute(function (): Generator {
            $result = yield $this->projectionsManager->getResultAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertEquals('{"count":1}', $result);
        });
    }

    /**
     * @test
     */
    public function should_be_able_to_get_the_projection_query(): Generator
    {
        yield $this->execute(function (): Generator {
            $query = yield $this->projectionsManager->getQueryAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertSame($this->query, $query);
        });
    }
}
