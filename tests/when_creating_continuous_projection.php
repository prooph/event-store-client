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
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Util\Guid;

class when_creating_continuous_projection extends AsyncTestCase
{
    use ProjectionSpecification;

    private string $projectionName;

    private string $streamName;

    private string $emittedStreamName;

    private string $query;

    private string $projectionId;

    public function given(): void
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_creating_transient_projection-' . $id;
        $this->streamName = 'test-stream-' . $id;
        $this->emittedStreamName = 'emittedStream-' . $id;

        $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $this->query = $this->createStandardQuery($this->streamName);
    }

    protected function when(): void
    {
        $this->projectionsManager->createContinuous(
            $this->projectionName,
            $this->query,
            false,
            'JS',
            $this->credentials
        );
    }

    /** @test */
    public function should_create_projection(): string
    {
        $this->execute(function (): void {
            /** @var ProjectionDetails[] $allProjections */
            $allProjections = $this->projectionsManager->listContinuous($this->credentials);

            $found = false;

            foreach ($allProjections as $projection) {
                if ($projection->effectiveName() === $this->projectionName) {
                    $this->projectionId = $projection->name();
                    $found = true;

                    break;
                }
            }

            $this->assertTrue($found);
            $this->assertNotNull($this->projectionId);
        });

        return $this->projectionId;
    }

    /**
     * @test
     * @depends should_create_projection
     */
    public function should_have_turn_on_emit_to_stream(string $projectionId): void
    {
        $this->execute(function () use ($projectionId): void {
            $event = $this->connection->readEvent(
                '$projections-' . $projectionId,
                0,
                true,
                $this->credentials
            );
            $data = $event->event()->event()->data();
            $eventData = \json_decode($data, true);
            $this->assertTrue((bool) $eventData['emitEnabled']);
        });
    }
}
