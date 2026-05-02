<?php

declare(strict_types=1);

namespace App\Client;

/**
 * Minimal Redis interface used for cache reads and writes.
 */
interface RedisClientInterface
{
    /**
     * Returns the cached value for the given key, or null if absent.
     *
     * @param string $key
     * @return string|null
     */
    public function get(string $key): ?string;

    /**
     * Stores a value under the given key with a TTL in seconds.
     *
     * @param string $key
     * @param int    $seconds Time to live in seconds
     * @param string $value
     */
    public function setex(string $key, int $seconds, string $value): void;
}