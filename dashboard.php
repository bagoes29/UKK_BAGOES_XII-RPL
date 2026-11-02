<?php
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

// Ambil data pengguna
$nama = htmlspecialchars($_SESSION["nama"] ?? "Pengguna");
$role = $_SESSION["role"] ?? "";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Peminjaman Alat</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .header {
            background: #1e40af;
            color: white;
            padding: 20px;
        }

        .header h2 {
            margin: 0;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }

        .welcome {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #2563eb;
        }

        .card h3 {
            color: #1e40af;
        }

        .card a {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 9px 14px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }

        .card a:hover {
            background: #1d4ed8;
        }

        .logout {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 15px;
        }

        .logout:hover {
            background: #b91c1c;
        }
    </style>
</head>

<body>

<div class="header">
    <h2>SISTEM PEMINJAMAN ALAT</h2>
    <p>UKK Bagoes XII RPL</p>
</div>

<div class="container">

    <!-- INFORMASI PENGGUNA -->
    <div class="welcome">

        <h2>Selamat Datang, <?= $nama ?>!</h2>

        <p>
            Anda login sebagai:
            <strong><?= htmlspecialchars($role) ?></strong>
        </p>

        <p>
            Selamat menggunakan aplikasi Sistem Peminjaman Alat.
        </p>

        <a href="logout.php" class="logout">
            Logout
        </a>

    </div>

    <!-- MENU DASHBOARD -->
    <div class="menu">

        <?php if ($role == "Admin"): ?>

            <!-- MENU ADMIN -->

            <div class="card">
                <h3>Data Pengguna</h3>
                <p>Mengelola akun pengguna aplikasi.</p>
                <a href="users.php">Buka Data Pengguna</a>
            </div>

            <div class="card">
                <h3>Data Kategori</h3>
                <p>Mengelola kategori alat.</p>
                <a href="kategori.php">Buka Data Kategori</a>
            </div>

            <div class="card">
                <h3>Data Alat</h3>
                <p>Mengelola seluruh data alat.</p>
                <a href="alat.php">Buka Data Alat</a>
            </div>

            <div class="card">
                <h3>Laporan</h3>
                <p>Melihat laporan peminjaman dan pengembalian.</p>
            </div>

        <?php elseif ($role == "Petugas"): ?>

            <!-- MENU PETUGAS -->

            <div class="card">
                <h3>Persetujuan Peminjaman</h3>
                <p>Memeriksa dan menyetujui pengajuan peminjaman.</p>
                <a href="persetujuan.php">Buka Persetujuan</a>
            </div>

            <div class="card">
                <h3>Pengembalian Alat</h3>
                <p>Mencatat pengembalian alat.</p>
            </div>

            <div class="card">
                <h3>Laporan</h3>
                <p>Melihat data transaksi.</p>
            </div>

        <?php elseif ($role == "Peminjam"): ?>

            <!-- MENU PEMINJAM -->

            <div class="card">
                <h3>Daftar Alat</h3>
                <p>Melihat alat yang tersedia.</p>
                <a href="daftar_alat.php">Lihat Daftar Alat</a>
            </div>

            <div class="card">
                <h3>Ajukan Peminjaman</h3>
                <p>Mengajukan peminjaman alat.</p>
                <a href="ajukan_peminjaman.php">Ajukan Sekarang</a>
            </div>

            <div class="card">
                <h3>Riwayat Peminjaman</h3>
                <p>Melihat status dan riwayat peminjaman.</p>
                <a href="riwayat_peminjaman.php">Lihat Riwayat</a>
            </div>

        <?php else: ?>

            <!-- ROLE TIDAK DIKENALI -->

            <div class="card">
                <h3>Akun tidak dikenali</h3>
                <p>Hubungi administrator aplikasi.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>