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

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Common\SystemMetadata;

class StreamMetadata
{
    /**
     * The maximum number of events allowed in the stream.
     * @var int|null
     */
    private $maxCount;

    /**
     * The maximum age in seconds for events allowed in the stream.
     * @var int|null
     */
    private $maxAge;

    /**
     * The event number from which previous events can be scavenged.
     * This is used to implement soft-deletion of streams.
     * @var int|null
     */
    private $truncateBefore;

    /**
     * The amount of time in seconds for which the stream head is cachable.
     * @var int|null
     */
    private $cacheControl;

    /**
     * The access control list for the stream.
     * @var StreamAcl|null
     */
    private $acl;

    /**
     * key => value pairs of custom metadata
     * @var array
     */
    private $customMetadata;

    public function __construct(
        ?int $maxCount,
        ?int $maxAge,
        ?int $truncateBefore,
        ?int $cacheControl,
        ?StreamAcl $acl,
        array $customMetadata = []
    ) {
        if (null !== $maxCount && $maxCount <= 0) {
            throw new \InvalidArgumentException('maxCount should be positive value');
        }

        if (null !== $maxAge && $maxAge < 1) {
            throw new \InvalidArgumentException('maxAge should be positive value');
        }

        if (null !== $truncateBefore && $truncateBefore < 0) {
            throw new \InvalidArgumentException('truncateBefore should be non-negative value');
        }

        if (null !== $cacheControl && $cacheControl < 1) {
            throw new \InvalidArgumentException('cacheControl should be positive value');
        }

        $this->maxCount = $maxCount;
        $this->maxAge = $maxAge;
        $this->truncateBefore = $truncateBefore;
        $this->cacheControl = $cacheControl;
        $this->acl = $acl;
        $this->customMetadata = $customMetadata;
    }

    public function maxCount(): ?int
    {
        return $this->maxCount;
    }

    public function maxAge(): ?int
    {
        return $this->maxAge;
    }

    public function truncateBefore(): ?int
    {
        return $this->truncateBefore;
    }

    public function cacheControl(): ?int
    {
        return $this->cacheControl;
    }

    public function acl(): ?StreamAcl
    {
        return $this->acl;
    }

    /**
     * @return array
     */
    public function customMetadata(): array
    {
        return $this->customMetadata;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getValue(string $key)
    {
        if (! isset($this->customMetadata[$key])) {
            throw new \InvalidArgumentException('Key ' . $key . ' not found in custom metadata');
        }

        return $this->customMetadata[$key];
    }

    public function toArray(): array
    {
        $data = [];

        if (null !== $this->maxCount) {
            $data[SystemMetadata::MaxCount] = $this->maxCount;
        }

        if (null !== $this->maxAge) {
            $data[SystemMetadata::MaxAge] = $this->maxAge;
        }

        if (null !== $this->truncateBefore) {
            $data[SystemMetadata::TruncateBefore] = $this->truncateBefore;
        }

        if (null !== $this->cacheControl) {
            $data[SystemMetadata::CacheControl] = $this->cacheControl;
        }

        if (null !== $this->acl) {
            $data[SystemMetadata::Acl] = $this->acl->toArray();
        }

        foreach ($this->customMetadata as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    public static function fromArray(array $data): StreamMetadata
    {
        $internal = [
            SystemMetadata::MaxCount,
            SystemMetadata::MaxAge,
            SystemMetadata::TruncateBefore,
            SystemMetadata::CacheControl,
        ];

        $params = [];

        foreach ($data as $key => $value) {
            if (\in_array($key, $internal, true)) {
                $params[$key] = $value;
            } elseif ($key === SystemMetadata::Acl) {
                $params[SystemMetadata::Acl] = StreamAcl::fromArray($value);
            } else {
                $params['customMetadata'][$key] = $value;
            }
        }

        return new self(
            $params[SystemMetadata::MaxCount] ?? null,
            $params[SystemMetadata::MaxAge] ?? null,
            $params[SystemMetadata::TruncateBefore] ?? null,
            $params[SystemMetadata::CacheControl] ?? null,
            $params[SystemMetadata::Acl] ?? null,
            $params['customMetadata'] ?? []
        );
    }
}
