# Third-Party Error Handling (Laravel)

## Mục tiêu

Khi tích hợp với hệ thống bên thứ ba (Datacom, GDS, Payment Gateway...),
**không để lỗi từ provider đi thẳng tới frontend**.

Flow chuẩn:

``` text
Frontend
    ↓
Controller
    ↓
Service
    ↓
DatacomAdapter
    ↓
Third-party Provider
```

## Trách nhiệm của từng tầng

### Controller

-   Nhận request từ client.
-   Gọi Service.
-   Trả HTTP response.
-   Bắt exception hoặc để Global Exception Handler xử lý.

Không nên: - Parse response của provider. - Kiểm tra HTTP status của
provider.

------------------------------------------------------------------------

### Service

-   Điều phối business flow.
-   Gọi Provider Port/Adapter.
-   Map request/response của ứng dụng.
-   Kết hợp cache, transaction hoặc nhiều provider nếu cần.

Không nên: - Biết chi tiết HTTP 401/500/504. - Trả JSON response.

------------------------------------------------------------------------

### DatacomAdapter

-   Giao tiếp trực tiếp với provider.
-   Gửi HTTP request.
-   Parse JSON.
-   Kiểm tra schema.
-   Chuyển lỗi của provider thành exception nội bộ.

Ví dụ các lỗi xử lý tại Adapter: - Timeout - Connection failed - HTTP
401/403/429/500/502/503/504 - Invalid JSON - Invalid schema - Business
error (`Success = false`)

------------------------------------------------------------------------

## Nguyên tắc

Adapter chỉ: - return dữ liệu thành công - hoặc throw ProviderException

Ví dụ:

``` text
Datacom trả 504
        ↓
DatacomAdapter
        ↓
throw ProviderException(PROVIDER_GATEWAY_TIMEOUT)
```

------------------------------------------------------------------------

## Vì sao không return JSON trong Adapter?

Adapter là tầng Infrastructure.

Nếu Adapter trả:

``` php
return response()->json(...);
```

thì Adapter sẽ phụ thuộc vào: - Laravel HTTP - Frontend response format

Điều này làm giảm khả năng tái sử dụng.

------------------------------------------------------------------------

## Vì sao Controller (hoặc Global Handler) trả JSON?

Đây là tầng giao tiếp với client.

Ví dụ:

``` json
{
  "success": false,
  "code": "PROVIDER_GATEWAY_TIMEOUT",
  "message": "Flight provider did not respond in time."
}
```

Frontend luôn nhận một format thống nhất.

------------------------------------------------------------------------

## Mapping lỗi

  Provider            Internal Code
  ------------------- ----------------------------
  401                 PROVIDER_UNAUTHORIZED
  403                 PROVIDER_FORBIDDEN
  429                 PROVIDER_RATE_LIMITED
  500                 PROVIDER_SERVER_ERROR
  502                 PROVIDER_BAD_GATEWAY
  503                 PROVIDER_UNAVAILABLE
  504                 PROVIDER_GATEWAY_TIMEOUT
  Timeout             PROVIDER_TIMEOUT
  Connection failed   PROVIDER_CONNECTION_FAILED
  Invalid JSON        PROVIDER_INVALID_JSON
  Invalid schema      PROVIDER_INVALID_SCHEMA
  Success=false       PROVIDER_BUSINESS_ERROR

------------------------------------------------------------------------

## Quy tắc vàng

❌ Không expose lỗi của provider:

``` html
504 Gateway Time-out
nginx/1.31.2
```

❌ Không expose stack trace.

✅ Luôn trả JSON chuẩn:

``` json
{
  "success": false,
  "code": "PROVIDER_UNAVAILABLE",
  "message": "Flight provider is temporarily unavailable."
}
```

------------------------------------------------------------------------

## Checklist kiểm thử

-   success
-   empty
-   business-error
-   invalid-json
-   invalid-schema
-   timeout
-   connect-timeout
-   server-error
-   bad-gateway
-   service-unavailable
-   gateway-timeout
-   unauthorized
-   forbidden
-   rate-limit

------------------------------------------------------------------------

## Tóm tắt

-   Adapter hiểu provider.
-   Service hiểu nghiệp vụ.
-   Controller/Exception Handler hiểu HTTP.
-   Frontend chỉ nhận response JSON thống nhất.
