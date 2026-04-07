<?php

namespace App\Services;

use RuntimeException;

class InputSanitizationException extends RuntimeException
{
    public function __construct(string $message = 'Input contains suspicious content.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
