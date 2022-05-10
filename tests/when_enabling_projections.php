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
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Util\Guid;

class when_enabling_projections extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $projectionName;

    private string $streamName;

    private string $query;

    public function given(): void
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_enabling_projections-' . $id;
        $this->streamName = 'test-stream-' . $id;

        $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $this->query = $this->createStandardQuery($this->streamName);

        $this->projectionsManager->createContinuous(
            $this->projectionName,
            $this->query,
            false,
            'JS',
            $this->credentials
        );

        $this->projectionsManager->disable(
            $this->projectionName,
            $this->credentials
        );
    }

    protected function when(): void
    {
        $this->projectionsManager->enable(
            $this->projectionName,
            $this->credentials
        );
    }

    /** @test */
    public function should_run_the_projection(): void
    {
        $this->execute(function (): void {
            /** @var ProjectionDetails $projectionStatus */
            $projectionStatus = $this->projectionsManager->getStatus(
                $this->projectionName,
                $this->credentials
            );

            $this->assertSame('Running', $projectionStatus->status());
        });
    }
}
