# Client-side Filter vs Server-side Filter

## Mục đích

Tài liệu này mô tả khi nào nên xử lý filter ở frontend và khi nào nên xử lý ở backend trong dự án VietFly.

---

# Client-side Filter

## Định nghĩa

Backend trả về toàn bộ dữ liệu cần thiết.

Frontend thực hiện:

- Filter
- Sort
- Search
- Group

mà không gọi API lại.

Ví dụ:

```
Search Flights
      │
      ▼
Laravel
      │
32 flights
      ▼
Frontend
      │
├── Airline
├── Stop
├── Price
├── Duration
└── Departure Time
```

## Ưu điểm

- UI phản hồi tức thì
- Không phải gọi API nhiều lần
- Giảm tải backend
- UX tốt

## Nhược điểm

- Không phù hợp với tập dữ liệu lớn
- Browser phải giữ toàn bộ dữ liệu

## Áp dụng trong VietFly

Hiện tại Search API trả toàn bộ danh sách chuyến bay.

Các bộ lọc sau nên xử lý ở frontend:

- Hãng bay
- Điểm dừng
- Giá
- Thời gian bay
- Giờ khởi hành
- Sắp xếp

---

# Server-side Filter

## Định nghĩa

Frontend gửi điều kiện lọc lên backend.

Backend truy vấn dữ liệu và chỉ trả kết quả phù hợp.

Ví dụ

```
GET /flights

?airline=VN
&stop=0
&sort=price
&page=2
```

Backend:

```
Database
    │
Filter
    │
Sort
    │
Pagination
    │
Frontend
```

## Ưu điểm

- Phù hợp dữ liệu lớn
- Tiết kiệm băng thông
- Không gửi dữ liệu dư thừa
- Dễ phân trang

## Nhược điểm

- Mỗi lần đổi filter phải gọi API
- UX chậm hơn nếu không cache

---

# Khi nào dùng Client-side

✔ Search API đã trả toàn bộ kết quả

✔ Dữ liệu < vài trăm bản ghi

✔ Không cần phân trang

Ví dụ

- Flight Search
- Danh sách khuyến mãi
- Danh sách sân bay

---

# Khi nào dùng Server-side

✔ Dữ liệu rất lớn

✔ Có phân trang

✔ Query trực tiếp Database

✔ Không muốn gửi toàn bộ dữ liệu về client

Ví dụ

- Booking History
- Ticket Management
- User Management
- Admin Dashboard
- Payment History

---

# VietFly

## Hiện tại

Search API

↓

Laravel

↓

Mock Datacom

↓

Trả toàn bộ chuyến bay

↓

Frontend Filter + Sort

## Tương lai

Nếu tích hợp Datacom thật:

Frontend

↓

Laravel

↓

Datacom

↓

DirectOnly
Cabin
Airline
...

↓

Datacom trả ít kết quả hơn

↓

Frontend tiếp tục filter/sort các điều kiện còn lại.

---

# Nguyên tắc

- Filter theo dữ liệu đã có → Frontend.
- Filter làm thay đổi truy vấn nguồn dữ liệu → Backend.
- Dữ liệu lớn hoặc có phân trang → Backend.
- Dữ liệu nhỏ, cần UX nhanh → Frontend.
