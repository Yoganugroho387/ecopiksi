<?php
// admin/export_laporan.php
// Skrip ini menangani ekspor laporan admin (Setoran, Penjualan, Saldo Nasabah)
// ke Excel atau PDF berdasarkan parameter type dan format.

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

// Pastikan hanya admin yang bisa mengakses dan sudah login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// --- Fungsi untuk mendapatkan data laporan (copy dari admin/laporan.php) ---
// CATATAN: Fungsi-fungsi ini harus dijamin ada dan bekerja dengan benar.
// Idealnya, letakkan ini di file terpisah (misal: ../utils/report_data_functions.php)
// dan include di sini dan di admin/laporan.php
function getRingkasanSetoran($conn, $start_date, $end_date, $filter_jenis_sampah = '', $search_query = '') {
    $data = [];
    $sql = "SELECT
                ts.jenis_sampah,
                SUM(tss.berat_kg) AS total_berat_kg,
                SUM(tss.total) AS total_nilai_sampah,
                SUM(tss.tabungan_nasabah) AS total_masuk_nasabah,
                SUM(tss.pos_penimbangan) AS total_operasional
            FROM `tb_setorsampah` tss
            JOIN `tb_sampah` ts ON tss.kode_sampah = ts.kode_sampah
            JOIN `tb_nasabah` tn ON tss.no_rekening = tn.no_rekening
            WHERE tss.tanggal_pengambilan BETWEEN ? AND ? AND tss.status_setoran = 'final'";
    $params = "ss";
    $values = [$start_date, $end_date];

    if (!empty($filter_jenis_sampah)) {
        $sql .= " AND tss.kode_sampah = ?";
        $params .= "s";
        $values[] = $filter_jenis_sampah;
    }
    if (!empty($search_query)) {
        $sql .= " AND (ts.jenis_sampah LIKE ? OR tn.nama_nasabah LIKE ? OR tss.no_transaksi LIKE ?)";
        $search_like = "%" . $search_query . "%";
        $params .= "sss";
        $values[] = $search_like;
        $values[] = $search_like;
        $values[] = $search_like;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === FALSE) { error_log("Error preparing getRingkasanSetoran for export: " . $conn->error); return []; }
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
        $sql .= " AND (ts.jenis_sampah LIKE ? OR ttp.pembeli LIKE ? OR ttp.no_transaksi_jual LIKE ?)";
        $search_like = "%" . $search_query . "%";
        $params .= "sss";
        $values[] = $search_like;
        $values[] = $search_like;
        $values[] = $search_like;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === FALSE) { error_log("Error preparing getRingkasanPenjualan for export: " . $conn->error); return []; }
    $stmt->bind_param($params, ...$values);
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    $stmt->close();
    return $data;
}

function getDaftarSaldoNasabah($conn, $search_query = '') { // No sorting here, assumed handled in main script if needed
    $data = [];
    $sql = "SELECT
                tn.no_rekening,
                tn.nama_nasabah,
                COALESCE(SUM(CASE WHEN ttn.tipe_mutasi = 'masuk' THEN ttn.jumlah_mutasi ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN ttn.tipe_mutasi = 'keluar' THEN ttn.jumlah_mutasi ELSE 0 END), 0) AS saldo_current
            FROM `tb_nasabah` tn
            LEFT JOIN `tb_tabungan_nasabah` ttn ON tn.no_rekening = ttn.no_rekening
            WHERE tn.role = 'nasabah'";
    $params = "";
    $values = [];

    if (!empty($search_query)) {
        $sql .= " AND (tn.nama_nasabah LIKE ? OR tn.no_rekening LIKE ?)";
        $search_param = "%" . $search_query . "%";
        $params .= "ss";
        $values[] = $search_param;
        $values[] = $search_param;
    }
    $sql .= " GROUP BY tn.no_rekening, tn.nama_nasabah ORDER BY tn.nama_nasabah ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === FALSE) { error_log("Error preparing getDaftarSaldoNasabah for export: " . $conn->error); return []; }
    if (!empty($values)) { $stmt->bind_param($params, ...$values); }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    $stmt->close();
    return $data;
}

