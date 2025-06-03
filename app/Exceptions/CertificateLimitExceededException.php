<?php

namespace App\Exceptions;

use Exception;

class CertificateLimitExceededException extends Exception
{
    private int $currentCount;
    private int $limit;

    public function __construct(int $currentCount, int $limit, string $message = null, int $code = 0, Exception $previous = null)
    {
        $this->currentCount = $currentCount;
        $this->limit = $limit;
        $message = $message ?: "Certificate limit exceeded. Current: {$currentCount}, Limit: {$limit}";
        
        parent::__construct($message, $code, $previous);
    }

    public function getCurrentCount(): int
    {
        return $this->currentCount;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}