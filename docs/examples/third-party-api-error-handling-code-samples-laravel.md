# Third-Party API Error Handling Code Samples (Laravel)

## Mục tiêu

Tài liệu này ghi chú code mẫu chuẩn để xử lý lỗi khi Laravel gọi API bên thứ ba như:

- Datacom
- GDS
- Payment Gateway
- SMS Gateway
- Email Provider
- Logistics API

Nguyên tắc chính:

```text
Adapter gọi provider
→ Adapter phát hiện lỗi
→ Adapter throw exception nội bộ
→ Controller hoặc Global Exception Handler trả JSON chuẩn cho frontend
```

---

# 1. Response format chuẩn cho frontend

## Success response

```json
{
  "success": true,
  "message": "Success",
  "data": {}
}
```

## Error response

```json
{
  "success": false,
  "code": "PROVIDER_UNAVAILABLE",
  "message": "Flight provider is temporarily unavailable."
}
```

Frontend không cần biết lỗi đến từ Datacom, Nginx, Timeout hay HTML response.

---

# 2. Tạo ProviderException

File:

```text
app/Exceptions/ProviderException.php
```

Code:

```php
<?php

namespace App\Exceptions;

use Exception;

class ProviderException extends Exception
{
    public function __construct(
        public string $codeName,
        string $message,
        public int $httpStatus = 502,
        public array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

Ý nghĩa:

```text
codeName   → mã lỗi nội bộ cho frontend
message    → message an toàn
httpStatus → HTTP status Laravel trả cho frontend
context    → dữ liệu để log/debug, không expose ra frontend
previous   → exception gốc
```

---

# 3. Tạo ProviderResponseException nếu muốn tách HTTP error

Có thể dùng chung `ProviderException`.

Nếu muốn rõ ràng hơn:

```text
app/Exceptions/ProviderHttpException.php
```

```php
<?php

namespace App\Exceptions;

class ProviderHttpException extends ProviderException
{
}
```

```text
app/Exceptions/ProviderSchemaException.php
```

```php
<?php

namespace App\Exceptions;

class ProviderSchemaException extends ProviderException
{
}
```

Giai đoạn đầu có thể chỉ cần `ProviderException`.

---

# 4. DatacomAdapter chuẩn

File ví dụ:

```text
app/Infrastructure/Providers/Datacom/DatacomAdapter.php
```

Code:

```php
<?php

namespace App\Infrastructure\Providers\Datacom;

