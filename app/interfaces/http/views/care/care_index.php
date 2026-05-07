<?php
$title = 'Chăm sóc';
ob_start();
?>
<style>
.tab-btn { transition: all 0.2s; }
.tab-btn.active { background: #2563eb; color: white; }
.tab-btn:not(.active) { background: #f1f5f9; color: #64748b; }
.tab-btn:not(.active):hover { background: #e2e8f0; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.form-grid .full { grid-column: 1 / -1; }
.offline-banner {
    background: #fef3c7; border: 1px solid #fcd34d;
    border-radius: 0.75rem; padding: 0.75rem 1rem;
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.875rem; color: #92400e; margin-bottom: 1rem;
}
</style>

<!-- Header -->
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold">🐔 Chăm sóc</h1>
        <div class="text-xs text-gray-400">Ghi chép chăm sóc trại từ cloud</div>
    </div>
</div>

<!-- Offline Banner -->
<div id="offline-banner" class="offline-banner" style="display:none">
    <span>⚠️</span>
    <span>Local server offline — không thể ghi chép</span>
</div>

<!-- Cycle Selector -->
<div class="mb-4">
    <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Chọn cycle</label>
    <select id="cycle-select"
            class="w-full rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500"
            onchange="loadCycleData()">
        <option value="">— Chọn cycle —</option>
    </select>
</div>

<!-- Barn Selector -->
<div class="mb-4" id="barn-select-wrap" style="display:none">
    <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Chuồng</label>
    <select id="barn-select"
            class="w-full rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm">
        <option value="">— Chọn chuồng —</option>
    </select>
</div>

<!-- Tab Menu -->
<div class="flex gap-1 mb-4 overflow-x-auto pb-1" id="tab-menu">
    <?php
    $tabs = [
        'feed' => '🍚 Thức ăn',
        'death' => '💀 Chết',
        'medication' => '💊 Thuốc',
        'weight' => '⚖️ Cân nặng',
        'water' => '💧 Nước',
        'sale' => '💰 Bán',
    ];
    foreach ($tabs as $key => $label):
    ?>
    <button class="tab-btn px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap"
            data-tab="<?= $key ?>"
            onclick="switchTab('<?= $key ?>')">
        <?= $label ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- Forms Container -->
<div id="forms-container">

    <!-- FEED FORM -->
    <div id="form-feed" class="tab-content" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">🍚 Ghi thức ăn</div>
            <form id="feed-form" onsubmit="submitForm(event, 'feed')">
                <input type="hidden" name="cycle_id" id="feed_cycle_id">
                <input type="hidden" name="barn_id" id="feed_barn_id">
                <div class="form-grid">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ngày</label>
                        <input type="date" name="feed_date" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Bữa ăn</label>
                        <select name="meal" required
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="morning">Sáng</option>
                            <option value="noon">Trưa</option>
                            <option value="evening">Chiều</option>
                            <option value="night">Tối</option>
                        </select>
                    </div>
                    <div class="full">
                        <label class="block text-xs text-gray-500 mb-1">Sản phẩm / Thức ăn</label>
                        <input type="text" name="product_name" placeholder="VD: Feed gold 311"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Số lượng (kg)</label>
                        <input type="number" step="0.1" name="quantity" min="0.1" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ghi chú</label>
                        <input type="text" name="notes" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-3 w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Ghi nhận
                </button>
            </form>
        </div>
    </div>

    <!-- DEATH FORM -->
    <div id="form-death" class="tab-content" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">💀 Ghi chết</div>
            <form id="death-form" onsubmit="submitForm(event, 'death')">
                <input type="hidden" name="cycle_id" id="death_cycle_id">
                <input type="hidden" name="barn_id" id="death_barn_id">
                <div class="form-grid">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ngày</label>
                        <input type="date" name="death_date" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Số lượng chết</label>
                        <input type="number" name="count" min="1" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ca</label>
                        <select name="shift"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="morning">Sáng</option>
                            <option value="afternoon">Chiều</option>
                            <option value="night">Đêm</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nguyên nhân</label>
                        <input type="text" name="cause" placeholder="VD: Bệnh"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div class="full">
                        <label class="block text-xs text-gray-500 mb-1">Triệu chứng</label>
                        <input type="text" name="symptoms" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div class="full">
                        <label class="block text-xs text-gray-500 mb-1">Ghi chú</label>
                        <input type="text" name="notes" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-3 w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Ghi nhận
                </button>
            </form>
        </div>
    </div>

    <!-- MEDICATION FORM -->
    <div id="form-medication" class="tab-content" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">💊 Ghi thuốc</div>
            <form id="medication-form" onsubmit="submitForm(event, 'medication')">
                <input type="hidden" name="cycle_id" id="medication_cycle_id">
                <input type="hidden" name="barn_id" id="medication_barn_id">
                <div class="form-grid">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ngày</label>
                        <input type="date" name="med_date" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Loại thuốc</label>
                        <select name="med_type" required
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="vaccine">Vaccine</option>
                            <option value="antibiotic">Kháng sinh</option>
                            <option value="vitamin">Vitamin</option>
                            <option value="disinfectant">Chất sát trùng</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Số lượng (liều/gói)</label>
                        <input type="number" step="0.1" name="quantity" min="0.1" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Cách dùng</label>
                        <select name="method"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="oral">Uống</option>
                            <option value="injection">Tiêm</option>
                            <option value="spray">Xịt</option>
                            <option value="bath">Tắm</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ca</label>
                        <select name="shift"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="morning">Sáng</option>
                            <option value="afternoon">Chiều</option>
                            <option value="night">Đêm</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Mục đích</label>
                        <input type="text" name="purpose" placeholder="VD: Phòng bệnh"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div class="full">
                        <label class="block text-xs text-gray-500 mb-1">Ghi chú</label>
                        <input type="text" name="notes" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-3 w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Ghi nhận
                </button>
            </form>
        </div>
    </div>

    <!-- WEIGHT FORM -->
    <div id="form-weight" class="tab-content" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">⚖️ Ghi cân nặng</div>
            <form id="weight-form" onsubmit="submitForm(event, 'weight')">
                <input type="hidden" name="cycle_id" id="weight_cycle_id">
                <input type="hidden" name="barn_id" id="weight_barn_id">
                <div class="form-grid">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ngày</label>
                        <input type="date" name="weigh_date" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Số con cân</label>
                        <input type="number" name="sample_count" min="1" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tổng kg</label>
                        <input type="number" step="0.1" name="total_weight" min="0.1" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Cân nặng TB (kg)</label>
                        <input type="number" step="0.01" name="avg_weight" min="0.01"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Cân nặng thấp nhất</label>
                        <input type="number" step="0.01" name="min_weight" min="0"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Cân nặng cao nhất</label>
                        <input type="number" step="0.01" name="max_weight" min="0"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Đồng đều (%)</label>
                        <input type="number" step="0.1" name="uniformity" min="0" max="100"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Giới tính</label>
                        <select name="gender"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="">—</option>
                            <option value="male">Đực</option>
                            <option value="female">Cái</option>
                        </select>
                    </div>
                    <div class="full">
                        <label class="block text-xs text-gray-500 mb-1">Ghi chú</label>
                        <input type="text" name="notes" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Ghi nhận
                </button>
            </form>
        </div>
    </div>

    <!-- WATER FORM -->
    <div id="form-water" class="tab-content" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">💧 Ghi nước</div>
            <form id="water-form" onsubmit="submitForm(event, 'water')">
                <input type="hidden" name="cycle_id" id="water_cycle_id">
                <input type="hidden" name="barn_id" id="water_barn_id">
                <div class="form-grid">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ngày</label>
                        <input type="date" name="water_date" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tiêu thụ (lít)</label>
                        <input type="number" step="0.1" name="consumption_liters" min="0.1"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ca</label>
                        <select name="shift"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="all_day">Cả ngày</option>
                            <option value="morning">Sáng</option>
                            <option value="afternoon">Chiều</option>
                            <option value="night">Đêm</option>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="medicated" value="1" class="rounded">
                            <span>Có pha thuốc</span>
                        </label>
                    </div>
                    <div class="full">
                        <label class="block text-xs text-gray-500 mb-1">Ghi chú</label>
                        <input type="text" name="notes" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-3 w-full bg-cyan-600 hover:bg-cyan-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Ghi nhận
                </button>
            </form>
        </div>
    </div>

    <!-- SALE FORM -->
    <div id="form-sale" class="tab-content" style="display:none">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
            <div class="text-sm font-semibold mb-3">💰 Ghi bán</div>
            <form id="sale-form" onsubmit="submitForm(event, 'sale')">
                <input type="hidden" name="cycle_id" id="sale_cycle_id">
                <input type="hidden" name="barn_id" id="sale_barn_id">
                <div class="form-grid">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ngày</label>
                        <input type="date" name="sale_date" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Số lượng bán</label>
                        <input type="number" name="count" min="1" required
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tổng kg</label>
                        <input type="number" step="0.1" name="total_weight" min="0.1"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Kg trung bình</label>
                        <input type="number" step="0.01" name="avg_weight" min="0.01"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Đơn giá</label>
                        <input type="number" step="1000" name="unit_price" min="0"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tổng tiền</label>
                        <input type="number" step="1000" name="total_amount" min="0"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Người mua</label>
                        <input type="text" name="buyer" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Loại bán</label>
                        <select name="sale_type"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                            <option value="sale">Bán thường</option>
                            <option value="preorder">Đặt trước</option>
                            <option value="cull">Thanh lý</option>
                        </select>
                    </div>
                    <div class="full">
                        <label class="block text-xs text-gray-500 mb-1">Ghi chú</label>
                        <input type="text" name="notes" placeholder="Tuỳ chọn"
                               class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-3 w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Ghi nhận
                </button>
            </form>
        </div>
    </div>

</div><!-- /forms-container -->

<!-- Recent Records -->
<div id="records-section" style="display:none">
    <div class="text-sm font-semibold mb-2">📋 Gần đây</div>
    <div id="records-list" class="space-y-2"></div>
</div>

<script>
// State
let cycles = [];
let barns = [];
let currentCycleId = null;
let currentBarnId = null;
let currentTab = 'feed';
let isOffline = false;

// ── Init ─────────────────────────────────
async function init() {
    await loadCycles();
    await loadBarns();
    switchTab('feed');

    // Set today's date as default for date inputs
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('input[type=date]').forEach(el => el.value = today);
}

// ── Load Cycles ───────────────────────────
async function loadCycles() {
    try {
        const r = await fetch('/api/cloud/care/cycles');
        const j = await r.json();
        if (j.ok && j.cycles) {
            cycles = j.cycles;
            renderCycleSelect();
        } else if (j.offline) {
            showOffline();
        }
    } catch(e) {
        showOffline();
    }
}

// ── Load Barns ────────────────────────────
async function loadBarns() {
    try {
        const r = await fetch('/api/cloud/care/barns');
        const j = await r.json();
        if (j.ok && j.barns) {
            barns = j.barns;
            renderBarnSelect();
        }
    } catch(e) {}
}

// ── Render Selectors ──────────────────────
function renderCycleSelect() {
    const sel = document.getElementById('cycle-select');
    sel.innerHTML = '<option value="">— Chọn cycle —</option>';
    cycles.forEach(c => {
        sel.innerHTML += `<option value="${c.id}">${c.code} (${c.current_quantity ?? 0} con, ${c.age_in_days ?? '?'} ngày)</option>`;
    });
}

function renderBarnSelect() {
    const sel = document.getElementById('barn-select');
    sel.innerHTML = '<option value="">— Chọn chuồng —</option>';
    barns.forEach(b => {
        sel.innerHTML += `<option value="${b.id}">${b.name}</option>`;
    });
}

// ── Cycle Changed ─────────────────────────
async function loadCycleData() {
    const cycleId = document.getElementById('cycle-select').value;
    currentCycleId = cycleId || null;
    document.getElementById('barn-select-wrap').style.display = cycleId ? 'block' : 'none';

    if (!cycleId) {
        document.getElementById('records-section').style.display = 'none';
        return;
    }

    // Set hidden inputs on all forms
    ['feed','death','medication','weight','water','sale'].forEach(t => {
        const cyInput = document.getElementById(`${t}_cycle_id`);
        const brInput = document.getElementById(`${t}_barn_id`);
        if (cyInput) cyInput.value = cycleId;
    });

    // Load records for current tab
    await loadRecords();
}

// ── Barn Changed ──────────────────────────
function onBarnChange(el) {
    currentBarnId = el.value || null;
    ['feed','death','medication','weight','water','sale'].forEach(t => {
        const brInput = document.getElementById(`${t}_barn_id`);
        if (brInput) brInput.value = currentBarnId || '';
    });
    if (currentBarnId) loadRecords();
}

// ── Tab Switching ──────────────────────────
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === tab);
    });
    document.querySelectorAll('.tab-content').forEach(f => f.style.display = 'none');
    const formEl = document.getElementById(`form-${tab}`);
    if (formEl) formEl.style.display = 'block';
    if (currentCycleId) loadRecords();
}

