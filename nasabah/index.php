<?php
// nasabah/index.php
require_once '../includes/header.php'; // Termasuk session_start() dan cek autentikasi
require_once '../config/db.php'; // Koneksi database

// Pastikan hanya nasabah yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

// Set halaman aktif untuk sidebar nasabah
$current_page = 'dashboard_nasabah'; // Sesuaikan ini jika Anda punya sidebar untuk nasabah

$current_balance = 0;
$no_rekening = $_SESSION['no_rekening']; // Ambil nomor rekening nasabah yang login
$id_tps = $_SESSION['id_tps']; // Tambahkan: Ambil id_tps dari sesi nasabah

// Hitung saldo terkini nasabah
$sql_balance = "SELECT
    SUM(CASE WHEN tipe_mutasi = 'masuk' THEN jumlah_mutasi ELSE 0 END) -
    SUM(CASE WHEN tipe_mutasi = 'keluar' THEN jumlah_mutasi ELSE 0 END) AS current_balance
FROM tb_tabungan_nasabah
WHERE no_rekening = ? AND id_tps = ?";
$stmt_balance = $conn->prepare($sql_balance);
$stmt_balance->bind_param("si", $no_rekening, $id_tps);
$stmt_balance->execute();
$result_balance = $stmt_balance->get_result();

if ($result_balance && $result_balance->num_rows > 0) {
    $row = $result_balance->fetch_assoc();
    $current_balance = $row['current_balance'] ?? 0;
}
$stmt_balance->close();

// Ambil riwayat setoran terbaru (contoh: 5 setoran terakhir)
$recent_deposits = [];
$sql_recent_deposits = "SELECT tanggal_pengambilan, jenis_sampah, berat_kg, total AS amount_credited
FROM tb_setorsampah
WHERE no_rekening = ? AND id_tps = ?
ORDER BY tanggal_pengambilan DESC
LIMIT 5";
$stmt_recent_deposits = $conn->prepare($sql_recent_deposits);
$stmt_recent_deposits->bind_param("si", $no_rekening, $id_tps);
$stmt_recent_deposits->execute();
$result_recent_deposits = $stmt_recent_deposits->get_result();

if ($result_recent_deposits) {
    while ($row = $result_recent_deposits->fetch_assoc()) {
        $recent_deposits[] = $row;
    }
}
$stmt_recent_deposits->close();

// Ambil jadwal pengambilan terbaru
$jadwal_pengambilan = [];
$sql_jadwal = "SELECT tanggal_jadwal, rt, rw, keterangan
FROM tb_jadwal_pengambilan
WHERE id_tps = ?
ORDER BY tanggal_jadwal DESC
LIMIT 5";
$stmt_jadwal = $conn->prepare($sql_jadwal);
$stmt_jadwal->bind_param("i", $id_tps);
$stmt_jadwal->execute();
$result_jadwal = $stmt_jadwal->get_result();

if ($result_jadwal) {
    while ($row = $result_jadwal->fetch_assoc()) {
        $jadwal_pengambilan[] = $row;
    }
}
$stmt_jadwal->close();

// Ambil daftar harga sampah dengan paginasi dan pencarian
$search_query = $_GET['search_harga'] ?? '';
$limit = 10;
$current_page_harga = isset($_GET['page_harga']) ? (int)$_GET['page_harga'] : 1;
$offset = ($current_page_harga - 1) * $limit;

$total_harga_sampah = 0;
$harga_sampah = [];

// Query untuk menghitung total baris (untuk paginasi)
$count_sql = "SELECT COUNT(*) AS total FROM tb_harga_sampah WHERE id_tps = ? AND jenis_sampah LIKE ?";
$stmt_count = $conn->prepare($count_sql);
$search_param = '%' . $search_query . '%';
$stmt_count->bind_param("is", $id_tps, $search_param);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
if ($count_result) {
    $total_harga_sampah = $count_result->fetch_assoc()['total'];
}
$stmt_count->close();

// Query untuk mengambil data harga sampah
$sql_harga_sampah = "SELECT jenis_sampah, harga_per_kg FROM tb_harga_sampah
WHERE id_tps = ? AND jenis_sampah LIKE ?
ORDER BY jenis_sampah ASC
LIMIT ? OFFSET ?";
$stmt_harga_sampah = $conn->prepare($sql_harga_sampah);
$stmt_harga_sampah->bind_param("isii", $id_tps, $search_param, $limit, $offset);
$stmt_harga_sampah->execute();
$result_harga_sampah = $stmt_harga_sampah->get_result();

