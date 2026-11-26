<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/config/app.php';
app_session_start();
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/partials/site_return.php';

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

function resolve_product_image_url(string $stored): string {
    $stored = trim($stored);
    if ($stored === '') {
        return '';
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
        $path = $c['file'];
        if ($path !== '' && is_file($path)) {
            return $c['url'];
        }
    }

    return '';
}

function category_synonyms(string $key): array {
    $groups = [
        ['Accessories', 'Aksesori'],
        ['Food Animals', 'Food animals', 'Makanan Haiwan'],
        ['Supplements', 'Suplemen'],
        ['Cage', 'Sangkar'],
    ];

    foreach ($groups as $synonyms) {
        foreach ($synonyms as $s) {
            if (strcasecmp(trim($key), trim($s)) === 0) {
                return $synonyms;
            }
        }
    }

    return [trim($key)];
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

$products = [];
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$animalFilter = trim($_GET['animal'] ?? '');
$sort = $_GET['sort'] ?? 'latest';
$columns = table_columns($conn);
$idColumn = null;
$nameColumn = null;
$priceColumn = null;
$categoryColumn = null;
$animalColumn = null;
$imageColumn = null;

if (!empty($columns)) {
    $idColumn = pick_column($columns, ['id_produk', 'id', 'produk_id']);
    $nameColumn = pick_column($columns, ['nama_produk', 'name']);
    $priceColumn = pick_column($columns, ['harga(RM)', 'harga_rm', 'harga', 'price']);
    $categoryColumn = pick_column($columns, ['category', 'kategori']);
    $animalColumn = pick_column($columns, ['jenis_haiwan', 'animal', 'haiwan']);
    $imageColumn = pick_column($columns, ['gambar_produk', 'image', 'gambar']);

    $conditions = [];
    if ($search !== '' && $nameColumn) {
        $safeSearch = $conn->real_escape_string($search);
        $conditions[] = "`$nameColumn` LIKE '%$safeSearch%'";
    }

    if ($categoryFilter !== '' && $categoryColumn) {
        $synonyms = category_synonyms($categoryFilter);
        $escaped = [];
        foreach ($synonyms as $syn) {
            $escaped[] = "'" . $conn->real_escape_string($syn) . "'";
        }
        $conditions[] = "`$categoryColumn` IN (" . implode(',', $escaped) . ")";
    }

    if ($animalFilter !== '' && $animalColumn) {
        $safeAnimal = $conn->real_escape_string($animalFilter);
        $conditions[] = "`$animalColumn` LIKE '%$safeAnimal%'";
    }

    $where = $conditions ? " WHERE " . implode(" AND ", $conditions) : "";
    $order = "";

    if ($sort === 'price' && $priceColumn) {
        $order = " ORDER BY `$priceColumn` ASC";
    } elseif ($sort === 'name' && $nameColumn) {
        $order = " ORDER BY `$nameColumn` ASC";
    } elseif ($idColumn) {
        if (db_column_bind_type_for_id($conn, 'products', $idColumn) === 's') {
            $order = " ORDER BY CAST(SUBSTRING(`$idColumn`, 2) AS UNSIGNED) DESC";
        } else {
            $order = " ORDER BY `$idColumn` DESC";
        }
    }

    $result = $conn->query("SELECT * FROM products" . $where . $order . " LIMIT 200");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rawPid = first_value($row, $idColumn ? [$idColumn] : [], '0');
            $id = product_display_kod($row, $rawPid);
            $name = first_value($row, $nameColumn ? [$nameColumn] : [], '');
            $price = first_value($row, $priceColumn ? [$priceColumn] : [], '0');
            $category = first_value($row, $categoryColumn ? [$categoryColumn] : [], '');
            $animal = first_value($row, $animalColumn ? [$animalColumn] : [], '');
            $rawImage = first_value($row, $imageColumn ? [$imageColumn] : [], '');
            $image = resolve_product_image_url((string) $rawImage);
            if ($image === '' && is_file(__DIR__ . '/images/logo.png')) {
                $image = 'images/logo.png';
            }

            $products[] = compact('id', 'name', 'price', 'category', 'animal', 'image');
        }
    }
}

