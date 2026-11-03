
<?php
session_start();
require_once "config.php";

// ==================================
// CEK HAK AKSES PETUGAS
// ==================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Petugas") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// TOKEN KEAMANAN
// ==================================

if (!isset($_SESSION["csrf_persetujuan"])) {
    $_SESSION["csrf_persetujuan"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_persetujuan"];
$error = "";

// ==================================
// PROSES PERSETUJUAN / PENOLAKAN
// ==================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid.";

    } else {

        $id_peminjaman = (int) ($_POST["id_peminjaman"] ?? 0);
        $aksi = $_POST["aksi"] ?? "";

        if (
            $id_peminjaman <= 0 ||
            !in_array($aksi, ["Disetujui", "Ditolak"], true)
        ) {
            $error = "Data permintaan tidak valid.";

        } else {

            try {

                $pdo->beginTransaction();

                // Kunci data peminjaman
                $cek = $pdo->prepare(
                    "SELECT id_peminjaman, status
                     FROM peminjaman
                     WHERE id_peminjaman = ?
                     FOR UPDATE"
                );

                $cek->execute([$id_peminjaman]);
                $peminjaman = $cek->fetch(PDO::FETCH_ASSOC);

                if (
                    !$peminjaman ||
                    $peminjaman["status"] !== "Menunggu"
                ) {

                    $pdo->rollBack();
                    $error = "Pengajuan tidak ditemukan atau sudah diproses.";

                } elseif ($aksi === "Disetujui") {

                    // ==================================
                    // KUNCI DATA STOK ALAT
                    // ==================================

                    $detail = $pdo->prepare(
                        "SELECT
                            detail_peminjaman.id_alat,
                            detail_peminjaman.jumlah_pinjam,
                            alat.stok,
                            alat.kondisi
                         FROM detail_peminjaman
                         INNER JOIN alat
                            ON detail_peminjaman.id_alat = alat.id_alat
                         WHERE detail_peminjaman.id_peminjaman = ?
                         FOR UPDATE"
                    );

                    $detail->execute([$id_peminjaman]);

                    $daftarDetail = $detail->fetchAll(PDO::FETCH_ASSOC);

                    if (count($daftarDetail) === 0) {

                        $pdo->rollBack();
                        $error = "Detail alat pada pengajuan tidak ditemukan.";

                    } else {

                        // ==================================
                        // CEK STOK MELALUI STORED PROCEDURE
                        // ==================================

                        $cekStok = $pdo->prepare(
                            "CALL sp_cek_stok_peminjaman(?)"
                        );

                        $cekStok->execute([$id_peminjaman]);
                        $cekStok->closeCursor();

                        // ==================================
                        // UBAH STATUS MENJADI DISETUJUI
                        // ==================================

                        $ubah = $pdo->prepare(
                            "UPDATE peminjaman
                             SET status = 'Disetujui',
                                 id_petugas = ?
                             WHERE id_peminjaman = ?
                               AND status = 'Menunggu'"
                        );

                        $ubah->execute([
                            $_SESSION["id"],
                            $id_peminjaman
                        ]);

                        // Pengurangan stok dilakukan otomatis
                        // oleh Trigger trg_kurangi_stok.

                        // ==================================
                        // CATAT AKTIVITAS
                        // ==================================

                        $log = $pdo->prepare(
                            "INSERT INTO log_aktivitas
                                (id_user, aktivitas)
                             VALUES (?, ?)"
                        );

                        $log->execute([
                            $_SESSION["id"],
                            "Menyetujui peminjaman ID " . $id_peminjaman
                        ]);

                        $pdo->commit();

                        header("Location: persetujuan.php?pesan=setuju");
                        exit;
                    }

                } else {

                    // ==================================
                    // PROSES PENOLAKAN
                    // ==================================

                    $ubah = $pdo->prepare(
                        "UPDATE peminjaman
                         SET status = 'Ditolak',
                             id_petugas = ?
                         WHERE id_peminjaman = ?
                           AND status = 'Menunggu'"
                    );

                    $ubah->execute([
                        $_SESSION["id"],
                        $id_peminjaman
                    ]);

                    // Catat aktivitas penolakan
                    $log = $pdo->prepare(
                        "INSERT INTO log_aktivitas
                            (id_user, aktivitas)
                         VALUES (?, ?)"
                    );

                    $log->execute([
                        $_SESSION["id"],
                        "Menolak peminjaman ID " . $id_peminjaman
                    ]);

                    $pdo->commit();

                    header("Location: persetujuan.php?pesan=tolak");
                    exit;
                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $info = $e->errorInfo ?? [];

                if (($info[0] ?? "") === "45000") {

                    $error = $info[2] ??
                        "Stok tidak mencukupi atau kondisi alat tidak baik.";

                } elseif (
                    stripos($e->getMessage(), "sp_cek_stok_peminjaman") !== false
                ) {

                    $error = "Stored Procedure sp_cek_stok_peminjaman belum ditemukan. Periksa kembali di phpMyAdmin.";

                } else {

                    $error = "Terjadi kesalahan saat memproses pengajuan. Periksa konfigurasi database dan Trigger.";
                }
            }
        }
    }
}

// ==================================
// AMBIL DATA PENGAJUAN
// ==================================

$sql = $pdo->query(
    "SELECT
        peminjaman.id_peminjaman,
        users.nama,
        peminjaman.tanggal_pengajuan,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.keperluan,
        peminjaman.status,
        GROUP_CONCAT(
            CONCAT(
                alat.nama_alat,
                ' (',
                detail_peminjaman.jumlah_pinjam,
                ')'
            )
            SEPARATOR ', '
        ) AS daftar_alat
     FROM peminjaman
     INNER JOIN users
        ON peminjaman.id_user = users.id
     LEFT JOIN detail_peminjaman
        ON peminjaman.id_peminjaman = detail_peminjaman.id_peminjaman
     LEFT JOIN alat
        ON detail_peminjaman.id_alat = alat.id_alat
     WHERE peminjaman.status = 'Menunggu'
     GROUP BY
        peminjaman.id_peminjaman,
        users.nama,
        peminjaman.tanggal_pengajuan,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.keperluan,
        peminjaman.status
     ORDER BY peminjaman.id_peminjaman DESC"
);

$pengajuan = $sql->fetchAll(PDO::FETCH_ASSOC);

$totalMenunggu = count($pengajuan);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Persetujuan Peminjaman | Sistem Peminjaman Alat</title>

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
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
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
            width: 38px;
            height: 38px;
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

        /* PESAN */

        .pesan-sukses {
            background: #e9f8ef;
            color: #16804a;
            border: 1px solid #c9eed7;
            padding: 13px 16px;
            border-radius: 11px;
            font-size: 12px;
            margin-bottom: 18px;
        }

        .pesan-error {
            background: #fff0f0;
            color: #c53030;
            border: 1px solid #ffdada;
            padding: 13px 16px;
            border-radius: 11px;
            font-size: 12px;
            margin-bottom: 18px;
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

        .nama {
            color: #263b67;
            font-weight: 700;
        }

        .daftar-alat {
            color: #4169c9;
            font-weight: 600;
            line-height: 1.7;
            min-width: 160px;
        }

        .tanggal {
            white-space: nowrap;
            font-size: 11px;
        }

        .keperluan {
            max-width: 220px;
            line-height: 1.6;
        }

        /* TOMBOL AKSI */

        .form-aksi {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            min-width: 150px;
        }

        .btn-aksi {
            border: none;
            padding: 9px 12px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            color: white;
            cursor: pointer;
            transition: .2s;
        }

        .btn-aksi:hover {
            transform: translateY(-2px);
        }

        .setuju {
            background: #16a36a;
        }

        .setuju:hover {
            background: #128553;
            box-shadow: 0 4px 10px rgba(22, 163, 106, .2);
        }

        .tolak {
            background: #e54848;
        }

        .tolak:hover {
            background: #c92f2f;
            box-shadow: 0 4px 10px rgba(229, 72, 72, .2);
        }

        .kosong {
            text-align: center;
            padding: 40px 20px;
            color: #929caf;
            line-height: 1.8;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {
            .sidebar {
                width: 215px;
            }

            .main {
                padding: 24px 20px;
            }
        }

        @media (max-width: 750px) {
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
            .form-aksi {
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

        <div class="menu-label">MENU PETUGAS</div>

        <nav class="menu">

            <a href="dashboard.php">
                <span>▦</span> Dashboard
            </a>

            <a href="persetujuan.php" class="active">
                <span>✓</span> Persetujuan
            </a>

            <a href="pengembalian.php">
                <span>↩</span> Pengembalian
            </a>

            <a href="laporan.php">
                <span>▤</span> Laporan
            </a>

        </nav>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- TOPBAR -->

        <div class="topbar">

            <h3>Persetujuan Peminjaman</h3>

            <div class="profile">

                <div class="avatar">
                    <?= strtoupper(substr($_SESSION["nama"], 0, 1)) ?>
                </div>

                <div>
                    <div class="profile-name">
                        <?= htmlspecialchars($_SESSION["nama"]) ?>
                    </div>

                    <div class="profile-role">
                        Petugas
                    </div>
                </div>

            </div>

        </div>

        <!-- HEADER -->

        <section class="page-header">

            <h1>Persetujuan Peminjaman</h1>

            <p>
                Periksa pengajuan peminjaman alat dan tentukan apakah
                permintaan dapat disetujui atau ditolak.
            </p>

        </section>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">
                <div class="stat-icon">◷</div>
                <p>Pengajuan Menunggu</p>
                <h2><?= $totalMenunggu ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <p>Perlu Ditinjau</p>
                <h2><?= $totalMenunggu ?></h2>
            </div>

            <div class="stat-card">
                <div class="stat-icon">▤</div>
                <p>Daftar Pengajuan</p>
                <h2><?= $totalMenunggu ?></h2>
            </div>

        </section>

        <!-- DAFTAR PENGAJUAN -->

        <section class="content-card">

            <div class="content-header">

                <div>
                    <h3>Daftar Pengajuan Peminjaman</h3>

                    <p>
                        Tinjau informasi peminjam dan alat sebelum mengambil keputusan.
                    </p>
                </div>

            </div>

            <!-- PESAN SUKSES -->

            <?php if (isset($_GET["pesan"]) && $_GET["pesan"] === "setuju"): ?>

                <div class="pesan-sukses">
                    ✓ Pengajuan berhasil disetujui dan stok alat telah diperbarui!
                </div>

            <?php elseif (isset($_GET["pesan"]) && $_GET["pesan"] === "tolak"): ?>

                <div class="pesan-sukses">
                    ✓ Pengajuan berhasil ditolak!
                </div>

            <?php endif; ?>

            <!-- PESAN ERROR -->

            <?php if ($error !== ""): ?>

                <div class="pesan-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <div class="info">
                Perhatian: Pastikan stok dan kondisi alat memenuhi persyaratan
                sebelum menyetujui pengajuan. Stok akan diperbarui otomatis
                oleh Trigger database setelah persetujuan berhasil.
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Nama Peminjam</th>
                            <th>Daftar Alat</th>
                            <th>Tanggal Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Keperluan</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($totalMenunggu > 0): ?>

                            <?php $no = 1; ?>

                            <?php foreach ($pengajuan as $data): ?>

                                <tr>

                                    <td class="nomor">
                                        <?= $no++ ?>
                                    </td>

                                    <td class="nama">
                                        <?= htmlspecialchars($data["nama"]) ?>
                                    </td>

                                    <td class="daftar-alat">
                                        <?= htmlspecialchars($data["daftar_alat"] ?? "-") ?>
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

                                        <form method="POST" class="form-aksi">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($token) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_peminjaman"
                                                value="<?= (int) $data["id_peminjaman"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="aksi"
                                                value="Disetujui"
                                                class="btn-aksi setuju"
                                                onclick="return confirm('Setujui pengajuan ini?')"
                                            >
                                                ✓ Setujui
                                            </button>

                                            <button
                                                type="submit"
                                                name="aksi"
                                                value="Ditolak"
                                                class="btn-aksi tolak"
                                                onclick="return confirm('Tolak pengajuan ini?')"
                                            >
                                                ✕ Tolak
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="kosong">
                                    <strong>Belum ada pengajuan peminjaman.</strong>
                                    <br>
                                    Semua pengajuan sudah diproses atau belum ada permintaan baru.
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
