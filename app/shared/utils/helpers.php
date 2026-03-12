<?php
/**
 * app/shared/utils/helpers.php
 *
 * Các hàm tiện ích dùng chung toàn ứng dụng.
 * Load một lần duy nhất trong bootstrap.php.
 */

declare(strict_types=1);

// Trả về đường dẫn tuyệt đối đến file view
function view_path(string $path): string
{
    return ROOT_PATH . '/app/interfaces/http/views/' . $path;
}

// Redirect về url khác và dừng script
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Escape HTML để tránh XSS
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Trả về class CSS active nếu url hiện tại khớp
function active(string $path): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Exact match cho '/', prefix match cho các path khác
    if ($path === '/') return $uri === '/' ? 'active' : '';
    return str_starts_with($uri, $path) ? 'active' : '';
}
