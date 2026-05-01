<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class TranslationUnavailableException extends RuntimeException
{
    public function __construct(string $reason = '')
    {
        parent::__construct("Translation unavailable" . ($reason ? ": {$reason}" : "."));
    }
}