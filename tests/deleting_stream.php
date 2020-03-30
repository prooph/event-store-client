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

use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;

class deleting_stream extends AsyncTestCase
{
    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_empty_stream_expected_version(): Generator
    {
        $stream = 'which_already_exists_should_success_when_passed_empty_stream_expected_version';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        yield $connection->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_any_for_expected_version(): Generator
    {
        $stream = 'which_already_exists_should_success_when_passed_any_for_expected_version';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        yield $connection->deleteStreamAsync($stream, ExpectedVersion::ANY, true);
    }

    /**
     * @test
     */
    public function with_invalid_expected_version_should_fail(): Generator
    {
        $stream = 'with_invalid_expected_version_should_fail';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $this->expectException(WrongExpectedVersion::class);
        yield $connection->deleteStreamAsync($stream, 1, true);
    }

    /**
     * @test
     */
    public function should_return_log_position_when_writing(): Generator
    {
        $stream = 'delete_should_return_log_position_when_writing';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);

        $delete = yield $connection->deleteStreamAsync($stream, 0, true);
        \assert($delete instanceof DeleteResult);

        $this->assertGreaterThan(0, $delete->logPosition()->preparePosition());
        $this->assertGreaterThan(0, $delete->logPosition()->commitPosition());
    }

    /**
     * @test
     */
    public function which_was_already_deleted_should_fail(): Generator
    {
        $stream = 'which_was_allready_deleted_should_fail';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        yield $connection->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);

        $this->expectException(StreamDeleted::class);
        yield $connection->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);
    }
}
