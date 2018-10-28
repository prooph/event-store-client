<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Projections\ProjectionDetails;
use Throwable;

class when_listing_one_time_projections extends TestCase
{
    use ProjectionSpecification;

    /** @var ProjectionDetails[] */
    private $result;

    protected function given(): Generator
    {
        yield $this->createOneTimeProjection('JS');
    }

    protected function when(): Generator
    {
        $this->result = yield $this->projectionsManager->listOneTimeAsync($this->credentials);
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_return_continuous_projections(): void
    {
        $this->execute(function () {
            $this->assertNotEmpty($this->result);

            yield new Success();
        });
    }
}
