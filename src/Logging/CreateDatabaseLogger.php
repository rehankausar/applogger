<?php

namespace FastnetKSA\AppLogger\Logging;

use Monolog\Logger;

class CreateDatabaseLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('database');
        $logger->pushHandler(new DatabaseLogHandler());

        return $logger;
    }
}
