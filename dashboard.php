
<?php
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

// Ambil data pengguna
$nama = htmlspecialchars($_SESSION["nama"] ?? "Pengguna", ENT_QUOTES, 'UTF-8');
$role = $_SESSION["role"] ?? "";
$inisial = strtoupper(substr($_SESSION["nama"] ?? "P", 0, 1));

$menu = [];

if ($role == "Admin") {
    $menu = [
        [
            "icon" => "👥",
            "title" => "Data Pengguna",
            "desc" => "Kelola akun, informasi, dan hak akses seluruh pengguna aplikasi.",
            "link" => "users.php",
            "label" => "Kelola Pengguna",
            "color" => "blue"
        ],
        [
            "icon" => "🗂️",
            "title" => "Data Kategori",
            "desc" => "Atur dan kelola kategori alat agar data tersusun rapi.",
            "link" => "kategori.php",
            "label" => "Kelola Kategori",
            "color" => "purple"
        ],
        [
            "icon" => "🛠️",
            "title" => "Data Alat",
            "desc" => "Kelola inventaris, jumlah stok, dan informasi peralatan.",
            "link" => "alat.php",
            "label" => "Kelola Alat",
            "color" => "cyan"
        ],
        [
            "icon" => "📊",
            "title" => "Laporan",
            "desc" => "Pantau laporan transaksi peminjaman dan pengembalian alat.",
            "link" => "laporan.php",
            "label" => "Lihat Laporan",
            "color" => "green"
        ],
        [
            "icon" => "📋",
            "title" => "Log Aktivitas",
            "desc" => "Pantau catatan aktivitas pengguna dalam sistem.",
            "link" => "log_aktivitas.php",
            "label" => "Lihat Aktivitas",
            "color" => "orange"
        ]
    ];
} elseif ($role == "Petugas") {
    $menu = [
        [
            "icon" => "📨",
            "title" => "Persetujuan Peminjaman",
            "desc" => "Periksa pengajuan dan proses persetujuan peminjaman alat.",
            "link" => "persetujuan.php",
            "label" => "Periksa Pengajuan",
            "color" => "blue"
        ],
        [
            "icon" => "📦",
            "title" => "Pengembalian Alat",
            "desc" => "Kelola transaksi pengembalian alat yang dipinjam.",
            "link" => "pengembalian.php",
            "label" => "Kelola Pengembalian",
            "color" => "purple"
        ],
        [
            "icon" => "📈",
            "title" => "Laporan",
            "desc" => "Lihat informasi transaksi peminjaman dan pengembalian.",
            "link" => "laporan.php",
            "label" => "Lihat Laporan",
            "color" => "green"
        ]
    ];
} elseif ($role == "Peminjam") {
    $menu = [
        [
            "icon" => "🔧",
            "title" => "Daftar Alat",
            "desc" => "Jelajahi daftar peralatan dan informasi ketersediaannya.",
            "link" => "daftar_alat.php",
            "label" => "Jelajahi Alat",
            "color" => "blue"
        ],
        [
            "icon" => "📝",
            "title" => "Ajukan Peminjaman",
            "desc" => "Ajukan permohonan untuk meminjam peralatan yang dibutuhkan.",
            "link" => "ajukan_peminjaman.php",
            "label" => "Ajukan Sekarang",
            "color" => "purple"
        ],
        [
            "icon" => "📚",
            "title" => "Riwayat Peminjaman",
            "desc" => "Pantau status dan riwayat pengajuan peminjaman kamu.",
            "link" => "riwayat_peminjaman.php",
            "label" => "Lihat Riwayat",
            "color" => "green"
        ]
    ];
}

$totalMenu = count($menu);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard | Sistem Peminjaman Alat</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --navy: #101a35;
    --navy-light: #1b2b50;
    --blue: #4169f5;
    --blue-light: #edf2ff;
    --text: #192642;
    --muted: #8994a9;
    --background: #f4f7fc;
    --white: #ffffff;
    --border: #edf0f6;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--background);
    color: var(--text);
    min-height: 100vh;
}

a {
    text-decoration: none;
}

/* LAYOUT */

.layout {
    min-height: 100vh;
    display: flex;
}

/* SIDEBAR */

