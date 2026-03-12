<?php
/**
 * CFarm Auth — Session + Remember Me 30 ngày
 */

function auth_check(PDO $pdo): bool
{
    // Đã có session
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    // Kiểm tra remember_me cookie
    $token = $_COOKIE['remember_me'] ?? '';
    if (empty($token)) return false;

    $stmt = $pdo->prepare("
        SELECT r.user_id, u.username
        FROM remember_tokens r
        JOIN users u ON u.id = r.user_id
        WHERE r.token = :token
          AND r.expires_at > NOW()
    ");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_OBJ);
    if (!$row) {
        // Token hết hạn — xóa cookie
        setcookie('remember_me', '', time() - 3600, '/', '', false, true);
        return false;
    }

    // Restore session
    $_SESSION['user_id']  = $row->user_id;
    $_SESSION['username'] = $row->username;

    // Gia hạn token thêm 30 ngày
    $new_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $pdo->prepare("UPDATE remember_tokens SET expires_at=:exp WHERE token=:tok")
        ->execute([':exp' => $new_expires, ':tok' => $token]);
    setcookie('remember_me', $token, time() + 86400 * 30, '/', '', false, true);

    return true;
}

function auth_login(PDO $pdo, string $username, string $password, bool $remember): bool
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$user || !password_verify($password, $user->password_hash)) {
        return false;
    }

    $_SESSION['user_id']  = $user->id;
    $_SESSION['username'] = $user->username;

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("
            INSERT INTO remember_tokens (user_id, token, expires_at)
            VALUES (:uid, :tok, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ")->execute([':uid' => $user->id, ':tok' => $token]);
        setcookie('remember_me', $token, time() + 86400 * 30, '/', '', false, true);
    }

    return true;
}

function auth_logout(PDO $pdo): void
{
    // Xóa remember token
    $token = $_COOKIE['remember_me'] ?? '';
    if ($token) {
        $pdo->prepare("DELETE FROM remember_tokens WHERE token=:tok")
            ->execute([':tok' => $token]);
        setcookie('remember_me', '', time() - 3600, '/', '', false, true);
    }

    $_SESSION = [];
    session_destroy();
}

function auth_user(): ?string
{
    return $_SESSION['username'] ?? null;
}
