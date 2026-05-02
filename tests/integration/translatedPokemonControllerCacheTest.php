<?php

declare(strict_types=1);

namespace Tests\Integration;

use GuzzleHttp\Psr7\Response;

/**
 * Integration tests for GET /pokemon/translated/{name} caching behaviour.
 *
 * These tests use a real Redis instance to verify that:
 * - The first request populates both the Pokémon and translation caches
 * - Subsequent requests are served from cache without calling PokéAPI or FunTranslations
 * - Cached translated data is deserialised correctly and produces an identical response
 *
 * Requires the redis-test service to be running (see docker-compose.yml).
 */
class TranslatedPokemonControllerCacheTest extends RedisIntegrationTestCase
{
    /**
     * Build a minimal PokéAPI species fixture.
     *
     * @return array<string, mixed>
     */
    private function speciesFixture(string $name, bool $isLegendary, ?string $habitat): array
    {
        return [
            'name'                => $name,
            'is_legendary'        => $isLegendary,
            'habitat'             => $habitat ? ['name' => $habitat] : null,
            'flavor_text_entries' => [
                [
                    'flavor_text' => 'A standard description.',
                    'language'    => ['name' => 'en'],
                    'version'     => ['name' => 'sword'],
                ],
            ],
        ];
    }

    /**
     * The second request for the same translated Pokémon is served entirely from cache.
     *
     * Verifies that both PokéAPI and FunTranslations are called exactly once
     * across two identical requests, and that both responses are identical.
     */
    public function test_second_request_is_served_from_cache(): void
    {
        // First request — both PokéAPI and FunTranslations are called
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->speciesFixture('mewtwo', true, 'rare'))),
            new Response(200, [], json_encode([
                'contents' => ['translated' => 'Created by a scientist, it was.'],
            ]))
        );

        $firstResponse = $this->get('/pokemon/translated/mewtwo');
        $firstBody     = $this->jsonBody($firstResponse);

        $this->assertSame(200, $firstResponse->getStatusCode());

        // Second request — no mocks appended, cache must serve both Pokémon and translation
        $secondResponse = $this->get('/pokemon/translated/mewtwo');
        $secondBody     = $this->jsonBody($secondResponse);

        $this->assertSame(200, $secondResponse->getStatusCode());
        $this->assertSame($firstBody, $secondBody);
    }

    /**
     * The cached translated description is returned correctly on subsequent requests.
     *
     * Confirms that the translation stored in Redis is deserialised and returned
     * without alteration on the second request.
     */
    public function test_cached_translation_is_returned_correctly(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->speciesFixture('mewtwo', true, 'rare'))),
            new Response(200, [], json_encode([
                'contents' => ['translated' => 'Created by a scientist, it was.'],
            ]))
        );

        // Populate cache
        $this->get('/pokemon/translated/mewtwo');

        // Serve from cache
        $response = $this->get('/pokemon/translated/mewtwo');
        $body     = $this->jsonBody($response);

        $this->assertSame('Created by a scientist, it was.', $body['description']);
        $this->assertSame('mewtwo', $body['name']);
        $this->assertTrue($body['isLegendary']);
    }
}