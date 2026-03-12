<?php
/**
 * consumable.php
 */
$title = 'Vật Tư Tiêu Hao';
ob_start();
?>
<?php
$title = 'Vật Tư Tiêu Hao'; ob_start();
$status_colors = ['stock'=>'blue','installed'=>'green','broken'=>'red','disposed'=>'gray'];
$status_labels = ['stock'=>'Trong kho','installed'=>'Đang lắp','broken'=>'Hỏng','disposed'=>'Thanh lý'];
$asset_by_status = [];
foreach ($assets as $a) { $asset_by_status[$a['status']][] = $a; }
?>
<div class="max-w-lg mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">🔧 Vật Tư Tiêu Hao</h1>
        <button onclick="openAddItem()" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-xl font-medium">+ Thêm</button>
    </div>
    <div class="grid grid-cols-4 gap-2">
        <?php foreach (['stock','installed','broken','disposed'] as $st): ?>
        <?php $count = count($asset_by_status[$st] ?? []); ?>
        <button onclick="filterAssets('<?= $st ?>')"
                class="bg-white dark:bg-gray-800 rounded-xl p-2.5 border border-gray-100 dark:border-gray-700 text-center">
            <div class="text-lg font-bold text-<?= $status_colors[$st] ?>-600"><?= $count ?></div>
            <div class="text-xs text-gray-400 mt-0.5"><?= $status_labels[$st] ?></div>
        </button>
        <?php endforeach; ?>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
            <span class="font-semibold text-sm">📦 Tồn kho theo loại</span>
        </div>
        <?php if (empty($items)): ?>
        <div class="p-6 text-center text-gray-400 text-sm">Chưa có vật tư nào</div>
        <?php else: ?>
        <div class="divide-y divide-gray-50 dark:divide-gray-700">
        <?php foreach ($items as $item): ?>
        <?php $central = (float)($item['central_stock'] ?? 0); ?>
        <div class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg"><?= match($item['sub_category']){'iot_device'=>'📡','sensor'=>'🌡️','lighting'=>'💡','fan'=>'🌀','camera'=>'📷','feeder'=>'🍽️',default=>'📦'} ?></span>
                    <div>
                        <div class="text-sm font-semibold"><?= e($item['name']) ?></div>
                        <div class="text-xs text-gray-400"><?= e($item['sub_category']) ?></div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-gray-800 dark:text-gray-200"><?= number_format($central,0) ?> <span class="text-xs font-normal text-gray-400"><?= e($item['unit']) ?></span></div>
                </div>
            </div>
            <div class="mt-2 flex gap-2">
                <button onclick="openPurchase(<?= $item['id'] ?>,'<?= e($item['name']) ?>','<?= e($item['unit']) ?>')"
                        class="flex-1 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 py-1.5 rounded-xl font-medium">+ Nhập kho</button>
                <button onclick="openAddAsset(<?= $item['id'] ?>,'<?= e($item['name']) ?>')"
                        class="flex-1 text-xs bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 py-1.5 rounded-xl font-medium">🔧 Theo dõi từng cái</button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
            <span class="font-semibold text-sm">🔩 Thiết bị theo từng cái</span>
            <span class="text-xs text-gray-400"><?= count($assets) ?> thiết bị</span>
        </div>
        <div id="asset_list">
        <?php if (empty($assets)): ?>
        <div class="p-6 text-center text-gray-400 text-sm">Chưa có thiết bị nào được theo dõi</div>
        <?php else: ?>
        <?php foreach ($assets as $a):
            $color = $status_colors[$a['status']] ?? 'gray';
            $label = $status_labels[$a['status']] ?? '?';
            $warranty_days = $a['warranty_until'] ? (int)((strtotime($a['warranty_until'])-time())/86400) : null;
        ?>
        <div class="p-4 border-b border-gray-50 dark:border-gray-700 asset-row" data-status="<?= $a['status'] ?>">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold"><?= e($a['item_name']) ?></span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-<?= $color ?>-100 dark:bg-<?= $color ?>-900/30 text-<?= $color ?>-700 dark:text-<?= $color ?>-300"><?= $label ?></span>
                    </div>
                    <?php if ($a['serial_no']): ?><div class="text-xs text-gray-400 mt-0.5">S/N: <?= e($a['serial_no']) ?></div><?php endif; ?>
                    <?php if ($a['barn_name']): ?><div class="text-xs text-gray-500 mt-0.5">📍 <?= e($a['barn_name']) ?><?= $a['install_location'] ? ' · '.e($a['install_location']) : '' ?></div><?php endif; ?>
                    <?php if ($a['device_name']): ?><div class="text-xs text-indigo-500 mt-0.5">📡 <?= e($a['device_name']) ?></div><?php endif; ?>
                    <?php if ($warranty_days !== null): ?>
                    <div class="text-xs mt-0.5 <?= $warranty_days < 30 ? 'text-orange-500' : 'text-gray-400' ?>">
                        🛡️ <?= $warranty_days >= 0 ? "Bảo hành còn {$warranty_days} ngày" : "Hết hạn ".abs($warranty_days)." ngày trước" ?>
                    </div>
                    <?php endif; ?>
                </div>
                <button onclick="openUpdateAsset(<?= $a['id'] ?>,'<?= e(addslashes($a['item_name'])) ?>','<?= $a['status'] ?>',<?= $a['barn_id']??'null' ?>,'<?= e(addslashes($a['install_location']??'')) ?>',<?= $a['ref_device_id']??'null' ?>,'<?= $a['installed_at']??'' ?>','<?= $a['warranty_until']??'' ?>')"
                        class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 px-3 py-1.5 rounded-xl shrink-0">Sửa</button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>

