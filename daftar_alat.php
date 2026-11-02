<?php
session_start();
require_once "config.php";

// ==================================
// CEK HAK AKSES PEMINJAM
// ==================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Peminjam") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// AMBIL DATA ALAT DAN KATEGORI
// ==================================

$sql = $pdo->query(
    "SELECT
        alat.id_alat,
        alat.kode_alat,
        alat.nama_alat,
        alat.jumlah,
        alat.stok,
        alat.kondisi,
        alat.lokasi,
        kategori.nama_kategori
     FROM alat
     INNER JOIN kategori
        ON alat.id_kategori = kategori.id_kategori
     ORDER BY alat.nama_alat ASC"
);

$dataAlat = $sql->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar Alat - Peminjam</title>

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

        .kembali {
            display: inline-block;
            margin-bottom: 15px;
            color: #2563eb;
            text-decoration: none;
        }

        .kembali:hover {
            text-decoration: underline;
        }

        .info {
            background: #eff6ff;
            color: #1e40af;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .tabel-wrapper {
            overflow-x: auto;
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

        .tersedia {
            color: #166534;
            font-weight: bold;
        }

        .habis {
            color: #dc2626;
            font-weight: bold;
        }

        .tidak-baik {
            color: #b45309;
            font-weight: bold;
        }

        .kosong {
            text-align: center;
            padding: 20px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>DAFTAR ALAT</h2>

    <a href="dashboard.php" class="kembali">
        ← Kembali ke Dashboard
    </a>

    <div class="info">
        Selamat datang, <?= htmlspecialchars($_SESSION["nama"]) ?>.
        Berikut adalah daftar alat yang tersedia.
    </div>

    <div class="tabel-wrapper">

        <table>

            <tr>
                <th>No</th>
                <th>Kode Alat</th>
                <th>Nama Alat</th>
                <th>Kategori</th>
                <th>Stok</th>
                <th>Kondisi</th>
                <th>Lokasi</th>
                <th>Status</th>
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
                            <?= (int) $alat["stok"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($alat["kondisi"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($alat["lokasi"] ?? "-") ?>
                        </td>

                        <td>

                            <?php if ($alat["kondisi"] !== "Baik"): ?>

                                <span class="tidak-baik">
                                    Tidak dapat dipinjam
                                </span>

                            <?php elseif ((int) $alat["stok"] > 0): ?>

                                <span class="tersedia">
                                    Tersedia
                                </span>

                            <?php else: ?>

                                <span class="habis">
                                    Stok Habis
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="kosong">
                        Belum ada data alat.
                    </td>
                </tr>

            <?php endif; ?>

        </table>

    </div>

</div>

</body>
</html>