<?php

namespace App\Logging;

use Monolog\Logger;
use Psr\Log\LoggerInterface;

class DatabaseLoggerFactory
{
    /**
     * Create a custom Monolog instance.
     */
    public function __invoke(array $config): LoggerInterface
    {
        $logger = new Logger('database');
        $logger->pushHandler(new DatabaseLogHandler($config['level'] ?? Logger::INFO));
        
        return $logger;
    }
}