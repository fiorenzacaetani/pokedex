<?php

declare(strict_types=1);

namespace Tests\Integration;

use GuzzleHttp\Psr7\Response;

/**
 * Integration tests for GET /pokemon/{name} caching behaviour.
 *
 * These tests use a real Redis instance to verify that:
 * - The first request populates the cache correctly
 * - Subsequent requests are served from cache without calling PokéAPI
 * - Cached data is deserialised correctly and produces an identical response
 *
 * Requires the redis-test service to be running (see docker-compose.yml).
 */
class PokemonControllerCacheTest extends RedisIntegrationTestCase
{
    /**
     * Fixture representing a minimal PokéAPI pokemon-species response for Mewtwo.
     *
     * @return array<string, mixed>
     */
    private function mewtwoSpeciesFixture(): array
    {
        return [
            'name'                => 'mewtwo',
            'is_legendary'        => true,
            'habitat'             => ['name' => 'rare'],
            'flavor_text_entries' => [
                [
                    'flavor_text' => 'It was created by a scientist after years of horrific gene splicing and DNA engineering experiments.',
                    'language'    => ['name' => 'en'],
                    'version'     => ['name' => 'sword'],
                ],
            ],
        ];
    }

    /**
     * The second request for the same Pokémon is served from cache.
     *
     * Verifies that PokéAPI is called exactly once across two identical requests,
     * and that both responses contain identical data — confirming that the cached
     * value is deserialised correctly and returned without modification.
     */
    public function test_second_request_is_served_from_cache(): void
    {
        // First request — PokéAPI is called, response is cached
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->mewtwoSpeciesFixture()))
        );

        $firstResponse = $this->get('/pokemon/mewtwo');
        $firstBody     = $this->jsonBody($firstResponse);

        $this->assertSame(200, $firstResponse->getStatusCode());

        // Second request — no mock appended, so if PokéAPI were called it would throw
        $secondResponse = $this->get('/pokemon/mewtwo');
        $secondBody     = $this->jsonBody($secondResponse);

        $this->assertSame(200, $secondResponse->getStatusCode());
        $this->assertSame($firstBody, $secondBody);
    }

    /**
     * Cached Pokémon data is deserialised correctly and contains all expected fields.
     *
     * Populates the cache via a first request, then verifies that the cached
     * response contains the correct name, habitat, legendary status, and a
     * non-empty description.
     */
    public function test_cached_response_contains_correct_data(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->mewtwoSpeciesFixture()))
        );

        // Populate cache
        $this->get('/pokemon/mewtwo');

        // Serve from cache — no mock needed
        $response = $this->get('/pokemon/mewtwo');
        $body     = $this->jsonBody($response);

        $this->assertSame('mewtwo', $body['name']);
        $this->assertSame('rare', $body['habitat']);
        $this->assertTrue($body['isLegendary']);
        $this->assertNotEmpty($body['description']);
    }
}