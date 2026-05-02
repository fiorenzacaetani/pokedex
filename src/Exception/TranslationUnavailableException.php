<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Thrown when the FunTranslations API is unavailable or returns an error.
 */
class TranslationUnavailableException extends RuntimeException
{
    /**
     * @param string $reason Optional reason (e.g. 'rate limit exceeded', 'HTTP 500')
     */
    public function __construct(string $reason = '')
    {
        parent::__construct("Translation unavailable" . ($reason ? ": {$reason}" : "."));
    }
}