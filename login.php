
<?php
session_start();
require_once "config.php";

$error = "";

// Token keamanan
if (empty($_SESSION["csrf_login"])) {
    $_SESSION["csrf_login"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Periksa token keamanan
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_login"], $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } else {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || $password === "") {

            $error = "Username dan password wajib diisi.";

        } else {

            $query = $pdo->prepare(
                "SELECT * FROM users WHERE username = ?"
            );

            $query->execute([$username]);

            $user = $query->fetch(PDO::FETCH_ASSOC);

            if (
                $user &&
                password_verify($password, $user["password"])
            ) {

                // Perbarui session
                session_regenerate_id(true);

                $_SESSION["id"] = $user["id"];
                $_SESSION["nama"] = $user["nama"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                // Catat aktivitas login
                $log = $pdo->prepare("
                    INSERT INTO log_aktivitas
                    (id_user, aktivitas)
                    VALUES (?, ?)
                ");

                $log->execute([
                    $_SESSION["id"],
                    "Login ke aplikasi sebagai " . $_SESSION["role"]
                ]);

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Username atau password salah!";

            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Sistem Peminjaman Alat</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #eef3ff;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 15px;
        }

        /* CONTAINER UTAMA */

        .login-container {
            width: 100%;
            max-width: 950px;
            min-height: 570px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: white;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(30, 64, 175, 0.13);
        }

        /* PANEL KIRI */

        .left-panel {
            position: relative;
            overflow: hidden;
            background: linear-gradient(
                145deg,
                #172554,
                #1e40af,
                #3b82f6
            );
            color: white;
            padding: 55px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .left-panel::before {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50%;
            right: -110px;
            top: -100px;
        }

        .left-panel::after {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50%;
            left: -180px;
            bottom: -180px;
        }

        .brand {
            position: relative;
            z-index: 1;
            margin-bottom: 40px;
        }

        .brand-line {
            width: 55px;
            height: 4px;
            background: #93c5fd;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .brand h2 {
            font-size: 28px;
            line-height: 1.35;
            margin: 0;
            letter-spacing: 1px;
        }

        .brand p {
            color: #dbeafe;
            font-size: 14px;
            line-height: 1.8;
            margin-top: 15px;
        }

        .illustration {
            position: relative;
            z-index: 1;
            padding: 25px;
            margin-top: 15px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.09);
            border-radius: 16px;
            backdrop-filter: blur(8px);
        }

        .illustration h3 {
            margin-top: 0;
            font-size: 17px;
        }

        .illustration p {
            font-size: 13px;
            line-height: 1.8;
            color: #dbeafe;
            margin-bottom: 0;
        }

        .features {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 20px;
        }

        .feature {
            padding: 8px 12px;
            border-radius: 20px;
            background: rgba(255,255,255,0.13);
            color: white;
            font-size: 12px;
        }

        /* PANEL KANAN */

        .right-panel {
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header .small-title {
            color: #2563eb;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .form-header h1 {
            color: #172554;
            font-size: 30px;
            margin: 12px 0 8px;
        }

        .form-header p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        /* PESAN ERROR */

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        /* FORM */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #334155;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            border-radius: 9px;
            outline: none;
            color: #1e293b;
            font-size: 14px;
            transition: 0.2s;
        }

        .input-wrapper input:focus {
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.10);
        }

        .input-wrapper input::placeholder {
            color: #a3afbf;
        }

        .password-input {
            padding-right: 85px !important;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            color: #2563eb;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            padding: 7px;
        }

        .toggle-password:hover {
            color: #1e40af;
        }

        /* TOMBOL LOGIN */

        .btn-login {
            width: 100%;
            padding: 15px;
            margin-top: 8px;
            border: none;
            border-radius: 9px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
            cursor: pointer;
            box-shadow: 0 6px 15px rgba(37,99,235,0.20);
            transition: 0.25s;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 9px 20px rgba(37,99,235,0.25);
        }

        /* FOOTER */

        .footer {
            text-align: center;
            margin-top: 35px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.8;
        }

        .footer strong {
            color: #64748b;
        }

        /* RESPONSIVE */

        @media (max-width: 750px) {

            body {
                padding: 15px;
            }

            .login-container {
                max-width: 450px;
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .left-panel {
                padding: 30px;
            }

            .brand {
                margin-bottom: 15px;
            }

            .brand h2 {
                font-size: 23px;
            }

            .illustration {
                padding: 17px;
                margin-top: 5px;
            }

            .right-panel {
                padding: 35px 28px;
            }

            .form-header h1 {
                font-size: 26px;
            }
        }

    </style>

</head>

<body>

<div class="login-container">

    <!-- PANEL KIRI -->

    <div class="left-panel">

        <div class="brand">

            <div class="brand-line"></div>

            <h2>
                SISTEM<br>
                PEMINJAMAN<br>
                ALAT
            </h2>

            <p>
                Sistem informasi untuk mengelola
                peminjaman dan pengembalian alat
                secara mudah, tertib, dan terorganisir.
            </p>

        </div>

        <div class="illustration">

            <h3>Selamat Datang!</h3>

            <p>
                Silakan masuk menggunakan akun yang
                sudah terdaftar untuk mengakses
                layanan sesuai hak akses pengguna.
            </p>

        </div>

        <div class="features">

            <span class="feature">Kelola Alat</span>

            <span class="feature">Peminjaman</span>

            <span class="feature">Pengembalian</span>

            <span class="feature">Laporan</span>

        </div>

    </div>

    <!-- PANEL KANAN -->

    <div class="right-panel">

        <div class="form-header">

            <span class="small-title">
                Login Account
            </span>

            <h1>Masuk</h1>

            <p>
                Masukkan username dan password
                untuk melanjutkan ke aplikasi.
            </p>

        </div>

        <!-- PESAN ERROR -->

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <!-- FORM LOGIN -->

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_login"]) ?>"
            >

            <div class="form-group">

                <label>Username</label>

                <div class="input-wrapper">

                    <input
                        type="text"
                        name="username"
                        placeholder="Masukkan username"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>

            <div class="form-group">

                <label>Password</label>

                <div class="input-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="password-input"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword()"
                    >
                        Lihat
                    </button>

                </div>

            </div>

            <button type="submit" class="btn-login">
                MASUK KE APLIKASI
            </button>

        </form>

        <div class="footer">

            <strong>Sistem Peminjaman Alat</strong>

            <br>

            UKK Bagoes XII RPL

            <br>

            © 2026

        </div>

    </div>

</div>

<script>

function togglePassword() {

    const password = document.getElementById("password");
    const tombol = document.querySelector(".toggle-password");

    if (password.type === "password") {

        password.type = "text";
        tombol.textContent = "Sembunyikan";

    } else {

        password.type = "password";
        tombol.textContent = "Lihat";

    }
}

</script>

</body>
</html>
