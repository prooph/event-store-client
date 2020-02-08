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

class when_creating_transient_projection extends TestCase
{
    use ProjectionSpecification;

    private string $projectionName;
    private string $streamName;
    private string $query;

    public function given(): Generator
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_creating_transient_projection-' . $id;
        $this->streamName = 'test-stream-' . $id;

        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $this->query = $this->createStandardQuery($this->streamName);
    }

    protected function when(): Generator
    {
        yield $this->projectionsManager->createTransientAsync(
            $this->projectionName,
            $this->query,
            'JS',
            $this->credentials
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_create_projection(): void
    {
        $this->execute(function () {
            $status = yield $this->projectionsManager->getStatusAsync(
                $this->projectionName,
                $this->credentials
            );

            $this->assertNotEmpty($status);
        });
    }
}
