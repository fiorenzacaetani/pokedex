<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\PokemonNotFoundException;
use App\Service\PokemonService;
use App\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TranslatedPokemonController
{
    public function __construct(
        private readonly PokemonService $pokemonService,
        private readonly TranslationService $translationService,
        private readonly LoggerInterface $logger,
    ) {}

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
            $pokemon     = $this->pokemonService->getByName($pokemonName);
            $description = $this->translationService->translate($pokemon);

            $data = $pokemon->toArray();
            $data['description'] = $description;

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PokemonNotFoundException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error fetching translated Pokemon', [
                'pokemon' => $pokemonName,
                'message' => $e->getMessage(),
            ]);
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred.']));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    private function validatePokemonName(string $name): bool
    {
        return preg_match('/^[a-z\-]+$/', $name) === 1;
    }
}