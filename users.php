
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
// TOKEN KEAMANAN CSRF
// ======================================

if (!isset($_SESSION["csrf_users"])) {
    $_SESSION["csrf_users"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_users"];
$error = "";

// ======================================
// TAMBAH PENGGUNA
// ======================================

if (isset($_POST["tambah"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";

    } else {

        $nama = trim($_POST["nama"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["role"] ?? "";

        $daftarRole = ["Admin", "Petugas", "Peminjam"];

        if (
            $nama === "" ||
            $username === "" ||
            $password === "" ||
            !in_array($role, $daftarRole, true)
        ) {
            $error = "Semua data wajib diisi dengan benar.";

        } else {

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            try {

                $pdo->beginTransaction();

                $sql = $pdo->prepare(
                    "INSERT INTO users (nama, username, password, role)
                     VALUES (?, ?, ?, ?)"
                );

                $sql->execute([
                    $nama,
                    $username,
                    $passwordHash,
                    $role
                ]);

                $id_user_baru = $pdo->lastInsertId();

                // CATAT LOG AKTIVITAS

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas (id_user, aktivitas)
                     VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Menambahkan pengguna: " .
                    $nama .
                    " (Username: " . $username .
                    ", Role: " . $role .
                    ", ID: " . $id_user_baru . ")"
                ]);

                $pdo->commit();

                header("Location: users.php?pesan=tambah");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Username sudah digunakan atau data tidak valid.";
            }
        }
    }
}

// ======================================
// HAPUS PENGGUNA
// ======================================

if (isset($_POST["hapus"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan hapus tidak valid.";

    } else {

        $id = (int) ($_POST["id"] ?? 0);

        if ($id === (int) $_SESSION["id"]) {

            $error = "Anda tidak dapat menghapus akun sendiri.";

        } elseif ($id <= 0) {

            $error = "ID pengguna tidak valid.";

        } else {

            try {

                $pdo->beginTransaction();

                $cek = $pdo->prepare(
                    "SELECT id, nama, username, role
                     FROM users
                     WHERE id = ?
                     FOR UPDATE"
                );

                $cek->execute([$id]);

                $dataUser = $cek->fetch(PDO::FETCH_ASSOC);

                if (!$dataUser) {
                    throw new Exception("Pengguna tidak ditemukan.");
                }

                $sql = $pdo->prepare(
                    "DELETE FROM users WHERE id = ?"
                );

                $sql->execute([$id]);

                // CATAT LOG AKTIVITAS

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas (id_user, aktivitas)
                     VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Menghapus pengguna: " .
                    $dataUser["nama"] .
                    " (Username: " . $dataUser["username"] .
                    ", Role: " . $dataUser["role"] .
                    ", ID: " . $id . ")"
                ]);

                $pdo->commit();

                header("Location: users.php?pesan=hapus");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Pengguna tidak dapat dihapus karena masih memiliki riwayat transaksi atau data terkait.";

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
// AMBIL DATA PENGGUNA
// ======================================

$sql = $pdo->query(
    "SELECT id, nama, username, role
     FROM users
     ORDER BY id ASC"
);

$users = $sql->fetchAll(PDO::FETCH_ASSOC);

// ======================================
// STATISTIK PENGGUNA
// ======================================

$totalAdmin = 0;
$totalPetugas = 0;
$totalPeminjam = 0;

foreach ($users as $user) {

    if ($user["role"] === "Admin") {
        $totalAdmin++;
    } elseif ($user["role"] === "Petugas") {
        $totalPetugas++;
    } elseif ($user["role"] === "Peminjam") {
        $totalPeminjam++;
    }
}

$totalUsers = count($users);

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Pengguna | Sistem Peminjaman Alat</title>

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
    background: linear-gradient(180deg, #111c38, #17294d);
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
    font-family: 'Plus Jakarta Sans', sans-serif;
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
    content: "♙";
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
    font-family: 'Plus Jakarta Sans', sans-serif;
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

/* STATISTICS */

.stats {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
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

.stat-orange {
    background: #fff3e8;
}

.stat-info span {
    display: block;
    color: var(--muted);
    font-size: 10px;
    margin-bottom: 5px;
}

.stat-info strong {
    font-family: 'Plus Jakarta Sans', sans-serif;
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
    animation: appear .3s ease;
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

.panel-heading h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 16px;
    font-weight: 800;
    margin-bottom: 6px;
}

.panel-heading p {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.6;
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
.form-group select {
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
.form-group select:focus {
    border-color: #4169f5;
    background: white;
    box-shadow: 0 0 0 3px rgba(65,105,245,.09);
}

.form-group input::placeholder {
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

/* TABLE */

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
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 16px;
    font-weight: 800;
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
    min-width: 600px;
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

.user-name {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 140px;
}

.user-avatar {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 10px;
    background: #edf2ff;
    color: #4169f5;
    font-weight: 800;
    font-size: 12px;
}

.user-name strong {
    display: block;
    color: #253451;
    font-size: 11px;
}

.user-name small {
    display: block;
    color: #a0aabd;
    font-size: 9px;
    margin-top: 3px;
}

/* ROLE BADGES */

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 7px;
    font-size: 9px;
    font-weight: 800;
}

.badge-admin {
    background: #f1ebff;
    color: #8051d9;
}

.badge-petugas {
    background: #e8f8ff;
    color: #1694ba;
}

.badge-peminjam {
    background: #e9f9ef;
    color: #1c9b60;
}

/* ACTIONS */

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

.self-label {
    display: inline-block;
    background: #f1f3f7;
    color: #8994a9;
    padding: 7px 9px;
    border-radius: 8px;
    font-size: 9px;
    font-weight: 700;
}

/* EMPTY DATA */

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

/* SECURITY NOTE */

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

/* ANIMATION */

@keyframes appear {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* RESPONSIVE */

@media(max-width: 1150px) {
    .content-grid {
        grid-template-columns: 1fr;
    }

    .stats {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }
}

@media(max-width: 750px) {
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

@media(max-width: 480px) {
    .stats {
        gap: 10px;
    }

    .stat-card {
        padding: 13px;
        gap: 9px;
    }

    .stat-icon {
        width: 38px;
        height: 38px;
        font-size: 17px;
    }

    .stat-info strong {
        font-size: 19px;
    }

    .stat-info span {
        font-size: 9px;
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

            <a href="users.php" class="side-link active">
                <span class="side-icon">👥</span>
                Data Pengguna
            </a>

            <a href="kategori.php" class="side-link">
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
                    Kelola akun pengguna dan atur hak akses
                    sesuai kebutuhan aplikasi.
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
                Dashboard / <strong>Data Pengguna</strong>
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

        <!-- PAGE HEADER -->

        <section class="page-header">

            <div class="header-content">

                <div class="header-tag">
                    ✦ USER MANAGEMENT
                </div>

                <h1>Manajemen Pengguna</h1>

                <p>
                    Kelola akun, informasi pengguna, dan hak akses
                    Admin, Petugas, serta Peminjam dalam satu tempat.
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
                    ✓ Pengguna berhasil ditambahkan dan aktivitas tercatat!
                </div>

            <?php elseif ($_GET["pesan"] === "edit"): ?>

                <div class="alert success">
                    ✓ Data pengguna berhasil diperbarui!
                </div>

            <?php elseif ($_GET["pesan"] === "hapus"): ?>

                <div class="alert success">
                    ✓ Pengguna berhasil dihapus dan aktivitas tercatat!
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <!-- PESAN ERROR -->

        <?php if ($error !== ""): ?>

            <div class="alert danger">
                ⚠ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-icon stat-blue">👥</div>

                <div class="stat-info">
                    <span>Total Pengguna</span>
                    <strong><?= $totalUsers ?></strong>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon stat-purple">👑</div>

                <div class="stat-info">
                    <span>Administrator</span>
                    <strong><?= $totalAdmin ?></strong>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon stat-cyan">🧑‍💼</div>

                <div class="stat-info">
                    <span>Petugas</span>
                    <strong><?= $totalPetugas ?></strong>
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-icon stat-green">🎓</div>

                <div class="stat-info">
                    <span>Peminjam</span>
                    <strong><?= $totalPeminjam ?></strong>
                </div>

            </div>

        </section>

        <!-- CONTENT -->

        <section class="content-grid">

            <!-- FORM TAMBAH -->

            <div class="panel">

                <div class="panel-heading">

                    <div class="heading-icon">➕</div>

                    <h2>Tambah Pengguna</h2>

                    <p>
                        Masukkan informasi akun baru untuk
                        mendaftarkan pengguna ke dalam sistem.
                    </p>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($token) ?>"
                    >

                    <div class="form-group">

                        <label>Nama Lengkap</label>

                        <input
                            type="text"
                            name="nama"
                            placeholder="Masukkan nama lengkap"
                            maxlength="100"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Username</label>

                        <input
                            type="text"
                            name="username"
                            placeholder="Buat username"
                            maxlength="50"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Password</label>

                        <input
                            type="password"
                            name="password"
                            placeholder="Masukkan password"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Hak Akses / Role</label>

                        <select name="role" required>

                            <option value="">Pilih role pengguna</option>

                            <option value="Admin">Admin</option>

                            <option value="Petugas">Petugas</option>

                            <option value="Peminjam">Peminjam</option>

                        </select>

                    </div>

                    <button
                        type="submit"
                        name="tambah"
                        class="btn-add"
                    >
                        + Tambahkan Pengguna
                    </button>

                </form>

                <div class="security-note">
                    🔐 <strong>Keamanan Akun</strong>
                    <br>
                    Password pengguna akan disimpan menggunakan
                    sistem hashing sebelum masuk ke database.
                </div>

            </div>

            <!-- TABEL PENGGUNA -->

            <div class="panel table-panel">

                <div class="table-heading">

                    <div>
                        <h2>Daftar Pengguna</h2>
                        <p style="font-size:11px;color:#8994a9;margin-top:5px;">
                            Informasi seluruh akun yang terdaftar.
                        </p>
                    </div>

                    <span class="table-count">
                        <?= $totalUsers ?> PENGGUNA
                    </span>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>No</th>
                                <th>Pengguna</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($totalUsers > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($users as $user): ?>

                                <?php
                                    $namaUser = htmlspecialchars(
                                        $user["nama"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    $usernameUser = htmlspecialchars(
                                        $user["username"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    $roleUser = htmlspecialchars(
                                        $user["role"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                    $inisialUser = strtoupper(
                                        substr($user["nama"], 0, 1)
                                    );

                                    $badgeClass = "badge-peminjam";

                                    if ($user["role"] === "Admin") {
                                        $badgeClass = "badge-admin";
                                    } elseif ($user["role"] === "Petugas") {
                                        $badgeClass = "badge-petugas";
                                    }
                                ?>

                                <tr>

                                    <td class="number">
                                        <?= $no++ ?>
                                    </td>

                                    <td>

                                        <div class="user-name">

                                            <div class="user-avatar">
                                                <?= htmlspecialchars($inisialUser) ?>
                                            </div>

                                            <div>
                                                <strong><?= $namaUser ?></strong>
                                                <small>ID Pengguna #<?= (int) $user["id"] ?></small>
                                            </div>

                                        </div>

                                    </td>

                                    <td>
                                        <?= $usernameUser ?>
                                    </td>

                                    <td>

                                        <span class="badge <?= $badgeClass ?>">
                                            <?= $roleUser ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="actions">

                                            <?php if ((int) $user["id"] !== (int) $_SESSION["id"]): ?>

                                                <a
                                                    href="edit_user.php?id=<?= (int) $user["id"] ?>"
                                                    class="btn-edit"
                                                >
                                                    ✎ Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars($token) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $user["id"] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="hapus"
                                                        class="btn-delete"
                                                    >
                                                        ✕ Hapus
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span class="self-label">
                                                    Akun sendiri
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="5">

                                    <div class="empty">
                                        <div class="empty-icon">👥</div>
                                        Belum ada data pengguna.
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