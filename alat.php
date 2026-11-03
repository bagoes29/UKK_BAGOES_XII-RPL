
<?php
session_start();
require_once "config.php";

// CEK HAK AKSES ADMIN
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Admin") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// TOKEN CSRF
if (!isset($_SESSION["csrf_alat"])) {
    $_SESSION["csrf_alat"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_alat"];
$error = "";

// TAMBAH DATA ALAT
if (isset($_POST["tambah"])) {
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid.";
    } else {
        $id_kategori = (int) ($_POST["id_kategori"] ?? 0);
        $kode_alat = trim($_POST["kode_alat"] ?? "");
        $nama_alat = trim($_POST["nama_alat"] ?? "");
        $jumlah = (int) ($_POST["jumlah"] ?? 0);
        $kondisi = $_POST["kondisi"] ?? "";
        $lokasi = trim($_POST["lokasi"] ?? "");

        $daftarKondisi = ["Baik", "Rusak Ringan", "Rusak Berat"];

        if (
            $id_kategori <= 0 ||
            $kode_alat === "" ||
            $nama_alat === "" ||
            $jumlah <= 0 ||
            !in_array($kondisi, $daftarKondisi, true)
        ) {
            $error = "Semua data wajib diisi dengan benar.";
        } else {
            try {
                $pdo->beginTransaction();

                $sql = $pdo->prepare(
                    "INSERT INTO alat
                    (id_kategori, kode_alat, nama_alat, jumlah, stok, kondisi, lokasi)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $sql->execute([
                    $id_kategori,
                    $kode_alat,
                    $nama_alat,
                    $jumlah,
                    $jumlah,
                    $kondisi,
                    $lokasi
                ]);

                $id_alat_baru = $pdo->lastInsertId();

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas (id_user, aktivitas)
                    VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Menambahkan alat: " . $nama_alat .
                    " (Kode: " . $kode_alat .
                    ", ID: " . $id_alat_baru . ")"
                ]);

                $pdo->commit();

                header("Location: alat.php?pesan=tambah");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Gagal menambahkan alat. Periksa kode alat dan kategori.";
            }
        }
    }
}

