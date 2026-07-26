-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 01, 2025 at 07:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sampah5`
--

-- --------------------------------------------------------

--
-- Table structure for table `tb_harga_sampah`
--

CREATE TABLE `tb_harga_sampah` (
  `id` int(11) NOT NULL,
  `jenis_sampah` varchar(100) NOT NULL,
  `harga_per_kg` decimal(10,2) NOT NULL,
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_harga_sampah`
--

INSERT INTO `tb_harga_sampah` (`id`, `jenis_sampah`, `harga_per_kg`, `last_updated`) VALUES
(1, 'ALE-ALE', '5000.00', '2025-07-22 05:51:05'),
(2, 'ALMUNIUM A', '9000.00', '2025-07-22 05:05:48'),
(3, 'BUKU CAMPUR', '1800.00', '2025-07-22 05:05:48'),
(4, 'KARDUS', '1500.00', '2025-07-22 05:05:48'),
(5, 'KERTAS HVS', '2000.00', '2025-07-22 05:05:48'),
(6, 'KORAN', '1200.00', '2025-07-22 05:05:48'),
(7, 'PLASTIK BENING', '2500.00', '2025-07-22 05:05:48'),
(8, 'GELAS BERSIH', '3000.00', '2025-07-22 05:05:48'),
(9, 'KALENG', '7000.00', '2025-07-22 05:05:48'),
(10, 'ALMUNIUM B', '8000.00', '2025-07-22 05:05:48'),
(11, 'BELING A', '3000.00', '2025-07-22 06:59:42'),
(12, 'BELING B', '200.00', '2025-07-22 05:05:48'),
(13, 'BESI A', '2800.00', '2025-07-22 05:05:48'),
(14, 'BESI B', '2001.00', '2025-07-24 14:14:45'),
(15, 'BESI SUPER', '3500.00', '2025-07-22 05:05:48'),
(16, 'BODONG BERSIH', '4000.00', '2025-07-22 05:05:48'),
(17, 'BODONG KOTOR', '2500.00', '2025-07-22 05:05:48'),
(18, 'BOTOL AM', '400.00', '2025-07-22 05:05:48'),
(19, 'BOTOL BIR', '800.00', '2025-07-22 05:05:48'),
(20, 'BOTOL KECAP', '200.00', '2025-07-22 05:05:48'),
(21, 'BOTOL WARNA', '1000.00', '2025-07-22 05:05:48'),
(22, 'CAMPUR', '500.00', '2025-07-22 05:05:48'),
(23, 'CAMPUR WARNA', '700.00', '2025-07-22 05:05:48'),
(24, 'DUPLEK', '1000.00', '2025-07-22 05:05:48'),
(25, 'GELAS KOTOR', '1500.00', '2025-07-22 05:05:48'),
(26, 'GALON', '10000.00', '2025-07-22 05:05:48'),
(27, 'GALON LEMINERAL', '9000.00', '2025-07-22 05:05:48'),
(28, 'GELAS WARNA', '800.00', '2025-07-22 05:05:48'),
(29, 'IMPEK', '5000.00', '2025-07-22 05:05:48'),
(30, 'JLIGEN', '6000.00', '2025-07-22 05:05:48'),
(31, 'KABEL', '15000.00', '2025-07-22 05:05:48'),
(32, 'KERTAS BURAM', '800.00', '2025-07-22 05:05:48'),
(33, 'KOMPOR MINYAK', '1500.00', '2025-07-22 05:05:48'),
(34, 'KRESEK', '500.00', '2025-07-22 05:05:48'),
(35, 'KERTAS SEMEN', '1000.00', '2025-07-22 05:05:48'),
(36, 'MEGICCOM', '1000.00', '2025-07-22 05:05:48'),
(37, 'MAJALAH', '1000.00', '2025-07-22 05:05:48'),
(38, 'MIKA', '1000.00', '2025-07-22 05:05:48'),
(39, 'MINYAK', '500.00', '2025-07-22 05:05:48'),
(40, 'NUTRIBUS', '600.00', '2025-07-22 05:05:48'),
(41, 'PUTIHAN', '2000.00', '2025-07-22 05:05:48'),
(42, 'PIPA', '1500.00', '2025-07-22 05:05:48'),
(43, 'PRALON', '1200.00', '2025-07-22 05:05:48'),
(44, 'SENG', '2000.00', '2025-07-22 05:05:48'),
(45, 'TUTUP BOTOL', '100.00', '2025-07-22 05:05:48'),
(46, 'TUTUP GALON', '50.00', '2025-07-22 05:05:48'),
(47, 'TALANG', '700.00', '2025-07-22 05:05:48'),
(48, 'TV', '1000.00', '2025-07-22 05:05:48'),
(49, 'YAKUL', '100.00', '2025-07-22 05:05:48');

