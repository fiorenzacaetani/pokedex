<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\PokemonNotFoundException;
use App\Service\PokemonService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles HTTP requests for the /pokemon/{name} endpoint.
 */
class PokemonController
{
    use ValidatesPokemonNameTrait;
    /**
     * @param PokemonService  $pokemonService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PokemonService $pokemonService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns the Pokémon data for the given name as a JSON response.
     * Responds with 400 for invalid names, 404 if not found, and 500 on unexpected errors.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param array                  $args     Route arguments; expects 'name'
     * @return ResponseInterface
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $pokemonName = strtolower(trim($args['name']));

        if (!$this->validatePokemonName($pokemonName)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid pokemon name.']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }


        try {
            $pokemon = $this->pokemonService->getByName($pokemonName);
            $response->getBody()->write(json_encode($pokemon->toArray()));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PokemonNotFoundException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error fetching Pokemon', [
                'pokemon' => $pokemonName,
                'message' => $e->getMessage(),
            ]);
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred.']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

}