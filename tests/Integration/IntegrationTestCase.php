<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\App;
use App\Client\RedisClientInterface;
use DI\ContainerBuilder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Slim\App as SlimApp;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Base class for integration tests.
 *
 * Boots a real Slim application with a real DI container, but replaces
 * all external dependencies (HTTP clients, Redis, logger) with controlled
 * test doubles. This allows end-to-end testing of the full request/response
 * cycle — routing, controllers, services, and response serialisation —
 * without making actual network calls or requiring external infrastructure.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected SlimApp $app;
    protected MockHandler $mockHandler;

    /**
     * Boot the application before each test.
     *
     * Builds a DI container that mirrors the production configuration but
     * substitutes a Guzzle MockHandler for the real HTTP client, a no-op
     * logger to suppress output, and a Redis stub that always returns null
     * (cache miss) so tests exercise the full service logic.
     */
    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();

        $handlerStack = HandlerStack::create($this->mockHandler);
        $mockGuzzle   = new GuzzleClient(['handler' => $handlerStack]);

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../config/container.php');
        $containerBuilder->addDefinitions([
            ClientInterface::class  => $mockGuzzle,
            LoggerInterface::class  => new NullLogger(),
            RedisClientInterface::class => $this->buildRedisStub(),
        ]);

        $container = $containerBuilder->build();

        $this->app = App::create($container);
    }

    /**
     * Build a Redis stub that always reports cache miss on reads
     * and silently ignores writes.
     *
     * This ensures every integration test exercises the full service logic
     * (API calls, translation, DTO construction) rather than short-circuiting
     * through a cached value.
     */
    private function buildRedisStub(): RedisClientInterface
    {
        return new class implements RedisClientInterface {
            /** Always returns null to simulate a cache miss. */
            public function get(string $key): ?string
            {
                return null;
            }

            /** No-op — writes are ignored in the test environment. */
            public function setex(string $key, int $seconds, string $value): void {}
        };
    }

    /**
     * Execute a GET request against the application and return the response.
     *
     * @param string $uri The request URI (e.g. '/pokemon/mewtwo')
     */
    protected function get(string $uri): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri);
        return $this->app->handle($request);
    }

    /**
     * Decode and return the JSON body of a response as an associative array.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array<string, mixed>
     */
    protected function jsonBody(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }
}