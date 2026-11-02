```php
<?php
session_start();

require_once 'config.php';

// Periksa login
if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

// Khusus Petugas
if ($_SESSION["role"] !== "Petugas") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

date_default_timezone_set("Asia/Jakarta");

$pesan = "";

// Proses pengembalian
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_pengembalian"], $_POST["csrf_token"])
    ) {
        exit("Token keamanan tidak valid!");
    }

    $id_peminjaman = (int) $_POST["id_peminjaman"];
    $tanggal_kembali = $_POST["tanggal_kembali"];
    $kondisi_kembali = trim($_POST["kondisi_kembali"]);
    $catatan = trim($_POST["catatan"] ?? "");

    try {
        $pdo->beginTransaction();

        // Ambil data peminjaman
        $stmt = $pdo->prepare("
            SELECT tanggal_rencana_kembali, status
            FROM peminjaman
            WHERE id_peminjaman = ?
            FOR UPDATE
        ");
        $stmt->execute([$id_peminjaman]);
        $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$peminjaman || $peminjaman["status"] !== "Disetujui") {
            throw new Exception("Transaksi tidak ditemukan atau belum disetujui.");
        }

        // Periksa apakah sudah pernah dikembalikan
        $cek = $pdo->prepare("
            SELECT id_pengembalian
            FROM pengembalian
            WHERE id_peminjaman = ?
        ");
        $cek->execute([$id_peminjaman]);

        if ($cek->fetch()) {
            throw new Exception("Peminjaman ini sudah dikembalikan.");
        }

        // Validasi tanggal
        $tanggal_rencana = new DateTime($peminjaman["tanggal_rencana_kembali"]);
        $tanggal_aktual = new DateTime($tanggal_kembali);

        if ($tanggal_aktual < $tanggal_rencana) {
            $terlambat = 0;
        } else {
            $selisih = $tanggal_rencana->diff($tanggal_aktual);
            $terlambat = $tanggal_aktual > $tanggal_rencana
                ? (int) $selisih->days
                : 0;
        }

        // Denda Rp5.000 per hari
        $denda = $terlambat * 5000;

        // Simpan data pengembalian
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

// Token CSRF
if (empty($_SESSION["csrf_pengembalian"])) {
    $_SESSION["csrf_pengembalian"] = bin2hex(random_bytes(32));
}

// Ambil peminjaman yang sudah disetujui
$stmt = $pdo->query("
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        u.nama
    FROM peminjaman p
    INNER JOIN users u ON p.id_user = u.id
    WHERE p.status = 'Disetujui'
      AND NOT EXISTS (
          SELECT 1
          FROM pengembalian pg
          WHERE pg.id_peminjaman = p.id_peminjaman
      )
    ORDER BY p.tanggal_pinjam ASC
");

$daftar = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembalian Alat</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 20px auto;
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
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            margin-top: 20px;
            padding: 11px 18px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .pesan {
            background: #fee2e2;
            padding: 12px;
            border-radius: 5px;
            color: #991b1b;
        }

        .kembali {
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Form Pengembalian Alat</h2>

    <?php if (isset($_GET["pesan"]) && $_GET["pesan"] === "berhasil"): ?>
        <p style="color:green;">
            Pengembalian berhasil dicatat!
        </p>
    <?php endif; ?>

    <?php if ($pesan !== ""): ?>
        <p class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </p>
    <?php endif; ?>

    <?php if (count($daftar) > 0): ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_pengembalian"]) ?>"
            >

            <label>Pilih Peminjaman:</label>

            <select name="id_peminjaman" required>
                <option value="">-- Pilih Peminjaman --</option>

                <?php foreach ($daftar as $item): ?>

                    <option value="<?= $item["id_peminjaman"] ?>">
                        Peminjaman #<?= $item["id_peminjaman"] ?>
                        - <?= htmlspecialchars($item["nama"]) ?>
                        - Rencana kembali:
                        <?= htmlspecialchars($item["tanggal_rencana_kembali"]) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label>Tanggal Pengembalian:</label>

            <input
                type="date"
                name="tanggal_kembali"
                value="<?= date('Y-m-d') ?>"
                required
            >

            <label>Kondisi Alat Saat Dikembalikan:</label>

            <select name="kondisi_kembali" required>
                <option value="">-- Pilih Kondisi --</option>
                <option value="Baik">Baik</option>
                <option value="Rusak Ringan">Rusak Ringan</option>
                <option value="Rusak Berat">Rusak Berat</option>
            </select>

            <label>Catatan:</label>

            <textarea
                name="catatan"
                rows="4"
                placeholder="Masukkan catatan jika ada..."
            ></textarea>

            <button type="submit">
                Simpan Pengembalian
            </button>

        </form>

    <?php else: ?>

        <p>
            Belum ada peminjaman yang perlu dikembalikan.
        </p>

    <?php endif; ?>

    <a href="dashboard.php" class="kembali">
        Kembali ke Dashboard
    </a>

</div>

</body>
</html>