use App\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DatacomAdapter
{
    public function search(array $payload): array
    {
        $requestId = (string) Str::uuid();
        $scenario = request()->query('scenario', 'success');

        Log::info('Datacom search request started', [
            'request_id' => $requestId,
            'scenario' => $scenario,
            'route_count' => count($payload['ListRoute'] ?? []),
        ]);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders([
                    'X-Request-ID' => $requestId,
                ])
                ->withQueryParameters([
                    'scenario' => $scenario,
                ])
                ->post(config('services.mock_api.url') . '/api/flights/search', $payload);
        } catch (ConnectionException $e) {
            Log::error('Datacom connection failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw new ProviderException(
                codeName: 'PROVIDER_CONNECTION_FAILED',
                message: 'Cannot connect to flight provider.',
                httpStatus: 502,
                context: [
                    'request_id' => $requestId,
                ],
                previous: $e
            );
        }

        $durationMs = optional($response->transferStats)->getTransferTime()
            ? round($response->transferStats->getTransferTime() * 1000)
            : null;

        Log::info('Datacom search response received', [
            'request_id' => $requestId,
            'status' => $response->status(),
            'duration_ms' => $durationMs,
        ]);

        if ($response->failed()) {
            throw $this->mapHttpError(
                status: $response->status(),
                body: $response->body(),
                requestId: $requestId
            );
        }

        $data = $this->parseJson($response->body(), $requestId);

        $this->assertValidSchema($data, $requestId);

        if (($data['Success'] ?? false) === false) {
            throw new ProviderException(
                codeName: 'PROVIDER_BUSINESS_ERROR',
                message: $data['Message'] ?? 'Flight provider returned business error.',
                httpStatus: 422,
                context: [
                    'request_id' => $requestId,
                    'provider_status_code' => $data['StatusCode'] ?? null,
                    'provider_message' => $data['Message'] ?? null,
                ]
            );
        }

        return $data;
    }

    private function parseJson(string $body, string $requestId): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::error('Datacom returned invalid JSON', [
                'request_id' => $requestId,
                'body_preview' => mb_substr($body, 0, 500),
                'error' => $e->getMessage(),
            ]);

            throw new ProviderException(
                codeName: 'PROVIDER_INVALID_JSON',
                message: 'Flight provider returned invalid JSON.',
                httpStatus: 502,
                context: [
                    'request_id' => $requestId,
                ],
                previous: $e
            );
        }

        if (!is_array($data)) {
            throw new ProviderException(
                codeName: 'PROVIDER_INVALID_JSON',
                message: 'Flight provider response is not a JSON object.',
                httpStatus: 502,
                context: [
                    'request_id' => $requestId,
                ]
            );
        }

        return $data;
    }

    private function assertValidSchema(array $data, string $requestId): void
    {
        $requiredFields = [
            'StatusCode',
            'Success',
            'Message',
            'ListGroup',
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                Log::error('Datacom response schema invalid', [
                    'request_id' => $requestId,
                    'missing_field' => $field,
                    'response_keys' => array_keys($data),
                ]);

                throw new ProviderException(
                    codeName: 'PROVIDER_INVALID_SCHEMA',
                    message: 'Flight provider response schema is invalid.',
                    httpStatus: 502,
                    context: [
                        'request_id' => $requestId,
                        'missing_field' => $field,
                    ]
                );
            }
        }

        if (!is_array($data['ListGroup'])) {
            throw new ProviderException(
                codeName: 'PROVIDER_INVALID_SCHEMA',
                message: 'Flight provider ListGroup must be an array.',
                httpStatus: 502,
                context: [
                    'request_id' => $requestId,
                ]
            );
        }
    }

    private function mapHttpError(int $status, string $body, string $requestId): ProviderException
    {
        Log::warning('Datacom HTTP error', [
            'request_id' => $requestId,
            'status' => $status,
            'body_preview' => mb_substr($body, 0, 500),
        ]);

        return match ($status) {
            401 => new ProviderException(
                codeName: 'PROVIDER_UNAUTHORIZED',
                message: 'Flight provider authentication failed.',
                httpStatus: 502,
                context: ['request_id' => $requestId]
            ),

            403 => new ProviderException(
                codeName: 'PROVIDER_FORBIDDEN',
                message: 'Flight provider access denied.',
                httpStatus: 502,
                context: ['request_id' => $requestId]
            ),

            429 => new ProviderException(
                codeName: 'PROVIDER_RATE_LIMITED',
                message: 'Flight provider rate limit exceeded.',
                httpStatus: 429,
                context: ['request_id' => $requestId]
            ),

            500 => new ProviderException(
                codeName: 'PROVIDER_SERVER_ERROR',
                message: 'Flight provider server error.',
                httpStatus: 502,
                context: ['request_id' => $requestId]
            ),

            502 => new ProviderException(
                codeName: 'PROVIDER_BAD_GATEWAY',
                message: 'Flight provider bad gateway.',
                httpStatus: 502,
                context: ['request_id' => $requestId]
            ),

            503 => new ProviderException(
                codeName: 'PROVIDER_UNAVAILABLE',
                message: 'Flight provider is temporarily unavailable.',
                httpStatus: 503,
                context: ['request_id' => $requestId]
            ),

            504 => new ProviderException(
                codeName: 'PROVIDER_GATEWAY_TIMEOUT',
                message: 'Flight provider did not respond in time.',
                httpStatus: 504,
                context: ['request_id' => $requestId]
            ),

            default => new ProviderException(
                codeName: 'PROVIDER_HTTP_ERROR',
                message: 'Flight provider returned an unexpected HTTP error.',
                httpStatus: 502,
                context: [
                    'request_id' => $requestId,
                    'provider_status' => $status,
                ]
            ),
        };
    }
}
```

---

# 5. SearchFlightService

Service không xử lý HTTP status chi tiết.

File:

```text
app/Services/SearchFlightService.php
```

Code:

```php
<?php

namespace App\Services;

use App\Mappers\Datacom\SearchFlightRequestMapper;
use App\Mappers\Datacom\SearchFlightResponseMapper;
use App\Ports\FlightProviderPort;

class SearchFlightService
{
    public function __construct(
        private FlightProviderPort $flightProvider
    ) {
    }

    public function search(array $data): array
    {
        $payload = SearchFlightRequestMapper::toDatacom($data);

        $providerResponse = $this->flightProvider->search($payload);

        return SearchFlightResponseMapper::toApplication($providerResponse);
    }
}
```

Service chỉ điều phối:

```text
App request
→ Datacom request
→ Provider response
→ App response
```

---

# 6. Controller bắt ProviderException

File:

```text
app/Http/Controllers/Api/SearchFlightController.php
```

Code:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchFlightRequest;
use App\Services\SearchFlightService;
use Illuminate\Support\Facades\Log;

class SearchFlightController extends Controller
{
    public function __construct(
        private SearchFlightService $searchFlightService
    ) {
    }

    public function search(SearchFlightRequest $request)
    {
        try {
            $data = $this->searchFlightService->search($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => $data,
            ]);
        } catch (ProviderException $e) {
            Log::warning('Flight search provider error', [
                'code' => $e->codeName,
                'message' => $e->getMessage(),
                'context' => $e->context,
            ]);

            return response()->json([
                'success' => false,
                'code' => $e->codeName,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        }
    }
}
```

