<?php
/**
 * app/interfaces/http/views/barn/barn_list.php
 *
 * Hiển thị danh sách tất cả barn.
 */

$title = 'Danh sách chuồng trại';
ob_start();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">🏠 Chuồng trại</h1>
    <a href="/barns/create"
       class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
        + Thêm chuồng
    </a>
</div>

<?php if (empty($barns)): ?>
    <div class="text-center py-16 text-gray-400 dark:text-gray-500">
        <div class="text-5xl mb-4">🐔</div>
        <p class="text-lg">Chưa có chuồng nào</p>
        <a href="/barns/create" class="mt-4 inline-block text-green-600 hover:underline">
            Tạo chuồng đầu tiên
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($barns as $barn): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">

                <!-- header -->
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <span class="text-xs font-medium text-gray-400 dark:text-gray-500">
                            Chuồng số <?= e($barn->number) ?>
                        </span>
                        <h2 class="text-lg font-bold"><?= e($barn->name) ?></h2>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full
                        <?= $barn->status === 'active'
                            ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' ?>">
                        <?= $barn->status === 'active' ? 'Hoạt động' : 'Ngừng' ?>
                    </span>
                </div>

                <!-- kích thước -->
                <div class="grid grid-cols-3 gap-2 text-center text-sm mb-4">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                        <div class="text-gray-400 text-xs">Dài</div>
                        <div class="font-medium"><?= e($barn->length_m) ?>m</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                        <div class="text-gray-400 text-xs">Rộng</div>
                        <div class="font-medium"><?= e($barn->width_m) ?>m</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                        <div class="text-gray-400 text-xs">Cao</div>
                        <div class="font-medium"><?= e($barn->height_m) ?>m</div>
                    </div>
                </div>

                <!-- diện tích -->
                <div class="text-xs text-gray-400 dark:text-gray-500 mb-4">
                    Diện tích: <span class="font-medium text-gray-600 dark:text-gray-300">
                        <?= e(number_format($barn->area(), 1)) ?> m²
                    </span>
                    &nbsp;|&nbsp;
                    Thể tích: <span class="font-medium text-gray-600 dark:text-gray-300">
                        <?= e(number_format($barn->volume(), 1)) ?> m³
                    </span>
                </div>

                <!-- actions -->
                <div class="flex gap-2 text-sm">
                    <a href="/barns/<?= e($barn->id) ?>"
                       class="flex-1 text-center bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg py-1.5">
                        Chi tiết
                    </a>
                    <a href="/barns/<?= e($barn->id) ?>/edit"
                       class="flex-1 text-center bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-lg py-1.5">
                        Sửa
                    </a>
                    <form method="POST" action="/barns/<?= e($barn->id) ?>/delete"
                          onsubmit="return confirm('Xóa chuồng <?= e($barn->name) ?>?')">
                        <button type="submit"
                                class="text-center bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 rounded-lg py-1.5 px-3">
                            Xóa
                        </button>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
