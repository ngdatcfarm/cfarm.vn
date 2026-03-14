<?php
/**
 * app/interfaces/http/views/event/event_create.php
 */
$title         = $cycle ? 'Ghi chép — ' . e($cycle->code) : 'Ghi chép sự kiện';
$active_cycles = $GLOBALS['active_cycles_for_fab'] ?? [];

// helper: render 4 cards cho 1 session
function render_session_cards(
    string $session,
    array  $feeds,
    array  $deaths,
    array  $medications,
    array  $sales,
    object $cycle,
    array  $feed_types,
    array  $medications_list,
    string $date_value,
    array  $weight_sessions = [],
    array  $vaccine_program_items = []
): void {
    $default_time = $session === 'morning' ? '08:00' : '14:00';
    $s = $session;
?>
<div class="space-y-3">

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CARD: Cho ăn -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-lg">🌾</div>
            <div>
                <div class="text-sm font-semibold">Cho ăn</div>
                <div class="text-xs text-gray-400"><?= count($feeds) > 0 ? count($feeds) . ' lần' : 'Chưa có' ?></div>
            </div>
        </div>
        <button onclick="document.getElementById('feed_form_<?= $s ?>').classList.toggle('hidden')"
                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
            + Thêm
        </button>
    </div>

    <!-- Inline form -->
    <div id="feed_form_<?= $s ?>" class="hidden border-t border-gray-100 dark:border-gray-700 pt-3 mb-3">
        <div id="feed_error_<?= $s ?>" class="hidden mb-2 p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
        <div class="space-y-2">
            <?php if (empty($feed_types)): ?>
                <div class="p-3 bg-yellow-50 text-yellow-700 rounded-xl text-xs">
                    ⚠️ Chưa có hãng cám — <a href="/cycles/<?= e($cycle->id) ?>/feed-program" class="underline">Cài đặt ngay</a>
                </div>
            <?php else: ?>
                <div>
                    <label class="block text-xs font-medium mb-1">Mã cám <span class="text-red-500">*</span></label>
                    <select id="feed_type_id_<?= $s ?>" onchange="updateKgPerBag_<?= $s ?>(this)"
                            class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Chọn mã cám —</option>
                        <?php foreach ($feed_types as $ft): ?>
                        <option value="<?= e($ft['id']) ?>" data-kg="<?= e($ft['kg_per_bag']) ?>">
                            <?= e($ft['brand_name']) ?> · <?= e($ft['code']) ?> (<?= e($ft['kg_per_bag']) ?>kg/bao)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($feed_inventory_items)): ?>
                <div>
                    <label class="block text-xs font-medium mb-1">Kho vật tư (tồn kho)</label>
                    <select id="feed_inventory_item_<?= $s ?>"
                            class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Xem tồn kho —</option>
                        <?php foreach ($feed_inventory_items as $inv): ?>
                        <option value="<?= e($inv['id']) ?>" data-qty="<?= e($inv['quantity'] ?? 0) ?>">
                            <?= e($inv['brand_name'] ?? $inv['name']) ?><?= !empty($inv['feed_type_code']) ? ' · ' . e($inv['feed_type_code']) : '' ?> — Tồn: <?= number_format($inv['quantity'] ?? 0, 1) ?> <?= e($inv['unit'] ?? 'bao') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="block text-xs font-medium mb-1">Số bao <span class="text-red-500">*</span></label>
                    <div class="flex gap-1 mb-1.5 flex-wrap">
                        <?php foreach ([0.5,1,2,3,4,5,10] as $n): ?>
                        <button type="button" onclick="quickFill_<?= $s ?>(<?= $n ?>)"
                                class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-blue-200 text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors">
                            <?= $n == 0.5 ? '½' : $n ?> bao
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" id="feed_bags_<?= $s ?>" step="0.5" min="0"
                               oninput="calcFeedKg_<?= $s ?>()"
                               placeholder="Số bao..."
                               class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <div>
                            <div class="flex justify-between mb-0.5">
                                <label class="text-xs font-medium">Kg thực tế</label>
                                <button type="button" onclick="toggleKgManual_<?= $s ?>()" class="text-blue-500 text-xs">sửa</button>
                            </div>
                            <input type="number" id="feed_kg_<?= $s ?>" step="0.1" min="0" readonly placeholder="Tự tính"
                                   class="w-full border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium mb-1">Thời gian</label>
                        <input type="datetime-local" id="feed_time_<?= $s ?>"
                               value="<?= $date_value ?>T<?= $default_time ?>"
                               class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Ghi chú</label>
                        <input type="text" id="feed_note_<?= $s ?>" placeholder="Tùy chọn..."
                               class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <button onclick="submitFeedInline('<?= $s ?>')"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    💾 Lưu cho ăn
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Danh sách bữa ăn -->
    <div class="space-y-2">
        <?php if (empty($feeds)): ?>
            <div class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">Chưa có dữ liệu.</div>
        <?php else: ?>
        <?php foreach ($feeds as $f): ?>
        <?php
            $has_check = $f['latest_remaining_pct'] !== null;
            $consumed  = $has_check ? round($f['kg_actual'] * (1 - $f['latest_remaining_pct'] / 100), 1) : null;
        ?>
        <div class="border-t border-gray-100 dark:border-gray-700 pt-2">
            <div class="flex justify-between items-center text-xs">
                <div>
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        <?= e($f['brand_name']) ?> · <?= e($f['feed_code']) ?>
                    </span>
                    <span class="text-gray-400 ml-1"><?= date('H:i', strtotime($f['recorded_at'])) ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold"><?= e($f['bags']) ?> bao · <?= e(number_format($f['kg_actual'],1)) ?>kg</span>
                    <button onclick="editRecord('feed', <?= e($f['id']) ?>)" class="text-blue-400 hover:text-blue-600 p-0.5">✏️</button>
                    <button onclick="deleteRecord('feed', <?= e($f['id']) ?>, this)" class="text-red-400 hover:text-red-600 p-0.5">🗑️</button>
                </div>
            </div>
            <?php if ($has_check): ?>
            <div class="mt-1 flex items-center justify-between bg-orange-50 dark:bg-orange-900/20 rounded-lg px-2.5 py-1.5">
                <div class="text-xs">
                    <?php if ((int)$f['latest_remaining_pct'] === 0): ?>
                        <span class="text-green-600 font-medium">✓ Ăn hết máng</span>
                    <?php else: ?>
                        <span class="text-orange-600 font-medium">🪣 Còn <?= e($f['latest_remaining_pct']) ?>%</span>
                        <span class="text-gray-400 ml-1">→ ăn ≈ <?= e(number_format($consumed,1)) ?>kg</span>
                    <?php endif; ?>
                    <span class="text-gray-400 ml-1"><?= date('H:i', strtotime($f['latest_checked_at'])) ?></span>
                </div>
                <button onclick="openTroughModal(<?= e($f['id']) ?>, '<?= e($f['brand_name'].' · '.$f['feed_code']) ?>', <?= e($f['kg_actual']) ?>)"
                        class="text-xs text-blue-500 hover:underline">Cập nhật</button>
            </div>
            <?php else: ?>
            <button onclick="openTroughModal(<?= e($f['id']) ?>, '<?= e($f['brand_name'].' · '.$f['feed_code']) ?>', <?= e($f['kg_actual']) ?>)"
                    class="mt-1 w-full text-xs text-left px-2.5 py-1.5 rounded-lg border border-dashed border-orange-200 dark:border-orange-700 text-orange-500 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors">
                🪣 Kiểm tra cám còn trong máng...
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CARD: Thuốc -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-lg">💊</div>
            <div>
                <div class="text-sm font-semibold">Thuốc</div>
                <div class="text-xs text-gray-400"><?= count($medications) > 0 ? count($medications) . ' lần' : 'Chưa có' ?></div>
            </div>
        </div>
        <button onclick="document.getElementById('med_form_<?= $s ?>').classList.toggle('hidden')"
                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
            + Thêm
        </button>
    </div>

    <!-- Inline form -->
    <div id="med_form_<?= $s ?>" class="hidden border-t border-gray-100 dark:border-gray-700 pt-3 mb-3">
        <div id="med_error_<?= $s ?>" class="hidden mb-2 p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
        <div class="space-y-2">
            <?php if (!empty($medications_list)): ?>
            <div>
                <label class="block text-xs font-medium mb-1">Chọn từ danh mục</label>
                <select onchange="fillMedicationInline(this, '<?= $s ?>')"
                        class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">— Chọn thuốc —</option>
                    <?php foreach ($medications_list as $m): ?>
                    <option value="<?= e($m['id']) ?>" data-name="<?= e($m['name']) ?>" data-unit="<?= e($m['unit']) ?>">
                        <?= e($m['name']) ?> (<?= e($m['unit']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <input type="text" id="med_name_<?= $s ?>" placeholder="Tên thuốc *"
                   class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="hidden" id="med_id_<?= $s ?>" value="">
            <div class="grid grid-cols-3 gap-2">
                <input type="number" id="med_dosage_<?= $s ?>" step="0.01" min="0" placeholder="Liều *"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <input type="text" id="med_unit_<?= $s ?>" placeholder="Đơn vị *"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select id="med_method_<?= $s ?>"
                        class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="water">Uống</option>
                    <option value="inject">Tiêm</option>
                    <option value="feed_mix">Trộn cám</option>
                    <option value="other">Khác</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="datetime-local" id="med_time_<?= $s ?>"
                       value="<?= $date_value ?>T<?= $default_time ?>"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <input type="text" id="med_note_<?= $s ?>" placeholder="Ghi chú..."
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button onclick="submitMedInline('<?= $s ?>')"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                💾 Lưu thuốc
            </button>
        </div>
    </div>

    <!-- Danh sách -->
    <div class="space-y-1.5">
        <?php foreach ($medications as $m): ?>
        <div class="flex justify-between items-center text-xs py-1.5 border-t border-gray-100 dark:border-gray-700">
            <span class="text-gray-500"><?= e($m['medication_name']) ?> <span class="text-gray-400"><?= date('H:i', strtotime($m['recorded_at'])) ?></span></span>
            <div class="flex items-center gap-2">
                <span class="font-semibold"><?= e($m['dosage']) ?> <?= e($m['unit']) ?></span>
                <button onclick="editRecord('medication', <?= e($m['id']) ?>)" class="text-blue-400 hover:text-blue-600 p-0.5">✏️</button>
                <button onclick="deleteRecord('medication', <?= e($m['id']) ?>, this)" class="text-red-400 hover:text-red-600 p-0.5">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($medications)): ?>
        <div class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">Chưa có.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CARD: Hao hụt -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-lg">📋</div>
            <div>
                <div class="text-sm font-semibold">Hao hụt</div>
                <div class="text-xs text-gray-400">
                    <?php $total = array_sum(array_column($deaths, 'quantity')); ?>
                    <?= $total > 0 ? $total . ' con' : 'Chưa có' ?>
                </div>
            </div>
        </div>
        <button onclick="document.getElementById('death_form_<?= $s ?>').classList.toggle('hidden')"
                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
            + Thêm
        </button>
    </div>

    <!-- Inline form -->
    <div id="death_form_<?= $s ?>" class="hidden border-t border-gray-100 dark:border-gray-700 pt-3 mb-3">
        <div id="death_error_<?= $s ?>" class="hidden mb-2 p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <input type="number" id="death_qty_<?= $s ?>" min="1" placeholder="Số con chết *"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <input type="datetime-local" id="death_time_<?= $s ?>"
                       value="<?= $date_value ?>T<?= $default_time ?>"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <input type="text" id="death_reason_<?= $s ?>" placeholder="Lý do..."
                   class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <textarea id="death_symptoms_<?= $s ?>" rows="2" placeholder="Triệu chứng (nếu có)..."
                      class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            <button onclick="submitDeathInline('<?= $s ?>')"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                💾 Lưu hao hụt
            </button>
        </div>
    </div>

    <!-- Danh sách -->
    <div class="space-y-1.5">
        <?php foreach ($deaths as $d): ?>
        <div class="flex justify-between items-center text-xs py-1.5 border-t border-gray-100 dark:border-gray-700">
            <span class="text-gray-500"><?= e($d['reason'] ?? 'Không rõ') ?> <span class="text-gray-400"><?= date('H:i', strtotime($d['recorded_at'])) ?></span></span>
            <div class="flex items-center gap-2">
                <span class="font-semibold text-red-600"><?= e($d['quantity']) ?> con</span>
                <button onclick="editRecord('death', <?= e($d['id']) ?>)" class="text-blue-400 hover:text-blue-600 p-0.5">✏️</button>
                <button onclick="deleteRecord('death', <?= e($d['id']) ?>, this)" class="text-red-400 hover:text-red-600 p-0.5">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($deaths)): ?>
        <div class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">Chưa có.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CARD: Bán gà -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center text-lg">💰</div>
            <div>
                <div class="text-sm font-semibold">Bán gà</div>
                <div class="text-xs text-gray-400">
                    <?php $rev = array_sum(array_column($sales, 'total_amount')); ?>
                    <?= $rev > 0 ? number_format($rev) . 'đ' : 'Chưa có' ?>
                </div>
            </div>
        </div>
        <button onclick="document.getElementById('sale_form_<?= $s ?>').classList.toggle('hidden')"
                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
            + Thêm
        </button>
    </div>

    <!-- Inline form -->
    <div id="sale_form_<?= $s ?>" class="hidden border-t border-gray-100 dark:border-gray-700 pt-3 mb-3">
        <div id="sale_error_<?= $s ?>" class="hidden mb-2 p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <input type="number" id="sale_weight_<?= $s ?>" step="0.1" min="0"
                       oninput="calcSaleTotalInline('<?= $s ?>')"
                       placeholder="Tổng cân (kg) *"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <input type="number" id="sale_price_<?= $s ?>" step="500" min="0"
                       oninput="calcSaleTotalInline('<?= $s ?>')"
                       placeholder="Giá/kg (đ) *"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-xl px-3 py-2 text-center">
                <div class="text-xs text-gray-400">Tổng tiền</div>
                <div class="text-base font-bold text-green-600" id="sale_preview_<?= $s ?>">—</div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" id="sale_qty_<?= $s ?>" min="0" placeholder="Số con (tuỳ chọn)"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select id="sale_gender_<?= $s ?>"
                        class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Không xác định</option>
                    <option value="male">Trống</option>
                    <option value="female">Mái</option>
                    <option value="mixed">Hỗn hợp</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="datetime-local" id="sale_time_<?= $s ?>"
                       value="<?= $date_value ?>T<?= $default_time ?>"
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <input type="text" id="sale_note_<?= $s ?>" placeholder="Ghi chú..."
                       class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button onclick="submitSaleInline('<?= $s ?>')"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                💾 Lưu bán gà
            </button>
        </div>
    </div>

    <!-- Danh sách -->
    <div class="space-y-1.5">
        <?php foreach ($sales as $sv): ?>
        <div class="flex justify-between items-center text-xs py-1.5 border-t border-gray-100 dark:border-gray-700">
            <span class="text-gray-500"><?= e(number_format($sv['weight_kg'],1)) ?>kg · <?= e(number_format($sv['price_per_kg'])) ?>đ/kg</span>
            <div class="flex items-center gap-2">
                <span class="font-semibold text-green-600"><?= e(number_format($sv['total_amount'])) ?>đ</span>
                <button onclick="editRecord('sale', <?= e($sv['id']) ?>)" class="text-blue-400 hover:text-blue-600 p-0.5">✏️</button>
                <button onclick="deleteRecord('sale', <?= e($sv['id']) ?>, this)" class="text-red-400 hover:text-red-600 p-0.5">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($sales)): ?>
        <div class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700">Chưa có.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CARD: Cân gà -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-lg">⚖️</div>
            <div>
                <div class="text-sm font-semibold">Cân gà</div>
                <div class="text-xs text-gray-400" id="weight_summary_<?= $session ?>">
                    <?php
                    $session_weights = array_filter($weight_sessions, fn($w) => $w['session_label'] === $session);
                    $session_weights = array_values($session_weights);
                    echo count($session_weights) > 0
                        ? count($session_weights) . ' buổi · avg ' . number_format((float)end($session_weights)['avg_weight_g']) . 'g'
                        : 'Chưa có';
                    ?>
                </div>
            </div>
        </div>
        <button onclick="document.getElementById('weight_form_<?= $s ?>').classList.toggle('hidden')"
                class="bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
            + Cân
        </button>
    </div>

    <!-- Danh sách buổi cân -->
    <?php foreach ($session_weights as $ws): ?>
    <div class="border-t border-gray-100 dark:border-gray-700 pt-2 mt-2">
        <div class="flex justify-between items-center text-xs">
            <div>
                <span class="font-medium text-purple-700 dark:text-purple-300"><?= e($ws['sample_count']) ?> con mẫu</span>
                <span class="text-gray-400 ml-1"><?= date('H:i', strtotime($ws['weighed_at'])) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="font-semibold">avg <?= e(number_format((float)$ws['avg_weight_g'])) ?>g</span>
                <span class="text-gray-400">(<?= e(number_format((float)$ws['avg_weight_g']/1000, 3)) ?>kg)</span>
                <button onclick="openWeightSessionInline(<?= e($ws['id']) ?>, '<?= $s ?>')"
                        class="text-blue-400 hover:text-blue-600 text-xs">📋</button>
                <button onclick="deleteWeightSession(<?= e($ws['id']) ?>, this)"
                        class="text-red-400 hover:text-red-600 text-xs">🗑️</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Inline form cân gà -->
    <div id="weight_form_<?= $s ?>" class="hidden border-t border-gray-100 dark:border-gray-700 pt-3 mt-2">
        <div id="weight_error_<?= $s ?>" class="hidden mb-2 p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>

        <!-- Live average -->
        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl px-3 py-2 mb-3 text-center">
            <div class="text-xs text-gray-400 mb-0.5">Trung bình hiện tại</div>
            <div class="text-2xl font-bold text-purple-700 dark:text-purple-300" id="wavg_<?= $s ?>">—</div>
            <div class="text-xs text-gray-400" id="wcnt_<?= $s ?>">0 con mẫu</div>
        </div>

        <!-- Nhập cân -->
        <div class="flex gap-2 mb-2">
            <input type="number" id="wg_<?= $s ?>" min="10" max="20000" step="1"
                   placeholder="Trọng lượng (gram)..."
                   class="flex-1 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
            <select id="wgender_<?= $s ?>"
                    class="w-24 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="unknown">—</option>
                <option value="male">Trống</option>
                <option value="female">Mái</option>
            </select>
        </div>
        <!-- Quick presets -->
        <div class="flex gap-1.5 mb-2 flex-wrap">
            <?php foreach ([500,750,1000,1250,1500,1750,2000,2500] as $g): ?>
            <button onclick="document.getElementById('wg_<?= $s ?>').value=<?= $g ?>"
                    class="text-xs px-2.5 py-1 rounded-full border border-gray-200 dark:border-gray-600 text-gray-500 hover:border-purple-400 hover:text-purple-600 transition-colors">
                <?= $g >= 1000 ? ($g/1000).'kg' : $g.'g' ?>
            </button>
            <?php endforeach; ?>
        </div>
        <button onclick="addWeightSampleInline('<?= $s ?>')"
                class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 rounded-xl text-sm mb-2">
            + Thêm con này
        </button>

        <!-- Danh sách mẫu -->
        <div id="wsamples_<?= $s ?>" class="space-y-1 mb-2 max-h-48 overflow-y-auto"></div>

        <div class="grid grid-cols-2 gap-2 mb-2">
            <input type="datetime-local" id="wtime_<?= $s ?>"
                   value="<?= $date_value ?>T<?= $default_time ?>"
                   class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
            <input type="text" id="wnote_<?= $s ?>" placeholder="Ghi chú..."
                   class="border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
        <button onclick="finalizeWeightInline('<?= $s ?>')" id="wfinalize_<?= $s ?>"
                class="hidden w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 rounded-xl text-sm">
            ✓ Hoàn tất buổi cân
        </button>
    </div>
