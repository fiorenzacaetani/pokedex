<?php

declare(strict_types=1);

use App\Controller\PokemonController;
use App\Controller\TranslatedPokemonController;
use Slim\App;

/** @var App $app */

$app->get('/pokemon/{name}', [PokemonController::class, 'get']);
$app->get('/pokemon/translated/{name}', [TranslatedPokemonController::class, 'get']);
