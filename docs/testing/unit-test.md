# Test Cases - SearchFlightRequest

## TC001 - Tìm chuyến một chiều hợp lệ

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 1,
  "children": 0,
  "infants": 0
}
```

### Expected

- HTTP 200
- Validation thành công

---

## TC002 - Tìm chuyến khứ hồi hợp lệ

### Request

```json
{
  "trip_type": "round-trip",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "return_date": "2026-07-05",
  "adults": 2,
  "children": 1,
  "infants": 1
}
```

### Expected

- HTTP 200
- Validation thành công

---

## TC003 - Thiếu trip_type

### Request

```json
{
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "trip_type": [
      "Vui lòng chọn loại hình chuyến đi."
    ]
  }
}
```

HTTP 422

---

## TC004 - trip_type không hợp lệ

### Request

```json
{
  "trip_type": "abc",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "trip_type": [
      "Loại hình chuyến đi không hợp lệ."
    ]
  }
}
```

HTTP 422

---

## TC005 - Mã sân bay đi không đủ 3 ký tự

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HA",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "origin": [
      "Mã sân bay phải gồm đúng 3 ký tự."
    ]
  }
}
```

HTTP 422

---

## TC006 - Điểm đi trùng điểm đến

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "HAN",
  "departure_date": "2026-07-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "destination": [
      "Sân bay đến không được trùng với sân bay đi."
    ]
  }
}
```

HTTP 422

---

## TC007 - Ngày khởi hành trong quá khứ

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2020-01-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "departure_date": [
      "Ngày khởi hành không được là ngày trong quá khứ."
    ]
  }
}
```

HTTP 422

---

## TC008 - Ngày khởi hành sai định dạng

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "01/07/2026",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "departure_date": [
      "Ngày khởi hành phải theo định dạng YYYY-MM-DD."
    ]
  }
}
```

HTTP 422

---

## TC009 - Ngày về nhỏ hơn ngày đi

### Request

```json
{
  "trip_type": "round-trip",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-10",
  "return_date": "2026-07-05",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "return_date": [
      "Ngày về phải sau hoặc trùng với ngày khởi hành."
    ]
  }
}
```

HTTP 422

---

## TC010 - Chuyến khứ hồi nhưng thiếu ngày về

### Request

```json
{
  "trip_type": "round-trip",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "return_date": [
      "Ngày về là bắt buộc đối với chuyến đi khứ hồi."
    ]
  }
}
```

HTTP 422

---

## TC011 - Chuyến một chiều nhưng truyền ngày về

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "return_date": "2026-07-05",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "return_date": [
      "Ngày về không được phép có đối với chuyến đi một chiều."
    ]
  }
}
```

HTTP 422

---

## TC012 - Không có người lớn

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 0
}
```

### Expected

```json
{
  "errors": {
    "adults": [
      "Số lượng người lớn tối thiểu là 1."
    ]
  }
}
```

HTTP 422

---

## TC013 - Quá 9 người lớn

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 10
}
```

### Expected

```json
{
  "errors": {
    "adults": [
      "Tối đa 9 hành khách."
    ]
  }
}
```

HTTP 422

---

## TC014 - Số em bé lớn hơn số người lớn

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "adults": 1,
  "infants": 2
}
```

### Expected

```json
{
  "errors": {
    "infants": [
      "Mỗi người lớn chỉ được đi kèm tối đa 1 em bé."
    ]
  }
}
```

HTTP 422

---

## TC015 - Ngày khởi hành vượt quá 1 năm kể từ hôm nay

### Request

```json
{
  "trip_type": "one-way",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2028-01-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "departure_date": [
      "Ngày khởi hành không được vượt quá 1 năm kể từ hôm nay."
    ]
  }
}
```

HTTP 422

---

## TC016 - Ngày về vượt quá 1 năm kể từ hôm nay

### Request

```json
{
  "trip_type": "round-trip",
  "origin": "HAN",
  "destination": "SGN",
  "departure_date": "2026-07-01",
  "return_date": "2028-01-01",
  "adults": 1
}
```

### Expected

```json
{
  "errors": {
    "return_date": [
      "Ngày về không được vượt quá 1 năm kể từ hôm nay."
    ]
  }
}
```

HTTP 422