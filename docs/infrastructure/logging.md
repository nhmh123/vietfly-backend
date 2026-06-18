# Laravel Logging Best Practices

## Table of Contents

### Logging

1. [Tổng quan](#tổng-quan)
2. [Sử dụng cơ bản](#1-sử-dụng-cơ-bản)
3. [Severity Levels](#2-severity-levels)
4. [Cấu hình Logging](#3-cấu-hình-logging)
5. [Contextual Logging](#4-contextual-logging-best-practice)
6. [Không log dữ liệu nhạy cảm](#5-không-log-dữ-liệu-nhạy-cảm)
7. [Try-Catch trong Service Layer](#6-dùng-try-catch-tại-service-layer)
8. [Log Exception Object](#7-log-exception-object)
9. [Trace Flow](#8-trace-flow-bằng-info-log)
10. [Correlation ID](#9-correlation-id-rất-quan-trọng)
11. [Queue Logging](#10-log-queue-job)
12. [External API Logging](#11-log-external-api)
13. [Transaction Logging](#12-log-database-transaction)
14. [Performance Logging](#13-performance-logging)
15. [Domain Channels](#14-tách-channel-theo-domain)
16. [Không lạm dụng Debug Log](#15-không-lạm-dụng-debug-log)
17. [Không log trong Loop lớn](#16-không-dùng-log-trong-loop-lớn)
18. [Custom Monolog](#17-sử-dụng-tap-để-custom-monolog)
19. [Structured JSON Logging](#18-sử-dụng-structured-json-logging-production)
20. [Request/Response Logging](#19-log-request-và-response)
21. [Scheduler Logging](#20-scheduler-logging)
22. [Authentication Logging](#21-log-authentication-event)
23. [Tail Log](#22-tail-log-khi-development)
24. [Production Monitoring](#23-production-monitoring)
25. [Không dùng dd()](#24-không-dùng-dd-trong-production)
26. [Những thứ nên log](#25-những-thứ-nên-log)
27. [Checklist](#checklist-best-practice)
28. [Các loại log trong thực tế](#các-loại-log-trong-thực-tế)
29. [Thời gian lưu log](#log-retention-policy)

---

## Tổng quan

Logging là một phần quan trọng của Observability (Logs - Metrics - Traces). Trong môi trường production, log là công cụ chính giúp:

- Debug lỗi mà không cần truy cập trực tiếp vào server.
- Theo dõi luồng xử lý của ứng dụng.
- Audit các sự kiện quan trọng.
- Điều tra nguyên nhân sự cố (Root Cause Analysis).
- Theo dõi Queue, Scheduler, External API, Payment, Booking,...

Laravel sử dụng Monolog, hỗ trợ nhiều kênh log như:

- File
- Daily file rotation
- Slack
- Syslog
- Papertrail
- Sentry
- Logstash / ELK
- CloudWatch
- Custom channel

---

# 1. Sử dụng cơ bản

```php
use Illuminate\Support\Facades\Log;

Log::debug('Debug data', ['data' => $data]);

Log::info('User login', [
    'user_id' => $user->id
]);

Log::warning('Memory usage high');

Log::error('Cannot connect Flight API', [
    'error' => $e->getMessage()
]);
```

---

# 2. Severity Levels

Laravel hỗ trợ chuẩn RFC 5424.

| Level | Ý nghĩa |
|---------|---------|
| emergency | Hệ thống không sử dụng được |
| alert | Cần xử lý ngay |
| critical | Lỗi nghiêm trọng |
| error | Runtime error |
| warning | Bất thường nhưng chưa gây lỗi |
| notice | Sự kiện quan trọng |
| info | Thông tin nghiệp vụ |
| debug | Debug chi tiết |

Ví dụ:

```php
Log::debug('Request payload', [...]);

Log::info('User booked flight', [...]);

Log::warning('Third-party API response time > 5s');

Log::error('Payment gateway failed');

Log::critical('Redis connection lost');

Log::emergency('Database unavailable');
```

---

# 3. Cấu hình Logging

## .env

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

## Các channel phổ biến

### Single

```env
LOG_CHANNEL=single
```

Tất cả ghi vào:

```
storage/logs/laravel.log
```

---

### Daily

```env
LOG_CHANNEL=daily
```

Tạo file theo ngày:

```
laravel-2026-06-18.log
laravel-2026-06-19.log
```

Giới hạn số ngày:

```php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
]
```

---

### Stack

Ghi nhiều nơi cùng lúc:

```php
'stack' => [
    'driver' => 'stack',
    'channels' => ['daily', 'slack'],
]
```

---

# 4. Contextual Logging (Best Practice)

❌ Không nên:

```php
Log::info(
    'User '.$userId.' booked flight '.$flightId
);
```

Khó search và parse.

---

✅ Nên:

```php
Log::info('Flight booked', [
    'user_id' => $userId,
    'flight_id' => $flightId,
]);
```

Dễ phân tích bởi:

- ELK
- Grafana
- Sentry
- Datadog

---

# 5. Không log dữ liệu nhạy cảm

❌ Không được:

```php
Log::info('Login', [
    'password' => $password,
    'credit_card' => $cardNumber,
    'access_token' => $token
]);
```

Có thể gây rò rỉ dữ liệu.

---

✅ Chỉ log metadata:

```php
Log::info('User login', [
    'user_id' => $user->id,
    'email' => $user->email
]);
```

Không log:

- Password
- Access token
- JWT
- Credit card
- OTP
- Cookie
- Session id

---

# 6. Dùng try-catch tại Service Layer

Controller không nên xử lý log.

Controller:

```php
public function store()
{
    $bookingService->book($request->validated());
}
```

Service:

```php
public function book(array $data)
{
    try {

        // business logic

    } catch (Exception $e) {

        Log::error('Booking failed', [
            'input' => $data,
            'exception' => $e->getMessage()
        ]);

        throw $e;
    }
}
```

---

# 7. Log Exception Object

❌

```php
Log::error($e->getMessage());
```

Mất stacktrace.

---

✅

```php
Log::error('Booking failed', [
    'exception' => $e
]);
```

Monolog sẽ ghi:

- message
- file
- line
- stacktrace

---

# 8. Trace Flow bằng Info Log

Khi debug quy trình phức tạp:

```php
Log::info('Booking started');

Log::info('Checking seat');

Log::info('Calling airline API');

Log::info('Saving database');

Log::info('Booking completed');
```

Giúp theo dõi flow trên production.

---

# 9. Correlation ID (Rất quan trọng)

Giúp trace một request xuyên suốt nhiều service.

Middleware:

```php
$requestId = Str::uuid();

Log::withContext([
    'request_id' => $requestId
]);
```

Sau đó mọi log đều chứa:

```json
{
  "request_id": "8f5d..."
}
```

Dễ điều tra lỗi.

---

# 10. Log Queue Job

Trong Job:

```php
public function handle()
{
    Log::info('Send email job started');

    // ...

    Log::info('Send email success');
}
```

Nếu lỗi:

```php
public function failed(Throwable $e)
{
    Log::error('Send email failed', [
        'exception' => $e
    ]);
}
```

---

# 11. Log External API

```php
Log::info('Calling Flight API', [
    'url' => $url,
    'flight_id' => $flightId
]);
```

Sau response:

```php
Log::info('Flight API response', [
    'status' => $response->status()
]);
```

Nếu lỗi:

```php
Log::error('Flight API failed', [
    'exception' => $e
]);
```

---

# 12. Log Database Transaction

```php
DB::beginTransaction();

try {

    Log::info('Transaction started');

    // ...

    DB::commit();

    Log::info('Transaction committed');

} catch (Throwable $e) {

    DB::rollBack();

    Log::error('Transaction rollback', [
        'exception' => $e
    ]);

    throw $e;
}
```

---

# 13. Performance Logging

Đo thời gian xử lý:

```php
$start = microtime(true);

// logic

$time = round(
    microtime(true) - $start,
    2
);

Log::info('Booking execution time', [
    'seconds' => $time
]);
```

Hoặc:

```php
$start = hrtime(true);

// logic

$duration = (hrtime(true) - $start) / 1_000_000;

Log::info('Duration', [
    'ms' => $duration
]);
```

---

# 14. Tách Channel Theo Domain

Ví dụ:

```php
'booking' => [
    'driver' => 'daily',
    'path' => storage_path('logs/booking.log')
],

'payment' => [
    'driver' => 'daily',
    'path' => storage_path('logs/payment.log')
]
```

Sử dụng:

```php
Log::channel('payment')
    ->error('Payment failed');
```

Rất hữu ích cho microservice hoặc hệ thống lớn.

---

# 15. Không lạm dụng Debug Log

❌

```php
foreach ($users as $user) {

    Log::debug($user);

}
```

Có thể tạo hàng GB log.

Chỉ log:

- Điểm bắt đầu
- Điểm kết thúc
- Dữ liệu quan trọng

---

# 16. Không dùng Log trong Loop lớn

❌

```php
foreach ($rows as $row) {

    Log::info($row);

}
```

Có thể làm chậm hệ thống.

Thay vào đó:

```php
Log::info('Import completed', [
    'rows' => count($rows)
]);
```

---

# 17. Sử dụng tap() để custom Monolog

```php
'booking' => [
    'driver' => 'daily',
    'tap' => [
        App\Logging\CustomizeFormatter::class
    ]
]
```

Cho phép:

- JSON format
- Correlation ID
- Request ID
- Custom formatter

---

# 18. Sử dụng Structured JSON Logging (Production)

```php
{
  "level":"error",
  "message":"Booking failed",
  "flight_id":100,
  "user_id":25,
  "request_id":"abc"
}
```

Dễ tích hợp:

- ELK
- Loki
- Datadog
- CloudWatch

---

# 19. Log Request và Response

Middleware:

```php
Log::info('Incoming request', [
    'method' => request()->method(),
    'url' => request()->fullUrl()
]);
```

Response:

```php
Log::info('Response sent', [
    'status' => response()->status()
]);
```

Không log toàn bộ body nếu chứa dữ liệu nhạy cảm.

---

# 20. Scheduler Logging

```php
Schedule::command('booking:sync')
    ->daily()
    ->appendOutputTo(
        storage_path('logs/scheduler.log')
    );
```

---

# 21. Log Authentication Event

```php
Log::info('User login success', [
    'user_id' => auth()->id(),
    'ip' => request()->ip()
]);
```

```php
Log::warning('Login failed', [
    'email' => $request->email,
    'ip' => request()->ip()
]);
```

---

# 22. Tail Log Khi Development

```bash
tail -f storage/logs/laravel.log
```

Hoặc:

```bash
tail -100f storage/logs/laravel.log
```

Docker:

```bash
docker logs -f app
```

Supervisor Queue:

```bash
tail -f storage/logs/worker.log
```

---

# 23. Production Monitoring

File log chỉ là bước đầu.

Nên tích hợp:

### Sentry

Theo dõi exception và stacktrace.

### Slack

Thông báo lỗi critical.

### ELK Stack

ElasticSearch + Logstash + Kibana.

### Grafana + Loki

Centralized logging.

### Datadog

Application observability.

### CloudWatch

AWS logging.

---

# 24. Không dùng dd() trong Production

❌

```php
dd($data);
```

Làm dừng ứng dụng.

Thay bằng:

```php
Log::debug('Debug data', [
    'data' => $data
]);
```

---

# 25. Những thứ nên log

### Business Events

- Booking created
- Payment success
- Refund completed
- User login

### External API

- Request
- Response status
- Timeout

### Queue

- Started
- Completed
- Failed

### Transaction

- Begin
- Commit
- Rollback

### Exception

- Message
- Stacktrace

### Performance

- Execution time
- Slow query

---

# Checklist Best Practice

## Security

- [ ] Không log password
- [ ] Không log token
- [ ] Không log OTP
- [ ] Không log credit card

## Structure

- [ ] Dùng context array
- [ ] Dùng JSON log
- [ ] Correlation ID
- [ ] Domain channel

## Exception

- [ ] Log exception object
- [ ] Có stacktrace
- [ ] Không nuốt exception

## Performance

- [ ] Không log trong loop lớn
- [ ] Không spam debug log
- [ ] Rotate log file

## Monitoring

- [ ] Queue log
- [ ] Scheduler log
- [ ] API log
- [ ] Sentry/Slack integration

## Production

- [ ] Không dùng dd()
- [ ] Structured logging
- [ ] Centralized logging
- [ ] Trace request xuyên suốt hệ thống

---

# Khi Nào Cần Hệ Thống Logging Tập Trung

## Tổng quan

Khi ứng dụng còn nhỏ, log file trong:

```text
storage/logs/laravel.log
```

thường đã đủ để debug.

Tuy nhiên, khi hệ thống phát triển lớn hơn, nhiều service hơn hoặc yêu cầu khả năng quan sát (Observability) cao hơn, việc chỉ đọc log trên từng server riêng lẻ sẽ không còn hiệu quả.

Lúc này cần xây dựng hệ thống **Centralized Logging (Logging tập trung)**.

---

# Bài toán

Một số vấn đề thường gặp:

- Log nằm rải rác trên nhiều server.
- Khó tìm nguyên nhân gây lỗi.
- Không thể truy vết toàn bộ request.
- Không lưu trữ được log lâu dài.
- Không có cơ chế cảnh báo tự động.
- Không đáp ứng yêu cầu audit và compliance.

---

# Khi nào cần Logging tập trung

## 1. Hệ thống phân tán

Ví dụ:

```text
User
 ↓
API Gateway
 ↓
Booking Service
 ↓
Payment Service
 ↓
Notification Service
```

Mỗi service có log riêng.

Nếu chỉ SSH vào từng server để đọc:

```bash
tail -f storage/logs/laravel.log
```

thì rất khó xác định:

- Request đi qua những service nào.
- Lỗi xảy ra ở đâu.
- Service nào trả về timeout.

Logging tập trung giúp gom toàn bộ log về một nơi duy nhất.

---

## 2. Dự án Microservices hoặc Multi-node

Ví dụ:

```text
3 API servers
2 Queue workers
1 Scheduler server
1 Redis
1 Database
```

Mỗi node sinh log riêng.

Nếu một request thất bại:

- Server nào gây lỗi?
- Queue nào thất bại?
- Worker nào timeout?

Việc kiểm tra thủ công từng máy gần như không khả thi.

Centralized Logging cho phép tìm kiếm trên toàn bộ cluster.

---

## 3. Cần thu thập log để phân tích

Ví dụ cần biết:

- API nào lỗi nhiều nhất?
- Endpoint nào chậm nhất?
- User nào tạo nhiều request bất thường?
- Tỷ lệ lỗi 500 trong ngày hôm nay?

Những thống kê này gần như không thể thực hiện hiệu quả bằng cách đọc file log thủ công.

---

## 4. Muốn truy vết sự cố bài bản

Ví dụ:

Khách hàng phản ánh:

> Tôi đặt vé lúc 14:20 nhưng bị trừ tiền mà không nhận được vé.

Cần tìm:

```text
Request
 ↓
Booking Service
 ↓
Payment Gateway
 ↓
Queue
 ↓
Notification
```

Nếu có Correlation ID:

```text
request_id = c18f2ab4
```

có thể truy vết toàn bộ luồng xử lý chỉ trong vài giây.

---

## 5. Tính chất mất log của log file

Log file thông thường có thể mất khi:

- Server bị restart.
- Container bị xóa.
- Kubernetes pod bị recreate.
- Docker volume bị mất.
- Log rotate quá sớm.

Ví dụ:

```text
storage/logs/laravel.log
```

không nên được xem là nơi lưu trữ lâu dài.

Centralized Logging sẽ lưu log ra hệ thống riêng biệt.

---

## 6. Compliance và Audit

Một số lĩnh vực yêu cầu lưu log nhiều năm:

### Banking

- Audit giao dịch.
- Truy vết thay đổi dữ liệu.

### Healthcare

- Theo dõi truy cập hồ sơ bệnh án.

### E-commerce

- Lịch sử thanh toán.
- Refund.
- Fraud detection.

### Enterprise

- ISO 27001
- SOC2
- PCI DSS

Những yêu cầu này đòi hỏi:

- Log không được sửa.
- Log được lưu lâu dài.
- Có khả năng tìm kiếm nhanh.

---

## 7. Điều tra sự cố Production

Ví dụ:

```text
500 Internal Server Error
```

Nếu chỉ có log file:

Developer phải:

```bash
ssh server-1
tail -f laravel.log

ssh server-2
tail -f laravel.log

ssh worker-1
tail -f worker.log
```

Rất tốn thời gian.

Centralized Logging cho phép tìm kiếm:

```text
status=500
```

và thấy toàn bộ log liên quan.

---

## 8. Monitoring và Alerting

Ví dụ:

Nếu số lượng lỗi:

```text
ERROR > 100 lần / phút
```

thì:

- Gửi Slack.
- Gửi Email.
- Tạo PagerDuty Incident.

Nếu:

```text
Payment timeout > 5%
```

thì cảnh báo ngay.

Log file truyền thống không hỗ trợ điều này.

---

# Lợi ích của Logging tập trung

## Tìm kiếm nhanh chóng

Có thể search:

```text
user_id=123

request_id=abc

status=500

booking_id=100
```

trong vài giây.

---

## Lưu trữ lâu dài

Có thể lưu:

- 30 ngày
- 90 ngày
- 1 năm
- 7 năm

tùy chính sách của doanh nghiệp.

---

## Phân quyền cụ thể

Ví dụ:

### Developer

Được xem:

- Application logs

---

### DevOps

Được xem:

- System logs
- Infrastructure logs

---

### Security Team

Được xem:

- Audit logs

---

## Dashboard trực quan

Có thể thống kê:

- Error rate
- Request volume
- Top exception
- Slow API
- CPU usage

Thông qua:

- Grafana
- Kibana

---

## Tự động cảnh báo

Ví dụ:

```text
Redis down
Database timeout
Queue failed
Payment API unavailable
```

hệ thống sẽ gửi cảnh báo tự động.

---

## Hỗ trợ Incident Response

Giảm thời gian:

- Phát hiện sự cố.
- Điều tra nguyên nhân.
- Khắc phục lỗi.

Giúp giảm:

- MTTD (Mean Time To Detect)
- MTTR (Mean Time To Recovery)

---

## Hỗ trợ Root Cause Analysis

Có thể xác định:

```text
User request
 ↓
API Gateway
 ↓
Booking Service
 ↓
Payment Service
 ↓
Redis
 ↓
Database
```

Service nào là nguyên nhân gây lỗi.

---

# Các giải pháp phổ biến

## ELK Stack

```text
ElasticSearch
    +
Logstash
    +
Kibana
```

Ưu điểm:

- Mạnh mẽ.
- Search rất tốt.

---

## Grafana + Loki

```text
Promtail
    ↓
Loki
    ↓
Grafana
```

Ưu điểm:

- Nhẹ.
- Chi phí thấp.
- Dễ tích hợp.

---

## Sentry

Tập trung vào:

- Exception
- Stacktrace
- Error monitoring

---

## Datadog

Nền tảng Observability toàn diện:

- Logs
- Metrics
- Traces

---

## AWS CloudWatch

Phù hợp với hệ thống chạy trên AWS.

---

## Azure Monitor

Phù hợp với Azure.

---

# Khi nào chưa cần?

Chưa cần Logging tập trung nếu:

- Dự án nhỏ.
- Chỉ có một server.
- Ít traffic.
- Chưa có yêu cầu audit.
- Không cần lưu log lâu dài.

Khi đó:

```text
Laravel Daily Log
+
Sentry
```

thường đã đủ.

---

# Checklist

Nên cân nhắc Logging tập trung khi:

- [ ] Hệ thống nhiều server.
- [ ] Có Queue Worker riêng.
- [ ] Có nhiều microservices.
- [ ] Chạy Kubernetes.
- [ ] Cần audit.
- [ ] Cần lưu log lâu dài.
- [ ] Muốn tìm kiếm log nhanh.
- [ ] Muốn dashboard trực quan.
- [ ] Cần alert tự động.
- [ ] Muốn truy vết sự cố bài bản.

---

# Kết luận

Logging tập trung là một thành phần quan trọng của Observability.

Nó giúp:

- Thu thập log từ nhiều nguồn.
- Tìm kiếm nhanh.
- Lưu trữ lâu dài.
- Cảnh báo tự động.
- Truy vết lỗi chính xác.
- Hỗ trợ audit và compliance.

Đối với hệ thống lớn, Logging tập trung gần như là một yêu cầu bắt buộc thay vì một tính năng tùy chọn.

---

# Các Loại Log Trong Thực Tế

Trong một hệ thống thực tế, log không chỉ đơn giản là những dòng text được ghi vào file. Mỗi loại log phục vụ một mục đích khác nhau và được tạo ra bởi những thành phần khác nhau của hệ thống.

Hiểu rõ từng loại log giúp:

- Debug hiệu quả hơn.
- Theo dõi hành vi của ứng dụng.
- Điều tra sự cố production.
- Hỗ trợ observability.
- Đáp ứng yêu cầu audit và compliance.

---

# 1. Application Logs

## Khái niệm

Application Logs là các bản ghi được tạo ra bởi chính source code của ứng dụng trong quá trình chạy.

Chúng phản ánh:

- Hành vi của ứng dụng.
- Luồng xử lý nghiệp vụ.
- Trạng thái của hệ thống.
- Các exception và lỗi xảy ra.

Application Log ghi lại "vòng đời" của ứng dụng từ lúc nhận request cho đến khi hoàn thành xử lý.

Ví dụ:

```text
User Request
      ↓
Validation
      ↓
Business Logic
      ↓
Database
      ↓
External API
      ↓
Response
```

Trong suốt quá trình này, ứng dụng sẽ liên tục tạo log.

---

# Thông tin thường có trong Application Log

Một Application Log chuyên nghiệp thường bao gồm:

## Timestamp

Thời điểm sự kiện xảy ra.

Ví dụ:

```text
2026-06-18 10:15:20
```

Giúp trả lời:

- Sự kiện xảy ra lúc nào?
- Bao lâu sau thì lỗi xuất hiện?

---

## Log Level

Thể hiện mức độ nghiêm trọng.

Ví dụ:

```text
INFO
WARN
ERROR
DEBUG
```

Giúp:

- Lọc log dễ dàng.
- Ưu tiên xử lý sự cố.

---

## Component hoặc Class

Xác định phần nào của chương trình sinh ra log.

Ví dụ:

```text
BookingService

PaymentService

SearchController
```

Giúp:

- Xác định vị trí gây lỗi.
- Khoanh vùng nhanh hơn.

---

## Message

Nội dung chi tiết của sự kiện.

Ví dụ:

```text
Payment completed successfully.

Datacom API timeout.

Booking created.
```

---

## Context

Thông tin bổ sung đi kèm.

Ví dụ:

```json
{
  "user_id": 15,
  "booking_id": 100,
  "flight_id": 200
}
```

Structured Logging giúp các hệ thống như:

- ELK
- Loki
- Datadog
- Sentry

có thể phân tích log dễ dàng hơn.

---

# Tại sao Application Log quan trọng?

Khác với:

- System Logs (ghi lại hoạt động của hệ điều hành).
- Access Logs (ghi lại HTTP request).

Application Logs phản ánh trực tiếp business logic của hệ thống.

Đây là công cụ quan trọng nhất để hiểu ứng dụng đang làm gì.

---

# Application Log trả lời những câu hỏi nào?

## Background Job có thực sự chạy xong không?

Ví dụ:

```text
SendInvoiceJob started

↓

SendInvoiceJob completed
```

Nếu chỉ có:

```text
SendInvoiceJob started
```

thì có thể job đã bị crash giữa chừng.

---

## Tại sao giao dịch thanh toán thất bại?

Ví dụ:

```text
Payment started

↓

Call Payment Gateway

↓

Gateway timeout

↓

Transaction rollback
```

Developer có thể nhanh chóng xác định nguyên nhân.

---

## Một API xử lý mất bao lâu?

Ví dụ:

```text
Search started

↓

Search completed

duration=523ms
```

Giúp:

- Phân tích hiệu năng.
- Tìm bottleneck.

---

## Tại sao người dùng cụ thể gặp lỗi?

Ví dụ:

```text
user_id=123

booking_id=456

Payment failed
```

Có thể truy vết theo:

- user_id
- booking_id
- request_id

---

# Ví dụ Application Log

```text
2026-06-18 10:20:15 INFO BookingService
Booking created successfully
{
    "booking_id":100,
    "user_id":15
}
```

---

```text
2026-06-18 10:20:16 INFO PaymentService
Calling payment gateway
{
    "booking_id":100
}
```

---

```text
2026-06-18 10:20:20 ERROR PaymentService
Gateway timeout
{
    "booking_id":100
}
```

---

```text
2026-06-18 10:20:21 INFO BookingService
Transaction rollback
{
    "booking_id":100
}
```

---

# Tập trung hóa Application Logs

Khi hệ thống lớn hơn:

```text
API Gateway
Booking Service
Payment Service
Notification Service
Queue Workers
```

Application Logs sẽ nằm trên nhiều server khác nhau.

Việc SSH vào từng máy để:

```bash
tail -f laravel.log
```

trở nên rất khó khăn.

Do đó, log thường được tập trung về một nơi duy nhất thông qua:

- ELK Stack
- Grafana + Loki
- Datadog
- CloudWatch
- Sentry

---

# Kết hợp với Metrics và Traces

Application Logs chỉ là một phần của Observability.

Thông thường chúng được kết hợp với:

## Metrics

Ví dụ:

- CPU Usage
- Memory Usage
- Request Per Second
- Error Rate

---

## Traces

Theo dõi một request xuyên suốt:

```text
API Gateway
    ↓
Booking Service
    ↓
Payment Service
    ↓
Notification Service
```

Thông qua:

```text
trace_id
request_id
```

---

Việc kết hợp:

```text
Logs
+
Metrics
+
Traces
```

giúp điều tra các hệ thống phân tán trở nên dễ dàng hơn.

---

# Kết luận

Application Logs là nguồn thông tin quan trọng nhất để hiểu business logic của hệ thống.

Chúng giúp trả lời:

- Job đã chạy xong chưa?
- Tại sao giao dịch thất bại?
- API mất bao lâu để xử lý?
- Người dùng nào gặp lỗi?
- Hệ thống đang hoạt động như thế nào?

Trong các hệ thống hiện đại, Application Logs thường được tập trung hóa và kết hợp với Metrics cùng Traces để tạo thành nền tảng Observability hoàn chỉnh.

---

# 2. System Logs

## Khái niệm

Nếu **Application Logs** phản ánh logic nghiệp vụ của ứng dụng, thì **System Logs** cung cấp khả năng quan sát đối với hạ tầng và môi trường mà ứng dụng đang chạy.

System Logs giống như "hồ sơ sức khỏe" của hệ điều hành và các service nền tảng.

Chúng ghi lại:

- Trạng thái của hệ điều hành.
- Sự kiện kernel.
- Trạng thái phần cứng.
- Hoạt động của service nền.
- Các sự kiện bảo mật.

Khác với Application Logs được sinh ra từ source code, System Logs được sinh ra bởi:

- Linux Kernel
- systemd
- Network stack
- Driver
- Authentication service
- Các daemon chạy nền

---

# Table of Contents

1. [Khái niệm](#khái-niệm)
2. [System Logs ghi lại những gì?](#system-logs-ghi-lại-những-gì)
    - [Kernel Events](#kernel-events)
    - [Hardware và Driver Status](#hardware-và-driver-status)
    - [Service Lifecycle](#service-lifecycle)
    - [Security và Access Logs](#security-và-access-logs)
3. [Tại sao System Logs quan trọng?](#tại-sao-system-logs-quan-trọng)
4. [Tại sao cần Centralized Logging cho System Logs?](#tại-sao-cần-centralized-logging-cho-system-logs)
    - [Correlating Errors](#correlating-errors)
    - [Predictive Maintenance](#predictive-maintenance)
    - [Security Forensics](#security-forensics)
5. [Thu thập System Logs như thế nào?](#thu-thập-system-logs-như-thế-nào)
6. [Ví dụ System Logs](#ví-dụ-system-logs)
7. [Một số vị trí log phổ biến trên Linux](#một-số-vị-trí-log-phổ-biến-trên-linux)
8. [Kết luận](#kết-luận)

---

# System Logs ghi lại những gì?

## Kernel Events

Kernel là thành phần cốt lõi của hệ điều hành.

System Logs ghi lại:

- CPU errors
- Memory errors
- ACPI events
- Disk I/O failures
- OOM Killer

Ví dụ:

```text
kernel: Out of memory: Kill process 1234 (php-fpm)
```

Hoặc:

```text
kernel: CPU0 temperature above threshold
```

Những log này rất quan trọng khi điều tra:

- Server treo
- CPU quá tải
- Memory leak

---

## Hardware và Driver Status

System Logs ghi lại trạng thái:

- Card mạng
- Ổ cứng
- Filesystem
- Driver

Ví dụ:

```text
eth0: Link is Up
```

Hoặc:

```text
EXT4-fs mounted filesystem
```

Giúp phát hiện:

- Network disconnect
- Disk failure
- Mount lỗi

---

## Service Lifecycle

Linux sử dụng:

```text
systemd
```

để quản lý các service.

System Logs ghi lại:

- Service start
- Service stop
- Service restart
- Service crash

Ví dụ:

```text
systemd:
Started nginx.service
```

Hoặc:

```text
systemd:
php-fpm.service failed
```

Giúp xác định:

- Service nào bị chết.
- Service nào restart liên tục.

---

## Security và Access Logs

System Logs ghi lại:

- Login thành công.
- Login thất bại.
- sudo command.
- SSH access.

Ví dụ:

```text
Failed password for root from 192.168.1.10
```

Hoặc:

```text
Accepted password for ubuntu
```

Các log này thường nằm ở:

```text
/var/log/auth.log
```

Chúng rất quan trọng trong:

- Security monitoring.
- Audit.
- Incident response.

---

# Tại sao System Logs quan trọng?

Nhiều khi ứng dụng chạy chậm không phải do code.

Ví dụ:

```text
Application timeout
```

nguyên nhân thực sự có thể là:

- CPU 100%
- Disk I/O bottleneck
- Memory exhaustion
- Network issue

Application Logs chỉ cho thấy:

```text
Request timeout
```

System Logs mới cho biết nguyên nhân gốc rễ.

---

# Tại sao cần Centralized Logging cho System Logs?

## Correlating Errors

Ví dụ:

Application Log:

```text
Payment API timeout
```

Trong cùng thời điểm, System Log:

```text
Disk IO wait high
```

hoặc:

```text
Out of memory
```

Kết hợp hai loại log giúp xác định:

- Lỗi không nằm ở code.
- Server đang quá tải.

---

## Predictive Maintenance

Ví dụ:

System Logs liên tục xuất hiện:

```text
I/O error on sda
```

Điều này có thể báo hiệu:

- Ổ cứng sắp hỏng.

Nếu được phát hiện sớm:

- Có thể backup.
- Thay ổ cứng trước khi server sập.

---

## Security Forensics

Ví dụ:

```text
Failed password for root
```

xuất hiện hàng nghìn lần trên nhiều server.

Centralized Logging cho phép:

- Phát hiện brute-force attack.
- Xác định IP tấn công.
- Truy vết toàn bộ hệ thống.

---

# Thu thập System Logs như thế nào?

Trong môi trường hiện đại, mỗi server thường chạy một agent.

Ví dụ:

```text
Filebeat
Fluentd
Promtail
CloudWatch Agent
```

Agent sẽ:

```text
Server
    ↓
System Logs
    ↓
Agent
    ↓
Centralized Logging Platform
```

Ví dụ:

```text
Filebeat
    ↓
ElasticSearch
    ↓
Kibana
```

hoặc:

```text
Promtail
    ↓
Loki
    ↓
Grafana
```

---

# Ví dụ System Logs

Kernel:

```text
kernel:
Out of memory:
Kill process 1234 (php-fpm)
```

---

Network:

```text
eth0:
Link is Up
```

---

Filesystem:

```text
EXT4-fs mounted filesystem
```

---

Service:

```text
systemd:
Started nginx.service
```

---

Authentication:

```text
Failed password for root from 192.168.1.100
```

---

# Một số vị trí log phổ biến trên Linux

## System Logs

```text
/var/log/syslog
```

hoặc:

```text
/var/log/messages
```

---

## Authentication Logs

```text
/var/log/auth.log
```

---

## Kernel Logs

```text
dmesg
```

---

## systemd Journal

Xem bằng:

```bash
journalctl
```

Ví dụ:

```bash
journalctl -u nginx
```

Hoặc:

```bash
journalctl -xe
```

---

# Kết luận

System Logs phản ánh tình trạng của:

- Hệ điều hành.
- Kernel.
- Hardware.
- Network.
- Service nền.
- Security.

Chúng đóng vai trò rất quan trọng trong:

- Root Cause Analysis.
- Performance troubleshooting.
- Predictive maintenance.
- Security forensics.

Trong các hệ thống hiện đại, System Logs thường được thu thập thông qua các agent như:

- Filebeat
- Fluentd
- Promtail
- CloudWatch Agent

và được gửi đến các nền tảng Centralized Logging như:

- ELK Stack
- Grafana + Loki
- Datadog
- CloudWatch

để phục vụ mục đích:

- Giám sát.
- Điều tra sự cố.
- Bảo mật.
- Audit.
- Observability.

---

# 3. Infrastructure / Platform Logs

## Khái niệm

Nếu:

- **Application Logs** phản ánh logic nghiệp vụ của ứng dụng.
- **System Logs** phản ánh tình trạng của hệ điều hành.

thì **Infrastructure / Platform Logs** phản ánh trạng thái của tầng nền tảng (platform layer), nơi điều phối và kết nối các service với nhau.

Chúng thường được sinh ra bởi:

- API Gateway
- Reverse Proxy
- Load Balancer
- Ingress Controller
- Service Mesh
- Message Broker
- Container Runtime

Platform Logs giống như "hệ thống giao thông" của toàn bộ hệ thống.

---

# Table of Contents

1. [Khái niệm](#khái-niệm)
2. [Infrastructure Logs ghi lại những gì?](#infrastructure-logs-ghi-lại-những-gì)
    - [Request Lifecycle](#request-lifecycle)
    - [Latency Monitoring](#latency-monitoring)
    - [Service Routing](#service-routing)
    - [Configuration Issues](#configuration-issues)
3. [Các thành phần thường sinh Platform Logs](#các-thành-phần-thường-sinh-platform-logs)
4. [Tại sao Platform Logs quan trọng?](#tại-sao-platform-logs-quan-trọng)
5. [Vai trò trong Centralized Logging](#vai-trò-trong-centralized-logging)
    - [Single Pane of Glass](#single-pane-of-glass)
    - [Performance Insights](#performance-insights)
    - [Security và Traffic Analysis](#security-và-traffic-analysis)
6. [Ví dụ Platform Logs](#ví-dụ-platform-logs)
7. [Kết hợp với Logs, Metrics và Traces](#kết-hợp-với-logs-metrics-và-traces)
8. [Kết luận](#kết-luận)

---

# Infrastructure Logs ghi lại những gì?

## Request Lifecycle

Platform logs theo dõi toàn bộ vòng đời của request.

Ví dụ:

```text
User
 ↓
API Gateway
 ↓
Load Balancer
 ↓
Product Service
 ↓
Database
```

Thông tin thường được ghi lại:

```text
method=GET
path=/products
status=200
request_id=abc123
```

Giúp trả lời:

- Request đã đi qua đâu?
- Request thất bại ở tầng nào?

---

## Latency Monitoring

Platform logs ghi lại thời gian xử lý request.

Ví dụ:

```text
latency=45ms
```

hoặc:

```text
upstream_response_time=1.8s
```

Giúp phát hiện:

- Bottleneck.
- Service phản hồi chậm.
- Timeout.

---

## Service Routing

Trong microservices:

```text
API Gateway
 ↓
Product Service
 ↓
Inventory Service
 ↓
Payment Service
```

Platform logs ghi lại:

```text
service=product-service
```

hoặc:

```text
upstream=inventory-service
```

Giúp:

- Xác định request được chuyển đến service nào.
- Debug lỗi routing.

---

## Configuration Issues

Ví dụ:

```text
TLS certificate missing
```

hoặc:

```text
Upstream unavailable
```

hoặc:

```text
No healthy backend
```

Giúp phát hiện:

- Sai cấu hình.
- Service không khả dụng.
- Lỗi SSL/TLS.

---

# Các thành phần thường sinh Platform Logs

## API Gateway

Ví dụ:

- Kong
- Apisix
- NGINX Gateway

---

## Reverse Proxy

Ví dụ:

- Nginx
- Traefik
- HAProxy

---

## Service Mesh

Ví dụ:

- Istio
- Linkerd

---

## Ingress Controller

Ví dụ:

- Nginx Ingress
- Traefik Ingress

---

## Message Broker

Ví dụ:

- RabbitMQ
- Kafka

---

## Container Platform

Ví dụ:

- Docker
- Kubernetes

---

# Tại sao Platform Logs quan trọng?

Trong microservices:

```text
User
 ↓
Gateway
 ↓
Auth Service
 ↓
Product Service
 ↓
Payment Service
 ↓
Notification Service
```

Một request có thể đi qua rất nhiều tầng.

Nếu không có Platform Logs, rất khó trả lời:

- Request bị lỗi ở đâu?
- Service nào timeout?
- Gateway trả về 503 vì lý do gì?
- Load balancer có đang route đúng không?

---

# Vai trò trong Centralized Logging

## Single Pane of Glass

Platform Logs giúp truy vết request xuyên suốt toàn hệ thống.

Ví dụ:

```text
request_id=8f3b4c
```

có thể được dùng để theo dõi:

```text
API Gateway
 ↓
Auth Service
 ↓
Product Service
 ↓
Payment Service
```

Giúp debug nhanh các lỗi:

```text
503 Upstream Timeout
502 Bad Gateway
504 Gateway Timeout
```

---

## Performance Insights

Ví dụ:

Platform Logs cho biết:

```text
product-service latency=45ms
```

nhưng:

```text
payment-service latency=3.2s
```

Giúp:

- Phát hiện bottleneck.
- Scale service phù hợp.
- Tránh outage.

---

## Security và Traffic Analysis

Ví dụ:

```text
POST /login
10000 requests/minute
```

hoặc:

```text
Repeated access to /admin
```

Giúp phát hiện:

- Brute force attack.
- DDoS.
- Traffic bất thường.

---

# Ví dụ Platform Logs

Kong Gateway:

```text
kong-proxy
request_id=abc123
method=GET
path=/products
status=200
latency=45ms
service=product-service
```

---

Traefik:

```text
traefik
entrypoint=websecure
status=503
upstream_timeout=true
```

---

Nginx:

```text
10.0.0.1 - GET /products HTTP/1.1
200
upstream_response_time=0.23
```

---

Istio:

```text
source=api-gateway
destination=payment-service
response_code=500
duration=2.3s
```

---

# Kết hợp với Logs, Metrics và Traces

Platform Logs hoàn thiện khả năng observability khi kết hợp với:

## Logs

- Application Logs
- System Logs
- Infrastructure Logs

---

## Metrics

Ví dụ:

- CPU
- Memory
- Requests Per Second
- Error Rate

---

## Traces

Theo dõi request:

```text
Gateway
 ↓
Auth Service
 ↓
Product Service
 ↓
Payment Service
```

Thông qua:

```text
trace_id
request_id
```

Ba thành phần:

```text
Logs
+
Metrics
+
Traces
```

được gọi là:

```text
Three Pillars of Observability
```

---

# Kết luận

Infrastructure / Platform Logs phản ánh trạng thái của tầng nền tảng chịu trách nhiệm điều phối lưu lượng giữa các service.

Chúng giúp:

- Theo dõi request lifecycle.
- Phân tích latency.
- Debug routing.
- Phát hiện lỗi cấu hình.
- Quan sát traffic.
- Điều tra sự cố microservices.

Trong các hệ thống hiện đại, Platform Logs thường được thu thập từ:

- API Gateway
- Reverse Proxy
- Service Mesh
- Kubernetes
- Message Broker

và được gửi về các hệ thống Centralized Logging như:

- ELK Stack
- Grafana + Loki
- Datadog
- CloudWatch

để hỗ trợ:

- Observability.
- Root Cause Analysis.
- Performance Troubleshooting.
- Security Monitoring.

---

# 4. Access Logs

## Khái niệm

Access Logs ghi lại toàn bộ các request đi vào hệ thống.

Chúng trả lời các câu hỏi:

- Ai truy cập hệ thống?
- Truy cập vào đâu?
- Truy cập lúc nào?
- Kết quả trả về là gì?

Access Logs thường được sinh ra bởi:

- Nginx
- Apache
- Load Balancer
- API Gateway
- Reverse Proxy
- CDN

Khác với:

- Application Logs → phản ánh business logic.
- System Logs → phản ánh hệ điều hành.
- Platform Logs → phản ánh hạ tầng và middleware.

Access Logs tập trung vào lưu lượng truy cập (traffic) của hệ thống.

---

# Table of Contents

1. [Khái niệm](#khái-niệm)
2. [Access Logs ghi lại những gì?](#access-logs-ghi-lại-những-gì)
    - [Client IP](#client-ip)
    - [Timestamp](#timestamp)
    - [HTTP Method và Path](#http-method-và-path)
    - [Status Code](#status-code)
    - [User-Agent](#user-agent)
3. [Tại sao Access Logs quan trọng?](#tại-sao-access-logs-quan-trọng)
4. [Vai trò trong Centralized Logging](#vai-trò-trong-centralized-logging)
    - [Security Monitoring](#security-monitoring)
    - [User Behavior Analysis](#user-behavior-analysis)
    - [Troubleshooting Failures](#troubleshooting-failures)
5. [Ví dụ Access Logs](#ví-dụ-access-logs)
6. [Các công cụ sinh Access Logs](#các-công-cụ-sinh-access-logs)
7. [Kết hợp với các loại log khác](#kết-hợp-với-các-loại-log-khác)
8. [Kết luận](#kết-luận)

---

# Access Logs ghi lại những gì?

## Client IP

Địa chỉ IP của client gửi request.

Ví dụ:

```text
192.168.1.20
```

Giúp trả lời:

- Request đến từ đâu?
- Người dùng nào đang truy cập?
- Có dấu hiệu tấn công từ IP nào không?

---

## Timestamp

Thời điểm request xảy ra.

Ví dụ:

```text
2026-06-18 10:20:15
```

Giúp:

- Điều tra sự cố.
- Truy vết theo thời gian.

---

## HTTP Method và Path

Ví dụ:

```text
GET /api/v1/products

POST /api/v1/login
```

Giúp biết:

- Người dùng đang truy cập endpoint nào.
- Hành động nào được thực hiện.

---

## Status Code

Ví dụ:

```text
200 OK
304 Not Modified
401 Unauthorized
404 Not Found
500 Internal Server Error
```

Giúp:

- Phân tích lỗi.
- Theo dõi tỷ lệ thất bại.

---

## User-Agent

Thông tin trình duyệt hoặc client.

Ví dụ:

```text
Mozilla/5.0

PostmanRuntime

curl
```

Giúp biết:

- Người dùng đang dùng trình duyệt nào.
- Request đến từ API client hay browser.

---

# Tại sao Access Logs quan trọng?

Access Logs phản ánh toàn bộ traffic của hệ thống.

Chúng giúp trả lời:

- Ai đang sử dụng hệ thống?
- Endpoint nào được gọi nhiều nhất?
- Bao nhiêu request thất bại?
- Lưu lượng tăng đột biến ở đâu?
- Người dùng gặp lỗi gì?

---

# Vai trò trong Centralized Logging

## Security Monitoring

Ví dụ:

```text
POST /api/v1/login
status=401
```

lặp lại hàng nghìn lần từ:

```text
192.168.1.100
```

Điều này có thể là:

- Brute force attack.
- Credential stuffing.

Hoặc:

```text
GET /admin
```

liên tục từ nhiều IP khác nhau.

Có thể là:

- Port scanning.
- Reconnaissance attack.

---

## User Behavior Analysis

Access Logs giúp trả lời:

- API nào được dùng nhiều nhất?
- Khung giờ nào traffic cao nhất?
- Browser nào phổ biến nhất?
- Tỷ lệ mobile và desktop?

Ví dụ:

```text
GET /products
100000 requests/day
```

Giúp:

- Capacity planning.
- Product analytics.

---

## Troubleshooting Failures

Người dùng báo:

> Tôi không truy cập được trang sản phẩm.

Developer có thể tìm:

```text
IP address
```

hoặc:

```text
timestamp
```

để xem:

```text
GET /products
status=404
```

hoặc:

```text
status=500
```

Từ đó tiếp tục truy vết sang:

- Application Logs.
- Platform Logs.
- System Logs.

---

# Ví dụ Access Logs

Nginx:

```text
192.168.1.20
GET /api/v1/products
200
Mozilla/5.0
```

---

Apache:

```text
192.168.1.25
POST /api/v1/login
401
curl/7.85.0
```

---

Load Balancer:

```text
10.0.0.5
GET /checkout
503
```

---

API Gateway:

```text
request_id=abc123
method=GET
path=/orders
status=200
```

---

# Các công cụ sinh Access Logs

## Web Server

- Nginx
- Apache

---

## Reverse Proxy

- Traefik
- HAProxy

---

## API Gateway

- Kong
- Apisix

---

## Load Balancer

- AWS ALB
- NLB

---

## CDN

- Cloudflare
- Akamai

---

# Kết hợp với các loại log khác

Access Logs chỉ cho biết:

```text
GET /checkout
status=500
```

Nhưng không cho biết nguyên nhân.

Cần kết hợp với:

## Application Logs

Cho biết:

```text
Payment timeout
```

---

## Platform Logs

Cho biết:

```text
Gateway timeout
```

---

## System Logs

Cho biết:

```text
Out of memory
```

---

Ví dụ:

```text
Access Log
↓
status=500

Application Log
↓
Payment API timeout

Platform Log
↓
upstream timeout

System Log
↓
Disk IO high
```

Giúp thực hiện:

```text
Root Cause Analysis
```

một cách đầy đủ.

---

# Kết hợp với Metrics và Traces

Trong observability hiện đại:

```text
Logs
+
Metrics
+
Traces
```

Access Logs là nguồn dữ liệu quan trọng cho:

- Request rate
- Error rate
- Peak traffic
- User behavior
- Security analysis

---

# Kết luận

Access Logs ghi lại toàn bộ request đến hệ thống và trả lời:

- Ai truy cập?
- Truy cập vào đâu?
- Khi nào?
- Kết quả trả về là gì?

Chúng đóng vai trò quan trọng trong:

- Security Monitoring.
- User Behavior Analysis.
- Troubleshooting.
- Capacity Planning.
- Root Cause Analysis.

Khi được tập trung hóa cùng với:

- Application Logs
- System Logs
- Infrastructure Logs

Access Logs giúp tạo nên một hệ sinh thái observability hoàn chỉnh, cho phép theo dõi và điều tra hệ thống hiệu quả hơn.

---

# 5. Security / Audit Logs

## Khái niệm

Security Logs và Audit Logs là "hồ sơ pháp y" (forensic record) của hệ thống.

Khác với:

- Application Logs → phục vụ debug nghiệp vụ.
- System Logs → theo dõi hệ điều hành.
- Access Logs → theo dõi traffic.

Security / Audit Logs tập trung vào:

- Bảo mật.
- Quyền truy cập.
- Truy vết hành động.
- Đáp ứng yêu cầu compliance.

Mục tiêu chính của chúng là:

- Accountability.
- Non-repudiation.
- Incident Response.
- Compliance.

---

# Table of Contents

1. [Khái niệm](#khái-niệm)
2. [Security / Audit Logs ghi lại những gì?](#security--audit-logs-ghi-lại-những-gì)
    - [Authentication Attempts](#authentication-attempts)
    - [Privilege Changes](#privilege-changes)
    - [Critical Data Operations](#critical-data-operations)
    - [Resource Access](#resource-access)
3. [Tại sao Security / Audit Logs quan trọng?](#tại-sao-security--audit-logs-quan-trọng)
4. [Vai trò trong Centralized Logging](#vai-trò-trong-centralized-logging)
    - [Non-Repudiation](#non-repudiation)
    - [Compliance Requirements](#compliance-requirements)
    - [Incident Response](#incident-response)
    - [Real-Time Alerting](#real-time-alerting)
5. [Ví dụ Security / Audit Logs](#ví-dụ-security--audit-logs)
6. [Các hệ thống thường sinh Audit Logs](#các-hệ-thống-thường-sinh-audit-logs)
7. [Best Practices](#best-practices)
8. [Kết luận](#kết-luận)

---

# Security / Audit Logs ghi lại những gì?

## Authentication Attempts

Theo dõi:

- Login thành công.
- Login thất bại.
- MFA failures.
- SSH access.

Ví dụ:

```text
Failed login attempt
user=admin
ip=192.168.1.100
```

Hoặc:

```text
Successful login
user=john
```

Giúp phát hiện:

- Brute force attack.
- Credential stuffing.
- Unauthorized access.

---

## Privilege Changes

Theo dõi việc thay đổi quyền.

Ví dụ:

```text
granted SELECT on db.customers to analyst
```

Hoặc:

```text
role admin assigned to user_id=15
```

Giúp trả lời:

- Ai đã cấp quyền?
- Quyền được cấp khi nào?
- Quyền nào đã được thay đổi?

---

## Critical Data Operations

Theo dõi các thao tác nguy hiểm.

Ví dụ:

```sql
DROP TABLE customers
```

Hoặc:

```sql
DELETE FROM orders
```

Đặc biệt quan trọng đối với:

- root
- super admin
- DBA

Giúp điều tra:

- Data corruption.
- Insider threat.
- Human error.

---

## Resource Access

Theo dõi:

- Ai truy cập file nào.
- Ai tải dữ liệu nào.
- Ai truy cập secret hoặc config.

Ví dụ:

```text
user=admin
accessed /etc/secrets
```

Hoặc:

```text
user=finance_manager
downloaded payroll.xlsx
```

---

# Tại sao Security / Audit Logs quan trọng?

Chúng không chỉ phục vụ debug.

Chúng là bằng chứng để trả lời:

- Ai đã làm gì?
- Làm khi nào?
- Làm trên tài nguyên nào?
- Có thay đổi gì xảy ra không?

Đây là nền tảng của:

```text
Accountability
```

---

# Vai trò trong Centralized Logging

## Non-Repudiation

Một hành động phải được ghi lại:

```text
User
Time
Action
Resource
```

Ví dụ:

```text
2026-06-18 10:20:15

user=admin

DELETE customer_id=100
```

Giúp đảm bảo:

Một người không thể phủ nhận rằng họ đã thực hiện hành động đó.

---

## Compliance Requirements

Nhiều tiêu chuẩn yêu cầu lưu Audit Logs.

### PCI-DSS

Đối với:

- Payment systems.
- Credit card information.

---

### HIPAA

Đối với:

- Healthcare.
- Medical records.

---

### SOC2

Đối với:

- SaaS.
- Cloud platforms.

---

### ISO 27001

Đối với:

- Information Security Management.

---

Các tiêu chuẩn này yêu cầu:

- Lưu log lâu dài.
- Không được chỉnh sửa.
- Có khả năng truy vết.

---

## Incident Response

Nếu hệ thống bị tấn công:

Security Logs giúp trả lời:

### Attacker vào bằng cách nào?

Ví dụ:

```text
SSH login success
```

---

### Họ đã làm gì?

Ví dụ:

```sql
DROP TABLE customers
```

---

### Dữ liệu nào bị truy cập?

Ví dụ:

```text
SELECT * FROM payroll
```

---

### Dữ liệu nào bị đánh cắp?

Thông qua:

- Download logs.
- Database audit logs.

---

## Real-Time Alerting

Ví dụ:

Nếu phát hiện:

```text
root login
```

thì:

- Gửi Slack.
- Gửi Email.
- PagerDuty Alert.

---

Nếu:

```sql
DROP TABLE
```

thì:

- Tạo incident ngay lập tức.

---

Nếu:

```text
1000 failed login attempts
```

thì:

- Khóa IP.
- Gửi cảnh báo Security Team.

---

# Ví dụ Security / Audit Logs

Authentication:

```text
Failed login attempt
user=admin
ip=192.168.1.100
```

---

Privilege Escalation:

```text
Granted role admin to user_id=15
```

---

Database:

```sql
DROP TABLE customers
```

---

Resource Access:

```text
user=finance_manager
downloaded payroll.xlsx
```

---

SSH:

```text
Accepted password for root
```

---

# Các hệ thống thường sinh Audit Logs

## Operating System

Ví dụ:

```text
auth.log
```

---

## Database

Ví dụ:

- MySQL Audit Plugin
- PostgreSQL Audit Extension

---

## Cloud Platform

Ví dụ:

- AWS CloudTrail
- Azure Activity Logs
- GCP Audit Logs

---

## Kubernetes

Ví dụ:

```text
Kubernetes Audit Logs
```

---

## IAM

Ví dụ:

- Keycloak
- Okta
- Active Directory

---

## Application

Ví dụ:

```text
User changed email

User updated password

User exported customer data
```

---

# Best Practices

## Không cho phép chỉnh sửa Audit Logs

Audit Logs nên:

- Immutable.
- Write-only.

---

## Lưu trữ lâu dài

Ví dụ:

- 90 ngày
- 1 năm
- 7 năm

tùy chính sách.

---

## Không log dữ liệu nhạy cảm

Không log:

- Password.
- OTP.
- Credit card number.
- Access token.

---

## Tách riêng Security Logs

Không trộn chung với:

- Application Logs.
- Access Logs.

---

## Thiết lập cảnh báo

Alert khi:

- Root login.
- Privilege escalation.
- Massive delete.
- Brute force attack.

---

# Kết luận

Security / Audit Logs là nền tảng của:

- Security Monitoring.
- Incident Response.
- Compliance.
- Forensics.

Chúng giúp trả lời:

- Ai đã làm gì?
- Khi nào?
- Trên tài nguyên nào?
- Có dữ liệu nào bị thay đổi hay đánh cắp không?

Trong các hệ thống hiện đại, Audit Logs thường được bảo vệ nghiêm ngặt hơn các loại log khác và được tích hợp với hệ thống Centralized Logging để hỗ trợ:

- Real-time alerting.
- Threat detection.
- Compliance.
- Security investigation.

Đối với các hệ thống tài chính, healthcare hoặc enterprise, Security / Audit Logs gần như là yêu cầu bắt buộc thay vì một tính năng tùy chọn.

---

# 6. Event Logs

## Khái niệm

Event Logs là các bản ghi mô tả các sự kiện ở mức nghiệp vụ (business-level events) hoặc các sự kiện hệ thống ở mức high-level.

Khác với:

- Application Logs → tập trung vào chi tiết kỹ thuật.
- Access Logs → tập trung vào HTTP requests.
- System Logs → tập trung vào hệ điều hành.

Event Logs trả lời câu hỏi:

> Điều gì đã xảy ra trong hệ thống?

Chúng mô tả:

- Các cột mốc của nghiệp vụ.
- Các hành động của người dùng.
- Vòng đời của một đối tượng business.

Ví dụ:

```text
User joined project

Order placed

Payment completed

Subscription cancelled
```

Event Logs thường được biểu diễn dưới dạng:

```json
{
    "event": "order_created",
    "order_id": 100,
    "user_id": 15
}
```

---

# Table of Contents

1. [Khái niệm](#khái-niệm)
2. [Event Logs ghi lại những gì?](#event-logs-ghi-lại-những-gì)
3. [Tại sao Event Logs khác với Application Logs?](#tại-sao-event-logs-khác-với-application-logs)
4. [Các loại Event phổ biến](#các-loại-event-phổ-biến)
5. [Giá trị của Centralized Event Logging](#giá-trị-của-centralized-event-logging)
    - [Business Intelligence](#business-intelligence)
    - [Customer Support](#customer-support)
    - [Workflow Audit](#workflow-audit)
6. [Ví dụ Event Logs](#ví-dụ-event-logs)
7. [Event Logs trong Microservices](#event-logs-trong-microservices)
8. [Best Practices](#best-practices)
9. [Kết luận](#kết-luận)

---

# Event Logs ghi lại những gì?

Event Logs ghi nhận:

## Business Actions

Ví dụ:

```text
User registered

User joined project

Order created

Payment completed

Refund requested

Subscription cancelled
```

---

## System Milestones

Ví dụ:

```text
Cache warmed

Data synchronization completed

Nightly batch job finished
```

---

## Domain Events

Ví dụ:

```text
BookingCreated

BookingCancelled

PaymentSucceeded

TicketIssued
```

Những event này thường phản ánh các trạng thái quan trọng của business.

---

# Tại sao Event Logs khác với Application Logs?

Application Log:

```text
Calling PaymentService

Response status=200

Transaction committed
```

Mang tính kỹ thuật.

---

Event Log:

```text
Payment completed
```

Mang tính nghiệp vụ.

---

Application Log trả lời:

> Hệ thống đã làm gì?

Event Log trả lời:

> Chuyện gì đã xảy ra?

---

Ví dụ:

Application Log:

```text
INSERT INTO orders
```

Event Log:

```text
Order created
```

---

# Các loại Event phổ biến

## User Events

Ví dụ:

```text
UserRegistered

UserLoggedIn

UserChangedPassword
```

---

## Order Events

Ví dụ:

```text
OrderCreated

OrderPaid

OrderCancelled
```

---

## Booking Events

Ví dụ:

```text
BookingCreated

BookingConfirmed

TicketIssued
```

---

## Payment Events

Ví dụ:

```text
PaymentSucceeded

PaymentFailed

RefundCompleted
```

---

## Subscription Events

Ví dụ:

```text
SubscriptionStarted

SubscriptionExpired

SubscriptionCancelled
```

---

# Giá trị của Centralized Event Logging

## Business Intelligence

Khi Event Logs được tập trung hóa, có thể phân tích:

- Bao nhiêu đơn hàng mỗi ngày?
- Bao nhiêu người dùng đăng ký mới?
- Bao nhiêu booking bị hủy?
- Tỷ lệ thanh toán thành công?

Ví dụ:

```text
order_created
10000/day
```

Giúp:

- Product analytics.
- Capacity planning.
- KPI dashboard.

Không cần query database phức tạp.

---

## Customer Support

Khách hàng nói:

> Tôi đã thanh toán nhưng chưa nhận được vé.

Có thể tìm:

```text
user_id=15
```

và thấy:

```text
BookingCreated

↓

PaymentSucceeded

↓

TicketIssued
```

hoặc:

```text
PaymentSucceeded

↓

TicketIssueFailed
```

Giúp support xác định vấn đề nhanh chóng.

---

## Workflow Audit

Có thể theo dõi toàn bộ vòng đời của một đối tượng.

Ví dụ:

```text
OrderCreated
      ↓
PaymentSucceeded
      ↓
InventoryReserved
      ↓
ShipmentCreated
      ↓
OrderCompleted
```

Nếu bị kẹt:

```text
OrderCreated
      ↓
PaymentSucceeded
      ↓
InventoryReserved
```

thì biết quy trình dừng ở bước nào.

---

# Ví dụ Event Logs

```json
{
  "event": "user_joined_project",
  "user_id": 15,
  "project_id": 100,
  "timestamp": "2026-06-18T10:20:15Z"
}
```

---

```json
{
  "event": "order_created",
  "order_id": 123,
  "user_id": 15
}
```

---

```json
{
  "event": "payment_succeeded",
  "payment_id": 1000,
  "amount": 250000
}
```

---

```json
{
  "event": "ticket_issued",
  "booking_id": 888
}
```

---

# Event Logs trong Microservices

Ví dụ:

```text
Booking Service
      ↓
BookingCreated

Payment Service
      ↓
PaymentSucceeded

Ticket Service
      ↓
TicketIssued

Notification Service
      ↓
EmailSent
```

Các event giúp xây dựng timeline:

```text
BookingCreated
      ↓
PaymentSucceeded
      ↓
TicketIssued
      ↓
EmailSent
```

Điều này rất hữu ích cho:

- Saga Pattern.
- Event-driven architecture.
- Workflow tracing.

---

# Best Practices

## Dùng tên event rõ ràng

Nên:

```text
OrderCreated

PaymentSucceeded

BookingCancelled
```

Không nên:

```text
Done

Update

Process
```

---

## Event nên immutable

Sau khi ghi:

```text
PaymentSucceeded
```

không nên sửa lại.

---

## Structured Logging

Nên dùng JSON:

```json
{
    "event":"booking_created",
    "booking_id":100,
    "user_id":15
}
```

---

## Có timestamp

Ví dụ:

```text
2026-06-18T10:20:15Z
```

---

## Có correlation_id hoặc trace_id

Giúp truy vết:

```text
BookingCreated
↓
PaymentSucceeded
↓
TicketIssued
```

trên nhiều service.

---

# Kết luận

Event Logs ghi lại các sự kiện ở mức nghiệp vụ thay vì chi tiết kỹ thuật.

Chúng giúp:

- Theo dõi hành vi người dùng.
- Phân tích business.
- Hỗ trợ customer support.
- Audit quy trình.
- Xây dựng dashboard BI.
- Truy vết workflow trong microservices.

Khi được tập trung hóa cùng với:

- Application Logs
- System Logs
- Platform Logs
- Access Logs
- Security Logs

Event Logs giúp tạo ra một hệ thống observability hoàn chỉnh, kết nối giữa góc nhìn kỹ thuật và góc nhìn nghiệp vụ của doanh nghiệp.

---

# 7. Trace Logs (Distributed Tracing)

## Khái niệm

Trong kiến trúc Monolith truyền thống, một request thường chỉ đi qua một ứng dụng.

Nhưng trong kiến trúc Microservices:

```text
User
 ↓
API Gateway
 ↓
Auth Service
 ↓
Order Service
 ↓
Payment Service
 ↓
Inventory Service
 ↓
Notification Service
```

Một request có thể đi qua hàng chục service khác nhau.

Nếu chỉ dùng Application Logs thông thường, rất khó nhìn thấy bức tranh tổng thể.

Trace Logs (Distributed Tracing) được sinh ra để giải quyết vấn đề này.

Chúng cho phép theo dõi toàn bộ hành trình của một request xuyên qua nhiều service.

---

# Table of Contents

1. [Khái niệm](#khái-niệm)
2. [Trace Logs hoạt động như thế nào?](#trace-logs-hoạt-động-như-thế-nào)
    - [Trace ID](#trace-id)
    - [Span ID](#span-id)
    - [Parent ID](#parent-id)
3. [Ví dụ một Trace hoàn chỉnh](#ví-dụ-một-trace-hoàn-chỉnh)
4. [Tại sao Trace Logs quan trọng?](#tại-sao-trace-logs-quan-trọng)
5. [Các vấn đề Trace Logs giải quyết](#các-vấn-đề-trace-logs-giải-quyết)
    - [Visualizing Bottlenecks](#visualizing-bottlenecks)
    - [Debugging Failures](#debugging-failures)
    - [Dependency Mapping](#dependency-mapping)
6. [Ví dụ Trace Logs](#ví-dụ-trace-logs)
7. [Trace Logs trong Microservices](#trace-logs-trong-microservices)
8. [OpenTelemetry và Distributed Tracing](#opentelemetry-và-distributed-tracing)
9. [Best Practices](#best-practices)
10. [Kết luận](#kết-luận)

---

# Trace Logs hoạt động như thế nào?

Distributed Tracing sử dụng các ID đặc biệt để kết nối các service lại với nhau.

---

## Trace ID

Mỗi request được gán một ID duy nhất ngay từ đầu.

Ví dụ:

```text
trace_id = abc123
```

ID này được giữ nguyên trong toàn bộ vòng đời của request.

Ví dụ:

```text
Gateway
 ↓
Auth Service
 ↓
Order Service
 ↓
Payment Service
```

Tất cả đều dùng:

```text
trace_id = abc123
```

Nhờ vậy ta có thể ghép các log lại thành một chuỗi hoàn chỉnh.

---

## Span ID

Mỗi thao tác bên trong request được gọi là một Span.

Ví dụ:

```text
Gateway
span_id=1

↓

Auth Service
span_id=2

↓

Order Service
span_id=3

↓

Payment Service
span_id=4
```

Mỗi Span có:

- Start time
- End time
- Duration
- Metadata

---

## Parent ID

Parent ID xác định span nào gọi span hiện tại.

Ví dụ:

```text
Gateway
span=1

↓

Order Service
span=2
parent=1

↓

Payment Service
span=3
parent=2
```

Nhờ đó hệ thống xây dựng được cây thực thi:

```text
Gateway
└── Order Service
     └── Payment Service
```

---

# Ví dụ một Trace hoàn chỉnh

```text
User
 ↓
API Gateway
 ↓
Auth Service
 ↓
Order Service
 ↓
Payment Service
 ↓
Notification Service
```

Tất cả đều mang:

```text
trace_id=abc123
```

Ví dụ:

```text
Gateway
duration=20ms

Auth Service
duration=50ms

Order Service
duration=80ms

Payment Service
duration=3.2s

Notification Service
duration=30ms
```

Nhìn vào trace có thể thấy ngay:

```text
Payment Service
```

là bottleneck.

---

# Tại sao Trace Logs quan trọng?

Application Logs cho biết:

```text
Payment timeout
```

Nhưng không cho biết:

- Request đã đi qua đâu?
- Service nào gây chậm?
- Request bị chết ở đâu?

Distributed Tracing trả lời được tất cả những câu hỏi đó.

---

# Các vấn đề Trace Logs giải quyết

## Visualizing Bottlenecks

Ví dụ:

```text
Auth Service
40ms

Order Service
80ms

Payment Service
3200ms
```

Có thể xác định ngay:

```text
Payment Service
```

là nguyên nhân làm chậm hệ thống.

---

## Debugging Failures

Ví dụ:

Request trả về:

```text
504 Gateway Timeout
```

Trace cho thấy:

```text
Gateway
 ↓

Order Service
 ↓

Payment Service
(status=504)
```

Ta biết ngay lỗi xảy ra tại:

```text
Payment Service
```

Sau đó có thể tìm:

```text
trace_id=abc123
```

trong:

- Application Logs
- Platform Logs
- Access Logs

để điều tra sâu hơn.

---

## Dependency Mapping

Trace tự động mô tả quan hệ giữa các service.

Ví dụ:

```text
Gateway
├── Auth Service
├── Product Service
│     └── Inventory Service
└── Payment Service
```

Điều này giúp hiểu:

- Kiến trúc thực tế của hệ thống.
- Service nào phụ thuộc service nào.

Đôi khi khác hoàn toàn với sơ đồ thiết kế ban đầu.

---

# Ví dụ Trace Logs

Gateway:

```json
{
    "trace_id":"abc123",
    "span_id":"1",
    "service":"gateway",
    "duration":"20ms"
}
```

---

Auth Service:

```json
{
    "trace_id":"abc123",
    "span_id":"2",
    "parent_id":"1",
    "service":"auth-service",
    "duration":"50ms"
}
```

---

Order Service:

```json
{
    "trace_id":"abc123",
    "span_id":"3",
    "parent_id":"2",
    "service":"order-service",
    "duration":"80ms"
}
```

---

Payment Service:

```json
{
    "trace_id":"abc123",
    "span_id":"4",
    "parent_id":"3",
    "service":"payment-service",
    "duration":"3200ms",
    "status":"504"
}
```

---

# Trace Logs trong Microservices

Ví dụ:

```text
Booking Service
 ↓

Payment Service
 ↓

Ticket Service
 ↓

Notification Service
```

Nếu Ticket Service bị timeout:

```text
Booking
 ↓
Payment
 ↓
Ticket
(X)
```

Trace sẽ cho thấy chính xác:

- Service nào lỗi.
- Service nào chậm.
- Request chết ở đâu.

---

# OpenTelemetry và Distributed Tracing

Ngày nay hầu hết hệ thống sử dụng:

```text
OpenTelemetry (OTel)
```

để thu thập:

- Logs
- Metrics
- Traces

và gửi đến:

- Jaeger
- Tempo
- Zipkin
- Datadog
- New Relic

Ví dụ:

```text
Application
 ↓
OpenTelemetry SDK
 ↓
Collector
 ↓
Jaeger
```

---

# Best Practices

## Luôn truyền trace_id giữa các service

Ví dụ:

```text
Gateway
 ↓
Order Service
 ↓
Payment Service
```

Tất cả phải dùng cùng:

```text
trace_id
```

---

## Kết hợp Trace với Logs

Ví dụ:

```json
{
    "trace_id":"abc123",
    "message":"Payment timeout"
}
```

Giúp tìm log theo trace rất nhanh.

---

## Đo duration của từng span

Ví dụ:

```text
DB Query
40ms

Redis
5ms

External API
2500ms
```

Giúp phát hiện bottleneck.

---

## Sử dụng OpenTelemetry

Đây là chuẩn phổ biến hiện nay.

---

# Three Pillars of Observability

Observability hiện đại gồm:

```text
Logs
+
Metrics
+
Traces
```

Trong đó:

### Logs

Cho biết:

```text
Điều gì đã xảy ra?
```

---

### Metrics

Cho biết:

```text
Hệ thống khỏe hay không?
```

---

### Traces

Cho biết:

```text
Request đã đi qua đâu?
```

---

# Kết luận

Trace Logs (Distributed Tracing) cho phép theo dõi toàn bộ hành trình của một request xuyên qua nhiều service.

Chúng giúp:

- Truy vết request end-to-end.
- Xác định bottleneck.
- Debug lỗi trong microservices.
- Hiểu dependency giữa các service.
- Phân tích hiệu năng.

Ngày nay, Distributed Tracing là thành phần không thể thiếu của các hệ thống hiện đại và là một trong ba trụ cột của Observability:

```text
Logs
+
Metrics
+
Traces
```

Thường được triển khai thông qua:

- OpenTelemetry
- Jaeger
- Tempo
- Zipkin
- Datadog
- New Relic

---

# Log Retention Policy

## Mục đích

Log Retention Policy quy định thời gian lưu trữ log đối với từng môi trường nhằm:

- Hỗ trợ debugging.
- Điều tra sự cố (Incident Investigation).
- Đáp ứng yêu cầu Audit và Compliance.
- Cân bằng giữa chi phí lưu trữ và khả năng truy vết.

---

# Table of Contents

1. Development Environment
2. Testing / Staging Environment
3. Production Environment
4. Compliance Requirements
5. Best Practices
6. Ví dụ cấu hình Laravel

---

# Development Environment

## Mục tiêu

Phục vụ:

- Debug hàng ngày.
- Phát triển tính năng.
- Reproduce bug.

## Thời gian lưu trữ

```text
7 ngày
```

## Lý do

- Log cũ ít giá trị.
- Giảm dung lượng ổ cứng.
- Dễ quản lý.

## Ví dụ

Laravel:

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=7
```

---

# Testing / Staging Environment

## Mục tiêu

Phục vụ:

- QA testing.
- UAT.
- Reproduce production bug.

## Thời gian lưu trữ

```text
7 ngày
```

Có thể tăng lên:

```text
14 ngày
```

nếu chu kỳ test dài.

## Ví dụ

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=7
```

---

# Production Environment

## Mục tiêu

Phục vụ:

- Incident investigation.
- Root Cause Analysis.
- Audit.
- Security investigation.
- Compliance.

---

## Mức tối thiểu

Khuyến nghị:

```text
90 ngày
```

Đây là mức phổ biến cho:

- SaaS
- E-commerce
- Enterprise applications

Ví dụ:

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=90
```

---

## Khuyến nghị thực tế

Nên lưu:

```text
1 năm
```

đối với:

- Financial systems
- Enterprise applications
- B2B SaaS
- Healthcare systems

Điều này giúp:

- Điều tra sự cố muộn.
- Truy vết các vấn đề bảo mật.
- Đáp ứng yêu cầu audit.

---

## Long-term Archive

Một số doanh nghiệp lưu:

```text
3 năm
5 năm
7 năm
```

thông qua:

- S3 Glacier
- Cold Storage
- Backup Archive

để phục vụ:

- Compliance.
- Legal.
- Forensics.

---

# Compliance Requirements

| Tiêu chuẩn | Thời gian lưu khuyến nghị |
|-------------|--------------------------|
| PCI-DSS | ≥ 1 năm |
| SOC2 | ≥ 1 năm |
| HIPAA | 6 năm |
| ISO 27001 | Theo chính sách tổ chức |
| Internal Audit | 1-7 năm |

Lưu ý:

Thời gian lưu cụ thể phụ thuộc vào:

- Quốc gia.
- Ngành nghề.
- Quy định nội bộ doanh nghiệp.

---

# Best Practices

## Development

```text
7 ngày
```

Không nên lưu quá lâu.

---

## Staging

```text
7 ngày
```

hoặc:

```text
14 ngày
```

---

## Production

Không nên dưới:

```text
90 ngày
```

Khuyến nghị:

```text
1 năm
```

---

## Security / Audit Logs

Khuyến nghị:

```text
1 năm
```

hoặc nhiều hơn.

---

## Centralized Logging

Không phụ thuộc vào:

```text
storage/logs/*.log
```

Thay vào đó:

```text
Application
    ↓
Filebeat / FluentBit
    ↓
ELK / Loki / Datadog
    ↓
Hot Storage (90 days)
    ↓
Cold Storage (1-7 years)
```

---

## Thiết lập Rotation

Tránh:

```text
laravel.log
```

phình lên hàng GB.

Nên dùng:

```env
LOG_CHANNEL=daily
```

và tự động xóa log hết hạn.

---

## Audit Logs

Không được:

- Chỉnh sửa.
- Xóa thủ công.

Nên:

- Immutable.
- Write-only.
- Archive dài hạn.

---

# Ví dụ cấu hình Laravel

## Development

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=7
```

---

## Testing / Staging

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=7
```

---

## Production

Mức tối thiểu:

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=90
```

Khuyến nghị:

```env
LOG_CHANNEL=daily
LOG_DAILY_DAYS=365
```

---

# Khuyến nghị tổng quát

| Environment | Retention |
|--------------|-----------|
| Development | 7 ngày |
| Testing / Staging | 7 ngày |
| Production (minimum) | 90 ngày |
| Production (recommended) | 1 năm |
| Security / Audit Logs | ≥ 1 năm |
| Archive / Compliance | 3-7 năm |

---

# Kết luận

Một chính sách lưu trữ log phổ biến trong thực tế:

```text
DEV
7 ngày

TEST / STAGING
7 ngày

PRODUCTION
≥ 90 ngày

PRODUCTION (recommended)
1 năm

AUDIT / SECURITY
1-7 năm

ARCHIVE
3-7 năm
```

Nguyên tắc chung:

> Development tối ưu chi phí và tốc độ, còn Production tối ưu khả năng điều tra sự cố, bảo mật và tuân thủ (Compliance).
```