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

namespace Prooph\EventStoreClient\UserManagement;

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\ChangePasswordDetails;
use Prooph\EventStore\UserManagement\ResetPasswordDetails;
use Prooph\EventStore\UserManagement\UserCreationInformation;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\UserManagement\UsersManager as UsersManagerInterface;
use Prooph\EventStore\UserManagement\UserUpdateInformation;

class UsersManager implements UsersManagerInterface
{
    private readonly UsersClient $client;

    private readonly EndPoint $endPoint;

    private readonly ?UserCredentials $defaultCredentials;

    public function __construct(
        EndPoint $endPoint,
        int $operationTimeout,
        bool $tlsTerminatedEndpoint = false,
        bool $verifyPeer = true,
        ?UserCredentials $userCredentials = null
    ) {
        $this->client = new UsersClient($operationTimeout, $tlsTerminatedEndpoint, $verifyPeer);
        $this->endPoint = $endPoint;
        $this->defaultCredentials = $userCredentials;
    }

    public function enable(string $login, ?UserCredentials $userCredentials = null): void
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        $this->client->enable(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials
        );
    }

    public function disable(string $login, ?UserCredentials $userCredentials = null): void
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        $this->client->disable(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials
        );
    }

    /** @inheritdoc */
    public function deleteUser(string $login, ?UserCredentials $userCredentials = null): void
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        $this->client->delete(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials
        );
    }

    /** @inheritdoc */
    public function listAll(?UserCredentials $userCredentials = null): array
    {
        return $this->client->listAll(
            $this->endPoint,
            $userCredentials ?? $this->defaultCredentials
        );
    }

    public function getCurrentUser(?UserCredentials $userCredentials = null): UserDetails
    {
        return $this->client->getCurrentUser(
            $this->endPoint,
            $userCredentials ?? $this->defaultCredentials
        );
    }

    public function getUser(string $login, ?UserCredentials $userCredentials = null): UserDetails
    {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        return $this->client->getUser(
            $this->endPoint,
            $login,
            $userCredentials ?? $this->defaultCredentials
        );
    }

    /** @inheritdoc */
    public function createUser(
        string $login,
        string $fullName,
        array $groups,
        string $password,
        ?UserCredentials $userCredentials = null
    ): void {
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
            if (empty($group)) {
                throw new InvalidArgumentException('Expected an array of non empty strings for group');
            }
        }

        $this->client->createUser(
            $this->endPoint,
            new UserCreationInformation(
                $login,
                $fullName,
                $groups,
                $password
            ),
            $userCredentials ?? $this->defaultCredentials
        );
    }

    /** @inheritdoc */
    public function updateUser(
        string $login,
        string $fullName,
        array $groups,
        ?UserCredentials $userCredentials = null
    ): void {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($fullName)) {
            throw new InvalidArgumentException('FullName cannot be empty');
        }

        foreach ($groups as $group) {
            if (empty($group)) {
                throw new InvalidArgumentException('Expected an array of non empty strings for group');
            }
        }

        $this->client->updateUser(
            $this->endPoint,
            $login,
            new UserUpdateInformation($fullName, $groups),
            $userCredentials ?? $this->defaultCredentials
        );
    }

    public function changePassword(
        string $login,
        string $oldPassword,
        string $newPassword,
        ?UserCredentials $userCredentials = null
    ): void {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($oldPassword)) {
            throw new InvalidArgumentException('Old password cannot be empty');
        }

        if (empty($newPassword)) {
            throw new InvalidArgumentException('New password cannot be empty');
        }

        $this->client->changePassword(
            $this->endPoint,
            $login,
            new ChangePasswordDetails($oldPassword, $newPassword),
            $userCredentials ?? $this->defaultCredentials
        );
    }

    public function resetPassword(
        string $login,
        string $newPassword,
        ?UserCredentials $userCredentials = null
    ): void {
        if (empty($login)) {
            throw new InvalidArgumentException('Login cannot be empty');
        }

        if (empty($newPassword)) {
            throw new InvalidArgumentException('New password cannot be empty');
        }

        $this->client->resetPassword(
            $this->endPoint,
            $login,
            new ResetPasswordDetails($newPassword),
            $userCredentials ?? $this->defaultCredentials
        );
    }
}
