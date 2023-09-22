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
use Prooph\EventStore\Util\Guid;

class when_creating_one_time_projection extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $streamName;

    private string $query;

    public function given(): void
    {
        $id = Guid::generateAsHex();
        $this->streamName = 'test-stream-' . $id;

        $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $this->query = $this->createStandardQuery($this->streamName);
    }

    protected function when(): void
    {
        $this->projectionsManager->createOneTime(
            $this->query,
            'JS',
            $this->credentials
        );
    }

    /** @test */
    public function should_create_projection(): void
    {
        $this->execute(function (): void {
            $projections = $this->projectionsManager->listOneTime(
                $this->credentials
            );

            $this->assertCount(1, $projections);
        });
    }
}
