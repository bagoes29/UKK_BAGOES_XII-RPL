
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
// AMBIL RIWAYAT PEMINJAMAN
// ==================================

$sql = $pdo->prepare(
    "SELECT
        peminjaman.id_peminjaman,
        peminjaman.tanggal_pengajuan,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.keperluan,
        peminjaman.status,
        alat.kode_alat,
        alat.nama_alat,
        detail_peminjaman.jumlah_pinjam
    FROM peminjaman
    INNER JOIN detail_peminjaman
        ON peminjaman.id_peminjaman = detail_peminjaman.id_peminjaman
    INNER JOIN alat
        ON detail_peminjaman.id_alat = alat.id_alat
    WHERE peminjaman.id_user = ?
    ORDER BY peminjaman.id_peminjaman DESC"
);

$sql->execute([$_SESSION["id"]]);

$riwayat = $sql->fetchAll(PDO::FETCH_ASSOC);

// ==================================
// HITUNG STATISTIK
// ==================================

$totalRiwayat = count($riwayat);
$totalMenunggu = 0;
$totalDisetujui = 0;
$totalDitolak = 0;
$totalSelesai = 0;

foreach ($riwayat as $data) {
    switch ($data["status"]) {
        case "Menunggu":
            $totalMenunggu++;
            break;

        case "Disetujui":
            $totalDisetujui++;
            break;

        case "Ditolak":
            $totalDitolak++;
            break;

        case "Selesai":
            $totalSelesai++;
            break;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Peminjaman | Sistem Peminjaman Alat</title>

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
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid #edf0f6;
            border-radius: 17px;
            padding: 19px;
            box-shadow: 0 4px 15px rgba(30, 50, 90, .035);
        }

        .stat-icon {
            width: 37px;
            height: 37px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #edf2ff;
            color: #4169f5;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .stat-card p {
            font-size: 10px;
            color: #8792a7;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-card h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 24px;
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
            min-width: 1050px;
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

        .jumlah {
            font-weight: 700;
            color: #263b67;
        }

        .tanggal {
            white-space: nowrap;
            font-size: 11px;
        }

        .keperluan {
            max-width: 230px;
            line-height: 1.6;
        }

        /* STATUS */

        .badge-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .menunggu {
            color: #b7791f;
            background: #fff7e6;
        }

        .disetujui {
            color: #16804a;
            background: #e9f8ef;
        }

        .ditolak {
            color: #dc2626;
            background: #fff0f0;
        }

        .selesai {
            color: #3158df;
            background: #edf2ff;
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

        /* RESPONSIVE */

        @media (max-width: 1150px) {
            .stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 215px;
            }

            .main {
                padding: 24px 20px;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

        /* CETAK */

        @media print {
            body {
                background: white;
            }

            .sidebar,
            .topbar,
            .page-header {
                display: none !important;
            }

            .layout {
                display: block;
            }

            .main {
                padding: 0;
            }

            .stats {
                margin-bottom: 15px;
            }

            .content-card {
                border: none;
                box-shadow: none;
                padding: 0;
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

        <div class="menu-label">MENU PEMINJAM</div>

        <nav class="menu">

            <a href="dashboard.php">
                <span>▦</span> Dashboard
            </a>

            <a href="daftar_alat.php">
                <span>▣</span> Daftar Alat
            </a>

            <a href="ajukan_peminjaman.php">
                <span>＋</span> Ajukan Peminjaman
            </a>

            <a href="riwayat_peminjaman.php" class="active">
                <span>◷</span> Riwayat Peminjaman
            </a>

        </nav>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <h3>Riwayat Peminjaman</h3>

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

            <h1>Riwayat Peminjaman</h1>

            <p>
                Pantau status dan informasi seluruh pengajuan peminjaman alat
                yang pernah kamu kirimkan.
            </p>

        </section>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">
                <div class="stat-icon">▤</div>
                <p>Total Riwayat</p>
                <h2><?= $totalRiwayat ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">◷</div>
                <p>Menunggu</p>
                <h2><?= $totalMenunggu ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <p>Disetujui</p>
                <h2><?= $totalDisetujui ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">×</div>
                <p>Ditolak</p>
                <h2><?= $totalDitolak ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">★</div>
                <p>Selesai</p>
                <h2><?= $totalSelesai ?></h2>
            </div>

        </section>

        <!-- TABEL RIWAYAT -->

        <section class="content-card">

            <div class="content-header">

                <div>
                    <h3>Data Riwayat Peminjaman</h3>

                    <p>
                        Daftar pengajuan peminjaman berdasarkan akun yang sedang login.
                    </p>
                </div>

                <a href="ajukan_peminjaman.php" class="btn">
                    + Ajukan Peminjaman
                </a>

            </div>

            <div class="info">
                Informasi: Status pengajuan akan diperbarui setelah Petugas
                memeriksa permintaan peminjaman.
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Alat</th>
                            <th>Nama Alat</th>
                            <th>Jumlah</th>
                            <th>Tanggal Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Keperluan</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($totalRiwayat > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($riwayat as $data): ?>

                                <tr>

                                    <td class="nomor">
                                        <?= $no++ ?>
                                    </td>

                                    <td class="kode">
                                        <?= htmlspecialchars($data["kode_alat"]) ?>
                                    </td>

                                    <td class="nama-alat">
                                        <?= htmlspecialchars($data["nama_alat"]) ?>
                                    </td>

                                    <td class="jumlah">
                                        <?= (int) $data["jumlah_pinjam"] ?>
                                    </td>

                                    <td class="tanggal">
                                        <?= htmlspecialchars($data["tanggal_pinjam"]) ?>
                                    </td>

                                    <td class="tanggal">
                                        <?= htmlspecialchars($data["tanggal_rencana_kembali"]) ?>
                                    </td>

                                    <td class="keperluan">
                                        <?= htmlspecialchars($data["keperluan"]) ?>
                                    </td>

                                    <td>

                                        <?php if ($data["status"] === "Menunggu"): ?>

                                            <span class="badge-status menunggu">
                                                Menunggu
                                            </span>

                                        <?php elseif ($data["status"] === "Disetujui"): ?>

                                            <span class="badge-status disetujui">
                                                Disetujui
                                            </span>

                                        <?php elseif ($data["status"] === "Ditolak"): ?>

                                            <span class="badge-status ditolak">
                                                Ditolak
                                            </span>

                                        <?php elseif ($data["status"] === "Selesai"): ?>

                                            <span class="badge-status selesai">
                                                Selesai
                                            </span>

                                        <?php else: ?>

                                            <span class="badge-status menunggu">
                                                <?= htmlspecialchars($data["status"]) ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="8" class="kosong">
                                    Belum ada riwayat peminjaman.
                                    <br><br>
                                    Silakan ajukan peminjaman alat terlebih dahulu.
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
