
<?php
session_start();
require_once "config.php";

// ==================================
// CEK HAK AKSES ADMIN
// ==================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Admin") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// TOKEN KEAMANAN
// ==================================

if (!isset($_SESSION["csrf_alat"])) {
    $_SESSION["csrf_alat"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_alat"];
$error = "";

// ==================================
// AMBIL ID ALAT
// ==================================

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    exit("ID alat tidak valid.");
}

// ==================================
// AMBIL DATA ALAT
// ==================================

$sql = $pdo->prepare(
    "SELECT * FROM alat WHERE id_alat = ?"
);

$sql->execute([$id]);
$alat = $sql->fetch(PDO::FETCH_ASSOC);

if (!$alat) {
    exit("Data alat tidak ditemukan.");
}

// ==================================
// AMBIL DATA KATEGORI
// ==================================

$sqlKategori = $pdo->query(
    "SELECT * FROM kategori ORDER BY nama_kategori ASC"
);

$daftarKategori = $sqlKategori->fetchAll(PDO::FETCH_ASSOC);

// ==================================
// PROSES UPDATE DATA ALAT
// ==================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid.";

    } else {

        $id_kategori = (int) ($_POST["id_kategori"] ?? 0);
        $kode_alat = trim($_POST["kode_alat"] ?? "");
        $nama_alat = trim($_POST["nama_alat"] ?? "");
        $jumlah = filter_var(
            $_POST["jumlah"] ?? "",
            FILTER_VALIDATE_INT
        );
        $kondisi = $_POST["kondisi"] ?? "";
        $lokasi = trim($_POST["lokasi"] ?? "");

        $daftarKondisi = [
            "Baik",
            "Rusak Ringan",
            "Rusak Berat"
        ];

        // ==================================
        // VALIDASI DATA
        // ==================================

        if (
            $id_kategori <= 0 ||
            $kode_alat === "" ||
            $nama_alat === "" ||
            $jumlah === false ||
            $jumlah <= 0 ||
            !in_array($kondisi, $daftarKondisi, true)
        ) {

            $error = "Semua data wajib diisi dengan benar.";

        } else {

            try {

                // Mulai transaksi database
                $pdo->beginTransaction();

                // Kunci data alat agar tidak diedit bersamaan
                $cekAlat = $pdo->prepare(
                    "SELECT *
                     FROM alat
                     WHERE id_alat = ?
                     FOR UPDATE"
                );

                $cekAlat->execute([$id]);

                $dataLama = $cekAlat->fetch(PDO::FETCH_ASSOC);

                if (!$dataLama) {
                    throw new Exception(
                        "Data alat tidak ditemukan."
                    );
                }

                // ==================================
                // VALIDASI KATEGORI
                // ==================================

                $cekKategori = $pdo->prepare(
                    "SELECT id_kategori
                     FROM kategori
                     WHERE id_kategori = ?"
                );

                $cekKategori->execute([$id_kategori]);

                if (!$cekKategori->fetch()) {
                    throw new Exception(
                        "Kategori alat tidak ditemukan."
                    );
                }

                // ==================================
                // HITUNG STOK YANG SEDANG DIPINJAM
                // ==================================

                $jumlahDipinjam =
                    (int) $dataLama["jumlah"] -
                    (int) $dataLama["stok"];

                // Pengaman jika data stok sebelumnya tidak sesuai
                $jumlahDipinjam = max(0, $jumlahDipinjam);

                if ($jumlah < $jumlahDipinjam) {

                    throw new Exception(
                        "Jumlah alat tidak boleh lebih kecil dari jumlah yang sedang dipinjam."
                    );
                }

                // Hitung stok tersedia yang baru
                $stokBaru = $jumlah - $jumlahDipinjam;

                // ==================================
                // AMBIL NAMA KATEGORI
                // ==================================

                $namaKategoriLama = $dataLama["id_kategori"];
                $namaKategoriBaru = $id_kategori;

                $kategoriLama = $pdo->prepare(
                    "SELECT nama_kategori
                     FROM kategori
                     WHERE id_kategori = ?"
                );

                $kategoriLama->execute([
                    $dataLama["id_kategori"]
                ]);

                $hasilKategoriLama = $kategoriLama->fetchColumn();

                if ($hasilKategoriLama !== false) {
                    $namaKategoriLama = $hasilKategoriLama;
                }

                $kategoriBaru = $pdo->prepare(
                    "SELECT nama_kategori
                     FROM kategori
                     WHERE id_kategori = ?"
                );

                $kategoriBaru->execute([$id_kategori]);

                $hasilKategoriBaru = $kategoriBaru->fetchColumn();

                if ($hasilKategoriBaru !== false) {
                    $namaKategoriBaru = $hasilKategoriBaru;
                }

                // ==================================
                // UPDATE DATA ALAT
                // ==================================

                $update = $pdo->prepare(
                    "UPDATE alat SET
                        id_kategori = ?,
                        kode_alat = ?,
                        nama_alat = ?,
                        jumlah = ?,
                        stok = ?,
                        kondisi = ?,
                        lokasi = ?
                     WHERE id_alat = ?"
                );

                $update->execute([
                    $id_kategori,
                    $kode_alat,
                    $nama_alat,
                    $jumlah,
                    $stokBaru,
                    $kondisi,
                    $lokasi,
                    $id
                ]);

                // ==================================
                // BANDINGKAN DATA LAMA DAN BARU
                // ==================================

                $perubahan = [];

                if (
                    (int) $dataLama["id_kategori"] !== $id_kategori
                ) {
                    $perubahan[] =
                        "Kategori: " . $namaKategoriLama .
                        " menjadi " . $namaKategoriBaru;
                }

                if ($dataLama["kode_alat"] !== $kode_alat) {
                    $perubahan[] =
                        "Kode alat: " . $dataLama["kode_alat"] .
                        " menjadi " . $kode_alat;
                }

                if ($dataLama["nama_alat"] !== $nama_alat) {
                    $perubahan[] =
                        "Nama alat: " . $dataLama["nama_alat"] .
                        " menjadi " . $nama_alat;
                }

                if ((int) $dataLama["jumlah"] !== $jumlah) {
                    $perubahan[] =
                        "Jumlah: " . $dataLama["jumlah"] .
                        " menjadi " . $jumlah;
                }

                if ((int) $dataLama["stok"] !== $stokBaru) {
                    $perubahan[] =
                        "Stok: " . $dataLama["stok"] .
                        " menjadi " . $stokBaru;
                }

                if ($dataLama["kondisi"] !== $kondisi) {
                    $perubahan[] =
                        "Kondisi: " . $dataLama["kondisi"] .
                        " menjadi " . $kondisi;
                }

                if (($dataLama["lokasi"] ?? "") !== $lokasi) {
                    $perubahan[] =
                        "Lokasi: " .
                        ($dataLama["lokasi"] ?: "-") .
                        " menjadi " .
                        ($lokasi ?: "-");
                }

                // ==================================
                // CATAT AKTIVITAS EDIT ALAT
                // ==================================

                if (count($perubahan) > 0) {

                    $keterangan = implode("; ", $perubahan);

                } else {

                    $keterangan = "Data disimpan tanpa perubahan nilai.";
                }

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas
                    (id_user, aktivitas)
                    VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Mengubah data alat ID " .
                    $id . " (" . $nama_alat . "): " .
                    $keterangan
                ]);

                // ==================================
                // SIMPAN TRANSAKSI
                // ==================================

                $pdo->commit();

                header("Location: alat.php?pesan=edit");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Gagal memperbarui data. Kode alat mungkin sudah digunakan.";

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();

            }
        }

        // Tampilkan kembali data setelah validasi gagal
        $alat["id_kategori"] = $id_kategori;
        $alat["kode_alat"] = $kode_alat;
        $alat["nama_alat"] = $nama_alat;

        if ($jumlah !== false) {
            $alat["jumlah"] = $jumlah;
        }

        $alat["kondisi"] = $kondisi;
        $alat["lokasi"] = $lokasi;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Data Alat</title>

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
            max-width: 550px;
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
        }

        input, select, button {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border-radius: 5px;
        }

        input, select {
            border: 1px solid #ccc;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .kembali {
            color: #2563eb;
            text-decoration: none;
        }

        .kembali:hover {
            text-decoration: underline;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 5px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>EDIT DATA ALAT</h2>

    <a href="alat.php" class="kembali">
        ← Kembali ke Data Alat
    </a>

    <?php if ($error !== ""): ?>

        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="id"
            value="<?= $id ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($token) ?>"
        >

        <label>Kategori Alat</label>

        <select name="id_kategori" required>

            <option value="">
                Pilih Kategori
            </option>

            <?php foreach ($daftarKategori as $kategori): ?>

                <option
                    value="<?= (int) $kategori["id_kategori"] ?>"
                    <?= (int) $alat["id_kategori"] === (int) $kategori["id_kategori"] ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($kategori["nama_kategori"]) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <label>Kode Alat</label>

        <input
            type="text"
            name="kode_alat"
            value="<?= htmlspecialchars($alat["kode_alat"]) ?>"
            required
        >

        <label>Nama Alat</label>

        <input
            type="text"
            name="nama_alat"
            value="<?= htmlspecialchars($alat["nama_alat"]) ?>"
            required
        >

        <label>Jumlah Alat</label>

        <input
            type="number"
            name="jumlah"
            min="1"
            value="<?= (int) $alat["jumlah"] ?>"
            required
        >

        <label>Kondisi Alat</label>

        <select name="kondisi" required>

            <option value="Baik"
                <?= $alat["kondisi"] === "Baik" ? "selected" : "" ?>>
                Baik
            </option>

            <option value="Rusak Ringan"
                <?= $alat["kondisi"] === "Rusak Ringan" ? "selected" : "" ?>>
                Rusak Ringan
            </option>

            <option value="Rusak Berat"
                <?= $alat["kondisi"] === "Rusak Berat" ? "selected" : "" ?>>
                Rusak Berat
            </option>

        </select>

        <label>Lokasi Alat</label>

        <input
            type="text"
            name="lokasi"
            value="<?= htmlspecialchars($alat["lokasi"] ?? "") ?>"
        >

        <button type="submit">
            Simpan Perubahan
        </button>

    </form>

</div>

</body>
</html>