function getHistoriMutasiNasabah($conn, $no_rekening, $start_date, $end_date) {
    $data = [];
    $sql = "SELECT
                tanggal_mutasi,
                tipe_mutasi,
                jumlah_mutasi,
                keterangan
            FROM `tb_tabungan_nasabah`
            WHERE no_rekening = ? AND tanggal_mutasi BETWEEN ? AND ?
            ORDER BY tanggal_mutasi ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === FALSE) { error_log("Error preparing getHistoriMutasiNasabah for export: " . $conn->error); return []; }
    $stmt->bind_param("sss", $no_rekening, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    $stmt->close();
    return $data;
}
// --- Akhir Fungsi Laporan ---

// Common parameters for filters and search queries
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_jenis_sampah = $_GET['jenis_sampah'] ?? '';
$search_query_setoran = $_GET['search_setoran'] ?? ''; // For setoran report search
$search_query_penjualan = $_GET['search_penjualan'] ?? ''; // For penjualan report search
$search_query_nasabah_saldo = $_GET['search'] ?? ''; // For overall saldo report search

// --- Main Export Logic ---
$report_type = $_GET['type'] ?? '';
$export_format = $_GET['format'] ?? '';

if ($report_type === 'setoran') {
    $report_data = getRingkasanSetoran($conn, $start_date, $end_date, $filter_jenis_sampah, $search_query_setoran);
    $report_title = 'Laporan Ringkasan Setoran Sampah';
    $file_prefix = 'Laporan_Setoran';
    $headers = ['Jenis Sampah', 'Total Berat (KG)', 'Total Nilai Sampah (Rp)', 'Total Masuk Nasabah (Rp)', 'Total Operasional (Rp)'];

    if ($export_format === 'excel') {
        exportToExcel($report_data, $report_title, $headers, $file_prefix, $start_date, $end_date, $filter_jenis_sampah);
    } elseif ($export_format === 'pdf') {
        exportToPdf($report_data, $report_title, $headers, $file_prefix, $start_date, $end_date, $filter_jenis_sampah, 'setoran');
    }
} elseif ($report_type === 'penjualan') {
    $report_data = getRingkasanPenjualan($conn, $start_date, $end_date, $filter_jenis_sampah, $search_query_penjualan);
    $report_title = 'Laporan Ringkasan Penjualan Sampah';
    $file_prefix = 'Laporan_Penjualan';
    $headers = ['Jenis Sampah', 'Total Berat Terjual (KG)', 'Total Penjualan (Rp)'];

    if ($export_format === 'excel') {
        exportToExcel($report_data, $report_title, $headers, $file_prefix, $start_date, $end_date, $filter_jenis_sampah);
    } elseif ($export_format === 'pdf') {
        exportToPdf($report_data, $report_title, $headers, $file_prefix, $start_date, $end_date, $filter_jenis_sampah, 'penjualan');
    }
} elseif ($report_type === 'saldo') {
    $report_data = getDaftarSaldoNasabah($conn, $search_query_nasabah_saldo); // Sort handled in getDaftarSaldoNasabah
    $report_title = 'Daftar Saldo Nasabah';
    $file_prefix = 'Daftar_Saldo_Nasabah';
    $headers = ['No. Rekening', 'Nama Nasabah', 'Saldo Aktif (Rp)'];

    if ($export_format === 'excel') {
        exportToExcel($report_data, $report_title, $headers, $file_prefix, null, null, null, $search_query_nasabah_saldo);
    } elseif ($export_format === 'pdf') {
        exportToPdf($report_data, $report_title, $headers, $file_prefix, null, null, null, 'saldo', $search_query_nasabah_saldo);
    }
} elseif ($report_type === 'saldo_individual' && isset($_GET['nasabah_ids'])) {
    $selected_nasabah_ids = $_GET['nasabah_ids']; // This will be an array of No Rekening
    
    // Create a multi-sheet Excel or multi-page PDF
    if ($export_format === 'excel') {
        exportIndividualSaldoToExcel($conn, $selected_nasabah_ids, $start_date, $end_date);
    } elseif ($export_format === 'pdf') {
        exportIndividualSaldoToPdf($conn, $selected_nasabah_ids, $start_date, $end_date);
    }
} else {
    die("Tipe laporan atau format ekspor tidak valid.");
}

