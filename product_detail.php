<?php
include __DIR__ . '/config/app.php';
app_session_start();
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/partials/site_return.php';

$isLoggedIn = !empty($_SESSION['user']);
$userEmail = '';
if ($isLoggedIn) {
    $userEmail = (string) ($_SESSION['user']['email'] ?? $_SESSION['user']['emel'] ?? '');
}

$idRaw = trim((string) ($_GET['id'] ?? ''));

if (!$isLoggedIn) {
    $returnUrl = 'product_detail.php?id=' . urlencode($idRaw !== '' ? $idRaw : '');
    header('Location: login.php?return=' . urlencode($returnUrl));
    exit;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function first_value($row, $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '') {
            return $row[$key];
        }
    }

    return $default;
}

function table_columns($conn) {
    return db_table_columns($conn, 'products');
}

function pick_column($columns, $options) {
    foreach ($options as $option) {
        if (in_array($option, $columns, true)) {
            return $option;
        }
    }

    return null;
}

function product_image($stored) {
    $stored = trim((string) $stored);

    if ($stored === '') {
        return is_file(__DIR__ . '/images/logo.png')
            ? 'images/logo.png'
            : 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?auto=format&fit=crop&w=520&q=80';
    }

    if (preg_match('#^https?://#i', $stored)) {
        return $stored;
    }

    $stored = str_replace('\\', '/', $stored);
    $stored = ltrim($stored, './');
    $basename = basename($stored);

    $candidates = [
        ['file' => __DIR__ . '/uploads/' . $stored, 'url' => 'uploads/' . $stored],
        ['file' => __DIR__ . '/' . $stored, 'url' => $stored],
        ['file' => __DIR__ . '/uploads/' . $basename, 'url' => 'uploads/' . $basename],
        ['file' => __DIR__ . '/images/' . $basename, 'url' => 'images/' . $basename],
        ['file' => __DIR__ . '/images/' . $stored, 'url' => 'images/' . $stored],
    ];

    foreach ($candidates as $c) {
        if ($c['file'] !== '' && is_file($c['file'])) {
            return $c['url'];
        }
    }

    return is_file(__DIR__ . '/images/logo.png')
        ? 'images/logo.png'
        : 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?auto=format&fit=crop&w=520&q=80';
}

function detail_text($description, $name) {
    $description = trim((string) $description);

    if ($description !== '') {
        return $description;
    }

    return "Key Features :\n- Suitable for daily pet care and storage.\n- Durable product material for long term use.\n- Easy to clean and simple to maintain.\n- Comfortable design for cats, birds, hamsters or small animals.\n- Available through Pendaftaran Produk Haiwan.\n\nProduct note :\n- Please confirm size, color and stock before purchase.\n- Product image is for illustration if no uploaded image is available.\n- {$name} is listed with current shop stock information.";
}

$idRaw = trim((string) ($_GET['id'] ?? ''));
$product = null;

if ($idRaw !== '') {
    $columns = table_columns($conn);
    $idColumn = pick_column($columns, ['id_produk', 'id', 'produk_id']);

    if ($idColumn) {
        $idBindType = db_column_bind_type_for_id($conn, 'products', $idColumn);
        [$lookupType, $lookupVal] = products_normalize_pk_param($idRaw, $idBindType);
        $stmt = $conn->prepare("SELECT * FROM products WHERE `$idColumn` = ? LIMIT 1");

        if ($stmt) {
            if ($lookupType === 'i') {
                $lookupInt = (int) $lookupVal;
                $stmt->bind_param('i', $lookupInt);
            } else {
                $lookupStr = (string) $lookupVal;
                $stmt->bind_param('s', $lookupStr);
            }
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $rawPk = first_value($row, [$idColumn, 'id_produk', 'id', 'produk_id'], (string) $idRaw);
                $product = [
                    'id' => product_display_kod($row, $rawPk),
                    'name' => first_value($row, ['nama_produk', 'name'], 'Product'),
                    'price' => first_value($row, ['harga(RM)', 'harga_rm', 'harga', 'price'], '0'),
                    'category' => first_value($row, ['category', 'kategori'], ''),
                    'image' => first_value($row, ['gambar_produk', 'image', 'gambar'], ''),
                    'description' => first_value($row, ['butiran_produk', 'description', 'deskripsi', 'keterangan', 'details'], ''),
                    'stock' => (int) first_value($row, ['stok', 'stock'], '0'),
                ];
            }
        }
    }
}

if (!$product) {
    http_response_code(404);
}