.sidebar {
    width: 265px;
    min-height: 100vh;
    background: linear-gradient(180deg, #111c38 0%, #17294d 100%);
    padding: 30px 20px;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    color: white;
    position: relative;
    overflow: hidden;
}

.sidebar::before {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    background: rgba(65, 105, 245, .13);
    border-radius: 50%;
    top: -100px;
    right: -100px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
    padding: 0 5px;
    margin-bottom: 48px;
}

.brand-icon {
    width: 47px;
    height: 47px;
    background: linear-gradient(135deg, #557cff, #3156db);
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 24px;
    box-shadow: 0 8px 20px rgba(65,105,245,.3);
}

.brand-name {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 15px;
    font-weight: 800;
    line-height: 1.4;
}

.brand-name span {
    display: block;
    color: #8298c5;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1.6px;
    margin-top: 3px;
}

.side-label {
    font-size: 10px;
    color: #7185ad;
    font-weight: 800;
    letter-spacing: 2px;
    padding: 0 12px;
    margin-bottom: 15px;
}

.side-menu {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.side-link {
    padding: 13px 14px;
    border-radius: 12px;
    color: #a9b8d4;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    font-weight: 600;
    transition: .25s;
}

.side-link:hover,
.side-link.active {
    background: linear-gradient(100deg, #4169f5, #3157d8);
    color: white;
    box-shadow: 0 7px 18px rgba(49,87,216,.25);
    transform: translateX(3px);
}

.side-icon {
    font-size: 17px;
    width: 23px;
    text-align: center;
}

.sidebar-bottom {
    margin-top: auto;
    position: relative;
    z-index: 1;
}

.help-card {
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.08);
    padding: 17px;
    border-radius: 15px;
    margin-bottom: 18px;
}

.help-card h4 {
    font-size: 12px;
    margin-bottom: 7px;
}

.help-card p {
    font-size: 11px;
    color: #9cacc9;
    line-height: 1.7;
}

.sidebar-footer {
    text-align: center;
    font-size: 10px;
    color: #7185ad;
    line-height: 1.8;
}

/* MAIN */

.main {
    flex: 1;
    min-width: 0;
    padding: 30px 38px;
}

/* TOPBAR */

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 32px;
}

.breadcrumb {
    font-size: 12px;
    color: var(--muted);
}

.breadcrumb strong {
    color: var(--text);
}

.top-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.date-box {
    background: white;
    border: 1px solid var(--border);
    padding: 10px 15px;
    border-radius: 12px;
    color: #68758d;
    font-size: 12px;
    font-weight: 600;
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 7px 13px 7px 7px;
    border-radius: 13px;
    border: 1px solid var(--border);
}

.avatar {
    width: 37px;
    height: 37px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #4169f5, #7a91ff);
    border-radius: 11px;
    color: white;
    font-weight: 800;
    font-size: 16px;
}

.profile-info strong {
    display: block;
    font-size: 12px;
    color: var(--text);
}

.profile-info span {
    font-size: 10px;
    color: var(--muted);
}

/* HERO */

.hero {
    background: linear-gradient(115deg, #172b58 0%, #254b9e 55%, #4169f5 100%);
    border-radius: 23px;
    padding: 35px 38px;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
    box-shadow: 0 13px 35px rgba(43,79,166,.18);
    animation: appear .6s ease;
}

.hero::before {
    content: "";
    position: absolute;
    width: 300px;
    height: 300px;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 50%;
    right: -60px;
    top: -150px;
    box-shadow: 0 0 0 35px rgba(255,255,255,.035),
                0 0 0 70px rgba(255,255,255,.025);
}

.hero::after {
    content: "✦";
    position: absolute;
    font-size: 100px;
    color: rgba(255,255,255,.08);
    right: 100px;
    bottom: -30px;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-tag {
    display: inline-block;
    background: rgba(255,255,255,.14);
    padding: 7px 12px;
    border-radius: 30px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.3px;
    margin-bottom: 17px;
}

.hero h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(24px, 3vw, 34px);
    font-weight: 800;
    letter-spacing: -.8px;
    margin-bottom: 10px;
}

.hero h1 span {
    color: #b9cbff;
}

.hero p {
    color: #d0dcff;
    font-size: 13px;
    line-height: 1.8;
    max-width: 550px;
}

.hero-bottom {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 23px;
    font-size: 11px;
    color: #e0e8ff;
}

.online-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #58e6a2;
    box-shadow: 0 0 10px #58e6a2;
}

/* SECTION */

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 12px;
    margin-bottom: 18px;
}

.section-heading h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 19px;
    font-weight: 800;
}

