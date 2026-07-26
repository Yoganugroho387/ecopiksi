<?php
// nasabah/export_laporan_nasabah.php
// Skrip ini menangani ekspor riwayat setoran nasabah ke Excel atau PDF.

require_once '../vendor/autoload.php'; // Sesuaikan path jika tidak pakai Composer atau letak vendor berbeda
require_once '../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
$export_format = $_GET['format'] ?? '';

// --- Ambil data mentah dari database ---
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

// --- Proses data untuk ringkasan total ---
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
    
    $histori_setoran_ringkasan[] = [
        'label' => $current_jenis_sampah,
        'berat' => $total_per_jenis_berat_kg,
        'nilai' => $total_per_jenis_nilai,
        'tabungan' => $total_per_jenis_tabungan
    ];

    $total_all_berat_kg = array_sum(array_column($histori_setoran_raw, 'berat_kg'));
    $total_all_terjual = array_sum(array_column($histori_setoran_raw, 'total'));
    $total_all_tabungan = array_sum(array_column($histori_setoran_raw, 'tabungan_nasabah'));
}

if ($export_format == 'excel') {
    // --- EKSPOR KE EXCEL ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $static_prefix_length = strlen('Setoran ');
    $max_name_length = 31 - $static_prefix_length;
    $short_nama_nasabah = mb_substr($nama_nasabah_session, 0, $max_name_length, 'UTF-8');
    if (empty($short_nama_nasabah)) { $short_nama_nasabah = 'Nasabah'; }
    $sheet->setTitle('Setoran ' . $short_nama_nasabah);

    $rowNum = 1;
    
    // Header Laporan
    $sheet->setCellValue('A' . $rowNum, 'LAPORAN RIWAYAT SETORAN SAMPAH NASABAH');
    $sheet->mergeCells('A' . $rowNum . ':H' . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum += 2;
    $sheet->setCellValue('A' . $rowNum, 'Nama Nasabah: ' . $nama_nasabah_session);
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, 'No. Rekening: ' . $no_rekening_nasabah);
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, 'Tanggal Cetak: ' . date('d-m-Y H:i:s'));
    $rowNum += 2;

    // Tabel Riwayat Transaksi
    $sheet->setCellValue('A' . $rowNum, 'Riwayat Transaksi');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    $headers = ['No.', 'Tanggal', 'Jenis Sampah', 'Berat (KG)', 'Harga/KG', 'Total Nilai', 'Masuk Tabungan', 'Status'];
    $sheet->fromArray($headers, NULL, 'A' . $rowNum);

    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9E9E9']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->applyFromArray($headerStyle);
    $rowNum++;

    $no = 1;
    foreach ($histori_setoran_raw as $setoran) {
        $status_display = ($setoran['status_setoran'] == 'pending_harga') ? 'Menunggu Dijual' : 'Dijual';
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $setoran['tanggal_pengambilan']);
        $sheet->setCellValue('C' . $rowNum, $setoran['jenis_sampah']);
        $sheet->setCellValue('D' . $rowNum, $setoran['berat_kg']);
        $sheet->setCellValue('E' . $rowNum, ($setoran['status_setoran'] == 'final' && isset($setoran['harga_per_kg'])) ? $setoran['harga_per_kg'] : '-');
        $sheet->setCellValue('F' . $rowNum, ($setoran['status_setoran'] == 'final' && isset($setoran['total'])) ? $setoran['total'] : '-');
        $sheet->setCellValue('G' . $rowNum, ($setoran['status_setoran'] == 'final' && isset($setoran['tabungan_nasabah'])) ? $setoran['tabungan_nasabah'] : '-');
        $sheet->setCellValue('H' . $rowNum, $status_display);

        if (is_numeric($setoran['berat_kg'])) $sheet->getStyle('D' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        if (is_numeric($setoran['harga_per_kg'])) $sheet->getStyle('E' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        if (is_numeric($setoran['total'])) $sheet->getStyle('F' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        if (is_numeric($setoran['tabungan_nasabah'])) $sheet->getStyle('G' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        $rowNum++;
    }
    
    $rowNum += 2;

    // Tabel Ringkasan per Jenis Sampah
    $sheet->setCellValue('A' . $rowNum, 'Total Sampah Terjual per Jenis');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    $headers_ringkasan = ['Jenis Sampah', 'Berat (KG)', 'Total Nilai', 'Masuk Tabungan'];
    $sheet->fromArray($headers_ringkasan, NULL, 'A' . $rowNum);
    $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->applyFromArray($headerStyle);
    $rowNum++;
    foreach ($histori_setoran_ringkasan as $ringkasan) {
        $sheet->setCellValue('A' . $rowNum, $ringkasan['label']);
        $sheet->setCellValue('B' . $rowNum, $ringkasan['berat']);
        $sheet->setCellValue('C' . $rowNum, $ringkasan['nilai']);
        $sheet->setCellValue('D' . $rowNum, $ringkasan['tabungan']);
        $sheet->getStyle('B' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('C' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        $sheet->getStyle('D' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        $rowNum++;
    }
    
    $rowNum += 2;

    // Tabel Total Keseluruhan
    $sheet->setCellValue('A' . $rowNum, 'Total Keseluruhan Sampah Terjual');
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
    $headers_total = ['Total Berat (KG)', 'Total Nilai', 'Total Masuk Tabungan'];
    $sheet->fromArray($headers_total, NULL, 'A' . $rowNum);
    $sheet->getStyle('A' . $rowNum . ':C' . $rowNum)->applyFromArray($headerStyle);
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, $total_all_berat_kg);
    $sheet->setCellValue('B' . $rowNum, $total_all_terjual);
    $sheet->setCellValue('C' . $rowNum, $total_all_tabungan);
    $sheet->getStyle('A' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('B' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
    $sheet->getStyle('C' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
    
    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $filename = 'Riwayat_Setoran_' . $nama_nasabah_session . '_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} elseif ($export_format == 'pdf') {
    // --- EKSPOR KE PDF ---
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Riwayat Setoran</title>
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
                font-size: 0.65em;
                font-weight: bold;
                line-height: 1;
                text-align: center;
                white-space: nowrap;
                vertical-align: baseline;
                border-radius: 0.25rem;
                color: #fff;
            }
            .badge-warning { background-color: #ffc107; color: #212529; }
            .badge-success { background-color: #28a745; }
            .total-row { font-weight: bold; background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h1>LAPORAN RIWAYAT SETORAN SAMPAH NASABAH</h1>
        <div class="info-section">
            <p><strong>Nama Nasabah:</strong> ' . htmlspecialchars($nama_nasabah_session) . '</p>
            <p><strong>No. Rekening:</strong> ' . htmlspecialchars($no_rekening_nasabah) . '</p>
            <p><strong>Tanggal Cetak:</strong> ' . date('d-m-Y H:i:s') . '</p>
        </div>
        
        <h2>Daftar Setoran Sampah</h2>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Tanggal</th>
                    <th>Jenis Sampah</th>
                    <th>Berat (KG)</th>
                    <th>Harga/KG</th>
                    <th>Total Nilai</th>
                    <th>Masuk Tabungan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
    
    if (!empty($histori_setoran_raw)) {
        $no = 1;
        foreach ($histori_setoran_raw as $setoran) {
            $status_text = ($setoran['status_setoran'] == 'pending_harga') ? 'Menunggu Dijual' : 'Dijual';
            $status_class = ($setoran['status_setoran'] == 'pending_harga') ? 'badge-warning' : 'badge-success';
            $html .= '
                <tr>
                    <td>' . $no++ . '.</td>
                    <td>' . htmlspecialchars($setoran['tanggal_pengambilan']) . '</td>
                    <td>' . htmlspecialchars($setoran['jenis_sampah']) . '</td>
                    <td>' . number_format($setoran['berat_kg'], 2, ',', '.') . '</td>
                    <td>' . (($setoran['status_setoran'] == 'final' && isset($setoran['harga_per_kg'])) ? "Rp " . number_format($setoran['harga_per_kg'], 2, ',', '.') : "-") . '</td>
                    <td>' . (($setoran['status_setoran'] == 'final' && isset($setoran['total'])) ? "Rp " . number_format($setoran['total'], 2, ',', '.') : "-") . '</td>
                    <td>' . (($setoran['status_setoran'] == 'final' && isset($setoran['tabungan_nasabah'])) ? "Rp " . number_format($setoran['tabungan_nasabah'], 2, ',', '.') : "-") . '</td>
                    <td><span class="badge ' . $status_class . '">' . $status_text . '</span></td>
                </tr>';
        }
    } else {
        $html .= '<tr><td colspan="8" class="text-center">Anda belum memiliki riwayat setoran sampah.</td></tr>';
    }

    $html .= '
            </tbody>
        </table>
        <br>
        <h2>Total Sampah Terjual per Jenis</h2>
        <table>
            <thead>
                <tr>
                    <th>Jenis Sampah</th>
                    <th>Berat (KG)</th>
                    <th>Total Nilai</th>
                    <th>Masuk Tabungan</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($histori_setoran_ringkasan as $ringkasan) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($ringkasan['label']) . '</td>
                <td>' . number_format($ringkasan['berat'], 2, ',', '.') . '</td>
                <td>Rp ' . number_format($ringkasan['nilai'], 2, ',', '.') . '</td>
                <td>Rp ' . number_format($ringkasan['tabungan'], 2, ',', '.') . '</td>
            </tr>';
    }
    $html .= '
            </tbody>
        </table>
        <br>
        <h2>Total Keseluruhan Sampah Terjual</h2>
        <table>
            <thead>
                <tr>
                    <th>Total Berat (KG)</th>
                    <th>Total Nilai</th>
                    <th>Total Masuk Tabungan</th>
                </tr>
            </thead>
            <tbody>
                <tr class="total-row">
                    <td>' . number_format($total_all_berat_kg, 2, ',', '.') . '</td>
                    <td>Rp ' . number_format($total_all_terjual, 2, ',', '.') . '</td>
                    <td>Rp ' . number_format($total_all_tabungan, 2, ',', '.') . '</td>
                </tr>
            </tbody>
        </table>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $filename = 'Riwayat_Setoran_' . $nama_nasabah_session . '_' . date('Ymd_His') . '.pdf';
    $dompdf->stream($filename, ["Attachment" => true]);
    exit();

} else {
    echo "Format ekspor tidak valid.";
    exit();
}
?>