$stock = $product ? (int) $product['stock'] : 0;
$inStock = $stock > 0;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="images/favicon-192.png">
    <link rel="apple-touch-icon" href="images/apple-touch-icon.png">
    <title><?php echo $product ? e($product['name']) : 'Product'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --orange: #ff6b24;
            --orange-deep: #ff6b24;
            --ink: #070707;
            --muted: #737b86;
            --soft: #f5f6f8;
            --white: #ffffff;
            --line: #eef0f3;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f2f2f2;
            color: var(--ink);
            font-family: "Poppins", Arial, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img {
            display: block;
            max-width: 100%;
        }

        .page {
            min-height: 100vh;
            background: #f2f2f2;
        }

        .product-page-card {
            width: min(1240px, calc(100% - 24px));
            margin: 0 auto 24px;
            background: var(--white);
        }

        .product-hero {
            padding: 16px 20px 12px;
            background: transparent;
        }

        .product-main {
            padding: 8px 20px 28px;
            background: #fff;
        }

        .nav {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1.5fr;
            align-items: center;
            width: min(1240px, calc(100% - 20px));
            min-height: 68px;
            margin: 0 auto 10px;
            padding: 0 24px;
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .08);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 800;
            white-space: nowrap;
        }

        .brand .logo {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        .brand span {
            line-height: 1;
            font-size: 13px;
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
            color: #111;
        }

        .nav-links .active {
            color: var(--orange);
            border-color: var(--orange);
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
            min-width: 170px;
            height: 29px;
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
            background: #000;
            color: #fff;
            cursor: pointer;
        }

        .cart {
            position: relative;
            font-size: 16px;
        }

        .cart::after {
            content: "";
            position: absolute;
            top: 0;
            right: -3px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--orange);
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

        .translate-wrap.open .translate,
        .user-email-logout,
        .nav-links a:hover,
        .nav-links a:focus-visible,
        .user-email-logout:hover,
        .user-email-logout:focus-visible {
            color: var(--orange-deep);
        }

        .user-email-logout {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            color: var(--ink);
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

        .product-title {
            margin: 8px 0 6px;
            font-size: 42px;
            line-height: 1.1;
            font-weight: 600;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            margin-bottom: 10px;
            padding: 0 11px;
            border: 1px solid #111;
            border-radius: 4px;
            background: #fff;
            font-size: 12px;
            font-weight: 700;
        }

        .summary {
            display: grid;
            grid-template-columns: 190px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .gallery {
            width: 190px;
        }

        .product-image {
            width: 190px;
            height: 168px;
            object-fit: cover;
            background: #f7f7f7;
            border-radius: 10px;
        }


        .info {
            max-width: 560px;
            padding-top: 1px;
        }

        .info h1 {
            margin: 0 0 1px;
            font-size: 23px;
            line-height: 1.25;
            font-weight: 600;
        }

        .price {
            margin: 0;
            color: var(--orange);
            font-size: 22px;
            line-height: 1.2;
            font-weight: 500;
        }

        .meta-line {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            min-height: 28px;
            margin-top: 6px;
            font-size: 15px;
            line-height: 1.3;
            font-weight: 500;
            color: #111;
        }

        .meta-line strong {
            font-weight: 500;
            color: #111;
        }

        .meta-line .accent {
            color: var(--orange);
            font-weight: 500;
        }

        .qty-control {
            display: inline-flex;
            align-items: stretch;
            height: 24px;
            border: 1px solid var(--orange);
            border-radius: 2px;
            overflow: hidden;
            font-size: 13px;
        }

        .qty-control button,
        .qty-control span {
            display: grid;
            min-width: 26px;
            height: 100%;
            place-items: center;
            border: 0;
            background: #fff;
            font-family: inherit;
            line-height: 1;
            color: #111;
        }

        .qty-control button {
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .qty-control span {
            border-left: 1px solid var(--orange);
            border-right: 1px solid var(--orange);
            min-width: 28px;
        }

        .stock {
            color: <?php echo $inStock ? '#ff6b24' : '#ff1c2d'; ?>;
            font-size: 14px;
            font-weight: 500;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .chip {
            display: inline-grid;
            min-width: 70px;
            min-height: 25px;
            place-items: center;
            padding: 0 12px;
            border: 1px solid var(--orange);
            background: #fff;
            color: #000;
            font-size: 12px;
        }

        .chip.disabled {
            border-color: #e6e6e6;
            color: #d4d4d4;
        }

        .details-section {
            margin: 0;
            padding: 0;
            background: transparent;
        }

        .details-head {
            margin: 0;
            padding: 16px 24px 14px;
            background: #d5d5d9;
        }

        .details-head h2 {
            margin: 0;
            font-size: 32px;
            line-height: 1.2;
            font-weight: 600;
            color: #000;
        }

        .details-body {
            padding: 18px 24px 36px;
            background: #fff;
        }

        .details-box {
            max-width: 980px;
            min-height: 280px;
            font-size: 13px;
            line-height: 1.45;
            white-space: pre-line;
            color: #111;
        }

        .not-found {
            width: min(1240px, calc(100% - 24px));
            margin: 0 auto 24px;
            padding: 70px 40px;
            background: #fff;
            font-size: 24px;
            font-weight: 700;
        }

        @media (max-width: 760px) {
            .product-hero {
                padding: 18px;
            }

            .nav {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 14px;
            }

            .nav-links,
            .nav-tools {
                justify-content: flex-start;
            }

            .search {
                width: 100%;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .gallery,
            .product-image {
                width: 100%;
                height: auto;
                aspect-ratio: 1.2;
            }

            .meta-line {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="product-hero">
            <nav class="nav" aria-label="Navigasi utama">
                <a class="brand" href="index.php">
                    <img class="logo" src="images/logo.png" alt="Logo">
                    <span>PENDAFTARAN PRODUK HAIWAN</span>
                </a>

                <div class="nav-links">
                    <a href="index.php" data-i18n="pdNavUtama">Utama</a>
                    <a class="active" href="index.php?shop=1#produk" data-i18n="pdNavKedai">Kedai</a>
                </div>

                <div class="nav-tools">
                    <form class="search" action="index.php" method="GET">
                        <input type="hidden" name="shop" value="1">
                        <input type="text" name="search" data-i18n-placeholder="pdSearchPlaceholder" data-i18n-aria-label="pdSearchAria" placeholder="Cari produk…" aria-label="Cari produk">
                        <button type="submit" data-i18n-aria-label="pdSubmitSearchAria" aria-label="Hantar carian"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                    <span class="cart" data-i18n-aria-label="pdCartAria" aria-label="Troli"><i class="fa-solid fa-cart-shopping"></i></span>
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
                    <?php include __DIR__ . '/config/partials/site_auth.php'; ?>
                </div>
            </nav>
        </section>

        <?php if (!$product): ?>
            <div class="not-found" data-i18n="pdProductNotFound">Produk tidak dijumpai.</div>
        <?php else: ?>
        <div class="product-page-card">
            <section class="product-main">
                <a class="back-link" href="index.php?shop=1#produk" data-i18n="pdBackShop">&larr; Kembali ke Kedai</a>
                <h1 class="product-title" data-i18n="pdPageTitle">Produk</h1>

                <div class="summary">
                    <div class="gallery">
                        <img class="product-image" src="<?php echo e(product_image($product['image'])); ?>" alt="<?php echo e($product['name']); ?>">
                    </div>

                    <section class="info" aria-label="Product summary">
                        <h1><?php echo e($product['name']); ?></h1>
                        <?php $unitPrice = (float) $product['price']; ?>
                        <p class="price" id="priceText" data-unit-price="<?php echo e(number_format($unitPrice, 2, '.', '')); ?>">
                            RM<?php echo number_format($unitPrice, 2); ?>
                        </p>

                        <div class="meta-line">
                            <strong data-i18n="pdCategoryLabel">Kategori:</strong>
                            <span class="accent" data-category-code="<?php echo e($product['category']); ?>"><?php echo e($product['category']); ?></span>
                        </div>

                        <div class="meta-line">
                            <strong data-i18n="pdQtyLabel">Kuantiti:</strong>
                            <span class="qty-control" data-i18n-aria-label="qtyAria" aria-label="Kuantiti">
                                <button type="button" data-qty-minus>-</button>
                                <span id="qtyValue">1</span>
                                <button type="button" data-qty-plus>+</button>
                            </span>
                            <span class="stock stock-in" <?php echo $inStock ? '' : 'style="display:none"'; ?> data-i18n="pdStockIn">Ada stok</span>
                            <span class="stock stock-out" <?php echo $inStock ? 'style="display:none"' : ''; ?> data-i18n="pdStockOut">Kehabisan stok</span>
                        </div>
                    </section>
                </div>
            </section>

            <section class="details-section" id="details">
                <div class="details-head">
                    <h2 data-i18n="pdDetailsHeading">Butiran Produk</h2>
                </div>
                <div class="details-body">
                    <div class="details-box"><?php echo e(detail_text($product['description'], $product['name'])); ?></div>
                </div>
            </section>
        </div>
        <?php endif; ?>
    </main>
    <script src="js/site-i18n.js?v=8"></script>
    <?php if ($product): ?>
    <script>
        const qtyValue = document.getElementById('qtyValue');
        const maxStock = <?php echo max(1, $stock); ?>;
        const priceText = document.getElementById('priceText');
        const unitPrice = priceText ? Number(priceText.getAttribute('data-unit-price') || '0') : 0;

        function formatRm(value) {
            const n = Number.isFinite(value) ? value : 0;
            return 'RM' + n.toFixed(2);
        }

        function refreshTotalPrice() {
            if (!priceText) return;
            const qty = Math.max(1, Number(qtyValue?.textContent || '1'));
            priceText.textContent = formatRm(unitPrice * qty);
        }

        document.querySelector('[data-qty-minus]')?.addEventListener('click', () => {
            qtyValue.textContent = Math.max(1, Number(qtyValue.textContent) - 1);
            refreshTotalPrice();
        });
        document.querySelector('[data-qty-plus]')?.addEventListener('click', () => {
            qtyValue.textContent = Math.min(maxStock, Number(qtyValue.textContent) + 1);
            refreshTotalPrice();
        });

        refreshTotalPrice();
    </script>
    <?php endif; ?>
</body>
</html>
