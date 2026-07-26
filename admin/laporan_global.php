<?php
ob_start();

session_start();
$current_page = 'laporan';

require_once '../includes/header.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id_tps_admin = $_SESSION['id_tps'];

// Inisialisasi filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_jenis_sampah = isset($_GET['filter_jenis_sampah']) ? $_GET['filter_jenis_sampah'] : '';
$search_setoran = isset($_GET['search_setoran']) ? $_GET['search_setoran'] : '';
$search_penjualan = isset($_GET['search_penjualan']) ? $_GET['search_penjualan'] : '';

function get_config_value($conn_obj, $setting_name, $id_tps, $default_value = null) {
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
    $stmt = $conn_obj->prepare($sql);
    if ($stmt === FALSE) { error_log("Error preparing get_config_value: " . $conn_obj->error); return $default_value; }
    $stmt->bind_param("si", $setting_name, $id_tps);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return floatval($row['nilai']);
    }
    $stmt->close();
    return $default_value;
}

$all_jenis_sampah_list = [];
$sql_all_sampah = "SELECT `kode_sampah`, `jenis_sampah` FROM `tb_sampah` WHERE `id_tps` = ? ORDER BY `jenis_sampah` ASC";
$stmt_all_sampah = $conn->prepare($sql_all_sampah);
$stmt_all_sampah->bind_param("i", $id_tps_admin);
$stmt_all_sampah->execute();
$result_all_sampah = $stmt_all_sampah->get_result();
if ($result_all_sampah) {
    while ($row = $result_all_sampah->fetch_assoc()) {
        $all_jenis_sampah_list[] = $row;
    }
}
$stmt_all_sampah->close();

// FUNGSI BARU: Get Laporan Setoran Detail
function getDetailSetoran($conn, $id_tps, $start_date, $end_date, $filter_jenis_sampah = '', $search_query = '') {
    $data = [];
    $sql = "SELECT
                tss.tanggal_pengambilan,
                tss.nama_nasabah,
                tss.jenis_sampah,
                tss.berat_kg,
                tss.harga_per_kg,
                tss.total,
                tss.tabungan_nasabah AS nominal,
                tss.pos_penimbangan,
                tss.tps3r
            FROM `tb_setorsampah` tss
            WHERE tss.tanggal_pengambilan BETWEEN ? AND ? AND tss.status_setoran = 'final' AND tss.id_tps = ?";
    $params = "ssi";
    $values = [$start_date, $end_date, $id_tps];

    if (!empty($filter_jenis_sampah)) {
        $sql .= " AND tss.kode_sampah = ?";
        $params .= "s";
        $values[] = $filter_jenis_sampah;
    }
    if (!empty($search_query)) {
        $sql .= " AND (tss.jenis_sampah LIKE ? OR tss.nama_nasabah LIKE ?)";
        $search_like = "%" . $search_query . "%";
        $params .= "ss";
        $values[] = $search_like;
        $values[] = $search_like;
    }
    $sql .= " ORDER BY tss.tanggal_pengambilan ASC, tss.nama_nasabah ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === FALSE) { error_log("Error preparing getDetailSetoran: " . $conn->error); return []; }
    $stmt->bind_param($params, ...$values);
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    $stmt->close();
    return $data;
}


// PANGGILAN FUNGSI
$persen_nasabah_config = get_config_value($conn, 'persen_nasabah', $id_tps_admin, 60.00);
$persen_tps_config = get_config_value($conn, 'persen_tps', $id_tps_admin, 20.00);
$persen_pengepul_config = get_config_value($conn, 'persen_pengepul', $id_tps_admin, 20.00);

// Panggil fungsi laporan detail setoran
$detail_setoran = getDetailSetoran($conn, $id_tps_admin, $start_date, $end_date, $filter_jenis_sampah, $search_setoran);

$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Global</h1>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Filter Laporan Global</h2>
        <form action="laporan_global.php" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-4">
                <div>
                    <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">Tanggal Mulai:</label>
                    <input type="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div>
                    <label for="end_date" class="block mb-2 text-sm font-medium text-gray-900">Tanggal Akhir:</label>
                    <input type="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div>
                    <label for="filter_jenis_sampah" class="block mb-2 text-sm font-medium text-gray-900">Filter Jenis Sampah:</label>
                    <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="filter_jenis_sampah" name="filter_jenis_sampah">
                        <option value="">Semua Jenis Sampah</option>
                        <?php foreach ($all_jenis_sampah_list as $sampah): ?>
                            <option value="<?php echo htmlspecialchars($sampah['kode_sampah']); ?>" <?php echo ($filter_jenis_sampah == $sampah['kode_sampah']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sampah['jenis_sampah']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 font-semibold w-full">Tampilkan Laporan</button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Laporan Setoran Sampah (Detail)</h2>
        <div class="overflow-x-auto mb-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Nasabah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Berat (KG)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga/KG (Rp)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total (Rp)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nominal (<?= number_format($persen_nasabah_config, 0); ?>%)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pos Penimbangan (<?= number_format($persen_tps_config, 0); ?>%)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TPS3R (<?= number_format($persen_pengepul_config, 0); ?>%)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $total_berat = 0; 
                    $total_nilai = 0; 
                    $total_nominal_nasabah = 0;
                    $total_pos = 0;
                    $total_tps3r = 0;
                    ?>
                    <?php if (!empty($detail_setoran)): ?>
                        <?php foreach ($detail_setoran as $data): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['tanggal_pengambilan']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_nasabah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($data['berat_kg'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['harga_per_kg'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['total'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['nominal'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['pos_penimbangan'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['tps3r'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php
                            $total_berat += $data['berat_kg'];
                            $total_nilai += $data['total'];
                            $total_nominal_nasabah += $data['nominal'];
                            $total_pos += $data['pos_penimbangan'];
                            $total_tps3r += $data['tps3r'];
                            ?>
                        <?php endforeach; ?>
                        <tr class="font-bold">
                            <td colspan="3" class="px-6 py-4 text-right">TOTAL</td>
                            <td class="px-6 py-4"><?php echo number_format($total_berat, 2, ',', '.'); ?></td>
                            <td></td>
                            <td class="px-6 py-4">Rp <?php echo number_format($total_nilai, 2, ',', '.'); ?></td>
                            <td class="px-6 py-4">Rp <?php echo number_format($total_nominal_nasabah, 2, ',', '.'); ?></td>
                            <td class="px-6 py-4">Rp <?php echo number_format($total_pos, 2, ',', '.'); ?></td>
                            <td class="px-6 py-4">Rp <?php echo number_format($total_tps3r, 2, ',', '.'); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data setoran untuk periode ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex justify-end space-x-2">
            <a href="export_laporan.php?type=detail_setoran&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&jenis_sampah=<?php echo htmlspecialchars($filter_jenis_sampah); ?>&format=excel" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 flex items-center">
                <i class="fas fa-file-excel mr-2"></i> Ekspor Excel
            </a>
            <a href="export_laporan.php?type=detail_setoran&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&jenis_sampah=<?php echo htmlspecialchars($filter_jenis_sampah); ?>&format=pdf" class="bg-red-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-red-700 flex items-center">
                <i class="fas fa-file-pdf mr-2"></i> Ekspor PDF
            </a>
        </div>
    </div>
</div>

</body>
</html>