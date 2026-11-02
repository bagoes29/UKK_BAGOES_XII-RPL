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
// TAMBAH DATA ALAT
// ==================================

if (isset($_POST["tambah"])) {

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

        if (
            $id_kategori <= 0 ||
            $kode_alat === "" ||
            $nama_alat === "" ||
            $jumlah <= 0 ||
            !in_array($kondisi, $daftarKondisi, true)
        ) {
            $error = "Semua data wajib diisi dengan benar.";

        } else {

            try {

                // Stok awal sama dengan jumlah alat
                $sql = $pdo->prepare(
                    "INSERT INTO alat
                    (id_kategori, kode_alat, nama_alat, jumlah, stok, kondisi, lokasi)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $sql->execute([
                    $id_kategori,
                    $kode_alat,
                    $nama_alat,
                    $jumlah,
                    $jumlah,
                    $kondisi,
                    $lokasi
                ]);

                header("Location: alat.php?pesan=tambah");
                exit;

            } catch (PDOException $e) {

                $error = "Gagal menambahkan alat. Periksa kode alat dan kategori.";

            }
        }
    }
}

// ==================================
// HAPUS DATA ALAT
// ==================================

if (isset($_POST["hapus"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan hapus tidak valid.";

    } else {

        $id = (int) ($_POST["id_alat"] ?? 0);

        if ($id <= 0) {

            $error = "ID alat tidak valid.";

        } else {

            try {

                $sql = $pdo->prepare(
                    "DELETE FROM alat WHERE id_alat = ?"
                );

                $sql->execute([$id]);

                header("Location: alat.php?pesan=hapus");
                exit;

            } catch (PDOException $e) {

                $error = "Alat tidak dapat dihapus karena masih digunakan dalam riwayat peminjaman.";

            }
        }
    }
}

// ==================================
// AMBIL DATA KATEGORI
// ==================================

$sqlKategori = $pdo->query(
    "SELECT * FROM kategori ORDER BY nama_kategori ASC"
);

$daftarKategori = $sqlKategori->fetchAll(PDO::FETCH_ASSOC);

// ==================================
// AMBIL DATA ALAT
// ==================================

$sqlAlat = $pdo->query(
    "SELECT
        alat.*,
        kategori.nama_kategori
     FROM alat
     INNER JOIN kategori
        ON alat.id_kategori = kategori.id_kategori
     ORDER BY alat.id_alat DESC"
);

$dataAlat = $sqlAlat->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Alat</title>

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
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        h2 {
            color: #1e40af;
        }

        h3 {
            color: #334155;
            margin-top: 25px;
        }

        .kembali {
            display: inline-block;
            margin-bottom: 15px;
            color: #2563eb;
            text-decoration: none;
        }

        label {
            display: block;
            margin-top: 12px;
        }

        input, select, button {
            width: 100%;
            padding: 11px;
            margin: 7px 0;
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #1e40af;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        /* TOMBOL EDIT */

        .edit {
            color: #2563eb;
            font-weight: bold;
            text-decoration: none;
        }

        .edit:hover {
            text-decoration: underline;
        }

        /* TOMBOL HAPUS */

        .hapus {
            background: #dc2626;
            padding: 8px 12px;
            width: auto;
        }

        .hapus:hover {
            background: #b91c1c;
        }

        .form-hapus {
            display: inline;
            margin: 0;
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

        .tabel-wrapper {
            overflow-x: auto;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>DATA ALAT</h2>

    <a href="dashboard.php" class="kembali">
        ← Kembali ke Dashboard
    </a>

    <!-- PESAN BERHASIL -->

    <?php if (isset($_GET["pesan"])): ?>

        <?php if ($_GET["pesan"] === "tambah"): ?>

            <p class="pesan-sukses">
                Data alat berhasil ditambahkan!
            </p>

        <?php elseif ($_GET["pesan"] === "hapus"): ?>

            <p class="pesan-sukses">
                Data alat berhasil dihapus!
            </p>

        <?php elseif ($_GET["pesan"] === "edit"): ?>

            <p class="pesan-sukses">
                Data alat berhasil diperbarui!
            </p>

        <?php endif; ?>

    <?php endif; ?>


    <!-- PESAN ERROR -->

    <?php if ($error !== ""): ?>

        <p class="pesan-error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <!-- FORM TAMBAH ALAT -->

    <h3>Tambah Data Alat</h3>

    <?php if (count($daftarKategori) > 0): ?>

        <form method="POST">

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

                    <option value="<?= (int) $kategori["id_kategori"] ?>">
                        <?= htmlspecialchars($kategori["nama_kategori"]) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <label>Kode Alat</label>

            <input
                type="text"
                name="kode_alat"
                placeholder="Contoh: ALT001"
                required
            >


            <label>Nama Alat</label>

            <input
                type="text"
                name="nama_alat"
                placeholder="Contoh: Laptop"
                required
            >


            <label>Jumlah Alat</label>

            <input
                type="number"
                name="jumlah"
                min="1"
                placeholder="Masukkan jumlah alat"
                required
            >


            <label>Kondisi Alat</label>

            <select name="kondisi" required>

                <option value="">
                    Pilih Kondisi
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


            <label>Lokasi Alat</label>

            <input
                type="text"
                name="lokasi"
                placeholder="Contoh: Laboratorium RPL"
            >


            <button type="submit" name="tambah">
                Tambah Data Alat
            </button>

        </form>

    <?php else: ?>

        <p class="pesan-error">
            Belum ada kategori. Tambahkan kategori terlebih dahulu
            melalui halaman Data Kategori.
        </p>

    <?php endif; ?>


    <!-- TABEL DATA ALAT -->

    <h3>Daftar Alat</h3>

    <div class="tabel-wrapper">

        <table>

            <tr>
                <th>No</th>
                <th>Kode Alat</th>
                <th>Nama Alat</th>
                <th>Kategori</th>
                <th>Jumlah</th>
                <th>Stok Tersedia</th>
                <th>Kondisi</th>
                <th>Lokasi</th>
                <th>Aksi</th>
            </tr>

            <?php if (count($dataAlat) > 0): ?>

                <?php $no = 1; ?>

                <?php foreach ($dataAlat as $alat): ?>

                    <tr>

                        <td>
                            <?= $no++ ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($alat["kode_alat"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($alat["nama_alat"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($alat["nama_kategori"]) ?>
                        </td>

                        <td>
                            <?= (int) $alat["jumlah"] ?>
                        </td>

                        <td>
                            <?= (int) $alat["stok"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($alat["kondisi"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($alat["lokasi"] ?? "") ?>
                        </td>

                        <td>

                            <!-- TOMBOL EDIT -->

                            <a
                                href="edit_alat.php?id=<?= (int) $alat["id_alat"] ?>"
                                class="edit"
                            >
                                Edit
                            </a>

                            |

                            <!-- TOMBOL HAPUS -->

                            <form
                                method="POST"
                                class="form-hapus"
                                onsubmit="return confirm('Yakin ingin menghapus alat ini?')"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id_alat"
                                    value="<?= (int) $alat["id_alat"] ?>"
                                >

                                <button
                                    type="submit"
                                    name="hapus"
                                    class="hapus"
                                >
                                    Hapus
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9">
                        Belum ada data alat.
                    </td>
                </tr>

            <?php endif; ?>

        </table>

    </div>

</div>

</body>
</html>