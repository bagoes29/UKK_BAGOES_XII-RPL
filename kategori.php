<?php
session_start();
require_once "config.php";

// Hanya Admin yang boleh mengakses
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Admin") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// Membuat token keamanan
if (!isset($_SESSION["csrf_kategori"])) {
    $_SESSION["csrf_kategori"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_kategori"];
$error = "";

// ==================================
// TAMBAH KATEGORI
// ==================================

if (isset($_POST["tambah"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid.";
    } else {

        $nama = trim($_POST["nama_kategori"] ?? "");
        $deskripsi = trim($_POST["deskripsi"] ?? "");

        if ($nama === "") {
            $error = "Nama kategori wajib diisi.";
        } else {

            try {
                $sql = $pdo->prepare(
                    "INSERT INTO kategori (nama_kategori, deskripsi)
                     VALUES (?, ?)"
                );

                $sql->execute([$nama, $deskripsi]);

                header("Location: kategori.php?pesan=tambah");
                exit;

            } catch (PDOException $e) {
                $error = "Gagal menambahkan kategori.";
            }
        }
    }
}

// ==================================
// HAPUS KATEGORI
// ==================================

if (isset($_POST["hapus"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan hapus tidak valid.";
    } else {

        $id = (int) ($_POST["id_kategori"] ?? 0);

        if ($id <= 0) {
            $error = "ID kategori tidak valid.";
        } else {

            try {
                $sql = $pdo->prepare(
                    "DELETE FROM kategori WHERE id_kategori = ?"
                );

                $sql->execute([$id]);

                header("Location: kategori.php?pesan=hapus");
                exit;

            } catch (PDOException $e) {
                $error = "Kategori tidak dapat dihapus karena masih digunakan oleh data alat.";
            }
        }
    }
}

// ==================================
// AMBIL DATA KATEGORI
// ==================================

$sql = $pdo->query(
    "SELECT * FROM kategori ORDER BY id_kategori ASC"
);

$kategori = $sql->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Kategori</title>

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
            max-width: 1000px;
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

        input, textarea, button {
            width: 100%;
            padding: 11px;
            margin: 7px 0;
            border-radius: 5px;
        }

        input, textarea {
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

        .aksi {
            white-space: nowrap;
        }

        .edit {
            color: #2563eb;
            font-weight: bold;
            text-decoration: none;
        }

        .edit:hover {
            text-decoration: underline;
        }

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

    </style>

</head>

<body>

<div class="container">

    <h2>DATA KATEGORI ALAT</h2>

    <a href="dashboard.php" class="kembali">
        ← Kembali ke Dashboard
    </a>

    <!-- PESAN BERHASIL -->

    <?php if (isset($_GET["pesan"])): ?>

        <?php if ($_GET["pesan"] === "tambah"): ?>

            <p class="pesan-sukses">
                Kategori berhasil ditambahkan!
            </p>

        <?php elseif ($_GET["pesan"] === "hapus"): ?>

            <p class="pesan-sukses">
                Kategori berhasil dihapus!
            </p>

        <?php elseif ($_GET["pesan"] === "edit"): ?>

            <p class="pesan-sukses">
                Kategori berhasil diperbarui!
            </p>

        <?php endif; ?>

    <?php endif; ?>


    <!-- PESAN ERROR -->

    <?php if ($error !== ""): ?>

        <p class="pesan-error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <!-- FORM TAMBAH KATEGORI -->

    <h3>Tambah Kategori</h3>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($token) ?>"
        >

        <label>Nama Kategori</label>

        <input
            type="text"
            name="nama_kategori"
            placeholder="Contoh: Elektronik"
            required
        >

        <label>Deskripsi</label>

        <textarea
            name="deskripsi"
            rows="4"
            placeholder="Masukkan deskripsi kategori"
        ></textarea>

        <button type="submit" name="tambah">
            Tambah Kategori
        </button>

    </form>


    <!-- TABEL KATEGORI -->

    <h3>Daftar Kategori</h3>

    <table>

        <tr>
            <th>No</th>
            <th>Nama Kategori</th>
            <th>Deskripsi</th>
            <th>Aksi</th>
        </tr>

        <?php if (count($kategori) > 0): ?>

            <?php $no = 1; ?>

            <?php foreach ($kategori as $data): ?>

                <tr>

                    <td>
                        <?= $no++ ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($data["nama_kategori"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($data["deskripsi"] ?? "") ?>
                    </td>

                    <td class="aksi">

                        <!-- TOMBOL EDIT -->

                        <a
                            href="edit_kategori.php?id=<?= (int) $data["id_kategori"] ?>"
                            class="edit"
                        >
                            Edit
                        </a>

                        |

                        <!-- TOMBOL HAPUS -->

                        <form
                            method="POST"
                            class="form-hapus"
                            onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($token) ?>"
                            >

                            <input
                                type="hidden"
                                name="id_kategori"
                                value="<?= (int) $data["id_kategori"] ?>"
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
                <td colspan="4">
                    Belum ada data kategori.
                </td>
            </tr>

        <?php endif; ?>

    </table>

</div>

</body>
</html>