Dễ hiểu cho giai đoạn đầu.

---

# 7. Global Exception Handler tốt hơn

Khi project lớn hơn, không nên `try/catch` lặp lại trong nhiều controller.

Laravel 11 có thể xử lý trong:

```text
bootstrap/app.php
```

Ví dụ:

```php
use App\Exceptions\ProviderException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ProviderException $e, $request) {
            Log::warning('Provider exception rendered', [
                'code' => $e->codeName,
                'message' => $e->getMessage(),
                'context' => $e->context,
            ]);

            return response()->json([
                'success' => false,
                'code' => $e->codeName,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        });
    })
    ->create();
```

Sau đó Controller gọn hơn:

```php
public function search(SearchFlightRequest $request)
{
    $data = $this->searchFlightService->search($request->validated());

    return response()->json([
        'success' => true,
        'message' => 'Success',
        'data' => $data,
    ]);
}
```

---

# 8. Binding Provider Port

Interface:

```text
app/Ports/FlightProviderPort.php
```

```php
<?php

namespace App\Ports;

interface FlightProviderPort
{
    public function search(array $payload): array;
}
```

Adapter implement interface:

```php
<?php

namespace App\Infrastructure\Providers\Datacom;

use App\Ports\FlightProviderPort;

class DatacomAdapter implements FlightProviderPort
{
    public function search(array $payload): array
    {
        // call Datacom
    }
}
```

Bind trong Service Provider:

```php
use App\Infrastructure\Providers\Datacom\DatacomAdapter;
use App\Ports\FlightProviderPort;

public function register(): void
{
    $this->app->bind(FlightProviderPort::class, DatacomAdapter::class);
}
```

Lợi ích:

```text
Service không phụ thuộc trực tiếp DatacomAdapter
Sau này đổi provider dễ hơn
```

---

# 9. Retry strategy

Không retry mọi lỗi.

## Nên retry

```text
500
502
503
504
ConnectionException
Timeout
```

## Không nên retry

```text
400
401
403
422 business error
invalid schema
invalid JSON
```

Ví dụ:

```php
$response = Http::timeout(10)
    ->connectTimeout(5)
    ->retry(
        times: 3,
        sleepMilliseconds: 200,
        when: function ($exception, $request) {
            return $exception instanceof ConnectionException;
        }
    )
    ->post($url, $payload);
```

Nếu muốn retry theo HTTP status:

```php
$response = Http::timeout(10)
    ->connectTimeout(5)
    ->retry(3, 200)
    ->post($url, $payload);
```

Cần cẩn thận vì retry không kiểm soát tốt có thể làm provider quá tải hơn.

---

# 10. Logging best practice

## Nên log

```php
Log::info('Datacom request started', [
    'request_id' => $requestId,
    'scenario' => $scenario,
    'route_count' => count($payload['ListRoute'] ?? []),
]);
```

```php
Log::warning('Datacom HTTP error', [
    'request_id' => $requestId,
    'status' => $status,
    'body_preview' => mb_substr($body, 0, 500),
]);
```

## Không nên log

Không log dữ liệu nhạy cảm:

```text
password
token
api key
credit card
passport
full passenger document
```

## Body preview

Chỉ log một phần body:

```php
'body_preview' => mb_substr($body, 0, 500)
```

Không log nguyên response quá lớn.

---

# 11. Xử lý invalid JSON

Provider hoặc Nginx có thể trả HTML:

```html
<html>
  <h1>504 Gateway Time-out</h1>
</html>
```

Nếu frontend parse trực tiếp sẽ lỗi:

```text
Unexpected token '<'
```

Laravel phải bắt trước:

```php
try {
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    throw new ProviderException(
        codeName: 'PROVIDER_INVALID_JSON',
        message: 'Flight provider returned invalid JSON.',
        httpStatus: 502,
        previous: $e
    );
}
```

---

# 12. Xử lý invalid schema

JSON hợp lệ nhưng sai cấu trúc.

Ví dụ provider trả:

```json
{
  "code": "0000",
  "ok": true,
  "data": []
}
```

Trong khi app cần:

```json
{
  "StatusCode": "0000",
  "Success": true,
  "Message": "Success",
  "ListGroup": []
}
```

Check schema:

