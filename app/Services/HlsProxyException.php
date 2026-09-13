<?php

namespace App\Services;

use RuntimeException;

/** A fetch HlsProxyService refused or could not complete; $status is the HTTP status to answer with. */
class HlsProxyException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
