<?php

namespace App\Exceptions;

use Exception;

class FlightProviderException extends Exception
{
    public function __construct(
        public string $codeName,
        string $message,
        public int $httpStatus,
        public array $context = []
    ) {
        parent::__construct($message);
    }
}