</div>

    <!-- CARD: Sức khỏe -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-lg">🏥</div>
                <div>
                    <div class="text-sm font-semibold">Sức khỏe đàn</div>
                    <div class="text-xs text-gray-400">
                        <?php
                        $today_health = array_filter($health_notes ?? [], fn($h) => date('Y-m-d', strtotime($h['recorded_at'])) === date('Y-m-d'));
                        $unresolved   = array_filter($health_notes ?? [], fn($h) => !$h['resolved']);
                        echo count($today_health) > 0 ? count($today_health) . ' ghi chú hôm nay' : 'Chưa có hôm nay';
                        if (count($unresolved) > 0) echo ' · ' . count($unresolved) . ' chưa xử lý';
                        ?>
                    </div>
                </div>
            </div>
            <button onclick="document.getElementById('health_form_<?= $session ?>').classList.toggle('hidden')"
                    class="bg-red-500 hover:bg-red-600 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
                + Ghi
            </button>
        </div>
        <div id="health_form_<?= $session ?>" class="hidden border-t border-gray-100 dark:border-gray-700 pt-3">
            <form onsubmit="return submitHealth(event, this)" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="cycle_id" value="<?= e($cycle->id) ?>">
                <input type="hidden" name="recorded_at" value="<?= date('Y-m-d H:i:s') ?>">
                <textarea name="symptoms" rows="2" required placeholder="Triệu chứng, quan sát..."
                          class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
                <div class="flex gap-2">
                    <select name="severity" class="flex-1 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm">
                        <option value="mild">🟡 Nhẹ</option>
                        <option value="moderate">🟠 Trung bình</option>
                        <option value="severe">🔴 Nặng</option>
                    </select>
                    <input type="file" name="image" accept="image/*" capture="environment"
                           class="flex-1 text-xs text-gray-500 file:py-1.5 file:px-2 file:rounded-lg file:border-0 file:text-xs file:bg-red-50 file:text-red-700">
                </div>
                <button type="submit" class="w-full bg-red-500 text-white text-sm font-semibold py-2 rounded-xl">💾 Lưu</button>
            </form>
        </div>
        <?php foreach ($today_health as $hn): ?>
        <?php $sev_icon = ['mild'=>'🟡','moderate'=>'🟠','severe'=>'🔴'][$hn['severity']] ?? '⚪'; ?>
        <div class="mt-2 flex items-start justify-between text-sm border-t border-gray-100 dark:border-gray-700 pt-2" id="health_row_<?= e($hn['id']) ?>">
            <div class="flex-1 min-w-0">
                <span><?= $sev_icon ?> <?= e(mb_substr($hn['symptoms'], 0, 40)) ?><?= mb_strlen($hn['symptoms']) > 40 ? '...' : '' ?></span>
                <div class="text-xs text-gray-400 mt-0.5"><?= date('H:i', strtotime($hn['recorded_at'])) ?><?= $hn['resolved'] ? ' · <span class="text-green-500">Đã xử lý</span>' : '' ?></div>
            </div>
            <div class="flex items-center gap-1.5 ml-2 flex-shrink-0">
                <?php if (!$hn['resolved']): ?>
                <button onclick="resolveHealth(<?= e($hn['id']) ?>)" class="text-xs text-green-600 p-0.5" title="Đã xử lý">✅</button>
                <?php endif; ?>
                <button onclick="editHealth(<?= e($hn['id']) ?>)" class="text-blue-400 hover:text-blue-600 p-0.5" title="Sửa">✏️</button>
                <button onclick="deleteHealth(<?= e($hn['id']) ?>, this)" class="text-red-400 hover:text-red-600 p-0.5" title="Xóa">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($today_health)): ?>
        <div class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700 mt-2">Chưa có ghi chú hôm nay.</div>
        <?php endif; ?>
    </div>

    <!-- CARD: Vaccine -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-100 dark:bg-teal-900/30 flex items-center justify-center text-lg">💉</div>
                <div>
                    <div class="text-sm font-semibold">Vaccine</div>
                    <div class="text-xs text-gray-400">
                        <?php
                        $upcoming_vac = array_filter($vaccine_schedules ?? [], fn($v) => !$v['done'] && strtotime($v['scheduled_date']) <= strtotime('+' . ($v['remind_days'] ?? 1) . ' days'));
                        $today_vac    = array_filter($vaccine_schedules ?? [], fn($v) => !$v['done'] && $v['scheduled_date'] === date('Y-m-d'));
                        if (count($today_vac) > 0) echo '⏰ ' . count($today_vac) . ' lịch hôm nay';
                        elseif (count($upcoming_vac) > 0) echo count($upcoming_vac) . ' sắp tới';
                        else echo 'Không có lịch gần';
                        ?>
                    </div>
                </div>
            </div>
            <button onclick="document.getElementById('vaccine_form_<?= $session ?>').classList.toggle('hidden')"
                    class="bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors">
                + Thêm
            </button>
        </div>
        <?php foreach ($upcoming_vac as $vac): ?>
        <?php $days_left = (int)((strtotime($vac['scheduled_date']) - strtotime('today')) / 86400); ?>
        <div class="flex items-center justify-between text-sm border-t border-gray-100 dark:border-gray-700 pt-2 mt-2" id="vac_row_<?= e($vac['id']) ?>">
            <div class="flex-1 min-w-0">
                <span class="font-medium"><?= e($vac['vaccine_name']) ?></span>
                <span class="text-xs text-gray-400 ml-1"><?= $days_left === 0 ? '· Hôm nay' : '· ' . $days_left . ' ngày nữa' ?></span>
                <?php $method_labels = ['drink'=>'💧','inject'=>'💉','eye_drop'=>'👁️','spray'=>'🌫️']; ?>
                <span class="text-xs text-gray-400 ml-1"><?= $method_labels[$vac['method']] ?? '' ?></span>
            </div>
            <div class="flex items-center gap-1.5 ml-2 flex-shrink-0">
                <button onclick="doneVaccine(<?= e($vac['id']) ?>)" class="text-xs text-green-600 font-medium p-0.5" title="Đã tiêm">✅</button>
                <button onclick="editVaccine(<?= e($vac['id']) ?>)" class="text-blue-400 hover:text-blue-600 p-0.5" title="Sửa">✏️</button>
                <button onclick="deleteVaccine(<?= e($vac['id']) ?>, this)" class="text-red-400 hover:text-red-600 p-0.5" title="Xóa">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($upcoming_vac)): ?>
        <div class="text-xs text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700 mt-2">Không có lịch vaccine gần đây.</div>
        <?php endif; ?>
        <div id="vaccine_form_<?= $session ?>" class="hidden border-t border-gray-100 dark:border-gray-700 pt-3 mt-2">
            <form onsubmit="return submitVaccine(event, this)" class="space-y-2">
                <input type="hidden" name="cycle_id" value="<?= e($cycle->id) ?>">
                <?php if (!empty($vaccine_program_items)): ?>
                <div>
                    <label class="block text-xs font-medium mb-1">Chọn từ bộ lịch</label>
                    <select onchange="fillVaccineFromProgram(this, '<?= $session ?>')"
                            class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
                        <option value="">— Chọn vaccine —</option>
                        <?php foreach ($vaccine_program_items as $vpi): ?>
                        <option value="<?= e($vpi['id']) ?>"
                                data-name="<?= e($vpi['vaccine_name']) ?>"
                                data-method="<?= e($vpi['method']) ?>"
                                data-day-age="<?= e($vpi['day_age']) ?>"
                                data-brand="<?= e($vpi['brand_name'] ?? '') ?>">
                            <?= e($vpi['vaccine_name']) ?> · ngày <?= e($vpi['day_age']) ?> <?= $vpi['brand_name'] ? '(' . e($vpi['brand_name']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <input type="text" name="vaccine_name" id="vac_name_<?= $session ?>" required placeholder="Tên vaccine..."
                       class="w-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
                <div class="flex gap-2">
                    <input type="date" name="scheduled_date" id="vac_date_<?= $session ?>" value="<?= date('Y-m-d') ?>" required
                           class="flex-1 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none">
                    <select name="method" id="vac_method_<?= $session ?>" class="flex-1 border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2 text-sm focus:outline-none">
                        <option value="drink">💧 Uống</option>
                        <option value="inject">💉 Tiêm</option>
                        <option value="eye_drop">👁️ Nhỏ mắt</option>
                        <option value="spray">🌫️ Phun</option>
                    </select>
                </div>
                <input type="hidden" name="remind_days" value="1">
                <button type="submit" class="w-full bg-teal-600 text-white text-sm font-semibold py-2 rounded-xl">💾 Lưu lịch</button>
            </form>
        </div>
    </div>

</div>
<?php
} // end render_session_cards

