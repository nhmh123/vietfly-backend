<?php

namespace App\Services;

use App\Contracts\FlightProviderInterface;
use App\Mappers\Datacom\SearchFlightRequestMapper;
use App\Mappers\Datacom\SearchFlightResponseMapper;

class SearchFlightService
{
    protected FlightProviderInterface $flightProvider;

    /**
     * Create a new class instance.
     */
    public function __construct(FlightProviderInterface $flightProvider)
    {
        $this->flightProvider = $flightProvider;
    }

    public function search(array $data, ?string $scenario = null)
    {
        $requestData = SearchFlightRequestMapper::toDatacom($data);
        $responseData = $this->flightProvider->search($requestData, $scenario);
        return SearchFlightResponseMapper::toApplication($responseData);
    }
}