// ── Load Records ──────────────────────────
async function loadRecords() {
    if (!currentCycleId) return;
    const section = document.getElementById('records-section');
    section.style.display = 'block';

    try {
        const r = await fetch(`/api/cloud/care/${currentTab}/${currentCycleId}`);
        const j = await r.json();
        renderRecords(j.records || []);
    } catch(e) {
        renderRecords([]);
    }
}

// ── Render Records ────────────────────────
function renderRecords(records) {
    const list = document.getElementById('records-list');
    if (!records.length) {
        list.innerHTML = '<div class="text-sm text-gray-400 text-center py-4">Chưa có bản ghi nào</div>';
        return;
    }

    const labels = {
        feed: { icon: '🍚', date: 'feed_date', qty: 'quantity', extra: 'meal' },
        death: { icon: '💀', date: 'death_date', qty: 'count', extra: 'cause' },
        medication: { icon: '💊', date: 'med_date', qty: 'quantity', extra: 'med_type' },
        weight: { icon: '⚖️', date: 'weigh_date', qty: 'avg_weight', extra: 'sample_count' },
        water: { icon: '💧', date: 'water_date', qty: 'consumption_liters', extra: 'shift' },
        sale: { icon: '💰', date: 'sale_date', qty: 'count', extra: 'total_amount' },
    };
    const lbl = labels[currentTab] || {};

    list.innerHTML = records.slice(0, 20).map(r => {
        const date = r[lbl.date] || r.created_at || '—';
        const qty = r[lbl.qty] ?? '—';
        const extra = r[lbl.extra] || '';
        const note = r.notes || '';
        const dateStr = date.includes('T') ? date.split('T')[0] : date;
        return `
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 text-sm">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-gray-500">${dateStr}</span>
                    ${extra ? `<span class="ml-2 text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">${extra}</span>` : ''}
                </div>
                <span class="font-semibold text-blue-600">${qty}${currentTab === 'weight' ? ' kg' : ''}</span>
            </div>
            ${note ? `<div class="text-xs text-gray-400 mt-1">${note}</div>` : ''}
        </div>`;
    }).join('');
}