ob_start();
?>

<?php if (!$cycle): ?>
    <div class="text-center py-16 text-gray-400">
        <div class="text-5xl mb-4">🐔</div>
        <p>Không tìm thấy cycle</p>
        <a href="/barns" class="mt-3 inline-block text-blue-600 hover:underline text-sm">← Quay lại</a>
    </div>
<?php else: ?>

<!-- quick switcher -->
<?php if (!empty($active_cycles)): ?>
<div class="mb-4">
    <div class="flex gap-2 overflow-x-auto pb-1" style="-webkit-overflow-scrolling:touch;scrollbar-width:none;">
        <?php foreach ($active_cycles as $ac): ?>
        <?php $is_current = $ac->id === $cycle->id; ?>
        <a href="/events/create?cycle_id=<?= e($ac->id) ?>"
           class="flex-shrink-0 flex items-center gap-2 px-3 py-2 rounded-xl border transition-all
                  <?= $is_current
                      ? 'bg-blue-600 border-blue-600 text-white shadow-md'
                      : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-blue-400' ?>">
            <span><?= $is_current ? '📍' : '🐔' ?></span>
            <div>
                <div class="text-xs font-semibold leading-tight"><?= e($ac->code) ?></div>
                <div class="text-xs leading-tight <?= $is_current ? 'text-blue-200' : 'text-gray-400' ?>">
                    <?= e(number_format($ac->current_quantity)) ?> con · <?= e($ac->age_in_days()) ?>n
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- summary strip -->
<div class="bg-blue-600 rounded-2xl p-4 mb-4 flex justify-between items-center">
    <div class="text-center">
        <div class="text-xl font-bold text-white"><?= e(number_format($cycle->current_quantity)) ?></div>
        <div class="text-xs text-blue-200">Con hiện tại</div>
    </div>
    <div class="w-px h-9 bg-blue-400"></div>
    <div class="text-center">
        <div class="text-xl font-bold text-white"><?= e($cycle->age_in_days()) ?></div>
        <div class="text-xs text-blue-200">Ngày tuổi</div>
    </div>
    <div class="w-px h-9 bg-blue-400"></div>
    <div class="text-center">
        <div class="text-xl font-bold text-white"><?= e($cycle->mortality_rate()) ?>%</div>
        <div class="text-xs text-blue-200">Hao hụt</div>
    </div>
