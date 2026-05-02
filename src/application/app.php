<?php

declare(strict_types=1);

namespace App\Application;

use DI\Container;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;

/**
 * Application factory.
 *
 * Centralises Slim app creation so that both the HTTP entry point (public/index.php)
 * and the integration test suite can boot an identical application instance,
 * differing only in the container bindings they provide.
 */
class App
{
    /**
     * Create and configure a Slim application from the given DI container.
     *
     * Registers error middleware and loads the route definitions.
     * The caller is responsible for building and populating the container
     * before passing it here.
     */
    public static function create(Container $container): SlimApp
    {
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addErrorMiddleware(false, true, true);

        require __DIR__ . '/../../routes/api.php';

        return $app;
    }
    
}