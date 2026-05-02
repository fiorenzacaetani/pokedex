<?php

declare(strict_types=1);

namespace App\Service;

use App\Client\PokeApiClient;
use App\Exception\PokemonNotFoundException;
use App\Helper\FlavorTextExtractor;
use App\Model\Pokemon;
use Psr\Log\LoggerInterface;
use App\Client\RedisClientInterface;

/**
 * Retrieves Pokémon data from the PokéAPI and caches results in Redis.
 */
class PokemonService
{
    /** Cache TTL in seconds (1 hour) */
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'pokemon:';

    /**
     * @param PokeApiClient        $pokeApiClient
     * @param FlavorTextExtractor  $flavorTextExtractor
     * @param LoggerInterface      $logger
     * @param RedisClientInterface $redis
     */
    public function __construct(
        private readonly PokeApiClient $pokeApiClient,
        private readonly FlavorTextExtractor $flavorTextExtractor,
        private readonly LoggerInterface $logger,
        private readonly RedisClientInterface $redis,
    ) {}

    /**
     * Returns the Pokémon with the given name, using the Redis cache when available.
     * Falls back to the PokéAPI on a cache miss and persists the result.
     *
     * @param string $pokemonName
     * @return Pokemon
     * @throws PokemonNotFoundException
     */
    public function getByName(string $pokemonName): Pokemon
    {
        $cacheKey = self::CACHE_PREFIX . $pokemonName;

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $species = $this->pokeApiClient->fetchSpecies($pokemonName);

        $description = $this->flavorTextExtractor->extract(
            $species['flavor_text_entries'],
            $pokemonName
        );

        if ($description === null) {
            $this->logger->warning('No valid flavor text found for Pokemon', [
                'pokemon' => $pokemonName,
            ]);
            $description = '';
        }

        $pokemon = new Pokemon(
            name:        $species['name'],
            description: $description,
            habitat:     $species['habitat']['name'] ?? null,
            isLegendary: $species['is_legendary'],
        );

        $this->saveToCache($cacheKey, $pokemon);

        return $pokemon;
    }

    /**
     * Returns a hydrated Pokemon from the Redis cache, or null on miss or error.
     *
     * @param string $key
     * @return Pokemon|null
     */
    private function getFromCache(string $key): ?Pokemon
    {
        try {
            $data = $this->redis->get($key);
            if ($data === null) {
                return null;
            }
            $decoded = json_decode($data, true);
            return new Pokemon(
                name:        $decoded['name'],
                description: $decoded['description'],
                habitat:     $decoded['habitat'],
                isLegendary: $decoded['isLegendary'],
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Redis read failed in PokemonService, proceeding without cache', [
                'key'     => $key,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Persists a Pokemon to the Redis cache as JSON.
     * Logs a warning and silently continues if the write fails.
     *
     * @param string  $key
     * @param Pokemon $pokemon
     */
    private function saveToCache(string $key, Pokemon $pokemon): void
    {
        try {
            $this->redis->setex($key, self::CACHE_TTL, json_encode($pokemon->toArray()));
        } catch (\Throwable $e) {
            $this->logger->warning('Redis write failed in PokemonService, cache not updated', [
                'key'     => $key,
                'message' => $e->getMessage(),
            ]);
        }
    }
}