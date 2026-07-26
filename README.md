# Sistem Informasi Manajemen Bank Sampah (EcoPiksi)

Sistem Informasi Manajemen Bank Sampah berbasis Web ini dikembangkan sebagai solusi digitalisasi tata kelola pengumpulan, penimbangan, pencatatan transaksi tabungan, dan penjualan sampah pada Bank Sampah / Tempat Pengolahan Sampah (TPS). 

Aplikasi ini dirancang untuk mempermudah pengelola dalam mengintegrasikan transaksi setor sampah dari nasabah di tingkat RT/RW hingga proses penjualan hasil pilahan sampah ke pihak pengepul secara teratur, transparan, dan akurat.

---

## Identitas Pengembang

- **Nama Pengembang**: Yoga Nugroho
- **Jenis Proyek**: Laporan & Aplikasi Tugas Akhir
- **Nama Sistem**: Sistem Informasi Bank Sampah (EcoPiksi)

---

## Arsitektur & Teknologi

Sistem dibangun menggunakan kombinasi teknologi web modern yang stabil, hemat sumber daya, dan mudah disesuaikan:

1. **Bahasa Pemrograman Utama**: PHP (PHP Native 8.0+)
2. **Sistem Manajemen Basis Data**: MySQL / MariaDB
3. **Antarmuka Pengguna (Frontend)**: HTML5, CSS3, JavaScript, Vanilla CSS, Responsive Layout
4. **Web Server**: Apache / Nginx (Laragon, XAMPP, atau Server Linux)
5. **Modul Laporan**: PHPSpreadsheet & FPDF untuk otomatisasi cetak laporan transaksi ke format Microsoft Excel (.xlsx) dan PDF.

---

## Pembagian Hak Akses Pengguna (Multi-Role System)

Sistem ini mendukung pengelolaan berbasis peran (*Role-Based Access Control*) dengan pembagian tingkat pengguna sebagai berikut:

1. **Role Admin / Pengelola Bank Sampah**:
   - Akses penuh terhadap seluruh modul operasional.
   - Pengelolaan master data sampah, penetapan harga beli/jual, dan pengelolaan nasabah per RT/RW.
   - Eksekusi transaksi setor sampah, verifikasi pencairan dana nasabah, dan pencatatan penjualan ke pengepul.
   - Akses penuh terhadap laporan transaksi global, rekapitulasi wilayah, dan ekspor data (Excel/PDF).

2. **Role Nasabah (Masyarakat)**:
   - Akses mandiri terhadap dashboard ringkasan saldo tabungan dan total akumulasi bobot sampah.
   - Pemantauan riwayat transaksi setor sampah dan buku mutasi tabungan (kredit/debit).
   - Pengajuan permohonan pencairan saldo tabungan ke rekening bank atau tunai.
   - Akses informasi katalog harga sampah per kilogram dan jadwal pelayanan penimbangan.

---

## Penjelasan Lengkap Fitur Sistem

### 1. Modul Admin / Pengelola TPS

#### A. Transaksi & Operasional Inti
- **Transaksi Setor Sampah Real-Time (`setor_sampah.php`)**:
  Petugas memilih nomor rekening/nama nasabah, memilih jenis sampah, dan menginput berat (kg). Sistem secara otomatis menghitung nilai rupiah (`Berat x Harga per kg`) serta mengupdate saldo tabungan nasabah secara instan.
- **Pencairan Saldo Nasabah (`pencairan.php`)**:
  Modul verifikasi dan persetujuan pengajuan penarikan dana nasabah. Admin dapat menyetujui (*Approve*) atau menolak permohonan serta mencatat metode pembayaran (Tunai atau Transfer Bank).
- **Penjualan Sampah ke Pengepul (`penjualan_sampah.php`)**:
  Pencatatan transaksi saat Bank Sampah menjual stok sampah yang terkumpul di gudang ke pihak ketiga/pengepul untuk menambah saldo kas operasional TPS.

#### B. Manajemen Master Data
- **Pengaturan Jenis & Harga Sampah (`sampah.php`)**:
  Penambahan, pengubahan, dan penghapusan kategori sampah (Kertas HVS, Kardus, Botol Plastik, Besi, Kaleng, Kaca, dll) beserta penyesuaian tarif harga beli dan harga jual secara dinamis.
