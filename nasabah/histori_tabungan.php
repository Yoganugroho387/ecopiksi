<?php
// nasabah/histori_tabungan.php
$current_page = 'histori_tabungan';

require_once '../includes/header.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

$no_rekening_nasabah = $_SESSION['no_rekening'];
$nama_nasabah_session = $_SESSION['nama_nasabah'];
$id_tps_nasabah = $_SESSION['id_tps'];

$histori_mutasi = [];
$sql_fetch_mutasi = "SELECT id_mutasi, tanggal_mutasi, tipe_mutasi, jumlah_mutasi, keterangan
                     FROM tb_tabungan_nasabah
                     WHERE no_rekening = ? AND id_tps = ?
                     ORDER BY tanggal_mutasi DESC, id_mutasi DESC";
$stmt_fetch_mutasi = $conn->prepare($sql_fetch_mutasi);
$stmt_fetch_mutasi->bind_param("si", $no_rekening_nasabah, $id_tps_nasabah);
$stmt_fetch_mutasi->execute();
$result_fetch_mutasi = $stmt_fetch_mutasi->get_result();

if ($result_fetch_mutasi) {
    while ($row = $result_fetch_mutasi->fetch_assoc()) {
        $histori_mutasi[] = $row;
    }
}
$stmt_fetch_mutasi->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Mutasi Tabungan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .summary-row {
            background-color: #f0fdf4;
            font-weight: bold;
            color: #166534;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">

<?php include '../includes/sidebar_nasabah.php'; ?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Riwayat Mutasi Tabungan Anda</h1>

    <div class="flex justify-end mb-4">
        <a href="export_mutasi_excel.php?id_tps=<?php echo htmlspecialchars($id_tps_nasabah); ?>" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center mr-2">
            <i class="fas fa-file-excel mr-2"></i> Ekspor Excel
        </a>
        <a href="export_mutasi_pdf.php?id_tps=<?php echo htmlspecialchars($id_tps_nasabah); ?>" class="bg-red-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-red-700 transition-colors duration-200 flex items-center">
            <i class="fas fa-file-pdf mr-2"></i> Ekspor PDF
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Mutasi Tabungan</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No.</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah (Rp)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detail</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    if (!empty($histori_mutasi)):
                        $current_month = null;
                        $monthly_total_masuk = 0;
                        $no = 1; // BARU: Inisialisasi nomor
                    ?>
                        <?php foreach ($histori_mutasi as $mutasi): ?>
                            <?php
                            $mutasi_month = date('Y-m', strtotime($mutasi['tanggal_mutasi']));
                            if ($current_month !== $mutasi_month && $current_month !== null) {
                                // Cetak baris total untuk bulan sebelumnya
                                echo '
                                    <tr class="summary-row">
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            Total Pendapatan (' . date('F Y', strtotime($current_month . '-01')) . ')
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700">
                                            Rp ' . number_format($monthly_total_masuk, 2, ',', '.') . '
                                        </td>
                                    </tr>';
                                $monthly_total_masuk = 0;
                            }
                            $current_month = $mutasi_month;

                            if ($mutasi['tipe_mutasi'] == 'masuk') {
                                $monthly_total_masuk += $mutasi['jumlah_mutasi'];
                            }
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $no++; ?>.</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($mutasi['tanggal_mutasi']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php
                                            if ($mutasi['tipe_mutasi'] == 'masuk') echo 'bg-purple-100 text-purple-800';
                                            else echo 'bg-red-100 text-red-800';
                                        ?>">
                                        <?php echo ucfirst($mutasi['tipe_mutasi']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium
                                    <?php echo ($mutasi['tipe_mutasi'] == 'masuk') ? 'text-purple-600' : 'text-red-600'; ?>">
                                    Rp <?php echo number_format($mutasi['jumlah_mutasi'], 2, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button onclick="showDetails('<?php echo htmlspecialchars($mutasi['keterangan']); ?>')" class="text-blue-600 hover:text-blue-900 font-semibold focus:outline-none">Lihat</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php
                            // Cetak baris total untuk bulan terakhir
                            if ($current_month !== null) {
                                echo '
                                    <tr class="summary-row">
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            Total Pendapatan (' . date('F Y', strtotime($current_month . '-01')) . ')
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-700">
                                            Rp ' . number_format($monthly_total_masuk, 2, ',', '.') . '
                                        </td>
                                    </tr>';
                            }
                        ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Anda belum memiliki riwayat mutasi tabungan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="detail-popup" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Detail Keterangan</h3>
            <div class="mt-2 px-7 py-3">
                <p id="popup-text" class="text-sm text-gray-500"></p>
            </div>
            <div class="items-center px-4 py-3">
                <button id="close-popup" class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const popup = document.getElementById('detail-popup');
    const popupText = document.getElementById('popup-text');
    const closeButton = document.getElementById('close-popup');

    function showDetails(keterangan) {
        popupText.innerText = keterangan;
        popup.classList.remove('hidden');
    }

    closeButton.addEventListener('click', () => {
        popup.classList.add('hidden');
    });

    popup.addEventListener('click', (e) => {
        if (e.target.id === 'detail-popup') {
            popup.classList.add('hidden');
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>