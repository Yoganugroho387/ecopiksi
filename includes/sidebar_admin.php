<?php
// includes/sidebar_admin.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // header("Location: ../auth/login.php");
}

$current_page = isset($current_page) ? $current_page : '';
$nama_admin = isset($_SESSION['nama_nasabah']) ? $_SESSION['nama_nasabah'] : 'Admin';
?>

<div id="sidebar-wrapper" class="fixed top-0 left-0 h-screen w-64 bg-gray-200 shadow-xl flex flex-col z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="flex flex-col items-center justify-center p-4 h-32 border-b border-gray-200">
        <img src="logo.jpg" alt="Logo" class="h-16 mb-2 rounded-full ">
        <h1 class="text-lg font-extrabold text-purple-800 tracking-wide">ECO PIKSI</h1>
    </div>
    <nav class="flex-grow p-4 space-y-4 overflow-y-auto text-sm">
        <a href="../admin/index.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'dashboard') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-tachometer-alt text-lg mr-4 text-purple-700"></i>
            Dashboard
        </a>
        <a href="../admin/nasabah.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'nasabah') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-users text-lg mr-4 text-purple-700"></i>
            Manajemen Nasabah
        </a>
         <a href="../admin/set_saldo.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'set_saldo') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-wallet text-lg mr-4 text-purple-700"></i>
            Set Saldo
        </a>
        <a href="../admin/jadwal.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'jadwal') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-calendar-alt text-lg mr-4 text-purple-700"></i>
            Penjadwalan Pengambilan
        </a>
        <a href="../admin/setor_sampah.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'setor_sampah') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-inbox text-lg mr-4 text-purple-700"></i>
            Setor Sampah
        </a>
        <a href="../admin/stok_sampah.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
             <?= ($current_page == 'stok_sampah') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-box-open text-lg mr-4 text-purple-700"></i>
            Stok Sampah
        </a>
        <a href="../admin/sampah.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'sampah') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-box text-lg mr-4 text-purple-700"></i>
            <span>Manajemen Jenis Sampah</span>
        </a>
        <a href="../admin/pembagian.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'pembagian') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-percent text-lg mr-4 text-purple-700"></i>
            <span>Pengaturan Pembagian</span>
        </a>
        <a href="../admin/pencairan.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'pencairan') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-money-bill-wave text-lg mr-4 text-purple-700"></i>
            Pencairan Dana
        </a>
        <a href="../admin/penjualan_sampah.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'penjualan') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-handshake text-lg mr-4 text-purple-700"></i>
            Penjualan Sampah
        </a>
        <a href="../admin/laporan.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
            <?= ($current_page == 'laporan') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-chart-line text-lg mr-4 text-purple-700"></i>
            Laporan
        </a>
         <a href="../admin/ubah_password.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
                    <?= ($current_page == 'ubah_password') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-key text-lg mr-4 text-purple-700"></i>
            Ubah Password
        </a>
    </nav>
    <div class="p-4 border-t border-gray-100">
        <a href="../auth/logout.php" class="flex items-center justify-center p-3 rounded-lg text-red-700 hover:bg-red-50 hover:text-red-800 transition-colors duration-200">
            <i class="fas fa-fw fa-sign-out-alt text-lg mr-4 text-red-700"></i>
            <span>Log Out</span>
        </a>
    </div>
</div>