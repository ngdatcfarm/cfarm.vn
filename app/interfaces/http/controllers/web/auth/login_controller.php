<?php
declare(strict_types=1);
namespace App\Interfaces\Http\Controllers\Web\Auth;
use PDO;

class LoginController
{
    public function __construct(private PDO $pdo) {}

    public function show(array $vars): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /'); exit;
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        require view_path('auth/login.php');
    }

    public function login(array $vars): void
    {
        require_once ROOT_PATH . '/app/shared/auth/auth.php';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (auth_login($this->pdo, $username, $password, $remember)) {
            header('Location: ' . ($_SESSION['intended_url'] ?? '/')); exit;
        }

        $_SESSION['login_error'] = 'Sai tên đăng nhập hoặc mật khẩu';
        header('Location: /login'); exit;
    }


    // GET /account
    public function account(array $vars): void
    {
        $user = $this->pdo->prepare("SELECT id, username, created_at FROM users WHERE id=:id");
        $user->execute([':id' => $_SESSION['user_id']]);
        $user = $user->fetch(\PDO::FETCH_OBJ);
        $success = $_SESSION['account_success'] ?? null;
        $error   = $_SESSION['account_error']   ?? null;
        unset($_SESSION['account_success'], $_SESSION['account_error']);
        require view_path('auth/account.php');
    }

    // POST /account/change-password
    public function change_password(array $vars): void
    {
        require_once ROOT_PATH . '/app/shared/auth/auth.php';
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        $user = $this->pdo->prepare("SELECT * FROM users WHERE id=:id");
        $user->execute([':id' => $_SESSION['user_id']]);
        $user = $user->fetch(\PDO::FETCH_OBJ);

        if (!password_verify($current, $user->password_hash)) {
            $_SESSION['account_error'] = 'Mật khẩu hiện tại không đúng';
            header('Location: /account'); exit;
        }
        if (strlen($new) < 6) {
            $_SESSION['account_error'] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
            header('Location: /account'); exit;
        }
        if ($new !== $confirm) {
            $_SESSION['account_error'] = 'Mật khẩu xác nhận không khớp';
            header('Location: /account'); exit;
        }

        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo->prepare("UPDATE users SET password_hash=:h WHERE id=:id")
            ->execute([':h' => $hash, ':id' => $_SESSION['user_id']]);

        // Xóa tất cả remember tokens cũ
        $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id=:id")
            ->execute([':id' => $_SESSION['user_id']]);

        $_SESSION['account_success'] = 'Đã đổi mật khẩu thành công';
        header('Location: /account'); exit;
    }
    public function logout(array $vars): void
    {
        require_once ROOT_PATH . '/app/shared/auth/auth.php';
        auth_logout($this->pdo);
        header('Location: /login'); exit;
    }
}
