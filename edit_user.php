
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
// TOKEN KEAMANAN
// ======================================

if (!isset($_SESSION["csrf_edit_user"])) {
    $_SESSION["csrf_edit_user"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_edit_user"];
$error = "";

// ======================================
// AMBIL ID PENGGUNA
// ======================================

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    exit("ID pengguna tidak valid.");
}

// ======================================
// AMBIL DATA PENGGUNA
// ======================================

$query = $pdo->prepare(
    "SELECT id, nama, username, role
     FROM users
     WHERE id = ?"
);

$query->execute([$id]);

$user = $query->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit("Data pengguna tidak ditemukan.");
}

// ======================================
// PROSES UPDATE DATA PENGGUNA
// ======================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Periksa token CSRF
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";

    } else {

        $nama = trim($_POST["nama"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $role = $_POST["role"] ?? "";
        $password = $_POST["password"] ?? "";

        $daftarRole = [
            "Admin",
            "Petugas",
            "Peminjam"
        ];

        if (
            $nama === "" ||
            $username === "" ||
            !in_array($role, $daftarRole, true)
        ) {

            $error = "Nama, username, dan role wajib diisi.";

        } else {

            try {

                // Mulai transaksi database
                $pdo->beginTransaction();

                // Kunci data pengguna yang akan diedit
                $cek = $pdo->prepare(
                    "SELECT id, nama, username, role
                     FROM users
                     WHERE id = ?
                     FOR UPDATE"
                );

                $cek->execute([$id]);

                $dataLama = $cek->fetch(PDO::FETCH_ASSOC);

                if (!$dataLama) {
                    throw new Exception(
                        "Data pengguna tidak ditemukan."
                    );
                }

                // Simpan informasi perubahan untuk log
                $perubahan = [];

                if ($dataLama["nama"] !== $nama) {
                    $perubahan[] =
                        "Nama: " . $dataLama["nama"] .
                        " menjadi " . $nama;
                }

                if ($dataLama["username"] !== $username) {
                    $perubahan[] =
                        "Username: " . $dataLama["username"] .
                        " menjadi " . $username;
                }

                if ($dataLama["role"] !== $role) {
                    $perubahan[] =
                        "Role: " . $dataLama["role"] .
                        " menjadi " . $role;
                }

                if ($password !== "") {
                    $perubahan[] = "Password diperbarui";
                }

                // ======================================
                // UPDATE DATA PENGGUNA
                // ======================================

                if ($password !== "") {

                    // Password baru diisi
                    $hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $sql = $pdo->prepare(
                        "UPDATE users
                         SET nama = ?,
                             username = ?,
                             password = ?,
                             role = ?
                         WHERE id = ?"
                    );

                    $sql->execute([
                        $nama,
                        $username,
                        $hash,
                        $role,
                        $id
                    ]);

                } else {

                    // Password lama tetap digunakan
                    $sql = $pdo->prepare(
                        "UPDATE users
                         SET nama = ?,
                             username = ?,
                             role = ?
                         WHERE id = ?"
                    );

                    $sql->execute([
                        $nama,
                        $username,
                        $role,
                        $id
                    ]);
                }

                // ======================================
                // CATAT AKTIVITAS EDIT PENGGUNA
                // ======================================

                if (count($perubahan) > 0) {

                    $keterangan = implode("; ", $perubahan);

                } else {

                    $keterangan = "Tidak ada perubahan nilai data";
                }

                $log = $pdo->prepare(
                    "INSERT INTO log_aktivitas
                    (id_user, aktivitas)
                    VALUES (?, ?)"
                );

                $log->execute([
                    $_SESSION["id"],
                    "Mengubah data pengguna ID " .
                    $id . ": " . $keterangan
                ]);

                // Simpan seluruh transaksi
                $pdo->commit();

                header("Location: users.php?pesan=edit");
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Username sudah digunakan atau data tidak valid.";

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }

        // Perbarui tampilan setelah validasi gagal
        $user["nama"] = $nama;
        $user["username"] = $username;
        $user["role"] = $role;
    }
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

        input, select, button {
            width: 100%;
            padding: 11px;
            margin: 8px 0;
            box-sizing: border-box;
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

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 5px;
        }

        .kembali {
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
            font-size: 13px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Edit Data Pengguna</h2>

    <a href="users.php" class="kembali">
        ← Kembali ke Data Pengguna
    </a>

    <!-- PESAN ERROR -->

    <?php if ($error !== ""): ?>

        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>

    <div class="info">
        Kosongkan password jika tidak ingin mengganti password lama.
    </div>

    <!-- FORM EDIT PENGGUNA -->

    <form method="POST">

        <input
            type="hidden"
            name="id"
            value="<?= (int) $id ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($token) ?>"
        >

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

            <option
                value="Admin"
                <?= $user["role"] == "Admin" ? "selected" : "" ?>
            >
                Admin
            </option>

            <option
                value="Petugas"
                <?= $user["role"] == "Petugas" ? "selected" : "" ?>
            >
                Petugas
            </option>

            <option
                value="Peminjam"
                <?= $user["role"] == "Peminjam" ? "selected" : "" ?>
            >
                Peminjam
            </option>

        </select>

        <button type="submit">
            Simpan Perubahan
        </button>

    </form>

</div>

</body>
</html>
