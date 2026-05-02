<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Thrown when a requested Pokémon cannot be found in the PokéAPI.
 */
class PokemonNotFoundException extends RuntimeException
{
    /**
     * @param string $pokemonName The name of the Pokémon that was not found
     */
    public function __construct(string $pokemonName)
    {
        parent::__construct("Pokemon '{$pokemonName}' not found.");
    }
}