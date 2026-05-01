<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Client\PokeApiClient;
use App\Exception\PokemonNotFoundException;
use App\Helper\FlavorTextExtractor;
use App\Model\Pokemon;
use App\Service\PokemonService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Client\RedisClientInterface;
use Psr\Log\LoggerInterface;


class PokemonServiceTest extends TestCase
{
    private PokeApiClient&MockObject $pokeApiClient;
    private FlavorTextExtractor&MockObject $flavorTextExtractor;
    private LoggerInterface&MockObject $logger;
    private RedisClientInterface&MockObject $redis;
    private PokemonService $service;

    protected function setUp(): void
    {
        $this->pokeApiClient       = $this->createMock(PokeApiClient::class);
        $this->flavorTextExtractor = $this->createMock(FlavorTextExtractor::class);
        $this->logger              = $this->createMock(LoggerInterface::class);
        $this->redis               = $this->createMock(RedisClientInterface::class);
        $this->service             = new PokemonService(
            $this->pokeApiClient,
            $this->flavorTextExtractor,
            $this->logger,
            $this->redis,
        );
    }

    public function test_returns_pokemon_from_cache_when_available(): void
    {
        $cached = json_encode([
            'name'        => 'mewtwo',
            'description' => 'Cached description.',
            'habitat'     => 'rare',
            'isLegendary' => true,
        ]);

        $this->redis
            ->expects($this->once())
            ->method('get')
            ->willReturn($cached);

        $this->pokeApiClient
            ->expects($this->never())
            ->method('fetchSpecies');

        $result = $this->service->getByName('mewtwo');

        $this->assertSame('mewtwo', $result->name);
        $this->assertSame('Cached description.', $result->description);
    }

    public function test_fetches_from_api_and_saves_to_cache_on_cache_miss(): void
    {
        $this->redis
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->redis
            ->expects($this->once())
            ->method('setex');

        $this->pokeApiClient
            ->expects($this->once())
            ->method('fetchSpecies')
            ->with('mewtwo')
            ->willReturn([
                'name'                => 'mewtwo',
                'is_legendary'        => true,
                'habitat'             => ['name' => 'rare'],
                'flavor_text_entries' => [],
            ]);

        $this->flavorTextExtractor
            ->method('extract')
            ->willReturn('It was created by a scientist.');

        $result = $this->service->getByName('mewtwo');

        $this->assertSame('mewtwo', $result->name);
        $this->assertSame('It was created by a scientist.', $result->description);
    }

    public function test_proceeds_without_cache_and_logs_warning_when_redis_read_fails(): void
    {
        $this->redis
            ->method('get')
            ->willThrowException(new \RuntimeException('Redis unavailable'));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('warning');

        $this->pokeApiClient
            ->expects($this->once())
            ->method('fetchSpecies')
            ->willReturn([
                'name'                => 'mewtwo',
                'is_legendary'        => true,
                'habitat'             => ['name' => 'rare'],
                'flavor_text_entries' => [],
            ]);

        $this->flavorTextExtractor
            ->method('extract')
            ->willReturn('Some description.');

        $result = $this->service->getByName('mewtwo');

        $this->assertSame('mewtwo', $result->name);
    }

    public function test_logs_warning_when_redis_write_fails(): void
    {
        $this->redis
            ->method('get')
            ->willReturn(null);

        $this->redis
            ->method('setex')
            ->willThrowException(new \RuntimeException('Redis unavailable'));

        $this->logger
            ->expects($this->once())
            ->method('warning');

        $this->pokeApiClient
            ->method('fetchSpecies')
            ->willReturn([
                'name'                => 'mewtwo',
                'is_legendary'        => true,
                'habitat'             => ['name' => 'rare'],
                'flavor_text_entries' => [],
            ]);

        $this->flavorTextExtractor
            ->method('extract')
            ->willReturn('Some description.');

        $result = $this->service->getByName('mewtwo');

        $this->assertSame('mewtwo', $result->name);
    }

    public function test_uses_empty_description_and_logs_warning_when_no_flavor_text_found(): void
    {
        $this->redis->method('get')->willReturn(null);

        $this->pokeApiClient
            ->method('fetchSpecies')
            ->willReturn([
                'name'                => 'mewtwo',
                'is_legendary'        => true,
                'habitat'             => ['name' => 'rare'],
                'flavor_text_entries' => [],
            ]);

        $this->flavorTextExtractor
            ->method('extract')
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('warning');

        $result = $this->service->getByName('mewtwo');

        $this->assertSame('', $result->description);
    }

    public function test_handles_null_habitat(): void
    {
        $this->redis->method('get')->willReturn(null);

        $this->pokeApiClient
            ->method('fetchSpecies')
            ->willReturn([
                'name'                => 'mewtwo',
                'is_legendary'        => true,
                'habitat'             => null,
                'flavor_text_entries' => [],
            ]);

        $this->flavorTextExtractor
            ->method('extract')
            ->willReturn('Some description.');

        $result = $this->service->getByName('mewtwo');

        $this->assertNull($result->habitat);
    }

    public function test_propagates_pokemon_not_found_exception(): void
    {
        $this->redis->method('get')->willReturn(null);

        $this->pokeApiClient
            ->method('fetchSpecies')
            ->willThrowException(new PokemonNotFoundException('unknownpokemon'));

        $this->expectException(PokemonNotFoundException::class);

        $this->service->getByName('unknownpokemon');
    }
}