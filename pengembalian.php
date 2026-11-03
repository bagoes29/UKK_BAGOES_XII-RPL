
<?php
session_start();
require_once "config.php";

date_default_timezone_set("Asia/Jakarta");

// ==================================
// CEK LOGIN DAN HAK AKSES
// ==================================

if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "Petugas") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// TOKEN KEAMANAN
// ==================================

if (empty($_SESSION["csrf_pengembalian"])) {
    $_SESSION["csrf_pengembalian"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_pengembalian"];
$pesan = "";

// ==================================
// PROSES PENGEMBALIAN
// ==================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $pesan = "Token keamanan tidak valid.";

    } else {

        $id_peminjaman = filter_var(
            $_POST["id_peminjaman"] ?? "",
            FILTER_VALIDATE_INT
        );

        $tanggal_kembali = $_POST["tanggal_kembali"] ?? "";
        $kondisi_kembali = trim($_POST["kondisi_kembali"] ?? "");
        $catatan = trim($_POST["catatan"] ?? "");

        $formatTanggal = DateTime::createFromFormat(
            "!Y-m-d",
            $tanggal_kembali
        );

        $tanggalValid =
            $formatTanggal !== false &&
            $formatTanggal->format("Y-m-d") === $tanggal_kembali;

        $daftarKondisi = [
            "Baik",
            "Rusak Ringan",
            "Rusak Berat"
        ];

        if (
            $id_peminjaman === false ||
            $id_peminjaman <= 0 ||
            !$tanggalValid ||
            !in_array($kondisi_kembali, $daftarKondisi, true)
        ) {
            $pesan = "Data pengembalian tidak valid.";

        } else {

            try {

                $pdo->beginTransaction();

                // Ambil data peminjaman
                $stmt = $pdo->prepare("
                    SELECT
                        tanggal_pinjam,
                        tanggal_rencana_kembali,
                        status
                    FROM peminjaman
                    WHERE id_peminjaman = ?
                    FOR UPDATE
                ");

                $stmt->execute([$id_peminjaman]);

                $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$peminjaman ||
                    $peminjaman["status"] !== "Disetujui"
                ) {
                    throw new Exception(
                        "Peminjaman tidak ditemukan atau belum disetujui."
                    );
                }

                // Validasi tanggal pengembalian
                if ($tanggal_kembali < $peminjaman["tanggal_pinjam"]) {
                    throw new Exception(
                        "Tanggal pengembalian tidak boleh sebelum tanggal pinjam."
                    );
                }

                // Periksa apakah sudah pernah dikembalikan
                $cek = $pdo->prepare("
                    SELECT id_pengembalian
                    FROM pengembalian
                    WHERE id_peminjaman = ?
                ");

                $cek->execute([$id_peminjaman]);

                if ($cek->fetch()) {
                    throw new Exception(
                        "Peminjaman ini sudah dikembalikan."
                    );
                }

                // ==================================
                // HITUNG KETERLAMBATAN
                // ==================================

                $tanggalRencana = new DateTime(
                    $peminjaman["tanggal_rencana_kembali"]
                );

                $tanggalAktual = new DateTime($tanggal_kembali);

                $terlambat = 0;

                if ($tanggalAktual > $tanggalRencana) {
                    $terlambat = (int) $tanggalRencana
                        ->diff($tanggalAktual)->days;
                }

                // Denda Rp5.000 per hari
                $denda = $terlambat * 5000;

                // ==================================
                // SIMPAN DATA PENGEMBALIAN
                // ==================================

                $stmt = $pdo->prepare("
                    INSERT INTO pengembalian
                    (
                        id_peminjaman,
                        id_petugas,
                        tanggal_kembali,
                        terlambat,
                        denda,
                        kondisi_kembali,
                        catatan
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $id_peminjaman,
                    $_SESSION["id"],
                    $tanggal_kembali,
                    $terlambat,
                    $denda,
                    $kondisi_kembali,
                    $catatan
                ]);

                // ==================================
                // CATAT AKTIVITAS
                // ==================================

                $log = $pdo->prepare("
                    INSERT INTO log_aktivitas
                    (id_user, aktivitas)
                    VALUES (?, ?)
                ");

                $log->execute([
                    $_SESSION["id"],
                    "Mencatat pengembalian peminjaman ID "
                    . $id_peminjaman
                    . " dengan denda Rp"
                    . number_format($denda, 0, ",", ".")
                ]);

                // Trigger trg_tambah_stok akan:
                // 1. Menambahkan stok alat.
                // 2. Mengubah status menjadi Selesai.

                $pdo->commit();

                header("Location: pengembalian.php?pesan=berhasil");
                exit;

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $pesan = $e->getMessage();
            }
        }
    }
}

// ==================================
// AMBIL DATA PEMINJAMAN DISETUJUI
// ==================================

$stmt = $pdo->query("
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        u.nama
    FROM peminjaman p
    INNER JOIN users u
        ON p.id_user = u.id
    WHERE p.status = 'Disetujui'
      AND NOT EXISTS (
          SELECT 1
          FROM pengembalian pg
          WHERE pg.id_peminjaman = p.id_peminjaman
      )
    ORDER BY p.tanggal_pinjam ASC
");

$daftar = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPengembalian = count($daftar);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengembalian Alat | Sistem Peminjaman</title>

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
            max-width: 900px;
        }

        .content-header {
            margin-bottom: 20px;
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

        .info {
            background: #eff4ff;
            color: #4169c9;
            border: 1px solid #e0e9ff;
            padding: 13px 15px;
            border-radius: 11px;
            font-size: 12px;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        /* FORM */

        .form-group {
            margin-bottom: 19px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #43516a;
            margin-bottom: 8px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e1e6ef;
            border-radius: 10px;
            background: #fbfcff;
            color: #34415a;
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            outline: none;
            transition: .2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #6384ff;
            background: white;
            box-shadow: 0 0 0 3px rgba(65, 105, 245, .09);
        }

        textarea {
            resize: vertical;
            min-height: 110px;
            line-height: 1.6;
        }

        .form-hint {
            display: block;
            margin-top: 7px;
            font-size: 10px;
            color: #929caf;
        }

        /* TOMBOL */

        .btn-submit {
            width: 100%;
            border: none;
            padding: 14px 18px;
            border-radius: 10px;
            background: linear-gradient(120deg, #3158df, #6384ff);
            color: white;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
            box-shadow: 0 5px 13px rgba(65, 105, 245, .18);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 17px rgba(65, 105, 245, .25);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #929caf;
            line-height: 1.8;
        }

        .empty-icon {
            font-size: 38px;
            margin-bottom: 12px;
            color: #4169f5;
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
                padding: 18px;
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

            <a href="persetujuan.php">
                <span>✓</span> Persetujuan
            </a>

            <a href="pengembalian.php" class="active">
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

            <h3>Pengembalian Alat</h3>

            <div class="profile">

                <div class="avatar">
                    <?= htmlspecialchars(strtoupper(substr($_SESSION["nama"], 0, 1))) ?>
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

            <h1>Form Pengembalian Alat</h1>

            <p>
                Catat pengembalian alat, periksa kondisi barang,
                dan hitung keterlambatan serta denda secara otomatis.
            </p>

        </section>

        <!-- STATISTIK -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-icon">↩</div>

                <p>Menunggu Pengembalian</p>

                <h2><?= $totalPengembalian ?></h2>

            </div>

            <div class="stat-card">

                <div class="stat-icon">▤</div>

                <p>Total Form Pengembalian</p>

                <h2><?= $totalPengembalian ?></h2>

            </div>

            <div class="stat-card">

                <div class="stat-icon">✓</div>

                <p>Status Layanan</p>

                <h2 style="font-size: 17px;">Aktif</h2>

            </div>

        </section>

        <!-- FORM PENGEMBALIAN -->

        <section class="content-card">

            <div class="content-header">

                <h3>Input Data Pengembalian</h3>

                <p>
                    Lengkapi informasi berikut untuk mencatat pengembalian alat.
                </p>

            </div>

            <!-- PESAN SUKSES -->

            <?php if (
                isset($_GET["pesan"]) &&
                $_GET["pesan"] === "berhasil"
            ): ?>

                <div class="pesan-sukses">
                    ✓ Pengembalian berhasil dicatat dan aktivitas tersimpan!
                </div>

            <?php endif; ?>

            <!-- PESAN ERROR -->

            <?php if ($pesan !== ""): ?>

                <div class="pesan-error">
                    <?= htmlspecialchars($pesan) ?>
                </div>

            <?php endif; ?>

            <div class="info">
                <strong>Informasi:</strong>
                Denda keterlambatan sebesar Rp5.000 per hari.
                Jika pengembalian melewati tanggal rencana kembali,
                jumlah denda akan dihitung otomatis.
            </div>

            <?php if ($totalPengembalian > 0): ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($token) ?>"
                    >

                    <div class="form-group">

                        <label>Pilih Peminjaman</label>

                        <select name="id_peminjaman" required>

                            <option value="">
                                -- Pilih Peminjaman --
                            </option>

                            <?php foreach ($daftar as $item): ?>

                                <option value="<?= (int) $item["id_peminjaman"] ?>">

                                    Peminjaman #<?= (int) $item["id_peminjaman"] ?>

                                    - <?= htmlspecialchars($item["nama"]) ?>

                                    - Rencana kembali:
                                    <?= htmlspecialchars($item["tanggal_rencana_kembali"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <span class="form-hint">
                            Pilih peminjaman yang sudah disetujui dan belum dikembalikan.
                        </span>

                    </div>

                    <div class="form-group">

                        <label>Tanggal Pengembalian</label>

                        <input
                            type="date"
                            name="tanggal_kembali"
                            value="<?= date('Y-m-d') ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Kondisi Alat Saat Dikembalikan</label>

                        <select name="kondisi_kembali" required>

                            <option value="">
                                -- Pilih Kondisi --
                            </option>

                            <option value="Baik">
                                Baik
                            </option>

                            <option value="Rusak Ringan">
                                Rusak Ringan
                            </option>

                            <option value="Rusak Berat">
                                Rusak Berat
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Catatan</label>

                        <textarea
                            name="catatan"
                            rows="4"
                            placeholder="Masukkan catatan pengembalian jika ada..."
                        ></textarea>

                        <span class="form-hint">
                            Bagian ini boleh dikosongkan jika tidak ada catatan.
                        </span>

                    </div>

                    <button type="submit" class="btn-submit">
                        ✓ Simpan Pengembalian
                    </button>

                </form>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">✓</div>

                    <strong>Tidak Ada Peminjaman yang Perlu Dikembalikan</strong>

                    <p>
                        Saat ini belum ada peminjaman berstatus Disetujui
                        yang menunggu pengembalian.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>
