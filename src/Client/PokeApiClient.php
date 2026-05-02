<?php

declare(strict_types=1);

namespace App\Client;

use App\Exception\PokemonNotFoundException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for the PokéAPI pokemon-species endpoint.
 */
class PokeApiClient
{
    private const BASE_URL = 'https://pokeapi.co/api/v2/pokemon-species/';

    /**
     * @param ClientInterface $httpClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetches raw species data for the given Pokémon name from the PokéAPI.
     *
     * @param string $pokemonName
     * @return array
     * @throws PokemonNotFoundException if the API returns 404
     * @throws \GuzzleHttp\Exception\ClientException for other HTTP errors
     */
    public function fetchSpecies(string $pokemonName): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $pokemonName);
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new PokemonNotFoundException($pokemonName);
            }
            $this->logger->error('PokeAPI request failed', [
                'pokemon' => $pokemonName,
                'status'  => $e->getResponse()->getStatusCode(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}