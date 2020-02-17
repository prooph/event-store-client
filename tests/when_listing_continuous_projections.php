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

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Util\Guid;
use Throwable;

class when_listing_continuous_projections extends TestCase
{
    use ProjectionSpecification;

    /** @var ProjectionDetails[] */
    private array $result;
    private string $projectionName;

    protected function given(): Generator
    {
        $this->projectionName = Guid::generateAsHex();
        yield $this->createContinuousProjection($this->projectionName);
    }

    protected function when(): Generator
    {
        $this->result = yield $this->projectionsManager->listContinuousAsync($this->credentials);
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_return_continuous_projections(): void
    {
        $this->execute(function () {
            $found = false;

            foreach ($this->result as $value) {
                if ($value->effectiveName() === $this->projectionName) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found);

            yield new Success();
        });
    }
}
