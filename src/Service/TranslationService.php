<?php

declare(strict_types=1);

namespace App\Service;

use App\Client\FunTranslationsClient;
use App\Client\RedisClientInterface;
use App\Exception\TranslationUnavailableException;
use App\Model\Pokemon;
use Psr\Log\LoggerInterface;

/**
 * Translates a Pokémon's description via the FunTranslations API and caches results in Redis.
 */
class TranslationService
{
    /** Cache TTL in seconds (1 hour) */
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'translation:';

    /**
     * @param FunTranslationsClient $translationsClient
     * @param RedisClientInterface  $redis
     * @param LoggerInterface       $logger
     */
    public function __construct(
        private readonly FunTranslationsClient $translationsClient,
        private readonly RedisClientInterface $redis,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns a fun-translated description for the given Pokémon.
     * Falls back to the standard description if the translation API is unavailable.
     *
     * @param Pokemon $pokemon
     * @return string
     */
    public function translate(Pokemon $pokemon): string
    {
        $cacheKey = self::CACHE_PREFIX . $this->cacheKeySuffix($pokemon);

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $translated = $this->callTranslationApi($pokemon);
            $this->saveToCache($cacheKey, $translated);
            return $translated;
        } catch (TranslationUnavailableException $e) {
            $this->logger->warning('Translation unavailable, falling back to standard description', [
                'pokemon' => $pokemon->name,
                'reason'  => $e->getMessage(),
            ]);
            return $pokemon->description;
        }
    }

    /**
     * Apply translation rules:
     * - Yoda: cave habitat or legendary
     * - Shakespeare: all others
     */
    private function callTranslationApi(Pokemon $pokemon): string
    {
        if ($pokemon->habitat === 'cave' || $pokemon->isLegendary) {
            return $this->translationsClient->translateYoda($pokemon->description);
        }

        return $this->translationsClient->translateShakespeare($pokemon->description);
    }

    /**
     * Builds the cache key suffix encoding the Pokémon name and translation type.
     *
     * @param Pokemon $pokemon
     * @return string
     */
    private function cacheKeySuffix(Pokemon $pokemon): string
    {
        $translationType = ($pokemon->habitat === 'cave' || $pokemon->isLegendary) ? 'yoda' : 'shakespeare';
        return $pokemon->name . ':' . $translationType;
    }

    /**
     * Returns the cached translation for the given key, or null on miss or error.
     *
     * @param string $key
     * @return string|null
     */
    private function getFromCache(string $key): ?string
    {
        try {
            return $this->redis->get($key);
        } catch (\Throwable $e) {
            $this->logger->warning('Redis read failed in TranslationService, proceeding without cache', [
                'key'     => $key,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Persists a translation string to the Redis cache.
     * Logs a warning and silently continues if the write fails.
     *
     * @param string $key
     * @param string $translation
     */
    private function saveToCache(string $key, string $translation): void
    {
        try {
            $this->redis->setex($key, self::CACHE_TTL, $translation);
        } catch (\Throwable $e) {
            $this->logger->warning('Redis write failed in TranslationService, cache not updated', [
                'key'     => $key,
                'message' => $e->getMessage(),
            ]);
        }
    }
}