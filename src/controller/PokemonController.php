<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\PokemonNotFoundException;
use App\Service\PokemonService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class PokemonController
{
    public function __construct(
        private readonly PokemonService $pokemonService,
        private readonly LoggerInterface $logger,
    ) {}

    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $pokemonName = strtolower(trim($args['name']));

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