<?php
session_start();
require_once "config.php";

// ======================================
// CEK HAK AKSES ADMIN
// ======================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Admin") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ======================================
// MEMBUAT TOKEN KEAMANAN
// ======================================

if (!isset($_SESSION["csrf_users"])) {
    $_SESSION["csrf_users"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_users"];
$error = "";

// ======================================
// TAMBAH PENGGUNA
// ======================================

if (isset($_POST["tambah"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";
    } else {

        $nama = trim($_POST["nama"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["role"] ?? "";

        $daftarRole = ["Admin", "Petugas", "Peminjam"];

        if (
            $nama === "" ||
            $username === "" ||
            $password === "" ||
            !in_array($role, $daftarRole, true)
        ) {
            $error = "Semua data wajib diisi dengan benar.";

        } else {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {

                $sql = $pdo->prepare(
                    "INSERT INTO users
                    (nama, username, password, role)
                    VALUES (?, ?, ?, ?)"
                );

                $sql->execute([
                    $nama,
                    $username,
                    $passwordHash,
                    $role
                ]);

                header("Location: users.php?pesan=tambah");
                exit;

            } catch (PDOException $e) {

                $error = "Username sudah digunakan atau data tidak valid.";

            }
        }
    }
}

// ======================================
// HAPUS PENGGUNA
// ======================================

if (isset($_POST["hapus"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan hapus tidak valid.";

    } else {

        $id = (int) ($_POST["id"] ?? 0);

        // Mencegah penghapusan akun sendiri
        if ($id === (int) $_SESSION["id"]) {

            $error = "Anda tidak dapat menghapus akun sendiri.";

        } elseif ($id <= 0) {

            $error = "ID pengguna tidak valid.";

        } else {

            try {

                $sql = $pdo->prepare(
                    "DELETE FROM users WHERE id = ?"
                );

                $sql->execute([$id]);

                header("Location: users.php?pesan=hapus");
                exit;

            } catch (PDOException $e) {

                $error = "Pengguna tidak dapat dihapus karena masih memiliki riwayat transaksi atau data terkait.";

            }
        }
    }
}

// ======================================
// AMBIL DATA SELURUH PENGGUNA
// ======================================

$sql = $pdo->query(
    "SELECT id, nama, username, role
     FROM users
     ORDER BY id ASC"
);

$users = $sql->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Data Pengguna</title>

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
            margin-top: 25px;
            color: #334155;
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

        input,
        select,
        button {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }

        input,
        select {
            border: 1px solid #ccc;
        }

        .form-tambah {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-tambah input,
        .form-tambah select {
            flex: 1;
            min-width: 150px;
        }

        button {
            cursor: pointer;
            border: none;
        }

        .btn-tambah {
            background: #2563eb;
            color: white;
        }

        .btn-tambah:hover {
            background: #1d4ed8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
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

        .edit {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .edit:hover {
            text-decoration: underline;
        }

        .hapus {
            background: #dc2626;
            color: white;
            padding: 7px 10px;
        }

        .hapus:hover {
            background: #b91c1c;
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

        .aksi {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .aksi form {
            margin: 0;
        }

        @media (max-width: 700px) {
            .container {
                overflow-x: auto;
            }

            table {
                min-width: 650px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <h2>DATA PENGGUNA</h2>

    <a href="dashboard.php" class="kembali">
        ← Kembali ke Dashboard
    </a>

    <!-- PESAN BERHASIL -->

    <?php if (isset($_GET["pesan"])): ?>

        <?php if ($_GET["pesan"] === "tambah"): ?>

            <p class="pesan-sukses">
                Pengguna berhasil ditambahkan!
            </p>

        <?php elseif ($_GET["pesan"] === "edit"): ?>

            <p class="pesan-sukses">
                Data pengguna berhasil diperbarui!
            </p>

        <?php elseif ($_GET["pesan"] === "hapus"): ?>

            <p class="pesan-sukses">
                Pengguna berhasil dihapus!
            </p>

        <?php endif; ?>

    <?php endif; ?>

    <!-- PESAN ERROR -->

    <?php if ($error !== ""): ?>

        <p class="pesan-error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <!-- FORM TAMBAH PENGGUNA -->

    <h3>Tambah Pengguna</h3>

    <form method="POST" class="form-tambah">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($token) ?>"
        >

        <input
            type="text"
            name="nama"
            placeholder="Nama lengkap"
            required
        >

        <input
            type="text"
            name="username"
            placeholder="Username"
            required
        >

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
        >

        <select name="role" required>

            <option value="">
                Pilih Role
            </option>

            <option value="Admin">
                Admin
            </option>

            <option value="Petugas">
                Petugas
            </option>

            <option value="Peminjam">
                Peminjam
            </option>

        </select>

        <button
            type="submit"
            name="tambah"
            class="btn-tambah"
        >
            Tambah Pengguna
        </button>

    </form>


    <!-- TABEL DATA PENGGUNA -->

    <h3>Daftar Pengguna</h3>

    <table>

        <tr>
            <th>No</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Role</th>
            <th>Aksi</th>
        </tr>

        <?php $no = 1; ?>

        <?php foreach ($users as $user): ?>

            <tr>

                <td>
                    <?= $no++ ?>
                </td>

                <td>
                    <?= htmlspecialchars($user["nama"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($user["username"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($user["role"]) ?>
                </td>

                <td>

                    <div class="aksi">

                        <?php if ((int) $user["id"] !== (int) $_SESSION["id"]): ?>

                            <!-- TOMBOL EDIT -->

                            <a
                                class="edit"
                                href="edit_user.php?id=<?= (int) $user["id"] ?>"
                            >
                                Edit
                            </a>


                            <!-- TOMBOL HAPUS -->

                            <form method="POST"
                                  onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $user["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    name="hapus"
                                    class="hapus"
                                >
                                    Hapus
                                </button>

                            </form>

                        <?php else: ?>

                            <strong>Akun sendiri</strong>

                        <?php endif; ?>

                    </div>

                </td>

            </tr>

        <?php endforeach; ?>

    </table>

</div>

</body>
</html>