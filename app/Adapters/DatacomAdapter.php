<?php

namespace App\Adapters;

use App\Contracts\FlightProviderInterface;
use App\Support\Http\HttpClient;
use Illuminate\Support\Facades\Log;

class DatacomAdapter implements FlightProviderInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function search(array $data): array
    {   
        // $response = HttpClient::mockapi()->post('/flights/search', $data);
        // Log::channel('mockapi')->info(get_class($response));
        // $data = $response->json();
        // Log::channel('mockapi')->info($data);
        return HttpClient::mockapi()->post('/flights/search', $data)->json();
    }
}
