<?php
// superadmin/laporan.php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php';

// Periksa apakah pengguna adalah Superadmin
if ($_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

// Inisialisasi filter tanggal dan jenis sampah
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_jenis_sampah = isset($_GET['filter_jenis_sampah']) ? $_GET['filter_jenis_sampah'] : '';

// Inisialisasi query pencarian
$search_setoran = isset($_GET['search_setoran']) ? $_GET['search_setoran'] : '';
$search_penjualan = isset($_GET['search_penjualan']) ? $_GET['search_penjualan'] : '';

// Ambil daftar jenis sampah dari semua TPS untuk filter dropdown
$all_jenis_sampah_list = [];
$sql_all_sampah = "SELECT `kode_sampah`, `jenis_sampah` FROM `tb_sampah` GROUP BY `kode_sampah`, `jenis_sampah` ORDER BY `jenis_sampah` ASC";
$result_all_sampah = $conn->query($sql_all_sampah);
if ($result_all_sampah) {
    while ($row = $result_all_sampah->fetch_assoc()) {
        $all_jenis_sampah_list[] = $row;
    }
}

// --- Fungsi untuk mendapatkan data laporan (tanpa filter id_tps) ---
function getRingkasanSetoran($conn, $start_date, $end_date, $filter_jenis_sampah = '', $search_query = '') {
    $data = [];
    $sql = "SELECT
                ts.jenis_sampah,
                SUM(tss.berat_kg) AS total_berat_kg,
                SUM(tss.total) AS total_nilai_sampah,
                SUM(tss.tabungan_nasabah) AS total_masuk_nasabah,
                SUM(tss.pos_penimbangan) AS total_operasional,
                SUM(tss.tps3r) AS total_pengepul
            FROM `tb_setorsampah` tss
            JOIN `tb_sampah` ts ON tss.kode_sampah = ts.kode_sampah
            WHERE tss.tanggal_pengambilan BETWEEN ? AND ? AND tss.status_setoran = 'final'";
    $params = "ss";
    $values = [$start_date, $end_date];

    if (!empty($filter_jenis_sampah)) {
        $sql .= " AND tss.kode_sampah = ?";
        $params .= "s";
        $values[] = $filter_jenis_sampah;
    }
    if (!empty($search_query)) {
        $sql .= " AND (ts.jenis_sampah LIKE ?)";
        $search_like = "%" . $search_query . "%";
        $params .= "s";
        $values[] = $search_like;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    $stmt->close();
    return $data;
}

function getRingkasanPenjualan($conn, $start_date, $end_date, $filter_jenis_sampah = '', $search_query = '') {
    $data = [];
    $sql = "SELECT
                ts.jenis_sampah,
                SUM(ttp.berat_kg) AS total_berat_jual,
                SUM(ttp.total_penjualan) AS total_penjualan_rupiah
            FROM `tb_transaksi_penjualan` ttp
            JOIN `tb_sampah` ts ON ttp.kode_sampah = ts.kode_sampah
            WHERE ttp.tanggal_jual BETWEEN ? AND ?";
    $params = "ss";
    $values = [$start_date, $end_date];

    if (!empty($filter_jenis_sampah)) {
        $sql .= " AND ttp.kode_sampah = ?";
        $params .= "s";
        $values[] = $filter_jenis_sampah;
    }
    if (!empty($search_query)) {
        $sql .= " AND (ts.jenis_sampah LIKE ? OR ttp.nama_pengepul LIKE ?)";
        $search_like = "%" . $search_query . "%";
        $params .= "ss";
        $values[] = $search_like;
        $values[] = $search_like;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    $stmt->close();
    return $data;
}


$ringkasan_setoran = getRingkasanSetoran($conn, $start_date, $end_date, $filter_jenis_sampah, $search_setoran);
$ringkasan_penjualan = getRingkasanPenjualan($conn, $start_date, $end_date, $filter_jenis_sampah, $search_penjualan);


$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Sistem Keseluruhan (Superadmin)</h1>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Filter Laporan Global</h2>
        <form action="laporan.php" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-4">
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
            </div>
            <div class="flex items-end justify-end space-x-2 mt-4">
                <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 font-semibold">Tampilkan Laporan</button>
                <a href="laporan.php" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-lg shadow-md hover:bg-gray-300 transition-colors duration-200 text-center font-semibold">Reset Filter</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Laporan Setoran Sampah (Periode: <?php echo htmlspecialchars($start_date); ?> s/d <?php echo htmlspecialchars($end_date); ?>)
            <?php if (!empty($filter_jenis_sampah)): ?>
                <small class="text-sm text-gray-500 ml-2">(Jenis Sampah:
                    <?php
                    $selected_jenis_sampah_name = '';
                    foreach ($all_jenis_sampah_list as $s) {
                        if ($s['kode_sampah'] == $filter_jenis_sampah) {
                            $selected_jenis_sampah_name = $s['jenis_sampah'];
                            break;
                        }
                    }
                    echo htmlspecialchars($selected_jenis_sampah_name);
                    ?>)
                </small>
            <?php endif; ?>
        </h2>
        <div class="overflow-x-auto mb-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Berat (KG)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Nilai Sampah (Rp)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Masuk Nasabah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Operasional Bank Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Pos Penimbang</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $total_setoran = 0; $total_nasabah_share = 0; $total_ops_share = 0; $total_pengepul_share = 0; ?>
                    <?php if (!empty($ringkasan_setoran)): ?>
                        <?php foreach ($ringkasan_setoran as $data): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($data['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($data['total_berat_kg'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['total_nilai_sampah'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['total_masuk_nasabah'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['total_operasional'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['total_pengepul'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php
                                $total_setoran += $data['total_nilai_sampah'];
                                $total_nasabah_share += $data['total_masuk_nasabah'];
                                $total_ops_share += $data['total_operasional'];
                                $total_pengepul_share += $data['total_pengepul'];
                            ?>
                        <?php endforeach; ?>
                        <tr>
                            <th colspan="2" class="px-6 py-4 text-right text-sm font-bold text-gray-900">TOTAL KESELURUHAN</th>
                            <th class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Rp <?php echo number_format($total_setoran, 2, ',', '.'); ?></th>
                            <th class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Rp <?php echo number_format($total_nasabah_share, 2, ',', '.'); ?></th>
                            <th class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Rp <?php echo number_format($total_ops_share, 2, ',', '.'); ?></th>
                            <th class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Rp <?php echo number_format($total_pengepul_share, 2, ',', '.'); ?></th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Tidak ada data setoran untuk periode ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Laporan Penjualan Sampah (Periode: <?php echo htmlspecialchars($start_date); ?> s/d <?php echo htmlspecialchars($end_date); ?>)
            <?php if (!empty($filter_jenis_sampah)): ?>
                <small class="text-sm text-gray-500 ml-2">(Jenis Sampah:
                    <?php
                    $selected_jenis_sampah_name = '';
                    foreach ($all_jenis_sampah_list as $s) {
                        if ($s['kode_sampah'] == $filter_jenis_sampah) {
                            $selected_jenis_sampah_name = $s['jenis_sampah'];
                            break;
                        }
                    }
                    echo htmlspecialchars($selected_jenis_sampah_name);
                    ?>)
                </small>
            <?php endif; ?>
        </h2>
        <div class="overflow-x-auto mb-4">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Berat Terjual (KG)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Penjualan (Rp)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $total_penjualan_global = 0; ?>
                    <?php if (!empty($ringkasan_penjualan)): ?>
                        <?php foreach ($ringkasan_penjualan as $data): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($data['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($data['total_berat_jual'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($data['total_penjualan_rupiah'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php $total_penjualan_global += $data['total_penjualan_rupiah']; ?>
                        <?php endforeach; ?>
                        <tr>
                            <th colspan="2" class="px-6 py-4 text-right text-sm font-bold text-gray-900">TOTAL KESELURUHAN</th>
                            <th class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Rp <?php echo number_format($total_penjualan_global, 2, ',', '.'); ?></th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Tidak ada data penjualan untuk periode ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
