<?php
// admin/laporan_detail_nasabah.php
ob_start();

session_start();
$current_page = 'laporan';

require_once '../includes/header.php';
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id_tps_admin = $_SESSION['id_tps'];
$no_rekening_nasabah = isset($_GET['no_rekening']) ? $_GET['no_rekening'] : '';

// Jika tidak ada no_rekening, redirect kembali ke laporan utama
if (empty($no_rekening_nasabah)) {
    header("Location: laporan.php");
    exit();
}

// Ambil data nasabah
$nasabah_data = null;
$sql_nasabah = "SELECT nama_nasabah, rt, rw, alamat, no_hp FROM tb_nasabah WHERE no_rekening = ? AND id_tps = ?";
$stmt_nasabah = $conn->prepare($sql_nasabah);
$stmt_nasabah->bind_param("si", $no_rekening_nasabah, $id_tps_admin);
$stmt_nasabah->execute();
$result_nasabah = $stmt_nasabah->get_result();
if ($result_nasabah && $result_nasabah->num_rows > 0) {
    $nasabah_data = $result_nasabah->fetch_assoc();
} else {
    // Nasabah tidak ditemukan atau bukan dari TPS ini
    echo "<div class='p-6'><div class='bg-red-100 text-red-700 p-4 rounded'>Data nasabah tidak ditemukan.</div></div>";
    exit();
}
$stmt_nasabah->close();

// Inisialisasi filter tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fungsi Config
function get_config_value($conn_obj, $setting_name, $id_tps, $default_value = null) {
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
    $stmt = $conn_obj->prepare($sql);
    $stmt->bind_param("si", $setting_name, $id_tps);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ? floatval($res['nilai']) : $default_value;
}

