<?php

declare(strict_types=1);

use App\Controller\PokemonController;
use Slim\App;

/** @var App $app */

$app->get('/pokemon/{name}', [PokemonController::class, 'get']);

// $app->get('/pokemon/{name}', function ($request, $response, $args) {
//     $response->getBody()->write(json_encode(['status' => 'ok', 'endpoint' => 'pokemon', 'name' => $args['name']]));
//     return $response->withHeader('Content-Type', 'application/json');
// });

$app->get('/pokemon/translated/{name}', function ($request, $response, $args) {
    $response->getBody()->write(json_encode(['status' => 'ok', 'endpoint' => 'translated', 'name' => $args['name']]));
    return $response->withHeader('Content-Type', 'application/json');
});