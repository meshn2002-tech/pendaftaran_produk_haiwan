<?php
include __DIR__ . '/config/app.php';
app_session_start();
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/partials/site_return.php';

function password_stored_is_hash(string $stored): bool {
    $stored = trim($stored);

    if ($stored === '') {
        return false;
    }

    return (bool) preg_match('/^\$(2[ayb]|argon2id|argon2i)\$/', $stored);
}

function verify_user_password(string $plain, string $stored): bool {
    if ($stored === '') {
        return false;
    }

    if (password_stored_is_hash($stored)) {
        return password_verify($plain, $stored);
    }

    return hash_equals($stored, $plain);
}

function users_email_column(mysqli $conn): ?string {
    foreach (['email', 'emel'] as $column) {
        if (db_has_column($conn, 'users', $column)) {
            return $column;
        }
    }

    return null;
}

function users_password_column(mysqli $conn): ?string {
    foreach (['password', 'kata_laluan'] as $column) {
        if (db_has_column($conn, 'users', $column)) {
            return $column;
        }
    }

    return null;
}

$email = trim($_POST['login_email'] ?? $_POST['email'] ?? $_POST['emel'] ?? '');
$password = $_POST['login_password'] ?? $_POST['password'] ?? $_POST['kata_laluan'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login.php?error=missing');
    exit;
}

$emailColumn = users_email_column($conn);
if ($emailColumn === null) {
    die('Login unavailable.');
}

$passwordColumn = users_password_column($conn);
if ($passwordColumn === null) {
    die('Login unavailable.');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE `$emailColumn` = ?");

if (!$stmt) {
    die('Login unavailable.');
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

$candidates = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}

usort($candidates, static function ($a, $b) {
    $ra = strtolower(trim((string) ($a['role'] ?? '')));
    $rb = strtolower(trim((string) ($b['role'] ?? '')));

    return ($rb === 'admin') <=> ($ra === 'admin');
});

$user = null;

foreach ($candidates as $row) {
    $stored = (string) ($row[$passwordColumn] ?? '');
    if (!verify_user_password($password, $stored)) {
        continue;
    }

    $user = $row;

    break;
}

if ($user) {
    unset($user['password']);
    unset($user['kata_laluan']);

    if (!isset($user['email']) && isset($user['emel'])) {
        $user['email'] = $user['emel'];
    }
    if (!isset($user['name']) && isset($user['nama'])) {
        $user['name'] = $user['nama'];
    }

    $_SESSION['user'] = $user;

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        $return = site_safe_return_url((string) ($_POST['return'] ?? ''));
        header('Location: ' . $return);
    }

    exit;
}

header('Location: login.php?error=failed');
exit;
