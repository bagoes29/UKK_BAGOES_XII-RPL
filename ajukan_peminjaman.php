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

    // Periksa token keamanan
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

                    // Ambil ID peminjaman yang baru dibuat
                    $id_peminjaman = $pdo->lastInsertId();

                    // Simpan detail alat yang dipinjam
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

                    // Simpan transaksi
                    $pdo->commit();

                    header("Location: ajukan_peminjaman.php?pesan=berhasil");
                    exit;
                }

            } catch (PDOException $e) {

                // Batalkan transaksi jika terjadi kesalahan
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Pengajuan gagal disimpan. Silakan coba kembali.";
            }
        }
    }
}

// ==================================
// AMBIL DAFTAR ALAT YANG TERSEDIA
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

    <title>Pengajuan Peminjaman Alat</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 650px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        h2 {
            color: #1e40af;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, select, textarea, button {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border-radius: 5px;
        }

        input, select, textarea {
            border: 1px solid #ccc;
        }

        textarea {
            resize: vertical;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
            margin-top: 20px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .kembali {
            color: #2563eb;
            text-decoration: none;
        }

        .pesan-sukses {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 5px;
        }

        .pesan-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 5px;
        }

        .info {
            background: #eff6ff;
            color: #1e40af;
            padding: 12px;
            border-radius: 5px;
            margin-top: 15px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>FORM PENGAJUAN PEMINJAMAN</h2>

    <a href="dashboard.php" class="kembali">
        ← Kembali ke Dashboard
    </a>

    <p>
        Nama Peminjam:
        <strong>
            <?= htmlspecialchars($_SESSION["nama"]) ?>
        </strong>
    </p>

    <!-- PESAN BERHASIL -->

    <?php if (isset($_GET["pesan"]) && $_GET["pesan"] === "berhasil"): ?>

        <p class="pesan-sukses">
            Pengajuan peminjaman berhasil dikirim!
            Silakan menunggu persetujuan Petugas.
        </p>

    <?php endif; ?>

    <!-- PESAN ERROR -->

    <?php if ($error !== ""): ?>

        <p class="pesan-error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>

    <!-- FORM PEMINJAMAN -->

    <?php if (count($daftarAlat) > 0): ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($token) ?>"
            >

            <label>Pilih Alat</label>

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
                        - Stok: <?= (int) $item["stok"] ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label>Jumlah yang Dipinjam</label>

            <input
                type="number"
                name="jumlah"
                min="1"
                value="<?= htmlspecialchars((string) $jumlah) ?>"
                placeholder="Masukkan jumlah alat"
                required
            >

            <label>Tanggal Pinjam</label>

            <input
                type="date"
                name="tanggal_pinjam"
                min="<?= $hariIni ?>"
                value="<?= htmlspecialchars($tanggal_pinjam) ?>"
                required
            >

            <label>Tanggal Rencana Kembali</label>

            <input
                type="date"
                name="tanggal_kembali"
                min="<?= $hariIni ?>"
                value="<?= htmlspecialchars($tanggal_kembali) ?>"
                required
            >

            <label>Keperluan Peminjaman</label>

            <textarea
                name="keperluan"
                rows="4"
                placeholder="Tuliskan keperluan peminjaman alat"
                required
            ><?= htmlspecialchars($keperluan) ?></textarea>

            <button type="submit" name="ajukan">
                Kirim Pengajuan Peminjaman
            </button>

        </form>

    <?php else: ?>

        <p class="pesan-error">
            Saat ini belum ada alat yang tersedia untuk dipinjam.
        </p>

    <?php endif; ?>

    <div class="info">
        Pengajuan yang dikirim akan memiliki status
        <strong>Menunggu</strong> sampai diperiksa oleh Petugas.
    </div>

</div>

</body>
</html>