<?php
$title = 'Vật Tư Sản Xuất';
ob_start();
$group_labels = [
    'feed'     => ['🌾','Cám'],
    'medicine' => ['💊','Thuốc'],
    'litter'   => ['🪨','Trấu / Chất độn'],
    'vitamin'  => ['🧪','Vitamin & Khoáng'],
    'bio'      => ['🦠','Chế phẩm sinh học'],
    'other'    => ['📦','Khác'],
];
$grouped = [];
foreach ($items as $item) $grouped[$item['sub_category']][] = $item;
?>
<div class="max-w-lg mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <a href="/inventory" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">←</a>
            <h1 class="text-xl font-bold">🌾 Vật Tư Sản Xuất</h1>
        </div>
        <button onclick="toggleAddItem()" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-xl font-medium">+ Thêm</button>
    </div>
    <div id="form_add_item" class="hidden bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-bold">Thêm vật tư sản xuất</div>
            <button onclick="toggleAddItem()" class="text-gray-400 text-xl leading-none">×</button>
        </div>
        <div id="add_item_error" class="hidden mb-2 p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
        <div class="space-y-2">
            <input type="text" id="new_item_name" placeholder="Tên vật tư *" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <div class="grid grid-cols-2 gap-2">
                <select id="new_item_sub" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($group_labels as $sv => [$ic,$lb]): ?><option value="<?= $sv ?>"><?= $ic ?> <?= $lb ?></option><?php endforeach; ?>
                </select>
                <input type="text" id="new_item_unit" placeholder="Đơn vị *" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <input type="number" id="new_item_min" placeholder="Ngưỡng cảnh báo (0 = tắt)" step="0.1" min="0" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button onclick="submitAddItem()" class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-semibold text-sm">+ Thêm vật tư</button>
        </div>
    </div>
    <?php if (!empty($expiring_meds)): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-2xl p-4 mb-4">
        <div class="font-semibold text-red-700 dark:text-red-400 text-sm mb-2">⚠️ Thuốc sắp hết hạn</div>
        <?php foreach ($expiring_meds as $em): ?>
        <div class="flex justify-between text-sm mt-1">
            <span class="text-gray-700 dark:text-gray-300"><?= e($em['item_name']) ?> (lô <?= e($em['batch_no']??'N/A') ?>)</span>
            <span class="text-red-600 font-medium"><?= date('d/m/Y',strtotime($em['expiry_date'])) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php foreach ($group_labels as $sub => [$icon,$label]): ?>
    <?php if (empty($grouped[$sub])) continue; ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden mb-3">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
            <span class="font-semibold text-sm"><?= $icon ?> <?= $label ?></span>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-gray-700">
        <?php foreach ($grouped[$sub] as $item):
            $central = (float)($item['central_stock'] ?? 0);
            $is_low  = $item['min_stock_alert'] > 0 && $central <= (float)$item['min_stock_alert'];
            $iid     = (int)$item['id'];
        ?>
        <div class="p-4" id="item_block_<?= $iid ?>">
            <div id="form_edit_item_<?= $iid ?>" class="hidden mb-3 pb-3 border-b border-blue-100 dark:border-gray-600">
                <div class="text-xs font-bold text-blue-600 mb-2">✏️ Sửa thông tin vật tư</div>
                <div id="edit_item_error_<?= $iid ?>" class="hidden mb-2 p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
                <div class="space-y-2">
                    <input type="text" id="ei_name_<?= $iid ?>" value="<?= e($item['name']) ?>" placeholder="Tên vật tư *" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div class="grid grid-cols-2 gap-2">
                        <select id="ei_sub_<?= $iid ?>" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($group_labels as $sv => [$ic,$lb]): ?><option value="<?= $sv ?>" <?= $item['sub_category']===$sv?'selected':'' ?>><?= $ic ?> <?= $lb ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" id="ei_unit_<?= $iid ?>" value="<?= e($item['unit']) ?>" placeholder="Đơn vị *" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <input type="number" id="ei_min_<?= $iid ?>" value="<?= e($item['min_stock_alert']) ?>" step="0.1" min="0" placeholder="Ngưỡng cảnh báo" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <select id="ei_supplier_<?= $iid ?>" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Không có NCC --</option>
                        <?php foreach ($suppliers as $sv): ?><option value="<?= $sv['id'] ?>" <?= ($item['supplier_id']==$sv['id'])?'selected':'' ?>><?= e($sv['name']) ?></option><?php endforeach; ?>
                    </select>
                    <textarea id="ei_note_<?= $iid ?>" rows="2" placeholder="Ghi chú..." class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e($item['note']??'') ?></textarea>
                    <div class="flex gap-2">
                        <button onclick="submitEditItem(<?= $iid ?>)" class="flex-1 bg-blue-600 text-white py-2 rounded-xl text-sm font-semibold">💾 Lưu</button>
                        <button onclick="closeEditItem(<?= $iid ?>)" class="px-4 bg-gray-100 dark:bg-gray-700 text-gray-500 py-2 rounded-xl text-sm">Hủy</button>
                    </div>
                </div>
            </div>
            <div class="flex items-start justify-between gap-2 mb-3">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm"><?= e($item['name']) ?><?php if ($is_low): ?> <span class="text-orange-500 text-xs ml-1">⚠️ Thấp</span><?php endif; ?></div>
                    <?php if ($item['supplier_name']): ?><div class="text-xs text-gray-400 mt-0.5">🏪 <?= e($item['supplier_name']) ?></div><?php endif; ?>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="text-right">
                        <div class="font-bold text-base <?= $is_low?'text-orange-500':'text-gray-800 dark:text-gray-200' ?>"><?= number_format($central,1) ?> <span class="text-xs font-normal text-gray-400"><?= e($item['unit']) ?></span></div>
                        <div class="text-xs text-gray-400">Kho trung tâm</div>
                    </div>
                    <button onclick="openEditItem(<?= $iid ?>)" class="w-7 h-7 flex items-center justify-center rounded-lg text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-sm">✏️</button>
                    <button onclick="deleteItem(<?= $iid ?>)" class="w-7 h-7 flex items-center justify-center rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 text-sm">🗑️</button>
                </div>
            </div>
            <?php if (!empty($item['barn_stocks'])): ?>
            <div class="flex flex-wrap gap-1.5 mb-3">
                <?php foreach ($item['barn_stocks'] as $bs): if (!$bs['barn_id']) continue; ?>
                <span class="text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-2 py-0.5 rounded-full"><?= e($bs['barn_name']??'Barn #'.$bs['barn_id']) ?>: <?= number_format((float)$bs['quantity'],1) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="flex gap-2">
                <button onclick="toggleForm('purchase',<?= $iid ?>)" class="flex-1 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 py-2 rounded-xl font-medium">+ Nhập kho</button>
                <?php if ($item['sub_category']==='medicine'): ?>
                <button onclick="toggleForm('transfer',<?= $iid ?>)" class="flex-1 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 py-2 rounded-xl font-medium">↓ Xuất barn</button>
                <button onclick="toggleForm('sell',<?= $iid ?>)" class="flex-1 text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 py-2 rounded-xl font-medium">💰 Bán lẻ</button>
                <?php endif; ?>
                <button onclick="toggleForm('history',<?= $iid ?>)" class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 py-2 px-3 rounded-xl">📋</button>
            </div>
            <div id="form_purchase_<?= $iid ?>" class="hidden mt-3 border-t border-gray-100 dark:border-gray-700 pt-3 space-y-2">
                <div id="purchase_error_<?= $iid ?>" class="hidden p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
                <?php if ($item['sub_category']==='feed'||$item['sub_category']==='litter'): ?>
                <select id="purchase_barn_<?= $iid ?>" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">-- Kho trung tâm --</option>
                    <?php foreach ($barns as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" id="purchase_qty_<?= $iid ?>" step="0.1" min="0.1" placeholder="Số lượng *" oninput="calcTotal(<?= $iid ?>)" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <input type="number" id="purchase_price_<?= $iid ?>" min="0" placeholder="Đơn giá (VNĐ)" oninput="calcTotal(<?= $iid ?>)" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div id="purchase_total_<?= $iid ?>" class="hidden text-right text-xs font-semibold text-blue-600">Thành tiền: <span id="purchase_total_val_<?= $iid ?>"></span> đ</div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" id="purchase_date_<?= $iid ?>" value="<?= date('Y-m-d') ?>" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <input type="date" id="purchase_expiry_<?= $iid ?>" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <select id="purchase_supplier_<?= $iid ?>" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">-- Nhà cung cấp --</option>
                    <?php foreach ($suppliers as $sv): ?><option value="<?= $sv['id'] ?>"><?= e($sv['name']) ?><?= $sv['phone']?' · '.$sv['phone']:'' ?></option><?php endforeach; ?>
                </select>
                <input type="text" id="purchase_batch_<?= $iid ?>" placeholder="Số lô (tùy chọn)" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                <input type="text" id="purchase_storage_<?= $iid ?>" placeholder="Vị trí kho (tùy chọn)" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                <button onclick="submitPurchase(<?= $iid ?>)" class="w-full bg-green-600 text-white py-2.5 rounded-xl font-semibold text-sm">✅ Xác nhận nhập kho</button>
            </div>
            <?php if ($item['sub_category']==='medicine'): ?>
            <div id="form_transfer_<?= $iid ?>" class="hidden mt-3 border-t border-gray-100 dark:border-gray-700 pt-3 space-y-2">
                <div id="transfer_error_<?= $iid ?>" class="hidden p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl px-3 py-2 text-xs text-blue-700 dark:text-blue-300">Kho trung tâm: <span class="font-bold"><?= number_format($central,1) ?> <?= e($item['unit']) ?></span></div>
                <select id="transfer_barn_<?= $iid ?>" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Chọn chuồng --</option>
                    <?php foreach ($barns as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
                <input type="number" id="transfer_qty_<?= $iid ?>" step="0.1" min="0.1" placeholder="Số lượng *" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <input type="text" id="transfer_note_<?= $iid ?>" placeholder="Ghi chú..." class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button onclick="submitTransfer(<?= $iid ?>)" class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-semibold text-sm">↓ Xác nhận xuất barn</button>
            </div>
            <div id="form_sell_<?= $iid ?>" class="hidden mt-3 border-t border-gray-100 dark:border-gray-700 pt-3 space-y-2">
                <div id="sell_error_<?= $iid ?>" class="hidden p-2 bg-red-50 text-red-600 rounded-xl text-xs"></div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl px-3 py-2 text-xs text-purple-700 dark:text-purple-300">Tồn kho: <span class="font-bold"><?= number_format($central,1) ?> <?= e($item['unit']) ?></span></div>
                <input type="text" id="sell_buyer_<?= $iid ?>" placeholder="Tên người mua *" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                <div class="grid grid-cols-2 gap-2">
                    <input type="tel" id="sell_phone_<?= $iid ?>" placeholder="SĐT" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <input type="date" id="sell_date_<?= $iid ?>" value="<?= date('Y-m-d') ?>" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" id="sell_qty_<?= $iid ?>" step="0.1" min="0.1" placeholder="Số lượng *" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <input type="number" id="sell_price_<?= $iid ?>" min="0" placeholder="Đơn giá (VNĐ)" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <input type="text" id="sell_note_<?= $iid ?>" placeholder="Ghi chú..." class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                <button onclick="submitSell(<?= $iid ?>)" class="w-full bg-purple-600 text-white py-2.5 rounded-xl font-semibold text-sm">💰 Xác nhận bán</button>
            </div>
            <?php endif; ?>
            <div id="form_history_<?= $iid ?>" class="hidden mt-3 border-t border-gray-100 dark:border-gray-700 pt-3">
                <div id="history_content_<?= $iid ?>"><div class="text-center py-4 text-gray-400 text-sm">⏳ Đang tải...</div></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
    <div class="text-center py-16 text-gray-400">
        <div class="text-4xl mb-3">📦</div>
        <div class="text-sm">Chưa có vật tư nào</div>
        <button onclick="toggleAddItem()" class="mt-3 text-blue-600 text-sm">+ Thêm vật tư đầu tiên</button>
    </div>
    <?php endif; ?>
</div>

<div id="modal_backdrop" class="hidden fixed inset-0 bg-black/50 z-30" onclick="closeModal()"></div>
<div id="modal_edit_purchase" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4 pointer-events-none">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-5 pointer-events-auto max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4"><div class="font-bold text-sm">✏️ Sửa lần nhập kho</div><button onclick="closeModal()" class="text-gray-400 text-2xl leading-none w-8 h-8 flex items-center justify-center">×</button></div>
        <div id="ep_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs text-gray-500 font-medium block mb-1">Số lượng *</label><input type="number" id="ep_qty" step="0.1" min="0.1" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500"></div>
                <div><label class="text-xs text-gray-500 font-medium block mb-1">Đơn giá (VNĐ)</label><input type="number" id="ep_price" min="0" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs text-gray-500 font-medium block mb-1">Ngày nhập</label><input type="date" id="ep_date" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500"></div>
                <div><label class="text-xs text-gray-500 font-medium block mb-1">Hạn sử dụng</label><input type="date" id="ep_expiry" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500"></div>
            </div>
            <input type="text" id="ep_batch" placeholder="Số lô" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
            <input type="text" id="ep_storage" placeholder="Vị trí kho" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
            <input type="text" id="ep_note" placeholder="Ghi chú" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
            <input type="hidden" id="ep_id">
            <button onclick="submitEditPurchase()" class="w-full bg-green-600 text-white py-3 rounded-xl font-semibold text-sm">💾 Lưu thay đổi</button>
        </div>
    </div>
</div>
<div id="modal_edit_transfer" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4 pointer-events-none">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-5 pointer-events-auto">
        <div class="flex justify-between items-center mb-4"><div class="font-bold text-sm">✏️ Sửa lần xuất barn</div><button onclick="closeModal()" class="text-gray-400 text-2xl leading-none w-8 h-8 flex items-center justify-center">×</button></div>
        <div id="et_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <div><label class="text-xs text-gray-500 font-medium block mb-1">Chuồng nhận</label>
            <select id="et_barn" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">-- Chọn chuồng --</option>
                <?php foreach ($barns as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div><label class="text-xs text-gray-500 font-medium block mb-1">Số lượng *</label><input type="number" id="et_qty" step="0.1" min="0.1" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
            <input type="text" id="et_note" placeholder="Ghi chú" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="hidden" id="et_id">
            <button onclick="submitEditTransfer()" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold text-sm">💾 Lưu thay đổi</button>
        </div>
    </div>
</div>
<div id="modal_edit_sale" class="hidden fixed inset-0 z-40 flex items-center justify-center p-4 pointer-events-none">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-5 pointer-events-auto max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4"><div class="font-bold text-sm">✏️ Sửa lần bán lẻ</div><button onclick="closeModal()" class="text-gray-400 text-2xl leading-none w-8 h-8 flex items-center justify-center">×</button></div>
        <div id="es_error" class="hidden mb-3 p-3 bg-red-50 text-red-600 rounded-xl text-sm"></div>
        <div class="space-y-3">
            <input type="text" id="es_buyer" placeholder="Tên người mua *" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
            <div class="grid grid-cols-2 gap-3">
                <input type="tel" id="es_phone" placeholder="SĐT" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                <input type="date" id="es_date" class="border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs text-gray-500 font-medium block mb-1">Số lượng *</label><input type="number" id="es_qty" step="0.1" min="0.1" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500"></div>
                <div><label class="text-xs text-gray-500 font-medium block mb-1">Đơn giá (VNĐ)</label><input type="number" id="es_price" min="0" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500"></div>
            </div>
            <input type="text" id="es_note" placeholder="Ghi chú" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
            <input type="hidden" id="es_id">
            <button onclick="submitEditSale()" class="w-full bg-purple-600 text-white py-3 rounded-xl font-semibold text-sm">💾 Lưu thay đổi</button>
        </div>
    </div>
</div>

<script>
async function api(url,data,errId){const el=errId?document.getElementById(errId):null;if(el)el.classList.add('hidden');try{const r=await fetch(url,{method:'POST',body:data});if(r.redirected||r.status===302||r.status===401){window.location.href=r.url||'/login';return null;}const d=await r.json();if(!d.ok){const m=d.message||'Có lỗi xảy ra';if(el){el.textContent=m;el.classList.remove('hidden');}else alert('❌ '+m);return null;}return d;}catch{const m='Lỗi kết nối';if(el){el.textContent=m;el.classList.remove('hidden');}else alert('❌ '+m);return null;}}
function toggleAddItem(){document.getElementById('form_add_item').classList.toggle('hidden');}
function toggleForm(type,id){const t=document.getElementById('form_'+type+'_'+id);if(!t)return;const op=t.classList.contains('hidden');const b=document.getElementById('item_block_'+id);['purchase','transfer','sell','history'].forEach(x=>{b.querySelector('#form_'+x+'_'+id)?.classList.add('hidden');});if(op){t.classList.remove('hidden');if(type==='history')loadHistory(id);}}
function openEditItem(id){const b=document.getElementById('item_block_'+id);['purchase','transfer','sell','history'].forEach(x=>{b.querySelector('#form_'+x+'_'+id)?.classList.add('hidden');});document.getElementById('form_edit_item_'+id).classList.remove('hidden');}
function closeEditItem(id){document.getElementById('form_edit_item_'+id).classList.add('hidden');}
function calcTotal(id){const q=parseFloat(document.getElementById('purchase_qty_'+id)?.value)||0;const p=parseFloat(document.getElementById('purchase_price_'+id)?.value)||0;const t=q*p;const el=document.getElementById('purchase_total_'+id);if(!el)return;if(t>0){el.classList.remove('hidden');document.getElementById('purchase_total_val_'+id).textContent=t.toLocaleString('vi-VN');}else el.classList.add('hidden');}
let _om=null;
function openModal(type){_om=type;document.getElementById('modal_backdrop').classList.remove('hidden');document.getElementById('modal_edit_'+type).classList.remove('hidden');document.body.style.overflow='hidden';}
function closeModal(){if(_om){document.getElementById('modal_edit_'+_om).classList.add('hidden');_om=null;}document.getElementById('modal_backdrop').classList.add('hidden');document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
async function submitAddItem(){const f=new FormData();f.append('name',document.getElementById('new_item_name').value);f.append('category','production');f.append('sub_category',document.getElementById('new_item_sub').value);f.append('unit',document.getElementById('new_item_unit').value);f.append('min_stock_alert',document.getElementById('new_item_min').value||0);const ok=await api('/inventory/items',f,'add_item_error');if(ok)location.reload();}
async function submitEditItem(id){const f=new FormData();f.append('name',document.getElementById('ei_name_'+id)?.value||'');f.append('sub_category',document.getElementById('ei_sub_'+id)?.value||'');f.append('unit',document.getElementById('ei_unit_'+id)?.value||'');f.append('min_stock_alert',document.getElementById('ei_min_'+id)?.value||0);f.append('supplier_id',document.getElementById('ei_supplier_'+id)?.value||'');f.append('note',document.getElementById('ei_note_'+id)?.value||'');const ok=await api('/inventory/items/'+id+'/update',f,'edit_item_error_'+id);if(ok)location.reload();}
async function deleteItem(id){if(!confirm('Xóa vật tư này?\n(Chỉ xóa được khi tồn kho = 0)'))return;const ok=await api('/inventory/items/'+id+'/delete',new FormData(),null);if(ok)location.reload();}
async function submitPurchase(id){const f=new FormData();f.append('item_id',id);f.append('quantity',document.getElementById('purchase_qty_'+id)?.value||'');f.append('unit_price',document.getElementById('purchase_price_'+id)?.value||'');f.append('purchased_at',document.getElementById('purchase_date_'+id)?.value||'');f.append('expiry_date',document.getElementById('purchase_expiry_'+id)?.value||'');f.append('supplier_id',document.getElementById('purchase_supplier_'+id)?.value||'');f.append('batch_no',document.getElementById('purchase_batch_'+id)?.value||'');f.append('storage_location',document.getElementById('purchase_storage_'+id)?.value||'');const b=document.getElementById('purchase_barn_'+id);if(b?.value)f.append('barn_id',b.value);const ok=await api('/inventory/purchase',f,'purchase_error_'+id);if(ok)location.reload();}
async function submitTransfer(id){const f=new FormData();f.append('item_id',id);f.append('barn_id',document.getElementById('transfer_barn_'+id)?.value||'');f.append('quantity',document.getElementById('transfer_qty_'+id)?.value||'');f.append('note',document.getElementById('transfer_note_'+id)?.value||'');const ok=await api('/inventory/transfer',f,'transfer_error_'+id);if(ok)location.reload();}
async function submitSell(id){const f=new FormData();f.append('item_id',id);f.append('buyer_name',document.getElementById('sell_buyer_'+id)?.value||'');f.append('buyer_phone',document.getElementById('sell_phone_'+id)?.value||'');f.append('quantity',document.getElementById('sell_qty_'+id)?.value||'');f.append('unit_price',document.getElementById('sell_price_'+id)?.value||'');f.append('sold_at',document.getElementById('sell_date_'+id)?.value||'');f.append('note',document.getElementById('sell_note_'+id)?.value||'');const ok=await api('/inventory/sell',f,'sell_error_'+id);if(ok)location.reload();}
async function openEditTxn(txnType,refId,txnId){const urls={purchase:'/inventory/purchases/'+refId,transfer_out:'/inventory/transactions/'+txnId,sell:'/inventory/sales/'+refId};const r=await fetch(urls[txnType]);const d=await r.json();if(!d.ok){alert('❌ '+d.message);return;}const x=d.data;if(txnType==='purchase'){document.getElementById('ep_id').value=refId;document.getElementById('ep_qty').value=x.quantity;document.getElementById('ep_price').value=x.unit_price;document.getElementById('ep_date').value=x.purchased_at;document.getElementById('ep_expiry').value=x.expiry_date||'';document.getElementById('ep_batch').value=x.batch_no||'';document.getElementById('ep_storage').value=x.storage_location||'';document.getElementById('ep_note').value=x.note||'';document.getElementById('ep_error').classList.add('hidden');openModal('purchase');}else if(txnType==='transfer_out'){document.getElementById('et_id').value=txnId;document.getElementById('et_qty').value=x.quantity;document.getElementById('et_barn').value=x.to_barn_id||'';document.getElementById('et_note').value=x.note||'';document.getElementById('et_error').classList.add('hidden');openModal('transfer');}else if(txnType==='sell'){document.getElementById('es_id').value=refId;document.getElementById('es_qty').value=x.quantity;document.getElementById('es_price').value=x.unit_price;document.getElementById('es_buyer').value=x.buyer_name||'';document.getElementById('es_phone').value=x.buyer_phone||'';document.getElementById('es_date').value=x.sold_at||'';document.getElementById('es_note').value=x.note||'';document.getElementById('es_error').classList.add('hidden');openModal('sale');}}
async function submitEditPurchase(){const id=document.getElementById('ep_id').value;const f=new FormData();f.append('quantity',document.getElementById('ep_qty').value);f.append('unit_price',document.getElementById('ep_price').value);f.append('purchased_at',document.getElementById('ep_date').value);f.append('expiry_date',document.getElementById('ep_expiry').value);f.append('batch_no',document.getElementById('ep_batch').value);f.append('storage_location',document.getElementById('ep_storage').value);f.append('note',document.getElementById('ep_note').value);const ok=await api('/inventory/purchases/'+id+'/update',f,'ep_error');if(ok){closeModal();location.reload();}}
async function submitEditTransfer(){const id=document.getElementById('et_id').value;const f=new FormData();f.append('quantity',document.getElementById('et_qty').value);f.append('barn_id',document.getElementById('et_barn').value);f.append('note',document.getElementById('et_note').value);const ok=await api('/inventory/transactions/'+id+'/update',f,'et_error');if(ok){closeModal();location.reload();}}
async function submitEditSale(){const id=document.getElementById('es_id').value;const f=new FormData();f.append('quantity',document.getElementById('es_qty').value);f.append('unit_price',document.getElementById('es_price').value);f.append('buyer_name',document.getElementById('es_buyer').value);f.append('buyer_phone',document.getElementById('es_phone').value);f.append('sold_at',document.getElementById('es_date').value);f.append('note',document.getElementById('es_note').value);const ok=await api('/inventory/sales/'+id+'/update',f,'es_error');if(ok){closeModal();location.reload();}}
async function deleteTxn(txnType,refId,txnId){const lbl={purchase:'lần nhập kho',transfer_out:'lần xuất barn',sell:'lần bán lẻ'};if(!confirm('Xóa '+(lbl[txnType]||'giao dịch')+'?\nKho sẽ được hoàn lại tự động.'))return;const urls={purchase:'/inventory/purchases/'+refId+'/delete',transfer_out:'/inventory/transactions/'+txnId+'/delete',sell:'/inventory/sales/'+refId+'/delete'};const ok=await api(urls[txnType],new FormData(),null);if(ok)location.reload();}
const _hl={};
async function loadHistory(id){if(_hl[id])return;const c=document.getElementById('history_content_'+id);if(!c)return;try{const r=await fetch('/inventory/items/'+id+'/stock');const d=await r.json();if(!d.ok){c.innerHTML='<div class="text-center py-4 text-red-400 text-sm">Lỗi tải dữ liệu</div>';return;}if(!d.transactions?.length){c.innerHTML='<div class="text-center py-4 text-gray-400 text-sm">Chưa có giao dịch nào</div>';return;}const lbl={purchase:'🟢 Nhập kho',transfer_out:'🔵 Xuất barn',transfer_in:'🔵 Nhận barn',use_feed:'🟡 Dùng cám',use_medicine:'🟠 Dùng thuốc',use_litter:'⚪ Dùng trấu',sell:'🔴 Bán',adjust:'⚫ Điều chỉnh',dispose:'🗑️ Thanh lý'};let h='<div class="divide-y divide-gray-100 dark:divide-gray-700">';for(const t of d.transactions){const lb=lbl[t.txn_type]||t.txn_type;const bn=t.from_barn_name||t.to_barn_name||'';const ce=['purchase','transfer_out','sell'].includes(t.txn_type);let ri=t.id;if(t.txn_type==='purchase'&&t.ref_purchase_id)ri=t.ref_purchase_id;if(t.txn_type==='sell'&&t.install_location?.startsWith('sale:'))ri=parseInt(t.install_location.replace('sale:',''));h+=`<div class="py-2.5 flex items-center justify-between gap-2"><div class="flex-1 min-w-0"><div class="font-medium text-gray-800 dark:text-gray-200 text-sm">${lb}${bn?' · '+bn:''}</div><div class="text-xs text-gray-400 mt-0.5">${t.recorded_at.substring(0,16)}</div>${t.note?`<div class="text-xs text-gray-400 truncate">${t.note}</div>`:''}</div><div class="flex items-center gap-1 flex-shrink-0"><span class="font-semibold tabular-nums text-sm">${parseFloat(t.quantity).toFixed(1)}</span>${ce?`<button onclick="openEditTxn('${t.txn_type}',${ri},${t.id})" class="w-6 h-6 flex items-center justify-center text-blue-400 hover:text-blue-600 text-xs">✏️</button><button onclick="deleteTxn('${t.txn_type}',${ri},${t.id})" class="w-6 h-6 flex items-center justify-center text-red-400 hover:text-red-600 text-xs">🗑️</button>`:''}</div></div>`;}h+='</div>';c.innerHTML=h;_hl[id]=true;}catch{c.innerHTML='<div class="text-center py-4 text-red-400 text-sm">Lỗi kết nối</div>';}}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
