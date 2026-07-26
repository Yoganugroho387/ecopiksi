<?php
// includes/footer_nasabah_mobile.php
// Pastikan file ini hanya di-include di halaman nasabah
// File ini tidak memerlukan session_start() karena akan di-include oleh footer.php yang sudah memulainya.
?>
<nav class="navbar fixed-bottom navbar-dark bg-dark d-lg-none d-flex justify-content-around py-2" id="mobile-footer-nav">
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../nasabah/index.php">
        <small class="fw-bold">Dashboard</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../nasabah/histori_setor.php">
        <small class="fw-bold">Setoran</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../nasabah/histori_tabungan.php">
        <small class="fw-bold">Tabungan</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../nasabah/request_pencairan.php">
        <small class="fw-bold">Pencairan</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../nasabah/ubah_password.php">
        <small class="fw-bold">Password</small>
    </a>
</nav>

<style>
    /* CSS tambahan untuk memastikan konten tidak tertutup footer */
    /* Ini akan diterapkan secara global via style.css atau di-override di sini jika diperlukan */
    body {
        padding-bottom: 70px; /* Sesuaikan tinggi footer */
    }
    #mobile-footer-nav {
        box-shadow: 0 -2px 5px rgba(0,0,0,0.2);
    }
    /* Menyesuaikan tampilan tautan agar mirip tombol biasa */
    #mobile-footer-nav a {
        padding: 0.5rem 0.2rem; /* Sesuaikan padding */
        text-align: center;
        border-radius: 0.25rem; /* Border radius seperti tombol Bootstrap */
        margin: 0 0.1rem; /* Margin antar tautan (dibuat lebih kecil karena ada 5 item) */
        background-color: #0d6efd; /* Warna primary Bootstrap untuk background */
        color: white !important; /* Pastikan teks putih */
        /* Flexbox stuff is already on the parent nav and each item */
    }
    #mobile-footer-nav a:hover {
        background-color: #0b5ed7; /* Warna hover yang sedikit lebih gelap dari primary */
    }
    #mobile-footer-nav small {
        font-size: 0.65rem; /* Ukuran font lebih kecil karena jumlah item yang banyak di mobile */
    }
</style>