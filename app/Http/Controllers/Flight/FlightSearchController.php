<?php

namespace App\Http\Controllers\Flight;

use App\Exceptions\FlightProviderException;
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
        try {
            $data = $this->searchFlightService->search(
                $request->validated(),
                $request->query('scenario')
            );

            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => $data,
            ]);
        } catch (FlightProviderException $e) {
            return response()->json([
                'success' => false,
                'code' => $e->codeName,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        }
    }
}
