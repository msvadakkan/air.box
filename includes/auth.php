<?php

function users_db(): JsonDB
{
    static $db = null;
    if ($db === null) $db = new JsonDB('users');
    return $db;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = users_db()->find($_SESSION['user_id']);
    }
    return $cache;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user || ($user['role'] ?? 'user') !== 'admin') {
        redirect(base_url('admin/login.php'));
    }
    return $user;
}

function attempt_login(string $email, string $password)
{
    $email = strtolower(trim($email));
    $user = users_db()->where(fn($u) => $u['email'] === $email)[0] ?? null;

    if (!$user || !password_verify($password, $user['password'])) {
        return 'Incorrect email or password.';
    }

    $_SESSION['user_id'] = $user['id'];
    return $user;
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function change_password(string $userId, string $currentPassword, string $newPassword, string $confirmPassword)
{
    $user = users_db()->find($userId);
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        return 'Current password is incorrect.';
    }
    if (strlen($newPassword) < 6) {
        return 'New password must be at least 6 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        return 'New password and confirmation do not match.';
    }

    users_db()->update($userId, ['password' => password_hash($newPassword, PASSWORD_DEFAULT)]);
    return true;
}