</div>

<!-- BANNER: Bữa gần nhất + hỏi kiểm tra máng -->
<?php if ($latest_feed): ?>
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4 mb-3">
    <div class="flex items-center justify-between">
        <div>
            <div class="text-xs text-gray-400 mb-0.5">🌾 Bữa gần nhất</div>
            <div class="text-sm font-semibold">
                <?= e($latest_feed['brand_name']) ?> · <?= e($latest_feed['feed_code']) ?>
                <span class="font-normal text-gray-400 ml-1">
                    <?= e($latest_feed['bags']) ?> bao · <?= date('H:i d/m', strtotime($latest_feed['recorded_at'])) ?>
                </span>
            </div>
        </div>
        <?php if (!$latest_feed_has_trough): ?>
        <button onclick="openTroughModal(<?= e($latest_feed['id']) ?>, '<?= e($latest_feed['brand_name'].' · '.$latest_feed['feed_code']) ?>', <?= e($latest_feed['kg_actual']) ?>)"
                class="flex-shrink-0 ml-3 text-xs bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 font-semibold px-3 py-2 rounded-xl hover:bg-orange-200 transition-colors">
            🪣 Còn bao nhiêu?
        </button>
        <?php else: ?>
        <span class="text-xs text-green-600 bg-green-50 dark:bg-green-900/20 px-3 py-2 rounded-xl">✓ Đã kiểm tra</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- BANNER: Nhắc nhở bỏ bữa -->
<?php if ($banner_level > 0): ?>
<div class="rounded-2xl p-4 mb-3 <?= $banner_level >= 2
    ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
    : 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800' ?>">
    <div class="flex items-start gap-3">
        <span class="text-xl"><?= $banner_level >= 2 ? '🔴' : '⚠️' ?></span>
        <div>
            <div class="text-sm font-semibold <?= $banner_level >= 2 ? 'text-red-700 dark:text-red-300' : 'text-yellow-700 dark:text-yellow-300' ?>">
                <?= $banner_level >= 2 ? 'Nhiều bữa chưa ghi!' : 'Bữa trước chưa ghi' ?>
            </div>
            <div class="text-xs mt-0.5 <?= $banner_level >= 2 ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400' ?>">
                <?= $banner_level >= 2
                    ? "Phát hiện {$missed_sessions} bữa liên tiếp chưa có dữ liệu cho ăn"
                    : 'Bữa ngay trước đó chưa có dữ liệu cho ăn' ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- header ngày + tab -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 mb-4 overflow-hidden">
    <div class="px-4 pt-3 pb-2 flex items-center justify-between">
        <button onclick="changeDate(-1)" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 text-lg">‹</button>
        <div class="text-center">
            <div class="text-xs text-gray-400"><?= e($cycle->code) ?> · <?= e($barn->name) ?></div>
            <div class="text-sm font-bold">Ngày: <span class="text-blue-600"><?= e($today) ?></span></div>
        </div>
        <button onclick="changeDate(1)"
                class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 text-lg
                       <?= $date_value >= date('Y-m-d') ? 'opacity-30 cursor-not-allowed' : '' ?>">›</button>
    </div>
    <div class="flex border-t border-gray-100 dark:border-gray-700">
        <button onclick="switchTab('morning', this)"
                class="tab-btn flex-1 py-2.5 text-sm font-semibold text-blue-600 border-b-2 border-blue-600">
            🌅 Buổi Sáng
            <?php if (!empty($morning_feeds)): ?>
            <span class="ml-1 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 px-1.5 py-0.5 rounded-full"><?= count($morning_feeds) ?></span>
            <?php endif; ?>
        </button>
        <button onclick="switchTab('evening', this)"
                class="tab-btn flex-1 py-2.5 text-sm font-medium text-gray-400 border-b-2 border-transparent">
            🌙 Buổi Tối
            <?php if (!empty($evening_feeds)): ?>
            <span class="ml-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-1.5 py-0.5 rounded-full"><?= count($evening_feeds) ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>

