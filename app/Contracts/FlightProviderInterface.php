<?php

namespace App\Contracts;

interface FlightProviderInterface
{
    public function search(array $data): array;
}
