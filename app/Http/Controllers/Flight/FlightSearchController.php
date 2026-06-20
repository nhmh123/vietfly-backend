<?php

namespace App\Http\Controllers\Flight;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchFlightRequest;
use App\Services\SearchFlightService;

class FlightSearchController extends Controller
{
    private $searchFlightService;
    public function __construct(SearchFlightService $searchFlightService) {
        $this->searchFlightService = $searchFlightService;
    }

    public function __invoke(SearchFlightRequest $request, SearchFlightService $service) {
        return response()->json([
            'message' => 'Flight search endpoint hit',
            'data' => $request->validated()
        ]);
    }
}