<!-- TAB: sáng -->
<div id="content_morning">
    <?php render_session_cards(
        'morning',
        $morning_feeds, $morning_deaths,
        $morning_medications, $morning_sales,
        $cycle, $feed_types, $medications_list, $date_value,
        $today_weight_sessions ?? [],
        $vaccine_program_items ?? []
    ); ?>
</div>

<!-- TAB: tối -->
<div id="content_evening" class="hidden">
    <?php render_session_cards(
        'evening',
        $evening_feeds, $evening_deaths,
        $evening_medications, $evening_sales,
        $cycle, $feed_types, $medications_list, $date_value,
        $today_weight_sessions ?? [],
        $vaccine_program_items ?? []
    ); ?>
</div>

<!-- ============================================================ -->
<!-- MODALS (chỉ giữ: trough, edit *, password) -->
<!-- ============================================================ -->
<div id="modal_backdrop" onclick="closeModal()"
     class="hidden fixed inset-0 bg-black/40 z-30"></div>

<!-- MODAL: Kiểm tra máng -->
<div id="modal_trough" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div>
                <div class="text-base font-bold">🪣 Kiểm tra máng</div>
                <div class="text-xs text-gray-400 mt-0.5" id="trough_feed_label">—</div>
            </div>
            <button onclick="closeModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="trough_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl px-4 py-3 mb-4 text-sm text-center">
            Lần cho ăn: <span class="font-bold text-blue-600" id="trough_feed_kg">—</span> kg đổ vào
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium mb-2">Cám còn lại trong máng <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-4 gap-2 mb-3">
                    <?php foreach ([0, 10, 25, 50] as $pct): ?>
                    <button type="button" onclick="setTroughPct(<?= $pct ?>)" id="trough_btn_<?= $pct ?>"
                            class="py-2.5 text-sm font-bold rounded-xl border-2 transition-all
                                   <?= $pct === 0
                                       ? 'border-green-400 text-green-600 bg-green-50'
                                       : 'border-gray-200 text-gray-500 bg-white dark:bg-gray-700 dark:border-gray-600' ?>">
                        <?= $pct ?>%
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="flex items-center gap-3">
                    <input type="range" id="trough_slider" min="0" max="100" step="5" value="0"
                           oninput="syncTroughPct(this.value)" class="flex-1 accent-orange-500">
                    <div class="flex items-center gap-1">
                        <input type="number" id="trough_pct_input" min="0" max="100" step="5" value="0"
                               oninput="syncTroughPct(this.value)"
                               class="w-14 border border-orange-200 bg-white dark:bg-gray-700 rounded-lg px-2 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-orange-400">
                        <span class="text-xs text-gray-400">%</span>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 text-center">
                <div class="text-xs text-gray-400 mb-1">Gà thực ăn từ lần này</div>
                <div class="text-xl font-bold text-blue-600" id="trough_consumed_preview">—</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Thời gian kiểm tra</label>
                    <input type="datetime-local" id="trough_time"
                           value="<?= e($date_value) ?>T<?= date('H:i') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Ghi chú</label>
                    <input type="text" id="trough_note" placeholder="Tùy chọn..."
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <input type="hidden" id="trough_ref_feed_id" value="">
            <input type="hidden" id="trough_feed_kg_actual" value="0">
            <button onclick="submitTroughCheck()"
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
                Lưu kiểm tra máng
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Password override -->
<div id="modal_pass" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold">🔐 Xác nhận mật khẩu</div>
            <button onclick="closePassModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <p class="text-sm text-gray-500 mb-4" id="pass_modal_reason">Bản ghi này đã quá hạn. Nhập mật khẩu để tiếp tục.</p>
        <div class="space-y-3">
            <input type="password" id="override_pass_input" placeholder="Nhập mật khẩu..."
                   class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div id="pass_error" class="hidden text-xs text-red-500 px-1"></div>
            <button onclick="confirmPass()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm">
                Xác nhận
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Edit feed -->
<div id="modal_edit_feed" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold">✏️ Sửa cho ăn</div>
            <button onclick="closeModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="edit_feed_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Số bao</label>
                    <input type="number" id="ef_bags" step="0.5" min="0"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Kg thực tế</label>
                    <input type="number" id="ef_kg" step="0.1" min="0"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Thời gian</label>
                    <input type="datetime-local" id="ef_time"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Ghi chú</label>
                    <input type="text" id="ef_note"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <input type="hidden" id="ef_id">
            <input type="hidden" id="ef_override_pass" value="">
            <button onclick="submitEditFeed()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm">Lưu thay đổi</button>
        </div>
    </div>
</div>

<!-- MODAL: Edit death -->
<div id="modal_edit_death" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold">✏️ Sửa hao hụt</div>
            <button onclick="closeModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="edit_death_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Số con</label>
                    <input type="number" id="ed_quantity" min="1"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Thời gian</label>
                    <input type="datetime-local" id="ed_time"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Lý do</label>
                <input type="text" id="ed_reason"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Triệu chứng</label>
                <textarea id="ed_symptoms" rows="2"
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <input type="hidden" id="ed_id">
            <input type="hidden" id="ed_override_pass" value="">
            <button onclick="submitEditDeath()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm">Lưu thay đổi</button>
        </div>
    </div>
</div>

<!-- MODAL: Edit medication -->
<div id="modal_edit_medication" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold">✏️ Sửa thuốc</div>
            <button onclick="closeModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="edit_med_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium mb-1">Tên thuốc</label>
                <input type="text" id="em_name"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Liều lượng</label>
                    <input type="number" id="em_dosage" step="0.01"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Đơn vị</label>
                    <input type="text" id="em_unit"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Cách dùng</label>
                    <select id="em_method"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="water">Uống nước</option>
                        <option value="inject">Tiêm</option>
                        <option value="feed_mix">Trộn cám</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Thời gian</label>
                    <input type="datetime-local" id="em_time"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Ghi chú</label>
                    <input type="text" id="em_note"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <input type="hidden" id="em_id">
            <input type="hidden" id="em_override_pass" value="">
            <button onclick="submitEditMedication()"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm">Lưu thay đổi</button>
        </div>
    </div>
</div>

<!-- MODAL: Edit sale -->
<div id="modal_edit_sale" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold">✏️ Sửa bán gà</div>
            <button onclick="closeModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="edit_sale_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Cân nặng (kg)</label>
                    <input type="number" id="es_weight" step="0.1" oninput="calcEditSaleTotal()"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Giá/kg (đ)</label>
                    <input type="number" id="es_price" step="500" oninput="calcEditSaleTotal()"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-3 text-center">
                <div class="text-xs text-gray-400">Tổng tiền</div>
                <div class="text-xl font-bold text-green-600" id="es_total_preview">—</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Thời gian</label>
                    <input type="datetime-local" id="es_time"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Ghi chú</label>
                    <input type="text" id="es_note"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <input type="hidden" id="es_id">
            <input type="hidden" id="es_override_pass" value="">
            <button onclick="submitEditSale()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-xl text-sm">Lưu thay đổi</button>
        </div>
    </div>
</div>

<!-- MODAL: Edit Health -->
<div id="modal_edit_health" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold">✏️ Sửa ghi chú sức khỏe</div>
            <button onclick="closeModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="edit_health_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium mb-1">Triệu chứng <span class="text-red-500">*</span></label>
                <textarea id="eh_symptoms" rows="3"
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Mức độ</label>
                <select id="eh_severity"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="mild">🟡 Nhẹ</option>
                    <option value="moderate">🟠 Trung bình</option>
                    <option value="severe">🔴 Nặng</option>
                </select>
            </div>
            <input type="hidden" id="eh_id">
            <button onclick="submitEditHealth()"
                    class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 rounded-xl text-sm">Lưu thay đổi</button>
        </div>
    </div>
</div>

<!-- MODAL: Edit Vaccine -->
<div id="modal_edit_vaccine" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-5 pt-5 pb-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <div class="text-base font-bold">✏️ Sửa lịch vaccine</div>
            <button onclick="closeModal()" class="text-gray-400 text-2xl leading-none">×</button>
        </div>
        <div id="edit_vaccine_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium mb-1">Tên vaccine <span class="text-red-500">*</span></label>
                <input type="text" id="ev_name"
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Ngày tiêm</label>
                    <input type="date" id="ev_date"
                           class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Cách tiêm</label>
                    <select id="ev_method"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <option value="drink">💧 Uống</option>
                        <option value="inject">💉 Tiêm</option>
                        <option value="eye_drop">👁️ Nhỏ mắt</option>
                        <option value="spray">🌫️ Phun</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Ghi chú</label>
                <input type="text" id="ev_notes" placeholder="Tùy chọn..."
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500">
            </div>
            <input type="hidden" id="ev_id">
            <button onclick="submitEditVaccine()"
                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-3 rounded-xl text-sm">Lưu thay đổi</button>
        </div>
    </div>
</div>

<?php endif; ?>
<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>

