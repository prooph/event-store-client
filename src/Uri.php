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

namespace Prooph\EventStoreClient;

use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\UserCredentials;

/** @psalm-immutable */
class Uri
{
    /**
     * Sub-delimiters used in user info, query strings and fragments.
     */
    private const CharSubDelims = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters used in user info, paths, query strings, and fragments.
     */
    private const CharUnreserved = 'a-zA-Z0-9_\-\.~\pL';

    private const DefaultTcpPort = 1113;

    public function __construct(
        private readonly string $scheme,
        private readonly string $host,
        private readonly int $port,
        private readonly ?UserCredentials $userCredentials = null
    ) {
    }

    public static function fromString(string $uri): self
    {
        $parts = \parse_url($uri);

        if (false === $parts) {
            throw new InvalidArgumentException(
                'The source URI string appears to be malformed'
            );
        }

        $scheme = isset($parts['scheme']) ? self::filterScheme($parts['scheme']) : '';
        $host = isset($parts['host']) ? \strtolower($parts['host']) : '';
        $port = isset($parts['port']) ? (int) $parts['port'] : self::DefaultTcpPort;
        $userCredentials = null;

        if (isset($parts['user'])) {
            $user = self::filterUserInfoPart($parts['user']);
            $pass = $parts['pass'] ?? '';

            $userCredentials = new UserCredentials($user, $pass);
        }

        return new self($scheme, $host, $port, $userCredentials);
    }

    public function scheme(): string
    {
        return $this->scheme;
    }

    public function userCredentials(): ?UserCredentials
    {
        return $this->userCredentials;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    /** @psalm-pure */
    private static function filterScheme(string $scheme): string
    {
        return \preg_replace('#:(//)?$#', '', \strtolower($scheme));
    }

    /** @psalm-pure */
    private static function filterUserInfoPart(string $part): string
    {
        // Note the addition of `%` to initial charset; this allows `|` portion
        // to match and thus prevent double-encoding.
        return \preg_replace_callback(
            '/(?:[^%' . self::CharUnreserved . self::CharSubDelims . ']+|%(?![A-Fa-f0-9]{2}))/u',
            fn (array $matches): string => \rawurlencode((string) $matches[0]),
            $part
        );
    }
}
