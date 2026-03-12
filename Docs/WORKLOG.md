# WORK LOG – app.cfarm.vn

## 2026-02-03
- Chốt thiết kế Chương I – ENV
- Quyết định dùng env_data dạng long format
- Gắn cycle_id trực tiếp vào từng bản ghi ENV

## 2026-02-04
- Tái cấu trúc backend theo chương nghiệp vụ
- Bỏ hoàn toàn sensor_raw
- Backend-first, chưa deploy frontend
- Thống nhất viết README trước khi viết logic


CHECKLIST VALIDATE INGEST ENV v1 ## 2026-02-04
(Áp dụng cho Chương I – ENV)

Nguyên tắc xuyên suốt:
Fail sớm – fail rõ – không đoán hộ thiết bị

🧱 TẦNG 1 – VALIDATE HÌNH THỨC (Controller / DTO)
☐ 1. Payload có đầy đủ trường tối thiểu?

BẮT BUỘC:

node_code

env_code

value

KHÔNG BẮT BUỘC:

measured_at (có thể để server gán)

👉 Thiếu trường bắt buộc → REJECT NGAY

☐ 2. Kiểu dữ liệu có hợp lệ?

node_code: string, không rỗng

env_code: string, không rỗng

value: numeric (float/int)

👉 Sai kiểu → REJECT

☐ 3. Giá trị value có phải số hợp lệ?

Không phải NaN

Không phải INF

Không phải string giả số

👉 Không hợp lệ → REJECT

🧱 TẦNG 2 – VALIDATE ĐỊNH DANH (Service)
☐ 4. Node có tồn tại không?

node_code phải map được → node_id

👉 Không tồn tại → REJECT

Lý do: không nhận dữ liệu từ node “ma”

☐ 5. Node có đang active không?

Node không bị disable

(Offline/online xử lý sau)

👉 Bị disable → REJECT

☐ 6. Node có được phép đo ENV này không?

Check node_env_map

Node này được khai báo đo env_code này

👉 Không có mapping → REJECT

Tránh ESP32 gửi bừa dữ liệu

🧱 TẦNG 3 – VALIDATE ENV (Service)
☐ 7. ENV code có tồn tại trong env_def không?

TEMP, HUM, NH3… phải được khai báo trước

👉 Không tồn tại → REJECT

☐ 8. Giá trị value có nằm trong biên vật lý hợp lý?

(Không phải kết luận sinh học)

Ví dụ:

TEMP: -10 → 60

HUM: 0 → 100

NH3: ≥ 0

👉 Ngoài biên → REJECT hoặc FLAG
(Tuỳ bạn muốn reject cứng hay soft-fail)

🧱 TẦNG 4 – VALIDATE NGỮ CẢNH (Service)
☐ 9. Node đang thuộc barn nào?

Resolve node_id → barn_id

👉 Không resolve được → REJECT

☐ 10. Barn này có cycle đang active không?

Tìm cycle:

start_date ≤ now

end_date IS NULL hoặc ≥ now

👉 Không có cycle → REJECT

Không ghi dữ liệu “trôi nổi ngoài cycle”

☐ 11. Xác định timestamp đo

Nếu có measured_at:

Parse được datetime

Nếu không:

Dùng server time

👉 Không parse được → REJECT

🧱 TẦNG 5 – CHUẨN BỊ GHI DỮ LIỆU (Service → Repository)
☐ 12. Chuẩn hoá dữ liệu trước khi ghi

Xác định đầy đủ:

env_id

node_id

barn_id

cycle_id

measured_at

value

👉 Thiếu bất kỳ trường nào → KHÔNG GHI

☐ 13. Không xử lý AI / rule tại ingest

Không so sánh với sensor khác

Không kết luận bất thường

Không cảnh báo

👉 Ingest chỉ tạo sự thật lịch sử

🧱 TẦNG 6 – SAU KHI GHI (Optional, v1.5)
☐ 14. Có cần log ingest thành công?

Phục vụ debug

Không bắt buộc

☐ 15. Có cần trả response chi tiết cho ESP32?

v1:

{ "status": "ok" }


v1.5:

{ "status": "error", "reason": "NODE_NOT_FOUND" }

🔑 CÂU CHỐT QUAN TRỌNG NHẤT

Ingest ENV không được “thông minh”,
chỉ được “chính xác và kỷ luật”.

Thông minh để:

Rule engine

AI

Alert

→ làm SAU ingest

🧭 CHECKLIST TÓM TẮT 1 DÒNG
Payload OK
→ Node tồn tại
→ Node được phép đo ENV
→ ENV hợp lệ
→ Resolve barn
→ Resolve cycle active
→ Chuẩn hoá timestamp
→ Ghi env_data


## 2026-02-20
- Hoàn thiện bootstrap + container
- Kết nối MySQL qua PDO
- Hoàn thiện create barn flow (view test)
- Xác nhận bảng canonical: barns



## 2026-02-28