<script>
const CYCLE_ID = <?= e($cycle->id) ?>;
const DAY_AGE  = <?= (int)((time() - strtotime($cycle->start_date)) / 86400) + 1 ?>;
const DATE_VAL = '<?= e($date_value) ?>';

// ================================================================
// TAB + NAVIGATION
// ================================================================
function switchTab(session, btn) {
    ['morning','evening'].forEach(s => document.getElementById('content_' + s).classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(t => {
        t.classList.remove('text-blue-600','border-blue-600','font-semibold');
        t.classList.add('text-gray-400','border-transparent','font-medium');
    });
    btn.classList.add('text-blue-600','border-blue-600','font-semibold');
    btn.classList.remove('text-gray-400','border-transparent','font-medium');
    document.getElementById('content_' + session).classList.remove('hidden');
}

function changeDate(delta) {
    const d = new Date(DATE_VAL);
    d.setDate(d.getDate() + delta);
    if (d > new Date()) return;
    const y   = d.getFullYear();
    const m   = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    window.location.href = `/events/create?cycle_id=${CYCLE_ID}&date=${y}-${m}-${day}`;
}

// ================================================================
// MODAL (chỉ dùng cho: trough, edit *, password)
// ================================================================
let current_modal = null;
function openModal(type) {
    current_modal = type;
    document.getElementById('modal_backdrop').classList.remove('hidden');
    document.getElementById('modal_' + type).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    if (current_modal) document.getElementById('modal_' + current_modal).classList.add('hidden');
    document.getElementById('modal_backdrop').classList.add('hidden');
    document.body.style.overflow = '';
    current_modal = null;
}

// ================================================================
// INLINE FORM HELPERS
// ================================================================

// ── Per-session kg/bao ───────────────────────────────────────────
const _kpb = { morning: 0, evening: 0 };

function updateKgPerBag_morning(sel) {
    _kpb.morning = parseFloat(sel.options[sel.selectedIndex].dataset.kg || 0);
    calcFeedKg_morning();
}
function updateKgPerBag_evening(sel) {
    _kpb.evening = parseFloat(sel.options[sel.selectedIndex].dataset.kg || 0);
    calcFeedKg_evening();
}
function calcFeedKg_morning() {
    const bags = parseFloat(document.getElementById('feed_bags_morning')?.value || 0);
    const kg   = document.getElementById('feed_kg_morning');
    if (kg && kg.readOnly) kg.value = (bags * _kpb.morning).toFixed(1);
}
function calcFeedKg_evening() {
    const bags = parseFloat(document.getElementById('feed_bags_evening')?.value || 0);
    const kg   = document.getElementById('feed_kg_evening');
    if (kg && kg.readOnly) kg.value = (bags * _kpb.evening).toFixed(1);
}
function quickFill_morning(n) { const el = document.getElementById('feed_bags_morning'); if(el) el.value=n; calcFeedKg_morning(); }
function quickFill_evening(n) { const el = document.getElementById('feed_bags_evening'); if(el) el.value=n; calcFeedKg_evening(); }
function toggleKgManual_morning() {
    const kg = document.getElementById('feed_kg_morning'); if(!kg) return;
    kg.readOnly = !kg.readOnly;
    kg.classList.toggle('bg-gray-50', kg.readOnly);
    kg.classList.toggle('bg-white',   !kg.readOnly);
    if (kg.readOnly) calcFeedKg_morning();
}
function toggleKgManual_evening() {
    const kg = document.getElementById('feed_kg_evening'); if(!kg) return;
    kg.readOnly = !kg.readOnly;
    kg.classList.toggle('bg-gray-50', kg.readOnly);
    kg.classList.toggle('bg-white',   !kg.readOnly);
    if (kg.readOnly) calcFeedKg_evening();
}

// ── Shared inline postCare ────────────────────────────────────────
async function postCareInline(url, data, error_el_id) {
    const el = document.getElementById(error_el_id);
    if (el) el.classList.add('hidden');
    try {
        const res  = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(data)
        });
        const json = await res.json();
        if (!json.ok) {
            if (el) { el.textContent = json.message; el.classList.remove('hidden'); }
            return false;
        }
        return true;
    } catch(e) {
        if (el) { el.textContent = 'Lỗi kết nối'; el.classList.remove('hidden'); }
        return false;
    }
}

// postCare alias dùng cho modal edit (giữ tương thích)
async function postCare(url, data, error_el) {
    return postCareInline(url, data, error_el);
}

// ── Feed inline ───────────────────────────────────────────────────
async function submitFeedInline(session) {
    const ok = await postCareInline('/care/feed', {
        cycle_id:     CYCLE_ID,
        feed_type_id: document.getElementById('feed_type_id_' + session)?.value,
        bags:         document.getElementById('feed_bags_' + session)?.value,
        kg_actual:    document.getElementById('feed_kg_' + session)?.value,
        kg_per_bag:   _kpb[session],
        session,
        recorded_at:  document.getElementById('feed_time_' + session)?.value.replace('T',' ') + ':00',
        note:         document.getElementById('feed_note_' + session)?.value,
    }, 'feed_error_' + session);
    if (ok) location.reload();
}

// ── Death inline ──────────────────────────────────────────────────
async function submitDeathInline(session) {
    const ok = await postCareInline('/care/death', {
        cycle_id:    CYCLE_ID,
        quantity:    document.getElementById('death_qty_' + session)?.value,
        reason:      document.getElementById('death_reason_' + session)?.value,
        symptoms:    document.getElementById('death_symptoms_' + session)?.value,
        recorded_at: document.getElementById('death_time_' + session)?.value.replace('T',' ') + ':00',
    }, 'death_error_' + session);
    if (ok) location.reload();
}

// ── Medication inline ─────────────────────────────────────────────
function fillMedicationInline(sel, session) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('med_id_'   + session).value = opt.value;
    document.getElementById('med_name_' + session).value = opt.dataset.name;
    document.getElementById('med_unit_' + session).value = opt.dataset.unit;
}
async function submitMedInline(session) {
    const ok = await postCareInline('/care/medication', {
        cycle_id:        CYCLE_ID,
        medication_id:   document.getElementById('med_id_'     + session)?.value,
        medication_name: document.getElementById('med_name_'   + session)?.value,
        dosage:          document.getElementById('med_dosage_'  + session)?.value,
        unit:            document.getElementById('med_unit_'    + session)?.value,
        method:          document.getElementById('med_method_'  + session)?.value,
        note:            document.getElementById('med_note_'    + session)?.value,
        recorded_at:     document.getElementById('med_time_'    + session)?.value.replace('T',' ') + ':00',
    }, 'med_error_' + session);
    if (ok) location.reload();
}

// ── Sale inline ───────────────────────────────────────────────────
function calcSaleTotalInline(session) {
    const w  = parseFloat(document.getElementById('sale_weight_' + session)?.value || 0);
    const p  = parseFloat(document.getElementById('sale_price_'  + session)?.value || 0);
    const el = document.getElementById('sale_preview_' + session);
    if (el) el.textContent = w * p > 0 ? new Intl.NumberFormat('vi-VN').format(w * p) + ' đ' : '—';
}
async function submitSaleInline(session) {
    const ok = await postCareInline('/care/sale', {
        cycle_id:     CYCLE_ID,
        weight_kg:    document.getElementById('sale_weight_' + session)?.value,
        price_per_kg: document.getElementById('sale_price_'  + session)?.value,
        quantity:     document.getElementById('sale_qty_'    + session)?.value,
        gender:       document.getElementById('sale_gender_' + session)?.value,
        note:         document.getElementById('sale_note_'   + session)?.value,
        recorded_at:  document.getElementById('sale_time_'   + session)?.value.replace('T',' ') + ':00',
    }, 'sale_error_' + session);
    if (ok) location.reload();
}

// ── Weight inline ─────────────────────────────────────────────────
const _wstate = {
    morning: { session_id: null, samples: [] },
    evening: { session_id: null, samples: [] },
};

async function addWeightSampleInline(session) {
    const g      = parseFloat(document.getElementById('wg_' + session)?.value);
    const gender = document.getElementById('wgender_' + session)?.value;
    const err    = document.getElementById('weight_error_' + session);
    if (!g || g <= 0) {
        if (err) { err.textContent = 'Nhập trọng lượng hợp lệ'; err.classList.remove('hidden'); }
        return;
    }
    if (err) err.classList.add('hidden');

    if (!_wstate[session].session_id) {
        const time_val = document.getElementById('wtime_' + session)?.value || '';
        const body = new URLSearchParams({
            cycle_id:      CYCLE_ID,
            day_age:       DAY_AGE,
            weighed_at:    time_val.replace('T',' ') + ':00',
            note:          document.getElementById('wnote_' + session)?.value || '',
            session_label: session,
        });
        const res  = await fetch('/weight/session', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const json = await res.json();
        if (!json.ok) { if (err) { err.textContent = json.message; err.classList.remove('hidden'); } return; }
        _wstate[session].session_id = json.session_id;
    }

    const res2  = await fetch(`/weight/session/${_wstate[session].session_id}/sample`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ weight_g: g, gender }),
    });
    const json2 = await res2.json();
    if (!json2.ok) { if (err) { err.textContent = json2.message; err.classList.remove('hidden'); } return; }

    _wstate[session].samples = json2.samples;
    renderWeightSamplesInline(session, json2.samples);
    updateWeightAvgInline(session, json2.samples);

    const inp = document.getElementById('wg_' + session);
    if (inp) { inp.value = ''; inp.focus(); }
    const fin = document.getElementById('wfinalize_' + session);
    if (fin) fin.classList.remove('hidden');
}

