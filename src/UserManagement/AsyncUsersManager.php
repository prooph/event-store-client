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

class AsyncUsersManager
{
    /** @var UsersClient */
    private $client;
    /** @var IpEndPoint */
    private $endPoint;
    /** @var string */
    private $schema;
    /** @var UserCredentials|null */
    private $defaultCredentials;

    public function __construct(
        IpEndPoint $endPoint,
        int $operationTimeout,
        string $schema = EndpointExtensions::HTTP_SCHEMA,
        UserCredentials $userCredentials = null
    ) {
        $this->client = new UsersClient($operationTimeout);
        $this->endPoint = $endPoint;
        $this->schema = $schema;
        $this->defaultCredentials = $userCredentials;
    }

    public function enableAsync(string $login, UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->enable($this->endPoint, $login, $userCredentials, $this->schema);
    }

    public function disableAsync(string $login, UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->disable($this->endPoint, $login, $userCredentials, $this->schema);
    }

    /** @throws UserCommandFailedException */
    public function deleteUserAsync(string $login, UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->delete($this->endPoint, $login, $userCredentials, $this->schema);
    }

    /** @return Promise<UserDetails[]> */
    public function listAllAsync(UserCredentials $userCredentials = null): Promise
    {
        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->listAll($this->endPoint, $userCredentials, $this->schema);
    }

    /** @return Promise<UserDetails> */
    public function getCurrentUserAsync(UserCredentials $userCredentials): Promise
    {
        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->getCurrentUser($this->endPoint, $userCredentials, $this->schema);
    }

    /** @return Promise<UserDetails> */
    public function getUserAsync(string $login, UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->getUser($this->endPoint, $login, $userCredentials, $this->schema);
    }

    /**
     * @param string $login
     * @param string $fullName
     * @param string[] $groups
     * @param string $password
     * @param UserCredentials|null $userCredentials
     * @return Promise
     */
    public function createUserAsync(
        string $login,
        string $fullName,
        array $groups,
        string $password,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($fullName)) {
            throw new InvalidArgumentException('FullName cannot be empty');
        }

        if (empty($password)) {
            throw new InvalidArgumentException('Password cannot be empty');
        }

        foreach ($groups as $group) {
            if (! \is_string($group) || empty($group)) {
                throw new InvalidArgumentException('Expected an array of non empty strings for group');
            }
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->createUser(
            $this->endPoint,
            new UserCreationInformation(
                $login,
                $fullName,
                $groups,
                $password
            ),
            $userCredentials,
            $this->schema
        );
    }

    /**
     * @param string $login
     * @param string $fullName
     * @param string[] $groups
     * @param UserCredentials|null $userCredentials
     * @return Promise
     */
    public function updateUserAsync(
        string $login,
        string $fullName,
        array $groups,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($fullName)) {
            throw new InvalidArgumentException('FullName cannot be empty');
        }

        foreach ($groups as $group) {
            if (! \is_string($group) || empty($group)) {
                throw new InvalidArgumentException('Expected an array of non empty strings for group');
            }
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->updateUser(
            $this->endPoint,
            $login,
            new UserUpdateInformation($fullName, $groups),
            $userCredentials,
            $this->schema
        );
    }

    public function changePasswordAsync(
        string $login,
        string $oldPassword,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($oldPassword)) {
            throw new InvalidArgumentException('Old password cannot be empty');
        }

        if (empty($newPassword)) {
            throw new InvalidArgumentException('New password cannot be empty');
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->changePassword(
            $this->endPoint,
            $login,
            new ChangePasswordDetails($oldPassword, $newPassword),
            $userCredentials,
            $this->schema
        );
    }

    public function resetPasswordAsync(
        string $login,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($newPassword)) {
            throw new InvalidArgumentException('New password cannot be empty');
        }

        $userCredentials = $userCredentials ?? $this->defaultCredentials;

        return $this->client->resetPassword(
            $this->endPoint,
            $login,
            new ResetPasswordDetails($newPassword),
            $userCredentials,
            $this->schema
        );
    }
}
