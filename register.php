<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/app.php';
app_session_start();
include __DIR__ . '/config/config.php';
// Keep registration resilient: treat MySQL issues as normal failures on this page.
mysqli_report(MYSQLI_REPORT_OFF);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function has_column($conn, $table, $column) {
    $tableSafe = str_replace('`', '``', $table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$tableSafe` WHERE Field = '$safeColumn'");

    return $result && $result->num_rows > 0;
}

function user_column($conn, array $options) {
    foreach ($options as $column) {
        if (has_column($conn, 'users', $column)) {
            return $column;
        }
    }

    return null;
}

function db_connection_lost(mysqli $conn, ?mysqli_stmt $stmt = null): bool {
    $errno = $stmt ? (int) $stmt->errno : (int) $conn->errno;
    $error = strtolower(trim((string) ($stmt ? $stmt->error : $conn->error)));

    return $errno === 2006
        || $errno === 2013
        || str_contains($error, 'server has gone away')
        || str_contains($error, 'lost connection');
}

$message = '';
$messageType = '';
$old = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'user',
    'gender' => '',
];

if (isset($_POST['register'])) {
    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['role'] = $_POST['role'] ?? '';
    $allowedRoles = ['admin', 'user'];
    if (!in_array($old['role'], $allowedRoles)) {
        $old['role'] = 'user';
    }
    $old['gender'] = $_POST['gender'] ?? '';

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '' || $old['email'] === '' || $password === '' || $confirmPassword === '') {
        $message = 'Please complete all required fields.';
        $messageType = 'error';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'error';
    } else {
        $emailCol = user_column($conn, ['emel', 'email']);
        $checkStmt = $emailCol
            ? $conn->prepare('SELECT 1 FROM users WHERE `' . str_replace('`', '``', $emailCol) . '` = ? LIMIT 1')
            : false;

        if ($checkStmt) {
            $checkStmt->bind_param("s", $old['email']);
            if ($checkStmt->execute()) {
                $existingUser = $checkStmt->get_result();
            } elseif (db_connection_lost($conn, $checkStmt)) {
                $message = 'Sambungan pangkalan data terputus. Sila cuba lagi sebentar.';
                $messageType = 'error';
                $existingUser = false;
            } else {
                $message = 'Unable to verify the email at the moment. Please try again.';
                $messageType = 'error';
                $existingUser = false;
            }

            if ($message === '' && $existingUser && $existingUser->num_rows > 0) {
                $message = 'This email is already registered. Please log in instead.';
                $messageType = 'error';
            }
        }
    }

    if ($message === '') {
        $nameCol = user_column($conn, ['nama', 'name']);
        $emailCol = user_column($conn, ['emel', 'email']);
        $passCol = user_column($conn, ['kata_laluan', 'password']);
        $phoneCol = user_column($conn, ['no_telefon', 'phone']);
        $genderCol = user_column($conn, ['jantina', 'gender']);
        $roleCol = user_column($conn, ['role']);

        if (!$nameCol || !$emailCol || !$passCol) {
            $message = 'Unable to register account. Users table columns are incomplete.';
            $messageType = 'error';
        }

        $columns = [$nameCol, $emailCol, $passCol];
        $values = [$old['name'], $old['email'], $password];
        $types = 'sss';

        $pkCol = users_insert_pk_column($conn);
        if ($message === '' && $pkCol !== null) {
            $roleIsAdmin = strtolower(trim((string) $old['role'])) === 'admin';
            $newPk = next_user_admin_role_kod($conn, $roleIsAdmin);
            array_unshift($columns, $pkCol);
            array_unshift($values, $newPk);
            $types = 's' . $types;
        }

        if ($message === '' && $phoneCol) {
            $columns[] = $phoneCol;
            $values[] = $old['phone'];
            $types .= 's';
        }

        if ($message === '' && $genderCol) {
            $columns[] = $genderCol;
            $values[] = users_normalize_gender($old['gender']);
            $types .= 's';
        }

        if ($message === '' && $roleCol) {
            $columns[] = $roleCol;
            $values[] = $old['role'] === 'admin' ? 'admin' : 'user';
            $types .= 's';
        }

        $safeColumns = array_map(static function ($column) {
            return '`' . str_replace('`', '``', (string) $column) . '`';
        }, $columns);
        $columnSql = implode(',', $safeColumns);
        $placeholderSql = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $message === ''
            ? $conn->prepare("INSERT INTO users ($columnSql) VALUES ($placeholderSql)")
            : false;

        if ($stmt) {
            $stmt->bind_param($types, ...$values);

            if ($stmt->execute()) {
                $registeredRole = $old['role'] === 'admin' ? 'admin' : 'user';
                if ($registeredRole === 'admin') {
                    $sessionUser = [
                        'name' => $old['name'],
                        'email' => $old['email'],
                        'role' => 'admin',
                    ];
                    if ($nameCol && $nameCol !== 'name') {
                        $sessionUser[$nameCol] = $old['name'];
                    }
                    if ($emailCol && $emailCol !== 'email') {
                        $sessionUser[$emailCol] = $old['email'];
                    }
                    if ($phoneCol) {
                        $sessionUser[$phoneCol] = $old['phone'];
                    }
                    if ($genderCol) {
                        $sessionUser[$genderCol] = $old['gender'];
                    }
                    if ($roleCol) {
                        $sessionUser[$roleCol] = 'admin';
                    }
                    if ($pkCol !== null) {
                        $sessionUser[$pkCol] = $newPk ?? '';
                    }

                    $_SESSION['user'] = $sessionUser;
                    header('Location: admin/dashboard.php');
                    exit;
                }

                $message = 'Account registered successfully. You can log in now.';
                $messageType = 'success';
                $old = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'user', 'gender' => ''];
            } elseif (db_connection_lost($conn, $stmt)) {
                $message = 'Sambungan pangkalan data terputus semasa pendaftaran. Sila cuba lagi.';
                $messageType = 'error';
            } elseif ((int) $stmt->errno === 1062 || (int) $conn->errno === 1062) {
                $message = 'This email is already registered. Please log in instead.';
                $messageType = 'error';
            } else {
                $message = 'Unable to register account. The email may already exist.';
                $messageType = 'error';
            }
        } else {
            $message = 'Unable to prepare registration.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="images/favicon-192.png">
    <link rel="apple-touch-icon" href="images/apple-touch-icon.png">
    <title data-i18n="pageTitleRegister">Register Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --orange: #ff8f1f;
            --orange-deep: #ff7a00;
            --ink: #111111;
            --muted: #737b86;
            --line: #eef0f3;
            --soft: #f6f7f9;
            --page: #f4f5f7;
            --panel: #ffffff;
            --field: #ffffff;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--page);
            color: var(--ink);
            font-family: "Poppins", Arial, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .page {
            min-height: 100vh;
            padding: 25px 18px 42px;
        }

        .topbar {
            display: flex;
            justify-content: center;
            gap: 34px;
            padding: 17px 20px 12px;
            color: #0f1115;
            font-size: 12px;
            font-weight: 600;
        }

        .topbar span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .nav-wrap {
            position: sticky;
            top: 0;
            z-index: 20;
            padding: 0;
            margin-bottom: 8px;
        }

        .nav {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1.5fr;
            align-items: center;
            width: min(1030px, 100%);
            min-height: 68px;
            margin: 0 auto;
            padding: 0 24px;
            background: var(--white);
            border-radius: 22px;
            box-shadow: 0 18px 35px rgba(15, 23, 42, .08);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .brand .logo {
            width: 26px;
            height: 26px;
            object-fit: contain;
        }

        .brand span {
            display: block;
            line-height: 1;
            font-size: 13px;
        }

        .brand-mark {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border-radius: 50%;
            background: #fff0e1;
            color: var(--orange-deep);
            font-size: 10px;
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: 62px;
            font-size: 14px;
            font-weight: 600;
        }

        .nav-links a {
            padding: 25px 0 22px;
            border-bottom: 2px solid transparent;
            color: var(--ink);
        }

        .nav-links .active {
            color: var(--orange-deep);
            border-color: var(--orange-deep);
        }

        .nav-tools {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .search {
            display: flex;
            align-items: center;
            height: 29px;
            min-width: 155px;
            padding: 0 4px 0 14px;
            background: var(--soft);
            border-radius: 999px;
        }

        .search input {
            width: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            font-family: inherit;
            font-size: 11px;
        }

        .search button {
            display: grid;
            width: 24px;
            height: 24px;
            place-items: center;
            border: 0;
            border-radius: 50%;
            background: #050505;
            color: #fff;
            cursor: pointer;
        }

        .cart {
            position: relative;
            font-size: 20px;
            line-height: 1;
        }

        .cart::after {
            content: "";
            position: absolute;
            top: 0;
            right: -2px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--orange-deep);
        }

        .translate {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
            border: 0;
            padding: 0;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .translate .globe {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border: 2px solid #111;
            border-radius: 50%;
            font-size: 15px;
        }

        .auth {
            white-space: nowrap;
        }

        .auth a {
            color: var(--ink);
        }

        .auth a.active {
            color: var(--orange-deep);
        }

        .translate-wrap.open .translate,
        .auth a:hover,
        .auth a:focus-visible,
        .nav-links a:hover,
        .nav-links a:focus-visible {
            color: var(--orange-deep);
        }

        .translate-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 18px 35px rgba(15, 23, 42, .12);
            padding: 8px;
            min-width: 130px;
            z-index: 50;
        }

        .dropdown-content a {
            display: block;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            color: #111;
        }

        .dropdown-content a:hover {
            background: #fff4e9;
            color: var(--orange-deep);
        }

        .dropdown-content a.active {
            background: #fff4e9;
            color: var(--orange-deep);
        }

        .translate-wrap.open .dropdown-content {
            display: block;
        }

        .cart-btn {
            border: 0;
            background: transparent;
            padding: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .toast {
            position: fixed;
            left: 50%;
            bottom: 24px;
            transform: translateX(-50%);
            background: #0b0d10;
            color: var(--white);
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 18px 35px rgba(0,0,0,.25);
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s ease;
            z-index: 100;
        }

        .toast.show {
            opacity: 1;
        }

        .register-shell {
            position: relative;
            width: min(1258px, 100%);
            min-height: 690px;
            margin: 0 auto;
            padding: 56px 72px 62px;
            background: var(--panel);
            overflow: hidden;
        }

        .register-shell::before {
            content: "";
            position: absolute;
            top: -88px;
            right: 90px;
            width: 210px;
            height: 160px;
            border-radius: 0 0 105px 105px;
            background: var(--orange);
        }

        .register-shell::after {
            content: "";
            position: absolute;
            left: 135px;
            bottom: -78px;
            width: 175px;
            height: 126px;
            border-radius: 100px 100px 0 0;
            background: var(--orange);
            opacity: .92;
        }

        .page-heading {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            max-width: 980px;
            margin: 0 auto 38px;
        }

        .page-heading h1 {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
            font-weight: 800;
        }

        .page-heading p {
            max-width: 360px;
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
            font-weight: 500;
        }

        .page-heading a.auth {
            color: var(--orange-deep);
            font-weight: 600;
        }

        .account-panel {
            position: relative;
            z-index: 2;
            width: min(920px, 100%);
            margin: 0 auto;
            padding: 34px 38px 32px;
            background: #f7f7f7;
            border-top: 5px solid var(--orange-deep);
            border-radius: 8px;
            box-shadow: 0 16px 38px rgba(15, 23, 42, .08);
        }

        .account-panel h2 {
            margin: 0 0 28px;
            font-size: 24px;
            font-weight: 800;
        }

        .message {
            width: min(920px, 100%);
            margin: 0 auto 14px;
            padding: 12px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        .message.success {
            background: #e4f8e9;
            color: #176b2c;
        }

        .message.error {
            background: #ffe8e5;
            color: #9a1d13;
        }

        .account-form {
            display: grid;
            gap: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px 28px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field label,
        .field-title {
            font-size: 15px;
            font-weight: 700;
        }

        .field input,
        .field select {
            width: 100%;
            height: 43px;
            border: 0;
            border-radius: 8px;
            background: var(--field);
            font-family: inherit;
            font-size: 14px;
            padding: 0 13px;
            outline: 1px solid transparent;
        }

        .field select {
            appearance: none;
            background-image: linear-gradient(45deg, transparent 50%, #111 50%), linear-gradient(135deg, #111 50%, transparent 50%);
            background-position: calc(100% - 18px) 18px, calc(100% - 12px) 18px;
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-right: 36px;
        }

        .field input:focus,
        .field select:focus {
            outline-color: var(--orange-deep);
        }

        .password-help {
            max-width: 390px;
            margin: -1px 0 0;
            color: #3b3f45;
            font-size: 11px;
            line-height: 1.35;
            font-weight: 600;
        }

        .radio-group {
            display: flex;
            min-height: 43px;
            gap: 34px;
            align-items: center;
            padding: 0 4px;
        }

        .radio-group label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .radio-group input {
            width: 20px;
            height: 20px;
            accent-color: var(--orange-deep);
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-top: 6px;
        }

        .login-link {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .login-link strong {
            color: var(--orange-deep);
        }

        .register-button {
            width: 172px;
            height: 38px;
            border: 0;
            border-radius: 6px;
            background: var(--orange-deep);
            color: #fff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        @media (max-width: 1040px) {
            .nav {
                width: 100%;
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 15px 20px;
            }

            .nav-links,
            .nav-tools {
                justify-content: flex-start;
            }

            .topbar {
                flex-wrap: wrap;
                justify-content: flex-start;
            }
        }

        @media (max-width: 700px) {
            .page {
                padding: 18px 16px 32px;
            }

            .register-shell {
                padding: 32px 18px 42px;
            }

            .page-heading {
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .register-button {
                width: 100%;
            }

            .nav-tools {
                flex-wrap: wrap;
            }

            .search {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <span>📞 +60 10-211 9319 / +60 10-381 1946</span>
            <span>✉️ petseberangjaya@gmail.com</span>
            <span>📍 No 63A, Lorong Lampam Seberang Jaya, 13700 Perai, Pulau Pinang</span>
        </div>

        <div class="nav-wrap">
            <nav class="nav" aria-label="Navigasi utama">
                <a class="brand" href="index.php">
                    <img class="logo" src="images/logo.png" alt="Logo">
                    <span>PENDAFTARAN PRODUK HAIWAN</span>
                </a>
                <div class="nav-links">
                    <a href="index.php" data-i18n="navUtama">Utama</a>
                    <a href="index.php#produk" data-i18n="navKedai">Kedai</a>
                </div>
                <div class="nav-tools">
                    <form class="search" action="index.php" method="GET">
                        <input type="text" name="search" data-i18n-placeholder="searchPlaceholder" data-i18n-aria-label="pdSearchAria" placeholder="Cari produk…" aria-label="Cari produk">
                        <button type="submit" data-i18n-aria-label="pdSubmitSearchAria" aria-label="Hantar carian"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                    <button type="button" class="cart-btn" id="cartBtn" data-i18n-aria-label="pdCartAria" aria-label="Troli">
                        <span class="cart" aria-hidden="true"><i class="fa-solid fa-cart-shopping"></i></span>
                    </button>
                    <div class="translate-wrap" id="translateWrap">
                        <button type="button" class="translate" id="translateToggle" aria-haspopup="menu" aria-expanded="false">
                            <span class="globe"><i class="fa-solid fa-globe"></i></span>
                            <span data-i18n="translateLabel">Terjemah</span> <i class="fa-solid fa-caret-down"></i>
                        </button>
                        <div class="dropdown-content" id="translateMenu" role="menu" data-i18n-aria-label="translateMenuAria" aria-label="Terjemah">
                            <a href="#" data-lang="en" role="menuitem" data-i18n="langEnglish">English</a>
                            <a href="#" data-lang="ms" role="menuitem" data-i18n="langMalay">Melayu</a>
                        </div>
                    </div>
                    <span class="auth"><a href="login.php" data-i18n="loginLink">Log Masuk</a> | <a class="active" href="register.php" data-i18n="registerLink">Daftar</a></span>
                </div>
            </nav>
        </div>

        <section class="register-shell" aria-label="Daftar akaun">
            <div class="page-heading">
                <div>
                    <h1 data-i18n="registerHeading">Akaun Saya</h1>
                    <p data-i18n="registerLead">Buat akaun untuk melayari produk dengan lebih cepat dan akses mengikut jenis akaun anda.</p>
                </div>
                <p><span data-i18n="registerAlreadyPrompt">Sudah daftar?</span> <a class="auth" href="login.php" data-i18n="loginLink">Log Masuk</a></p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="message <?php echo e($messageType); ?>"><?php echo e($message); ?></div>
            <?php endif; ?>

            <div class="account-panel">
                <h2 data-i18n="registerPersonalInformation">Maklumat peribadi</h2>
                <form class="account-form" method="POST" action="register.php">
                    <div class="form-grid">
                        <div class="field">
                            <label for="name" data-i18n="formLabelName">Nama</label>
                            <input id="name" type="text" name="name" value="<?php echo e($old['name']); ?>" required>
                        </div>
                        <div class="field">
                            <label for="email" data-i18n="formLabelEmail">Emel</label>
                            <input id="email" type="email" name="email" value="<?php echo e($old['email']); ?>" required>
                        </div>
                        <div class="field">
                            <label for="password" data-i18n="registerNewPassword">Kata laluan baharu</label>
                            <input id="password" type="password" name="password" required>
                            <p class="password-help" data-i18n="registerPasswordHelp">Kata laluan anda mestilah sekurang-kurangnya 8 aksara, termasuk huruf kecil, huruf besar, nombor dan aksara khas.</p>
                        </div>
                        <div class="field">
                            <label for="confirm_password" data-i18n="registerConfirmPassword">Sahkan kata laluan</label>
                            <input id="confirm_password" type="password" name="confirm_password" required>
                        </div>
                        <div class="field">
                            <label for="phone" data-i18n="registerMobileNo">No. telefon bimbit</label>
                            <input id="phone" type="text" name="phone" value="<?php echo e($old['phone']); ?>">
                        </div>
                        <div class="field">
                            <span class="field-title" data-i18n="formLabelGender">Jantina</span>
                            <div class="radio-group">
                                <label><input type="radio" name="gender" value="Male" <?php echo $old['gender'] === 'Male' ? 'checked' : ''; ?>> <span data-i18n="formMale">Lelaki</span></label>
                                <label><input type="radio" name="gender" value="Female" <?php echo $old['gender'] === 'Female' ? 'checked' : ''; ?>> <span data-i18n="formFemale">Perempuan</span></label>
                            </div>
                        </div>
                        <div class="field">
                            <label for="role" data-i18n="registerAccountType">Akaun</label>
                            <select id="role" name="role">
                                <option value="user" <?php echo $old['role'] === 'user' ? 'selected' : ''; ?> data-i18n="registerAccountUser">User / Pelanggan</option>
                                <option value="admin" <?php echo $old['role'] === 'admin' ? 'selected' : ''; ?> data-i18n="registerAccountAdmin">Admin / Pemilik</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <a class="login-link" href="login.php"><span data-i18n="registerAlreadyHave">Sudah ada akaun?</span> <strong data-i18n="loginLink">Log Masuk</strong></a>
                        <button class="register-button" type="submit" name="register" data-i18n="registerSubmit">Daftar</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <div class="toast" id="toast" role="status" aria-live="polite" data-i18n="toastCartEmpty">Troli anda kosong buat masa ini.</div>

    <script src="js/site-i18n.js?v=8"></script>
    <script>
        (function () {
            const toast = document.getElementById('toast');
            const cartBtn = document.getElementById('cartBtn');
            let toastTimer = null;
            function emptyCartCopy() {
                return (
                    (typeof window.siteI18nPhrase === 'function' && window.siteI18nPhrase('toastCartEmpty')) ||
                    'Troli anda kosong buat masa ini.'
                );
            }
            function showToast(message) {
                if (!toast) return;
                toast.textContent = message;
                toast.classList.add('show');
                if (toastTimer) window.clearTimeout(toastTimer);
                toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2200);
            }
            if (cartBtn) {
                cartBtn.addEventListener('click', function () {
                    showToast(emptyCartCopy());
                });
            }
            document.addEventListener('site-lang-change', function () {
                if (toast && !toast.classList.contains('show')) {
                    toast.textContent = emptyCartCopy();
                }
            });
        })();
    </script>
</body>
</html>
