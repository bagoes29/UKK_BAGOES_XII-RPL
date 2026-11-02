```php
<?php
session_start();
require_once "config.php";

// ==================================
// CEK HAK AKSES PETUGAS
// ==================================

if (!isset($_SESSION["id"]) || $_SESSION["role"] !== "Petugas") {
    http_response_code(403);
    exit("403 - Anda tidak memiliki akses!");
}

// ==================================
// TOKEN KEAMANAN
// ==================================

if (!isset($_SESSION["csrf_persetujuan"])) {
    $_SESSION["csrf_persetujuan"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_persetujuan"];
$error = "";

// ==================================
// PROSES PERSETUJUAN / PENOLAKAN
// ==================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($token, $_POST["csrf_token"])
    ) {
        $error = "Permintaan tidak valid.";

    } else {

        $id_peminjaman = (int) ($_POST["id_peminjaman"] ?? 0);
        $aksi = $_POST["aksi"] ?? "";

        if (
            $id_peminjaman <= 0 ||
            !in_array($aksi, ["Disetujui", "Ditolak"], true)
        ) {
            $error = "Data permintaan tidak valid.";

        } else {

            try {

                $pdo->beginTransaction();

                // Kunci data peminjaman
                $cek = $pdo->prepare(
                    "SELECT id_peminjaman, status
                     FROM peminjaman
                     WHERE id_peminjaman = ?
                     FOR UPDATE"
                );

                $cek->execute([$id_peminjaman]);
                $peminjaman = $cek->fetch(PDO::FETCH_ASSOC);

                if (
                    !$peminjaman ||
                    $peminjaman["status"] !== "Menunggu"
                ) {

                    $pdo->rollBack();
                    $error = "Pengajuan tidak ditemukan atau sudah diproses.";

                } elseif ($aksi === "Disetujui") {

                    // ==================================
                    // KUNCI DATA STOK ALAT
                    // ==================================

                    $detail = $pdo->prepare(
                        "SELECT
                            detail_peminjaman.id_alat,
                            detail_peminjaman.jumlah_pinjam,
                            alat.stok,
                            alat.kondisi
                         FROM detail_peminjaman
                         INNER JOIN alat
                            ON detail_peminjaman.id_alat = alat.id_alat
                         WHERE detail_peminjaman.id_peminjaman = ?
                         FOR UPDATE"
                    );

                    $detail->execute([$id_peminjaman]);

                    $daftarDetail = $detail->fetchAll(PDO::FETCH_ASSOC);

                    if (count($daftarDetail) === 0) {

                        $pdo->rollBack();
                        $error = "Detail alat pada pengajuan tidak ditemukan.";

                    } else {

                        // ==================================
                        // JALANKAN STORED PROCEDURE CEK STOK
                        // ==================================

                        $cekStok = $pdo->prepare(
                            "CALL sp_cek_stok_peminjaman(?)"
                        );

                        $cekStok->execute([$id_peminjaman]);
                        $cekStok->closeCursor();

                        // ==================================
                        // UBAH STATUS MENJADI DISETUJUI
                        // ==================================

                        $ubah = $pdo->prepare(
                            "UPDATE peminjaman
                             SET status = 'Disetujui',
                                 id_petugas = ?
                             WHERE id_peminjaman = ?
                               AND status = 'Menunggu'"
                        );

                        $ubah->execute([
                            $_SESSION["id"],
                            $id_peminjaman
                        ]);

                        // ==================================
                        // TRIGGER AKAN MENGURANGI STOK
                        // ==================================
                        // Pengurangan stok dilakukan otomatis
                        // oleh trg_kurangi_stok.
                        // Tidak perlu UPDATE stok di PHP.

                        // ==================================
                        // CATAT AKTIVITAS
                        // ==================================

                        $log = $pdo->prepare(
                            "INSERT INTO log_aktivitas
                                (id_user, aktivitas)
                             VALUES (?, ?)"
                        );

                        $log->execute([
                            $_SESSION["id"],
                            "Menyetujui peminjaman ID " . $id_peminjaman
                        ]);

                        $pdo->commit();

                        header("Location: persetujuan.php?pesan=setuju");
                        exit;
                    }

                } else {

                    // ==================================
                    // PROSES PENOLAKAN
                    // ==================================

                    $ubah = $pdo->prepare(
                        "UPDATE peminjaman
                         SET status = 'Ditolak',
                             id_petugas = ?
                         WHERE id_peminjaman = ?
                           AND status = 'Menunggu'"
                    );

                    $ubah->execute([
                        $_SESSION["id"],
                        $id_peminjaman
                    ]);

                    // Catat aktivitas penolakan
                    $log = $pdo->prepare(
                        "INSERT INTO log_aktivitas
                            (id_user, aktivitas)
                         VALUES (?, ?)"
                    );

                    $log->execute([
                        $_SESSION["id"],
                        "Menolak peminjaman ID " . $id_peminjaman
                    ]);

                    $pdo->commit();

                    header("Location: persetujuan.php?pesan=tolak");
                    exit;
                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                // Tampilkan pesan dari Stored Procedure
                $info = $e->errorInfo ?? [];

                if (($info[0] ?? "") === "45000") {

                    $error = $info[2] ??
                        "Stok tidak mencukupi atau kondisi alat tidak baik.";

                } elseif (
                    stripos($e->getMessage(), "sp_cek_stok_peminjaman") !== false
                ) {

                    $error = "Stored Procedure sp_cek_stok_peminjaman belum ditemukan. Periksa kembali di phpMyAdmin.";

                } else {

                    $error = "Terjadi kesalahan saat memproses pengajuan. Periksa konfigurasi database dan Trigger.";
                }
            }
        }
    }
}

// ==================================
// AMBIL DATA PENGAJUAN
// ==================================

$sql = $pdo->query(
    "SELECT
        peminjaman.id_peminjaman,
        users.nama,
        peminjaman.tanggal_pengajuan,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.keperluan,
        peminjaman.status,
        GROUP_CONCAT(
            CONCAT(
                alat.nama_alat,
                ' (',
                detail_peminjaman.jumlah_pinjam,
                ')'
            )
            SEPARATOR ', '
        ) AS daftar_alat
     FROM peminjaman
     INNER JOIN users
        ON peminjaman.id_user = users.id
     LEFT JOIN detail_peminjaman
        ON peminjaman.id_peminjaman = detail_peminjaman.id_peminjaman
     LEFT JOIN alat
        ON detail_peminjaman.id_alat = alat.id_alat
     WHERE peminjaman.status = 'Menunggu'
     GROUP BY
        peminjaman.id_peminjaman,
        users.nama,
        peminjaman.tanggal_pengajuan,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.keperluan,
        peminjaman.status
     ORDER BY peminjaman.id_peminjaman DESC"
);

$pengajuan = $sql->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Persetujuan Peminjaman</title>

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
            max-width: 1150px;
            margin: auto;
            background: white;
            padding: 25px;
            border