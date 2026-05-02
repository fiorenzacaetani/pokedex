<?php

declare(strict_types=1);

namespace App\Controller;

trait ValidatesPokemonNameTrait
{
    /**
     * Returns true if the name contains only lowercase letters and hyphens.
     * Valid Pokémon names consist exclusively of the characters a–z and the hyphen (e.g. mr-mime).
     */
    private function validatePokemonName(string $pokemonName): bool
    {
        return preg_match('/^[a-z\-]+$/', $pokemonName) === 1;
    }
}
