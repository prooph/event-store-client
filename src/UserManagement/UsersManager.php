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

namespace Prooph\EventStoreClient\UserManagement;

use Amp\Promise;
use Prooph\EventStore\Async\UserManagement\UsersManager as AsyncUsersManager;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Transport\Http\EndpointExtensions;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\ChangePasswordDetails;
use Prooph\EventStore\UserManagement\ResetPasswordDetails;
use Prooph\EventStore\UserManagement\UserCreationInformation;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\UserManagement\UserUpdateInformation;
use Prooph\EventStoreClient\Exception\UserCommandFailed;

class UsersManager implements AsyncUsersManager
{
    private UsersClient $client;
    private EndPoint $endPoint;
    private string $schema;
    private ?UserCredentials $defaultCredentials;

    public function __construct(
        EndPoint $endPoint,
        int $operationTimeout,
        string $schema = EndpointExtensions::HTTP_SCHEMA,
        ?UserCredentials $userCredentials = null
    ) {
        $this->client = new UsersClient($operationTimeout);
        $this->endPoint = $endPoint;
        $this->schema = $schema;
        $this->defaultCredentials = $userCredentials;
    }

    public function enableAsync(string $login, ?UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->enable(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }

    public function disableAsync(string $login, ?UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->disable(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }

    /** @throws UserCommandFailed */
    public function deleteUserAsync(string $login, ?UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->delete(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }

    /** @return Promise<UserDetails[]> */
    public function listAllAsync(?UserCredentials $userCredentials = null): Promise
    {
        return $this->client->listAll(
            $this->endPoint,
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }

    /** @return Promise<UserDetails> */
    public function getCurrentUserAsync(?UserCredentials $userCredentials = null): Promise
    {
        return $this->client->getCurrentUser(
            $this->endPoint,
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }

    /** @return Promise<UserDetails> */
    public function getUserAsync(string $login, ?UserCredentials $userCredentials = null): Promise
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->getUser(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
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
        ?UserCredentials $userCredentials = null
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

        return $this->client->createUser(
            $this->endPoint,
            new UserCreationInformation(
                $login,
                $fullName,
                $groups,
                $password
            ),
            $userCredentials ?? $this->defaultCredentials,
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
        ?UserCredentials $userCredentials = null
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

        return $this->client->updateUser(
            $this->endPoint,
            $login,
            new UserUpdateInformation($fullName, $groups),
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }

    public function changePasswordAsync(
        string $login,
        string $oldPassword,
        string $newPassword,
        ?UserCredentials $userCredentials = null
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

        return $this->client->changePassword(
            $this->endPoint,
            $login,
            new ChangePasswordDetails($oldPassword, $newPassword),
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }

    public function resetPasswordAsync(
        string $login,
        string $newPassword,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($newPassword)) {
            throw new InvalidArgumentException('New password cannot be empty');
        }

        return $this->client->resetPassword(
            $this->endPoint,
            $login,
            new ResetPasswordDetails($newPassword),
            $userCredentials ?? $this->defaultCredentials,
            $this->schema
        );
    }
}
