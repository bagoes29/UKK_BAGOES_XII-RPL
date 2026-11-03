
<?php
session_start();
require_once "config.php";

// ==================================
// CEK HAK AKSES PEMINJAM
// ==================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Peminjam") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// AMBIL DATA ALAT DAN KATEGORI
// ==================================

$sql = $pdo->query(
    "SELECT
        alat.id_alat,
        alat.kode_alat,
        alat.nama_alat,
        alat.jumlah,
        alat.stok,
        alat.kondisi,
        alat.lokasi,
        kategori.nama_kategori
     FROM alat
     INNER JOIN kategori
        ON alat.id_kategori = kategori.id_kategori
     ORDER BY alat.nama_alat ASC"
);

$dataAlat = $sql->fetchAll(PDO::FETCH_ASSOC);

// ==================================
// HITUNG STATISTIK
// ==================================

$totalAlat = count($dataAlat);
$totalTersedia = 0;
$totalHabis = 0;
$totalTidakBaik = 0;

foreach ($dataAlat as $item) {
    if ($item["kondisi"] !== "Baik") {
        $totalTidakBaik++;
    } elseif ((int) $item["stok"] > 0) {
        $totalTersedia++;
    } else {
        $totalHabis++;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar Alat | Sistem Peminjaman Alat</title>

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
            gap: 15px;
            margin-bottom: 28px;
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
            line-height: 1.6;
        }

        /* STATISTIK */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid #edf0f6;
            border-radius: 17px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(30, 50, 90, .035);
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
            font-size: 18px;
            margin-bottom: 13px;
        }

        .stat-card p {
            font-size: 11px;
            color: #8792a7;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-card h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 25px;
            color: #263b67;
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
            line-height: 1.6;
        }

        /* TABEL */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
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

        .kode {
            color: #4169f5;
            font-weight: 700;
            white-space: nowrap;
        }

        .nama-alat {
            color: #263b67;
            font-weight: 700;
        }

        .stok {
            font-weight: 700;
            color: #263b67;
        }

        .badge-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .tersedia {
            color: #16804a;
            background: #e9f8ef;
        }

        .habis {
            color: #dc2626;
            background: #fff0f0;
        }

        .tidak-baik {
            color: #b7791f;
            background: #fff7e6;
        }

        .kondisi {
            font-weight: 600;
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

        @media (max-width: 1100px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

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
                padding: 15px;
            }

            .stat-card h2 {
                font-size: 22px;
            }

            .content-card {
                padding: 17px;
            }

            .content-header {
                align-items: flex-start;
                flex-direction: column;
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

        <div class="menu-label">MENU PEMINJAM</div>

        <nav class="menu">

            <a href="dashboard.php">
                <span>▦</span> Dashboard
            </a>

            <a href="daftar_alat.php" class="active">
                <span>▣</span> Daftar Alat
            </a>

            <a href="ajukan_peminjaman.php">
                <span>＋</span> Ajukan Peminjaman
            </a>

            <a href="riwayat_peminjaman.php">
                <span>◷</span> Riwayat Peminjaman
            </a>

        </nav>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <h3>Daftar Alat</h3>

            <div class="profile">

                <div class="avatar">
                    <?= strtoupper(substr($_SESSION["nama"], 0, 1)) ?>
                </div>

                <div>
                    <div class="profile-name">
                        <?= htmlspecialchars($_SESSION["nama"]) ?>
                    </div>

                    <div class="profile-role">
                        Peminjam
                    </div>
                </div>

            </div>

        </div>

        <!-- HEADER -->

        <section class="page-header">

            <h1>Daftar Alat</h1>

            <p>
                Lihat informasi alat, kategori, stok, kondisi, dan lokasi alat
                sebelum mengajukan peminjaman.
            </p>

        </section>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">
                <div class="stat-icon">▣</div>
                <p>Total Jenis Alat</p>
                <h2><?= $totalAlat ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <p>Alat Tersedia</p>
                <h2><?= $totalTersedia ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">!</div>
                <p>Stok Habis</p>
                <h2><?= $totalHabis ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⚙</div>
                <p>Kondisi Tidak Baik</p>
                <h2><?= $totalTidakBaik ?></h2>
            </div>

        </section>

        <!-- DAFTAR ALAT -->

        <section class="content-card">

            <div class="content-header">

                <div>
                    <h3>Data Alat</h3>

                    <p>
                        Berikut daftar alat yang terdaftar dalam sistem.
                    </p>
                </div>

                <a href="ajukan_peminjaman.php" class="btn">
                    + Ajukan Peminjaman
                </a>

            </div>

            <div class="info">
                Pastikan alat memiliki kondisi Baik dan stok tersedia sebelum
                mengajukan peminjaman.
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Alat</th>
                            <th>Nama Alat</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                            <th>Kondisi</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($totalAlat > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($dataAlat as $alat): ?>

                                <tr>

                                    <td class="nomor">
                                        <?= $no++ ?>
                                    </td>

                                    <td class="kode">
                                        <?= htmlspecialchars($alat["kode_alat"]) ?>
                                    </td>

                                    <td class="nama-alat">
                                        <?= htmlspecialchars($alat["nama_alat"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($alat["nama_kategori"]) ?>
                                    </td>

                                    <td class="stok">
                                        <?= (int) $alat["stok"] ?>
                                    </td>

                                    <td class="kondisi">
                                        <?= htmlspecialchars($alat["kondisi"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($alat["lokasi"] ?? "-") ?>
                                    </td>

                                    <td>

                                        <?php if ($alat["kondisi"] !== "Baik"): ?>

                                            <span class="badge-status tidak-baik">
                                                Tidak dapat dipinjam
                                            </span>

                                        <?php elseif ((int) $alat["stok"] > 0): ?>

                                            <span class="badge-status tersedia">
                                                Tersedia
                                            </span>

                                        <?php else: ?>

                                            <span class="badge-status habis">
                                                Stok Habis
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="kosong">
                                    Belum ada data alat.
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