// HAPUS DATA ALAT
if (isset($_POST["hapus"])) {
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan hapus tidak valid.";
    } else {
        $id = (int) ($_POST["id_alat"] ?? 0);

        if ($id <= 0) {
            $error = "ID alat tidak valid.";
        } else {
            try {
                $pdo->beginTransaction();

                $cek = $pdo->prepare(
                    "SELECT nama_alat, kode_alat
                    FROM alat WHERE id_alat = ? FOR UPDATE"
                );

                $cek->execute([$id]);
                $dataHapus = $cek->fetch(PDO::FETCH_ASSOC);

                if (!$dataHapus) {
                    throw new Exception("Data alat tidak ditemukan.");
                }

                $sql = $pdo->prepare(
                    "DELETE FROM alat WHERE id_alat = ?"
                );

                $sql->execute([$id]);

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas (id_user, aktivitas)
                    VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Menghapus alat: " .
                    $dataHapus["nama_alat"] .
                    " (Kode: " .
                    $dataHapus["kode_alat"] .
                    ", ID: " . $id . ")"
                ]);

                $pdo->commit();

                header("Location: alat.php?pesan=hapus");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Alat tidak dapat dihapus karena masih digunakan dalam riwayat peminjaman.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

// AMBIL DATA KATEGORI
$sqlKategori = $pdo->query(
    "SELECT * FROM kategori ORDER BY nama_kategori ASC"
);

$daftarKategori = $sqlKategori->fetchAll(PDO::FETCH_ASSOC);

// AMBIL DATA ALAT
$sqlAlat = $pdo->query(
    "SELECT alat.*, kategori.nama_kategori
    FROM alat
    INNER JOIN kategori
    ON alat.id_kategori = kategori.id_kategori
    ORDER BY alat.id_alat DESC"
);

$dataAlat = $sqlAlat->fetchAll(PDO::FETCH_ASSOC);

// STATISTIK
$totalAlat = count($dataAlat);
$totalStok = 0;
$totalBaik = 0;
$totalRusak = 0;

foreach ($dataAlat as $item) {
    $totalStok += (int) $item["stok"];

    if ($item["kondisi"] === "Baik") {
        $totalBaik++;
    } else {
        $totalRusak++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Alat | Sistem Peminjaman</title>

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
            grid-template-columns: repeat(4, 1fr);
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

        .stat-card span {
            color: #8995aa;
            font-size: 11px;
            display: block;
            margin-bottom: 5px;
        }

        .stat-card h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #263858;
        }

        /* NOTIFIKASI */
        .alert {
            padding: 14px 17px;
            border-radius: 11px;
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 600;
        }

        .success {
            background: #e6f8ed;
            border: 1px solid #c5edd3;
            color: #187644;
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

        /* FORM */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 17px 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 12px;
            font-weight: 700;
            color: #52617a;
            margin-bottom: 8px;
        }

        input, select {
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

        input:focus, select:focus {
            border-color: #4779ef;
            background: white;
            box-shadow: 0 0 0 3px rgba(65,105,223,.1);
        }

        .full {
            grid-column: 1 / -1;
        }

        .btn-primary {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 13px;
            background: linear-gradient(120deg, #416ff0, #2856c7);
            color: white;
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 5px 13px rgba(49,94,211,.17);
            transition: .25s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 17px rgba(49,94,211,.25);
        }

        /* TABEL */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
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
        }

        tbody tr {
            transition: .2s;
        }

        tbody tr:hover {
            background: #f9fbff;
        }

        .nama-alat {
            color: #293b5c;
            font-weight: 800;
        }

        .kode {
            color: #4169d9;
            font-weight: 700;
        }

        .stok {
            color: #315fd2;
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

        .baik {
            background: #e3f8ed;
            color: #19834d;
        }

        .rusak-ringan {
            background: #fff2dc;
            color: #a66b13;
        }

        .rusak-berat {
            background: #ffe8e8;
            color: #c03939;
        }

        .aksi {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .btn-edit, .btn-delete {
            display: inline-block;
            padding: 7px 11px;
            border: none;
            border-radius: 7px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: .2s;
        }

        .btn-edit {
            background: #e8efff;
            color: #315fd2;
        }

        .btn-edit:hover {
            background: #d8e4ff;
        }

        .btn-delete {
            background: #ffebeb;
            color: #c83c3c;
        }

        .btn-delete:hover {
            background: #ffdada;
        }

        .form-hapus {
            display: inline;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
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

            <a href="users.php">
                <span class="menu-icon">♙</span>
                <span class="menu-text">Data Pengguna</span>
            </a>

            <a href="kategori.php">
                <span class="menu-icon">▤</span>
                <span class="menu-text">Data Kategori</span>
            </a>

            <a href="alat.php" class="active">
                <span class="menu-icon">▣</span>
                <span class="menu-text">Data Alat</span>
            </a>

            <a href="laporan.php">
                <span class="menu-icon">▧</span>
                <span class="menu-text">Laporan</span>
            </a>

            <a href="log_aktivitas.php">
                <span class="menu-icon">◷</span>
                <span class="menu-text">Log Aktivitas</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <strong>Panel Administrator</strong>
            <p>Kelola inventaris dan data peminjaman alat dengan mudah.</p>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <!-- TOPBAR -->
        <div class="topbar">

            <div class="breadcrumb">
                Halaman / Data Master / <strong>Data Alat</strong>
            </div>

            <div class="profile">
                <div class="avatar">
                    <?= strtoupper(substr($_SESSION["nama"] ?? "A", 0, 1)) ?>
                </div>

                <div class="profile-info">
                    <strong><?= htmlspecialchars($_SESSION["nama"] ?? "Admin") ?></strong>
                    <span>Administrator</span>
                </div>
            </div>

        </div>

        <!-- HEADER -->
        <section class="page-header">
            <div class="header-label">ADMINISTRATOR PANEL</div>
            <h1>📦 Data Alat</h1>
            <p>Kelola inventaris, informasi, kondisi, dan ketersediaan stok alat.</p>
        </section>

        <!-- STATISTIK -->
        <section class="stats">

            <div class="stat-card">
                <div class="stat-icon blue-bg">📦</div>
                <div>
                    <span>Total Jenis Alat</span>
                    <h3><?= $totalAlat ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green-bg">✓</div>
                <div>
                    <span>Total Stok Tersedia</span>
                    <h3><?= $totalStok ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange-bg">✦</div>
                <div>
                    <span>Alat Kondisi Baik</span>
                    <h3><?= $totalBaik ?></h3>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red-bg">⚠</div>
                <div>
                    <span>Alat Rusak</span>
                    <h3><?= $totalRusak ?></h3>
                </div>
            </div>

        </section>

        <!-- NOTIFIKASI -->
        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "tambah"): ?>
                <div class="alert success">
                    ✓ Data alat berhasil ditambahkan dan aktivitas tercatat!
                </div>

            <?php elseif ($_GET["pesan"] === "hapus"): ?>
                <div class="alert success">
                    ✓ Data alat berhasil dihapus dan aktivitas tercatat!
                </div>

            <?php elseif ($_GET["pesan"] === "edit"): ?>
                <div class="alert success">
                    ✓ Data alat berhasil diperbarui!
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert danger">
                ⚠ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- FORM TAMBAH ALAT -->
        <section class="card">

            <div class="card-heading">
                <div>
                    <h2><span class="heading-icon">＋</span>Tambah Data Alat</h2>
                    <p>Masukkan informasi alat yang akan ditambahkan ke inventaris.</p>
                </div>
            </div>

            <?php if (count($daftarKategori) > 0): ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($token) ?>"
                    >

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Kategori Alat</label>

                            <select name="id_kategori" required>
                                <option value="">Pilih Kategori</option>

                                <?php foreach ($daftarKategori as $kategori): ?>
                                    <option value="<?= (int) $kategori["id_kategori"] ?>">
                                        <?= htmlspecialchars($kategori["nama_kategori"]) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div class="form-group">
                            <label>Kode Alat</label>

                            <input
                                type="text"
                                name="kode_alat"
                                placeholder="Contoh: ALT001"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Nama Alat</label>

                            <input
                                type="text"
                                name="nama_alat"
                                placeholder="Contoh: Laptop"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Jumlah Alat</label>

                            <input
                                type="number"
                                name="jumlah"
                                min="1"
                                placeholder="Masukkan jumlah alat"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Kondisi Alat</label>

                            <select name="kondisi" required>
                                <option value="">Pilih Kondisi</option>
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Lokasi Alat</label>

                            <input
                                type="text"
                                name="lokasi"
                                placeholder="Contoh: Laboratorium RPL"
                            >
                        </div>

                        <div class="full">
                            <button type="submit" name="tambah" class="btn-primary">
                                ＋ Tambahkan Alat
                            </button>
                        </div>

                    </div>

                </form>

            <?php else: ?>

                <div class="alert danger">
                    Belum ada kategori. Tambahkan kategori terlebih dahulu
                    melalui halaman Data Kategori.
                </div>

            <?php endif; ?>

        </section>

        <!-- DAFTAR ALAT -->
        <section class="card">

            <div class="card-heading">
                <div>
                    <h2><span class="heading-icon">☷</span>Daftar Alat</h2>
                    <p>Informasi seluruh alat yang tersimpan dalam database.</p>
                </div>
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Alat</th>
                            <th>Nama Alat</th>
                            <th>Kategori</th>
                            <th>Jumlah</th>
                            <th>Stok Tersedia</th>
                            <th>Kondisi</th>
                            <th>Lokasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($dataAlat) > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($dataAlat as $alat): ?>

                                <?php
                                $kelasKondisi = [
                                    "Baik" => "baik",
                                    "Rusak Ringan" => "rusak-ringan",
                                    "Rusak Berat" => "rusak-berat"
                                ];

                                $kelas = $kelasKondisi[$alat["kondisi"]] ?? "";
                                ?>

                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td class="kode">
                                        <?= htmlspecialchars($alat["kode_alat"]) ?>
                                    </td>

                                    <td class="nama-alat">
                                        <?= htmlspecialchars($alat["nama_alat"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($alat["nama_kategori"]) ?>
                                    </td>

                                    <td>
                                        <?= (int) $alat["jumlah"] ?>
                                    </td>

                                    <td class="stok">
                                        <?= (int) $alat["stok"] ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= $kelas ?>">
                                            <?= htmlspecialchars($alat["kondisi"]) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($alat["lokasi"] ?? "") ?>
                                    </td>

                                    <td>
                                        <div class="aksi">

                                            <a
                                                href="edit_alat.php?id=<?= (int) $alat["id_alat"] ?>"
                                                class="btn-edit"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                class="form-hapus"
                                                onsubmit="return confirm('Yakin ingin menghapus alat ini?')"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars($token) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id_alat"
                                                    value="<?= (int) $alat["id_alat"] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="hapus"
                                                    class="btn-delete"
                                                >
                                                    Hapus
                                                </button>

                                            </form>

                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="9" class="kosong">
                                    <span class="empty-icon">📭</span>
                                    Belum ada data alat.
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