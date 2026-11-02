<?php
session_start();
require_once "config.php";

// Hanya Admin yang boleh mengakses
if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Admin") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

$error = "";

// Ambil ID pengguna
$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    exit("ID pengguna tidak valid.");
}

// Ambil data pengguna
$query = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$query->execute([$id]);
$user = $query->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit("Data pengguna tidak ditemukan.");
}

// Proses update data
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $role = $_POST["role"] ?? "";
    $password = $_POST["password"] ?? "";

    $daftarRole = ["Admin", "Petugas", "Peminjam"];

    if ($nama === "" || $username === "" || !in_array($role, $daftarRole, true)) {
        $error = "Nama, username, dan role wajib diisi.";
    } else {
        try {
            if ($password !== "") {
                // Password baru diisi, maka password ikut diperbarui
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $sql = $pdo->prepare(
                    "UPDATE users
                     SET nama = ?, username = ?, password = ?, role = ?
                     WHERE id = ?"
                );

                $sql->execute([
                    $nama, $username, $hash, $role, $id
                ]);

            } else {
                // Password kosong, password lama tetap digunakan
                $sql = $pdo->prepare(
                    "UPDATE users
                     SET nama = ?, username = ?, role = ?
                     WHERE id = ?"
                );

                $sql->execute([
                    $nama, $username, $role, $id
                ]);
            }

            header("Location: users.php?pesan=edit");
            exit;

        } catch (PDOException $e) {
            $error = "Username sudah digunakan atau data tidak valid.";
        }
    }

    // Perbarui tampilan data setelah validasi
    $user["nama"] = $nama;
    $user["username"] = $username;
    $user["role"] = $role;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Pengguna</title>

    <style>
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

        input, select, button {
            width: 100%;
            padding: 11px;
            margin: 8px 0;
            box-sizing: border-box;
        }

        input, select {
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .error {
            color: red;
        }

        a {
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Edit Data Pengguna</h2>

    <a href="users.php">← Kembali ke Data Pengguna</a>

    <?php if ($error !== ""): ?>
        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input type="hidden" name="id" value="<?= $id ?>">

        <label>Nama Lengkap</label>
        <input
            type="text"
            name="nama"
            value="<?= htmlspecialchars($user["nama"]) ?>"
            required
        >

        <label>Username</label>
        <input
            type="text"
            name="username"
            value="<?= htmlspecialchars($user["username"]) ?>"
            required
        >

        <label>Password Baru</label>
        <input
            type="password"
            name="password"
            placeholder="Kosongkan jika tidak ingin mengubah password"
        >

        <label>Role</label>
        <select name="role" required>
            <option value="Admin" <?= $user["role"] == "Admin" ? "selected" : "" ?>>
                Admin
            </option>

            <option value="Petugas" <?= $user["role"] == "Petugas" ? "selected" : "" ?>>
                Petugas
            </option>

            <option value="Peminjam" <?= $user["role"] == "Peminjam" ? "selected" : "" ?>>
                Peminjam
            </option>
        </select>

        <button type="submit">Simpan Perubahan</button>

    </form>

</div>

</body>
</html>