function renderWeightSamplesInline(session, samples) {
    const el = document.getElementById('wsamples_' + session);
    if (!el) return;
    if (!samples.length) { el.innerHTML = ''; return; }
    el.innerHTML = samples.map((s, i) => `
        <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700 rounded-lg px-2.5 py-1.5 text-xs" id="wsr_${s.id}">
            <span class="text-gray-400">#${i+1}</span>
            <span class="font-semibold text-purple-700 dark:text-purple-300">${Number(s.weight_g).toLocaleString('vi-VN')}g</span>
            <span class="text-gray-400">${s.gender === 'male' ? '♂ Trống' : s.gender === 'female' ? '♀ Mái' : '—'}</span>
            <button onclick="removeWeightSampleInline(${s.id}, '${session}')" class="text-red-400 hover:text-red-600 px-1 text-sm">×</button>
        </div>
    `).join('');
}

function updateWeightAvgInline(session, samples) {
    const avg_el = document.getElementById('wavg_' + session);
    const cnt_el = document.getElementById('wcnt_' + session);
    if (!samples.length) {
        if (avg_el) avg_el.textContent = '—';
        if (cnt_el) cnt_el.textContent = '0 con mẫu';
        return;
    }
    const avg = samples.reduce((s, x) => s + parseFloat(x.weight_g), 0) / samples.length;
    if (avg_el) avg_el.textContent = Math.round(avg).toLocaleString('vi-VN') + 'g';
    if (cnt_el) cnt_el.textContent = samples.length + ' con mẫu';
}

async function removeWeightSampleInline(sample_id, session) {
    const res  = await fetch(`/weight/sample/${sample_id}/delete`, { method: 'POST' });
    const json = await res.json();
    if (json.ok) {
        _wstate[session].samples = _wstate[session].samples.filter(s => s.id !== sample_id);
        renderWeightSamplesInline(session, _wstate[session].samples);
        updateWeightAvgInline(session, _wstate[session].samples);
    }
}

async function openWeightSessionInline(session_id, session) {
    _wstate[session].session_id = session_id;
    _wstate[session].samples    = [];
    const formEl = document.getElementById('weight_form_' + session);
    if (formEl) formEl.classList.remove('hidden');
    const samplesEl = document.getElementById('wsamples_' + session);
    if (samplesEl) samplesEl.innerHTML = '<div class="text-xs text-gray-400 text-center py-1">Đang tải...</div>';
    const res  = await fetch(`/weight/session/${session_id}/samples`);
    const data = await res.json();
    if (data.ok) {
        _wstate[session].samples = data.samples;
        renderWeightSamplesInline(session, data.samples);
        updateWeightAvgInline(session, data.samples);
        const fin = document.getElementById('wfinalize_' + session);
        if (fin) fin.classList.remove('hidden');
    }
}

function finalizeWeightInline(session) { location.reload(); }

async function deleteWeightSession(session_id, btn) {
    if (!confirm('Xóa buổi cân này?')) return;
    const res  = await fetch(`/weight/session/${session_id}/delete`, { method: 'POST' });
    const json = await res.json();
    if (json.ok) location.reload();
}

// ================================================================
// TROUGH CHECK MODAL
// ================================================================
let trough_feed_kg = 0;

function openTroughModal(feed_id, feed_label, feed_kg) {
    document.getElementById('trough_ref_feed_id').value     = feed_id;
    document.getElementById('trough_feed_label').textContent = feed_label;
    document.getElementById('trough_feed_kg').textContent    = feed_kg;
    document.getElementById('trough_feed_kg_actual').value   = feed_kg;
    trough_feed_kg = parseFloat(feed_kg);
    setTroughPct(0);
    openModal('trough');
}
function setTroughPct(pct) {
    document.getElementById('trough_pct_input').value = pct;
    document.getElementById('trough_slider').value    = pct;
    highlightTroughBtn(pct);
    updateTroughPreview(pct);
}
function syncTroughPct(val) {
    const pct = Math.min(100, Math.max(0, parseInt(val) || 0));
    document.getElementById('trough_slider').value    = pct;
    document.getElementById('trough_pct_input').value = pct;
    highlightTroughBtn(pct);
    updateTroughPreview(pct);
}
function highlightTroughBtn(pct) {
    [0,10,25,50].forEach(p => {
        const btn = document.getElementById('trough_btn_' + p);
        if (!btn) return;
        btn.classList.remove(
            'border-green-400','text-green-600','bg-green-50',
            'border-orange-400','text-orange-600','bg-orange-50',
            'border-gray-200','text-gray-500','bg-white',
            'dark:bg-gray-700','dark:border-gray-600'
        );
        if (p === pct) {
            btn.classList.add(p === 0 ? 'border-green-400' : 'border-orange-400');
            btn.classList.add(p === 0 ? 'text-green-600'   : 'text-orange-600');
            btn.classList.add(p === 0 ? 'bg-green-50'      : 'bg-orange-50');
        } else {
            btn.classList.add('border-gray-200','text-gray-500','bg-white','dark:bg-gray-700','dark:border-gray-600');
        }
    });
}
function updateTroughPreview(pct) {
    document.getElementById('trough_consumed_preview').textContent =
        trough_feed_kg > 0 ? (trough_feed_kg * (1 - pct/100)).toFixed(1) + ' kg' : '—';
}
async function submitTroughCheck() {
    const ok = await postCareInline('/care/trough-check', {
        cycle_id:      CYCLE_ID,
        ref_feed_id:   document.getElementById('trough_ref_feed_id').value,
        remaining_pct: document.getElementById('trough_pct_input').value,
        checked_at:    document.getElementById('trough_time').value.replace('T',' ') + ':00',
        note:          document.getElementById('trough_note').value,
    }, 'trough_error');
    if (ok) { closeModal(); location.reload(); }
}

// ================================================================
// EDIT & DELETE (modal-based, giữ nguyên)
// ================================================================
let _pending_action = null;

async function editRecord(type, id) {
    const res  = await fetch(`/care/${type}/${id}`);
    const json = await res.json();
    if (!json.ok && json.need_pass) {
        askPassword({ type, id, action: 'edit' }, 'Bản ghi quá hạn sửa (3 ngày). Nhập mật khẩu:');
        return;
    }
    if (!json.ok) { alert(json.message); return; }
    prefillEdit(type, json.data);
}

function prefillEdit(type, data) {
    const fmt = (dt) => dt ? dt.replace(' ', 'T').substring(0, 16) : '';
    if (type === 'feed') {
        document.getElementById('ef_id').value    = data.id;
        document.getElementById('ef_bags').value  = data.bags;
        document.getElementById('ef_kg').value    = data.kg_actual;
        document.getElementById('ef_time').value  = fmt(data.recorded_at);
        document.getElementById('ef_note').value  = data.note || '';
        document.getElementById('ef_override_pass').value = '';
        openModal('edit_feed');
    } else if (type === 'death') {
        document.getElementById('ed_id').value       = data.id;
        document.getElementById('ed_quantity').value = data.quantity;
        document.getElementById('ed_reason').value   = data.reason || '';
        document.getElementById('ed_symptoms').value = data.symptoms || '';
        document.getElementById('ed_time').value     = fmt(data.recorded_at);
        document.getElementById('ed_override_pass').value = '';
        openModal('edit_death');
    } else if (type === 'medication') {
        document.getElementById('em_id').value     = data.id;
        document.getElementById('em_name').value   = data.medication_name;
        document.getElementById('em_dosage').value = data.dosage;
        document.getElementById('em_unit').value   = data.unit;
        document.getElementById('em_method').value = data.method;
        document.getElementById('em_time').value   = fmt(data.recorded_at);
        document.getElementById('em_note').value   = data.note || '';
        document.getElementById('em_override_pass').value = '';
        openModal('edit_medication');
    } else if (type === 'sale') {
        document.getElementById('es_id').value     = data.id;
        document.getElementById('es_weight').value = data.weight_kg;
        document.getElementById('es_price').value  = data.price_per_kg;
        document.getElementById('es_time').value   = fmt(data.recorded_at);
        document.getElementById('es_note').value   = data.note || '';
        document.getElementById('es_override_pass').value = '';
        calcEditSaleTotal();
        openModal('edit_sale');
    }
}

