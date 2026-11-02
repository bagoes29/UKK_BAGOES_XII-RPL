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
        $jumlah = (int) ($_POST["jumlah"] ?? 0);
        $kondisi = $_POST["kondisi"] ?? "";
        $lokasi = trim($_POST["lokasi"] ?? "");

        $daftarKondisi = [
            "Baik",
            "Rusak Ringan",
            "Rusak Berat"
        ];

        // Periksa kategori
        $cekKategori = $pdo->prepare(
            "SELECT id_kategori FROM kategori WHERE id_kategori = ?"
        );

        $cekKategori->execute([$id_kategori]);

        $kategoriAda = $cekKategori->fetch();

        if (
            $id_kategori <= 0 ||
            !$kategoriAda ||
            $kode_alat === "" ||
            $nama_alat === "" ||
            $jumlah <= 0 ||
            !in_array($kondisi, $daftarKondisi, true)
        ) {
            $error = "Semua data wajib diisi dengan benar.";

        } else {

            // Hitung jumlah alat yang sedang dipinjam
            $jumlahDipinjam = (int) $alat["jumlah"]
                            - (int) $alat["stok"];

            // Jumlah total tidak boleh lebih kecil
            // dari jumlah alat yang sedang dipinjam
            if ($jumlah < $jumlahDipinjam) {

                $error = "Jumlah alat tidak boleh lebih kecil dari jumlah yang sedang dipinjam.";

            } else {

                // Hitung stok baru
                $stokBaru = $jumlah - $jumlahDipinjam;

                try {

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

                    header("Location: alat.php?pesan=edit");
                    exit;

                } catch (PDOException $e) {

                    $error = "Gagal memperbarui data. Kode alat mungkin sudah digunakan.";

                }
            }
        }

        // Tampilkan kembali data jika terjadi kesalahan
        $alat["id_kategori"] = $id_kategori;
        $alat["kode_alat"] = $kode_alat;
        $alat["nama_alat"] = $nama_alat;
        $alat["jumlah"] = $jumlah;
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