.section-heading p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 5px;
}

.menu-count {
    color: #4169f5;
    background: #e9efff;
    padding: 7px 12px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 800;
}

/* MENU CARDS */

.menu-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.menu-card {
    background: white;
    border: 1px solid #edf0f6;
    border-radius: 18px;
    padding: 23px;
    position: relative;
    overflow: hidden;
    transition: .3s ease;
    animation: appear .7s ease both;
}

.menu-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 35px rgba(31,53,100,.09);
    border-color: #dbe4ff;
}

.menu-card::after {
    content: "";
    position: absolute;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    right: -45px;
    top: -45px;
    background: var(--card-light);
    opacity: .7;
    transition: .3s;
}

.menu-card:hover::after {
    transform: scale(1.5);
}

.card-icon {
    width: 51px;
    height: 51px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    font-size: 24px;
    background: var(--card-light);
    margin-bottom: 19px;
    position: relative;
    z-index: 1;
}

.menu-card h3 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 15px;
    font-weight: 800;
    margin-bottom: 9px;
    position: relative;
    z-index: 1;
}

.menu-card p {
    font-size: 11.5px;
    color: #8994a9;
    line-height: 1.8;
    min-height: 60px;
    margin-bottom: 19px;
    position: relative;
    z-index: 1;
}

.card-button {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 11px 13px;
    border-radius: 10px;
    background: var(--card-light);
    color: var(--card-color);
    font-size: 11px;
    font-weight: 800;
    transition: .2s;
    position: relative;
    z-index: 1;
}

.card-button:hover {
    background: var(--card-color);
    color: white;
}

.card-button span:last-child {
    font-size: 17px;
}

/* CARD COLORS */

.blue {
    --card-light: #edf2ff;
    --card-color: #4169f5;
}

.purple {
    --card-light: #f2edff;
    --card-color: #8962e8;
}

.cyan {
    --card-light: #e8faff;
    --card-color: #12a6c9;
}

.green {
    --card-light: #e8f9f0;
    --card-color: #1aa66b;
}

.orange {
    --card-light: #fff3e8;
    --card-color: #ed9143;
}

/* BOTTOM INFO */

