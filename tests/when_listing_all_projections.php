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

class when_listing_all_projections extends AsyncTestCase
{
    use ProjectionSpecification;

    /** @var ProjectionDetails[] */
    private array $result;

    protected function when(): void
    {
        $this->result = $this->projectionsManager->listAll($this->credentials);
    }

    /** @test */
    public function should_return_all_projections(): void
    {
        $this->execute(function (): void {
            $this->assertNotEmpty($this->result);
        });
    }
}
