<?php
// admin/laporan.php
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

// --- INISIALISASI FILTER ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_jenis_sampah = isset($_GET['filter_jenis_sampah']) ? $_GET['filter_jenis_sampah'] : '';
$filter_rt = isset($_GET['filter_rt']) ? $_GET['filter_rt'] : '';
$filter_rw = isset($_GET['filter_rw']) ? $_GET['filter_rw'] : '';
$search_nasabah_saldo = isset($_GET['search_nasabah_saldo']) ? $_GET['search_nasabah_saldo'] : '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ringkasan';

// --- DATA HELPER UNTUK DROPDOWN ---
$all_jenis_sampah_list = [];
$sql_all_sampah = "SELECT `kode_sampah`, `jenis_sampah` FROM `tb_sampah` WHERE `id_tps` = ? ORDER BY `jenis_sampah` ASC";
$stmt = $conn->prepare($sql_all_sampah);
$stmt->bind_param("i", $id_tps_admin);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $all_jenis_sampah_list[] = $row; }
$stmt->close();

$rt_options = []; $rw_options = [];
$sql_loc = "SELECT DISTINCT rt, rw FROM tb_nasabah WHERE id_tps = ?";
$stmt = $conn->prepare($sql_loc);
$stmt->bind_param("i", $id_tps_admin);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['rt']) && !in_array($row['rt'], $rt_options)) $rt_options[] = $row['rt'];
    if (!empty($row['rw']) && !in_array($row['rw'], $rw_options)) $rw_options[] = $row['rw'];
}
$stmt->close();
sort($rt_options); sort($rw_options);

// --- FUNGSI GET DATA UTAMA ---

function getConfig($conn, $key, $id_tps, $default) {
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $key, $id_tps);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ? floatval($res['nilai']) : $default;
}

