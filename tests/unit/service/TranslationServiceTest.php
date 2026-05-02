<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Client\FunTranslationsClient;
use App\Client\RedisClientInterface;
use App\Exception\TranslationUnavailableException;
use App\Model\Pokemon;
use App\Service\TranslationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for TranslationService.
 */
class TranslationServiceTest extends TestCase
{
    private FunTranslationsClient&MockObject $translationsClient;
    private RedisClientInterface&MockObject $redis;
    private LoggerInterface&MockObject $logger;
    private TranslationService $service;

    /**
     * Initialises mocks and constructs the service under test.
     */
    protected function setUp(): void
    {
        $this->translationsClient = $this->createMock(FunTranslationsClient::class);
        $this->redis              = $this->createMock(RedisClientInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->service            = new TranslationService(
            $this->translationsClient,
            $this->redis,
            $this->logger,
        );
    }

    /**
     * Helper that builds a Pokemon instance for use in test cases.
     *
     * @param string      $name
     * @param string      $description
     * @param string|null $habitat
     * @param bool        $isLegendary
     * @return Pokemon
     */
    private function makePokemon(string $name, string $description, ?string $habitat, bool $isLegendary): Pokemon
    {
        return new Pokemon(
            name:        $name,
            description: $description,
            habitat:     $habitat,
            isLegendary: $isLegendary,
        );
    }

    /** Verifies that a cache hit returns the stored translation without calling the API. */
    public function test_returns_cached_translation_without_calling_api(): void
    {
        $this->redis->method('get')->willReturn('Cached yoda translation.');

        $this->translationsClient->expects($this->never())->method('translateYoda');
        $this->translationsClient->expects($this->never())->method('translateShakespeare');

        $pokemon = $this->makePokemon('mewtwo', 'Some description.', 'rare', true);
        $result  = $this->service->translate($pokemon);

        $this->assertSame('Cached yoda translation.', $result);
    }

    /** Verifies that legendary Pokémon receive a Yoda translation. */
    public function test_applies_yoda_translation_for_legendary_pokemon(): void
    {
        $this->redis->method('get')->willReturn(null);

        $this->translationsClient
            ->expects($this->once())
            ->method('translateYoda')
            ->willReturn('Created by a scientist, it was.');

        $pokemon = $this->makePokemon('mewtwo', 'It was created by a scientist.', 'rare', true);
        $result  = $this->service->translate($pokemon);

        $this->assertSame('Created by a scientist, it was.', $result);
    }

    /** Verifies that Pokémon with a cave habitat receive a Yoda translation. */
    public function test_applies_yoda_translation_for_cave_habitat_pokemon(): void
    {
        $this->redis->method('get')->willReturn(null);

        $this->translationsClient
            ->expects($this->once())
            ->method('translateYoda')
            ->willReturn('Yoda translation of gengar.');

        $pokemon = $this->makePokemon('gengar', 'Some description.', 'cave', false);
        $result  = $this->service->translate($pokemon);

        $this->assertSame('Yoda translation of gengar.', $result);
    }

    /** Verifies that regular (non-legendary, non-cave) Pokémon receive a Shakespeare translation. */
    public function test_applies_shakespeare_translation_for_regular_pokemon(): void
    {
        $this->redis->method('get')->willReturn(null);

        $this->translationsClient
            ->expects($this->once())
            ->method('translateShakespeare')
            ->willReturn('Shakespeare translation of bulbasaur.');

        $pokemon = $this->makePokemon('bulbasaur', 'Some description.', 'grassland', false);
        $result  = $this->service->translate($pokemon);

        $this->assertSame('Shakespeare translation of bulbasaur.', $result);
    }

    /** Verifies that the standard description is returned when the translation API is unavailable. */
    public function test_falls_back_to_standard_description_when_translation_unavailable(): void
    {
        $this->redis->method('get')->willReturn(null);

        $this->translationsClient
            ->method('translateYoda')
            ->willThrowException(new TranslationUnavailableException('rate limit exceeded'));

        $this->logger->expects($this->once())->method('warning');

        $pokemon = $this->makePokemon('mewtwo', 'Standard description.', 'rare', true);
        $result  = $this->service->translate($pokemon);

        $this->assertSame('Standard description.', $result);
    }

    /** Verifies that a Redis read failure falls through to the API and logs a warning. */
    public function test_proceeds_without_cache_and_logs_warning_when_redis_read_fails(): void
    {
        $this->redis->method('get')->willThrowException(new \RuntimeException('Redis down'));

        $this->translationsClient
            ->method('translateShakespeare')
            ->willReturn('Shakespeare translation.');

        $this->logger->expects($this->once())->method('warning');

        $pokemon = $this->makePokemon('bulbasaur', 'Some description.', 'grassland', false);
        $result  = $this->service->translate($pokemon);

        $this->assertSame('Shakespeare translation.', $result);
    }

    /** Verifies that a Redis write failure logs a warning but still returns the translation. */
    public function test_logs_warning_when_redis_write_fails(): void
    {
        $this->redis->method('get')->willReturn(null);
        $this->redis->method('setex')->willThrowException(new \RuntimeException('Redis down'));

        $this->translationsClient
            ->method('translateShakespeare')
            ->willReturn('Shakespeare translation.');

        $this->logger->expects($this->once())->method('warning');

        $pokemon = $this->makePokemon('bulbasaur', 'Some description.', 'grassland', false);
        $result  = $this->service->translate($pokemon);

        $this->assertSame('Shakespeare translation.', $result);
    }
}