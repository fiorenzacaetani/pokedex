# Pokédex API

A RESTful Pokémon information API built with PHP 8.2 and Slim 4, developed as a
software engineering challenge.

## Requirements

- [Docker](https://docs.docker.com/get-docker/) (includes Docker Compose)
- [PHP 8.2+](https://www.php.net/downloads) and [Composer](https://getcomposer.org/download/) — only required to run the test suite locally

## Running the application

All you need to run the API is Docker. No PHP installation required.

```bash
docker-compose up --build
```

## Endpoints

### GET /pokemon/{name}

Returns basic information about a Pokémon.

```bash
curl http://localhost:5002/pokemon/mewtwo
```

```json
{
    "name": "mewtwo",
    "description": "It was created by a scientist after years of horrific gene splicing and DNA engineering experiments.",
    "habitat": "rare",
    "isLegendary": true
}
```

### GET /pokemon/translated/{name}

Returns Pokémon information with a translated description, applying the following rules:

- **Yoda translation**: legendary Pokémon or cave-habitat Pokémon
- **Shakespeare translation**: all other Pokémon
- **Fallback**: if translation is unavailable for any reason, the standard description is returned

```bash
curl http://localhost:5002/pokemon/translated/mewtwo
```

```json
{
    "name": "mewtwo",
    "description": "Created by a scientist after years of horrific gene splicing and dna engineering experiments, it was.",
    "habitat": "rare",
    "isLegendary": true
}
```

## Running the tests

Unit and Integration tests run against your local PHP installation. Ensure you have PHP 8.2+ and Composer installed, then:

```bash
composer install --dev
./vendor/bin/phpunit
```

To run the Redis cache integration tests, the Docker services must be running first:

```bash
docker-compose up -d
./vendor/bin/phpunit tests/Integration
```

## Architecture

### Tech stack

- **PHP 8.2** — language
- **Slim 4** — micro-framework for REST APIs
- **Guzzle 7** — HTTP client for external API calls
- **Predis 2** — Redis client for caching
- **Monolog 3** — PSR-3 logging
- **PHP-DI 7** — dependency injection container
- **PHPUnit 11** — testing

### Project structure
```
src/
├── Application/       # App factory (shared between HTTP entry point and tests)
├── Client/            # HTTP clients for external APIs + Redis interface
├── Controller/        # Slim route handlers
├── Exception/         # Domain exceptions
├── Helper/            # FlavorTextExtractor — flavor text selection logic
├── Model/             # Pokemon DTO
└── Service/           # Business logic — PokemonService, TranslationService
tests/
├── Integration/       # Full request/response cycle tests with mocked HTTP
└── Unit/              # Unit tests for services, clients, and helpers
```

### Key design decisions

**The framework**
I evaluated both Laravel and Slim. I usually work with Yii2 and have worked extensively
with Laravel in the past. Yii2, which I use daily in a legacy context, is not PSR-7 compliant and has a 
significantly smaller and less active community than Laravel. It was not a 
serious candidate for a new greenfield project. Laravel, while familiar, felt over-engineered for two
endpoints. I chose Slim because it is a smaller framework, better suited to what I am
trying to achieve here, and nevertheless production-grade. I had used it once before in
a similar context, in production.

**Service layer shared between endpoints**
Both endpoints use the same `PokemonService`. The translated endpoint calls the service
directly rather than making an internal HTTP call to endpoint 1, avoiding unnecessary
latency and fragility. Caching is handled inside the service layer, so both endpoints
benefit from it transparently.

**Redis caching**
PokéAPI data is cached for 1 hour (Pokémon data never changes). Translations are also
cached for 1 hour (translations are deterministic — the same input always produces the
same output). Redis is treated as a best-effort dependency: if it is unavailable, the
application degrades gracefully by calling the external APIs directly.

**Flavor text selection**
PokéAPI returns dozens of flavor text entries across game versions and languages.
`FlavorTextExtractor` applies a deliberate selection strategy:
1. Most recent game version with clean text (no ALL CAPS Pokémon name)
2. Any version with clean text
3. Any version without hard formatting artifacts (`\f`, soft hyphen)
4. Last resort: first available English entry, fully sanitised

ALL CAPS names (e.g. `MEWTWO`, `GENGAR`) are artefacts of old game hardware character
sets and are excluded from clean text candidates.

**Custom Redis interface**
Rather than depending directly on `Predis\ClientInterface` (which is not mockable by PHPUnit), I defined a minimal `RedisClientInterface` declaring
only the methods we use (`get`, `setex`). This keeps tests clean and decouples the
application from Predis internals.

**Graceful degradation**
All external dependency failures are handled explicitly:
- Redis unavailable → proceed without cache, log warning
- Translation API unavailable (rate limit, timeout, any error) → return standard description, log warning
- PokéAPI unavailable → log error, return 500
- Pokémon not found → return 404

The client never receives a stack trace or an unhandled exception — every error
produces a well-formed JSON response with an appropriate HTTP status code.

**Input validation**
Pokémon names are validated against `/^[a-z\-]+$/` after lowercasing. Invalid names
return 400 immediately without calling any external API.

## What I would add for production

- **Authentication** — API key or OAuth2 depending on the use case
- **Rate limiting** on our own endpoints — not just handling the upstream rate limit
- **Structured logging** routed to an external monitoring system (e.g. Datadog).
  Monolog's handler stack makes this a configuration change, not a code change.
- **Health check endpoint** — `GET /health` returning Redis and external API status
- **Integration with a proper circuit breaker** library rather than the lightweight
  try/catch approach currently used for Redis
- **Longer cache TTL for translations** — translations are fully deterministic and
  could be cached indefinitely; 1 hour is conservative
- **OpenAPI/Swagger documentation** — Slim integrates well with swagger-php
- **Container hardening** — non-root user in Dockerfile, read-only filesystem,
  resource limits in docker-compose