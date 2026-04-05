<?php
// auth.php — Gestion des sessions et authentification

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        start_session();
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
}

function get_auth_user(): ?array
{
    start_session();
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'          => $_SESSION['user_id'],
        'username'    => $_SESSION['username'],
        'role'        => $_SESSION['role'] ?? 'viewer',
        'employee_id' => $_SESSION['employee_id'] ?? null,
    ];
}

function require_login(?string $required_role = null): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    if ($required_role) {
        $user = get_auth_user();
        if ($user['role'] !== $required_role && $user['role'] !== 'admin') {
            http_response_code(403);
            die('Acces refusee.');
        }
    }
}

function login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, username, password, role, employee_id FROM users WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    start_session();
    $_SESSION['user_id']     = (int) $user['id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['employee_id'] = $user['employee_id'] ? (int) $user['employee_id'] : null;

    return true;
}

function logout(): void
{
    start_session();
    session_destroy();
}
