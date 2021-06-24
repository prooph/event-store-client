<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Success;
use Generator;
use Prooph\EventStore\Projections\ProjectionDetails;

class when_listing_one_time_projections extends AsyncTestCase
{
    use ProjectionSpecification;

    /** @var ProjectionDetails[] */
    private array $result;

    protected function given(): Generator
    {
        yield $this->createOneTimeProjection();
    }

    protected function when(): Generator
    {
        $this->result = yield $this->projectionsManager->listOneTimeAsync($this->credentials);
    }

    /** @test */
    public function should_return_continuous_projections(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertNotEmpty($this->result);

            yield new Success();
        });
    }
}
