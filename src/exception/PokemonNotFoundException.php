<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class PokemonNotFoundException extends RuntimeException
{
    public function __construct(string $pokemonName)
    {
        parent::__construct("Pokemon '{$pokemonName}' not found.");
    }
}