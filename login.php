<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/partials/site_return.php';

$loginError = $_GET['error'] ?? '';
$forgot = isset($_GET['forgot']);
$returnUrl = site_safe_return_url((string) ($_GET['return'] ?? ''));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="images/favicon-192.png">
    <link rel="apple-touch-icon" href="images/apple-touch-icon.png">
    <title data-i18n="pageTitleLogin">Log In — Pendaftaran Produk Haiwan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --orange: #ff8f1f;
            --orange-deep: #ff7a00;
            --ink: #111111;
            --muted: #c8c8c8;
            --page: #f4f5f7;
            --hero: #f6f7f9;
            --white: #ffffff;
            --line: #eef0f3;
            --soft: #f6f7f9;
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
            overflow: hidden;
        }

        .page-title {
            margin: 0;
            font-size: 32px;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .hero-label {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 16px;
            font-weight: 600;
        }

        .hero-shell {
            position: relative;
            min-height: 505px;
            margin-top: -7px;
            background: var(--hero);
            overflow: hidden;
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
            padding: 0 18px;
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
            min-width: 0;
            padding: 0;
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
            letter-spacing: 0;
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
            align-items: center;
            justify-content: flex-end;
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

        .hero-content {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            width: min(860px, calc(100% - 38px));
            height: 394px;
            margin: -3px auto 0;
        }

        .hero-copy {
            position: relative;
            z-index: 4;
            align-self: center;
            max-width: 330px;
            padding-top: 42px;
            margin-left: 0;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: var(--orange-deep);
            font-size: 11px;
            font-weight: 800;
        }

        .hero-copy h2 {
            margin: 0;
            font-size: 14px;
            line-height: 1.45;
            font-weight: 800;
        }

        .hero-copy p:last-child {
            margin: 33px 0 0;
            color: #3f444c;
            font-size: 11px;
            line-height: 1.7;
            font-weight: 500;
        }

        .accent-top,
        .accent-bottom {
            position: absolute;
            background: var(--orange);
        }

        .accent-top {
            top: 0;
            left: calc(50% - 275px);
            width: 118px;
            height: 120px;
            border-radius: 0 0 65px 65px;
        }

        .accent-bottom {
            bottom: 0;
            left: calc(50% - 205px);
            width: 112px;
            height: 58px;
            border-radius: 70px 70px 0 0;
        }

        .bird {
            position: absolute;
            top: 90px;
            left: 50%;
            width: 98px;
            height: 86px;
            border-radius: 50%;
            object-fit: cover;
            transform: translateX(-88px) rotate(-13deg);
            z-index: 3;
            mix-blend-mode: normal;
        }

        .hero-art {
            position: relative;
            align-self: end;
            height: 360px;
        }

        .hero-blob {
            position: absolute;
            right: -44px;
            bottom: 0;
            width: 585px;
            height: 375px;
            border-radius: 51% 49% 0 0 / 70% 72% 0 0;
            background: var(--orange);
            overflow: hidden;
            z-index: 1;
        }

        .hero-blob::before {
            content: "";
            position: absolute;
            inset: 42px 92px -22px 92px;
            border-radius: 50% 50% 0 0;
            background: rgba(255, 130, 21, .92);
        }

        .hero-pets {
            position: absolute;
            right: 24px;
            bottom: 0;
            display: grid;
            grid-template-columns: 170px 250px;
            align-items: end;
            gap: 0;
            width: 440px;
            z-index: 2;
        }

        .hero-pets img {
            width: 100%;
            height: 290px;
            object-fit: cover;
            object-position: center;
            mix-blend-mode: normal;
        }

        .hero-pets .cat {
            height: 370px;
            margin-left: -52px;
            object-fit: contain;
            object-position: center bottom;
        }

        .hero-pets .dog {
            margin-right: -8px;
            height: 285px;
            object-fit: contain;
            object-position: center bottom;
        }

        .hero-pets .hamster {
            position: absolute;
            left: 205px;
            bottom: 0;
            width: 86px;
            height: 102px;
            z-index: 4;
            object-fit: contain;
            object-position: center bottom;
        }

        .login-card {
            position: absolute;
            top: 70px;
            right: 78px;
            z-index: 8;
            left: auto;
            transform: none;
            width: 410px;
            min-height: 230px;
            padding: 24px 28px 20px 24px;
            background: rgba(255, 255, 255, .9);
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(16, 24, 40, .06);
        }

        .form-row {
            display: grid;
            grid-template-columns: 128px 1fr;
            align-items: center;
            gap: 10px;
            margin-bottom: 11px;
            font-size: 16px;
            font-weight: 500;
            color: #222;
        }

        .form-row span {
            white-space: nowrap;
            font-size: 13px;
            font-weight: 600;
        }

        .form-row input {
            height: 30px;
            border: 1px solid #222;
            border-radius: 7px;
            background: #fff;
            font-family: inherit;
            font-size: 13px;
            padding: 0 10px;
        }

        .password-field {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-field input {
            width: 100%;
            padding-right: 42px;
        }

        .password-toggle {
            position: absolute;
            right: 5px;
            top: 50%;
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            transform: translateY(-50%);
            border: 1px solid #111;
            border-radius: 50%;
            background: #111;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
        }

        .password-toggle i {
            font-size: 13px;
            line-height: 1;
        }

        .forgot,
        .register-link {
            color: #ff6433;
        }

        .forgot {
            display: block;
            margin: -2px 0 12px 104px;
            font-size: 13px;
        }

        .login-button {
            display: block;
            width: 150px;
            height: 33px;
            margin: 0 0 7px 104px;
            border: 0;
            border-radius: 6px;
            background: var(--orange-deep);
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .login-note {
            margin-left: 100px;
            font-size: 13px;
            white-space: nowrap;
        }

        .login-error {
            margin: 0 0 10px 104px;
            color: #9a1d13;
            font-size: 12px;
            font-weight: 700;
        }

        .login-help {
            margin: 0 0 10px 104px;
            max-width: 245px;
            padding: 10px 12px;
            border: 3px solid #ef2d24;
            border-radius: 2px;
            background: rgba(255, 255, 255, .92);
            color: #6c4b47;
            font-size: 11px;
            line-height: 1.35;
            font-weight: 800;
        }

        .forgot-reset-link {
            display: inline-block;
            margin-top: 6px;
            color: #ff7a00;
            font-size: 11px;
            font-weight: 800;
            text-decoration: underline;
        }

        @media (max-width: 1120px) {
            .hero-content {
                width: min(860px, calc(100% - 34px));
            }

            .hero-content {
                min-height: 650px;
            }

            .login-card {
                left: 50%;
                top: 255px;
                transform: translateX(-50%);
            }

            .hero-blob {
                right: -44px;
            }

            .hero-pets {
                right: 34px;
            }
        }

        @media (max-width: 760px) {
            .page {
                padding: 0;
            }

            .page-title {
                font-size: 25px;
            }

            .topbar {
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .nav {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 15px;
            }

            .nav-tools {
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .search {
                width: 100%;
            }

            .hero-copy {
                width: 100%;
                padding-top: 65px;
            }

            .login-card {
                width: calc(100% - 20px);
                padding: 28px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .forgot,
            .login-button,
            .login-note,
            .login-error,
            .login-help {
                margin-left: 0;
            }

            .login-note {
                white-space: normal;
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
                        <input type="hidden" name="shop" value="1">
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
                    <span class="auth"><a class="active" href="login.php" data-i18n="loginLink">Log Masuk</a> | <a href="register.php" data-i18n="registerLink">Daftar</a></span>
                </div>
            </nav>
        </div>

        <section class="hero-shell" aria-label="Log masuk">
            <div class="hero-content">
                <span class="accent-top"></span>
                <span class="accent-bottom"></span>
                <img class="bird" src="images/bird.png" alt="Bird">

                <div class="hero-copy">
                    <p class="eyebrow" data-i18n="loginEyebrow">Pendaftaran Produk Haiwan</p>
                    <h2 data-i18n="loginHeroTitle">Kedai haiwan ini menyediakan keperluan lengkap untuk kucing, burung dan haiwan kecil.</h2>
                    <p data-i18n="loginHeroDesc">Kami menawarkan pelbagai produk seperti makanan haiwan, sangkar, suplemen dan aksesori dengan harga berpatutan serta kualiti terjamin.</p>
                </div>

                <div class="hero-art" aria-hidden="true">
                    <div class="hero-blob"></div>
                    <div class="hero-pets">
                        <img class="dog" src="images/dog.png" alt="">
                        <img class="cat" src="images/hero.png" alt="">
                        <img class="hamster" src="images/hamster.png" alt="">
                    </div>
                </div>

                <form class="login-card" action="process_login.php" method="POST" autocomplete="off">
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($loginError === 'failed' || $loginError === 'missing'): ?>
                        <div class="login-error" data-i18n="loginInvalid">Emel atau kata laluan tidak sah.</div>
                    <?php endif; ?>
                    <?php if ($forgot): ?>
                        <div class="login-help">
                            <span data-i18n="loginForgotHelp">Sila hubungi pentadbir kedai untuk menetapkan semula kata laluan.</span>
                            <a class="forgot-reset-link" href="forgot_password.php" data-i18n="loginForgotResetLink">Reset sekarang</a>
                        </div>
                    <?php endif; ?>

                    <label class="form-row">
                        <span data-i18n="loginEmailLabel">Alamat emel</span>
                        <input type="email" name="login_email" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" required>
                    </label>
                    <label class="form-row">
                        <span data-i18n="loginPasswordLabel">Kata laluan</span>
                        <span class="password-field">
                            <input id="loginPassword" type="password" name="login_password" autocomplete="new-password" required>
                            <button class="password-toggle" type="button" id="loginPasswordToggle" aria-label="Tunjuk kata laluan" aria-pressed="false">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </span>
                    </label>
                    <a class="forgot" href="login.php?forgot=1" data-i18n="loginForgot">Lupa kata laluan?</a>
                    <button class="login-button" type="submit" data-i18n="loginSubmit">Log Masuk</button>
                    <div class="login-note"><span data-i18n="loginNoAccount">Tiada akaun?</span> <a class="register-link" href="register.php" data-i18n="registerLink">Daftar</a></div>
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
            const loginEmail = document.querySelector('input[name="login_email"]');
            const loginPassword = document.getElementById('loginPassword');

            if (loginEmail) {
                loginEmail.value = '';
                loginEmail.setAttribute('autocomplete', 'off');
            }
            if (loginPassword) {
                loginPassword.value = '';
                loginPassword.setAttribute('autocomplete', 'new-password');
            }

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
        (function () {
            const password = document.getElementById('loginPassword');
            const toggle = document.getElementById('loginPasswordToggle');
            if (!password || !toggle) return;

            toggle.addEventListener('click', function () {
                const showing = password.type === 'text';
                password.type = showing ? 'password' : 'text';
                toggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
                toggle.setAttribute('aria-label', showing ? 'Tunjuk kata laluan' : 'Sembunyi kata laluan');
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', showing);
                    icon.classList.toggle('fa-eye-slash', !showing);
                }
            });
        })();
    </script>
</body>
</html>