<div id="modal_purchase" class="hidden fixed inset-0 z-50 flex items-end overflow-hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeModal('modal_purchase')"></div>
    <div class="relative w-full max-w-lg mx-auto bg-white dark:bg-gray-800 rounded-t-2xl p-5 space-y-3 animate-sheet overflow-y-auto" style="max-height:85vh">
        <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mb-2"></div>
        <h3 class="font-bold" id="purchase_title">Nhập kho</h3>
        <input type="hidden" id="purchase_item_id">
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs text-gray-500 font-medium">Số lượng *</label>
                <input type="number" id="purchase_qty" step="1" min="1" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
            <div><label class="text-xs text-gray-500 font-medium">Đơn giá (VNĐ)</label>
                <input type="number" id="purchase_price" min="0" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        </div>
        <div><label class="text-xs text-gray-500 font-medium">Ngày nhập</label>
            <input type="date" id="purchase_date" value="<?= date('Y-m-d') ?>" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <input type="checkbox" id="create_assets" checked class="rounded">
            <label for="create_assets" class="text-sm text-gray-700 dark:text-gray-300">Tạo hồ sơ theo dõi cho từng cái</label>
        </div>
        <button onclick="submitPurchase()" class="w-full bg-green-600 text-white py-3 rounded-xl font-semibold">✅ Nhập kho</button>
    </div>
</div>

