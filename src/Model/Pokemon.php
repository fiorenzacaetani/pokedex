<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Represents a Pokémon with its basic attributes retrieved from the PokéAPI.
 */
class Pokemon
{
    /**
     * @param string      $name
     * @param string      $description  English flavor text
     * @param string|null $habitat      Habitat name from PokéAPI, or null if unavailable
     * @param bool        $isLegendary
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $habitat,
        public readonly bool $isLegendary,
    ) {}

    /**
     * Returns the Pokémon attributes as an associative array.
     *
     * @return array{name: string, description: string, habitat: string|null, isLegendary: bool}
     */
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