$categories = [
    [
        'name' => 'Aksesori',
        'filter' => 'Accessories',
        'count' => 0,
        'image' => 'images/kategori1.jpg',
    ],
    [
        'name' => 'Makanan Haiwan',
        'filter' => 'Food Animals',
        'count' => 0,
        'image' => 'images/kategori2.jpg',
    ],
    [
        'name' => 'Sangkar',
        'filter' => 'Cage',
        'count' => 0,
        'image' => 'images/kategori3.jpg',
    ],
    [
        'name' => 'Suplemen',
        'filter' => 'Supplements',
        'count' => 0,
        'image' => 'images/kategori4.jpg',
    ],
];

if ($categoryColumn) {
    foreach ($categories as $i => $cat) {
        $syns = category_synonyms($cat['filter']);
        $escaped = [];
        foreach ($syns as $syn) {
            $escaped[] = "'" . $conn->real_escape_string($syn) . "'";
        }
        $sql = "SELECT COUNT(*) AS c FROM products WHERE `$categoryColumn` IN (" . implode(',', $escaped) . ")";
        $countRes = $conn->query($sql);
        $categories[$i]['count'] = ($countRes && ($cr = $countRes->fetch_assoc())) ? (int) ($cr['c'] ?? 0) : 0;
    }
}

$pets = [
    [
        'name' => 'Kucing',
        'filter' => 'Cat',
        'image' => 'images/hero.png',
        'accent' => false,
    ],
    [
        'name' => 'Hamster',
        'filter' => 'Hamster',
        'image' => 'images/hamster.png',
        'accent' => false,
    ],
    [
        'name' => 'Anjing',
        'filter' => 'Dog',
        'image' => 'images/dog.png',
        'accent' => false,
    ],
    [
        'name' => 'Burung',
        'filter' => 'Bird',
        'image' => 'images/bird.png',
        'accent' => false,
    ],
];
$isLoggedIn = !empty($_SESSION['user']);
$userEmail = '';
if ($isLoggedIn) {
    $userEmail = (string) ($_SESSION['user']['email'] ?? $_SESSION['user']['emel'] ?? '');
}

$isShopPage = !empty($_GET['shop']) || $search !== '' || $categoryFilter !== '' || $animalFilter !== '';

