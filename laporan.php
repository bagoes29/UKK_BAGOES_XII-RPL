
<?php
session_start();

require_once "config.php";

// ==================================
// CEK LOGIN DAN HAK AKSES
// ==================================

if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION["role"] ?? "";

if (!in_array($role, ["Admin", "Petugas"], true)) {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// FILTER TANGGAL
// ==================================

$dari = $_GET["dari"] ?? "";
$sampai = $_GET["sampai"] ?? "";
$error = "";

function validasiTanggal($tanggal) {
    if ($tanggal === "") {
        return true;
    }

    $hasil = DateTime::createFromFormat("!Y-m-d", $tanggal);

    return $hasil && $hasil->format("Y-m-d") === $tanggal;
}

if (!validasiTanggal($dari) || !validasiTanggal($sampai)) {
    $error = "Format tanggal tidak valid.";
    $dari = "";
    $sampai = "";
}

if ($dari !== "" && $sampai !== "" && $dari > $sampai) {
    $error = "Tanggal awal tidak boleh melebihi tanggal akhir.";
    $dari = "";
    $sampai = "";
}

// ==================================
// AMBIL DATA LAPORAN
// ==================================

$where = "";
$params = [];

if ($dari !== "") {
    $where .= " AND p.tanggal_pinjam >= ?";
    $params[] = $dari;
}

if ($sampai !== "") {
    $where .= " AND p.tanggal_pinjam <= ?";
    $params[] = $sampai;
}

$sql = "
    SELECT
        p.id_peminjaman,
        u.nama AS nama_peminjam,
        p.tanggal_pengajuan,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.keperluan,
        p.status,

        GROUP_CONCAT(
            CONCAT(
                a.nama_alat,
                ' (',
                dp.jumlah_pinjam,
                ')'
            )
            SEPARATOR ', '
        ) AS daftar_alat,

        pg.tanggal_kembali,
        pg.terlambat,
        pg.denda,
        pg.kondisi_kembali,
        petugas.nama AS nama_petugas

    FROM peminjaman p

    INNER JOIN users u
        ON p.id_user = u.id

    LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman

    LEFT JOIN alat a
        ON dp.id_alat = a.id_alat

    LEFT JOIN pengembalian pg
        ON p.id_peminjaman = pg.id_peminjaman

    LEFT JOIN users petugas
        ON pg.id_petugas = petugas.id

    WHERE 1=1
    $where

    GROUP BY
        p.id_peminjaman,
        u.nama,
        p.tanggal_pengajuan,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.keperluan,
        p.status,
        pg.tanggal_kembali,
        pg.terlambat,
        pg.denda,
        pg.kondisi_kembali,
        petugas.nama

    ORDER BY p.id_peminjaman DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================================
// HITUNG RINGKASAN
// ==================================

$total = count($laporan);
$menunggu = 0;
$disetujui = 0;
$ditolak = 0;
$selesai = 0;
$totalDenda = 0;

foreach ($laporan as $item) {

    if ($item["status"] === "Menunggu") {
        $menunggu++;
    } elseif ($item["status"] === "Disetujui") {
        $disetujui++;
    } elseif ($item["status"] === "Ditolak") {
        $ditolak++;
    } elseif ($item["status"] === "Selesai") {
        $selesai++;
    }

    $totalDenda += (float) ($item["denda"] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Peminjaman | Sistem Peminjaman Alat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f4f7fc;
            color: #26334d;
            min-height: 100vh;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 255px;
            min-height: 100vh;
            background: linear-gradient(180deg, #111c38, #17294d);
            color: white;
            padding: 27px 17px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 30px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            border-radius: 13px;
            background: linear-gradient(135deg, #5285ff, #2860df);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 5px 15px rgba(45,100,230,.3);
        }

        .brand h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 15px;
            font-weight: 800;
        }

        .brand p {
            font-size: 10px;
            color: #9eafd0;
            margin-top: 4px;
        }

        .menu-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #8294b7;
            padding: 28px 12px 12px;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            color: #bac7df;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border-radius: 11px;
            transition: .25s;
        }

        .menu a:hover {
            background: rgba(255,255,255,.08);
            color: white;
        }

        .menu a.active {
            background: linear-gradient(135deg, #416ff0, #315bd0);
            color: white;
            box-shadow: 0 6px 16px rgba(40,91,218,.3);
        }

        .menu-icon {
            width: 20px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-bottom {
            position: absolute;
            bottom: 25px;
            left: 17px;
            right: 17px;
            padding: 16px;
            border-radius: 13px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.08);
        }

        .sidebar-bottom p {
            font-size: 11px;
            color: #a9b9d5;
            line-height: 1.7;
        }

        .sidebar-bottom strong {
            display: block;
            color: white;
            font-size: 12px;
            margin-bottom: 4px;
        }

        /* MAIN */

        .main {
            width: calc(100% - 255px);
            margin-left: 255px;
            padding: 28px 35px;
        }

        /* TOPBAR */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 27px;
            gap: 15px;
        }

        .breadcrumb {
            color: #8995aa;
            font-size: 12px;
        }

        .breadcrumb strong {
            color: #315ed0;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 11px;
            background: white;
            padding: 8px 13px;
            border-radius: 12px;
            border: 1px solid #edf0f6;
            box-shadow: 0 3px 12px rgba(25,45,80,.03);
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            background: linear-gradient(135deg, #4779ef, #274fc1);
        }

        .profile-info strong {
            display: block;
            font-size: 12px;
        }

        .profile-info span {
            font-size: 10px;
            color: #8b98ad;
        }

        /* HEADER */

        .page-header {
            position: relative;
            overflow: hidden;
            background: linear-gradient(120deg, #172b58, #285bc4 65%, #477df0);
            padding: 30px 32px;
            border-radius: 19px;
            color: white;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(43,91,190,.15);
        }

        .page-header::after {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            border: 35px solid rgba(255,255,255,.07);
            border-radius: 50%;
            right: -45px;
            top: -105px;
        }

        .page-header h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            color: #d5e3ff;
            font-size: 13px;
            position: relative;
            z-index: 1;
        }

        .header-label {
            display: inline-block;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.17);
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 13px;
            position: relative;
            z-index: 1;
        }

        /* STATISTIK */

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 17px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border: 1px solid #edf0f6;
            padding: 19px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 15px rgba(25,45,80,.035);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .blue-bg {
            background: #e8efff;
        }

        .green-bg {
            background: #e3f8ed;
        }

        .orange-bg {
            background: #fff2df;
        }

        .red-bg {
            background: #ffebeb;
        }

        .purple-bg {
            background: #f0eaff;
        }

        .stat-card span {
            color: #8995aa;
            font-size: 11px;
            display: block;
            margin-bottom: 5px;
        }

        .stat-card h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 21px;
            font-weight: 800;
            color: #263858;
        }

        /* ALERT */

        .alert {
            padding: 14px 17px;
            border-radius: 11px;
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 600;
        }

        .danger {
            background: #fff0f0;
            border: 1px solid #ffd4d4;
            color: #b42323;
        }

        /* CARD */

        .card {
            background: white;
            border: 1px solid #edf0f6;
            border-radius: 17px;
            padding: 24px;
            margin-bottom: 23px;
            box-shadow: 0 4px 17px rgba(25,45,80,.035);
        }

        .card-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 21px;
        }

        .card-heading h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: #263858;
        }

        .card-heading p {
            font-size: 11px;
            color: #98a3b5;
            margin-top: 5px;
        }

        .heading-icon {
            color: #4169df;
            margin-right: 7px;
        }

        /* FILTER */

        .filter-form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 180px;
        }

        label {
            font-size: 12px;
            font-weight: 700;
            color: #52617a;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e1e7f0;
            border-radius: 10px;
            background: #f9faff;
            color: #33415b;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: .2s;
        }

        input:focus {
            border-color: #4779ef;
            background: white;
            box-shadow: 0 0 0 3px rgba(65,105,223,.1);
        }

        .filter-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 12px 16px;
            border: none;
            border-radius: 9px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: .2s;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(120deg, #416ff0, #2856c7);
            color: white;
            box-shadow: 0 5px 13px rgba(49,94,211,.17);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-reset {
            background: #edf1f8;
            color: #53627b;
        }

        .btn-reset:hover {
            background: #e1e7f1;
        }

        .btn-print {
            background: #e3f8ed;
            color: #19834d;
        }

        .btn-print:hover {
            background: #d3f2e0;
        }

        /* TABEL */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        th {
            padding: 14px 12px;
            background: #f2f5fc;
            color: #52658d;
            font-size: 11px;
            font-weight: 800;
            text-align: left;
            white-space: nowrap;
        }

        th:first-child {
            border-radius: 8px 0 0 8px;
        }

        th:last-child {
            border-radius: 0 8px 8px 0;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f2f7;
            font-size: 12px;
            color: #63718a;
            vertical-align: middle;
        }

        tbody tr {
            transition: .2s;
        }

        tbody tr:hover {
            background: #f9fbff;
        }

        .nama-peminjam {
            color: #293b5c;
            font-weight: 800;
        }

        .id-peminjaman {
            color: #4169d9;
            font-weight: 800;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-menunggu {
            background: #fff2dc;
            color: #a66b13;
        }

        .status-disetujui {
            background: #e8efff;
            color: #315fd2;
        }

        .status-ditolak {
            background: #ffe8e8;
            color: #c03939;
        }

        .status-selesai {
            background: #e3f8ed;
            color: #19834d;
        }

        .denda {
            color: #c03939;
            font-weight: 800;
            white-space: nowrap;
        }

        .kosong {
            text-align: center;
            padding: 35px;
            color: #9aa5b7;
        }

        .empty-icon {
            display: block;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .footer {
            text-align: center;
            padding: 10px;
            color: #a0aabd;
            font-size: 11px;
        }

        /* RESPONSIVE */

        @media (max-width: 1100px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 800px) {
            .sidebar {
                width: 75px;
                padding: 20px 10px;
            }

            .brand {
                justify-content: center;
                padding: 0 0 25px;
            }

            .brand-text,
            .menu-title,
            .menu-text,
            .sidebar-bottom {
                display: none;
            }

            .menu a {
                justify-content: center;
                padding: 14px 8px;
            }

            .menu-icon {
                font-size: 19px;
            }

            .main {
                width: calc(100% - 75px);
                margin-left: 75px;
                padding: 22px 18px;
            }
        }

        @media (max-width: 550px) {
            .main {
                padding: 17px 12px;
            }

            .topbar {
                align-items: flex-start;
            }

            .profile {
                padding: 6px;
            }

            .profile-info {
                display: none;
            }

            .page-header {
                padding: 24px 21px;
            }

            .page-header h1 {
                font-size: 21px;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 13px 10px;
                gap: 9px;
            }

            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            .stat-card h3 {
                font-size: 18px;
            }

            .stat-card span {
                font-size: 10px;
            }

            .card {
                padding: 17px 13px;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group {
                width: 100%;
            }

            .filter-actions {
                width: 100%;
            }

            .filter-actions .btn {
                flex: 1;
            }
        }

        /* CETAK LAPORAN */

        @media print {
            body {
                background: white;
                padding: 0;
                color: black;
            }

            .sidebar,
            .topbar,
            .filter-card,
            .footer {
                display: none !important;
            }

            .main {
                width: 100%;
                margin: 0;
                padding: 0;
            }

            .page-header {
                background: white !important;
                color: black !important;
                box-shadow: none;
                border: 1px solid #ddd;
                padding: 15px;
                margin-bottom: 15px;
            }

            .page-header h1,
            .page-header p {
                color: black !important;
            }

            .page-header::after,
            .header-label {
                display: none;
            }

            .stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }

            .stat-card,
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .card {
                padding: 10px;
            }

            .table-wrapper {
                overflow: visible;
            }

            table {
                min-width: 0;
                width: 100%;
            }

            th,
            td {
                font-size: 8px;
                padding: 6px 4px;
                color: black;
                border: 1px solid #ccc;
            }

            th {
                background: #e8edf5 !important;
                color: black !important;
            }

            .badge {
                border: 1px solid #ccc;
                background: white !important;
                color: black !important;
            }

            .card,
            .stats,
            table {
                break-inside: avoid;
            }
        }

    </style>

</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">📦</div>

            <div class="brand-text">
                <h2>INVENTORY APP</h2>
                <p>Sistem Peminjaman Alat</p>
            </div>
        </div>

        <div class="menu-title">MENU UTAMA</div>

        <nav class="menu">

            <a href="dashboard.php">
                <span class="menu-icon">▦</span>
                <span class="menu-text">Dashboard</span>
            </a>

            <?php if ($role === "Admin"): ?>

                <a href="users.php">
                    <span class="menu-icon">♙</span>
                    <span class="menu-text">Data Pengguna</span>
                </a>

                <a href="kategori.php">
                    <span class="menu-icon">▤</span>
                    <span class="menu-text">Data Kategori</span>
                </a>

                <a href="alat.php">
                    <span class="menu-icon">▣</span>
                    <span class="menu-text">Data Alat</span>
                </a>

            <?php else: ?>

                <a href="persetujuan.php">
                    <span class="menu-icon">✓</span>
                    <span class="menu-text">Persetujuan</span>
                </a>

                <a href="pengembalian.php">
                    <span class="menu-icon">↩</span>
                    <span class="menu-text">Pengembalian</span>
                </a>

            <?php endif; ?>

            <a href="laporan.php" class="active">
                <span class="menu-icon">▧</span>
                <span class="menu-text">Laporan</span>
            </a>

            <?php if ($role === "Admin"): ?>
                <a href="log_aktivitas.php">
                    <span class="menu-icon">◷</span>
                    <span class="menu-text">Log Aktivitas</span>
                </a>
            <?php endif; ?>

        </nav>

        <div class="sidebar-bottom">
            <strong>Panel <?= htmlspecialchars($role) ?></strong>
            <p>Informasi laporan peminjaman dan pengembalian alat.</p>
        </div>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <div class="breadcrumb">
                Halaman / Laporan / <strong>Laporan Peminjaman</strong>
            </div>

            <div class="profile">

                <div class="avatar">
                    <?= strtoupper(substr($_SESSION["nama"] ?? "U", 0, 1)) ?>
                </div>

                <div class="profile-info">
                    <strong><?= htmlspecialchars($_SESSION["nama"] ?? "Pengguna") ?></strong>
                    <span><?= htmlspecialchars($role) ?></span>
                </div>

            </div>

        </div>

        <!-- HEADER -->

        <section class="page-header">

            <div class="header-label">LAPORAN SISTEM</div>

            <h1>📊 Laporan Peminjaman dan Pengembalian</h1>

            <p>
                Pantau data peminjaman, status pengajuan, pengembalian,
                dan total denda dalam satu halaman.
            </p>

        </section>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">
                <div class="stat-icon blue-bg">📋</div>
                <div>
                    <span>Total Peminjaman</span>
                    <h3><?= $total ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange-bg">◷</div>
                <div>
                    <span>Menunggu</span>
                    <h3><?= $menunggu ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue-bg">✓</div>
                <div>
                    <span>Disetujui</span>
                    <h3><?= $disetujui ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red-bg">✕</div>
                <div>
                    <span>Ditolak</span>
                    <h3><?= $ditolak ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green-bg">✔</div>
                <div>
                    <span>Selesai</span>
                    <h3><?= $selesai ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple-bg">Rp</div>
                <div>
                    <span>Total Denda</span>
                    <h3 style="font-size:16px;">
                        Rp<?= number_format($totalDenda, 0, ",", ".") ?>
                    </h3>
                </div>
            </div>

        </section>

        <!-- ERROR -->

        <?php if ($error !== ""): ?>
            <div class="alert danger">
                ⚠ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- FILTER TANGGAL -->

        <section class="card filter-card">

            <div class="card-heading">
                <div>
                    <h2>
                        <span class="heading-icon">⌕</span>
                        Filter Laporan
                    </h2>
                    <p>Pilih rentang tanggal untuk melihat laporan tertentu.</p>
                </div>
            </div>

            <form method="GET" class="filter-form">

                <div class="form-group">
                    <label>Tanggal Dari</label>

                    <input
                        type="date"
                        name="dari"
                        value="<?= htmlspecialchars($dari) ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Tanggal Sampai</label>

                    <input
                        type="date"
                        name="sampai"
                        value="<?= htmlspecialchars($sampai) ?>"
                    >
                </div>

                <div class="filter-actions">

                    <button type="submit" class="btn btn-primary">
                        ⌕ Cari Laporan
                    </button>

                    <a href="laporan.php" class="btn btn-reset">
                        ↻ Reset
                    </a>

                    <button
                        type="button"
                        class="btn btn-print"
                        onclick="window.print()"
                    >
                        ▤ Cetak
                    </button>

                </div>

            </form>

        </section>

        <!-- TABEL LAPORAN -->

        <section class="card">

            <div class="card-heading">

                <div>
                    <h2>
                        <span class="heading-icon">☷</span>
                        Daftar Laporan Peminjaman
                    </h2>

                    <p>
                        Menampilkan <?= $total ?> data peminjaman
                        sesuai filter tanggal.
                    </p>
                </div>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID</th>
                            <th>Nama Peminjam</th>
                            <th>Daftar Alat</th>
                            <th>Tanggal Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Tanggal Kembali</th>
                            <th>Terlewat</th>
                            <th>Denda</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($laporan) > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($laporan as $item): ?>

                                <?php
                                $kelasStatus = [
                                    "Menunggu" => "status-menunggu",
                                    "Disetujui" => "status-disetujui",
                                    "Ditolak" => "status-ditolak",
                                    "Selesai" => "status-selesai"
                                ];

                                $kelas = $kelasStatus[$item["status"]] ?? "";
                                ?>

                                <tr>

                                    <td><?= $no++ ?></td>

                                    <td class="id-peminjaman">
                                        #<?= (int) $item["id_peminjaman"] ?>
                                    </td>

                                    <td class="nama-peminjam">
                                        <?= htmlspecialchars($item["nama_peminjam"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item["daftar_alat"] ?? "-") ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item["tanggal_pinjam"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item["tanggal_rencana_kembali"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item["tanggal_kembali"] ?? "-") ?>
                                    </td>

                                    <td>
                                        <?= (int) ($item["terlambat"] ?? 0) ?> hari
                                    </td>

                                    <td class="denda">
                                        Rp<?= number_format(
                                            (float) ($item["denda"] ?? 0),
                                            0,
                                            ",",
                                            "."
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $kelas ?>">
                                            <?= htmlspecialchars($item["status"]) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="10" class="kosong">
                                    <span class="empty-icon">📭</span>
                                    Belum ada data laporan.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <div class="footer">
            © <?= date("Y") ?> Sistem Informasi Peminjaman Alat
        </div>

    </main>

</div>

</body>
</html>