$conn->close();
exit();


// --- EXCEL Export Function (using PHPSpreadsheet) ---
function exportToExcel($data, $title, $headers, $file_prefix, $start_date = null, $end_date = null, $filter_jenis_sampah = null, $search_query = null) {
    global $conn; // Access the global connection for fetching jenis_sampah name

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Determine sheet title
    $sheet_title_base = '';
    if (strpos($title, 'Setoran') !== false) $sheet_title_base = 'Setoran';
    else if (strpos($title, 'Penjualan') !== false) $sheet_title_base = 'Penjualan';
    else if (strpos($title, 'Saldo') !== false) $sheet_title_base = 'Saldo';

    $current_sheet_title = $sheet_title_base;
    $max_title_length = 31;

    if (!empty($filter_jenis_sampah)) {
        $jenis_sampah_name = '';
        $sql_get_jenis_name = "SELECT jenis_sampah FROM tb_sampah WHERE kode_sampah = ?";
        $stmt_get_jenis = $conn->prepare($sql_get_jenis_name);
        if ($stmt_get_jenis) {
            $stmt_get_jenis->bind_param("s", $filter_jenis_sampah);
            $stmt_get_jenis->execute();
            $result_jenis = $stmt_get_jenis->get_result();
            if ($row_jenis = $result_jenis->fetch_assoc()) {
                $jenis_sampah_name = $row_jenis['jenis_sampah'];
            }
            $stmt_get_jenis->close();
        }
        if (!empty($jenis_sampah_name)) {
            $suffix = ' (' . $jenis_sampah_name . ')';
            $current_sheet_title .= mb_substr($suffix, 0, $max_title_length - mb_strlen($current_sheet_title, 'UTF-8'), 'UTF-8');
        }
    } elseif (!empty($search_query)) {
        $suffix = ' (' . $search_query . ')';
        $current_sheet_title .= mb_substr($suffix, 0, $max_title_length - mb_strlen($current_sheet_title, 'UTF-8'), 'UTF-8');
    }
    $current_sheet_title = mb_substr($current_sheet_title, 0, $max_title_length, 'UTF-8');
    $sheet->setTitle($current_sheet_title);


    // --- Header Laporan ---
    $sheet->setCellValue('A1', $title);
    $sheet->mergeCells('A1:' . chr(64 + count($headers)) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowNum = 3;
    if ($start_date && $end_date) {
        $sheet->setCellValue('A' . $rowNum, 'Periode: ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)));
        $rowNum++;
    }
    if (!empty($filter_jenis_sampah)) {
         $jenis_sampah_name = '';
        $sql_get_jenis_name = "SELECT jenis_sampah FROM tb_sampah WHERE kode_sampah = ?";
        $stmt_get_jenis = $conn->prepare($sql_get_jenis_name);
        if ($stmt_get_jenis) {
            $stmt_get_jenis->bind_param("s", $filter_jenis_sampah);
            $stmt_get_jenis->execute();
            $result_jenis = $stmt_get_jenis->get_result();
            if ($row_jenis = $result_jenis->fetch_assoc()) {
                $jenis_sampah_name = $row_jenis['jenis_sampah'];
            }
            $stmt_get_jenis->close();
        }
        $sheet->setCellValue('A' . $rowNum, 'Jenis Sampah: ' . (empty($jenis_sampah_name) ? $filter_jenis_sampah : $jenis_sampah_name));
        $rowNum++;
    }
    if (!empty($search_query)) {
        $sheet->setCellValue('A' . $rowNum, 'Pencarian: ' . $search_query);
        $rowNum++;
    }
    $sheet->setCellValue('A' . $rowNum, 'Tanggal Cetak: ' . date('d-m-Y H:i:s'));
    $sheet->getStyle('A3:A' . $rowNum)->getFont()->setBold(true);

    $rowNum += 2; // Move to start of table headers

    // --- Header Tabel ---
    $sheet->fromArray($headers, NULL, 'A' . $rowNum);

    // Styling Header Tabel
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF3B82F6']], // Tailwind blue-500
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    $sheet->getStyle('A' . $rowNum . ':' . chr(64 + count($headers)) . $rowNum)->applyFromArray($headerStyle);
    
    $rowNum++; // Pindah ke baris berikutnya untuk data

    // --- Data Tabel ---
    foreach ($data as $data_row) {
        $col_index = 0;
        foreach ($headers as $header) {
            $cellValue = '';
            switch ($header) {
                case 'Jenis Sampah': $cellValue = $data_row['jenis_sampah'] ?? ''; break;
                case 'Total Berat (KG)': $cellValue = $data_row['total_berat_kg'] ?? ''; break;
                case 'Total Nilai Sampah (Rp)': $cellValue = $data_row['total_nilai_sampah'] ?? ''; break;
                case 'Total Masuk Nasabah (Rp)': $cellValue = $data_row['total_masuk_nasabah'] ?? ''; break;
                case 'Total Operasional (Rp)': $cellValue = $data_row['total_operasional'] ?? ''; break;
                case 'Total Berat Terjual (KG)': $cellValue = $data_row['total_berat_jual'] ?? ''; break;
                case 'Total Penjualan (Rp)': $cellValue = $data_row['total_penjualan_rupiah'] ?? ''; break;
                case 'No. Rekening': $cellValue = $data_row['no_rekening'] ?? ''; break;
                case 'Nama Nasabah': $cellValue = $data_row['nama_nasabah'] ?? ''; break;
                case 'Saldo Aktif (Rp)': $cellValue = $data_row['saldo_current'] ?? ''; break;
                default: $cellValue = ''; break;
            }
            $sheet->setCellValueByColumnAndRow($col_index + 1, $rowNum, $cellValue);
            
            // Apply number format for currency/numeric columns
            if (strpos($header, '(KG)') !== false || strpos($header, '(Rp)') !== false) {
                $sheet->getStyleByColumnAndRow($col_index + 1, $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $col_index++;
        }
        $rowNum++;
    }

    // Add totals row if applicable
    if (strpos($title, 'Setoran') !== false) { // Assuming title indicates report type
        $total_setoran = array_sum(array_column($data, 'total_nilai_sampah'));
        $total_nasabah_share = array_sum(array_column($data, 'total_masuk_nasabah'));
        $total_operasional = array_sum(array_column($data, 'total_operasional'));
        
        $sheet->setCellValue('A' . $rowNum, 'TOTAL KESELURUHAN');
        $sheet->mergeCells('A' . $rowNum . ':' . chr(64 + count($headers) - 3) . $rowNum); // Merge for total label, adjust based on columns
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $sheet->setCellValue(chr(64 + count($headers) - 2) . $rowNum, $total_setoran); 
        $sheet->setCellValue(chr(64 + count($headers) - 1) . $rowNum, $total_nasabah_share);
        $sheet->setCellValue(chr(64 + count($headers)) . $rowNum, $total_operasional);

        $sheet->getStyle(chr(64 + count($headers) - 2) . $rowNum . ':' . chr(64 + count($headers)) . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        $sheet->getStyle('A' . $rowNum . ':' . chr(64 + count($headers)) . $rowNum)->getFont()->setBold(true);

    } elseif (strpos($title, 'Penjualan') !== false) {
        $total_penjualan_rupiah = array_sum(array_column($data, 'total_penjualan_rupiah'));
        $sheet->setCellValue('A' . $rowNum, 'TOTAL KESELURUHAN');
        $sheet->mergeCells('A' . $rowNum . ':' . chr(64 + count($headers) - 1) . $rowNum); // Adjust merge based on columns
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue(chr(64 + count($headers)) . $rowNum, $total_penjualan_rupiah);
        $sheet->getStyle(chr(64 + count($headers)) . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        $sheet->getStyle('A' . $rowNum . ':' . chr(64 + count($headers)) . $rowNum)->getFont()->setBold(true);
    }

    // Auto size columns
    foreach (range('A', chr(64 + count($headers))) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Set border for entire table content
    $tableRange = 'A' . (isset($firstDataRow) ? $firstDataRow : 6) . ':' . chr(64 + count($headers)) . ($rowNum - 1);
    if (!empty($data)) {
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
        ]);
    }


    $filename = $file_prefix . '_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// --- PDF Export Function (using Dompdf) ---
function exportToPdf($data, $title, $headers, $file_prefix, $start_date = null, $end_date = null, $filter_jenis_sampah = null, $report_type_key = '', $search_query = null) {
    global $conn; // Access the global connection for fetching jenis_sampah name

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);

    // Re-fetch jenis_sampah name if filter is present
    $jenis_sampah_name = '';
    if (!empty($filter_jenis_sampah)) {
        $sql_get_jenis_name = "SELECT jenis_sampah FROM tb_sampah WHERE kode_sampah = ?";
        $stmt_get_jenis = $conn->prepare($sql_get_jenis_name);
        if ($stmt_get_jenis) {
            $stmt_get_jenis->bind_param("s", $filter_jenis_sampah);
            $stmt_get_jenis->execute();
            $result_jenis = $stmt_get_jenis->get_result();
            if ($row_jenis = $result_jenis->fetch_assoc()) {
                $jenis_sampah_name = $row_jenis['jenis_sampah'];
            }
            $stmt_get_jenis->close();
        }
    }

    // Generate HTML for PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>' . $title . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; font-size: 9pt; }
            h1 { font-size: 16pt; text-align: center; margin-bottom: 20px; }
            .info-section { margin-bottom: 15px; }
            .info-section p { margin: 2px 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
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
            .badge-danger { background-color: #dc3545; }
        </style>
    </head>
    <body>
        <h1>' . strtoupper($title) . '</h1>
        <div class="info-section">';
            if ($start_date && $end_date) {
                $html .= '<p><strong>Periode:</strong> ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)) . '</p>';
            }
            if (!empty($jenis_sampah_name)) {
                $html .= '<p><strong>Jenis Sampah:</strong> ' . htmlspecialchars($jenis_sampah_name) . '</p>';
            }
            if (!empty($search_query)) { // For overall saldo search
                $html .= '<p><strong>Pencarian:</strong> ' . htmlspecialchars($search_query) . '</p>';
            }
            $html .= '<p><strong>Tanggal Cetak:</strong> ' . date('d-m-Y H:i:s') . '</p>
        </div>
        <table>
            <thead>
                <tr>';
                    foreach ($headers as $header) {
                        $html .= '<th>' . htmlspecialchars($header) . '</th>';
                    }
                    $html .= '
                </tr>
            </thead>
            <tbody>';

    if (!empty($data)) {
        foreach ($data as $data_row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $cell_content = '';
                switch ($header) {
                    case 'Jenis Sampah': $cell_content = htmlspecialchars($data_row['jenis_sampah'] ?? ''); break;
                    case 'Total Berat (KG)': $cell_content = number_format($data_row['total_berat_kg'] ?? '', 2, ',', '.'); break;
                    case 'Total Nilai Sampah (Rp)': $cell_content = "Rp " . number_format($data_row['total_nilai_sampah'] ?? '', 2, ',', '.'); break;
                    case 'Total Masuk Nasabah (Rp)': $cell_content = "Rp " . number_format($data_row['total_masuk_nasabah'] ?? '', 2, ',', '.'); break;
                    case 'Total Operasional (Rp)': $cell_content = "Rp " . number_format($data_row['total_operasional'] ?? '', 2, ',', '.'); break;
                    case 'Total Berat Terjual (KG)': $cell_content = number_format($data_row['total_berat_jual'] ?? '', 2, ',', '.'); break;
                    case 'Total Penjualan (Rp)': $cell_content = "Rp " . number_format($data_row['total_penjualan_rupiah'] ?? '', 2, ',', '.'); break;
                    case 'No. Rekening': $cell_content = htmlspecialchars($data_row['no_rekening'] ?? ''); break;
                    case 'Nama Nasabah': $cell_content = htmlspecialchars($data_row['nama_nasabah'] ?? ''); break;
                    case 'Saldo Aktif (Rp)': $cell_content = "Rp " . number_format($data_row['saldo_current'] ?? '', 2, ',', '.'); break;
                    default: $cell_content = ''; break;
                }
                $html .= '<td>' . $cell_content . '</td>';
            }
            $html .= '</tr>';
        }

        // Add Totals Row for PDF
        if ($report_type_key == 'setoran') {
            $total_setoran_val = array_sum(array_column($data, 'total_nilai_sampah'));
            $total_nasabah_share_val = array_sum(array_column($data, 'total_masuk_nasabah'));
            $total_operasional_val = array_sum(array_column($data, 'total_operasional'));
            $html .= '<tr>
                        <th colspan="2" class="text-right">TOTAL KESELURUHAN</th>
                        <th>Rp ' . number_format($total_setoran_val, 2, ',', '.') . '</th>
                        <th>Rp ' . number_format($total_nasabah_share_val, 2, ',', '.') . '</th>
                        <th>Rp ' . number_format($total_operasional_val, 2, ',', '.') . '</th>
                      </tr>';
        } elseif ($report_type_key == 'penjualan') {
            $total_penjualan_rupiah_val = array_sum(array_column($data, 'total_penjualan_rupiah'));
            $html .= '<tr>
                        <th colspan="2" class="text-right">TOTAL KESELURUHAN</th>
                        <th>Rp ' . number_format($total_penjualan_rupiah_val, 2, ',', '.') . '</th>
                      </tr>';
        }

    } else {
        $html .= '<tr><td colspan="' . count($headers) . '" class="text-center">Tidak ada data laporan.</td></tr>';
    }

    $html .= '
            </tbody>
        </table>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    // Set paper size and orientation based on report type
    if ($report_type_key == 'setoran') {
        $dompdf->setPaper('A4', 'landscape'); // Setoran has more columns, better in landscape
    } else {
        $dompdf->setPaper('A4', 'portrait');
    }
    
    $dompdf->render();

    $filename = $file_prefix . '_' . date('Ymd_His') . '.pdf';
    $dompdf->stream($filename, ["Attachment" => true]);
    exit();
}


// --- Individual Saldo Export to Excel (Multi-sheet) ---
function exportIndividualSaldoToExcel($conn, $nasabah_ids, $start_date, $end_date) {
    $spreadsheet = new Spreadsheet();
    $firstSheet = true; // Flag to manage the first sheet

    foreach ($nasabah_ids as $index => $no_rekening) {
        $no_rekening_safe = $conn->real_escape_string($no_rekening);

        // Fetch nasabah info
        $sql_nasabah_info = "SELECT nama_nasabah FROM tb_nasabah WHERE no_rekening = ?";
        $stmt_nasabah_info = $conn->prepare($sql_nasabah_info);
        $stmt_nasabah_info->bind_param("s", $no_rekening_safe);
        $stmt_nasabah_info->execute();
        $nasabah_info = $stmt_nasabah_info->get_result()->fetch_assoc();
        $nama_nasabah = $nasabah_info['nama_nasabah'] ?? 'Tidak Ditemukan';
        $stmt_nasabah_info->close();

        // Fetch mutation history
        $mutasi_data = getHistoriMutasiNasabah($conn, $no_rekening_safe, $start_date, $end_date);
        
        // Fetch current balance
        $current_balance = 0;
        $sql_current_balance = "SELECT
                                    COALESCE(SUM(CASE WHEN tipe_mutasi = 'masuk' THEN jumlah_mutasi ELSE 0 END), 0) -
                                    COALESCE(SUM(CASE WHEN tipe_mutasi = 'keluar' THEN ttn.jumlah_mutasi ELSE 0 END), 0) AS current_balance
                                FROM tb_tabungan_nasabah ttn
                                WHERE no_rekening = ?";
        $stmt_current_balance = $conn->prepare($sql_current_balance);
        $stmt_current_balance->bind_param("s", $no_rekening_safe);
        $stmt_current_balance->execute();
        $result_current_balance = $stmt_current_balance->get_result();
        if ($result_current_balance && $row = $result_current_balance->fetch_assoc()) {
            $current_balance = $row['current_balance'] ?? 0;
        }
        $stmt_current_balance->close();


        // Create a new sheet for each nasabah (or use the first one)
        if ($firstSheet) {
            $sheet = $spreadsheet->getActiveSheet();
            $firstSheet = false;
        } else {
            $sheet = $spreadsheet->createSheet();
        }
        $sheet->setTitle(mb_substr($nama_nasabah, 0, 31, 'UTF-8')); // Max 31 chars for sheet name


        // Write content to sheet
        $sheet->setCellValue('A1', 'Laporan Mutasi Tabungan Nasabah');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Nama Nasabah:');
        $sheet->setCellValue('B3', $nama_nasabah);
        $sheet->setCellValue('A4', 'No. Rekening:');
        $sheet->setCellValue('B4', $no_rekening);
        $sheet->setCellValue('A5', 'Periode Laporan:');
        $sheet->setCellValue('B5', date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)));
        $sheet->setCellValue('A6', 'Saldo Terkini:');
        $sheet->setCellValue('B6', $current_balance);
        $sheet->getStyle('B6')->getNumberFormat()->setFormatCode('"Rp "#,##0.00'); // Apply currency format
        $sheet->getStyle('A3:A6')->getFont()->setBold(true);

        $sheet->setCellValue('A8', 'Tanggal Mutasi');
        $sheet->setCellValue('B8', 'Tipe Mutasi');
        $sheet->setCellValue('C8', 'Jumlah Mutasi (Rp)');
        $sheet->setCellValue('D8', 'Keterangan');
        $sheet->getStyle('A8:D8')->getFont()->setBold(true)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCCCCCC']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        ]);

        $row_index = 9;
        foreach ($mutasi_data as $mutasi) {
            $sheet->setCellValue('A' . $row_index, $mutasi['tanggal_mutasi']);
            $sheet->setCellValue('B' . $row_index, ucfirst($mutasi['tipe_mutasi']));
            $sheet->setCellValue('C' . $row_index, floatval($mutasi['jumlah_mutasi']));
            $sheet->getStyle('C' . $row_index)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
            $sheet->setCellValue('D' . $row_index, $mutasi['keterangan']);
            $row_index++;
        }
        // Apply borders to data rows
        if (!empty($mutasi_data)) {
            $sheet->getStyle('A9:D' . ($row_index - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            ]);
        }
        
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // Set active sheet back to the first one
    $spreadsheet->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Laporan_Saldo_Nasabah_Individual_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

// --- Individual Saldo Export to PDF (Multi-page) ---
function exportIndividualSaldoToPdf($conn, $nasabah_ids, $start_date, $end_date) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);

    $full_html = '';
    foreach ($nasabah_ids as $index => $no_rekening) {
        $no_rekening_safe = $conn->real_escape_string($no_rekening);

        // Fetch nasabah info
        $sql_nasabah_info = "SELECT nama_nasabah FROM tb_nasabah WHERE no_rekening = ?";
        $stmt_nasabah_info = $conn->prepare($sql_nasabah_info);
        $stmt_nasabah_info->bind_param("s", $no_rekening_safe);
        $stmt_nasabah_info->execute();
        $nasabah_info = $stmt_nasabah_info->get_result()->fetch_assoc();
        $nama_nasabah = $nasabah_info['nama_nasabah'] ?? 'Tidak Ditemukan';
        $stmt_nasabah_info->close();

        // Fetch mutation history
        $mutasi_data = getHistoriMutasiNasabah($conn, $no_rekening_safe, $start_date, $end_date);
        
        // Fetch current balance
        $current_balance = 0;
        $sql_current_balance = "SELECT
                                    COALESCE(SUM(CASE WHEN tipe_mutasi = 'masuk' THEN jumlah_mutasi ELSE 0 END), 0) -
                                    COALESCE(SUM(CASE WHEN tipe_mutasi = 'keluar' THEN ttn.jumlah_mutasi ELSE 0 END), 0) AS current_balance
                                FROM tb_tabungan_nasabah ttn
                                WHERE no_rekening = ?";
        $stmt_current_balance = $conn->prepare($sql_current_balance);
        $stmt_current_balance->bind_param("s", $no_rekening_safe);
        $stmt_current_balance->execute();
        $result_current_balance = $stmt_current_balance->get_result();
        if ($result_current_balance && $row = $result_current_balance->fetch_assoc()) {
            $current_balance = $row['current_balance'] ?? 0;
        }
        $stmt_current_balance->close();

        $html_content = '
        <h2 style="text-align:center;">Laporan Mutasi Tabungan Nasabah</h2>
        <p><b>Nama Nasabah:</b> ' . htmlspecialchars($nama_nasabah) . '</p>
        <p><b>No. Rekening:</b> ' . htmlspecialchars($no_rekening) . '</p>
        <p><b>Periode Laporan:</b> ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)) . '</p>
        <p><b>Saldo Terkini:</b> Rp ' . number_format($current_balance, 2, ',', '.') . '</p>
        <br>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th><b>Tanggal Mutasi</b></th>
                    <th><b>Tipe Mutasi</b></th>
                    <th><b>Jumlah Mutasi (Rp)</b></th>
                    <th><b>Keterangan</b></th>
                </tr>
            </thead>
            <tbody>';

        if (!empty($mutasi_data)) {
            foreach ($mutasi_data as $mutasi) {
                $html_content .= '<tr>';
                $html_content .= '<td>' . htmlspecialchars($mutasi['tanggal_mutasi']) . '</td>';
                $html_content .= '<td>' . ucfirst($mutasi['tipe_mutasi']) . '</td>';
                $html_content .= '<td style="text-align: right;">Rp ' . number_format($mutasi['jumlah_mutasi'], 2, ',', '.') . '</td>';
                $html_content .= '<td>' . htmlspecialchars($mutasi['keterangan']) . '</td>';
                $html_content .= '</tr>';
            }
        } else {
            $html_content .= '<tr><td colspan="4" style="text-align: center;">Tidak ada data mutasi untuk periode ini.</td></tr>';
        }
        
        $html_content .= '
            </tbody>
        </table>';

        $full_html .= $html_content;

        // Add page break if it's not the last nasabah
        if ($index < count($nasabah_ids) - 1) {
            $full_html .= '<div style="page-break-after: always;"></div>';
        }
    }

    $final_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laporan Saldo Nasabah Individual</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; font-size: 9pt; }
            h2 { font-size: 14pt; text-align: center; margin-bottom: 15px; }
            p { margin: 2px 0; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ccc; padding: 6px; }
            th { background-color: #f2f2f2; font-weight: bold; text-align: left; }
            .text-right { text-align: right; }
            /* Dompdf does not support display: inline-block for page-break-after well */
            /* Ensure table and content fit within page width, default is portrait */
        </style>
    </head>
    <body>' . $full_html . '</body></html>';


    $dompdf->loadHtml($final_html);
    $dompdf->setPaper('A4', 'portrait'); // Individual reports likely fit portrait
    $dompdf->render();

    $filename = 'Laporan_Saldo_Nasabah_Individual_' . date('Ymd_His') . '.pdf';
    $dompdf->stream($filename, ["Attachment" => true]);
    exit();
}

?>