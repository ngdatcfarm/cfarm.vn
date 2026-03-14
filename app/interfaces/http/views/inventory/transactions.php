<?php
/**
 * app/interfaces/http/views/inventory/transactions.php
 *
 * Trang xem lịch sử giao dịch kho
 */
$title = 'Lịch Sử Giao Dịch';
ob_start();

$txn_labels = [
    'purchase'       => ['🟢', 'Nhập kho'],
    'transfer_out'   => ['🔵', 'Xuất barn'],
    'transfer_in'    => ['🔵', 'Nhận barn'],
    'use_feed'       => ['🟡', 'Dùng cám'],
    'use_medicine'   => ['🟠', 'Dùng thuốc'],
    'use_litter'     => ['⚪', 'Dùng trấu'],
    'sell'           => ['🔴', 'Bán ra'],
    'adjust'         => ['⚫', 'Điều chỉnh'],
    'dispose'        => ['🗑️', 'Thanh lý'],
];
?>

<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-2 mb-4">
        <a href="/inventory" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">←</a>
        <h1 class="text-xl font-bold">📋 Lịch Sử Giao Dịch</h1>
    </div>

    <!-- Filter Form -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
        <form method="GET" action="/inventory/transactions" class="space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-medium mb-1 block">Từ ngày</label>
                    <input type="date" name="date_from" value="<?= e($date_from) ?>"
                           class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
                </div>
                <div>
                    <label class="text-xs font-medium mb-1 block">Đến ngày</label>
                    <input type="date" name="date_to" value="<?= e($date_to) ?>"
                           class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-medium mb-1 block">Loại giao dịch</label>
                    <select name="type" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
                        <option value="all">Tất cả</option>
                        <option value="purchase" <?= $filter_type === 'purchase' ? 'selected' : '' ?>>🟢 Nhập kho</option>
                        <option value="transfer_out" <?= $filter_type === 'transfer_out' ? 'selected' : '' ?>>🔵 Xuất barn</option>
                        <option value="use_feed" <?= $filter_type === 'use_feed' ? 'selected' : '' ?>>🟡 Dùng cám</option>
                        <option value="use_medicine" <?= $filter_type === 'use_medicine' ? 'selected' : '' ?>>🟠 Dùng thuốc</option>
                        <option value="sell" <?= $filter_type === 'sell' ? 'selected' : '' ?>>🔴 Bán ra</option>
                        <option value="adjust" <?= $filter_type === 'adjust' ? 'selected' : '' ?>>⚫ Điều chỉnh</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium mb-1 block">Vật tư</label>
                    <select name="item" class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-gray-700">
                        <option value="">Tất cả</option>
                        <?php foreach ($items as $it): ?>
                        <option value="<?= $it['id'] ?>" <?= (string)$filter_item === (string)$it['id'] ? 'selected' : '' ?>>
                            <?= e($it['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-xl text-sm font-semibold">Lọc</button>
        </form>
    </div>

    <!-- Transactions List -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
        <?php if (empty($txns)): ?>
        <div class="text-center py-12 text-gray-400">
            <div class="text-4xl mb-3">📋</div>
            <div class="text-sm">Không có giao dịch nào</div>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-50 dark:divide-gray-700">
            <?php foreach ($txns as $t):
                $tl = $txn_labels[$t['txn_type']] ?? ['⚪', $t['txn_type']];
                $can_edit = in_array($t['txn_type'], ['purchase', 'transfer_out', 'sell']);
            ?>
            <div class="p-4 flex items-center gap-3">
                <div class="text-lg"><?= $tl[0] ?></div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate"><?= e($t['item_name']) ?></div>
                    <div class="text-xs text-gray-400">
                        <?= $tl[1] ?>
                        <?php if ($t['from_barn_name']): ?> · <?= e($t['from_barn_name']) ?><?php endif; ?>
                        <?php if ($t['to_barn_name']): ?> → <?= e($t['to_barn_name']) ?><?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($t['recorded_at'])) ?></div>
                    <?php if (!empty($t['note'])): ?>
                    <div class="text-xs text-gray-500 mt-1"><?= e($t['note']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <div class="font-bold <?= $t['txn_type'] === 'purchase' ? 'text-green-600' : 'text-gray-700 dark:text-gray-200' ?>">
                        <?= $t['txn_type'] === 'purchase' ? '+' : '-' ?><?= number_format((float)$t['quantity'], 1) ?>
                    </div>
                    <div class="text-xs text-gray-400"><?= e($t['unit']) ?></div>
                    <?php if ($can_edit): ?>
                    <div class="flex gap-1 mt-1 justify-end">
                        <button onclick="editTxn('<?= $t['txn_type'] ?>', <?= $t['id'] ?>)" class="text-blue-500 text-xs">✏️</button>
                        <button onclick="deleteTxn('<?= $t['txn_type'] ?>', <?= $t['id'] ?>)" class="text-red-500 text-xs">🗑️</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function editTxn(type, id) {
    const urls = {
        'purchase': '/inventory/purchases/' + id,
        'transfer_out': '/inventory/transactions/' + id,
        'sell': '/inventory/sales/' + id
    };
    const url = urls[type];
    if (!url) return;

    const r = await fetch(url);
    const d = await r.json();
    if (!d.ok) { alert('❌ ' + d.message); return; }

    // Handle edit - redirect to edit page or show modal
    alert('Chức năng sửa đang phát triển');
}

async function deleteTxn(type, id) {
    const labels = {
        'purchase': 'lần nhập kho',
        'transfer_out': 'lần xuất',
        'sell': 'lần bán'
    };
    const label = labels[type] || 'giao dịch';

    if (!confirm('Xóa ' + label + '?\nKho sẽ được hoàn lại tự động.')) return;

    const urls = {
        'purchase': '/inventory/purchases/' + id + '/delete',
        'transfer_out': '/inventory/transactions/' + id + '/delete',
        'sell': '/inventory/sales/' + id + '/delete'
    };
    const url = urls[type];
    if (!url) return;

    const formData = new FormData();
    const r = await fetch(url, { method: 'POST', body: formData });
    const d = await r.json();
    if (d.ok) {
        location.reload();
    } else {
        alert('❌ ' + d.message);
    }
}
</script>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