-- --------------------------------------------------------

--
-- Table structure for table `tb_jadwal_pengambilan`
--

CREATE TABLE `tb_jadwal_pengambilan` (
  `id_jadwal` int(11) NOT NULL,
  `tanggal_jadwal` date NOT NULL,
  `rt` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `rw` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `keterangan` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_konfigurasi`
--

CREATE TABLE `tb_konfigurasi` (
  `id_konfigurasi` int(11) NOT NULL,
  `nama_setting` varchar(100) NOT NULL,
  `nilai` decimal(5,2) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `terakhir_diubah` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_konfigurasi`
--

INSERT INTO `tb_konfigurasi` (`id_konfigurasi`, `nama_setting`, `nilai`, `deskripsi`, `terakhir_diubah`) VALUES
(1, 'persen_nasabah', '80.00', 'Persentase hasil untuk Nasabah', '2025-07-31 15:17:34'),
(2, 'persen_tps', '15.00', 'Persentase hasil untuk TPS', '2025-07-31 15:17:34'),
(3, 'persen_pengepul', '5.00', 'Persentase hasil untuk Pengepul', '2025-07-31 15:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `tb_nasabah`
--

CREATE TABLE `tb_nasabah` (
  `no_rekening` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `nama_nasabah` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `rt` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `rw` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `alamat` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `no_hp` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `no_rek_bank` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `nama_bank` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `nama_pemilik_rekening` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `username` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `password` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `role` varchar(512) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `status` enum('aktif','tidak aktif') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_nasabah`
--

INSERT INTO `tb_nasabah` (`no_rekening`, `nama_nasabah`, `rt`, `rw`, `alamat`, `no_hp`, `no_rek_bank`, `nama_bank`, `nama_pemilik_rekening`, `username`, `password`, `role`, `status`) VALUES
('A001', 'Admin Utama', '000', '000', 'Kantor Bank Sampah', '089876543210', NULL, NULL, NULL, 'admin', '$2y$10$nkGTYfHdZRU5z7QxO9BwguunskOTKCvag64lxEN6jMsylR8whYEce', 'admin', 'aktif'),
('N001', 'Nasabah Demo', '001', '001', 'Jl. Demo No. 1', '081234567890', '1234567890', 'Bank Sampah', 'Nasabah Demo', 'demo', '$2y$10$IDZbqP2XT3IjUiv839izpe.VD/dDyXWBwhSI0rB/J4Tn2Nud1tfLq', 'nasabah', 'aktif');
INSERT INTO `tb_nasabah` (`no_rekening`, `nama_nasabah`, `rt`, `rw`, `alamat`, `no_hp`, `no_rek_bank`, `nama_bank`, `nama_pemilik_rekening`, `username`, `password`, `role`, `status`) VALUES
('A001', 'Admin Utama', '000', '000', 'Kantor Bank Sampah', '089876543210', NULL, NULL, NULL, 'admin', '$2y$10$nkGTYfHdZRU5z7QxO9BwguunskOTKCvag64lxEN6jMsylR8whYEce', 'admin', 'aktif'),
('N001', 'Nasabah Demo', '001', '001', 'Jl. Demo No. 1', '081234567890', '1234567890', 'Bank Sampah', 'Nasabah Demo', 'demo', '$2y$10$IDZbqP2XT3IjUiv839izpe.VD/dDyXWBwhSI0rB/J4Tn2Nud1tfLq', 'nasabah', 'aktif');

-- --------------------------------------------------------

--
-- Table structure for table `tb_pencairan_dana`
--

CREATE TABLE `tb_pencairan_dana` (
  `id_pencairan` int(11) NOT NULL,
  `no_rekening` varchar(100) NOT NULL,
  `tanggal_pencairan` date NOT NULL,
  `jumlah_cair` decimal(12,2) NOT NULL,
  `status` enum('pending','diterima','ditolak') DEFAULT 'pending',
  `keterangan` text DEFAULT NULL,
  `tanggal_transfer` date DEFAULT NULL,
  `bukti_transfer_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_pencairan_dana`
--

(1, 'P004/01/009', '2025-07-24', '3000.00', 'diterima', 'transfer', NULL, NULL),
(2, 'P004/01/009', '2025-07-24', '2000.00', 'diterima', 'Cash / 02 - 20 -2025', NULL, NULL),
(3, 'P004/01/009', '2025-07-24', '1000.00', 'diterima', 'Permintaan pencairan dana oleh nasabah.', '2025-07-24', '../uploads/bukti_pencairan/bukti_6881f82df3133_2.jpg'),
(4, 'P004/01/009', '2025-07-24', '1000.00', 'diterima', 'Permintaan pencairan dana oleh nasabah.', '2025-07-24', '../uploads/bukti_pencairan/bukti_6881f8f5cf5e3_4.jpg'),
(5, 'P004/01/009', '2025-07-31', '10000.00', 'diterima', 'Permintaan pencairan dana oleh nasabah.', '2025-07-31', '../uploads/bukti_pencairan/bukti_688b26a02898a_Revisi Activity Diagram.drawio.png');

-- --------------------------------------------------------

--
-- Table structure for table `tb_sampah`
--

CREATE TABLE `tb_sampah` (
  `id_sampah` int(11) NOT NULL,
  `kode_sampah` varchar(20) NOT NULL,
  `jenis_sampah` varchar(100) DEFAULT NULL,
  `kategori_sampah` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_sampah`
--

INSERT INTO `tb_sampah` (`id_sampah`, `kode_sampah`, `jenis_sampah`, `kategori_sampah`) VALUES
(45, 'AL', 'ALE-ALE', 'PLASTIK'),
(23, 'AL A', 'ALMUNIUM A', 'LOGAM'),
(24, 'AL B', 'ALMUNIUM B', 'LOGAM'),
(38, 'BkC', 'BUKU CAMPUR', 'KERTAS'),
(13, 'BL A', 'BELING A', 'BELING'),
(14, 'BL B', 'BELING B', 'BELING'),
(20, 'BS A', 'BESI A', 'LOGAM'),
(21, 'BS B', 'BESI B', 'LOGAM'),
(22, 'BS S', 'BESI SUPER', 'LOGAM'),
(7, 'BTB', 'BODONG BERSIH', 'PLASTIK'),
(33, 'BTK', 'BODONG KOTOR', 'PLASTIK'),
(17, 'BTL AM', 'BOTOL AM', 'BELING'),
(18, 'BTL BIR', 'BOTOL BIR', 'BELING'),
(16, 'BTL KCP', 'BOTOL KECAP', 'BELING'),
(15, 'BW', 'BOTOL WARNA', 'PLASTIK'),
(32, 'C', 'CAMPUR', 'PLASTIK'),
(10, 'CW', 'CAMPUR WARNA', 'PLASTIK'),
(2, 'D', 'DUPLEK', 'KERTAS'),
(8, 'GBB', 'GELAS BERSIH', 'PLASTIK'),
(34, 'GBK', 'GELAS KOTOR', 'PLASTIK'),
(27, 'GL', 'GALON', 'PLASTIK'),
(41, 'GLL', 'GALON LEMINERAL', 'PLASTIK'),
(9, 'GW', 'GELAS WARNA', 'PLASTIK'),
(3, 'HVS', 'KERTAS HVS', 'KERTAS'),
(49, 'IK', 'IMPEK', 'LOGAM'),
(31, 'IMPEK', 'PRINTER', 'LOGAM'),
(40, 'JN', 'JLIGEN', 'PLASTIK'),
(30, 'KBL', 'KABEL', 'LOGAM'),
(4, 'KbR', 'KERTAS BURAM', 'KERTAS'),
(1, 'KD', 'KARDUS', 'KERTAS'),
(25, 'KL', 'KALENG', 'LOGAM'),
(48, 'KM', 'KOMPOR MINYAK', 'LOGAM'),
(6, 'KrN', 'KORAN', 'KERTAS'),
(12, 'KrS', 'KRESEK', 'PLASTIK'),
(5, 'KSm', 'KERTAS SEMEN', 'KERTAS'),
(47, 'MG', 'MEGICCOM', 'LOGAM'),
(50, 'MJ', 'MAJALAH', 'KERTAS'),
(36, 'MK', 'MIKA', 'PLASTIK'),
(42, 'MYK', 'MINYAK', 'MINYAK'),
(44, 'NT', 'NUTRIBUS', 'PLASTIK'),
(19, 'P', 'PUTIHAN', 'PLASTIK'),
(46, 'PA', 'PIPA', 'PLASTIK'),
(11, 'PLB', 'PLASTIK BENING', 'PLASTIK'),
(43, 'PRL', 'PRALON', 'PLASTIK'),
(26, 'SG', 'SENG', 'LOGAM'),
(28, 'TB', 'TUTUP BOTOL', 'PLASTIK'),
(29, 'TG', 'TUTUP GALON', 'PLASTIK'),
(39, 'TL', 'TALANG', 'PLASTIK'),
(37, 'TV', 'TV', 'LOGAM'),
(35, 'YKL', 'YAKUL', 'PLASTIK');

-- --------------------------------------------------------

--
-- Table structure for table `tb_setorsampah`
--

CREATE TABLE `tb_setorsampah` (
  `id_transaksi` int(11) NOT NULL,
  `tanggal_pengambilan` date NOT NULL,
  `no_rekening` varchar(100) NOT NULL,
  `nama_nasabah` varchar(100) DEFAULT NULL,
  `kode_sampah` varchar(20) NOT NULL,
  `jenis_sampah` varchar(100) DEFAULT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `berat_kg` decimal(10,2) DEFAULT NULL,
  `harga_per_kg` decimal(10,2) DEFAULT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `tabungan_nasabah` decimal(12,2) DEFAULT NULL,
  `pos_penimbangan` decimal(12,2) DEFAULT NULL,
  `tps3r` decimal(12,2) DEFAULT NULL,
  `status_setoran` enum('pending_harga','final') NOT NULL DEFAULT 'pending_harga',
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_tabungan_nasabah`
--

CREATE TABLE `tb_tabungan_nasabah` (
  `id_mutasi` int(11) NOT NULL,
  `no_rekening` varchar(100) NOT NULL,
  `tanggal_mutasi` date NOT NULL,
  `tipe_mutasi` enum('masuk','keluar') NOT NULL,
  `jumlah_mutasi` decimal(12,2) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_transaksi_penjualan`
--

CREATE TABLE `tb_transaksi_penjualan` (
  `id_penjualan` int(11) NOT NULL,
  `tanggal_jual` date NOT NULL,
  `kode_sampah` varchar(20) NOT NULL,
  `berat_kg` decimal(10,2) NOT NULL,
  `harga_jual_per_kg` decimal(12,2) NOT NULL,
  `total_penjualan` decimal(15,2) GENERATED ALWAYS AS (`berat_kg` * `harga_jual_per_kg`) STORED,
  `nama_pengepul` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tb_harga_sampah`
--
ALTER TABLE `tb_harga_sampah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jenis_sampah` (`jenis_sampah`);

--
-- Indexes for table `tb_jadwal_pengambilan`
--
ALTER TABLE `tb_jadwal_pengambilan`
  ADD PRIMARY KEY (`id_jadwal`);

--
-- Indexes for table `tb_konfigurasi`
--
ALTER TABLE `tb_konfigurasi`
  ADD PRIMARY KEY (`id_konfigurasi`),
  ADD UNIQUE KEY `nama_setting` (`nama_setting`);

--
-- Indexes for table `tb_nasabah`
--
ALTER TABLE `tb_nasabah`
  ADD PRIMARY KEY (`no_rekening`);

--
-- Indexes for table `tb_pencairan_dana`
--
ALTER TABLE `tb_pencairan_dana`
  ADD PRIMARY KEY (`id_pencairan`),
  ADD KEY `no_rekening` (`no_rekening`);

--
-- Indexes for table `tb_sampah`
--
ALTER TABLE `tb_sampah`
  ADD PRIMARY KEY (`kode_sampah`);

--
-- Indexes for table `tb_setorsampah`
--
ALTER TABLE `tb_setorsampah`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `no_rekening` (`no_rekening`);

--
-- Indexes for table `tb_tabungan_nasabah`
--
ALTER TABLE `tb_tabungan_nasabah`
  ADD PRIMARY KEY (`id_mutasi`),
  ADD KEY `no_rekening` (`no_rekening`);

--
-- Indexes for table `tb_transaksi_penjualan`
--
ALTER TABLE `tb_transaksi_penjualan`
  ADD PRIMARY KEY (`id_penjualan`),
  ADD KEY `fk_kode_sampah` (`kode_sampah`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tb_harga_sampah`
--
ALTER TABLE `tb_harga_sampah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `tb_jadwal_pengambilan`
--
ALTER TABLE `tb_jadwal_pengambilan`
  MODIFY `id_jadwal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tb_konfigurasi`
--
ALTER TABLE `tb_konfigurasi`
  MODIFY `id_konfigurasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tb_pencairan_dana`
--
ALTER TABLE `tb_pencairan_dana`
  MODIFY `id_pencairan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tb_setorsampah`
--
ALTER TABLE `tb_setorsampah`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tb_tabungan_nasabah`
--
ALTER TABLE `tb_tabungan_nasabah`
  MODIFY `id_mutasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tb_transaksi_penjualan`
--
ALTER TABLE `tb_transaksi_penjualan`
  MODIFY `id_penjualan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tb_pencairan_dana`
--
ALTER TABLE `tb_pencairan_dana`
  ADD CONSTRAINT `tb_pencairan_dana_ibfk_1` FOREIGN KEY (`no_rekening`) REFERENCES `tb_nasabah` (`no_rekening`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tb_setorsampah`
--
ALTER TABLE `tb_setorsampah`
  ADD CONSTRAINT `tb_setorsampah_ibfk_1` FOREIGN KEY (`no_rekening`) REFERENCES `tb_nasabah` (`no_rekening`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tb_tabungan_nasabah`
--
ALTER TABLE `tb_tabungan_nasabah`
  ADD CONSTRAINT `tb_tabungan_nasabah_ibfk_1` FOREIGN KEY (`no_rekening`) REFERENCES `tb_nasabah` (`no_rekening`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tb_transaksi_penjualan`
--
ALTER TABLE `tb_transaksi_penjualan`
  ADD CONSTRAINT `fk_kode_sampah` FOREIGN KEY (`kode_sampah`) REFERENCES `tb_sampah` (`kode_sampah`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;