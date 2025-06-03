<?php

namespace App\Exceptions;

use Exception;

class SSLProviderUnavailableException extends Exception
{
    private string $provider;

    public function __construct(string $provider, string $message = null, int $code = 0, Exception $previous = null)
    {
        $this->provider = $provider;
        $message = $message ?: "SSL provider '{$provider}' is currently unavailable";
        
        parent::__construct($message, $code, $previous);
    }

    public function getProvider(): string
    {
        return $this->provider;
    }
}