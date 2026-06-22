<?php

namespace App\Adapters;

use App\Contracts\FlightProviderInterface;
use App\Support\Http\HttpClient;
use Illuminate\Support\Facades\Log;

class DatacomAdapter implements FlightProviderInterface
{
    public function search(array $data, ?string $scenario = null): array
    {   
        // $response = HttpClient::mockapi()->post('/flights/search', $data);
        // Log::channel('mockapi')->info(get_class($response));
        // $data = $response->json();
        // Log::channel('mockapi')->info($data);
        return HttpClient::mockapi()->post('/flights/search?scenario='.$scenario, $data)->json();
    }
}
