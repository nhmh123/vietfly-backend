# Load Testing Strategy for Laravel Backend (VietFly)

## Mục tiêu

Load testing giúp đánh giá khả năng chịu tải của hệ thống, phát hiện bottleneck, tối ưu hiệu năng và xác minh các cơ chế như Redis Cache, Rate Limiting, Circuit Breaker trước khi đưa vào production.

---

# Công cụ

| Tool | Mục đích |
|-------|----------|
| k6 | Load Test / Stress Test API |
| Postman | Functional Test |
| PostgreSQL EXPLAIN ANALYZE | Phân tích Query |
| Laravel Log | Theo dõi request |
| Redis | Cache & Rate Limiter |
| Docker Stats | CPU / RAM Container |
| Grafana (Optional) | Dashboard |
| Prometheus (Optional) | Metrics |
| Loki / ELK (Optional) | Log Aggregation |

---

# Kịch bản cần kiểm thử

## 1. Smoke Test

### Mục tiêu

Kiểm tra API còn hoạt động.

### Kịch bản

```
1 User
1 Request
```

Ví dụ

```
POST /api/flights/search
```

Kỳ vọng

- HTTP 200
- Response đúng format

---

## 2. Baseline Test

### Mục tiêu

Đo hiệu năng bình thường của hệ thống.

### Kịch bản

```
5~10 Virtual Users
2 phút
```

Thu thập

- Average Response Time
- P95
- Requests/sec
- Error Rate

---

## 3. Load Test

### Mục tiêu

Mô phỏng lượng người dùng thực tế.

### Ví dụ

```
20~50 VUs
5 phút
```

Payload

```
SGN -> HAN
HAN -> DAD
SGN -> DAD
```

Quan sát

- CPU
- RAM
- DB Connections
- Response Time

---

## 4. Stress Test

### Mục tiêu

Tìm giới hạn chịu tải.

### Ví dụ

```
10 Users

↓

50 Users

↓

100 Users

↓

200 Users
```

Quan sát

- Timeout
- HTTP 500
- Database bottleneck
- CPU 100%

---

## 5. Spike Test

### Mục tiêu

Mô phỏng lượng truy cập tăng đột biến.

Ví dụ

```
5 Users

↓

200 Users

↓

5 Users
```

Đây là tình huống thường gặp khi mở bán vé hoặc Flash Sale.

---

# Redis Cache Test

## Mục tiêu

Đánh giá hiệu quả cache.

### Cold Cache

```
Payload khác nhau
```

Ví dụ

```
SGN -> HAN

SGN -> DAD

HAN -> PQC
```

Kỳ vọng

```
Cache Miss
DB Query
```

---

### Warm Cache

```
100 request giống nhau
```

Ví dụ

```
SGN -> HAN
2026-08-01
```

Kỳ vọng

```
Redis Hit
Không Query Database
```

Log

```json
{
    "cache_hit": true,
    "duration_ms": 18
}
```

So sánh

| Cold Cache | Warm Cache |
|------------|------------|
| DB Query | Redis |
| Chậm hơn | Nhanh hơn |

---

# Database Performance Test

## Mục tiêu

Kiểm tra hiệu quả Index.

Các truy vấn

- Search Route
- Search Date
- Filter Airline
- Sort Price
- Pagination

Thực hiện

```
EXPLAIN ANALYZE
```

Quan sát

- Seq Scan
- Index Scan
- Bitmap Scan
- Query Cost
- Execution Time

Sau đó

```
Tạo Index

↓

EXPLAIN ANALYZE lại

↓

So sánh kết quả
```

---

# Rate Limiting Test

## Mục tiêu

Ngăn spam API Search.

Ví dụ

```
10 requests/minute/IP
```

Reproduce

```
1 IP

↓

Spam 100 request

↓

Laravel trả HTTP 429
```

Log

```json
{
    "event": "rate_limit_exceeded",
    "ip": "192.168.1.10",
    "path": "/api/flights/search",
    "user_agent": "...",
    "payload_hash": "...",
    "time": "..."
}
```

Phân tích

- Có bao nhiêu IP spam
- Spam endpoint nào
- Payload có giống nhau không
- User Agent bất thường

---

# Error Handling Test

## Mục tiêu

Kiểm tra khả năng xử lý lỗi.

Các tình huống

### Database Timeout

```
DB chậm
```

---

### Redis Down

```
Redis không hoạt động
```

---

### Provider Timeout

```
Mock API Timeout
```

---

### Provider HTTP 500

```
External API Error
```

---

### Payload Invalid

```
Validation Error
```

Kỳ vọng

- Không crash
- Log đầy đủ
- Trả đúng HTTP Status
- Không lộ Stack Trace

---

# Circuit Breaker Test

## Mục tiêu

Bảo vệ hệ thống khi Provider lỗi liên tục.

Ví dụ

```
Provider

↓

500

↓

500

↓

500

↓

500

↓

500

↓

Circuit Open

↓

Không gọi Provider nữa
```

Sau cooldown

```
Half Open

↓

Thử 1 request

↓

Nếu thành công

↓

Close Circuit
```

Quan sát

- Failure Count
- Circuit State
- Recovery Time

---

# Logging cần thu thập

Mỗi request nên ghi

```json
{
    "request_id": "...",
    "ip": "...",
    "endpoint": "...",
    "duration_ms": 120,
    "status": 200,
    "cache_hit": false,
    "query_count": 3,
    "memory_mb": 32
}
```

---

# Metrics cần theo dõi

API

- Requests/sec
- Average Response Time
- P95
- Error Rate

Database

- Query Time
- Index Scan
- Seq Scan
- Connections

Redis

- Cache Hit
- Cache Miss
- Memory Usage

Laravel

- CPU
- Memory
- Request Duration

---

# Thứ tự thực hiện

1. Smoke Test
2. Baseline Test
3. Database Performance Test
4. Redis Cache Test
5. Rate Limiting Test
6. Load Test
7. Stress Test
8. Spike Test
9. Error Handling Test
10. Circuit Breaker Test

---

# Mục tiêu cuối cùng

Sau khi hoàn thành các bài kiểm thử này, backend cần chứng minh được:

- Có khả năng chịu tải ở mức tải dự kiến.
- Query Database được tối ưu bằng Index.
- Redis Cache giúp giảm số lần truy vấn Database.
- Rate Limiter bảo vệ API trước hành vi spam.
- Circuit Breaker giúp hệ thống không phụ thuộc hoàn toàn vào Provider.
- Logging đầy đủ để phục vụ việc điều tra và phân tích sự cố.
- Có thể trình bày rõ ràng quá trình đo lường, tối ưu và kết quả trong buổi phỏng vấn hoặc khi review kiến trúc hệ thống.