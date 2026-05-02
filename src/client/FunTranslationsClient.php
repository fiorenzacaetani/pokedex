<?php

declare(strict_types=1);

namespace App\Client;

use App\Exception\TranslationUnavailableException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class FunTranslationsClient
{
    private const BASE_URL = 'https://api.funtranslations.mercxry.me/v1/translate/';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function translateYoda(string $text): string
    {
        return $this->translate('yoda', $text);
    }

    public function translateShakespeare(string $text): string
    {
        return $this->translate('shakespeare', $text);
    }

    private function translate(string $type, string $text): string
    {
        try {
            $response = $this->httpClient->request('POST', self::BASE_URL . $type, [
                'json' => ['text' => $text],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['contents']['translated'];
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($status === 429) {
                $this->logger->warning('FunTranslations rate limit exceeded', [
                    'type' => $type,
                ]);
                throw new TranslationUnavailableException('rate limit exceeded');
            }

            $this->logger->error('FunTranslations request failed', [
                'type'    => $type,
                'status'  => $status,
                'message' => $e->getMessage(),
            ]);
            throw new TranslationUnavailableException("HTTP {$status}");
        } catch (GuzzleException $e) {
            $this->logger->error('FunTranslations connection failed', [
                'type'    => $type,
                'message' => $e->getMessage(),
            ]);
            throw new TranslationUnavailableException('connection error');
        }
    }
}