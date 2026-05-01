<?php

declare(strict_types=1);

namespace Tests\Unit\Client;

use App\Client\PokeApiClient;
use App\Exception\PokemonNotFoundException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class PokeApiClientTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private PokeApiClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);
        $this->client     = new PokeApiClient($this->httpClient, $this->logger);
    }

    public function test_returns_decoded_species_data_on_success(): void
    {
        $payload = ['name' => 'mewtwo', 'is_legendary' => true];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://pokeapi.co/api/v2/pokemon-species/mewtwo')
            ->willReturn(new Response(200, [], json_encode($payload)));

        $result = $this->client->fetchSpecies('mewtwo');

        $this->assertSame($payload, $result);
    }

    public function test_throws_pokemon_not_found_on_404(): void
    {
        $this->expectException(PokemonNotFoundException::class);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new ClientException(
                'Not found',
                $this->createMock(RequestInterface::class),
                new Response(404)
            ));

        $this->client->fetchSpecies('unknownpokemon');
    }

    public function test_logs_and_rethrows_on_non_404_error(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('error');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new ClientException(
                'Server error',
                $this->createMock(RequestInterface::class),
                new Response(500)
            ));

        $this->expectException(ClientException::class);

        $this->client->fetchSpecies('mewtwo');
    }
}