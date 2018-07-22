<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class UserCredentials
{
    /** @var string */
    private $username;
    /** @var string */
    private $password;

    public function __construct(string $username, string $password)
    {
        if (empty($username)) {
            throw new \InvalidArgumentException('Username cannot be empty');
        }

        $this->username = $username;
        $this->password = $password;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }
}
