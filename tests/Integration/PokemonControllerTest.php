<?php

declare(strict_types=1);

namespace Tests\Integration;

use GuzzleHttp\Psr7\Response;

/**
 * Integration tests for GET /pokemon/{name}.
 *
 * Verifies the full request/response cycle for the basic Pokémon endpoint:
 * routing, service orchestration, flavor text extraction, and JSON serialisation.
 * External HTTP calls are intercepted by a Guzzle MockHandler.
 */
class PokemonControllerTest extends IntegrationTestCase
{
    /**
     * Fixture representing a minimal PokéAPI pokemon-species response for Mewtwo.
     *
     * @return array<string, mixed>
     */
    private function mewtwoSpeciesFixture(): array
    {
        return [
            'name'         => 'mewtwo',
            'is_legendary' => true,
            'habitat'      => ['name' => 'rare'],
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
     * A valid request for a known Pokémon returns 200 with the expected JSON structure.
     */
    public function test_returns_200_with_pokemon_data(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->mewtwoSpeciesFixture()))
        );

        $response = $this->get('/pokemon/mewtwo');
        $body     = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('mewtwo', $body['name']);
        $this->assertSame('rare', $body['habitat']);
        $this->assertTrue($body['isLegendary']);
        $this->assertArrayHasKey('description', $body);
        $this->assertNotEmpty($body['description']);
    }

    /**
     * A request for an unknown Pokémon returns 404 with a JSON error body.
     */
    public function test_returns_404_for_unknown_pokemon(): void
    {
        $this->mockHandler->append(
            new Response(404)
        );

        $response = $this->get('/pokemon/unknownpokemon');
        $body     = $this->jsonBody($response);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * A request with an invalid Pokémon name (containing illegal characters)
     * returns 400 without calling any external API.
     */
    public function test_returns_400_for_invalid_pokemon_name(): void
    {
        $response = $this->get('/pokemon/mewtwo[]');
        $body     = $this->jsonBody($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('error', $body);
    }
}