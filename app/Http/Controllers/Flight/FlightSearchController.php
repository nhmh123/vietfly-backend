<?php

namespace App\Http\Controllers\Flight;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchFlightRequest;
use App\Services\SearchFlightService;

class FlightSearchController extends Controller
{
    private SearchFlightService $searchFlightService;
    public function __construct(SearchFlightService $searchFlightService)
    {
        $this->searchFlightService = $searchFlightService;
    }

    public function __invoke(SearchFlightRequest $request)
    {
        return response()->json(
            $this->searchFlightService->search($request->validated())
        );

        return response()->json([
            'message' => 'Flight search endpoint hit',
            'data' => $request->validated()
        ]);
    }
}
