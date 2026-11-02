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

if (!isset($_SESSION["csrf_kategori"])) {
    $_SESSION["csrf_kategori"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_kategori"];
$error = "";

// ==================================
// AMBIL ID KATEGORI
// ==================================

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    exit("ID kategori tidak valid.");
}

// ==================================
// AMBIL DATA KATEGORI
// ==================================

$sql = $pdo->prepare(
    "SELECT * FROM kategori WHERE id_kategori = ?"
);

$sql->execute([$id]);

$kategori = $sql->fetch(PDO::FETCH_ASSOC);

if (!$kategori) {
    exit("Data kategori tidak ditemukan.");
}

// ==================================
// PROSES UPDATE KATEGORI
// ==================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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

                $update = $pdo->prepare(
                    "UPDATE kategori
                     SET nama_kategori = ?, deskripsi = ?
                     WHERE id_kategori = ?"
                );

                $update->execute([
                    $nama,
                    $deskripsi,
                    $id
                ]);

                header("Location: kategori.php?pesan=edit");
                exit;

            } catch (PDOException $e) {

                $error = "Gagal memperbarui kategori.";

            }
        }

        // Menampilkan kembali data yang dimasukkan
        $kategori["nama_kategori"] = $nama;
        $kategori["deskripsi"] = $deskripsi;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Kategori</title>

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
            max-width: 500px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h2 {
            color: #1e40af;
        }

        label {
            display: block;
            margin-top: 15px;
        }

        input, textarea, button {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
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

    <h2>EDIT KATEGORI</h2>

    <a href="kategori.php" class="kembali">
        ← Kembali ke Data Kategori
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

        <label>Nama Kategori</label>

        <input
            type="text"
            name="nama_kategori"
            value="<?= htmlspecialchars($kategori["nama_kategori"]) ?>"
            required
        >

        <label>Deskripsi</label>

        <textarea
            name="deskripsi"
            rows="5"
        ><?= htmlspecialchars($kategori["deskripsi"] ?? "") ?></textarea>

        <button type="submit">
            Simpan Perubahan
        </button>

    </form>

</div>

</body>
</html>