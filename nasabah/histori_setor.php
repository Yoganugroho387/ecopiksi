<?php
// nasabah/histori_setor.php
$current_page = 'histori_setor';

require_once '../includes/header.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

$no_rekening_nasabah = $_SESSION['no_rekening'];
$nama_nasabah_session = $_SESSION['nama_nasabah'];
$id_tps_nasabah = $_SESSION['id_tps'];

// --- LANGKAH 1: AMBIL DAN PROSES DATA ---
// Mengambil semua data setoran
$histori_setoran_raw = [];
$sql_fetch_setoran = "SELECT `tanggal_pengambilan`, `jenis_sampah`, `berat_kg`, `harga_per_kg`, `total`, `tabungan_nasabah`, `status_setoran`
                     FROM `tb_setorsampah`
                     WHERE `no_rekening` = ? AND `id_tps` = ?
                     ORDER BY `jenis_sampah` ASC, `tanggal_pengambilan` DESC";
$stmt_fetch_setoran = $conn->prepare($sql_fetch_setoran);
$stmt_fetch_setoran->bind_param("si", $no_rekening_nasabah, $id_tps_nasabah);
$stmt_fetch_setoran->execute();
$result_fetch_setoran = $stmt_fetch_setoran->get_result();

if ($result_fetch_setoran) {
    while ($row = $result_fetch_setoran->fetch_assoc()) {
        $histori_setoran_raw[] = $row;
    }
}
$stmt_fetch_setoran->close();
$conn->close();

// Memproses data mentah menjadi ringkasan total
$histori_setoran_ringkasan = [];
$total_all_berat_kg = 0;
$total_all_terjual = 0;
$total_all_tabungan = 0;
$total_per_jenis_berat_kg = 0;
$total_per_jenis_nilai = 0;
$total_per_jenis_tabungan = 0;
$current_jenis_sampah = null;

if (!empty($histori_setoran_raw)) {
    foreach ($histori_setoran_raw as $setoran) {
        if ($current_jenis_sampah !== $setoran['jenis_sampah'] && $current_jenis_sampah !== null) {
            $histori_setoran_ringkasan[] = [
                'label' => $current_jenis_sampah,
                'berat' => $total_per_jenis_berat_kg,
                'nilai' => $total_per_jenis_nilai,
                'tabungan' => $total_per_jenis_tabungan
            ];
            $total_per_jenis_berat_kg = 0;
            $total_per_jenis_nilai = 0;
            $total_per_jenis_tabungan = 0;
        }

        $current_jenis_sampah = $setoran['jenis_sampah'];

        if ($setoran['status_setoran'] == 'final') {
            $total_per_jenis_berat_kg += $setoran['berat_kg'];
            $total_per_jenis_nilai += $setoran['total'];
            $total_per_jenis_tabungan += $setoran['tabungan_nasabah'];
        }
    }
    
    // Tambahkan total untuk jenis sampah terakhir
    $histori_setoran_ringkasan[] = [
        'label' => $current_jenis_sampah,
        'berat' => $total_per_jenis_berat_kg,
        'nilai' => $total_per_jenis_nilai,
        'tabungan' => $total_per_jenis_tabungan
    ];

    // Hitung total keseluruhan
    $total_all_berat_kg = array_sum(array_column($histori_setoran_raw, 'berat_kg'));
    $total_all_terjual = array_sum(array_column($histori_setoran_raw, 'total'));
    $total_all_tabungan = array_sum(array_column($histori_setoran_raw, 'tabungan_nasabah'));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Setoran Sampah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">

<?php include '../includes/sidebar_nasabah.php'; ?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Riwayat Setoran Sampah Anda</h1>

    <div class="flex justify-end mb-4">
        <a href="export_laporan_nasabah.php?format=excel&id_tps=<?php echo htmlspecialchars($id_tps_nasabah); ?>" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center mr-2">
            <i class="fas fa-file-excel mr-2"></i> Ekspor Excel
        </a>
        <a href="export_laporan_nasabah.php?format=pdf&id_tps=<?php echo htmlspecialchars($id_tps_nasabah); ?>" class="bg-red-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-red-700 transition-colors duration-200 flex items-center">
            <i class="fas fa-file-pdf mr-2"></i> Ekspor PDF
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Setoran Sampah</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Berat (KG)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga/KG</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Nilai</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Masuk Tabungan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($histori_setoran_raw)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($histori_setoran_raw as $setoran): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $no++; ?>.</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($setoran['tanggal_pengambilan']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($setoran['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($setoran['berat_kg'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo ($setoran['status_setoran'] == 'final' && isset($setoran['harga_per_kg'])) ? "Rp " . number_format($setoran['harga_per_kg'], 2, ',', '.') : "-"; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo ($setoran['status_setoran'] == 'final' && isset($setoran['total'])) ? "Rp " . number_format($setoran['total'], 2, ',', '.') : "-"; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo ($setoran['status_setoran'] == 'final' && isset($setoran['tabungan_nasabah'])) ? "Rp " . number_format($setoran['tabungan_nasabah'], 2, ',', '.') : "-"; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo ($setoran['status_setoran'] == 'pending_harga') ? 'bg-yellow-100 text-yellow-800' : 'bg-purple-100 text-purple-800'; ?>">
                                        <?php echo ($setoran['status_setoran'] == 'pending_harga') ? 'Menunggu Dijual' : 'Dijual'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Anda belum memiliki riwayat setoran sampah.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($histori_setoran_ringkasan)): ?>
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Total Sampah Terjual per Jenis</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Berat (KG)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Nilai</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Masuk Tabungan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($histori_setoran_ringkasan as $ringkasan): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($ringkasan['label']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($ringkasan['berat'], 2, ',', '.'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($ringkasan['nilai'], 2, ',', '.'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo number_format($ringkasan['tabungan'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($histori_setoran_raw)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Total Keseluruhan Sampah Terjual</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Berat (KG)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Nilai</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Masuk Tabungan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr class="bg-purple-100 font-bold">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-purple-800"><?php echo number_format($total_all_berat_kg, 2, ',', '.'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-purple-800">Rp <?php echo number_format($total_all_terjual, 2, ',', '.'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-purple-800">Rp <?php echo number_format($total_all_tabungan, 2, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>