.bottom-info {
    margin-top: 28px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 17px;
    padding: 19px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.info-left {
    display: flex;
    align-items: center;
    gap: 13px;
}

.info-symbol {
    width: 41px;
    height: 41px;
    display: grid;
    place-items: center;
    background: #edf2ff;
    border-radius: 12px;
    font-size: 19px;
}

.info-left strong {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}

.info-left span {
    color: var(--muted);
    font-size: 10px;
}

.logout-button {
    padding: 10px 16px;
    background: #fff0f0;
    color: #e34e5a;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 800;
    transition: .2s;
    white-space: nowrap;
}

.logout-button:hover {
    background: #e34e5a;
    color: white;
}

/* FOOTER */

.footer {
    text-align: center;
    padding: 25px 10px 5px;
    font-size: 10px;
    color: #a2aabb;
    line-height: 1.8;
}

/* ANIMATION */

@keyframes appear {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .menu-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .main {
        padding: 25px;
    }
}

@media (max-width: 750px) {
    .layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        min-height: auto;
        padding: 17px 18px;
    }

    .brand {
        margin-bottom: 20px;
    }

    .side-label,
    .sidebar-bottom {
        display: none;
    }

    .side-menu {
        flex-direction: row;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .side-link {
        flex-shrink: 0;
        padding: 11px 13px;
        font-size: 11px;
    }

    .main {
        padding: 20px 17px;
    }

    .topbar {
        margin-bottom: 22px;
    }

    .date-box {
        display: none;
    }

    .hero {
        padding: 27px 23px;
        border-radius: 18px;
    }

    .menu-grid {
        gap: 13px;
    }
}

@media (max-width: 500px) {
    .menu-grid {
        grid-template-columns: 1fr;
    }

    .menu-card {
        padding: 21px;
    }

    .menu-card p {
        min-height: auto;
    }

    .section-heading h2 {
        font-size: 16px;
    }

    .profile {
        padding-right: 9px;
    }

    .profile-info strong {
        max-width: 100px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .bottom-info {
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">🛠️</div>
            <div class="brand-name">
                TOOL MANAGEMENT
                <span>UKK XII RPL</span>
            </div>
        </div>

        <div class="side-label">MENU UTAMA</div>

        <nav class="side-menu">

            <a href="dashboard.php" class="side-link active">
                <span class="side-icon">⌂</span>
                Dashboard
            </a>

            <?php foreach ($menu as $item): ?>
                <a href="<?= htmlspecialchars($item['link']) ?>" class="side-link">
                    <span class="side-icon"><?= $item['icon'] ?></span>
                    <?= htmlspecialchars($item['title']) ?>
                </a>
            <?php endforeach; ?>

        </nav>

        <div class="sidebar-bottom">

            <div class="help-card">
                <h4>💡 Informasi Sistem</h4>
                <p>
                    Gunakan menu yang tersedia untuk mengakses
                    fitur sesuai dengan hak akses akun kamu.
                </p>
            </div>

            <div class="sidebar-footer">
                © 2026 Sistem Peminjaman Alat
                <br>
                UKK Bagoes XII RPL
            </div>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <!-- TOPBAR -->
        <div class="topbar">

            <div class="breadcrumb">
                Halaman / <strong>Dashboard</strong>
            </div>

            <div class="top-right">

                <div class="date-box" id="clock">
                    Memuat waktu...
                </div>

                <div class="profile">

                    <div class="avatar">
                        <?= htmlspecialchars($inisial) ?>
                    </div>

                    <div class="profile-info">
                        <strong><?= $nama ?></strong>
                        <span><?= htmlspecialchars($role) ?></span>
                    </div>

                </div>

            </div>

        </div>

        <!-- HERO -->
        <section class="hero">

            <div class="hero-content">

                <div class="hero-tag">
                    ✦ SISTEM INFORMASI PEMINJAMAN ALAT
                </div>

                <h1>
                    Selamat Datang,<br>
                    <span><?= $nama ?>!</span>
                </h1>

                <p>
                    Selamat datang di pusat pengelolaan peminjaman alat.
                    Kelola aktivitas dengan lebih mudah, cepat, dan terorganisir
                    melalui satu dashboard.
                </p>

                <div class="hero-bottom">
                    <span class="online-dot"></span>
                    Sistem aktif · Login sebagai <?= htmlspecialchars($role) ?>
                </div>

            </div>

        </section>

        <!-- MENU -->
        <div class="section-heading">

            <div>
                <h2>Jelajahi Menu</h2>
                <p>Pilih fitur yang ingin kamu gunakan.</p>
            </div>

            <div class="menu-count">
                <?= $totalMenu ?> MENU TERSEDIA
            </div>

        </div>

        <section class="menu-grid">

            <?php if ($totalMenu > 0): ?>

                <?php foreach ($menu as $item): ?>

                    <article class="menu-card <?= htmlspecialchars($item['color']) ?>">

                        <div class="card-icon">
                            <?= $item['icon'] ?>
                        </div>

                        <h3>
                            <?= htmlspecialchars($item['title']) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars($item['desc']) ?>
                        </p>

                        <a href="<?= htmlspecialchars($item['link']) ?>" class="card-button">
                            <span><?= htmlspecialchars($item['label']) ?></span>
                            <span>→</span>
                        </a>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <article class="menu-card orange">
                    <div class="card-icon">⚠️</div>
                    <h3>Akun Tidak Dikenali</h3>
                    <p>
                        Role akun tidak dikenali oleh sistem.
                        Silakan hubungi administrator.
                    </p>
                </article>

            <?php endif; ?>

        </section>

        <!-- BOTTOM INFO -->
        <div class="bottom-info">

            <div class="info-left">

                <div class="info-symbol">🔐</div>

                <div>
                    <strong>Akun kamu terlindungi</strong>
                    <span>Gunakan aplikasi sesuai hak akses yang diberikan.</span>
                </div>

            </div>

            <a href="logout.php" class="logout-button"
               onclick="return confirm('Apakah kamu yakin ingin keluar dari aplikasi?')">
                ↪ Logout
            </a>

        </div>

        <!-- FOOTER -->
        <footer class="footer">
            © 2026 Sistem Peminjaman Alat · UKK Bagoes XII RPL
            <br>
            Designed for Better Equipment Management
        </footer>

    </main>

</div>

<script>
function updateClock() {
    const now = new Date();

    const options = {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    };

    const date = now.toLocaleDateString('id-ID', options);
    const time = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });

    document.getElementById('clock').textContent = date + ' · ' + time;
}

updateClock();
setInterval(updateClock, 1000);
</script>

</body>
</html>
