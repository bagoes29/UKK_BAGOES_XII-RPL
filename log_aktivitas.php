```php
<?php
session_start();

require_once "config.php";

// ==================================
// CEK LOGIN
// ==================================

if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

// ==================================
// KHUSUS ADMIN
// ==================================

if ($_SESSION["role"] !== "Admin") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// AMBIL DATA LOG AKTIVITAS
// ==================================

$stmt = $pdo->query("
    SELECT
        log_aktivitas.id_log,
        log_aktivitas.id_user,
        log_aktivitas.aktivitas,
        log_aktivitas.waktu,
        users.nama
    FROM log_aktivitas
    LEFT JOIN users
        ON log_aktivitas.id_user = users.id
    ORDER BY log_aktivitas.id_log DESC
    LIMIT 100
");

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAktivitas = count($logs);
$totalPengguna = count(array_unique(array_filter(
    array_column($logs, "id_user")
)));

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Log Aktivitas | Sistem Peminjaman Alat</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f4f7fc;
            color: #24324b;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            background: #111c38;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .brand {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 19px;
            font-weight: 800;
            padding: 0 12px;
            margin-bottom: 38px;
            line-height: 1.5;
        }

        .brand span {
            color: #7192ff;
        }

        .menu-label {
            color: #8492b2;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            padding: 0 12px;
            margin-bottom: 12px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #aebbd5;
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 11px;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .menu a:hover {
            background: #202f52;
            color: white;
        }

        .menu a.active {
            background: linear-gradient(135deg, #4169f5, #6384ff);
            color: white;
            box-shadow: 0 6px 16px rgba(65, 105, 245, .25);
        }

        /* MAIN */

        .main {
            flex: 1;
            min-width: 0;
            padding: 28px 35px;
        }

        /* TOPBAR */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            gap: 15px;
        }

        .topbar h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 19px;
            font-weight: 800;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 11px;
            background: white;
            padding: 9px 15px;
            border-radius: 13px;
            border: 1px solid #edf0f6;
        }

        .avatar {
            width: 36px;
            height: 36px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 11px;
            background: #e9efff;
            color: #4169f5;
            font-weight: 800;
        }

        .profile-name {
            font-size: 12px;
            font-weight: 700;
        }

        .profile-role {
            font-size: 10px;
            color: #8b96aa;
        }

        /* HEADER */

        .page-header {
            background: linear-gradient(120deg, #3158df, #6384ff);
            padding: 28px 30px;
            border-radius: 20px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 10px 25px rgba(65, 105, 245, .15);
        }

        .page-header h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 23px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .page-header p {
            font-size: 13px;
            opacity: .88;
        }

        /* STATISTIK */

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid #edf0f6;
            border-radius: 17px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(30, 50, 90, .035);
        }

        .stat-card p {
            font-size: 12px;
            color: #8792a7;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .stat-card h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 27px;
            color: #263b67;
        }

        .stat-icon {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #edf2ff;
            color: #4169f5;
            font-size: 19px;
            margin-bottom: 14px;
        }

        /* CONTENT */

        .content-card {
            background: white;
            border: 1px solid #edf0f6;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(30, 50, 90, .035);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
        }

        .content-header h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 16px;
            font-weight: 800;
        }

        .content-header p {
            color: #929caf;
            font-size: 11px;
            margin-top: 5px;
        }

        .info {
            background: #eff4ff;
            color: #4169c9;
            border: 1px solid #e0e9ff;
            padding: 13px 15px;
            border-radius: 11px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        /* TABEL */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th {
            background: #f5f7fc;
            color: #7b879d;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
            padding: 15px 13px;
            border-bottom: 1px solid #edf0f6;
            white-space: nowrap;
        }

        td {
            padding: 15px 13px;
            border-bottom: 1px solid #f0f2f7;
            font-size: 12px;
            color: #526078;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #fafbff;
        }

        .nomor {
            color: #9aa5b8;
            font-weight: 700;
        }

        .id-log {
            color: #4169f5;
            font-weight: 700;
        }

        .nama-user {
            color: #263b67;
            font-weight: 700;
        }

        .aktivitas {
            color: #526078;
            max-width: 400px;
            line-height: 1.6;
        }

        .waktu {
            white-space: nowrap;
            font-size: 11px;
        }

        .kosong {
            text-align: center;
            padding: 35px;
            color: #929caf;
        }

        /* BUTTON */

        .btn {
            border: none;
            background: linear-gradient(135deg, #4169f5, #6384ff);
            color: white;
            padding: 11px 17px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: .2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 13px rgba(65, 105, 245, .2);
        }

        .btn-secondary {
            background: #edf2ff;
            color: #4169f5;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {
            .sidebar {
                width: 215px;
            }

            .main {
                padding: 24px 20px;
            }
        }

        @media (max-width: 650px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 18px;
            }

            .brand {
                margin-bottom: 18px;
            }

            .menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .menu-label {
                margin-bottom: 10px;
            }

            .menu a {
                margin-bottom: 0;
                font-size: 11px;
                padding: 11px;
            }

            .main {
                padding: 20px 14px;
            }

            .topbar {
                align-items: flex-start;
            }

            .page-header {
                padding: 23px 20px;
            }

            .page-header h1 {
                font-size: 19px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card h2 {
                font-size: 23px;
            }

            .content-card {
                padding: 17px;
            }

            .content-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        /* CETAK */

        @media print {
            body {
                background: white;
            }

            .sidebar,
            .topbar,
            .page-header,
            .stats,
            .info,
            .content-header .btn {
                display: none !important;
            }

            .layout {
                display: block;
            }

            .main {
                padding: 0;
            }

            .content-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }

            .content-header {
                display: block;
            }

            .content-header h3 {
                font-size: 18px;
            }

            table {
                min-width: 0;
            }

            th, td {
                border: 1px solid #ddd;
                color: black;
            }
        }
    </style>
</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">
            SISTEM <span>PEMINJAMAN</span>
            <br>ALAT
        </div>

        <div class="menu-label">MENU UTAMA</div>

        <nav class="menu">

            <a href="dashboard.php">
                <span>▦</span> Dashboard
            </a>

            <a href="users.php">
                <span>♙</span> Data Pengguna
            </a>

            <a href="kategori.php">
                <span>▤</span> Data Kategori
            </a>

            <a href="alat.php">
                <span>▣</span> Data Alat
            </a>

            <a href="laporan.php">
                <span>▥</span> Laporan
            </a>

            <a href="log_aktivitas.php" class="active">
                <span>◷</span> Log Aktivitas
            </a>

        </nav>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <h3>Log Aktivitas</h3>

            <div class="profile">

                <div class="avatar">
                    <?= strtoupper(substr($_SESSION["nama"], 0, 1)) ?>
                </div>

                <div>
                    <div class="profile-name">
                        <?= htmlspecialchars($_SESSION["nama"]) ?>
                    </div>

                    <div class="profile-role">
                        Administrator
                    </div>
                </div>

            </div>

        </div>

        <!-- PAGE HEADER -->

        <section class="page-header">

            <h1>Log Aktivitas Pengguna</h1>

            <p>
                Pantau riwayat aktivitas pengguna dalam sistem peminjaman alat.
            </p>

        </section>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-icon">◷</div>

                <p>Total Aktivitas Ditampilkan</p>

                <h2><?= $totalAktivitas ?></h2>

            </div>

            <div class="stat-card">

                <div class="stat-icon">♙</div>

                <p>Jumlah Pengguna Beraktivitas</p>

                <h2><?= $totalPengguna ?></h2>

            </div>

        </section>

        <!-- TABEL LOG -->

        <section class="content-card">

            <div class="content-header">

                <div>
                    <h3>Riwayat Aktivitas</h3>

                    <p>
                        Menampilkan maksimal 100 aktivitas terbaru pengguna.
                    </p>
                </div>

                <button class="btn" onclick="window.print()">
                    Cetak Log Aktivitas
                </button>

            </div>

            <div class="info">
                Informasi: Data aktivitas ditampilkan berdasarkan waktu terbaru.
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Log</th>
                            <th>Nama Pengguna</th>
                            <th>Aktivitas</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($totalAktivitas > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($logs as $log): ?>

                                <tr>

                                    <td class="nomor">
                                        <?= $no++ ?>
                                    </td>

                                    <td class="id-log">
                                        #<?= (int) $log["id_log"] ?>
                                    </td>

                                    <td class="nama-user">
                                        <?= htmlspecialchars($log["nama"] ?? "Pengguna dihapus") ?>
                                    </td>

                                    <td class="aktivitas">
                                        <?= htmlspecialchars($log["aktivitas"]) ?>
                                    </td>

                                    <td class="waktu">
                                        <?= htmlspecialchars($log["waktu"]) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="kosong">
                                    Belum ada aktivitas yang tercatat.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>
```