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
// AMBIL RIWAYAT PEMINJAMAN
// ==================================

$sql = $pdo->prepare(
    "SELECT
        peminjaman.id_peminjaman,
        peminjaman.tanggal_pengajuan,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.keperluan,
        peminjaman.status,
        alat.kode_alat,
        alat.nama_alat,
        detail_peminjaman.jumlah_pinjam
    FROM peminjaman
    INNER JOIN detail_peminjaman
        ON peminjaman.id_peminjaman = detail_peminjaman.id_peminjaman
    INNER JOIN alat
        ON detail_peminjaman.id_alat = alat.id_alat
    WHERE peminjaman.id_user = ?
    ORDER BY peminjaman.id_peminjaman DESC"
);

$sql->execute([$_SESSION["id"]]);

$riwayat = $sql->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Peminjaman</title>

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
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
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
            margin-top: 15px;
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

        .menunggu {
            color: #b45309;
            font-weight: bold;
        }

        .disetujui {
            color: #166534;
            font-weight: bold;
        }

        .ditolak {
            color: #dc2626;
            font-weight: bold;
        }

        .selesai {
            color: #1d4ed8;
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

    <h2>RIWAYAT PEMINJAMAN</h2>

    <a href="dashboard.php" class="kembali">
        ← Kembali ke Dashboard
    </a>

    <div class="info">

        Nama Peminjam:
        <strong>
            <?= htmlspecialchars($_SESSION["nama"]) ?>
        </strong>

    </div>

    <div class="tabel-wrapper">

        <table>

            <tr>
                <th>No</th>
                <th>Kode Alat</th>
                <th>Nama Alat</th>
                <th>Jumlah</th>
                <th>Tanggal Pinjam</th>
                <th>Rencana Kembali</th>
                <th>Keperluan</th>
                <th>Status</th>
            </tr>

            <?php if (count($riwayat) > 0): ?>

                <?php $no = 1; ?>

                <?php foreach ($riwayat as $data): ?>

                    <tr>

                        <td>
                            <?= $no++ ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($data["kode_alat"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($data["nama_alat"]) ?>
                        </td>

                        <td>
                            <?= (int) $data["jumlah_pinjam"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($data["tanggal_pinjam"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($data["tanggal_rencana_kembali"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($data["keperluan"]) ?>
                        </td>

                        <td>

                            <?php if ($data["status"] === "Menunggu"): ?>

                                <span class="menunggu">
                                    Menunggu
                                </span>

                            <?php elseif ($data["status"] === "Disetujui"): ?>

                                <span class="disetujui">
                                    Disetujui
                                </span>

                            <?php elseif ($data["status"] === "Ditolak"): ?>

                                <span class="ditolak">
                                    Ditolak
                                </span>

                            <?php elseif ($data["status"] === "Selesai"): ?>

                                <span class="selesai">
                                    Selesai
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="kosong">
                        Belum ada riwayat peminjaman.
                    </td>
                </tr>

            <?php endif; ?>

        </table>

    </div>

</div>

</body>
</html>