if ($isShopPage && !$isLoggedIn) {
    $returnQuery = array_filter([
        'shop' => '1',
        'search' => $search !== '' ? $search : null,
        'category' => $categoryFilter !== '' ? $categoryFilter : null,
        'animal' => $animalFilter !== '' ? $animalFilter : null,
        'sort' => ($sort !== '' && $sort !== 'latest') ? $sort : null,
    ]);
    $returnUrl = 'index.php' . ($returnQuery ? '?' . http_build_query($returnQuery) : '?shop=1') . '#produk';
    header('Location: login.php?return=' . urlencode($returnUrl));
    exit;
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
    <title data-i18n="pageTitleHome">Pendaftaran Produk Haiwan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --orange: #ff8f1f;
            --orange-deep: #ff7a00;
            --ink: #111111;
            --muted: #777f89;
            --line: #eef0f3;
            --soft: #f6f7f9;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f5f7;
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
            padding-top: 0;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0;
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

        .brand-mark {
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border-radius: 50%;
            color: var(--orange-deep);
            background: #fff4e9;
            font-size: 12px;
            font-weight: 800;
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

        .search button,
        .icon-button {
            display: grid;
            width: 24px;
            height: 24px;
            place-items: center;
            border: 0;
            border-radius: 50%;
            color: var(--white);
            background: #050505;
            font-size: 12px;
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

        .auth a,
        .user-email-logout {
            color: var(--ink);
        }

        .user-email-logout {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .auth a {
            color: var(--ink);
        }

        .translate-wrap.open .translate,
        .auth a:hover,
        .auth a:focus-visible,
        .user-email-logout:hover,
        .user-email-logout:focus-visible,
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

        .hero {
            position: relative;
            min-height: 394px;
            margin-top: -7px;
            background: #f6f7f9;
        }

        .hero-inner {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            width: min(860px, calc(100% - 38px));
            height: 394px;
            margin: 0 auto;
        }

        .hero-copy {
            align-self: center;
            max-width: 330px;
            padding-top: 42px;
        }

        .eyebrow {
            margin: 0 0 9px;
            color: var(--orange-deep);
            font-size: 11px;
            font-weight: 800;
        }

        .hero h1 {
            margin: 0;
            max-width: 310px;
            font-size: 20px;
            line-height: 1.28;
            font-weight: 800;
        }

        .hero p {
            margin: 36px 0 0;
            max-width: 315px;
            color: #42474e;
            font-size: 11px;
            line-height: 1.65;
            font-weight: 500;
        }

        .hero-art {
            position: relative;
            align-self: end;
            height: 330px;
        }

        .hero-blob {
            position: absolute;
            right: -44px;
            bottom: 0;
            width: 535px;
            height: 345px;
            border-radius: 51% 49% 0 0 / 70% 72% 0 0;
            background: var(--orange);
            overflow: hidden;
        }

        .hero-blob::before {
            content: "";
            position: absolute;
            inset: 38px 82px -20px 82px;
            border-radius: 50% 50% 0 0;
            background: rgba(255, 130, 21, .92);
        }

        .hero-pets {
            position: absolute;
            right: 34px;
            bottom: 0;
            display: grid;
            grid-template-columns: 150px 220px;
            align-items: end;
            gap: 0;
            width: 390px;
            z-index: 2;
        }

        .hero-pets img {
            width: 100%;
            height: 260px;
            object-fit: cover;
            object-position: center;
            mix-blend-mode: normal;
        }

        .hero-pets .cat {
            height: 330px;
            margin-left: -44px; /* tarik cat lebih overlap */
        }

        .hero-pets .hamster {
            position: absolute;
            left: 175px; /* hamster lebih dekat tengah */
            bottom: 0;
            width: 74px;
            height: 88px;
            border-radius: 50%;
            z-index: 4; /* pastikan hamster di depan kucing & bentuk oren */
        }

        .hero-pets .dog {
            margin-right: -6px; /* bagi ruang untuk overlap cat */
            height: 255px;
            object-fit: contain;
            object-position: center bottom;
        }

        .hero-pets .hamster {
            object-fit: contain;
            object-position: center bottom;
            mix-blend-mode: normal;
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

        .accent-shape {
            position: absolute;
            border-radius: 0 0 65px 65px;
            background: var(--orange);
        }

        .accent-top {
            top: 0;
            left: calc(50% - 275px);
            width: 118px;
            height: 120px;
        }

        .accent-bottom {
            bottom: 0;
            left: calc(50% - 205px);
            width: 112px;
            height: 58px;
            border-radius: 70px 70px 0 0;
        }

        .section {
            background: var(--white);
        }

        .section-inner {
            width: min(860px, calc(100% - 38px));
            margin: 0 auto;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 33px;
        }

        .section-title {
            margin: 0;
            font-size: 28px;
            line-height: 1.15;
            font-weight: 800;
        }

        .arrows {
            display: flex;
            gap: 26px;
        }

        .arrow {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border-radius: 50%;
            color: var(--white);
            background: #000;
            font-size: 18px;
            font-weight: 800;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            padding: 43px 0 52px;
        }

        .category-card {
            overflow: hidden;
            border-radius: 10px;
            background: #f7f8fa;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .05);
        }

        .category-card img {
            width: 100%;
            aspect-ratio: 1.45;
            object-fit: cover;
        }

        .category-body {
            display: grid;
            grid-template-columns: 1fr 22px;
            gap: 8px;
            padding: 12px 13px 14px;
        }

        .category-body h3 {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 800;
        }

        .category-body p {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            font-weight: 500;
        }

        .category-body .go {
            align-self: center;
            color: var(--orange-deep);
            font-size: 18px;
            font-weight: 800;
        }

        .pets-section {
            padding: 38px 0 0;
        }

        .shop-section {
            padding: 48px 0 68px;
        }

        .pet-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 70px;
            padding: 39px 8px 68px;
        }

        .pet {
            display: grid;
            justify-items: center;
            gap: 17px;
            font-weight: 800;
            font-size: 14px;
        }

        .pet-portrait {
            position: relative;
            display: grid;
            width: 112px;
            height: 112px;
            place-items: center;
            border-radius: 50%;
            background: #f6f7f9;
            overflow: hidden;
        }

        .pet-portrait.accent {
            background: var(--orange);
        }

        .pet-portrait img {
            width: 103px;
            height: 103px;
            object-fit: cover;
            border-radius: 50%;
            mix-blend-mode: normal;
        }

        .product-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: min(640px, 100%);
            margin: 0 auto 38px;
            color: #7a8390;
            font-size: 13px;
            font-weight: 600;
        }

        .sort {
            height: 27px;
            border: 1px solid #8f98a3;
            border-radius: 3px;
            background: #fff;
            color: #65707d;
            font-family: inherit;
            font-size: 13px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            width: min(640px, 100%);
            margin: 0 auto;
            padding-bottom: 70px;
        }

        .product-empty {
            width: min(640px, 100%);
            margin: 12px auto 70px;
            padding: 18px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--white);
            color: #65707d;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
        }

        .product-card {
            overflow: hidden;
            min-height: 260px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--white);
        }

        .product-card img {
            width: 100%;
            aspect-ratio: 1.18;
            object-fit: cover;
            background: #fafafa;
        }

        .product-info {
            display: grid;
            grid-template-columns: 1fr 24px;
            gap: 8px;
            padding: 13px 13px 14px;
        }

        .product-info h3 {
            margin: 0 0 6px;
            font-size: 13px;
            line-height: 1.2;
            font-weight: 800;
        }

        .product-info p {
            margin: 0;
            color: #101010;
            font-size: 10px;
            font-weight: 500;
        }

        .info-icon {
            display: grid;
            width: 19px;
            height: 19px;
            place-items: center;
            align-self: start;
            border: 1px solid #111;
            border-radius: 50%;
            font-size: 12px;
            font-weight: 800;
        }

        @media (max-width: 900px) {
            .topbar {
                flex-wrap: wrap;
                gap: 10px 18px;
            }

            .nav {
                grid-template-columns: 1fr;
                gap: 14px;
                padding: 18px;
            }

            .nav-links,
            .nav-tools {
                justify-content: flex-start;
            }

            .hero-inner {
                grid-template-columns: 1fr;
                height: auto;
                min-height: 650px;
            }

            .hero-copy {
                padding: 76px 0 0;
            }

            .hero-art {
                min-height: 330px;
            }

            .hero-blob {
                right: 50%;
                transform: translateX(50%);
            }

            .category-grid,
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .pet-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 34px;
            }
        }

        @media (max-width: 560px) {
            .topbar {
                align-items: flex-start;
                justify-content: flex-start;
                padding-left: 18px;
            }

            .nav-links {
                gap: 24px;
            }

            .nav-tools {
                flex-wrap: wrap;
            }

            .search {
                width: 100%;
            }

            .hero h1 {
                font-size: 18px;
            }

            .hero-pets {
                right: auto;
                left: 50%;
                width: 330px;
                transform: translateX(-50%);
            }

            .hero-blob {
                width: 390px;
            }

            .category-grid,
            .product-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 24px;
            }

            .product-toolbar {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <main class="page<?php echo $isShopPage ? ' products-page' : ''; ?>">
        <div class="topbar">
            <span>📞 +60 10-211 9319 / +60 10-381 1946</span>
            <span>✉️ petseberangjaya@gmail.com</span>
            <span>📍 No 63A, Lorong Lampam Seberang Jaya, 13700 Perai, Pulau Pinang</span>
        </div>

        <div class="nav-wrap">
            <nav class="nav">
                <a class="brand" href="index.php">
                    <img class="logo" src="images/logo.png" alt="Logo">
                    <span>PENDAFTARAN PRODUK HAIWAN</span>
                </a>

                <div class="nav-links">
                    <a class="<?php echo !$isShopPage ? 'active' : ''; ?>" href="index.php" data-i18n="navUtama">Utama</a>
                    <a class="<?php echo $isShopPage ? 'active' : ''; ?>" href="index.php?shop=1#produk" data-i18n="navKedai">Kedai</a>
                </div>

                <div class="nav-tools">
                    <form class="search" method="GET" action="index.php">
                        <input type="hidden" name="shop" value="1">
                        <input type="text" name="search" value="<?php echo e($search); ?>" data-i18n-placeholder="searchPlaceholder" data-i18n-aria-label="pdSearchAria" placeholder="Cari produk…" aria-label="Cari produk">
                        <?php if ($categoryFilter !== ''): ?>
                            <input type="hidden" name="category" value="<?php echo e($categoryFilter); ?>">
                        <?php endif; ?>
                        <?php if ($animalFilter !== ''): ?>
                            <input type="hidden" name="animal" value="<?php echo e($animalFilter); ?>">
                        <?php endif; ?>
                        <button type="submit" data-i18n-aria-label="pdSubmitSearchAria" aria-label="Hantar carian"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                    <button type="button" class="cart-btn" id="cartBtn" data-i18n-aria-label="pdCartAria" aria-label="Troli">
                        <span class="cart" aria-hidden="true"><i class="fa-solid fa-cart-shopping"></i></span>
                    </button>

                    <div class="translate-wrap" id="translateWrap">
                        <button
                            type="button"
                            class="translate"
                            id="translateToggle"
                            aria-haspopup="menu"
                            aria-expanded="false"
                        >
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
        </div>

        <?php if (!$isShopPage): ?>
        <section class="hero" aria-label="Pengenalan">
            <span class="accent-shape accent-top"></span>
            <span class="accent-shape accent-bottom"></span>
           <img class="bird" src="images/bird.png" alt="Bird">
            <div class="hero-inner">
                <div class="hero-copy">
                    <p class="eyebrow" data-i18n="heroEyebrow">Pendaftaran Produk Haiwan</p>
                    <h1 data-i18n="heroTitle">Kedai haiwan ini menyediakan keperluan lengkap untuk kucing, burung dan haiwan kecil.</h1>
                    <p data-i18n="heroDesc">Kami menawarkan pelbagai produk seperti makanan haiwan, sangkar, suplemen dan aksesori dengan harga berpatutan serta kualiti terjamin.</p>
                </div>

                <div class="hero-art" aria-hidden="true">
                    <div class="hero-blob"></div>
                    <div class="hero-pets">
                        <img class="dog" src="images/dog.png" alt="Anjing">
                        <img class="cat" src="images/hero.png" alt="Kucing">
                        <img class="hamster" src="images/hamster.png" alt="Hamster">
                    </div>
                </div>
            </div>
        </section>

        <section class="section" aria-label="Kategori">
            <div class="section-inner">
                <div class="section-head">
                    <h2 class="section-title" data-i18n="categoryTitle">Kategori</h2>
                    <div class="arrows" aria-hidden="true">
                        <span class="arrow"><i class="fa-solid fa-chevron-left"></i></span>
                        <span class="arrow"><i class="fa-solid fa-chevron-right"></i></span>
                    </div>
                </div>

                <div class="category-grid">
                    <?php foreach ($categories as $category): ?>
                        <a class="category-card" href="index.php?shop=1&amp;category=<?php echo urlencode($category['filter']); ?><?php echo $animalFilter !== '' ? '&amp;animal=' . urlencode($animalFilter) : ''; ?>#produk">
                            <img src="<?php echo e($category['image']); ?>" alt="<?php echo e($category['name']); ?>">
                            <div class="category-body">
                                <div>
                                    <h3 data-category-key="<?php echo strtolower($category['filter']); ?>"><?php echo e($category['name']); ?></h3>
                                    <p><span><?php echo e($category['count']); ?></span> <span data-i18n="categoryProductsSuffix">produk</span></p>
                                </div>
                                <span class="go">&rsaquo;</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section pets-section" aria-label="Maklumat haiwan peliharaan">
            <div class="section-inner">
                <div class="section-head">
                    <h2 class="section-title" data-i18n="petInfoTitle">Maklumat mengikut haiwan peliharaan</h2>
                    <div class="arrows" aria-hidden="true">
                        <span class="arrow">&lsaquo;</span>
                        <span class="arrow">&rsaquo;</span>
                    </div>
                </div>

                <div class="pet-row">
                    <?php foreach ($pets as $pet): ?>
                        <a class="pet" href="index.php?shop=1&amp;animal=<?php echo urlencode($pet['filter']); ?>#produk">
                            <div class="pet-portrait<?php echo $pet['accent'] ? ' accent' : ''; ?>">
                                <img src="<?php echo e($pet['image']); ?>" alt="<?php echo e($pet['name']); ?>">
                            </div>
                            <span data-pet-key="<?php echo strtolower($pet['filter']); ?>"><?php echo e($pet['name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="section shop-section" id="produk" aria-label="Kedai">
            <div class="section-inner">
                <form class="product-toolbar" method="GET" action="index.php">
                    <input type="hidden" name="shop" value="1">
                    <span id="toolbarResults" data-count="<?php echo count($products); ?>">Menunjukkan <?php echo count($products); ?> hasil</span>
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="search" value="<?php echo e($search); ?>">
                    <?php endif; ?>
                    <?php if ($categoryFilter !== ''): ?>
                        <input type="hidden" name="category" value="<?php echo e($categoryFilter); ?>">
                    <?php endif; ?>
                    <?php if ($animalFilter !== ''): ?>
                        <input type="hidden" name="animal" value="<?php echo e($animalFilter); ?>">
                    <?php endif; ?>
                    <select class="sort" name="sort" data-i18n-aria-label="sortProductsAria" aria-label="Susun produk" onchange="this.form.submit()">
                        <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?> data-i18n="sortLatest">Susun mengikut terbaharu</option>
                        <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?> data-i18n="sortPrice">Susun mengikut harga</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?> data-i18n="sortName">Susun mengikut nama</option>
                    </select>
                </form>

                <?php if (empty($products)): ?>
                    <p class="product-empty" data-i18n="productEmpty">Tiada produk untuk dipaparkan.</p>
                <?php else: ?>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                            <article class="product-card">
                                <a href="product_detail.php?id=<?php echo e($product['id']); ?>">
                                    <img src="<?php echo e($product['image']); ?>" alt="<?php echo e($product['name']); ?>">
                                </a>
                                <div class="product-info">
                                    <div>
                                        <h3><?php echo e($product['name']); ?></h3>
                                        <p>RM<?php echo e($product['price']); ?></p>
                                    </div>
                                    <a class="info-icon" href="product_detail.php?id=<?php echo e($product['id']); ?>#details" data-i18n-aria-label="productDetailsLinkAria" aria-label="Lihat butiran produk">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
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
