<?php

namespace App\Support\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HttpClient
{
    public static function mockapi()
    {
        $requestId = Str::uuid()->toString();
        Log::withContext(['request_id' => $requestId]);

        return Http::baseUrl(config('services.mock_api.url'))
            ->withBasicAuth(
                config('services.mock_api.username'),
                config('services.mock_api.password')
            )
            ->asJson()
            ->timeout(30)
            ->connectTimeout(5)
            ->retry(3, 100, function ($exception) {
                return $exception instanceof ConnectionException;
            })
            ->acceptJson()
            ->withHeaders([
                'X-Request-ID' => $requestId,
            ])
            ->beforeSending(function ($request) use ($requestId) {
                Log::channel('mock_api')->info("[API Request] [$requestId]", [
                    'url' => $request->url(),
                    'method' => $request->method(),
                ]);
            })
            ->throw(function ($response, $exception) use ($requestId) {
                Log::channel('mock_api')->error("[API Error] [$requestId]", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'exception' => $exception->getMessage(),
                ]);
            })
        ;
    }
}
