<?php

declare(strict_types=1);

namespace App\Model;

class Pokemon
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $habitat,
        public readonly bool $isLegendary,
    ) {}

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'habitat'     => $this->habitat,
            'isLegendary' => $this->isLegendary,
        ];
    }
}