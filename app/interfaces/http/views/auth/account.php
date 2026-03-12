<?php
$title = 'Tài khoản';
ob_start();
?>

<div class="mb-4">
    <a href="/" class="text-sm text-blue-600 hover:underline">← Bảng điều khiển</a>
</div>

<!-- Header -->
<div class="bg-gray-800 rounded-2xl p-4 mb-4 flex items-center gap-4">
    <div class="w-14 h-14 rounded-2xl bg-blue-600 flex items-center justify-center text-3xl">👤</div>
    <div>
        <div class="font-bold text-white text-lg"><?= e($user->username) ?></div>
        <div class="text-xs text-gray-400">Admin · Tham gia <?= date('d/m/Y', strtotime($user->created_at)) ?></div>
    </div>
</div>

<?php if ($success): ?>
<div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-600 text-sm rounded-2xl px-4 py-3 mb-4">
    ✅ <?= e($success) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 text-sm rounded-2xl px-4 py-3 mb-4">
    ❌ <?= e($error) ?>
</div>
<?php endif; ?>

<!-- Đổi mật khẩu -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-4">🔑 Đổi mật khẩu</div>
    <form method="POST" action="/account/change-password" class="space-y-3">
        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Mật khẩu hiện tại</label>
            <input type="password" name="current_password" required
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Mật khẩu mới</label>
            <input type="password" name="new_password" required minlength="6"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="text-xs font-medium text-gray-500 block mb-1">Xác nhận mật khẩu mới</label>
            <input type="password" name="confirm_password" required minlength="6"
                   class="w-full border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-2.5 text-sm bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm transition">
            Đổi mật khẩu
        </button>
    </form>
</div>

<!-- Sessions -->
<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4">
    <div class="text-sm font-semibold mb-1">📱 Phiên đăng nhập</div>
    <div class="text-xs text-gray-400 mb-3">Các thiết bị đang nhớ đăng nhập</div>
    <?php
    global $pdo;
    $tokens = $pdo->prepare("SELECT * FROM remember_tokens WHERE user_id=:id ORDER BY created_at DESC");
    $tokens->execute([':id' => $user->id]);
    $tokens = $tokens->fetchAll(PDO::FETCH_OBJ);
    ?>
    <?php if (empty($tokens)): ?>
    <div class="text-xs text-gray-400">Không có phiên nào đang hoạt động</div>
    <?php else: ?>
    <div class="space-y-2">
        <?php foreach ($tokens as $t): ?>
        <div class="flex items-center justify-between text-xs py-1.5 border-t border-gray-50 dark:border-gray-700">
            <div>
                <span class="font-medium">📲 Thiết bị</span>
                <span class="text-gray-400 ml-2">Đăng nhập <?= date('d/m/Y H:i', strtotime($t->created_at)) ?></span>
            </div>
            <span class="text-gray-300">hết hạn <?= date('d/m', strtotime($t->expires_at)) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Đăng xuất -->
<a href="/logout"
   onclick="return confirm('Đăng xuất khỏi thiết bị này?')"
   class="block w-full text-center bg-red-50 dark:bg-red-900/20 hover:bg-red-100 text-red-600 font-semibold py-3.5 rounded-2xl text-sm transition border border-red-100 dark:border-red-800">
    🚪 Đăng xuất
</a>

<?php
$content = ob_get_clean();
require view_path('layouts/main.php');
?>