function calcEditSaleTotal() {
    const w = parseFloat(document.getElementById('es_weight').value || 0);
    const p = parseFloat(document.getElementById('es_price').value  || 0);
    document.getElementById('es_total_preview').textContent =
        w * p > 0 ? new Intl.NumberFormat('vi-VN').format(w * p) + ' đ' : '—';
}

async function submitEditFeed() {
    const ok = await postCareInline(`/care/feed/${document.getElementById('ef_id').value}/update`, {
        bags:          document.getElementById('ef_bags').value,
        kg_actual:     document.getElementById('ef_kg').value,
        recorded_at:   document.getElementById('ef_time').value.replace('T',' ') + ':00',
        note:          document.getElementById('ef_note').value,
        override_pass: document.getElementById('ef_override_pass').value,
    }, 'edit_feed_error');
    if (ok) { closeModal(); location.reload(); }
}
async function submitEditDeath() {
    const ok = await postCareInline(`/care/death/${document.getElementById('ed_id').value}/update`, {
        quantity:      document.getElementById('ed_quantity').value,
        reason:        document.getElementById('ed_reason').value,
        symptoms:      document.getElementById('ed_symptoms').value,
        recorded_at:   document.getElementById('ed_time').value.replace('T',' ') + ':00',
        override_pass: document.getElementById('ed_override_pass').value,
    }, 'edit_death_error');
    if (ok) { closeModal(); location.reload(); }
}
async function submitEditMedication() {
    const ok = await postCareInline(`/care/medication/${document.getElementById('em_id').value}/update`, {
        medication_name: document.getElementById('em_name').value,
        dosage:          document.getElementById('em_dosage').value,
        unit:            document.getElementById('em_unit').value,
        method:          document.getElementById('em_method').value,
        recorded_at:     document.getElementById('em_time').value.replace('T',' ') + ':00',
        note:            document.getElementById('em_note').value,
        override_pass:   document.getElementById('em_override_pass').value,
    }, 'edit_med_error');
    if (ok) { closeModal(); location.reload(); }
}
async function submitEditSale() {
    const ok = await postCareInline(`/care/sale/${document.getElementById('es_id').value}/update`, {
        weight_kg:     document.getElementById('es_weight').value,
        price_per_kg:  document.getElementById('es_price').value,
        recorded_at:   document.getElementById('es_time').value.replace('T',' ') + ':00',
        note:          document.getElementById('es_note').value,
        override_pass: document.getElementById('es_override_pass').value,
    }, 'edit_sale_error');
    if (ok) { closeModal(); location.reload(); }
}

async function deleteRecord(type, id, btn_el) {
    if (!confirm('Xóa bản ghi này?')) return;
    const res  = await fetch(`/care/${type}/${id}/delete`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({})
    });
    const json = await res.json();
    if (json.ok) { btn_el.closest('.border-t, .pt-2')?.remove(); return; }
    if (json.need_pass) { askPassword({ type, id, action: 'delete' }, 'Bản ghi quá hạn xóa (2 ngày). Nhập mật khẩu:'); return; }
    alert(json.message);
}

// ── Password override ─────────────────────────────────────────────
function askPassword(pending, reason) {
    _pending_action = pending;
    document.getElementById('pass_modal_reason').textContent = reason;
    document.getElementById('override_pass_input').value = '';
    document.getElementById('pass_error').classList.add('hidden');
    document.getElementById('modal_backdrop').classList.remove('hidden');
    document.getElementById('modal_pass').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closePassModal() {
    document.getElementById('modal_pass').classList.add('hidden');
    document.getElementById('modal_backdrop').classList.add('hidden');
    document.body.style.overflow = '';
    _pending_action = null;
}
async function confirmPass() {
    const pass = document.getElementById('override_pass_input').value;
    const err  = document.getElementById('pass_error');
    if (!pass) { err.textContent = 'Vui lòng nhập mật khẩu'; err.classList.remove('hidden'); return; }
    const { type, id, action } = _pending_action;
    if (action === 'delete') {
        const res  = await fetch(`/care/${type}/${id}/delete`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ override_pass: pass })
        });
        const json = await res.json();
        if (json.ok) { closePassModal(); location.reload(); return; }
        err.textContent = json.message || 'Mật khẩu không đúng'; err.classList.remove('hidden');
    } else if (action === 'edit') {
        const res  = await fetch(`/care/${type}/${id}?override_pass=${encodeURIComponent(pass)}`);
        const json = await res.json();
        if (json.ok) {
            closePassModal();
            prefillEdit(type, json.data);
            const field_map = { feed:'ef', death:'ed', medication:'em', sale:'es' };
            const prefix = field_map[type];
            const pf = document.getElementById(prefix + '_override_pass');
            if (pf) pf.value = pass;
            return;
        }
        err.textContent = json.message || 'Mật khẩu không đúng'; err.classList.remove('hidden');
    }
}

// ================================================================
// HEALTH
// ================================================================
async function submitHealth(e, form) {
    e.preventDefault();
    const fd = new FormData(form);
    try {
        const res  = await fetch('/health/store', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.ok) { location.reload(); return false; }
        alert(json.message || 'Lỗi lưu');
    } catch(err) { alert('Lỗi kết nối'); }
    return false;
}
async function resolveHealth(id) {
    const res  = await fetch('/health/' + id + '/resolve', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({}) });
    const json = await res.json();
    if (json.ok) location.reload(); else alert(json.message || 'Lỗi');
}
async function editHealth(id) {
    const res  = await fetch('/health/' + id);
    const json = await res.json();
    if (!json.ok) { alert(json.message || 'Không tìm thấy'); return; }
    const d = json.data;
    document.getElementById('eh_id').value      = d.id;
    document.getElementById('eh_symptoms').value = d.symptoms || '';
    document.getElementById('eh_severity').value = d.severity || 'mild';
    openModal('edit_health');
}
async function submitEditHealth() {
    const id = document.getElementById('eh_id').value;
    const ok = await postCareInline('/health/' + id + '/update', {
        symptoms: document.getElementById('eh_symptoms').value,
        severity: document.getElementById('eh_severity').value,
    }, 'edit_health_error');
    if (ok) { closeModal(); location.reload(); }
}
async function deleteHealth(id, btn) {
    if (!confirm('Xóa ghi chú sức khỏe này?')) return;
    const res  = await fetch('/health/' + id + '/delete', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({}) });
    const json = await res.json();
    if (json.ok) { const row = document.getElementById('health_row_' + id); if (row) row.remove(); }
    else { alert(json.message || 'Lỗi xóa'); }
}

// ================================================================
// VACCINE
// ================================================================
function fillVaccineFromProgram(sel, session) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('vac_name_'   + session).value = opt.dataset.name   || '';
    document.getElementById('vac_method_' + session).value = opt.dataset.method || 'drink';
}
async function submitVaccine(e, form) {
    e.preventDefault();
    const fd = new URLSearchParams(new FormData(form));
    try {
        const res  = await fetch('/vaccine/store', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd });
        const json = await res.json();
        if (json.ok) { location.reload(); return false; }
        alert(json.message || 'Lỗi lưu');
    } catch(err) { alert('Lỗi kết nối'); }
    return false;
}
async function doneVaccine(id) {
    const res  = await fetch('/vaccine/' + id + '/done', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({}) });
    const json = await res.json();
    if (json.ok) location.reload(); else alert(json.message || 'Lỗi');
}
async function editVaccine(id) {
    const res  = await fetch('/vaccine/' + id);
    const json = await res.json();
    if (!json.ok) { alert(json.message || 'Không tìm thấy'); return; }
    const d = json.data;
    document.getElementById('ev_id').value     = d.id;
    document.getElementById('ev_name').value   = d.vaccine_name || '';
    document.getElementById('ev_date').value   = d.scheduled_date || '';
    document.getElementById('ev_method').value = d.method || 'drink';
    document.getElementById('ev_notes').value  = d.notes || '';
    openModal('edit_vaccine');
}
async function submitEditVaccine() {
    const id = document.getElementById('ev_id').value;
    const ok = await postCareInline('/vaccine/' + id + '/update', {
        vaccine_name:   document.getElementById('ev_name').value,
        scheduled_date: document.getElementById('ev_date').value,
        method:         document.getElementById('ev_method').value,
        notes:          document.getElementById('ev_notes').value,
    }, 'edit_vaccine_error');
    if (ok) { closeModal(); location.reload(); }
}
async function deleteVaccine(id, btn) {
    if (!confirm('Xóa lịch vaccine này?')) return;
    const res  = await fetch('/vaccine/' + id + '/delete', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({}) });
    const json = await res.json();
    if (json.ok) { const row = document.getElementById('vac_row_' + id); if (row) row.remove(); }
    else { alert(json.message || 'Lỗi xóa'); }
}
</script>