<?php

declare(strict_types=1);

namespace Tests\Integration;

use GuzzleHttp\Psr7\Response;

/**
 * Integration tests for GET /pokemon/translated/{name}.
 *
 * Verifies the full request/response cycle for the translated Pokémon endpoint:
 * routing, translation rule application (Yoda vs Shakespeare), fallback to
 * standard description when translation is unavailable, and JSON serialisation.
 * All external HTTP calls are intercepted by a Guzzle MockHandler.
 */
class TranslatedPokemonControllerTest extends IntegrationTestCase
{
    /**
     * Build a PokéAPI species fixture for the given Pokémon.
     *
     * @param string      $name        Pokémon name
     * @param bool        $isLegendary Whether the Pokémon is legendary
     * @param string|null $habitat     Habitat name, or null if none
     * @return array<string, mixed>
     */
    private function speciesFixture(string $name, bool $isLegendary, ?string $habitat): array
    {
        return [
            'name'         => $name,
            'is_legendary' => $isLegendary,
            'habitat'      => $habitat ? ['name' => $habitat] : null,
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
     * A legendary Pokémon receives a Yoda translation.
     */
    public function test_legendary_pokemon_gets_yoda_translation(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->speciesFixture('mewtwo', true, 'rare'))),
            new Response(200, [], json_encode([
                'contents' => ['translated' => 'Created by a scientist, it was.'],
            ]))
        );

        $response = $this->get('/pokemon/translated/mewtwo');
        $body     = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Created by a scientist, it was.', $body['description']);
        $this->assertTrue($body['isLegendary']);
    }

    /**
     * A cave-habitat Pokémon receives a Yoda translation.
     */
    public function test_cave_pokemon_gets_yoda_translation(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->speciesFixture('gengar', false, 'cave'))),
            new Response(200, [], json_encode([
                'contents' => ['translated' => 'Lurks in darkness, it does.'],
            ]))
        );

        $response = $this->get('/pokemon/translated/gengar');
        $body     = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Lurks in darkness, it does.', $body['description']);
    }

    /**
     * A regular Pokémon (non-legendary, non-cave) receives a Shakespeare translation.
     */
    public function test_regular_pokemon_gets_shakespeare_translation(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->speciesFixture('bulbasaur', false, 'grassland'))),
            new Response(200, [], json_encode([
                'contents' => ['translated' => 'Hark! A seed upon its back it bears.'],
            ]))
        );

        $response = $this->get('/pokemon/translated/bulbasaur');
        $body     = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hark! A seed upon its back it bears.', $body['description']);
    }

    /**
     * When the translation API returns 429 (rate limit), the endpoint falls back
     * to the standard description and still returns 200.
     */
    public function test_falls_back_to_standard_description_when_translation_unavailable(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->speciesFixture('bulbasaur', false, 'grassland'))),
            new Response(429, [], json_encode([
                'error'       => ['code' => 429, 'message' => 'Too many requests.'],
                'retry_after' => 42,
            ]))
        );

        $response = $this->get('/pokemon/translated/bulbasaur');
        $body     = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('A standard description.', $body['description']);
    }

    /**
     * A request for an unknown Pokémon returns 404 with a JSON error body.
     */
    public function test_returns_404_for_unknown_pokemon(): void
    {
        $this->mockHandler->append(new Response(404));

        $response = $this->get('/pokemon/translated/unknownpokemon');
        $body     = $this->jsonBody($response);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * A request with an invalid Pokémon name returns 400 without calling any external API.
     */
    public function test_returns_400_for_invalid_pokemon_name(): void
    {
        $response = $this->get('/pokemon/translated/mewtwo[]');
        $body     = $this->jsonBody($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('error', $body);
    }
}