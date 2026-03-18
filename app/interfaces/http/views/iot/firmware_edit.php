<?php
global $pdo;

$title = 'Sửa Firmware';

// Get device types
$device_types = $pdo->query("SELECT * FROM device_types WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);

ob_start();
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-4">
        <a href="/settings/iot/firmwares" class="text-gray-400 hover:text-gray-600">← Quay lại</a>
        <h1 class="text-xl font-bold">✏️ Sửa Firmware</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm p-4">
        <form method="POST" action="/settings/iot/firmware/<?= $firmware->id ?>/update" class="space-y-3">
            
            <div>
                <label class="block text-xs font-medium mb-1">Tên firmware</label>
                <input type="text" name="name" value="<?= htmlspecialchars($firmware->name) ?>" required
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Version</label>
                <input type="text" name="version" value="<?= htmlspecialchars($firmware->version) ?>" required
                       class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Loại thiết bị</label>
                <select name="device_type_id" required
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm">
                    <?php foreach ($device_types as $t): ?>
                    <option value="<?= $t->id ?>" <?= $t->id == $firmware->device_type_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t->name) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Mô tả</label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-xl px-3 py-2.5 text-sm"><?= htmlspecialchars($firmware->description ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-medium mb-1">Mã nguồn (.ino)</label>
                <textarea name="code" rows="20" required
                          class="w-full border border-gray-300 dark:border-gray-600 bg-gray-900 text-green-400 font-mono rounded-xl px-3 py-2.5 text-xs"><?= htmlspecialchars($firmware->code) ?></textarea>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_latest" value="1" <?= $firmware->is_latest ? 'checked' : '' ?> class="rounded">
                <span>Phiên bản mới nhất</span>
            </label>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2.5 rounded-xl text-sm">
                    💾 Lưu
                </button>
                <a href="/settings/iot/firmwares" class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm">
                    Hủy
                </a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