// 1. Get Detail Setoran (Pemasukan)
function getDetailSetoran($conn, $no_rekening, $id_tps, $start, $end) {
    $sql = "SELECT tanggal_pengambilan, jenis_sampah, berat_kg, harga_per_kg, total, tabungan_nasabah, pos_penimbangan, tps3r
            FROM tb_setorsampah
            WHERE no_rekening = ? AND id_tps = ? AND tanggal_pengambilan BETWEEN ? AND ? AND status_setoran = 'final'
            ORDER BY tanggal_pengambilan ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siss", $no_rekening, $id_tps, $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 2. Get Detail Pencairan (Pengeluaran)
function getDetailPencairan($conn, $no_rekening, $id_tps, $start, $end) {
    $sql = "SELECT tanggal_transfer, jumlah_cair, keterangan 
            FROM tb_pencairan_dana
            WHERE no_rekening = ? AND id_tps = ? AND status = 'diterima' AND tanggal_transfer BETWEEN ? AND ?
            ORDER BY tanggal_transfer ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siss", $no_rekening, $id_tps, $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 3. Hitung Saldo Total Saat Ini
function getSaldoTotal($conn, $no_rekening, $id_tps) {
    $sql = "SELECT SUM(CASE WHEN tipe_mutasi='masuk' THEN jumlah_mutasi ELSE -jumlah_mutasi END) as saldo
            FROM tb_tabungan_nasabah WHERE no_rekening = ? AND id_tps = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $no_rekening, $id_tps);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['saldo'] ?? 0;
}

// --- EKSEKUSI ---
$persen_nasabah = get_config_value($conn, 'persen_nasabah', $id_tps_admin, 70);
$persen_tps = get_config_value($conn, 'persen_tps', $id_tps_admin, 20);
$persen_pos = get_config_value($conn, 'persen_pengepul', $id_tps_admin, 5); // Asumsi pos penimbangan

$data_setoran = getDetailSetoran($conn, $no_rekening_nasabah, $id_tps_admin, $start_date, $end_date);
$data_pencairan = getDetailPencairan($conn, $no_rekening_nasabah, $id_tps_admin, $start_date, $end_date);
$saldo_saat_ini = getSaldoTotal($conn, $no_rekening_nasabah, $id_tps_admin);

$conn->close();
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Detail Transaksi Nasabah</h1>
        <a href="laporan.php?tab=nasabah" class="text-gray-600 hover:text-gray-900 flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6 border-l-4 border-purple-600">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <table class="text-sm text-gray-700">
                    <tr><td class="py-1 w-32 font-semibold">Nama Nasabah</td><td>: <?php echo htmlspecialchars($nasabah_data['nama_nasabah']); ?></td></tr>
                    <tr><td class="py-1 font-semibold">No. Rekening</td><td>: <?php echo htmlspecialchars($no_rekening_nasabah); ?></td></tr>
                    <tr><td class="py-1 font-semibold">Alamat</td><td>: <?php echo htmlspecialchars($nasabah_data['alamat']); ?></td></tr>
                    <tr><td class="py-1 font-semibold">RT / RW</td><td>: <?php echo htmlspecialchars($nasabah_data['rt'] . ' / ' . $nasabah_data['rw']); ?></td></tr>
                    <tr><td class="py-1 font-semibold">No. HP</td><td>: <?php echo htmlspecialchars($nasabah_data['no_hp']); ?></td></tr>
                </table>
            </div>
            <div class="flex flex-col justify-center items-end">
                <div class="text-sm text-gray-500 mb-1">Total Saldo Saat Ini</div>
                <div class="text-4xl font-bold text-green-600">Rp <?php echo number_format($saldo_saat_ini, 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <form action="" method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="no_rekening" value="<?php echo htmlspecialchars($no_rekening_nasabah); ?>">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="border rounded p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="border rounded p-2 text-sm">
            </div>
            <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded text-sm hover:bg-purple-700">Filter Periode</button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800 border-b-2 border-green-500 pb-1">Riwayat Setoran Sampah (Masuk)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="bg-green-50 text-gray-700 uppercase text-xs">
                        <tr>
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Sampah</th>
                            <th class="px-3 py-2 text-right">Berat</th>
                            <th class="px-3 py-2 text-right">Masuk Tabungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_masuk = 0;
                        if (empty($data_setoran)): ?>
                            <tr><td colspan="4" class="px-3 py-4 text-center">Tidak ada data setoran di periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_setoran as $row): 
                                $total_masuk += $row['tabungan_nasabah'];
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap"><?php echo date('d/m/y', strtotime($row['tanggal_pengambilan'])); ?></td>
                                <td class="px-3 py-2"><?php echo htmlspecialchars($row['jenis_sampah']); ?></td>
                                <td class="px-3 py-2 text-right"><?php echo number_format($row['berat_kg'], 2, ',', '.'); ?> kg</td>
                                <td class="px-3 py-2 text-right font-bold text-green-600">Rp <?php echo number_format($row['tabungan_nasabah'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-green-100 font-bold">
                                <td colspan="3" class="px-3 py-2 text-right">Total Masuk</td>
                                <td class="px-3 py-2 text-right">Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800 border-b-2 border-red-500 pb-1">Riwayat Pencairan Dana (Keluar)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="bg-red-50 text-gray-700 uppercase text-xs">
                        <tr>
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Keterangan</th>
                            <th class="px-3 py-2 text-right">Jumlah Cair</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_keluar = 0;
                        if (empty($data_pencairan)): ?>
                            <tr><td colspan="3" class="px-3 py-4 text-center">Tidak ada data pencairan di periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($data_pencairan as $row): 
                                $total_keluar += $row['jumlah_cair'];
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap"><?php echo date('d/m/y', strtotime($row['tanggal_transfer'])); ?></td>
                                <td class="px-3 py-2"><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                <td class="px-3 py-2 text-right font-bold text-red-600">Rp <?php echo number_format($row['jumlah_cair'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-red-100 font-bold">
                                <td colspan="2" class="px-3 py-2 text-right">Total Keluar</td>
                                <td class="px-3 py-2 text-right">Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="mt-6 flex justify-end gap-2">
        <a href="export_laporan.php?type=detail_nasabah&no_rekening=<?php echo urlencode($no_rekening_nasabah); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=excel" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700 flex items-center">
            <i class="fas fa-file-excel mr-2"></i> Download Excel
        </a>
        <a href="export_laporan.php?type=detail_nasabah&no_rekening=<?php echo urlencode($no_rekening_nasabah); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=pdf" target="_blank" class="bg-red-600 text-white px-4 py-2 rounded shadow hover:bg-red-700 flex items-center">
            <i class="fas fa-file-pdf mr-2"></i> Cetak Buku Tabungan (PDF)
        </a>
    </div>

</div>

</body>
</html>
<?php ob_end_flush(); ?>