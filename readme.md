# app.cfarm.vn – Backend hệ thống trang trại

## Mục tiêu
Xây dựng backend cho hệ thống giám sát & phân tích trang trại gà, 
hướng tới:
- Giảm bệnh
- Cảnh báo sớm bất lợi môi trường
- Tối ưu FCR theo từng cycle

Backend được thiết kế theo hướng:
- Domain-driven (theo chương nghiệp vụ)
- Backend-first
- AI-friendly
- Dễ mở rộng cảm biến và dữ liệu trong tương lai

---

## Cấu trúc theo chương nghiệp vụ

- Domains/Env    : Chương I – Dữ liệu môi trường (ENV)
- Domains/Care   : Chương II – Dữ liệu chăm sóc (chưa triển khai)
- Domains/Core   : Các khái niệm lõi (Barn, Cycle)
- Shared         : Thành phần dùng chung (DB, Utils)
- docs           : Nhật ký & tài liệu quyết định

---

## Line dữ liệu tổng quát

ESP32
→ Controller
→ DTO
→ Service
→ Repository
→ MySQL (dữ liệu đã chuẩn hoá)

Không sử dụng bảng raw ingest ở giai đoạn này.

---

## Nguyên tắc phát triển
- Viết README trước khi viết logic
- Không trộn nghiệp vụ giữa các chương
- Mọi dữ liệu ENV đều gắn cycle_id
- Mọi quyết định kiến trúc phải được ghi log

Xem chi tiết từng chương trong README của từng Domain.

