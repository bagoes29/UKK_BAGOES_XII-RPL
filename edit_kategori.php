
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

    // Periksa token keamanan
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

                // Mulai transaksi database
                $pdo->beginTransaction();

                // Kunci data kategori
                $cek = $pdo->prepare(
                    "SELECT *
                     FROM kategori
                     WHERE id_kategori = ?
                     FOR UPDATE"
                );

                $cek->execute([$id]);

                $dataLama = $cek->fetch(PDO::FETCH_ASSOC);

                if (!$dataLama) {
                    throw new Exception(
                        "Data kategori tidak ditemukan."
                    );
                }

                // ==================================
                // UPDATE DATA KATEGORI
                // ==================================

                $update = $pdo->prepare(
                    "UPDATE kategori
                     SET nama_kategori = ?,
                         deskripsi = ?
                     WHERE id_kategori = ?"
                );

                $update->execute([
                    $nama,
                    $deskripsi,
                    $id
                ]);

                // ==================================
                // PERIKSA PERUBAHAN DATA
                // ==================================

                $perubahan = [];

                if ($dataLama["nama_kategori"] !== $nama) {

                    $perubahan[] =
                        "Nama kategori: " .
                        $dataLama["nama_kategori"] .
                        " menjadi " . $nama;
                }

                if (($dataLama["deskripsi"] ?? "") !== $deskripsi) {

                    $perubahan[] =
                        "Deskripsi kategori diperbarui";
                }

                if (count($perubahan) > 0) {

                    $keterangan = implode("; ", $perubahan);

                } else {

                    $keterangan = "Data disimpan tanpa perubahan nilai.";
                }

                // ==================================
                // CATAT AKTIVITAS EDIT KATEGORI
                // ==================================

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas
                    (id_user, aktivitas)
                    VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Mengubah kategori ID " .
                    $id . " (" . $nama . "): " .
                    $keterangan
                ]);

                // ==================================
                // SIMPAN TRANSAKSI
                // ==================================

                $pdo->commit();

                header("Location: kategori.php?pesan=edit");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Gagal memperbarui kategori.";

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }

        // Tampilkan kembali data jika terjadi kesalahan
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

        .kembali:hover {
            text-decoration: underline;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 5px;
        }

        .info {
            background: #eff6ff;
            color: #1e40af;
            padding: 12px;
            border-radius: 5px;
            margin-top: 15px;
            font-size: 13px;
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

    <div class="info">
        Perubahan nama dan deskripsi kategori akan tercatat
        otomatis di Log Aktivitas.
    </div>

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