if ($result_harga_sampah) {
    while ($row = $result_harga_sampah->fetch_assoc()) {
        $harga_sampah[] = $row;
    }
}
$stmt_harga_sampah->close();

// Hitung total halaman
$total_pages = ceil($total_harga_sampah / $limit);

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nasabah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">

<?php include '../includes/sidebar_nasabah.php'; // Contoh: menyertakan sidebar ?>
<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama_nasabah']); ?>!</h1>

    <div class="bg-purple-500 text-white rounded-lg shadow-lg p-6 mb-8 text-center">
        <h5 class="text-lg font-semibold mb-2">Saldo Tabungan Anda Saat Ini</h5>
        <h2 class="text-5xl md:text-6xl font-extrabold mb-4">Rp <?php echo number_format($current_balance, 2, ',', '.'); ?></h2>
        <p class="text-base text-purple-100 mb-5">Setiap setoran sampah akan menambah saldo Anda.</p>
        <a href="request_pencairan.php" class="inline-flex items-center bg-yellow-400 text-gray-900 px-6 py-3 rounded-full shadow-md hover:bg-yellow-500 font-semibold transition-colors duration-200">
            <i class="fas fa-money-check-alt mr-2"></i> Ajukan Pencairan Dana
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Setoran Sampah Terbaru</h2>
            <?php if (!empty($recent_deposits)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Berat (Kg)</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Diterima (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_deposits as $deposit): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($deposit['tanggal_pengambilan']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($deposit['jenis_sampah']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars(number_format($deposit['berat_kg'] ?? 0, 2, ',', '.')); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo htmlspecialchars(number_format($deposit['amount_credited'] ?? 0, 2, ',', '.')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right mt-5">
                    <a href="histori_setor.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold text-sm">
                        Lihat Semua Riwayat Setoran <i class="fas fa-arrow-circle-right ml-1"></i>
                    </a>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-4">Belum ada riwayat setoran sampah. Mulai setor sampah Anda sekarang!</p>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Jadwal Pengambilan Sampah</h2>
            <?php if (!empty($jadwal_pengambilan)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RT</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RW</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($jadwal_pengambilan as $jadwal): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($jadwal['tanggal_jadwal']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['rt'] ?? '-'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['rw'] ?? '-'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['keterangan'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-right mt-5">
                    <a href="jadwal_lengkap.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold text-sm">
                        Lihat Semua Jadwal <i class="fas fa-arrow-circle-right ml-1"></i>
                    </a>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 py-4">Belum ada jadwal pengambilan sampah yang tersedia.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mt-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Daftar Harga Sampah</h2>
            <form action="" method="get" class="flex items-center">
                <input type="text" name="search_harga" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Cari jenis sampah..." class="px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="ml-2 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">Cari</button>
                <?php if ($search_query): ?>
                    <a href="index.php" class="ml-2 px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (!empty($harga_sampah)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga per Kg (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($harga_sampah as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo htmlspecialchars(number_format($item['harga_per_kg'] ?? 0, 2, ',', '.')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-center mt-4">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($current_page_harga > 1): ?>
                        <a href="?page_harga=<?php echo $current_page_harga - 1; ?><?php echo $search_query ? '&search_harga=' . urlencode($search_query) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page_harga=<?php echo $i; ?><?php echo $search_query ? '&search_harga=' . urlencode($search_query) : ''; ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $current_page_harga ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page_harga < $total_pages): ?>
                        <a href="?page_harga=<?php echo $current_page_harga + 1; ?><?php echo $search_query ? '&search_harga=' . urlencode($search_query) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="text-right mt-5">
                <a href="harga_lengkap.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold text-sm">
                    Lihat Semua Harga <i class="fas fa-arrow-circle-right ml-1"></i>
                </a>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-4">Belum ada daftar harga sampah yang tersedia.</p>
        <?php endif; ?>
    </div>
</div>
<!-- 
╔══════════════════════════════════════════════════════════╗
║  Copyright © 2025                                        ║
║  Website ini dibuat oleh: Yoga Nugroho                   ║
║  Instagram: @yogaaszs                                    ║
║  Semua hak dilindungi undang-undang.                     ║
╚══════════════════════════════════════════════════════════╝
-->

<?php require_once '../includes/footer.php'; ?>