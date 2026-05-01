<?php

declare(strict_types=1);

use App\Client\FunTranslationsClient;
use App\Controller\PokemonController;
use App\Client\PokeApiClient;
use App\Client\RedisClientInterface;
use App\Helper\FlavorTextExtractor;
use App\Service\PokemonService;
use App\Service\TranslationService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\Client as PredisClient;
use Psr\Log\LoggerInterface;
use function DI\create;
use function DI\get;

return [
    LoggerInterface::class => function () {
        $logger = new Logger('pokedex');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log'));
        return $logger;
    },

    ClientInterface::class => function () {
        return new GuzzleClient(['timeout' => 5.0]);
    },

    RedisClientInterface::class => function () {
        $predis = new PredisClient([
            'scheme' => 'tcp',
            'host'   => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port'   => $_ENV['REDIS_PORT'] ?? 6379,
        ]);
        return new class($predis) implements RedisClientInterface {
            public function __construct(private PredisClient $client) {}
            public function get(string $key): ?string
            {
                return $this->client->get($key);
            }
            public function setex(string $key, int $seconds, string $value): void
            {
                $this->client->setex($key, $seconds, $value);
            }
        };
    },

    PokeApiClient::class => create(PokeApiClient::class)
        ->constructor(get(ClientInterface::class), get(LoggerInterface::class)),

    FlavorTextExtractor::class => create(FlavorTextExtractor::class),

    PokemonService::class => create(PokemonService::class)
        ->constructor(
            get(PokeApiClient::class),
            get(FlavorTextExtractor::class),
            get(LoggerInterface::class),
            get(RedisClientInterface::class),
        ),

    PokemonController::class => create(PokemonController::class)
        ->constructor(get(PokemonService::class), get(LoggerInterface::class)),

    FunTranslationsClient::class => create(FunTranslationsClient::class)
        ->constructor(get(ClientInterface::class),get(LoggerInterface::class)),
        
    TranslationService::class => create(TranslationService::class)
        ->constructor(
            get(FunTranslationsClient::class),
            get(RedisClientInterface::class),
            get(LoggerInterface::class)
        )
];