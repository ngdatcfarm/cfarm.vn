<?php
$title = 'Đăng nhập — CFarm';
?>
<!DOCTYPE html>
<html lang="vi" class="<?= $_COOKIE['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-sm">
    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="w-20 h-20 rounded-3xl bg-blue-600 flex items-center justify-center text-4xl mx-auto mb-4 shadow-lg">
            🐔
        </div>
        <div class="text-2xl font-bold dark:text-white">CFarm</div>
        <div class="text-sm text-gray-400 mt-1">Hệ thống quản lý trang trại</div>
    </div>

    <!-- Card -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl p-6">
        <?php if ($error): ?>
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm rounded-2xl px-4 py-3 mb-4">
            ❌ <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/login" class="space-y-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-1.5">
                    Tên đăng nhập
                </label>
                <input type="text" name="username" autocomplete="username"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       required autofocus
                       class="w-full border border-gray-200 dark:border-gray-600 rounded-2xl px-4 py-3
                              bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                              text-sm transition">
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-1.5">
                    Mật khẩu
                </label>
                <div class="relative">
                    <input type="password" name="password" id="password"
                           autocomplete="current-password"
                           required
                           class="w-full border border-gray-200 dark:border-gray-600 rounded-2xl px-4 py-3
                                  bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                  text-sm transition pr-12">
                    <button type="button" onclick="togglePw()"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg">
                        👁️
                    </button>
                </div>
            </div>

            <!-- Remember me -->
            <label class="flex items-center gap-3 cursor-pointer select-none">
                <div class="relative">
                    <input type="checkbox" name="remember" id="remember" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer-checked:bg-blue-600 transition-colors"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-300">Nhớ đăng nhập 30 ngày</span>
            </label>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold
                           py-3.5 rounded-2xl transition-colors text-sm shadow-md shadow-blue-200 dark:shadow-none">
                Đăng nhập
            </button>
        </form>
    </div>

    <div class="text-center mt-6 text-xs text-gray-400">CFarm © <?= date('Y') ?></div>
</div>

<script>
function togglePw() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
