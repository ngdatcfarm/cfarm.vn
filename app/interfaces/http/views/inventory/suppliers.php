<?php
/**
 * suppliers.php
 */
$title = 'Nhà Cung Cấp';
ob_start();
?>
<div class="max-w-lg mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">🏪 Nhà Cung Cấp</h1>
        <button onclick="document.getElementById('modal_add').classList.remove('hidden')"
                class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-xl font-medium">+ Thêm</button>
    </div>
    <?php if (empty($suppliers)): ?>
    <div class="text-center py-12 text-gray-400"><div class="text-4xl mb-3">🏪</div><div class="text-sm">Chưa có nhà cung cấp nào</div></div>
    <?php else: ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden divide-y divide-gray-50 dark:divide-gray-700">
        <?php foreach ($suppliers as $s): ?>
        <div class="p-4 flex items-start justify-between gap-3">
            <div class="flex-1">
                <div class="font-semibold text-sm"><?= e($s['name']) ?></div>
                <?php if ($s['phone']): ?><div class="text-xs text-gray-500 mt-0.5">📞 <?= e($s['phone']) ?></div><?php endif; ?>
                <?php if ($s['address']): ?><div class="text-xs text-gray-400 mt-0.5">📍 <?= e($s['address']) ?></div><?php endif; ?>
            </div>
            <button onclick="openEdit(<?= $s['id'] ?>,'<?= e(addslashes($s['name'])) ?>','<?= e($s['phone']??'') ?>','<?= e(addslashes($s['address']??'')) ?>','<?= e(addslashes($s['note']??'')) ?>')"
                    class="text-xs text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-xl">Sửa</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<div id="modal_add" class="hidden fixed inset-0 z-50 flex items-end overflow-hidden">
    <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('modal_add').classList.add('hidden')"></div>
    <div class="relative w-full max-w-lg mx-auto bg-white dark:bg-gray-800 rounded-t-2xl p-5 space-y-3 animate-sheet overflow-y-auto" style="max-height:85vh">
        <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mb-2"></div>
        <h3 class="font-bold">Thêm nhà cung cấp</h3>
        <div><label class="text-xs text-gray-500 font-medium">Tên *</label>
            <input type="text" id="add_name" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs text-gray-500 font-medium">SĐT</label>
                <input type="tel" id="add_phone" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
            <div><label class="text-xs text-gray-500 font-medium">Địa chỉ</label>
                <input type="text" id="add_address" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        </div>
        <div><label class="text-xs text-gray-500 font-medium">Ghi chú</label>
            <input type="text" id="add_note" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <button onclick="submitAdd()" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">+ Thêm</button>
    </div>
</div>
<div id="modal_edit" class="hidden fixed inset-0 z-50 flex items-end overflow-hidden">
    <div class="absolute inset-0 bg-black/40" onclick="document.getElementById('modal_edit').classList.add('hidden')"></div>
    <div class="relative w-full max-w-lg mx-auto bg-white dark:bg-gray-800 rounded-t-2xl p-5 space-y-3 animate-sheet overflow-y-auto" style="max-height:85vh">
        <div class="w-10 h-1 bg-gray-200 dark:bg-gray-600 rounded-full mx-auto mb-2"></div>
        <h3 class="font-bold">Sửa nhà cung cấp</h3>
        <input type="hidden" id="edit_id">
        <div><label class="text-xs text-gray-500 font-medium">Tên *</label>
            <input type="text" id="edit_name" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs text-gray-500 font-medium">SĐT</label>
                <input type="tel" id="edit_phone" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
            <div><label class="text-xs text-gray-500 font-medium">Địa chỉ</label>
                <input type="text" id="edit_address" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        </div>
        <div><label class="text-xs text-gray-500 font-medium">Ghi chú</label>
            <input type="text" id="edit_note" class="w-full mt-1 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 text-sm bg-white dark:bg-gray-700"></div>
        <button onclick="submitEdit()" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">💾 Lưu</button>
    </div>
</div>
<script>
function openEdit(id,name,phone,address,note){
    document.getElementById('edit_id').value=id;
    document.getElementById('edit_name').value=name;
    document.getElementById('edit_phone').value=phone;
    document.getElementById('edit_address').value=address;
    document.getElementById('edit_note').value=note;
    document.getElementById('modal_edit').classList.remove('hidden'); document.body.style.overflow='hidden';
}
async function submitAdd(){
    const f=new FormData();
    f.append('name',document.getElementById('add_name').value);
    f.append('phone',document.getElementById('add_phone').value);
    f.append('address',document.getElementById('add_address').value);
    f.append('note',document.getElementById('add_note').value);
    const r=await fetch('/settings/suppliers',{method:'POST',body:f});
    const d=await r.json();
    if(d.ok)location.reload();else alert('❌ '+d.message);
}
async function submitEdit(){
    const id=document.getElementById('edit_id').value;
    const f=new FormData();
    f.append('name',document.getElementById('edit_name').value);
    f.append('phone',document.getElementById('edit_phone').value);
    f.append('address',document.getElementById('edit_address').value);
    f.append('note',document.getElementById('edit_note').value);
    const r=await fetch('/settings/suppliers/'+id+'/update',{method:'POST',body:f});
    const d=await r.json();
    if(d.ok)location.reload();else alert('❌ '+d.message);
}
</script>
<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