```php
if (!array_key_exists('ListGroup', $data)) {
    throw new ProviderException(
        codeName: 'PROVIDER_INVALID_SCHEMA',
        message: 'Flight provider response schema is invalid.',
        httpStatus: 502
    );
}
```

---

# 13. Xử lý business error

HTTP 200 nhưng provider báo lỗi nghiệp vụ.

Ví dụ:

```json
{
  "StatusCode": "1001",
  "Success": false,
  "Message": "Fare is no longer available",
  "ListGroup": []
}
```

Code:

```php
if (($data['Success'] ?? false) === false) {
    throw new ProviderException(
        codeName: 'PROVIDER_BUSINESS_ERROR',
        message: $data['Message'] ?? 'Flight provider returned business error.',
        httpStatus: 422,
        context: [
            'provider_status_code' => $data['StatusCode'] ?? null,
        ]
    );
}
```

---

# 14. Empty result không phải lỗi

Ví dụ:

```json
{
  "StatusCode": "0000",
  "Success": true,
  "Message": "No flights found",
  "ListGroup": []
}
```

Laravel nên trả:

```json
{
  "success": true,
  "message": "No flights found",
  "data": {
    "outbound_flights": [],
    "inbound_flights": []
  }
}
```

Không nên throw exception.

---

# 15. Mapping scenario mock server

| Scenario | Expected Laravel code |
|---|---|
| success | success true |
| empty | success true, empty list |
| business-error | PROVIDER_BUSINESS_ERROR |
| invalid-json | PROVIDER_INVALID_JSON |
| invalid-schema | PROVIDER_INVALID_SCHEMA |
| timeout | PROVIDER_TIMEOUT hoặc PROVIDER_CONNECTION_FAILED |
| connect-timeout | PROVIDER_CONNECTION_FAILED |
| server-error | PROVIDER_SERVER_ERROR |
| bad-gateway | PROVIDER_BAD_GATEWAY |
| service-unavailable | PROVIDER_UNAVAILABLE |
| gateway-timeout | PROVIDER_GATEWAY_TIMEOUT |
| unauthorized | PROVIDER_UNAUTHORIZED |
| forbidden | PROVIDER_FORBIDDEN |
| rate-limit | PROVIDER_RATE_LIMITED |

---

# 16. Frontend xử lý response

Frontend không đoán provider lỗi gì.

Chỉ đọc response Laravel:

```js
const response = await fetch('/api/flights/search', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(payload),
})

const data = await response.json()

if (!response.ok || data.success === false) {
  throw new Error(data.message || 'Search flight failed')
}

return data.data
```

Nếu muốn an toàn hơn với non-JSON từ chính Laravel/Nginx:

```js
const contentType = response.headers.get('content-type') || ''

if (!contentType.includes('application/json')) {
  throw new Error('Server returned non-JSON response')
}
```

---

# 17. Cấu trúc thư mục đề xuất

```text
app/
├── Exceptions/
│   └── ProviderException.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── SearchFlightController.php
│   └── Requests/
│       └── SearchFlightRequest.php
├── Infrastructure/
│   └── Providers/
│       └── Datacom/
│           └── DatacomAdapter.php
├── Mappers/
│   └── Datacom/
│       ├── SearchFlightRequestMapper.php
│       └── SearchFlightResponseMapper.php
├── Ports/
│   └── FlightProviderPort.php
└── Services/
    └── SearchFlightService.php
```

---

# 18. Quy tắc nhớ nhanh

```text
Adapter
→ hiểu provider
→ throw ProviderException

Service
→ hiểu nghiệp vụ
→ không return JSON

Controller / Global Handler
→ hiểu HTTP response
→ trả JSON cho frontend
```

---

# 19. Anti-pattern cần tránh

## Sai: Adapter trả response JSON

```php
return response()->json([
    'success' => false,
    'message' => 'Provider error',
], 502);
```

Vì Adapter bị dính Laravel HTTP layer.

---

## Sai: Controller parse provider response

```php
if ($providerResponse['StatusCode'] !== '0000') {
    // xử lý Datacom ở Controller
}
```

Vì Controller bị dính schema Datacom.

---

## Sai: Frontend nhận lỗi HTML từ Nginx

```html
504 Gateway Time-out
nginx/1.31.2
```

Frontend sẽ khó xử lý thống nhất.

---

# 20. Production checklist

- Có timeout rõ ràng.
- Có connect timeout rõ ràng.
- Có request ID.
- Có log request started.
- Có log response status.
- Có log error context.
- Không log token/password.
- Không expose provider raw error.
- Không expose HTML provider.
- Có mapping HTTP error.
- Có xử lý invalid JSON.
- Có xử lý invalid schema.
- Có xử lý business error.
- Empty result không xem là lỗi.
- Frontend luôn nhận JSON chuẩn.
