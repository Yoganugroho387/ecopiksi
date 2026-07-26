<?php
// nasabah/export_mutasi_pdf.php
require_once '../vendor/autoload.php';
require_once '../config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'nasabah' || !isset($_SESSION['no_rekening'])) {
    header("Location: ../auth/login.php");
    exit();
}

$no_rekening_nasabah = $_SESSION['no_rekening'];
$nama_nasabah_session = $_SESSION['nama_nasabah'];
$id_tps_nasabah = $_SESSION['id_tps'];

// --- Ambil data mutasi tabungan ---
$histori_mutasi = [];
$sql_fetch_mutasi = "SELECT id_mutasi, tanggal_mutasi, tipe_mutasi, jumlah_mutasi, keterangan
                     FROM tb_tabungan_nasabah
                     WHERE no_rekening = ? AND id_tps = ?
                     ORDER BY tanggal_mutasi ASC, id_mutasi ASC";
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

// --- Generate HTML untuk PDF ---
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Mutasi Tabungan</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 10pt; }
        h1 { font-size: 16pt; text-align: center; margin-bottom: 15px; }
        h2 { font-size: 12pt; margin-top: 15px; }
        .info-section { margin-bottom: 10px; }
        .info-section p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge {
            display: inline-block;
            padding: 0.2em 0.5em;
            font-size: 0.6em;
            font-weight: bold;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            color: #fff;
        }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .summary-row {
            background-color: #f0fdf4;
            font-weight: bold;
            color: #166534;
        }
        thead { display: table-header-group; }
    </style>
</head>
<body>
    <h1>LAPORAN MUTASI TABUNGAN NASABAH</h1>
    <div class="info-section">
        <p><strong>Nama Nasabah:</strong> ' . htmlspecialchars($nama_nasabah_session) . '</p>
        <p><strong>No. Rekening:</strong> ' . htmlspecialchars($no_rekening_nasabah) . '</p>
        <p><strong>Tanggal Cetak:</strong> ' . date('d-m-Y H:i:s') . '</p>
    </div>
    
    <h2>Riwayat Mutasi Tabungan</h2>
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($histori_mutasi)) {
    $current_month = null;
    $monthly_total_masuk = 0;
    $no = 1;
    
    foreach ($histori_mutasi as $mutasi) {
        $mutasi_month = date('Y-m', strtotime($mutasi['tanggal_mutasi']));
        if ($current_month !== $mutasi_month && $current_month !== null) {
            $html .= '
                <tr class="summary-row">
                    <td colspan="3" style="text-align: right;">Total Pendapatan (' . date('F Y', strtotime($current_month . '-01')) . ')</td>
                    <td>Rp ' . number_format($monthly_total_masuk, 2, ',', '.') . '</td>
                </tr>';
            $monthly_total_masuk = 0;
        }
        $current_month = $mutasi_month;

        if ($mutasi['tipe_mutasi'] == 'masuk') {
            $monthly_total_masuk += $mutasi['jumlah_mutasi'];
        }

        $tipe_mutasi_class = ($mutasi['tipe_mutasi'] == 'masuk') ? 'badge-success' : 'badge-danger';
        $tipe_mutasi_display = ucfirst($mutasi['tipe_mutasi']);
        
        $html .= '
            <tr>
                <td>' . $no++ . '.</td>
                <td>' . htmlspecialchars($mutasi['tanggal_mutasi']) . '</td>
                <td><span class="badge ' . $tipe_mutasi_class . '">' . $tipe_mutasi_display . '</span></td>
                <td>Rp ' . number_format($mutasi['jumlah_mutasi'], 2, ',', '.') . '</td>
            </tr>';
    }

    if ($current_month !== null) {
        $html .= '
            <tr class="summary-row">
                <td colspan="3" style="text-align: right;">Total Pendapatan (' . date('F Y', strtotime($current_month . '-01')) . ')</td>
                <td>Rp ' . number_format($monthly_total_masuk, 2, ',', '.') . '</td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="4" class="text-center">Tidak ada riwayat mutasi tabungan.</td></tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Mutasi_Tabungan_' . $nama_nasabah_session . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
exit();