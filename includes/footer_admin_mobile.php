<?php
// includes/footer_admin_mobile.php
// File ini akan di-include di includes/footer.php jika role adalah 'admin'
?>
<nav class="navbar fixed-bottom navbar-dark bg-dark d-lg-none d-flex justify-content-around py-2" id="mobile-footer-nav-admin">
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../admin/index.php">
        <small class="fw-bold">Dashboard</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../admin/jadwal.php">
        <small class="fw-bold">Jadwal</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../admin/nasabah.php">
        <small class="fw-bold">Nasabah</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../admin/pencairan.php">
        <small class="fw-bold">Pencairan</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../admin/setor_sampah.php">
        <small class="fw-bold">Setoran</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../admin/penjualan.php">
        <small class="fw-bold">Penjualan</small>
    </a>
    <a class="text-white text-decoration-none d-flex flex-column align-items-center flex-grow-1" href="../admin/laporan.php">
        <small class="fw-bold">Laporan</small>
    </a>
</nav>

<style>
    /* CSS tambahan untuk memastikan konten tidak tertutup footer */
    /* Anda bisa menempatkan ini di style.css atau di sini jika hanya berlaku untuk admin footer */
    #mobile-footer-nav-admin {
        box-shadow: 0 -2px 5px rgba(0,0,0,0.2);
    }
    #mobile-footer-nav-admin a {
        padding: 0.5rem 0.2rem; /* Sesuaikan padding */
        text-align: center;
        border-radius: 0.25rem; /* Border radius seperti tombol Bootstrap */
        margin: 0 0.1rem; /* Margin antar tautan yang lebih kecil karena banyak item */
        background-color: #0d6efd; /* Warna primary Bootstrap untuk background */
        color: white !important; /* Pastikan teks putih */
    }
    #mobile-footer-nav-admin a:hover {
        background-color: #0b5ed7; /* Warna hover yang sedikit lebih gelap dari primary */
    }
    #mobile-footer-nav-admin small {
        font-size: 0.65rem; /* Ukuran font lebih kecil karena banyak item */
    }
</style>