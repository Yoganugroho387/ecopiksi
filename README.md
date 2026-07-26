# Sistem Informasi Bank Sampah

Sistem Informasi Manajemen Bank Sampah berbasis Web ini dibangun menggunakan PHP Native dan MySQL sebagai bagian dari proyek Tugas Akhir.

## Pengembang
- **Nama Pengembang**: Yoga Nugroho
- **Proyek**: Tugas Akhir - Sistem Informasi Bank Sampah (EcoPiksi)

## Fitur Utama
- **Manajemen Nasabah**: Pendaftaran nasabah, pendataan wilayah RT/RW, dan pengolahan saldo nasabah.
- **Setor Sampah**: Transaksi penimbangan sampah dan kalkulasi otomatis nilai saldo berdasarkan jenis sampah.
- **Pencairan Saldo**: Pengajuan penarikan dana oleh nasabah serta verifikasi oleh admin.
- **Penjualan Sampah**: Transaksi penjualan stok sampah dari TPS ke pihak pengepul.
- **Laporan dan Ekspor**: Rekapitulasi laporan transaksi dengan fitur ekspor ke format Excel dan PDF.

## Persyaratan Sistem
- Web Server (Apache / Nginx via Laragon / XAMPP)
- PHP versi 8.0 atau yang lebih baru
- Database Server MySQL / MariaDB

## Cara Instalasi dan Konfigurasi
1. Clone repositori ini ke folder root web server Anda (`htdocs` atau `www`):
   ```bash
   git clone <URL_REPOSITORY_ANDA>
   ```
2. Import file database yang berada pada folder `database/database_bank_sampah.sql` ke dalam MySQL melalui phpMyAdmin.
3. Sesuaikan konfigurasi koneksi database pada file `config/db.php`:
   ```php
   $servername = "localhost";
   $username   = "root";
   $password   = "";
   $dbname     = "tugasakhir";
   ```
4. Akses sistem melalui peramban web: `http://localhost/tugasakhir` (sesuaikan dengan nama folder project Anda).

## Akun Default Demo
- **Admin**: Username: `admin`
- **Nasabah**: Username: `demo`

