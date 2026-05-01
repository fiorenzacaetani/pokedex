<?php

declare(strict_types=1);

namespace App\Service;

use App\Client\FunTranslationsClient;
use App\Client\RedisClientInterface;
use App\Exception\TranslationUnavailableException;
use App\Model\Pokemon;
use Psr\Log\LoggerInterface;

class TranslationService
{
    /** Cache TTL in seconds (1 hour) */
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'translation:';

    public function __construct(
        private readonly FunTranslationsClient $translationsClient,
        private readonly RedisClientInterface $redis,
        private readonly LoggerInterface $logger,
    ) {}

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

    private function cacheKeySuffix(Pokemon $pokemon): string
    {
        $translationType = ($pokemon->habitat === 'cave' || $pokemon->isLegendary) ? 'yoda' : 'shakespeare';
        return $pokemon->name . ':' . $translationType;
    }

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