# WORK LOG – app.cfarm.vn

## 2026-03-16
- Phase 1: Firmware Version Control
  - Thêm cột firmware_version và base_firmware vào device_types
  - Tạo bảng device_firmware_allocations để tracking
  - Thêm chức năng cấp phát firmware cho từng device
  - Hiển thị lịch sử cấp phát trong firmware view

## 2026-03-15
- Tạo trang inventory stock by barn
  - Route: /inventory/stock
  - Hiển thị tồn kho cám theo từng chuồng
  - Hiển thị tồn kho trấu theo chuồng
  - Cảnh báo khi tồn kho < 10
- Fix CycleRepository - bỏ cột feed_waste_pct không tồn tại trong DB
- Fix EventController - thêm null check cho $cycle trước khi truy cập properties

## 2026-03-14 (tiếp)
- Tạo CareEditPermission service
  - File: app/domains/care/services/care_edit_permission.php
  - Kiểm tra quyền sửa (3 ngày) / xóa (2 ngày) event care
  - Hỗ trợ override password cho admin
- Fix lỗi xóa event trả về HTML thay vì JSON
  - Nguyên nhân: class CareEditPermission chưa được autoload
  - Giải pháp: chạy composer dump-autoload trên server

---

## 2026-03-14
- Thiết kế lại luồng dữ liệu Feed (feed_brands → feed_types → inventory_items → care_feeds)
- Tạo FeedBrandService.php để auto-generate feed_types + inventory_items khi tạo feed_brand
  - Tự động tạo 3 feed_types: chick, grower, adult cho mỗi brand
  - Tự động tạo inventory_items cho mỗi feed_type
- Fix InventoryStockService.php - thêm kiểm tra tồn kho trước khi trừ
  - Validate: "Tồn kho không đủ! Hiện có: X bao, cần: Y bao"
  - Ưu tiên lookup theo ref_feed_type_id (chính xác hơn ref_feed_brand_id)
- Fix CareController - validate cycle phải có feed_program mới được ghi care_feeds
  - Check: SELECT COUNT(*) FROM cycle_feed_programs WHERE cycle_id = ? AND end_date IS NULL
- Thêm dropdown inventory_items trong event_create.php
  - Hiển thị tồn kho hiện tại để user tiện theo dõi
- Thêm tính năng Sync Kho trong Settings → Hãng cám
  - Nút "🔄 Sync Kho" để đồng bộ inventory_items từ feed_types
  - Method syncInventoryFromFeedTypes() trong FeedBrandService
  - Tự động tạo mới hoặc update inventory_items khi feed_types thay đổi
- Fix inventory/production bị double items
  - Thêm GROUP BY ii.id trong InventoryRepository để ngăn duplicate từ JOIN
  - Fix FK constraint khi xóa inventory_items feed
    - Phải xóa các bảng liên quan trước: inventory_purchases, inventory_transactions, inventory_stock, inventory_sales
    - Dùng SET FOREIGN_KEY_CHECKS = 0 trước khi xóa
- Fix EventController - sử dụng inventory_stock table cho quantity (không có cột quantity trong inventory_items)
- Cập nhật SQLADD.md - lưu SQL commands cần chạy trên cloud

### Bước 5: Cycle chọn inventory_items theo giai đoạn (2026-03-14)
- Tạo bảng cycle_feed_program_items
  - Link cycle_feed_programs → inventory_items theo stage (chick/grower/adult)
  - Cho phép cycle chọn mã cám cụ thể cho từng giai đoạn
- Cập nhật cycle_feed_program.php
  - Form chọn inventory_items cho từng giai đoạn
  - Hiển thị tồn kho của từng mã cám
- Cập nhật cycle_controller.php
  - feed_program_form: load inventory_items theo stage
  - feed_program_store: lưu inventory_items đã chọn
- Cập nhật event_controller
  - Ưu tiên lấy inventory_items từ cycle_feed_program_items nếu có

### Database changes cần chạy trên cloud:
```sql
-- 1. Thêm cột ref_feed_type_id
ALTER TABLE inventory_items
ADD COLUMN ref_feed_type_id INT NULL AFTER ref_feed_brand_id,
ADD INDEX idx_ref_feed_type_id (ref_feed_type_id);

-- 2. Xóa inventory_items feed cũ và tạo mới từ feed_types
DELETE FROM inventory_items WHERE category = 'production' AND sub_category = 'feed';

INSERT INTO inventory_items (name, category, sub_category, unit, ref_feed_brand_id, ref_feed_type_id, status)
SELECT
    CONCAT(ft.code, ' - ', fb.name, ' - ', ft.name) AS name,
    'production', 'feed', 'bao',
    ft.feed_brand_id, ft.id, 'active'
FROM feed_types ft
JOIN feed_brands fb ON ft.feed_brand_id = fb.id
WHERE ft.status = 'active';
```

**Lưu ý quan trọng:**
- inventory_items PHẢI liên kết với feed_types (từng loại cám theo giai đoạn), KHÔNG phải feed_brands
- Name format: "CODE - Brand - Type Name" (ví dụ: "CCCH01 - Cám Con Cò - Gà con")

---

## 2026-03-13
- Tổng hợp tất cả router thành file `Docs/ROUTERS.md`
- Liệt kê ~100+ routes theo từng domain: Auth, Home, Barn, Cycle, Care, Weight, ENV, Vaccine, Health, Inventory, Report, Export, Event, Expense, Push, IoT, Settings
- Clone database `cfarm_app_raw` từ cloud server về (file `c:\dev\cfarm_app_raw.sql`)
- Xác định 44 bảng trong database: barns, cycles, care_*, devices, env_readings, feed_*, inventory_*, medications, vaccine_*, weight_*, users, notifications...
- Phân tích logic router vs database - phát hiện 6 vấn đề cần fix:
  1. Thiếu route list cycles theo barn
  2. Care routes thiếu cycle_id trong URL
  3. Thiếu route list cho Care records
  4. Bảng care_litters không có route
  5. Bảng care_expenses - routes hạn chế
  6. Thiếu route cho health_notes

### 2026-03-13 (tiếp)
- Fix care_litters tích hợp inventory:
  - Thêm dropdown chọn item từ inventory_items (loại litter) trong form cycle_show.php
  - Thêm kiểm tra tồn kho trước khi trừ stock trong inventory_controller.php
  - Hiển thị lỗi nếu tồn kho không đủ: "Tồn kho không đủ! Hiện có: X bao, cần: Y bao"
- Commit: `97750f8 fix: integrate care_litters with inventory - add item selection and stock validation`

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
