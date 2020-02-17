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

class when_a_projection_is_running extends TestCase
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
     * @throws Throwable
     */
    public function should_be_able_to_get_the_projection_state(): void
    {
        $this->execute(function () {
            $state = yield $this->projectionsManager->getStateAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($state);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_be_able_to_get_the_projection_status(): void
    {
        $this->execute(function () {
            $status = yield $this->projectionsManager->getStatusAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($status);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_be_able_to_get_the_projection_result(): void
    {
        $this->execute(function () {
            $result = yield $this->projectionsManager->getResultAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertEquals('{"count":1}', $result);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_be_able_to_get_the_projection_query(): void
    {
        $this->execute(function () {
            $query = yield $this->projectionsManager->getQueryAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertSame($this->query, $query);
        });
    }
}