- **Manajemen Data Nasabah (`nasabah.php`)**:
  Pengelolaan data profil nasabah berbasis wilayah RT/RW, perubahan status akun (Aktif / Tidak Aktif), dan pengaturan kata sandi.
- **Impor Data Nasabah Massal (`import_nasabah.php`)**:
  Fitur impor data banyak nasabah sekaligus dari file format Microsoft Excel (`.xlsx`).
- **Pengaturan Konfigurasi Bagi Hasil (`pembagian.php`)**:
  Pengaturan parameter persentase pembagian hasil penjualan sampah (misalnya: 80% untuk Nasabah, 15% untuk TPS, dan 5% untuk Pengepul/Pengelola).
- **Manajemen Jadwal Pelayanan (`jadwal.php`)**:
  Pengaturan kalender jadwal rutin penimbangan atau penjemputan sampah per wilayah RT/RW.
- **Monitoring Stok Gudang (`stok_sampah.php`)**:
  Pemantauan akumulasi berat fisik sampah yang tersimpan di gudang TPS sebelum siap dijual.

#### C. Modul Laporan & Rekapitulasi Data
- **Laporan Transaksi Global (`laporan_global.php`)**: Rekapitulasi total omset transaksi setor sampah dan penjualan berdasarkan rentang tanggal tertentu.
- **Laporan Per Wilayah RT (`laporan_per_rt.php`)**: Statistik keaktifan dan jumlah akumulasi tabungan nasabah per wilayah RT/RW.
- **Laporan Detail Nasabah (`laporan_detail_nasabah.php`)**: Cetak lembar mutasi buku tabungan lengkap untuk satu nasabah tertentu.
- **Modul Ekspor Data (`export_laporan.php`)**: Generasi dan pengunduhan laporan ke dalam format Microsoft Excel (`.xlsx`) dan PDF.

---

### 2. Modul Nasabah

- **Dashboard Tabungan (`index.php`)**:
  Halaman utama nasabah yang menampilkan saldo tabungan terkini (Rp), total akumulasi berat sampah yang pernah disetorkan (kg), serta statistik transaksi.
- **Riwayat Setor Sampah (`histori_setor.php`)**:
  Catatan detail histori penimbangan sampah mencakup tanggal transaksi, jenis sampah, bobot penimbangan (kg), dan jumlah rupiah yang didapatkan.
- **Buku Mutasi Tabungan (`histori_tabungan.php`)**:
  Catatan rinci arus keluar-masuk uang tabungan (Kredit saat setor sampah, Debit saat melakukan pencairan saldo).
- **Pengajuan Pencairan Saldo (`request_pencairan.php`)**:
  Formulir online bagi nasabah untuk mengajukan penarikan dana tabungan baik via transfer rekening bank maupun penarikan tunai di kantor TPS.
- **Katalog Harga Sampah (`harga_lengkap.php`)**:
  Informasi acuan daftar harga sampah terbaru per kilogram sebagai bentuk transparansi informasi harga kepada masyarakat.
- **Jadwal Pelayanan (`jadwal_lengkap.php`)**:
  Informasi jadwal hari dan jam pelayanan penimbangan Bank Sampah untuk masing-masing wilayah RT.

---

## Alur Kerja Transaksi Sistem (Workflow)

```text
[NASABAH] ──> Membawa Sampah ──> [PETUGAS / ADMIN]
                                       │
                                       ▼
                       [Input Penimbangan Berat Sampah]
                                       │
                                       ▼
                   [Kalkulasi Otomatis: Berat x Harga per kg]
                                       │
                                       ▼
                  [Saldo Otomatis Bertambah di Akun Nasabah]
                                       │
            ┌──────────────────────────┴──────────────────────────┐
            ▼                                                     ▼
 [Nasabah Pengajuan Pencairan]                          [Stok Sampah Gudang Terkumpul]
            │                                                     │
            ▼                                                     ▼
 [Admin Verifikasi & Pembayaran]                   [Admin Penjualan Sampah ke Pengepul]
```

---

## Logika Bisnis & Rumus Perhitungan