// ── Submit Form ───────────────────────────
async function submitForm(e, type) {
    e.preventDefault();
    if (!currentCycleId) { alert('Chọn cycle trước'); return; }

    const form = e.target;
    const submitBtn = form.querySelector('button[type=submit]');
    const origText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = '⏳';

    const formData = new FormData(form);
    // Ensure cycle_id and barn_id from selectors
    formData.set('cycle_id', currentCycleId);
    formData.set('barn_id', currentBarnId || '');

    // Convert to plain object
    const body = {};
    for (const [k, v] of formData.entries()) {
        if (v !== '') body[k] = v;
    }

    // Handle checkbox
    if (type === 'water') {
        body['medicated'] = form.querySelector('[name=medicated]')?.checked || false;
    }

    // Handle avg_weight auto-calc for weight
    if (type === 'weight' && body.total_weight && body.sample_count) {
        body['avg_weight'] = (parseFloat(body.total_weight) / parseInt(body.sample_count)).toFixed(2);
    }

    try {
        const r = await fetch(`/api/cloud/care/${type}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const j = await r.json();

        if (j.ok) {
            submitBtn.textContent = '✅';
            form.reset();
            // Reset today's date
            const today = new Date().toISOString().split('T')[0];
            form.querySelectorAll('input[type=date]').forEach(el => el.value = today);
            // Restore cycle/barn
            ['cycle_id','barn_id'].forEach(k => {
                const input = form.querySelector(`[name=${k}]`);
                if (input && k === 'cycle_id') input.value = currentCycleId;
                if (input && k === 'barn_id') input.value = currentBarnId || '';
            });
            await loadRecords();
        } else {
            alert('Lỗi: ' + (j.message || 'Không rõ'));
            submitBtn.textContent = origText;
        }
    } catch(e) {
        alert('Lỗi kết nối: ' + e.message);
        submitBtn.textContent = origText;
    } finally {
        submitBtn.disabled = false;
        setTimeout(() => submitBtn.textContent = origText, 2000);
    }
}

// ── Offline Banner ────────────────────────
function showOffline() {
    isOffline = true;
    document.getElementById('offline-banner').style.display = 'flex';
}

// ── Wire barn select ──────────────────────
document.getElementById('barn-select').addEventListener('change', function() {
    onBarnChange(this);
});

// Init
init();
</script>

<?php $content = ob_get_clean(); require view_path('layouts/main.php'); ?>