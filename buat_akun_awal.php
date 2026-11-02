<?php

require_once "config.php";

$akun = [
    [
        "nama" => "Administrator",
        "username" => "admin",
        "password" => "admin123",
        "role" => "Admin"
    ],
    [
        "nama" => "Petugas",
        "username" => "petugas",
        "password" => "petugas123",
        "role" => "Petugas"
    ],
    [
        "nama" => "Bagoes",
        "username" => "bagoes",
        "password" => "bagoes123",
        "role" => "Peminjam"
    ]
];

foreach ($akun as $data) {

    $cek = $pdo->prepare(
        "SELECT id FROM users WHERE username = ?"
    );

    $cek->execute([$data["username"]]);

    if ($cek->fetch()) {
        echo "Akun " . htmlspecialchars($data["username"])
             . " sudah tersedia.<br>";
    } else {

        $passwordHash = password_hash(
            $data["password"],
            PASSWORD_DEFAULT
        );

        $simpan = $pdo->prepare(
            "INSERT INTO users
            (nama, username, password, role)
            VALUES (?, ?, ?, ?)"
        );

        $simpan->execute([
            $data["nama"],
            $data["username"],
            $passwordHash,
            $data["role"]
        ]);

        echo "Akun " . htmlspecialchars($data["username"])
             . " berhasil dibuat.<br>";
    }
}

echo "<h2>Pembuatan akun selesai!</h2>";

?>