1. **Perhitungan Nilai Setor Sampah**:
   `Total Uang Setor (Rp) = Berat Sampah (kg) x Harga Sampah per kg (Rp)`
2. **Mutasi Tabungan Nasabah**:
   - *Setor Sampah*: `Saldo Baru = Saldo Lama + Total Uang Setor`
   - *Pencairan Saldo Approved*: `Saldo Baru = Saldo Lama - Nominal Penarikan`
3. **Pembagian Hasil Sistem**:
   Setiap transaksi diproses berdasarkan variabel persentase yang dikonfigurasi pada tabel `tb_konfigurasi` (Nasabah %, TPS %, Pengepul %).

---

## Skema & Struktur Basis Data

File basis data bersih telah disediakan pada direktori `database/database_bank_sampah.sql`. Berikut adalah rincian tabel utama:

1. `tb_nasabah`: Menyimpan data profil nasabah, nomor rekening internal, alamat RT/RW, username, password terenkripsi, status akun, dan role akses.
2. `tb_harga_sampah`: Master data jenis-jenis sampah beserta tarif harga per kilogram.
3. `tb_setorsampah`: Catatan riwayat transaksi setor sampah nasabah.
4. `tb_tabungan_nasabah`: Log rincian perubahan saldo dan mutasi tabungan nasabah.
5. `tb_pencairan_dana`: Catatan pengajuan penarikan dana nasabah dan status verifikasinya.
6. `tb_transaksi_penjualan`: Pencatatan pengeluaran stok sampah gudang dan pendapatan hasil penjualan ke pengepul.
7. `tb_konfigurasi`: Parameter persentase pembagian bagi hasil sistem.

---

## Keamanan & Ketahanan Sistem

1. **Enkripsi Kata Sandi**: Kata sandi seluruh akun pengguna disimpan menggunakan algoritma hashing standar industri `password_hash()` (Bcrypt).
2. **Validasi Transaksi**: Sistem melakukan pengecekan ketersediaan saldo sebelum transaksi pencairan disetujui untuk mencegah saldo minus.
3. **Pemisahan Hak Akses**: Setiap file halaman memiliki pengecekan verifikasi sesi (`session check`) untuk memastikan nasabah tidak dapat mengakses halaman administratif admin.

---

## Panduan Instalasi & Konfigurasi

### 1. Persyaratan Lingkungan Pengembangan
- Web Server (Apache 2.4+ atau Nginx)
- PHP versi 8.0 atau versi di atasnya (dengan ekstensi `mysqli`, `pdo`, `gd`, `zip` aktif)
- Database MySQL versi 5.7+ / MariaDB 10.4+

### 2. Langkah-Langkah Instalasi
1. **Clone Repositori**:
   Unduh atau clone repositori ini ke folder root web server Anda (misalnya `C:\laragon\www\` atau `/var/www/html/`):
   ```bash
   git clone <URL_REPOSITORY_ANDA>
   ```
2. **Impor Database**:
   - Buka aplikasi pengelola database (phpMyAdmin / HeidiSQL / DBeaver).
   - Buat basis data baru bernama `tugasakhir`.
   - Impor file SQL yang berada pada direktori: `database/database_bank_sampah.sql`.
3. **Konfigurasi Koneksi Database**:
   Buka file `config/db.php` dan sesuaikan parameter kredensial database Anda:
   ```php
   $servername = "localhost";
   $username   = "root";
   $password   = "";
   $dbname     = "tugasakhir";
   ```
4. **Jalankan Aplikasi**:
   Buka peramban web dan akses URL lokal Anda:
   ```text
   http://localhost/tugasakhir
   ```

---

## Hak Akses Default (Pengujian Demo)

Untuk keperluan pengujian awal sistem, Anda dapat menggunakan akun bawaan berikut:

1. **Hak Akses Admin Utama**:
   - Username: `admin`
   - Password: `admin` (atau sesuaikan dengan password awal)

2. **Hak Akses Nasabah Demo**:
   - Username: `demo`
   - Password: `demo`

---

## Lisensi & Catatan

Proyek ini dikembangkan khusus untuk kepentingan Laporan Tugas Akhir dan dapat digunakan sebagai referensi pengembangan Sistem Informasi Bank Sampah berbasis Web.


