<?php
// admin/stok_sampah.php
$current_page = 'stok_sampah';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/header.php';
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id_tps_admin = $_SESSION['id_tps'];

// --- QUERY PERBAIKAN ---
// Kita mengambil data langsung dari tb_setorsampah yang sudah pasti memiliki id_tps
// Ini lebih akurat daripada join dari tb_sampah yang berpotensi duplikat kode antar TPS
$stok_sampah_data = [];
$sql_stok = "SELECT 
                kode_sampah, 
                jenis_sampah, 
                kategori, 
                SUM(berat_kg) as total_berat_stok 
             FROM tb_setorsampah 
             WHERE id_tps = ? AND status_setoran = 'pending_harga'
             GROUP BY kode_sampah, jenis_sampah, kategori
             HAVING total_berat_stok > 0
             ORDER BY jenis_sampah ASC";

$stmt_stok = $conn->prepare($sql_stok);
$stmt_stok->bind_param("i", $id_tps_admin);
$stmt_stok->execute();
$result_stok = $stmt_stok->get_result();

if ($result_stok) {
    while ($row = $result_stok->fetch_assoc()) {
        $stok_sampah_data[] = $row;
    }
}
$stmt_stok->close();
$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Stok Sampah Tersedia (TPS Anda)</h1>

    <?php if (empty($stok_sampah_data)): ?>
        <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50" role="alert">
            <span class="font-medium">Info:</span> Tidak ada stok sampah yang menunggu penjualan di TPS Anda saat ini.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Total Berat Sampah 'Pending Harga' per Jenis</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Berat (KG)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($stok_sampah_data)): ?>
                        <?php foreach ($stok_sampah_data as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['kode_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($item['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($item['kategori']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-bold"><?php echo number_format($item['total_berat_stok'] ?? 0, 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Data kosong.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>