# Laravel HTTP Client Best Practices

## Table of Contents

### Shared Http Client & Exception

1. [Mục tiêu](#mục-tiêu)
2. [Shared Http Client](#1-shared-http-client)
3. [Khi nào nên dùng](#khi-nào-nên-dùng-shared-http-client)
4. [Tách theo Domain](#best-practice-tách-theo-domain)
5. [Base URL](#best-practice-base-url)
6. [Authentication](#best-practice-authentication)
7. [Retry](#best-practice-retry)
8. [Logging](#best-practice-logging)
9. [Correlation ID](#best-practice-correlation-id)
10. [Không gọi Http Facade trong Controller](#best-practice-không-gọi-http-facade-trực-tiếp-trong-controller)
11. [Ví dụ hoàn chỉnh](#ví-dụ-hoàn-chỉnh)

---

# Mục tiêu

Hai phần này thường được setup rất sớm trong một dự án để:

- Tránh lặp lại cấu hình HTTP.
- Chuẩn hóa việc gọi external API.
- Phân tách lỗi business và lỗi kỹ thuật.
- Tạo nền tảng cho logging, retry, monitoring và microservices.
- Dễ bảo trì khi hệ thống lớn lên.

---

# 1. Shared Http Client

## Vấn đề nếu không có Shared Http Client

Giả sử gọi Datacom API ở nhiều nơi:

```php
$response = Http::timeout(30)
    ->acceptJson()
    ->withHeaders([
        'Authorization' => 'Bearer '.$token
    ])
    ->get($url);
```

Sau này:

- timeout đổi từ 30 → 60
- thêm retry
- thêm correlation-id
- thêm logging
- đổi authentication

=> phải sửa hàng chục file.

Đây là dấu hiệu của duplicated infrastructure code.

---

# Giải pháp

Tạo:

```
app/
└── Support
    └── Http
        HttpClient.php
```

```php
<?php

namespace App\Support\Http;

use Illuminate\Support\Facades\Http;

class HttpClient
{
    public static function datacom()
    {
        return Http::timeout(30)
            ->acceptJson();
    }
}
```

Sử dụng:

```php
$response = HttpClient::datacom()
    ->get('/search');
```

Mọi cấu hình đều tập trung một nơi.

---

# Khi nào nên dùng Shared Http Client

Nếu project có:

- Datacom API
- Payment Gateway
- Airline API
- Google API
- Internal Service
- CRM Service

thì nên có Shared Http Client.

---

# Không nên

```php
Http::timeout(30)
    ->acceptJson()
    ->get(...);

Http::timeout(30)
    ->acceptJson()
    ->post(...);

Http::timeout(30)
    ->acceptJson()
    ->put(...);
```

Lặp code ở nhiều nơi.

---

# Best Practice: Tách theo Domain

```php
class HttpClient
{
    public static function datacom()
    {
        return Http::timeout(30)
            ->acceptJson();
    }

    public static function payment()
    {
        return Http::timeout(10)
            ->acceptJson();
    }

    public static function airline()
    {
        return Http::timeout(20)
            ->acceptJson();
    }
}
```

Service:

```php
HttpClient::datacom();

HttpClient::payment();

HttpClient::airline();
```

---

# Best Practice: Base URL

Không hardcode:

```php
Http::get(
    'https://api.datacom.com/search'
);
```

Nên:

```php
return Http::baseUrl(
    config('services.datacom.url')
);
```

.env

```env
DATACOM_URL=https://api.datacom.com
```

services.php

```php
'datacom' => [
    'url' => env('DATACOM_URL')
]
```

HttpClient

```php
public static function datacom()
{
    return Http::baseUrl(
            config('services.datacom.url')
        )
        ->timeout(30)
        ->acceptJson();
}
```

Sau đó:

```php
HttpClient::datacom()
    ->get('/search');
```

---

# Best Practice: Authentication

Thay vì:

```php
Http::withToken($token);
```

ở khắp nơi.

Nên:

```php
public static function datacom()
{
    return Http::baseUrl(...)
        ->withToken(
            config('services.datacom.token')
        );
}
```

.env

```env
DATACOM_TOKEN=xxxx
```

---

# Best Practice: Retry

External API có thể timeout tạm thời.

```php
return Http::retry(
        3,
        1000
    )
    ->timeout(30);
```

Nghĩa là:

- thử tối đa 3 lần
- cách nhau 1 giây

---

# Best Practice: Logging

Có thể dùng middleware:

```php
beforeSending(function ($request, $options) {

    Log::info('Calling Datacom API', [
        'url' => $request->url()
    ]);

});
```

Giúp trace production.

---

# Best Practice: Correlation ID

```php
return Http::withHeaders([
    'X-Request-Id' => request()->header('X-Request-Id')
]);
```

Giúp trace request xuyên suốt nhiều service.

---

# Best Practice: Không gọi Http Facade trực tiếp trong Controller

Không nên:

```php
class SearchController
{
    public function index()
    {
        Http::get(...);
    }
}
```

Controller không nên biết external API.

Nên:

```php
Controller
    ↓
Service
    ↓
DatacomClient
    ↓
HttpClient
```

---

# Ví dụ hoàn chỉnh

HttpClient

```php
class HttpClient
{
    public static function datacom()
    {
        return Http::baseUrl(
                config('services.datacom.url')
            )
            ->withToken(
                config('services.datacom.token')
            )
            ->acceptJson()
            ->retry(3,1000)
            ->timeout(30);
    }
}
```

Service

```php
$response = HttpClient::datacom()
    ->get('/search');
```

---

# Checklist

- [ ] Base URL
- [ ] Timeout
- [ ] Retry
- [ ] Authentication
- [ ] Headers
- [ ] Correlation ID
- [ ] Logging
- [ ] Centralized config