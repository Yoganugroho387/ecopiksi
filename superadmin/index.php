<?php
// superadmin/index.php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php'; // Asumsikan file ini sudah memuat koneksi DB dan HTML header

// Periksa apakah pengguna adalah Superadmin
if ($_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

// Hitung total nasabah di semua TPS
$sql_total_nasabah = "SELECT COUNT(no_rekening) AS total FROM tb_nasabah WHERE role = 'nasabah'";
$result_nasabah = $conn->query($sql_total_nasabah);
$totalNasabah = $result_nasabah ? $result_nasabah->fetch_assoc()['total'] : 0;

// Hitung total TPS
$sql_total_tps = "SELECT COUNT(id_tps) AS total FROM tb_tps";
$result_tps = $conn->query($sql_total_tps);
$totalTps = $result_tps ? $result_tps->fetch_assoc()['total'] : 0;

// Contoh data ringkasan lain yang bisa ditampilkan
$sql_total_setoran = "SELECT COALESCE(SUM(berat_kg), 0) AS total_berat FROM tb_setorsampah";
$result_setoran = $conn->query($sql_total_setoran);
$totalSetoran = $result_setoran ? $result_setoran->fetch_assoc()['total_berat'] : 0;
$totalSetoranKg = number_format($totalSetoran, 2, ',', '.');


$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Dashboard Superadmin</h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-purple-700 text-white rounded-lg shadow-lg overflow-hidden transform transition duration-300 hover:scale-105">
            <div class="p-5">
                <div class="text-xs uppercase font-bold mb-1 opacity-80">Total Bank Sampah Terdaftar</div>
                <div class="text-4xl font-extrabold"><?php echo $totalTps; ?></div>
            </div>
            <div class="bg-purple-800 px-5 py-3">
                <a href="manage_tps.php" class="text-white text-sm hover:underline flex items-center justify-between">
                    Kelola Bank sampah <i class="fas fa-arrow-circle-right ml-2"></i>
                </a>
            </div>
        </div>

        <div class="bg-green-700 text-white rounded-lg shadow-lg overflow-hidden transform transition duration-300 hover:scale-105">
            <div class="p-5">
                <div class="text-xs uppercase font-bold mb-1 opacity-80">Total Nasabah</div>
                <div class="text-4xl font-extrabold"><?php echo $totalNasabah; ?></div>
            </div>
            <div class="bg-green-800 px-5 py-3">
                <a href="manage_nasabah.php" class="text-white text-sm hover:underline flex items-center justify-between">
                    Kelola Nasabah <i class="fas fa-arrow-circle-right ml-2"></i>
                </a>
            </div>
        </div>
        
        <div class="bg-blue-700 text-white rounded-lg shadow-lg overflow-hidden transform transition duration-300 hover:scale-105">
            <div class="p-5">
                <div class="text-xs uppercase font-bold mb-1 opacity-80">Total Sampah Terkumpul (KG)</div>
                <div class="text-4xl font-extrabold"><?php echo $totalSetoranKg; ?></div>
            </div>
            <div class="bg-blue-800 px-5 py-3">
                <a href="laporan.php" class="text-white text-sm hover:underline flex items-center justify-between">
                    Lihat Laporan <i class="fas fa-arrow-circle-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>

    </div>
           </main>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
<script>
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOpen = document.getElementById('sidebarOpen'); // Asumsi ada tombol pembuka dengan ID ini
    const sidebar = document.getElementById('sidebar-wrapper');
    const body = document.body;
    const minDesktopWidth = 768;

    // Fungsionalitas tombol "x" untuk menutup sidebar di mobile
    if (sidebarClose) {
        sidebarClose.addEventListener('click', () => {
            body.classList.add('sidebar-collapsed');
        });
    }

    // Fungsionalitas tombol pembuka sidebar (misalnya, hamburger icon)
    if (sidebarOpen) {
        sidebarOpen.addEventListener('click', () => {
            body.classList.remove('sidebar-collapsed');
        });
    }

    // Tutup sidebar saat klik di luar area pada tampilan mobile
    document.addEventListener('click', (event) => {
        if (window.innerWidth < minDesktopWidth && !body.classList.contains('sidebar-collapsed')) {
            if (!sidebar.contains(event.target) && event.target !== sidebarOpen && !sidebarOpen.contains(event.target)) {
                body.classList.add('sidebar-collapsed');
            }
        }
    });

</script>
</body>
</html>
<!-- 
╔══════════════════════════════════════════════════════════╗
║  Copyright © 2025                                        ║
║  Website ini dibuat oleh: Yoga Nugroho                   ║
║  Instagram: @yogaaszs                                    ║
║  Semua hak dilindungi undang-undang.                     ║
╚══════════════════════════════════════════════════════════╝
-->
