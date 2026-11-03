
<?php
session_start();
require_once "config.php";

// ======================================
// CEK HAK AKSES ADMIN
// ======================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Admin") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

$namaAdmin = htmlspecialchars($_SESSION["nama"] ?? "Admin", ENT_QUOTES, 'UTF-8');
$inisial = strtoupper(substr($_SESSION["nama"] ?? "A", 0, 1));

// ======================================
// TOKEN KEAMANAN
// ======================================

if (!isset($_SESSION["csrf_kategori"])) {
    $_SESSION["csrf_kategori"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_kategori"];
$error = "";

// ======================================
// TAMBAH KATEGORI
// ======================================

if (isset($_POST["tambah"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid.";

    } else {

        $nama = trim($_POST["nama_kategori"] ?? "");
        $deskripsi = trim($_POST["deskripsi"] ?? "");

        if ($nama === "") {
            $error = "Nama kategori wajib diisi.";

        } else {

            try {

                $pdo->beginTransaction();

                $sql = $pdo->prepare(
                    "INSERT INTO kategori (nama_kategori, deskripsi)
                     VALUES (?, ?)"
                );

                $sql->execute([$nama, $deskripsi]);

                $id_kategori_baru = $pdo->lastInsertId();

                // CATAT AKTIVITAS

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas (id_user, aktivitas)
                     VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Menambahkan kategori: " .
                    $nama .
                    " (ID: " . $id_kategori_baru . ")"
                ]);

                $pdo->commit();

                header("Location: kategori.php?pesan=tambah");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Gagal menambahkan kategori. Silakan periksa data kategori.";
            }
        }
    }
}

// ======================================
// HAPUS KATEGORI
// ======================================

if (isset($_POST["hapus"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan hapus tidak valid.";

    } else {

        $id = (int) ($_POST["id_kategori"] ?? 0);

        if ($id <= 0) {
            $error = "ID kategori tidak valid.";

        } else {

            try {

                $pdo->beginTransaction();

                $cek = $pdo->prepare(
                    "SELECT nama_kategori
                     FROM kategori
                     WHERE id_kategori = ?
                     FOR UPDATE"
                );

                $cek->execute([$id]);

                $dataKategori = $cek->fetch(PDO::FETCH_ASSOC);

                if (!$dataKategori) {
                    throw new Exception("Kategori tidak ditemukan.");
                }

                $sql = $pdo->prepare(
                    "DELETE FROM kategori
                     WHERE id_kategori = ?"
                );

                $sql->execute([$id]);

                // CATAT AKTIVITAS

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas (id_user, aktivitas)
                     VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Menghapus kategori: " .
                    $dataKategori["nama_kategori"] .
                    " (ID: " . $id . ")"
                ]);

                $pdo->commit();

                header("Location: kategori.php?pesan=hapus");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Kategori tidak dapat dihapus karena masih digunakan oleh data alat.";

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

// ======================================
// AMBIL DATA KATEGORI
// ======================================

$sql = $pdo->query(
    "SELECT * FROM kategori ORDER BY id_kategori ASC"
);

$kategori = $sql->fetchAll(PDO::FETCH_ASSOC);

$totalKategori = count($kategori);
$totalDeskripsi = 0;

foreach ($kategori as $item) {
    if (trim($item["deskripsi"] ?? "") !== "") {
        $totalDeskripsi++;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Kategori | Sistem Peminjaman Alat</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

:root {
    --navy: #111c38;
    --blue: #4169f5;
    --text: #192642;
    --muted: #8994a9;
    --background: #f4f7fc;
    --border: #edf0f6;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--background);
    color: var(--text);
}

a {
    text-decoration: none;
}

/* LAYOUT */

.layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 265px;
    min-height: 100vh;
    background: linear-gradient(180deg,#111c38,#17294d);
    padding: 30px 20px;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    color: white;
    position: relative;
    overflow: hidden;
}

.sidebar::before {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    background: rgba(65,105,245,.13);
    border-radius: 50%;
    top: -100px;
    right: -100px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
    margin-bottom: 48px;
    padding: 0 5px;
}

.brand-icon {
    width: 47px;
    height: 47px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg,#557cff,#3156db);
    border-radius: 14px;
    font-size: 24px;
    box-shadow: 0 8px 20px rgba(65,105,245,.3);
}

.brand-name {
    font-family: 'Plus Jakarta Sans',sans-serif;
    font-size: 14px;
    font-weight: 800;
    line-height: 1.5;
}

.brand-name span {
    display: block;
    color: #8298c5;
    font-size: 10px;
    letter-spacing: 1.5px;
}

.side-label {
    font-size: 10px;
    color: #7185ad;
    font-weight: 800;
    letter-spacing: 2px;
    padding: 0 12px;
    margin-bottom: 15px;
}

.side-menu {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.side-link {
    padding: 13px 14px;
    border-radius: 12px;
    color: #a9b8d4;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    font-weight: 600;
    transition: .25s;
}

.side-link:hover,
.side-link.active {
    background: linear-gradient(100deg,#4169f5,#3157d8);
    color: white;
    box-shadow: 0 7px 18px rgba(49,87,216,.25);
    transform: translateX(3px);
}

.side-icon {
    font-size: 17px;
    width: 23px;
    text-align: center;
}

.sidebar-bottom {
    margin-top: auto;
    position: relative;
    z-index: 1;
}

.help-card {
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.08);
    padding: 17px;
    border-radius: 15px;
    margin-bottom: 18px;
}

.help-card h4 {
    font-size: 12px;
    margin-bottom: 7px;
}

.help-card p {
    font-size: 11px;
    color: #9cacc9;
    line-height: 1.7;
}

.sidebar-footer {
    text-align: center;
    font-size: 10px;
    color: #7185ad;
    line-height: 1.8;
}

/* MAIN */

.main {
    flex: 1;
    min-width: 0;
    padding: 30px 38px;
}

/* TOPBAR */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.breadcrumb {
    color: var(--muted);
    font-size: 12px;
}

.breadcrumb strong {
    color: var(--text);
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 7px 13px 7px 7px;
    border: 1px solid var(--border);
    border-radius: 13px;
}

.avatar {
    width: 37px;
    height: 37px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg,#4169f5,#7a91ff);
    border-radius: 11px;
    color: white;
    font-weight: 800;
}

.profile-info strong {
    display: block;
    font-size: 12px;
}

.profile-info span {
    color: var(--muted);
    font-size: 10px;
}

/* PAGE HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 29px 32px;
    border-radius: 21px;
    color: white;
    background: linear-gradient(115deg,#172b58,#254b9e 60%,#4169f5);
    position: relative;
    overflow: hidden;
    margin-bottom: 25px;
    box-shadow: 0 13px 35px rgba(43,79,166,.16);
}

.page-header::after {
    content: "▤";
    position: absolute;
    font-size: 150px;
    color: rgba(255,255,255,.07);
    right: 35px;
    bottom: -45px;
}

.header-content {
    position: relative;
    z-index: 1;
}

.header-tag {
    display: inline-block;
    background: rgba(255,255,255,.15);
    padding: 7px 12px;
    border-radius: 30px;
    font-size: 10px;
    letter-spacing: 1.3px;
    font-weight: 800;
    margin-bottom: 13px;
}

.page-header h1 {
    font-family: 'Plus Jakarta Sans',sans-serif;
    font-size: 27px;
    font-weight: 800;
    margin-bottom: 8px;
}

.page-header p {
    font-size: 12px;
    color: #d0dcff;
    line-height: 1.8;
}

.back-button {
    position: relative;
    z-index: 2;
    padding: 12px 17px;
    background: rgba(255,255,255,.15);
    color: white;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 11px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    transition: .2s;
}

.back-button:hover {
    background: white;
    color: #3156db;
}

/* STATISTIK */

.stats {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 16px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 17px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: .25s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(31,53,100,.07);
}

.stat-icon {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    font-size: 22px;
    flex-shrink: 0;
}

.stat-blue {
    background: #edf2ff;
}

.stat-purple {
    background: #f2edff;
}

.stat-green {
    background: #e8f9f0;
}

.stat-info span {
    display: block;
    color: var(--muted);
    font-size: 10px;
    margin-bottom: 5px;
}

.stat-info strong {
    font-family: 'Plus Jakarta Sans',sans-serif;
    font-size: 24px;
    font-weight: 800;
}

/* ALERT */

.alert {
    padding: 14px 17px;
    border-radius: 12px;
    margin-bottom: 18px;
    font-size: 12px;
    line-height: 1.6;
}

.success {
    background: #e9f9ef;
    color: #187a49;
    border: 1px solid #c8eed8;
}

.danger {
    background: #fff0f0;
    color: #c43f4c;
    border: 1px solid #ffd6d9;
}

/* CONTENT GRID */

.content-grid {
    display: grid;
    grid-template-columns: 340px minmax(0,1fr);
    gap: 20px;
    align-items: start;
}

/* PANEL */

.panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 23px;
    box-shadow: 0 4px 15px rgba(31,53,100,.025);
}

.panel-heading {
    margin-bottom: 21px;
}

.heading-icon {
    display: inline-grid;
    place-items: center;
    width: 38px;
    height: 38px;
    background: #edf2ff;
    border-radius: 12px;
    font-size: 19px;
    margin-bottom: 13px;
}

.panel-heading h2 {
    font-family: 'Plus Jakarta Sans',sans-serif;
    font-size: 16px;
    font-weight: 800;
    margin-bottom: 6px;
}

.panel-heading p {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.6;
}

/* FORM */

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
    color: #43516b;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 13px;
    background: #f8faff;
    border: 1px solid #e8edf6;
    border-radius: 10px;
    outline: none;
    font-family: inherit;
    font-size: 12px;
    color: var(--text);
    transition: .2s;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #4169f5;
    background: white;
    box-shadow: 0 0 0 3px rgba(65,105,245,.09);
}

.form-group textarea {
    min-height: 115px;
    resize: vertical;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #a8b1c1;
}

.btn-add {
    width: 100%;
    border: none;
    border-radius: 11px;
    padding: 13px;
    background: linear-gradient(100deg,#4169f5,#3157d8);
    color: white;
    font-family: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    margin-top: 5px;
    box-shadow: 0 7px 17px rgba(65,105,245,.2);
    transition: .2s;
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(65,105,245,.3);
}

/* TABEL */

.table-panel {
    min-width: 0;
}

.table-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.table-heading h2 {
    font-family: 'Plus Jakarta Sans',sans-serif;
    font-size: 16px;
    font-weight: 800;
}

.table-heading p {
    font-size: 11px;
    color: var(--muted);
    margin-top: 5px;
}

.table-count {
    background: #edf2ff;
    color: #4169f5;
    padding: 7px 11px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #edf0f6;
    border-radius: 13px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 580px;
}

thead {
    background: #f7f9fd;
}

th {
    padding: 15px 13px;
    text-align: left;
    color: #78859c;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
    border-bottom: 1px solid #edf0f6;
}

td {
    padding: 14px 13px;
    font-size: 11px;
    border-bottom: 1px solid #f0f2f7;
    color: #43516b;
    line-height: 1.7;
}

tbody tr {
    transition: .2s;
}

tbody tr:hover {
    background: #f8faff;
}

tbody tr:last-child td {
    border-bottom: none;
}

.number {
    color: #a1aabc;
    font-weight: 700;
}

.category-name {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 140px;
}

.category-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 10px;
    background: #edf2ff;
    font-size: 17px;
}

.category-name strong {
    color: #253451;
    font-size: 11px;
}

.description {
    color: #8994a9;
    max-width: 250px;
    overflow-wrap: anywhere;
}

/* AKSI */

.actions {
    display: flex;
    align-items: center;
    gap: 7px;
}

.btn-edit,
.btn-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 11px;
    border: none;
    border-radius: 8px;
    font-family: inherit;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
    white-space: nowrap;
}

.btn-edit {
    background: #edf2ff;
    color: #4169f5;
}

.btn-edit:hover {
    background: #4169f5;
    color: white;
}

.btn-delete {
    background: #fff0f0;
    color: #e34e5a;
}

.btn-delete:hover {
    background: #e34e5a;
    color: white;
}

.actions form {
    margin: 0;
}

/* KOSONG */

.empty {
    padding: 35px 15px;
    text-align: center;
    color: #9ba6b8;
    font-size: 12px;
}

.empty-icon {
    font-size: 35px;
    margin-bottom: 10px;
}

/* INFORMASI */

.security-note {
    margin-top: 20px;
    padding: 16px;
    background: #f4f7ff;
    border: 1px solid #e6ecff;
    border-radius: 12px;
    color: #64769e;
    font-size: 10px;
    line-height: 1.8;
}

/* FOOTER */

.footer {
    text-align: center;
    padding: 25px 10px 5px;
    font-size: 10px;
    color: #a2aabb;
    line-height: 1.8;
}

/* RESPONSIVE */

@media(max-width:1150px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width:750px) {

    .layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        min-height: auto;
        padding: 17px 18px;
    }

    .brand {
        margin-bottom: 20px;
    }

    .side-label,
    .sidebar-bottom {
        display: none;
    }

    .side-menu {
        flex-direction: row;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .side-link {
        flex-shrink: 0;
        font-size: 11px;
        padding: 11px 13px;
    }

    .main {
        padding: 20px 16px;
    }

    .page-header {
        padding: 24px;
        align-items: flex-start;
        flex-direction: column;
    }

    .page-header h1 {
        font-size: 23px;
    }

    .back-button {
        align-self: flex-start;
    }
}

@media(max-width:480px) {

    .stats {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-info strong {
        font-size: 20px;
    }

    .panel {
        padding: 17px;
    }

    .table-heading h2 {
        font-size: 14px;
    }
}

</style>

</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">🛠️</div>

            <div class="brand-name">
                TOOL MANAGEMENT
                <span>UKK XII RPL</span>
            </div>

        </div>

        <div class="side-label">MENU UTAMA</div>

        <nav class="side-menu">

            <a href="dashboard.php" class="side-link">
                <span class="side-icon">⌂</span>
                Dashboard
            </a>

            <a href="users.php" class="side-link">
                <span class="side-icon">👥</span>
                Data Pengguna
            </a>

            <a href="kategori.php" class="side-link active">
                <span class="side-icon">🗂️</span>
                Data Kategori
            </a>

            <a href="alat.php" class="side-link">
                <span class="side-icon">🛠️</span>
                Data Alat
            </a>

            <a href="laporan.php" class="side-link">
                <span class="side-icon">📊</span>
                Laporan
            </a>

            <a href="log_aktivitas.php" class="side-link">
                <span class="side-icon">📋</span>
                Log Aktivitas
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="help-card">
                <h4>💡 Informasi Sistem</h4>
                <p>
                    Kelola kategori inventaris agar data peralatan
                    tersusun dengan lebih rapi.
                </p>
            </div>

            <div class="sidebar-footer">
                © 2026 Sistem Peminjaman Alat
                <br>
                UKK Bagoes XII RPL
            </div>

        </div>

    </aside>

    <!-- MAIN -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <div class="breadcrumb">
                Dashboard / <strong>Data Kategori</strong>
            </div>

            <div class="profile">

                <div class="avatar">
                    <?= htmlspecialchars($inisial) ?>
                </div>

                <div class="profile-info">
                    <strong><?= $namaAdmin ?></strong>
                    <span>Administrator</span>
                </div>

            </div>

        </div>

        <!-- HEADER -->

        <section class="page-header">

            <div class="header-content">

                <div class="header-tag">
                    ✦ CATEGORY MANAGEMENT
                </div>

                <h1>Manajemen Kategori Alat</h1>

                <p>
                    Kelola pengelompokan inventaris dan deskripsi
                    kategori peralatan dalam sistem peminjaman.
                </p>

            </div>

            <a href="dashboard.php" class="back-button">
                ← Kembali ke Dashboard
            </a>

        </section>

        <!-- PESAN SUKSES -->

        <?php if (isset($_GET["pesan"])): ?>

            <?php if ($_GET["pesan"] === "tambah"): ?>

                <div class="alert success">
                    ✓ Kategori berhasil ditambahkan dan aktivitas tercatat!
                </div>

            <?php elseif ($_GET["pesan"] === "hapus"): ?>

                <div class="alert success">
                    ✓ Kategori berhasil dihapus dan aktivitas tercatat!
                </div>

            <?php elseif ($_GET["pesan"] === "edit"): ?>

                <div class="alert success">
                    ✓ Kategori berhasil diperbarui!
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="alert danger">
                ⚠ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-icon stat-blue">🗂️</div>

                <div class="stat-info">
                    <span>Total Kategori</span>
                    <strong><?= $totalKategori ?></strong>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon stat-purple">📝</div>

                <div class="stat-info">
                    <span>Kategori dengan Deskripsi</span>
                    <strong><?= $totalDeskripsi ?></strong>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon stat-green">✓</div>

                <div class="stat-info">
                    <span>Kategori Terdaftar</span>
                    <strong><?= $totalKategori ?></strong>
                </div>

            </div>

        </section>

        <!-- CONTENT -->

        <section class="content-grid">

            <!-- FORM TAMBAH -->

            <div class="panel">

                <div class="panel-heading">

                    <div class="heading-icon">➕</div>

                    <h2>Tambah Kategori</h2>

                    <p>
                        Masukkan nama dan deskripsi kategori
                        untuk menambahkan kelompok inventaris baru.
                    </p>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($token) ?>"
                    >

                    <div class="form-group">

                        <label>Nama Kategori</label>

                        <input
                            type="text"
                            name="nama_kategori"
                            placeholder="Contoh: Elektronik"
                            maxlength="100"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Deskripsi Kategori</label>

                        <textarea
                            name="deskripsi"
                            placeholder="Tuliskan deskripsi kategori..."
                        ></textarea>

                    </div>

                    <button
                        type="submit"
                        name="tambah"
                        class="btn-add"
                    >
                        + Tambahkan Kategori
                    </button>

                </form>

                <div class="security-note">
                    🔐 <strong>Informasi Pengelolaan</strong>
                    <br>
                    Pastikan nama kategori mudah dikenali agar
                    memudahkan pengelompokan data alat.
                </div>

            </div>

            <!-- TABEL KATEGORI -->

            <div class="panel table-panel">

                <div class="table-heading">

                    <div>
                        <h2>Daftar Kategori</h2>
                        <p>Seluruh kategori yang tersimpan dalam database.</p>
                    </div>

                    <span class="table-count">
                        <?= $totalKategori ?> KATEGORI
                    </span>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalKategori > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($kategori as $data): ?>

                                <tr>

                                    <td class="number">
                                        <?= $no++ ?>
                                    </td>

                                    <td>

                                        <div class="category-name">

                                            <div class="category-icon">
                                                🗂️
                                            </div>

                                            <strong>
                                                <?= htmlspecialchars($data["nama_kategori"], ENT_QUOTES, 'UTF-8') ?>
                                            </strong>

                                        </div>

                                    </td>

                                    <td>

                                        <div class="description">
                                            <?php
                                            $deskripsi = trim($data["deskripsi"] ?? "");
                                            echo $deskripsi !== ""
                                                ? htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8')
                                                : "Belum ada deskripsi";
                                            ?>
                                        </div>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <a
                                                href="edit_kategori.php?id=<?= (int) $data["id_kategori"] ?>"
                                                class="btn-edit"
                                            >
                                                ✎ Edit
                                            </a>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars($token) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id_kategori"
                                                    value="<?= (int) $data["id_kategori"] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="hapus"
                                                    class="btn-delete"
                                                >
                                                    ✕ Hapus
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="4">

                                    <div class="empty">
                                        <div class="empty-icon">🗂️</div>
                                        Belum ada data kategori.
                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>

        <!-- FOOTER -->

        <footer class="footer">
            © 2026 Sistem Peminjaman Alat · UKK Bagoes XII RPL
            <br>
            Designed for Better Equipment Management
        </footer>

    </main>

</div>

</body>
</html>