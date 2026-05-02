<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\App;
use DI\ContainerBuilder;

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

$app = App::create($container);
$app->run();