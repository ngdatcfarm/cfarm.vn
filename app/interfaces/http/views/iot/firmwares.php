<?php
$title = 'Firmware Library — IoT Settings';

ob_start();
?>
<div class="mb-4 flex items-center justify-between">
    <a href="/settings/iot" class="text-sm text-blue-600 hover:underline">← IoT Settings</a>
</div>

<!-- Upload Form -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-3">📤 Upload Firmware Mới</div>
    <form id="uploadForm" enctype="multipart/form-data" class="space-y-3">
        <div class="flex gap-3 flex-wrap">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-gray-500 block mb-1">Loại thiết bị</label>
                <select name="device_type_id" required
                        class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700">
                    <option value="">-- Chọn loại --</option>
                    <?php foreach ($device_types as $dt): ?>
                    <option value="<?= $dt->id ?>"><?= e($dt->name) ?> (<?= e($dt->device_class) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-32">
                <label class="text-xs text-gray-500 block mb-1">Version</label>
                <input type="text" name="version" placeholder="1.0.0" required
                       class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm font-mono bg-white dark:bg-gray-700">
            </div>
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">File Firmware (.bin)</label>
            <input type="file" name="firmware_file" accept=".bin" required
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Ghi chú (tùy chọn)</label>
            <input type="text" name="notes" placeholder="Mô tả phiên bản..."
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700">
        </div>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
            📤 Upload Firmware
        </button>
    </form>
    <div id="uploadResult" class="mt-3 text-sm hidden"></div>
</div>

<!-- Filter -->
<div class="flex gap-2 mb-3">
    <a href="/settings/iot/firmwares" class="text-xs px-3 py-1 rounded-full <?= !$type_id ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700' ?>">Tất cả</a>
    <?php foreach ($device_types as $dt): ?>
    <a href="/settings/iot/firmwares?type_id=<?= $dt->id ?>"
       class="text-xs px-3 py-1 rounded-full <?= $type_id == $dt->id ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700' ?>">
        <?= e($dt->name) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Firmware List -->
<?php if (empty($firmwares)): ?>
<div class="text-center text-gray-400 py-8">
    <div class="text-4xl mb-2">📦</div>
    <div>Chưa có firmware nào được upload</div>
</div>
<?php else: ?>
<div class="space-y-2">
    <?php foreach ($firmwares as $fw): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-xl">
                📦
            </div>
            <div>
                <div class="text-sm font-semibold">
                    <span class="font-mono bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">v<?= e($fw->version) ?></span>
                    <span class="ml-2"><?= e($fw->type_name) ?></span>
                </div>
                <div class="text-xs text-gray-400">
                    <?= number_format($fw->file_size) ?> bytes ·
                    <?= date('d/m/Y H:i', strtotime($fw->uploaded_at)) ?>
                    <?php if ($fw->notes): ?> · <?= e($fw->notes) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="/api/firmware/download/<?= $fw->id ?>"
               class="text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 px-3 py-1.5 rounded-lg"
               download>
                ⬇️ Tải
            </a>
            <button onclick="deleteFirmware(<?= $fw->id ?>)"
                    class="text-xs text-red-500 hover:text-red-600 px-2 py-1.5">
                🗑️
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const result = document.getElementById('uploadResult');
    result.classList.remove('hidden', 'text-green-600', 'text-red-600');

    try {
        const r = await fetch('/settings/iot/firmwares/upload', {
            method: 'POST',
            body: formData
        });
        const d = await r.json();
        if (d.ok) {
            result.textContent = '✅ ' + d.message;
            result.classList.add('text-green-600');
            setTimeout(() => location.reload(), 1500);
        } else {
            result.textContent = '❌ ' + d.message;
            result.classList.add('text-red-600');
        }
    } catch(err) {
        result.textContent = '❌ Lỗi kết nối';
        result.classList.add('text-red-600');
    }
    result.classList.remove('hidden');
});

async function deleteFirmware(id) {
    if (!confirm('Xóa firmware này?')) return;
    try {
        const r = await fetch('/settings/iot/firmware/' + id + '/delete', { method: 'POST' });
        const d = await r.json();
        if (d.ok) location.reload();
        else alert('❌ ' + d.message);
    } catch(e) {
        alert('❌ Lỗi kết nối');
    }
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
