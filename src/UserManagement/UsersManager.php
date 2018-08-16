<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\UserManagement;

use Amp\Promise;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\UserCredentials;

class UsersManager
{
    /** @var UsersClient */
    private $client;
    /** @var IpEndPoint */
    private $endPoint;
    /** @var string */
    private $schema;

    public function __construct(
        IpEndPoint $endPoint,
        int $operationTimeout,
        string $schema = EndpointExtensions::HttpSchema
    ) {
        $this->client = new UsersClient($operationTimeout);
        $this->endPoint = $endPoint;
        $this->schema = $schema;
    }

    public function enableAsync(string $login, UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->enable($this->endPoint, $login, $userCredentials, $this->schema);
    }

    public function disableAsync(string $login, UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->disable($this->endPoint, $login, $userCredentials, $this->schema);
    }

    /** @throws UserCommandFailedException */
    public function deleteUserAsync(string $login, UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->delete($this->endPoint, $login, $userCredentials, $this->schema);
    }
}
