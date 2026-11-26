<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/app.php';
app_session_start();
include __DIR__ . '/config/config.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function forgot_user_col(mysqli $conn, array $options): ?string {
    foreach ($options as $column) {
        if (db_has_column($conn, 'users', $column)) {
            return $column;
        }
    }

    return null;
}

$message = '';
$messageType = '';
$prefillEmail = trim($_GET['email'] ?? '');

if (isset($_POST['reset'])) {
    $email = trim($_POST['email'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    if ($email === '' || $newPassword === '') {
        $message = 'Sila isi semua maklumat.';
        $messageType = 'error';
    } else {
        $emailCol = forgot_user_col($conn, ['emel', 'email']);
        $passCol = forgot_user_col($conn, ['kata_laluan', 'password']);
        $issueCol = forgot_user_col($conn, ['password_issue']);

        if (!$emailCol || !$passCol) {
            $message = 'Struktur jadual users tidak lengkap.';
            $messageType = 'error';
        } else {
            $emailSql = '`' . str_replace('`', '``', $emailCol) . '`';
            $passSql = '`' . str_replace('`', '``', $passCol) . '`';
            $stmt = $conn->prepare("SELECT * FROM users WHERE $emailSql = ? LIMIT 1");

            if (!$stmt) {
                $message = 'Ralat sistem.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($user) {
                    $setParts = ["$passSql = ?"];
                    $params = [$newPassword];
                    $types = 's';

                    if ($issueCol) {
                        $issueSql = '`' . str_replace('`', '``', $issueCol) . '`';
                        $setParts[] = "$issueSql = 1";
                    }

                    $update = $conn->prepare('UPDATE users SET ' . implode(', ', $setParts) . " WHERE $emailSql = ? LIMIT 1");
                    if ($update) {
                        $params[] = $email;
                        $types .= 's';
                        $update->bind_param($types, ...$params);

                        if ($update->execute()) {
                            $message = 'Password berjaya ditukar. Admin akan nampak amaran pada akaun ini.';
                            $messageType = 'success';
                        } else {
                            $message = 'Ralat semasa update.';
                            $messageType = 'error';
                        }

                        $update->close();
                    } else {
                        $message = 'Ralat semasa update.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Email tidak dijumpai.';
                    $messageType = 'error';
                }
            }
        }
    }
}
$forgotMessageKeys = [
    'Sila isi semua maklumat.' => 'forgotMsgMissing',
    'Struktur jadual users tidak lengkap.' => 'forgotMsgTable',
    'Ralat sistem.' => 'forgotMsgSystem',
    'Password berjaya ditukar. Admin akan nampak amaran pada akaun ini.' => 'forgotMsgSuccess',
    'Ralat semasa update.' => 'forgotMsgUpdate',
    'Email tidak dijumpai.' => 'forgotMsgNotFound',
];
$messageKey = $forgotMessageKeys[$message] ?? '';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="images/favicon-192.png">
    <link rel="apple-touch-icon" href="images/apple-touch-icon.png">
    <title data-i18n="pageTitleForgot">Lupa Kata Laluan</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            color: #111;
        }
        .wrap {
            max-width: 560px;
            margin: 56px auto;
            padding: 0 18px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(16, 24, 40, .08);
            padding: 26px;
        }
        .reset-form {
            width: min(100%, 470px);
        }
        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }
        .help {
            margin: 0 0 18px;
            color: #5c636e;
            font-size: 13px;
            line-height: 1.6;
        }
        .message {
            margin: 0 0 18px;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
        }
        .message.success {
            background: #e7fbeb;
            color: #0f6a2d;
        }
        .message.error {
            background: #ffe6e3;
            color: #9a1d13;
        }
        label {
            display: block;
            margin: 14px 0 6px;
            font-size: 13px;
            font-weight: 700;
        }
        .reset-form input {
            width: 100%;
            height: 42px;
            padding: 0 12px;
            border: 1px solid #ccd1d8;
            border-radius: 8px;
            font: inherit;
        }
        .reset-form button {
            display: block;
            width: min(100%, 72%);
            margin: 18px auto 0;
            height: 44px;
            border: 0;
            border-radius: 8px;
            background: #ff7a00;
            color: #fff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        .lang-switch {
            text-align: right;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .lang-switch a {
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            opacity: .55;
        }
        .lang-switch a.active {
            opacity: 1;
            text-decoration: underline;
        }
        .back {
            display: inline-block;
            margin-top: 16px;
            color: #ff7a00;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            <div class="lang-switch">
                <a href="#" data-set-lang="ms">BM</a> | <a href="#" data-set-lang="en">EN</a>
            </div>
            <h1 data-i18n="forgotHeading">Lupa Kata Laluan</h1>
            <p class="help" data-i18n="forgotHelp">Masukkan emel anda dan kata laluan baharu. Akaun itu akan ditanda sebagai mempunyai masalah kata laluan supaya admin boleh nampak amaran tersebut.</p>

            <?php if ($message !== ''): ?>
                <div class="message <?php echo e($messageType); ?>"<?php echo $messageKey !== '' ? ' data-i18n="' . e($messageKey) . '"' : ''; ?>><?php echo e($message); ?></div>
            <?php endif; ?>

            <form class="reset-form" method="POST">
                <label for="email" data-i18n="forgotEmail">Emel</label>
                <input id="email" type="email" name="email" value="<?php echo e($prefillEmail); ?>" required>

                <label for="new_password" data-i18n="forgotNewPassword">Kata laluan baharu</label>
                <input id="new_password" type="password" name="new_password" required>

                <button type="submit" name="reset" data-i18n="forgotSubmit">Tetapkan Semula Kata Laluan</button>
            </form>

            <a class="back" href="login.php?forgot=1" data-i18n="forgotBack">Kembali ke Log Masuk</a>
        </section>
    </main>
    <script src="js/site-i18n.js?v=8"></script>
    <script>
        (function () {
            var links = document.querySelectorAll("[data-set-lang]");
            function mark() {
                var cur = window.__SITE_LANG__ || "ms";
                links.forEach(function (a) {
                    a.classList.toggle("active", a.getAttribute("data-set-lang") === cur);
                });
            }
            links.forEach(function (a) {
                a.addEventListener("click", function (e) {
                    e.preventDefault();
                    window.applySiteLanguage(a.getAttribute("data-set-lang"));
                });
            });
            document.addEventListener("site-lang-change", mark);
            mark();
        })();
    </script>
</body>
</html>