// 1. Ringkasan Setoran (Uang Masuk / Tabungan) - Status Final
function getLaporanSetoran($conn, $id_tps, $start, $end, $jenis = '') {
    $sql = "SELECT ts.jenis_sampah, 
                   SUM(tss.berat_kg) as total_berat, 
                   SUM(tss.total) as total_nilai,
                   SUM(tss.tabungan_nasabah) as total_nasabah,
                   SUM(tss.pos_penimbangan) as total_pos,
                   SUM(tss.tps3r) as total_tps
            FROM tb_setorsampah tss
            JOIN tb_sampah ts ON tss.kode_sampah = ts.kode_sampah AND tss.id_tps = ts.id_tps
            WHERE tss.id_tps = ? AND tss.status_setoran = 'final' 
            AND tss.tanggal_pengambilan BETWEEN ? AND ?";
    
    $types = "iss";
    $params = [$id_tps, $start, $end];
    
    if (!empty($jenis)) {
        $sql .= " AND tss.kode_sampah = ?";
        $types .= "s";
        $params[] = $jenis;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 2. Ringkasan Penjualan (Ke Pengepul)
function getLaporanPenjualan($conn, $id_tps, $start, $end, $jenis = '') {
    $sql = "SELECT ts.jenis_sampah, 
                   SUM(tp.berat_kg) as total_berat, 
                   SUM(tp.total_penjualan) as total_uang
            FROM tb_transaksi_penjualan tp
            JOIN tb_sampah ts ON tp.kode_sampah = ts.kode_sampah AND tp.id_tps = ts.id_tps
            WHERE tp.id_tps = ? AND tp.tanggal_jual BETWEEN ? AND ?";
            
    $types = "iss";
    $params = [$id_tps, $start, $end];
    
    if (!empty($jenis)) {
        $sql .= " AND tp.kode_sampah = ?";
        $types .= "s";
        $params[] = $jenis;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 3. Laporan Pencairan (Uang Keluar) - BARU
function getLaporanPencairan($conn, $id_tps, $start, $end) {
    $sql = "SELECT pd.*, n.nama_nasabah 
            FROM tb_pencairan_dana pd
            JOIN tb_nasabah n ON pd.no_rekening = n.no_rekening
            WHERE pd.id_tps = ? AND pd.status = 'diterima' 
            AND pd.tanggal_transfer BETWEEN ? AND ?
            ORDER BY pd.tanggal_transfer DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $id_tps, $start, $end);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 4. Laporan Aset Pending (Belum Dijual) - BARU
function getLaporanPending($conn, $id_tps) {
    $sql = "SELECT ts.jenis_sampah, SUM(tss.berat_kg) as total_berat
            FROM tb_setorsampah tss
            JOIN tb_sampah ts ON tss.kode_sampah = ts.kode_sampah AND tss.id_tps = ts.id_tps
            WHERE tss.id_tps = ? AND tss.status_setoran = 'pending_harga'
            GROUP BY ts.jenis_sampah";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_tps);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 5. Daftar Saldo Nasabah
function getDaftarSaldo($conn, $id_tps, $search, $rt, $rw) {
    $sql = "SELECT n.no_rekening, n.nama_nasabah, n.rt, n.rw,
            (COALESCE(SUM(CASE WHEN t.tipe_mutasi='masuk' THEN t.jumlah_mutasi ELSE 0 END),0) - 
             COALESCE(SUM(CASE WHEN t.tipe_mutasi='keluar' THEN t.jumlah_mutasi ELSE 0 END),0)) as saldo
            FROM tb_nasabah n
            LEFT JOIN tb_tabungan_nasabah t ON n.no_rekening = t.no_rekening AND n.id_tps = t.id_tps
            WHERE n.id_tps = ? AND n.role = 'nasabah'";
    
    $types = "i";
    $params = [$id_tps];

    if ($search) { $sql .= " AND (n.nama_nasabah LIKE ? OR n.no_rekening LIKE ?)"; $types .= "ss"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($rt) { $sql .= " AND n.rt = ?"; $types .= "s"; $params[] = $rt; }
    if ($rw) { $sql .= " AND n.rw = ?"; $types .= "s"; $params[] = $rw; }

    $sql .= " GROUP BY n.no_rekening ORDER BY n.nama_nasabah ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- EKSEKUSI DATA ---
$persen_nasabah = getConfig($conn, 'persen_nasabah', $id_tps_admin, 70);
$persen_tps = getConfig($conn, 'persen_tps', $id_tps_admin, 25);
$persen_pengepul = getConfig($conn, 'persen_pengepul', $id_tps_admin, 5);

$data_setoran = getLaporanSetoran($conn, $id_tps_admin, $start_date, $end_date, $filter_jenis_sampah);
$data_penjualan = getLaporanPenjualan($conn, $id_tps_admin, $start_date, $end_date, $filter_jenis_sampah);
$data_pencairan = getLaporanPencairan($conn, $id_tps_admin, $start_date, $end_date);
$data_pending = getLaporanPending($conn, $id_tps_admin);
$data_nasabah = getDaftarSaldo($conn, $id_tps_admin, $search_nasabah_saldo, $filter_rt, $filter_rw);

// Hitung Total Dashboard
$grand_total_berat = 0;
$grand_total_omset = 0; // Nilai dari penjualan ke pengepul
$grand_total_cair = 0;
$grand_total_pending_berat = 0;

foreach($data_penjualan as $d) { 
    $grand_total_berat += $d['total_berat']; 
    $grand_total_omset += $d['total_uang']; 
}
foreach($data_pencairan as $d) { 
    $grand_total_cair += $d['jumlah_cair']; 
}
foreach($data_pending as $d) {
    $grand_total_pending_berat += $d['total_berat'];
}

$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan & Analisis</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-100 p-4 rounded-lg shadow-sm border-l-4 border-blue-500">
            <div class="text-gray-500 text-sm">Total Berat Terjual</div>
            <div class="text-2xl font-bold text-blue-700"><?php echo number_format($grand_total_berat, 2, ',', '.'); ?> <span class="text-sm">Kg</span></div>
        </div>
        <div class="bg-green-100 p-4 rounded-lg shadow-sm border-l-4 border-green-500">
            <div class="text-gray-500 text-sm">Total Omset Penjualan</div>
            <div class="text-2xl font-bold text-green-700">Rp <?php echo number_format($grand_total_omset, 0, ',', '.'); ?></div>
        </div>
        <div class="bg-red-100 p-4 rounded-lg shadow-sm border-l-4 border-red-500">
            <div class="text-gray-500 text-sm">Total Pencairan Dana</div>
            <div class="text-2xl font-bold text-red-700">Rp <?php echo number_format($grand_total_cair, 0, ',', '.'); ?></div>
        </div>
        <div class="bg-yellow-100 p-4 rounded-lg shadow-sm border-l-4 border-yellow-500">
            <div class="text-gray-500 text-sm">Stok Pending (Belum Jual)</div>
            <div class="text-2xl font-bold text-yellow-700"><?php echo number_format($grand_total_pending_berat, 2, ',', '.'); ?> <span class="text-sm">Kg</span></div>
        </div>
    </div>

    <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="laporanTab" data-tabs-toggle="#laporanTabContent" role="tablist">
            <li class="me-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg <?php echo ($active_tab == 'ringkasan') ? 'text-purple-600 border-purple-600' : 'hover:text-gray-600 hover:border-gray-300'; ?>" id="ringkasan-tab" data-tabs-target="#ringkasan" type="button" role="tab">Ringkasan Transaksi</button>
            </li>
            <li class="me-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg <?php echo ($active_tab == 'keuangan') ? 'text-purple-600 border-purple-600' : 'hover:text-gray-600 hover:border-gray-300'; ?>" id="keuangan-tab" data-tabs-target="#keuangan" type="button" role="tab">Keuangan & Pencairan</button>
            </li>
            <li class="me-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg <?php echo ($active_tab == 'nasabah') ? 'text-purple-600 border-purple-600' : 'hover:text-gray-600 hover:border-gray-300'; ?>" id="nasabah-tab" data-tabs-target="#nasabah" type="button" role="tab">Laporan Nasabah</button>
            </li>
        </ul>
    </div>

    <div id="laporanTabContent">
        
        <div class="hidden p-4 rounded-lg bg-gray-50" id="ringkasan" role="tabpanel">
            <div class="bg-white p-4 rounded-lg shadow mb-4">
                <form action="laporan.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input type="hidden" name="tab" value="ringkasan">
                    <div>
                        <label class="block text-sm text-gray-700">Tanggal Mulai</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full border rounded p-2">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700">Tanggal Akhir</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full border rounded p-2">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700">Jenis Sampah</label>
                        <select name="filter_jenis_sampah" class="w-full border rounded p-2">
                            <option value="">Semua</option>
                            <?php foreach($all_jenis_sampah_list as $j): ?>
                                <option value="<?php echo $j['kode_sampah']; ?>" <?php echo ($filter_jenis_sampah == $j['kode_sampah']) ? 'selected' : ''; ?>>
                                    <?php echo $j['jenis_sampah']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded w-full hover:bg-purple-700">Filter Data</button>
                    </div>
                </form>
                <div class="mt-4 flex gap-2 justify-end border-t pt-4">
                     <form action="export_laporan.php" method="POST" target="_blank" class="flex gap-2">
                        <input type="hidden" name="type" value="ringkasan_setoran">
                        <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                        <input type="hidden" name="filter_jenis_sampah" value="<?php echo $filter_jenis_sampah; ?>">
                        <button type="submit" name="format" value="excel" class="bg-green-600 text-white px-4 py-2 rounded text-sm flex items-center hover:bg-green-700"><i class="fas fa-file-excel mr-2"></i> Excel Ringkasan</button>
                        <button type="submit" name="format" value="pdf" class="bg-red-600 text-white px-4 py-2 rounded text-sm flex items-center hover:bg-red-700"><i class="fas fa-file-pdf mr-2"></i> PDF Ringkasan</button>
                    </form>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h3 class="font-bold text-lg text-gray-800 mb-3">1. Rincian Setoran Nasabah (Final)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left">Jenis Sampah</th>
                                <th class="px-4 py-2 text-right">Berat (KG)</th>
                                <th class="px-4 py-2 text-right">Nilai Total (Rp)</th>
                                <th class="px-4 py-2 text-right">Nasabah (<?php echo $persen_nasabah; ?>%)</th>
                                <th class="px-4 py-2 text-right">Bank Sampah (<?php echo $persen_tps; ?>%)</th>
                                <th class="px-4 py-2 text-right">Pos/Ops (<?php echo $persen_pengepul; ?>%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if(empty($data_setoran)): ?>
                                <tr><td colspan="6" class="px-4 py-3 text-center text-gray-500">Tidak ada data setoran pada periode ini.</td></tr>
                            <?php else: ?>
                                <?php foreach($data_setoran as $d): ?>
                                <tr>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($d['jenis_sampah']); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo number_format($d['total_berat'], 2, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo number_format($d['total_nilai'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-right font-medium text-green-600"><?php echo number_format($d['total_nasabah'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo number_format($d['total_pos'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo number_format($d['total_tps'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold text-lg text-gray-800 mb-3">2. Rincian Penjualan ke Pengepul</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left">Jenis Sampah</th>
                                <th class="px-4 py-2 text-right">Berat Terjual (KG)</th>
                                <th class="px-4 py-2 text-right">Total Uang Masuk (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($data_penjualan)): ?>
                                <tr><td colspan="3" class="px-4 py-3 text-center text-gray-500">Tidak ada transaksi penjualan.</td></tr>
                            <?php else: ?>
                                <?php foreach($data_penjualan as $d): ?>
                                <tr>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($d['jenis_sampah']); ?></td>
                                    <td class="px-4 py-2 text-right"><?php echo number_format($d['total_berat'], 2, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-right font-bold text-blue-600">Rp <?php echo number_format($d['total_uang'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="hidden p-4 rounded-lg bg-gray-50" id="keuangan" role="tabpanel">
            <div class="bg-white p-4 rounded-lg shadow mb-4">
                <form action="laporan.php" method="GET" class="flex gap-4 items-end">
                    <input type="hidden" name="tab" value="keuangan">
                    <div class="w-1/3">
                        <label class="block text-sm">Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full border rounded p-2">
                    </div>
                    <div class="w-1/3">
                        <label class="block text-sm">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full border rounded p-2">
                    </div>
                    <button class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Update</button>
                </form>
                <div class="mt-4 flex gap-2 justify-end border-t pt-4">
                     <form action="export_laporan.php" method="POST" target="_blank" class="flex gap-2">
                        <input type="hidden" name="type" value="keuangan">
                        <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                        <button type="submit" name="format" value="excel" class="bg-green-600 text-white px-4 py-2 rounded text-sm flex items-center hover:bg-green-700"><i class="fas fa-file-excel mr-2"></i> Excel Keuangan</button>
                        <button type="submit" name="format" value="pdf" class="bg-red-600 text-white px-4 py-2 rounded text-sm flex items-center hover:bg-red-700"><i class="fas fa-file-pdf mr-2"></i> PDF Keuangan</button>
                    </form>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg text-gray-800">Riwayat Pencairan Dana Nasabah</h3>
                    <div class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-bold">
                        Total Keluar: Rp <?php echo number_format($grand_total_cair, 0, ',', '.'); ?>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-red-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Tanggal Transfer/Cair</th>
                                <th class="px-4 py-2 text-left">Nasabah</th>
                                <th class="px-4 py-2 text-right">Jumlah (Rp)</th>
                                <th class="px-4 py-2 text-left">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if(empty($data_pencairan)): ?>
                                <tr><td colspan="4" class="px-4 py-3 text-center text-gray-500">Belum ada data pencairan di periode ini.</td></tr>
                            <?php else: ?>
                                <?php foreach($data_pencairan as $p): ?>
                                <tr>
                                    <td class="px-4 py-2"><?php echo date('d-m-Y', strtotime($p['tanggal_transfer'])); ?></td>
                                    <td class="px-4 py-2">
                                        <div class="font-medium"><?php echo htmlspecialchars($p['nama_nasabah']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($p['no_rekening']); ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-right font-bold text-red-600">Rp <?php echo number_format($p['jumlah_cair'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-gray-600"><?php echo htmlspecialchars($p['keterangan']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="hidden p-4 rounded-lg bg-gray-50" id="nasabah" role="tabpanel">
            <div class="bg-white p-4 rounded-lg shadow mb-4">
                <form action="laporan.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input type="hidden" name="tab" value="nasabah">
                    <div>
                        <label class="block text-sm">Cari Nama/Rekening</label>
                        <input type="text" name="search_nasabah_saldo" value="<?php echo htmlspecialchars($search_nasabah_saldo); ?>" class="w-full border rounded p-2" placeholder="Ketik nama...">
                    </div>
                    <div>
                        <label class="block text-sm">RT</label>
                        <select name="filter_rt" id="f_rt" class="w-full border rounded p-2">
                            <option value="">Semua</option>
                            <?php foreach($rt_options as $rt) echo "<option value='$rt' ".($filter_rt==$rt?'selected':'').">$rt</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm">RW</label>
                        <select name="filter_rw" id="f_rw" class="w-full border rounded p-2">
                            <option value="">Semua</option>
                            <?php foreach($rw_options as $rw) echo "<option value='$rw' ".($filter_rw==$rw?'selected':'').">$rw</option>"; ?>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded w-full hover:bg-purple-700">Cari</button>
                    </div>
                </form>
                <div class="mt-3 flex gap-2">
                    <button type="button" id="btn-select-rt" class="text-xs bg-gray-200 px-3 py-1 rounded hover:bg-gray-300">Pilih Semua di RT Ini</button>
                    <button type="button" id="btn-select-rw" class="text-xs bg-gray-200 px-3 py-1 rounded hover:bg-gray-300">Pilih Semua di RW Ini</button>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow">
                <form id="exportForm" action="export_laporan.php" method="POST" target="_blank">
                    <input type="hidden" name="type" value="detail_nasabah">
                    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                    
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-lg">Daftar Saldo Nasabah</h3>
                        <div class="flex gap-2">
                            <button type="submit" name="format" value="excel" class="bg-green-600 text-white px-4 py-2 rounded text-sm flex items-center hover:bg-green-700">
                                <i class="fas fa-file-excel mr-2"></i> Export Excel (Checklist)
                            </button>
                            <button type="submit" name="format" value="pdf" class="bg-red-600 text-white px-4 py-2 rounded text-sm flex items-center hover:bg-red-700">
                                <i class="fas fa-file-pdf mr-2"></i> Export PDF (Checklist)
                            </button>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2"><input type="checkbox" id="check-all"></th>
                                    <th class="px-4 py-2 text-left">Rekening</th>
                                    <th class="px-4 py-2 text-left">Nama</th>
                                    <th class="px-4 py-2 text-left">RT/RW</th>
                                    <th class="px-4 py-2 text-right">Sisa Saldo</th>
                                    <th class="px-4 py-2 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($data_nasabah as $n): ?>
                                <tr data-rt="<?php echo $n['rt']; ?>" data-rw="<?php echo $n['rw']; ?>">
                                    <td class="px-4 py-2 text-center">
                                        <input type="checkbox" name="no_rekening[]" value="<?php echo htmlspecialchars($n['no_rekening']); ?>" class="chk-nasabah">
                                    </td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($n['no_rekening']); ?></td>
                                    <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($n['nama_nasabah']); ?></td>
                                    <td class="px-4 py-2"><?php echo $n['rt'] . '/' . $n['rw']; ?></td>
                                    <td class="px-4 py-2 text-right font-bold text-green-600">Rp <?php echo number_format($n['saldo'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-center">
                                        <a href="laporan_detail_nasabah.php?no_rekening=<?php echo urlencode($n['no_rekening']); ?>" class="text-blue-600 hover:underline text-xs">Detail</a>
                                        |
                                        <a href="export_laporan.php?type=detail_nasabah&format=pdf&no_rekening=<?= urlencode($n['no_rekening']) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" target="_blank" class="text-red-600 hover:underline text-xs">
                                            <i class="fas fa-print"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Tabs Manually if Flowbite doesn't auto-init
    const tabButtons = document.querySelectorAll('[data-tabs-target]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            // Hide all tabs
            document.querySelectorAll('[role="tabpanel"]').forEach(el => el.classList.add('hidden'));
            // Remove active class from all buttons
            tabButtons.forEach(b => {
                b.classList.remove('text-purple-600', 'border-purple-600');
                b.classList.add('hover:text-gray-600', 'hover:border-gray-300');
            });
            // Show target
            const target = document.querySelector(btn.getAttribute('data-tabs-target'));
            target.classList.remove('hidden');
            // Add active class
            btn.classList.remove('hover:text-gray-600', 'hover:border-gray-300');
            btn.classList.add('text-purple-600', 'border-purple-600');
            
            // Update URL param without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', btn.id.replace('-tab', ''));
            window.history.pushState({}, '', url);
        });
    });

    // Set Active Tab from PHP
    const activeTabId = "<?php echo $active_tab; ?>";
    if(document.getElementById(activeTabId + '-tab')) {
        document.getElementById(activeTabId + '-tab').click();
    }

    // Check All Logic
    const checkAll = document.getElementById('check-all');
    if(checkAll) {
        checkAll.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.chk-nasabah').forEach(c => c.checked = isChecked);
        });
    }

    // Select By RT
    const btnRt = document.getElementById('btn-select-rt');
    if(btnRt) {
        btnRt.addEventListener('click', function() {
            const currentRT = document.getElementById('f_rt').value;
            if(!currentRT) return alert('Pilih RT di filter terlebih dahulu!');
            document.querySelectorAll(`tr[data-rt="${currentRT}"] .chk-nasabah`).forEach(c => c.checked = true);
        });
    }

    // Select By RW
    const btnRw = document.getElementById('btn-select-rw');
    if(btnRw) {
        btnRw.addEventListener('click', function() {
            const currentRW = document.getElementById('f_rw').value;
            if(!currentRW) return alert('Pilih RW di filter terlebih dahulu!');
            document.querySelectorAll(`tr[data-rw="${currentRW}"] .chk-nasabah`).forEach(c => c.checked = true);
        });
    }
});
</script>
<?php require_once '../includes/footer.php'; ?>
<?php ob_end_flush(); ?>