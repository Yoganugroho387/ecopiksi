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

$filter_rt = isset($_GET['filter_rt']) ? $_GET['filter_rt'] : '';
$filter_rw = isset($_GET['filter_rw']) ? $_GET['filter_rw'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fungsi untuk mendapatkan semua RT/RW yang unik
function getUniqueRTandRW($conn, $id_tps) {
    $data = ['rt' => [], 'rw' => []];
    $sql_rt = "SELECT DISTINCT rt FROM tb_nasabah WHERE id_tps = ? ORDER BY rt ASC";
    $stmt = $conn->prepare($sql_rt);
    $stmt->bind_param("i", $id_tps);
    $stmt->execute();
    $result_rt = $stmt->get_result();
    while ($row = $result_rt->fetch_assoc()) {
        $data['rt'][] = $row['rt'];
    }
    $stmt->close();

    $sql_rw = "SELECT DISTINCT rw FROM tb_nasabah WHERE id_tps = ? ORDER BY rw ASC";
    $stmt = $conn->prepare($sql_rw);
    $stmt->bind_param("i", $id_tps);
    $stmt->execute();
    $result_rw = $stmt->get_result();
    while ($row = $result_rw->fetch_assoc()) {
        $data['rw'][] = $row['rw'];
    }
    $stmt->close();
    return $data;
}

// Fungsi untuk mendapatkan data nasabah per RT/RW
function getNasabahPerRT_RW($conn, $id_tps, $rt = '', $rw = '') {
    $data = [];
    $sql = "SELECT no_rekening, nama_nasabah, rt, rw FROM tb_nasabah WHERE id_tps = ?";
    $params = "i";
    $values = [$id_tps];

    if (!empty($rt)) {
        $sql .= " AND rt = ?";
        $params .= "s";
        $values[] = $rt;
    }
    if (!empty($rw)) {
        $sql .= " AND rw = ?";
        $params .= "s";
        $values[] = $rw;
    }
    $sql .= " ORDER BY rw, rt, nama_nasabah ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

$unique_locations = getUniqueRTandRW($conn, $id_tps_admin);
$nasabah_list = getNasabahPerRT_RW($conn, $id_tps_admin, $filter_rt, $filter_rw);

?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Berdasarkan Wilayah (RT/RW)</h1>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="laporan_per_rt.php" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-4">
                <div>
                    <label for="filter_rt" class="block mb-2 text-sm font-medium text-gray-900">Filter RT:</label>
                    <select id="filter_rt" name="filter_rt" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        <option value="">Semua RT</option>
                        <?php foreach ($unique_locations['rt'] as $rt): ?>
                            <option value="<?php echo htmlspecialchars($rt); ?>" <?php echo ($filter_rt == $rt) ? 'selected' : ''; ?>>RT <?php echo htmlspecialchars($rt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_rw" class="block mb-2 text-sm font-medium text-gray-900">Filter RW:</label>
                    <select id="filter_rw" name="filter_rw" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        <option value="">Semua RW</option>
                        <?php foreach ($unique_locations['rw'] as $rw): ?>
                            <option value="<?php echo htmlspecialchars($rw); ?>" <?php echo ($filter_rw == $rw) ? 'selected' : ''; ?>>RW <?php echo htmlspecialchars($rw); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">Tanggal Mulai:</label>
                    <input type="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div>
                    <label for="end_date" class="block mb-2 text-sm font-medium text-gray-900">Tanggal Akhir:</label>
                    <input type="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div>
                    <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 font-semibold w-full">Tampilkan</button>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Nasabah</h2>
        <div class="overflow-x-auto mb-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Rekening</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Nasabah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RT/RW</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($nasabah_list)): ?>
                        <?php foreach ($nasabah_list as $nasabah): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['no_rekening']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['nama_nasabah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['rt']) . '/' . htmlspecialchars($nasabah['rw']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <a href="laporan_detail_rt.php?no_rekening=<?php echo urlencode($nasabah['no_rekening']); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-blue-700">
                                        Lihat Laporan
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Tidak ada data nasabah.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="flex justify-end space-x-2 mt-4">
            <?php if (!empty($nasabah_list)): ?>
                <?php
                $rekening_list = array_column($nasabah_list, 'no_rekening');
                $rekening_string = http_build_query(['no_rekening' => $rekening_list]);
                ?>
                <a href="export_laporan.php?type=detail_nasabah&<?php echo $rekening_string; ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&format=excel" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Ekspor Semua ke Excel
                </a>
                <a href="export_laporan.php?type=detail_nasabah&<?php echo $rekening_string; ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&format=pdf" class="bg-red-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-red-700 transition-colors duration-200 flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Ekspor Semua ke PDF
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$conn->close();
ob_end_flush();
?>