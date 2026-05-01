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
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class FunTranslationsClientTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private FunTranslationsClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);
        $this->client     = new FunTranslationsClient($this->httpClient, $this->logger);
    }

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

    #[\PHPUnit\Framework\Attributes\DataProvider('translationProvider')]
    public function test_returns_translated_text(string $method, string $type, string $input, string $expected): void
    {
        $payload = ['contents' => ['translated' => $expected]];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', "https://funtranslations.mercxry.me/translate/{$type}.json")
            ->willReturn(new Response(200, [], json_encode($payload)));

        $result = $this->client->$method($input);

        $this->assertSame($expected, $result);
    }

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