<div id="modal_update_asset" class="hidden fixed inset-0 z-50 flex items-end overflow-hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeModal('modal_update_asset')"></div>
    <div class="relative w-full max-w-lg mx-auto bg-white dark:bg-gray-800 rounded-t-2xl p-5 space-y-3 animate-sheet overflow-y-auto" style="max-height:85vh">
        <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mb-2"></div>
        <h3 class="font-bold" id="update_asset_title">Cập nhật thiết bị</h3>
        <input type="hidden" id="update_asset_id">
        <div><label class="text-xs text-gray-500 font-medium">Trạng thái</label>
            <select id="update_asset_status" onchange="toggleInstallFields()" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700">
                <option value="stock">📦 Trong kho</option>
                <option value="installed">✅ Đang lắp đặt</option>
                <option value="broken">❌ Hỏng chờ xử lý</option>
                <option value="disposed">🗑️ Thanh lý</option>
            </select>
        </div>
        <div id="install_fields">
            <div><label class="text-xs text-gray-500 font-medium">Lắp tại chuồng</label>
                <select id="update_asset_barn" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700">
                    <option value="">-- Chọn chuồng --</option>
                    <?php foreach ($barns as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mt-2"><label class="text-xs text-gray-500 font-medium">Vị trí cụ thể</label>
                <input type="text" id="update_asset_location" placeholder="VD: Đầu chuồng, cách đất 1.5m" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
            <div class="mt-2"><label class="text-xs text-gray-500 font-medium">Liên kết thiết bị IoT</label>
                <select id="update_asset_device" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700">
                    <option value="">-- Không liên kết --</option>
                    <?php foreach ($devices as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['device_code']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-2">
                <div><label class="text-xs text-gray-500 font-medium">Ngày lắp</label>
                    <input type="date" id="update_asset_installed" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
                <div><label class="text-xs text-gray-500 font-medium">Bảo hành đến</label>
                    <input type="date" id="update_asset_warranty" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
            </div>
        </div>
        <div><label class="text-xs text-gray-500 font-medium">Ghi chú</label>
            <input type="text" id="update_asset_note" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <button onclick="submitUpdateAsset()" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">💾 Lưu</button>
    </div>
</div>

<div id="modal_add_item" class="hidden fixed inset-0 z-50 flex items-end overflow-hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeModal('modal_add_item')"></div>
    <div class="relative w-full max-w-lg mx-auto bg-white dark:bg-gray-800 rounded-t-2xl p-5 space-y-3 animate-sheet overflow-y-auto" style="max-height:85vh">
        <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mb-2"></div>
        <h3 class="font-bold">Thêm vật tư tiêu hao</h3>
        <div><label class="text-xs text-gray-500 font-medium">Tên *</label>
            <input type="text" id="new_item_name" placeholder="VD: ESP32 DevKit, Cảm biến SHT40..." class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs text-gray-500 font-medium">Nhóm</label>
                <select id="new_item_sub" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700">
                    <option value="iot_device">Thiết bị IoT</option>
                    <option value="sensor">Cảm biến</option>
                    <option value="camera">Camera</option>
                    <option value="lighting">Đèn chiếu sáng</option>
                    <option value="fan">Quạt/Thông gió</option>
                    <option value="feeder">Máng ăn/uống</option>
                    <option value="other">Khác</option>
                </select>
            </div>
            <div><label class="text-xs text-gray-500 font-medium">Đơn vị *</label>
                <input type="text" id="new_item_unit" placeholder="cái / bộ / m" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        </div>
        <div><label class="text-xs text-gray-500 font-medium">Cảnh báo tồn kho thấp hơn</label>
            <input type="number" id="new_item_min" placeholder="0 = không cảnh báo" step="1" min="0" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <button onclick="submitAddItem()" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">+ Thêm</button>
    </div>
</div>

<script>
function closeModal(id){document.getElementById(id).classList.add('hidden');document.body.style.overflow='';}
function openAddItem(){document.getElementById('modal_add_item').classList.remove('hidden'); document.body.style.overflow='hidden';}
function filterAssets(status){
    document.querySelectorAll('.asset-row').forEach(r=>{
        r.style.display=(status==='all'||r.dataset.status===status)?'':'none';
    });
}
function openPurchase(itemId,name,unit){
    document.getElementById('purchase_item_id').value=itemId;
    document.getElementById('purchase_title').textContent='Nhập kho: '+name;
    document.getElementById('purchase_qty').value='';
    document.getElementById('purchase_price').value='';
    document.getElementById('modal_purchase').classList.remove('hidden'); document.body.style.overflow='hidden';
}
function openAddAsset(itemId,name){openPurchase(itemId,name,'cái');document.getElementById('create_assets').checked=true;}
function openUpdateAsset(id,name,status,barnId,location,deviceId,installedAt,warrantyUntil){
    document.getElementById('update_asset_id').value=id;
    document.getElementById('update_asset_title').textContent=name+' #'+id;
    document.getElementById('update_asset_status').value=status;
    document.getElementById('update_asset_barn').value=barnId||'';
    document.getElementById('update_asset_location').value=location||'';
    document.getElementById('update_asset_device').value=deviceId||'';
    document.getElementById('update_asset_installed').value=installedAt||'';
    document.getElementById('update_asset_warranty').value=warrantyUntil||'';
    document.getElementById('update_asset_note').value='';
    toggleInstallFields();
    document.getElementById('modal_update_asset').classList.remove('hidden'); document.body.style.overflow='hidden';
}
function toggleInstallFields(){
    const s=document.getElementById('update_asset_status').value;
    document.getElementById('install_fields').style.display=(s==='installed')?'':'none';
}
async function submitPurchase(){
    const f=new FormData();
    f.append('item_id',document.getElementById('purchase_item_id').value);
    f.append('quantity',document.getElementById('purchase_qty').value);
    f.append('unit_price',document.getElementById('purchase_price').value||0);
    f.append('purchased_at',document.getElementById('purchase_date').value);
    if(document.getElementById('create_assets').checked)f.append('create_assets','1');
    const r=await fetch('/inventory/purchase',{method:'POST',body:f});
    const d=await r.json();
    if(d.ok){closeModal('modal_purchase');location.reload();}else alert('❌ '+d.message);
}
async function submitUpdateAsset(){
    const id=document.getElementById('update_asset_id').value;
    const f=new FormData();
    f.append('status',document.getElementById('update_asset_status').value);
    f.append('barn_id',document.getElementById('update_asset_barn').value);
    f.append('install_location',document.getElementById('update_asset_location').value);
    f.append('ref_device_id',document.getElementById('update_asset_device').value);
    f.append('installed_at',document.getElementById('update_asset_installed').value);
    f.append('warranty_until',document.getElementById('update_asset_warranty').value);
    f.append('note',document.getElementById('update_asset_note').value);
    const r=await fetch('/inventory/assets/'+id+'/status',{method:'POST',body:f});
    const d=await r.json();
    if(d.ok){closeModal('modal_update_asset');location.reload();}else alert('❌ '+d.message);
}
async function submitAddItem(){
    const f=new FormData();
    f.append('name',document.getElementById('new_item_name').value);
    f.append('category','consumable');
    f.append('sub_category',document.getElementById('new_item_sub').value);
    f.append('unit',document.getElementById('new_item_unit').value);
    f.append('min_stock_alert',document.getElementById('new_item_min').value||0);
    const r=await fetch('/inventory/items',{method:'POST',body:f});
    const d=await r.json();
    if(d.ok){closeModal('modal_add_item');location.reload();}else alert('❌ '+d.message);
}
</script>
<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
