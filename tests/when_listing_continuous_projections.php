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

class when_listing_continuous_projections extends AsyncTestCase
{
    use ProjectionSpecification;

    /** @var ProjectionDetails[] */
    private array $result;

    private string $projectionName;

    protected function given(): void
    {
        $this->projectionName = Guid::generateAsHex();
        $this->createContinuousProjection($this->projectionName);
    }

    protected function when(): void
    {
        $this->result = $this->projectionsManager->listContinuous($this->credentials);
    }

    /** @test */
    public function should_return_continuous_projections(): void
    {
        $this->execute(function (): void {
            $found = false;

            foreach ($this->result as $value) {
                if ($value->effectiveName() === $this->projectionName) {
                    $found = true;

                    break;
                }
            }

            $this->assertTrue($found);
        });
    }
}
