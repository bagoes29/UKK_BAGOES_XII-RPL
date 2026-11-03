-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 03, 2026 at 06:05 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_ukk_bagoes_xii_rpl`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cek_stok_peminjaman` (IN `p_id_peminjaman` INT)   BEGIN
    DECLARE v_jumlah_data INT DEFAULT 0;
    DECLARE v_stok_kurang INT DEFAULT 0;

    SELECT COUNT(*)
    INTO v_jumlah_data
    FROM detail_peminjaman
    WHERE id_peminjaman = p_id_peminjaman;

    IF v_jumlah_data = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Detail peminjaman tidak ditemukan';
    END IF;

    SELECT COUNT(*)
    INTO v_stok_kurang
    FROM detail_peminjaman dp
    INNER JOIN alat a
        ON dp.id_alat = a.id_alat
    WHERE dp.id_peminjaman = p_id_peminjaman
      AND (
          a.stok < dp.jumlah_pinjam
          OR a.kondisi <> 'Baik'
      );

    IF v_stok_kurang > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Stok tidak mencukupi atau kondisi alat tidak baik';
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `alat`
--

CREATE TABLE `alat` (
  `id_alat` int NOT NULL,
  `id_kategori` int NOT NULL,
  `kode_alat` varchar(30) NOT NULL,
  `nama_alat` varchar(100) NOT NULL,
  `jumlah` int NOT NULL DEFAULT '0',
  `stok` int NOT NULL DEFAULT '0',
  `kondisi` varchar(50) NOT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `alat`
--

INSERT INTO `alat` (`id_alat`, `id_kategori`, `kode_alat`, `nama_alat`, `jumlah`, `stok`, `kondisi`, `lokasi`, `created_at`) VALUES
(1, 3, 'ALT0001', 'HANDPHONE', 7, 3, 'Baik', 'LABORATORIUM', '2026-11-03 01:00:37'),
(2, 4, 'ALT0002', 'LAPTOP IPHONE', 20, 20, 'Baik', 'LABORATORIUM', '2026-11-03 01:01:20');

-- --------------------------------------------------------

--
-- Table structure for table `detail_peminjaman`
--

CREATE TABLE `detail_peminjaman` (
  `id_detail` int NOT NULL,
  `id_peminjaman` int NOT NULL,
  `id_alat` int NOT NULL,
  `jumlah_pinjam` int NOT NULL
) ;

--
-- Dumping data for table `detail_peminjaman`
--

INSERT INTO `detail_peminjaman` (`id_detail`, `id_peminjaman`, `id_alat`, `jumlah_pinjam`) VALUES
(1, 1, 1, 4),
(2, 2, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `deskripsi`, `created_at`) VALUES
(2, 'tv', 'layar lebar', '2026-11-02 08:01:13'),
(3, 'hanphone', 'layar kecil', '2026-11-03 00:36:33'),
(4, 'laptop', 'pekerjaan sekioolah', '2026-11-03 00:36:53'),
(5, 'modem', 'perlenkapan', '2026-11-03 00:37:04'),
(6, 'mous', '', '2026-11-03 00:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id_log` int NOT NULL,
  `id_user` int DEFAULT NULL,
  `aktivitas` text NOT NULL,
  `waktu` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id_log`, `id_user`, `aktivitas`, `waktu`) VALUES
(1, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 00:49:50'),
(2, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 00:51:27'),
(3, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 00:52:00'),
(4, 2, 'Login ke aplikasi sebagai Petugas', '2026-11-03 00:56:53'),
(5, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 00:57:12'),
(6, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 00:59:38'),
(7, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 01:01:29'),
(8, 3, 'Mengajukan peminjaman ID 1', '2026-11-03 01:02:07'),
(9, 2, 'Login ke aplikasi sebagai Petugas', '2026-11-03 01:02:21'),
(10, 2, 'Menyetujui peminjaman ID 1', '2026-11-03 01:02:27'),
(11, 2, 'Login ke aplikasi sebagai Petugas', '2026-11-03 01:05:03'),
(12, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:05:09'),
(13, 2, 'Login ke aplikasi sebagai Petugas', '2026-11-03 01:06:27'),
(14, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:07:17'),
(15, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:09:51'),
(16, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:12:43'),
(17, 1, 'Menambahkan pengguna: Tegar (Username: Tegar, Role: Peminjam, ID: 4)', '2026-11-03 01:16:53'),
(18, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 01:29:59'),
(19, 3, 'Mengajukan peminjaman ID 2', '2026-11-03 01:30:58'),
(20, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:32:22'),
(21, 2, 'Login ke aplikasi sebagai Petugas', '2026-11-03 01:32:42'),
(22, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:32:56'),
(23, 1, 'Mengubah data alat ID 2 (LAPTOP IPHONE): Kondisi: Rusak Ringan menjadi Baik', '2026-11-03 01:33:14'),
(24, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 01:34:17'),
(25, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:37:30'),
(26, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:37:47'),
(27, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:40:37'),
(28, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:42:30'),
(29, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 01:47:07'),
(30, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 02:03:36'),
(31, 2, 'Login ke aplikasi sebagai Petugas', '2026-11-03 02:07:25'),
(32, 2, 'Login ke aplikasi sebagai Petugas', '2026-11-03 02:42:30'),
(33, 2, 'Menolak peminjaman ID 2', '2026-11-03 02:42:55'),
(34, 1, 'Login ke aplikasi sebagai Admin', '2026-11-03 02:43:09'),
(35, 3, 'Login ke aplikasi sebagai Peminjam', '2026-11-03 02:43:25');

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id_peminjaman` int NOT NULL,
  `id_user` int NOT NULL,
  `id_petugas` int DEFAULT NULL,
  `tanggal_pengajuan` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_rencana_kembali` date NOT NULL,
  `keperluan` text NOT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak','Selesai') DEFAULT 'Menunggu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id_peminjaman`, `id_user`, `id_petugas`, `tanggal_pengajuan`, `tanggal_pinjam`, `tanggal_rencana_kembali`, `keperluan`, `status`) VALUES
(1, 3, 2, '2026-11-03 01:02:07', '2026-11-04', '2026-11-05', 'BUAT SELFI', 'Disetujui'),
(2, 3, 2, '2026-11-03 01:30:58', '2026-11-18', '2026-11-19', 'gabut', 'Ditolak');

--
-- Triggers `peminjaman`
--
DELIMITER $$
CREATE TRIGGER `trg_kurangi_stok` AFTER UPDATE ON `peminjaman` FOR EACH ROW BEGIN
    IF OLD.status = 'Menunggu'
       AND NEW.status = 'Disetujui' THEN

        UPDATE alat a
        INNER JOIN detail_peminjaman dp
            ON a.id_alat = dp.id_alat
        SET a.stok = a.stok - dp.jumlah_pinjam
        WHERE dp.id_peminjaman = NEW.id_peminjaman;

    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id_pengembalian` int NOT NULL,
  `id_peminjaman` int NOT NULL,
  `id_petugas` int DEFAULT NULL,
  `tanggal_kembali` date NOT NULL,
  `terlambat` int NOT NULL DEFAULT '0',
  `denda` decimal(12,2) NOT NULL DEFAULT '0.00',
  `kondisi_kembali` varchar(100) NOT NULL,
  `catatan` text
) ;

--
-- Triggers `pengembalian`
--
DELIMITER $$
CREATE TRIGGER `trg_tambah_stok` AFTER INSERT ON `pengembalian` FOR EACH ROW BEGIN

    UPDATE alat a
    INNER JOIN detail_peminjaman dp
        ON a.id_alat = dp.id_alat
    SET a.stok = a.stok + dp.jumlah_pinjam
    WHERE dp.id_peminjaman = NEW.id_peminjaman;

    UPDATE peminjaman
    SET status = 'Selesai'
    WHERE id_peminjaman = NEW.id_peminjaman;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Administrator', 'admin', '$2y$10$yNJlZ6dqMnKnmM7JWic/9.A20RUX.AynSX0Vt0OXpOpzodL3uOMum', 'Admin', '2026-11-02 07:34:37'),
(2, 'Petugas', 'petugas', '$2y$10$IUT91hmydwMVr/Gs/lWLxuc3Y.GXcrYGpYYHCG6T3Q3O2aBabXquG', 'Petugas', '2026-11-02 07:34:37'),
(3, 'Bagoes', 'bagoes', '$2y$10$tG1f/cSEcx4PYVDdcvrq2OP7F41MME7axER4mgTHRpKXXoX1uzKNe', 'Peminjam', '2026-11-02 07:34:37'),
(4, 'Tegar', 'Tegar', '$2y$10$.b7fC1AIxFNZQHxBC5eQhud5TFY/1mXXM9y2JvjJ4S7qYfAgyiL7e', 'Peminjam', '2026-11-03 01:16:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alat`
--
ALTER TABLE `alat`
  ADD PRIMARY KEY (`id_alat`),
  ADD UNIQUE KEY `kode_alat` (`kode_alat`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indexes for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD PRIMARY KEY (`id_detail`),
  ADD UNIQUE KEY `id_peminjaman` (`id_peminjaman`,`id_alat`),
  ADD KEY `id_alat` (`id_alat`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id_peminjaman`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indexes for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id_pengembalian`),
  ADD UNIQUE KEY `id_peminjaman` (`id_peminjaman`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alat`
--
ALTER TABLE `alat`
  MODIFY `id_alat` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  MODIFY `id_detail` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id_peminjaman` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id_pengembalian` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alat`
--
ALTER TABLE `alat`
  ADD CONSTRAINT `alat_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD CONSTRAINT `detail_peminjaman_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`),
  ADD CONSTRAINT `detail_peminjaman_ibfk_2` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id_alat`);

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `log_aktivitas_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`id_petugas`) REFERENCES `users` (`id`);

--
-- Constraints for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`),
  ADD CONSTRAINT `pengembalian_ibfk_2` FOREIGN KEY (`id_petugas`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