### Quyết định kiến trúc
- Tái cấu trúc toàn bộ dự án từ đầu — clean cả code lẫn database
- Chốt kiến trúc: Clean Architecture + Domain-Driven Design
- Tổ chức file theo domain trước, layer sau
- Thêm API controller layer song song với Web (chuẩn bị cho mobile)

### Convention đã chốt
- Lowercase + snake_case cho tất cả tên file và bảng DB
- PascalCase cho PHP class name (bắt buộc PSR-4)
- camelCase cho variable và method
- Header comment ở đầu mỗi file: đường dẫn + chức năng
- Không bao giờ dùng chữ in hoa trong tên file và bảng DB
- Tiếng Việt hoàn toàn trên UI

### Stack đã chốt
- Backend: PHP thuần — Clean Architecture
- Database: MySQL, charset utf8mb4, tên bảng lowercase snake_case
- Router: nikic/fast-route
- Autoload: classmap (giữ được snake_case filename)
- Frontend: PHP template + Tailwind CSS (CDN)
- Theme: Light/Dark toggle, lưu cookie
- Mobile: Responsive-first

### Bài học từ classmap vs psr-4
- PSR-4 yêu cầu filename khớp PascalCase với class name
- Dùng classmap để giữ snake_case filename
- Class name trong router.php phải dùng PascalCase khớp classmap
- Sau khi thêm class mới: chạy composer dump-autoload

### Vertical Slice #1 — barns ✅
- Tạo bảng barns
- Entity: Barn
- Interface: BarnRepositoryInterface
- Repository: BarnRepository (MySQL)
- Use Cases: CreateBarnUsecase, UpdateBarnUsecase, DeleteBarnUsecase, ListBarnUsecase
- Controller: BarnController
- Views: barn_list, barn_create, barn_edit, barn_show
- Layout chính: layouts/main.php với dark/light toggle
- Helpers: view_path(), redirect(), e(), active()

### Cấu trúc thư mục đã chốt
```
app/
  domains/{domain}/
    entities/
    contracts/
    usecases/
  infrastructure/persistence/mysql/repositories/
  interfaces/http/
    controllers/web/{domain}/
    views/{domain}/
    views/layouts/
  shared/
    database/mysql.php
    utils/helpers.php
```

### Database schema hiện tại
- barns: id, number, name, length_m, width_m, height_m, status, note, created_at

### Tiếp theo
- Vertical Slice #2 — cycles
EOF

# Verify
cat /var/www/app.cfarm.vn/Docs/WORKLOG.md
## 2026-02-28

### Quyết định kiến trúc
- Tái cấu trúc toàn bộ dự án từ đầu — clean cả code lẫn database
- Chốt kiến trúc: Clean Architecture + Domain-Driven Design
- Tổ chức file theo domain trước, layer sau
- Thêm API controller layer song song với Web (chuẩn bị cho mobile)

### Convention đã chốt
- Lowercase + snake_case cho tất cả tên file và bảng DB
- PascalCase cho PHP class name (bắt buộc PSR-4)
- camelCase cho variable và method
- Header comment ở đầu mỗi file: đường dẫn + chức năng
- Không bao giờ dùng chữ in hoa trong tên file và bảng DB
- Tiếng Việt hoàn toàn trên UI

### Stack đã chốt
- Backend: PHP thuần — Clean Architecture
- Database: MySQL, charset utf8mb4, tên bảng lowercase snake_case
- Router: nikic/fast-route
- Autoload: classmap (giữ được snake_case filename)
- Frontend: PHP template + Tailwind CSS (CDN)
- Theme: Light/Dark toggle, lưu cookie
- Mobile: Responsive-first

### Bài học từ classmap vs psr-4
- PSR-4 yêu cầu filename khớp PascalCase với class name
- Dùng classmap để giữ snake_case filename
- Class name trong router.php phải dùng PascalCase khớp classmap
- Sau khi thêm class mới: chạy composer dump-autoload

### Vertical Slice #1 — barns ✅
- Tạo bảng barns
- Entity: Barn
- Interface: BarnRepositoryInterface
- Repository: BarnRepository (MySQL)
- Use Cases: CreateBarnUsecase, UpdateBarnUsecase, DeleteBarnUsecase, ListBarnUsecase
- Controller: BarnController
- Views: barn_list, barn_create, barn_edit, barn_show
- Layout chính: layouts/main.php với dark/light toggle
- Helpers: view_path(), redirect(), e(), active()

### Cấu trúc thư mục đã chốt
app/
  domains/{domain}/
    entities/
    contracts/
    usecases/
  infrastructure/persistence/mysql/repositories/
  interfaces/http/
    controllers/web/{domain}/
    views/{domain}/
    views/layouts/
  shared/
    database/mysql.php
    utils/helpers.php

### Database schema hiện tại
- barns: id, number, name, length_m, width_m, height_m, status, note, created_at

### Tiếp theo
- Vertical Slice #2 — cycles
