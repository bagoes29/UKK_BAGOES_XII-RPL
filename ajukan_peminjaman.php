
<?php
session_start();
require_once "config.php";

date_default_timezone_set("Asia/Jakarta");

// ==================================
// CEK HAK AKSES PEMINJAM
// ==================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Peminjam") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// TOKEN KEAMANAN
// ==================================

if (!isset($_SESSION["csrf_peminjaman"])) {
    $_SESSION["csrf_peminjaman"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_peminjaman"];
$error = "";

// Nilai awal formulir
$id_alat = "";
$jumlah = "";
$tanggal_pinjam = "";
$tanggal_kembali = "";
$keperluan = "";

$hariIni = date("Y-m-d");

// ==================================
// PROSES PENGAJUAN PEMINJAMAN
// ==================================

if (isset($_POST["ajukan"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";

    } else {

        $id_alat = filter_var(
            $_POST["id_alat"] ?? "",
            FILTER_VALIDATE_INT
        );

        $jumlah = filter_var(
            $_POST["jumlah"] ?? "",
            FILTER_VALIDATE_INT
        );

        $tanggal_pinjam = $_POST["tanggal_pinjam"] ?? "";
        $tanggal_kembali = $_POST["tanggal_kembali"] ?? "";
        $keperluan = trim($_POST["keperluan"] ?? "");

        // Periksa format tanggal
        $formatPinjam = DateTime::createFromFormat(
            "!Y-m-d",
            $tanggal_pinjam
        );

        $formatKembali = DateTime::createFromFormat(
            "!Y-m-d",
            $tanggal_kembali
        );

        $tanggalValid =
            $formatPinjam !== false &&
            $formatPinjam->format("Y-m-d") === $tanggal_pinjam &&
            $formatKembali !== false &&
            $formatKembali->format("Y-m-d") === $tanggal_kembali;

        // Validasi data
        if (
            $id_alat === false ||
            $id_alat <= 0 ||
            $jumlah === false ||
            $jumlah <= 0 ||
            $keperluan === "" ||
            !$tanggalValid
        ) {
            $error = "Semua data wajib diisi dengan benar.";

        } elseif (
            $tanggal_pinjam < $hariIni ||
            $tanggal_kembali < $tanggal_pinjam
        ) {
            $error = "Tanggal peminjaman atau pengembalian tidak valid.";

        } else {

            try {

                // Periksa alat dan stok tersedia
                $cekAlat = $pdo->prepare(
                    "SELECT id_alat, nama_alat, stok, kondisi
                     FROM alat
                     WHERE id_alat = ?"
                );

                $cekAlat->execute([$id_alat]);

                $alat = $cekAlat->fetch(PDO::FETCH_ASSOC);

                if (!$alat) {

                    $error = "Alat tidak ditemukan.";

                } elseif ($alat["kondisi"] !== "Baik") {

                    $error = "Alat tidak dapat dipinjam karena kondisinya tidak baik.";

                } elseif ((int) $alat["stok"] <= 0) {

                    $error = "Stok alat sedang habis.";

                } elseif ($jumlah > (int) $alat["stok"]) {

                    $error = "Jumlah yang diminta melebihi stok tersedia.";

                } else {

                    // Mulai transaksi database
                    $pdo->beginTransaction();

                    // Simpan data utama peminjaman
                    $sql = $pdo->prepare(
                        "INSERT INTO peminjaman
                        (
                            id_user,
                            tanggal_pinjam,
                            tanggal_rencana_kembali,
                            keperluan,
                            status
                        )
                        VALUES (?, ?, ?, ?, 'Menunggu')"
                    );

                    $sql->execute([
                        $_SESSION["id"],
                        $tanggal_pinjam,
                        $tanggal_kembali,
                        $keperluan
                    ]);

                    // Ambil ID peminjaman
                    $id_peminjaman = $pdo->lastInsertId();

                    // Simpan detail peminjaman
                    $detail = $pdo->prepare(
                        "INSERT INTO detail_peminjaman
                        (
                            id_peminjaman,
                            id_alat,
                            jumlah_pinjam
                        )
                        VALUES (?, ?, ?)"
                    );

                    $detail->execute([
                        $id_peminjaman,
                        $id_alat,
                        $jumlah
                    ]);

                    // Catat aktivitas
                    $log = $pdo->prepare(
                        "INSERT INTO log_aktivitas
                        (id_user, aktivitas)
                        VALUES (?, ?)"
                    );

                    $log->execute([
                        $_SESSION["id"],
                        "Mengajukan peminjaman ID " . $id_peminjaman
                    ]);

                    // Simpan transaksi
                    $pdo->commit();

                    header("Location: ajukan_peminjaman.php?pesan=berhasil");
                    exit;
                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Pengajuan gagal disimpan. Silakan coba kembali.";
            }
        }
    }
}

// ==================================
// AMBIL DAFTAR ALAT TERSEDIA
// ==================================

$sqlAlat = $pdo->query(
    "SELECT id_alat, kode_alat, nama_alat, stok
     FROM alat
     WHERE stok > 0 AND kondisi = 'Baik'
     ORDER BY nama_alat ASC"
);

$daftarAlat = $sqlAlat->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajukan Peminjaman | Sistem Peminjaman Alat</title>

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

        /* CONTENT */

        .content-card {
            background: white;
            border: 1px solid #edf0f6;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(30, 50, 90, .035);
            max-width: 850px;
        }

        .content-header {
            margin-bottom: 22px;
        }

        .content-header h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: #263b67;
        }

        .content-header p {
            color: #929caf;
            font-size: 12px;
            margin-top: 6px;
            line-height: 1.6;
        }

        /* ALERT */

        .alert {
            padding: 14px 16px;
            border-radius: 11px;
            margin-bottom: 20px;
            font-size: 12px;
            line-height: 1.6;
        }

        .pesan-sukses {
            background: #e9f8ef;
            color: #16804a;
            border: 1px solid #c9eed7;
        }

        .pesan-error {
            background: #fff0f0;
            color: #b91c1c;
            border: 1px solid #ffd6d6;
        }

        .info {
            background: #eff4ff;
            color: #4169c9;
            border: 1px solid #e0e9ff;
            padding: 14px 16px;
            border-radius: 11px;
            margin-bottom: 22px;
            font-size: 12px;
            line-height: 1.7;
        }

        /* FORM */

        .form-group {
            margin-bottom: 19px;
        }

        label {
            display: block;
            color: #344563;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .required {
            color: #e05252;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #e1e6ef;
            border-radius: 10px;
            background: #fff;
            color: #344563;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            outline: none;
            transition: .2s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #6384ff;
            box-shadow: 0 0 0 3px rgba(65, 105, 245, .10);
        }

        input::placeholder,
        textarea::placeholder {
            color: #aab3c2;
        }

        textarea {
            resize: vertical;
            min-height: 115px;
            line-height: 1.6;
        }

        .form-hint {
            color: #929caf;
            font-size: 10px;
            margin-top: 7px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        /* BUTTON */

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            border: none;
            background: linear-gradient(135deg, #4169f5, #6384ff);
            color: white;
            padding: 13px 20px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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

        .btn-secondary:hover {
            background: #e2eaff;
            box-shadow: none;
        }

        /* EMPTY */

        .empty-state {
            background: #fff7e6;
            color: #9a6815;
            border: 1px solid #f5e5bd;
            border-radius: 12px;
            padding: 22px;
            font-size: 13px;
            line-height: 1.7;
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

            .content-card {
                padding: 19px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
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

            <a href="ajukan_peminjaman.php" class="active">
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

            <h3>Ajukan Peminjaman</h3>

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

            <h1>Form Pengajuan Peminjaman</h1>

            <p>
                Lengkapi formulir berikut untuk mengajukan peminjaman alat.
                Pengajuan akan diperiksa oleh Petugas.
            </p>

        </section>

        <!-- FORM CARD -->

        <section class="content-card">

            <div class="content-header">

                <h3>Informasi Peminjaman</h3>

                <p>
                    Nama peminjam:
                    <strong><?= htmlspecialchars($_SESSION["nama"]) ?></strong>
                </p>

            </div>

            <!-- PESAN BERHASIL -->

            <?php if (isset($_GET["pesan"]) && $_GET["pesan"] === "berhasil"): ?>

                <div class="alert pesan-sukses">
                    ✓ Pengajuan peminjaman berhasil dikirim!
                    Silakan menunggu persetujuan Petugas.
                </div>

            <?php endif; ?>

            <!-- PESAN ERROR -->

            <?php if ($error !== ""): ?>

                <div class="alert pesan-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if (count($daftarAlat) > 0): ?>

                <div class="info">
                    Pastikan jumlah alat yang diajukan tidak melebihi stok tersedia.
                    Periksa juga tanggal peminjaman dan tanggal rencana pengembalian
                    sebelum mengirim formulir.
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($token) ?>"
                    >

                    <!-- PILIH ALAT -->

                    <div class="form-group">

                        <label>
                            Pilih Alat <span class="required">*</span>
                        </label>

                        <select name="id_alat" required>

                            <option value="">
                                -- Pilih Alat --
                            </option>

                            <?php foreach ($daftarAlat as $item): ?>

                                <option
                                    value="<?= (int) $item["id_alat"] ?>"
                                    <?= (string) $id_alat === (string) $item["id_alat"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($item["nama_alat"]) ?>
                                    (<?= htmlspecialchars($item["kode_alat"]) ?>)
                                    - Stok: <?= (int) $item["stok"] ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <p class="form-hint">
                            Hanya alat dengan kondisi Baik dan stok tersedia yang ditampilkan.
                        </p>

                    </div>

                    <!-- JUMLAH -->

                    <div class="form-group">

                        <label>
                            Jumlah yang Dipinjam <span class="required">*</span>
                        </label>

                        <input
                            type="number"
                            name="jumlah"
                            min="1"
                            value="<?= htmlspecialchars((string) $jumlah) ?>"
                            placeholder="Masukkan jumlah alat"
                            required
                        >

                    </div>

                    <!-- TANGGAL -->

                    <div class="form-row">

                        <div class="form-group">

                            <label>
                                Tanggal Pinjam <span class="required">*</span>
                            </label>

                            <input
                                type="date"
                                name="tanggal_pinjam"
                                min="<?= htmlspecialchars($hariIni) ?>"
                                value="<?= htmlspecialchars($tanggal_pinjam) ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                Tanggal Rencana Kembali <span class="required">*</span>
                            </label>

                            <input
                                type="date"
                                name="tanggal_kembali"
                                min="<?= htmlspecialchars($hariIni) ?>"
                                value="<?= htmlspecialchars($tanggal_kembali) ?>"
                                required
                            >

                        </div>

                    </div>

                    <!-- KEPERLUAN -->

                    <div class="form-group">

                        <label>
                            Keperluan Peminjaman <span class="required">*</span>
                        </label>

                        <textarea
                            name="keperluan"
                            rows="4"
                            placeholder="Tuliskan keperluan peminjaman alat..."
                            required
                        ><?= htmlspecialchars($keperluan) ?></textarea>

                        <p class="form-hint">
                            Jelaskan tujuan penggunaan alat yang akan dipinjam.
                        </p>

                    </div>

                    <!-- BUTTON -->

                    <div class="form-actions">

                        <button type="submit" name="ajukan" class="btn">
                            ✓ Kirim Pengajuan Peminjaman
                        </button>

                        <a href="dashboard.php" class="btn btn-secondary">
                            Kembali ke Dashboard
                        </a>

                    </div>

                </form>

            <?php else: ?>

                <div class="empty-state">
                    <strong>Belum ada alat yang tersedia.</strong>
                    <br>
                    Saat ini belum ada alat dengan kondisi Baik dan stok lebih dari
                    nol. Silakan periksa kembali nanti.
                </div>

                <div class="form-actions">

                    <a href="daftar_alat.php" class="btn">
                        Lihat Daftar Alat
                    </a>

                    <a href="dashboard.php" class="btn btn-secondary">
                        Kembali ke Dashboard
                    </a>

                </div>

            <?php endif; ?>

            <div class="info" style="margin-top: 22px; margin-bottom: 0;">
                <strong>Informasi:</strong>
                Setelah dikirim, pengajuan akan memiliki status
                <strong>Menunggu</strong> sampai diperiksa oleh Petugas.
            </div>

        </section>

    </main>

</div>

</body>
</html>
