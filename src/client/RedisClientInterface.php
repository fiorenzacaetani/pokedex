<?php

declare(strict_types=1);

namespace App\Client;

interface RedisClientInterface
{
    public function get(string $key): ?string;
    public function setex(string $key, int $seconds, string $value): void;
}