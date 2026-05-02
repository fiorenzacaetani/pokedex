<?php

declare(strict_types=1);

namespace Tests\Unit\Client;

use App\Client\FunTranslationsClient;
use App\Exception\TranslationUnavailableException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;


/**
 * Unit tests for FunTranslationsClient.
 */
class FunTranslationsClientTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private FunTranslationsClient $client;

    /**
     * Initialises mocks and constructs the client under test.
     */
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);
        $this->client     = new FunTranslationsClient($this->httpClient, $this->logger);
    }

    /**
     * Provides translation method names, API types, input text, and expected output.
     *
     * @return array<string, array{method: string, type: string, input: string, expected: string}>
     */
    public static function translationProvider(): array
    {
        return [
            'yoda translation succeeds' => [
                'method'   => 'translateYoda',
                'type'     => 'yoda',
                'input'    => 'It was created by a scientist.',
                'expected' => 'Created by a scientist, it was.',
            ],
            'shakespeare translation succeeds' => [
                'method'   => 'translateShakespeare',
                'type'     => 'shakespeare',
                'input'    => 'It was created by a scientist.',
                'expected' => 'T\'was created by a scientist.',
            ],
        ];
    }

    /**
     * @param string $method   Client method to call (translateYoda or translateShakespeare)
     * @param string $type     Expected API path segment
     * @param string $input    Text to translate
     * @param string $expected Expected translated text
     */
    #[DataProvider('translationProvider')]
    public function test_returns_translated_text(string $method, string $type, string $input, string $expected): void
    {
        $payload = ['contents' => ['translated' => $expected]];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', "https://api.funtranslations.mercxry.me/v1/translate/{$type}")
            ->willReturn(new Response(200, [], json_encode($payload)));

        $result = $this->client->$method($input);

        $this->assertSame($expected, $result);
    }

    /** Verifies that a 429 response triggers a warning log and a TranslationUnavailableException. */
    public function test_throws_translation_unavailable_on_rate_limit(): void
    {
        $this->logger->expects($this->once())->method('warning');

        $this->httpClient
            ->method('request')
            ->willThrowException(new ClientException(
                'Too many requests',
                $this->createMock(RequestInterface::class),
                new Response(429)
            ));

        $this->expectException(TranslationUnavailableException::class);

        $this->client->translateYoda('some text');
    }

    /** Verifies that a non-429 HTTP error logs an error and throws TranslationUnavailableException. */
    public function test_throws_translation_unavailable_on_generic_error(): void
    {
        $this->logger->expects($this->once())->method('error');

        $this->httpClient
            ->method('request')
            ->willThrowException(new ClientException(
                'Server error',
                $this->createMock(RequestInterface::class),
                new Response(500)
            ));

        $this->expectException(TranslationUnavailableException::class);

        $this->client->translateShakespeare('some text');
    }
}