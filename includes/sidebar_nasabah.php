<?php
// includes/sidebar_nasabah.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}
if (!isset($current_page)) {
    $current_page = '';
}
?>

<div id="sidebar-wrapper" class="fixed top-0 left-0 h-screen w-64 bg-gray-200 shadow-xl flex flex-col z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="flex flex-col items-center justify-center p-4 h-32 border-b border-gray-200">
        <img src="logo.jpg" alt="Logo" class="h-16 mb-2 rounded-full ">
        <h1 class="text-lg font-extrabold text-purple-800 tracking-wide">ECO PIKSI</h1>
    </div>
    <nav class="flex-grow p-4 space-y-4 overflow-y-auto text-sm" >
        <a href="../nasabah/index.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
                        <?= ($current_page == 'dashboard_nasabah') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-fw fa-tachometer-alt text-lg mr-4 text-purple-700"></i>
            Dashboard
        </a>
        <a href="../nasabah/histori_setor.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
                        <?= ($current_page == 'histori_setor') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-history text-lg mr-4 text-purple-700"></i>
            Riwayat Setoran
        </a>
        <a href="../nasabah/histori_tabungan.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
                        <?= ($current_page == 'histori_tabungan') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-wallet text-lg mr-4 text-purple-700"></i>
            Mutasi Tabungan
        </a>
        <a href="../nasabah/request_pencairan.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
                        <?= ($current_page == 'request_pencairan') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-money-check-alt text-lg mr-4 text-purple-700"></i>
            Permintaan Pencairan
        </a>
        <a href="../nasabah/edit_profil.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
                        <?= ($current_page == 'edit_profil') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-user-edit text-lg mr-4 text-purple-700"></i>
            Edit Profil
        </a>
        <a href="../nasabah/ubah_password.php" class="flex items-center p-3 rounded-lg text-gray-700 hover:bg-purple-100 hover:text-purple-800 transition-colors duration-200
                        <?= ($current_page == 'ubah_password') ? 'bg-purple-100 text-purple-800 font-semibold shadow-sm' : ''; ?>">
            <i class="fas fa-key text-lg mr-4 text-purple-700"></i>
            Ubah Password
        </a>
    </nav>
    <div class="p-4 border-t border-gray-100">
        <a href="../auth/logout.php" class="flex items-center justify-center p-3 rounded-lg text-red-700 hover:bg-red-50 hover:text-red-800 transition-colors duration-200">
            <i class="fas fa-sign-out-alt text-lg mr-4 text-red-700"></i>
            <span>Log Out</span>
        </a>
    </div>
</div>