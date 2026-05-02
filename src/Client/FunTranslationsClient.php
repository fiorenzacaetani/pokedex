<?php

declare(strict_types=1);

namespace App\Client;

use App\Exception\TranslationUnavailableException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for the FunTranslations API.
 * Supports Yoda and Shakespeare translation styles.
 */
class FunTranslationsClient
{
    private const BASE_URL = 'https://api.funtranslations.mercxry.me/v1/translate/';

    /**
     * @param ClientInterface $httpClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Translates the given text into Yoda-style speech.
     *
     * @param string $text
     * @return string
     * @throws TranslationUnavailableException
     */
    public function translateYoda(string $text): string
    {
        return $this->translate('yoda', $text);
    }

    /**
     * Translates the given text into Shakespearean English.
     *
     * @param string $text
     * @return string
     * @throws TranslationUnavailableException
     */
    public function translateShakespeare(string $text): string
    {
        return $this->translate('shakespeare', $text);
    }

    /**
     * Sends a POST request to the FunTranslations API for the given translation type.
     * Throws TranslationUnavailableException on rate limit (HTTP 429) or connection errors.
     *
     * @param string $type Translation style (e.g. 'yoda', 'shakespeare')
     * @param string $text Text to translate
     * @return string      Translated text
     * @throws TranslationUnavailableException
     */
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