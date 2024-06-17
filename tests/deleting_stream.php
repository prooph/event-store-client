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

use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\TestEvent;

class deleting_stream extends EventStoreConnectionTestCase
{
    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_empty_stream_expected_version(): void
    {
        $stream = 'which_already_exists_should_success_when_passed_empty_stream_expected_version';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_any_for_expected_version(): void
    {
        $stream = 'which_already_exists_should_success_when_passed_any_for_expected_version';

        $this->connection->deleteStream($stream, ExpectedVersion::Any, true);
    }

    /** @test */
    public function with_invalid_expected_version_should_fail(): void
    {
        $stream = 'with_invalid_expected_version_should_fail';

        $this->expectException(WrongExpectedVersion::class);
        $this->connection->deleteStream($stream, 1, true);
    }

    /** @test */
    public function should_return_log_position_when_writing(): void
    {
        $stream = 'delete_should_return_log_position_when_writing';

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);

        $delete = $this->connection->deleteStream($stream, 0, true);

        $this->assertGreaterThan(0, $delete->logPosition()->preparePosition());
        $this->assertGreaterThan(0, $delete->logPosition()->commitPosition());
    }

    /** @test */
    public function which_was_already_deleted_should_fail(): void
    {
        $stream = 'which_was_allready_deleted_should_fail';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        $this->expectException(StreamDeleted::class);
        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);
    }
}
