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
use PHPUnit\Framework\TestCase;
use Predis\Client as PredisClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App as SlimApp;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Base class for integration tests that require a real Redis instance.
 *
 * Unlike IntegrationTestCase (which uses a stub that always returns cache miss),
 * this class connects to a dedicated Redis test instance. This allows testing
 * the full caching behaviour end-to-end: cache population, cache hit, TTL,
 * and serialisation/deserialisation of cached data.
 *
 * Requires the redis-test service to be running (see docker-compose.yml).
 * The Redis database is flushed before each test to guarantee isolation.
 */
abstract class RedisIntegrationTestCase extends TestCase
{
    protected SlimApp $app;
    protected MockHandler $mockHandler;
    private PredisClient $redisClient;

    /**
     * Boot the application and flush the Redis test database before each test.
     *
     * Connects to the dedicated Redis test instance (REDIS_TEST_HOST/REDIS_TEST_PORT),
     * flushes all keys to guarantee test isolation, and builds the application
     * with a real Redis adapter and a Guzzle MockHandler for external HTTP calls.
     */
    protected function setUp(): void
    {
        $host = $_ENV['REDIS_TEST_HOST'] ?? getenv('REDIS_TEST_HOST') ?: 'localhost';
        $port = $_ENV['REDIS_TEST_PORT'] ?? getenv('REDIS_TEST_PORT') ?: 6380;

        $this->redisClient = new PredisClient([
            'scheme' => 'tcp',
            'host'   => $host,
            'port'   => (int) $port,
        ]);

        $this->redisClient->flushdb();

        $this->mockHandler = new MockHandler();
        $handlerStack      = HandlerStack::create($this->mockHandler);
        $mockGuzzle        = new GuzzleClient(['handler' => $handlerStack]);

        $predisClient = $this->redisClient;

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../config/container.php');
        $containerBuilder->addDefinitions([
            ClientInterface::class      => $mockGuzzle,
            LoggerInterface::class      => new NullLogger(),
            RedisClientInterface::class => new class($predisClient) implements RedisClientInterface {
                /**
                 * Wrap the real Predis client in our RedisClientInterface.
                 * This gives us a real Redis connection while still satisfying
                 * the type system.
                 */
                public function __construct(private PredisClient $client) {}

                /** Retrieve a value from Redis. Returns null on cache miss. */
                public function get(string $key): ?string
                {
                    return $this->client->get($key);
                }

                /** Store a value in Redis with a TTL in seconds. */
                public function setex(string $key, int $seconds, string $value): void
                {
                    $this->client->setex($key, $seconds, $value);
                }
            },
        ]);

        $container  = $containerBuilder->build();
        $this->app  = App::create($container);
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