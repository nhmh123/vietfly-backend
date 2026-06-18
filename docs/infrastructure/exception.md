# Laravel Best Practices

## Table of Contents

### Shared Http Client & Exception

12. [Custom Exception](#2-custom-exception)
13. [BusinessException](#businessexception)
14. [DatacomException](#datacomexception)
15. [Tại sao phải phân loại Exception](#tại-sao-phải-phân-loại-exception)
16. [Domain Exception](#best-practice-kế-thừa-domain-exception)
17. [Không return false](#best-practice-không-return-false)
18. [Exception chỉ dành cho Exceptional Cases](#best-practice-exception-chỉ-dành-cho-exceptional-cases)
19. [Exception phải chứa Context](#best-practice-exception-phải-chứa-context)
20. [Không nuốt Exception](#best-practice-không-nuốt-exception)
21. [Kiến trúc tổng thể](#kiến-trúc-tổng-thể)
22. [Checklist](#checklist)

# 2. Custom Exception

Mục tiêu:

Không phải mọi lỗi đều giống nhau.

Ví dụ:

### Business Error

"Hết chỗ"

Đây không phải lỗi hệ thống.

---

### Technical Error

"Timeout Datacom API"

Đây là lỗi hệ thống.

Nếu dùng:

```php
throw new Exception();
```

thì rất khó phân biệt.

---

# BusinessException

```php
class BusinessException extends Exception
{
}
```

Dùng cho các lỗi nghiệp vụ.

Ví dụ:

- Hết chỗ
- Vé đã hết hạn
- Không đủ điểm thưởng
- Không thể hủy vé

```php
if ($flight->available_seats == 0) {

    throw new BusinessException(
        'Flight is full'
    );
}
```

BusinessException không phải bug.

Nó là trạng thái hợp lệ của nghiệp vụ.

---

# DatacomException

```php
class DatacomException extends Exception
{
}
```

Dùng cho:

- Timeout
- 500 Internal Server Error
- API unavailable
- Invalid response

Ví dụ:

```php
if ($response->failed()) {

    throw new DatacomException(
        'Datacom API failed'
    );
}
```

---

# Tại sao phải phân loại Exception

Không nên:

```php
catch (Exception $e)
```

Vì mọi lỗi đều bị gom chung.

---

Nên:

```php
try {

} catch (BusinessException $e) {

} catch (DatacomException $e) {

} catch (Throwable $e) {

}
```

Có thể xử lý khác nhau.

---

BusinessException

Response:

```json
{
    "message":"Flight full"
}
```

HTTP:

```
422
```

Không cần gửi Slack.

---

DatacomException

Response:

```json
{
    "message":"Internal Server Error"
}
```

HTTP:

```
500
```

Có thể:

- Log error
- Alert Slack
- Gửi Sentry

---

# Best Practice: Kế thừa Domain Exception

```php
BusinessException
```

↓

```php
FlightException
```

↓

```php
SeatUnavailableException
```

Ví dụ:

```php
class FlightException extends BusinessException
{
}
```

```php
class SeatUnavailableException extends FlightException
{
}
```

Throw:

```php
throw new SeatUnavailableException();
```

Giúp exception mang ý nghĩa nghiệp vụ.

---

# Best Practice: Không return false

Không nên:

```php
if ($seat == 0) {

    return false;
}
```

Caller phải đoán:

- false là gì?
- timeout?
- hết chỗ?

Nên:

```php
throw new SeatUnavailableException();
```

Code rõ nghĩa hơn.

---

# Best Practice: Exception chỉ dành cho exceptional cases

Không nên:

```php
foreach ($users as $user) {

    if (!$user->active) {

        throw new Exception();
    }
}
```

Exception không thay thế if.

Exception dành cho:

- lỗi bất thường
- business violation
- external failure

---

# Best Practice: Exception phải chứa context

Không nên:

```php
throw new DatacomException(
    'API error'
);
```

Nên:

```php
throw new DatacomException(
    "Search API failed. Flight ID: {$flightId}"
);
```

Hoặc:

```php
Log::error('Datacom failed', [
    'flight_id'=>$flightId,
    'response'=>$response->body()
]);
```

---

# Best Practice: Không nuốt Exception

Sai:

```php
try {

} catch (Exception $e) {

}
```

Mất lỗi.

Nên:

```php
try {

} catch (Throwable $e) {

    Log::error(
        'Booking failed',
        ['exception'=>$e]
    );

    throw $e;
}
```

---

# Kiến trúc tổng thể

```
Controller
    ↓
Application Service
    ↓
Datacom Client
    ↓
HttpClient
    ↓
External API

Exception Flow

External API timeout
        ↓
DatacomException
        ↓
Service
        ↓
Handler
        ↓
Log + Sentry
        ↓
HTTP 500
```

Business Flow

```
Seat = 0
    ↓
SeatUnavailableException
    ↓
Service
    ↓
Handler
    ↓
HTTP 422
```

---

# Checklist

- [ ] Không dùng Exception chung chung
- [ ] BusinessException
- [ ] ExternalApiException
- [ ] Domain Exception
- [ ] Không return false
- [ ] Không nuốt exception
- [ ] Có logging
- [ ] Có context
- [ ] Phân biệt 4xx và 5xx