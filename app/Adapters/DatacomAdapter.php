<?php

namespace App\Adapters;

use App\Contracts\FlightProviderInterface;
use App\Exceptions\FlightProviderException;
use App\Support\Http\HttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class DatacomAdapter implements FlightProviderInterface
{
    public function search(array $data, ?string $scenario = null): array
    {
        $startedAt = microtime(true);
        try {
            $client = HttpClient::mockapi();

            if ($scenario !== null) {
                $client = $client->withQueryParameters(
                    ['scenario' => $scenario]
                );
            }

            $response = $client->post('/flights/search', $data);

            Log::channel('mock_api')->info('Datacom search completed', [
                'duration_ms' => round((microtime(true) - $startedAt) * 1000),
                'status' => $response->status(),
            ]);
        } catch (RequestException $e) {
            $response = $e->response;

            Log::channel('mock_api')->warning('Datacom HTTP error', [
                'duration_ms' => round((microtime(true) - $startedAt) * 1000),
                'status' => $response?->status(),
                'body' => $response?->body(),
            ]);

            throw $this->mapHttpError(
                $response?->status() ?? 500,
                $response?->body() ?? ''
            );
        } catch (ConnectionException $e) {
            Log::channel('mock_api')->error('Datacom connection failed', [
                'duration_ms' => round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage()
            ]);
            $message = strtolower($e->getMessage());

            if (
                str_contains($message, 'timed out') ||
                str_contains($message, 'timeout')
            ) {
                throw new FlightProviderException(
                    codeName: 'PROVIDER_TIMEOUT',
                    message: 'Flight provider did not respond in time.',
                    httpStatus: 504,
                    context: ['exception' => $e]
                );
            }

            throw new FlightProviderException(
                codeName: 'PROVIDER_CONNECTION_FAILED',
                message: 'Cannot connect to flight provider.',
                httpStatus: 502,
                context: ['exception' => $e]
            );
        }

        if ($response->failed()) {
            Log::channel('mock_api')->error('Datacom HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw $this->mapHttpError($response->status(), $response->body());
        }

        try {
            $json = $response->json();
        } catch (\Throwable $th) {
            throw new FlightProviderException(
                codeName: 'PROVIDER_INVALID_JSON',
                message: 'Flight provider returned invalid JSON.',
                httpStatus: 502,
                context: [
                    'body_preview' => mb_substr($response->body(), 0, 500),
                    'exception' => $th,
                ]
            );
        }

        if (!is_array($json)) {
            throw new FlightProviderException(
                codeName: 'PROVIDER_INVALID_JSON',
                message: 'Flight provider response is not valid JSON.',
                httpStatus: 502
            );
        }

        if (
            !array_key_exists('StatusCode', $json) ||
            !array_key_exists('Success', $json) ||
            !array_key_exists('ListGroup', $json)
        ) {
            throw new FlightProviderException(
                codeName: 'PROVIDER_INVALID_SCHEMA',
                message: 'Flight provider response schema is invalid.',
                httpStatus: 502
            );
        }

        if ($json['Success'] === false) {
            throw new FlightProviderException(
                codeName: 'PROVIDER_BUSINESS_ERROR',
                message: $json['Message'] ?? 'Flight provider returned business error.',
                httpStatus: 422,
                context: [
                    'provider_status_code' => $json['StatusCode'] ?? null,
                ]
            );
        }

        return $json;
    }

    private function mapHttpError(int $statusCode, string $body)
    {
        return match ($statusCode) {
            401 => new FlightProviderException('PROVIDER_UNAUTHORIZED', 'Flight provider authentication failed.', 502),
            403 => new FlightProviderException('PROVIDER_FORBIDDEN', 'Flight provider access denied.', 502),
            429 => new FlightProviderException('PROVIDER_RATE_LIMITED', 'Flight provider rate limit exceeded.', 429),
            500 => new FlightProviderException('PROVIDER_SERVER_ERROR', 'Flight provider server error.', 502),
            502 => new FlightProviderException('PROVIDER_BAD_GATEWAY', 'Flight provider bad gateway.', 502),
            503 => new FlightProviderException('PROVIDER_UNAVAILABLE', 'Flight provider is temporarily unavailable.', 503),
            504 => new FlightProviderException('PROVIDER_GATEWAY_TIMEOUT', 'Flight provider did not respond in time.', 504),

            default => new FlightProviderException(
                'PROVIDER_HTTP_ERROR',
                'Flight provider returned unexpected HTTP error.',
                502,
                [
                    'provider_status' => $statusCode,
                    'body_preview' => mb_substr($body, 0, 500),
                ]
            ),
        };
    }
}
