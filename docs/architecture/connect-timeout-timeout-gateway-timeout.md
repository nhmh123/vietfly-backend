# Connect Timeout vs Timeout vs Gateway Timeout

## Mục tiêu

Ba khái niệm này xảy ra ở các giai đoạn khác nhau của quá trình gọi API
tới provider.

## 1. Connect Timeout

-   Không thiết lập được kết nối TCP tới provider.
-   Không có HTTP response.
-   Thường do sai host, sai port, DNS, firewall hoặc server down.
-   Laravel thường ném `ConnectionException`.
-   Mapping:
    -   Internal code: `PROVIDER_CONNECTION_FAILED`
    -   HTTP: `502 Bad Gateway`

## 2. Timeout (Read Timeout)

-   Đã kết nối được.
-   Provider xử lý quá lâu.
-   Laravel hết thời gian chờ (`Http::timeout()`) và tự hủy request.
-   Không có HTTP response.
-   Laravel thường ném `ConnectionException` (ví dụ cURL error 28).
-   Mapping:
    -   Internal code: `PROVIDER_TIMEOUT`
    -   HTTP: `504 Gateway Timeout`

Ví dụ:

``` php
Http::timeout(5)
```

Provider:

``` js
await sleep(15000)
```

## 3. Gateway Timeout (HTTP 504)

-   Provider hoặc gateway trả về HTTP 504.
-   Laravel nhận được HTTP response.
-   Không có `ConnectionException`.
-   Xử lý bằng `mapHttpError(504)`.
-   Mapping:
    -   Internal code: `PROVIDER_GATEWAY_TIMEOUT`
    -   HTTP: `504 Gateway Timeout`

## So sánh

  ------------------------------------------------------------------------------------------------------
  Tiêu chí          Connect Timeout              Timeout                      Gateway Timeout
  ----------------- ---------------------------- ---------------------------- --------------------------
  Kết nối được      ❌                           ✅                           ✅
  provider                                                                    

  Có HTTP response  ❌                           ❌                           ✅

  Laravel nhận      ConnectionException          ConnectionException          HTTP 504

  Xử lý             catch(ConnectionException)   catch(ConnectionException)   mapHttpError(504)

  Internal code     PROVIDER_CONNECTION_FAILED   PROVIDER_TIMEOUT             PROVIDER_GATEWAY_TIMEOUT
  ------------------------------------------------------------------------------------------------------

## Mock Server

### Connect timeout

-   Tắt mock server.
-   Sai host hoặc port.

### Timeout

``` js
case 'timeout':
  await sleep(15000);
```

Laravel:

``` php
Http::timeout(5)
```

### Gateway timeout

``` js
case 'gateway-timeout':
  return res.status(504).json({
    StatusCode: '5040',
    Success: false,
    Message: 'Gateway timeout'
  });
```

## Best Practice

-   Không gộp ba loại lỗi này.
-   Connect timeout và timeout không có HTTP response.
-   HTTP 504 là response thật từ provider/gateway.
-   Frontend chỉ nhận mã lỗi nội bộ (`code`) và message đã được Laravel
    chuẩn hóa.
