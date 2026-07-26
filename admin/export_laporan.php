<?php
// admin/export_laporan.php
require_once '../vendor/autoload.php';
require_once '../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use Dompdf\Options;

// Matikan output buffering agar error kelihatan jika ada, 
// tapi kita clean nanti sebelum download
ob_start();
session_start();

// Cek Login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id_tps_session = $_SESSION['id_tps'];

// --- AMBIL PARAMETER MENGGUNAKAN $_REQUEST (Bisa GET atau POST) ---
// Ini perbaikan utamanya agar tombol form (POST) dan link (GET) dua-duanya jalan
$report_type = $_REQUEST['type'] ?? '';
$export_format = $_REQUEST['format'] ?? '';
$start_date = $_REQUEST['start_date'] ?? date('Y-m-01');
$end_date = $_REQUEST['end_date'] ?? date('Y-m-d');

// Perbaikan nama variabel agar cocok dengan laporan.php (filter_jenis_sampah)
$filter_jenis_sampah = $_REQUEST['filter_jenis_sampah'] ?? ($_REQUEST['jenis_sampah'] ?? '');

$filter_rt = $_REQUEST['filter_rt'] ?? '';
$filter_rw = $_REQUEST['filter_rw'] ?? '';

// Handle Array untuk checkbox nasabah
$no_rekening_nasabah = isset($_REQUEST['no_rekening']) ? $_REQUEST['no_rekening'] : [];
// Jika cuma satu (dari link GET), ubah jadi array
if (!is_array($no_rekening_nasabah) && !empty($no_rekening_nasabah)) {
    $no_rekening_nasabah = [$no_rekening_nasabah];
} else if (isset($_REQUEST['selected_rekening'])) {
    // Handle jika nama inputnya selected_rekening[]
    $no_rekening_nasabah = $_REQUEST['selected_rekening'];
}

// --- HELPER: Config ---
function get_config_value($conn_obj, $setting_name, $id_tps, $default_value = null) {
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
    $stmt = $conn_obj->prepare($sql);
    if ($stmt === FALSE) return $default_value;
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

$persen_nasabah_config = get_config_value($conn, 'persen_nasabah', $id_tps_session, 60.00);
$persen_tps_config     = get_config_value($conn, 'persen_tps', $id_tps_session, 20.00); 
$persen_pengepul_config= get_config_value($conn, 'persen_pengepul', $id_tps_session, 20.00); 

// --- QUERY FUNCTIONS ---
function getRingkasanSetoran($conn, $id_tps, $start_date, $end_date, $filter_jenis_sampah = '') {
    $data = [];
    $sql = "SELECT ts.jenis_sampah, SUM(tss.berat_kg) AS total_berat_kg, SUM(tss.total) AS total_nilai_sampah,
            SUM(tss.tabungan_nasabah) AS total_masuk_nasabah, SUM(tss.pos_penimbangan) AS total_operasional, SUM(tss.tps3r) AS total_tps
            FROM `tb_setorsampah` tss
            JOIN `tb_sampah` ts ON tss.kode_sampah = ts.kode_sampah AND tss.id_tps = ts.id_tps
            WHERE tss.tanggal_pengambilan BETWEEN ? AND ? AND tss.status_setoran = 'final' AND tss.id_tps = ?";
    $params = "ssi";
    $values = [$start_date, $end_date, $id_tps];
    if (!empty($filter_jenis_sampah)) {
        $sql .= " AND tss.kode_sampah = ?";
        $params .= "s";
        $values[] = $filter_jenis_sampah;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$values);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
    return $data;
}

function getRingkasanPenjualan($conn, $id_tps, $start_date, $end_date, $filter_jenis_sampah = '') {
    $data = [];
    $sql = "SELECT ts.jenis_sampah, SUM(tp.berat_kg) AS total_berat_jual, SUM(tp.total_penjualan) AS total_penjualan_rupiah
            FROM `tb_transaksi_penjualan` tp
            JOIN `tb_sampah` ts ON tp.kode_sampah = ts.kode_sampah AND tp.id_tps = ts.id_tps
            WHERE tp.tanggal_jual BETWEEN ? AND ? AND tp.id_tps = ?";
    $params = "ssi";
    $values = [$start_date, $end_date, $id_tps];
    if (!empty($filter_jenis_sampah)) {
        $sql .= " AND tp.kode_sampah = ?";
        $params .= "s";
        $values[] = $filter_jenis_sampah;
    }
    $sql .= " GROUP BY ts.jenis_sampah ORDER BY ts.jenis_sampah ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$values);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
    return $data;
}

function getLaporanPencairan($conn, $id_tps, $start, $end) {
    $data = [];
    $sql = "SELECT pd.*, n.nama_nasabah FROM tb_pencairan_dana pd
            JOIN tb_nasabah n ON pd.no_rekening = n.no_rekening
            WHERE pd.id_tps = ? AND pd.status = 'diterima' AND pd.tanggal_transfer BETWEEN ? AND ?
            ORDER BY pd.tanggal_transfer DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $id_tps, $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
    return $data;
}

function getDaftarSaldoNasabah($conn, $id_tps, $filter_rt = '', $filter_rw = '') {
    $data = [];
    $sql = "SELECT tn.no_rekening, tn.nama_nasabah, tn.rt, tn.rw,
            (COALESCE(SUM(CASE WHEN ttn.tipe_mutasi = 'masuk' THEN ttn.jumlah_mutasi ELSE 0 END), 0) -
             COALESCE(SUM(CASE WHEN ttn.tipe_mutasi = 'keluar' THEN ttn.jumlah_mutasi ELSE 0 END), 0)) AS saldo_current
            FROM `tb_nasabah` tn
            LEFT JOIN `tb_tabungan_nasabah` ttn ON tn.no_rekening = ttn.no_rekening AND ttn.id_tps = tn.id_tps
            WHERE tn.role = 'nasabah' AND tn.id_tps = ?";
    $params = "i";
    $values = [$id_tps];
    
    if (!empty($filter_rt)) { $sql .= " AND tn.rt = ?"; $params .= "s"; $values[] = $filter_rt; }
    if (!empty($filter_rw)) { $sql .= " AND tn.rw = ?"; $params .= "s"; $values[] = $filter_rw; }
    
    $sql .= " GROUP BY tn.no_rekening ORDER BY tn.nama_nasabah ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$values);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
    return $data;
}

function getDetailSetoranNasabah($conn, $no_rekening, $id_tps, $start_date, $end_date) {
    $data = [];
    $sql = "SELECT tanggal_pengambilan AS tanggal, jenis_sampah AS jenis_barang, berat_kg, harga_per_kg, total,
            tabungan_nasabah AS nominal_70_persen, pos_penimbangan AS pos_penimbangan_5_persen, tps3r AS tps3r_25_persen
            FROM `tb_setorsampah`
            WHERE no_rekening = ? AND id_tps = ? AND tanggal_pengambilan BETWEEN ? AND ? AND status_setoran = 'final'
            ORDER BY tanggal_pengambilan ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siss", $no_rekening, $id_tps, $start_date, $end_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
    return $data;
}

function getNasabahInfo($conn, $no_rekening, $id_tps) {
    $sql = "SELECT nama_nasabah, rt, rw FROM tb_nasabah WHERE no_rekening = ? AND id_tps = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $no_rekening, $id_tps);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- LOGIKA PEMILIHAN LAPORAN ---
$report_data = [];
$report_title = '';
$file_prefix = '';
$headers = [];
$is_detail_report = false;
$nasabah_info = null;

// 1. RINGKASAN SETORAN (Support 'ringkasan' atau 'ringkasan_setoran')
if ($report_type === 'ringkasan_setoran' || $report_type === 'ringkasan') {
    $report_data = getRingkasanSetoran($conn, $id_tps_session, $start_date, $end_date, $filter_jenis_sampah);
    $report_title = 'Laporan Ringkasan Setoran Sampah';
    $file_prefix = 'Ringkasan_Setoran';
    $headers = [
        'Jenis Sampah', 'Total Berat (KG)', 'Total Nilai Sampah (Rp)', 
        'Nominal Nasabah (' . number_format($persen_nasabah_config, 0) . '%)',
        'Bank Sampah (' . number_format($persen_tps_config, 0) . '%)',
        'Pos Penimbang (' . number_format($persen_pengepul_config, 0) . '%)'
    ];

// 2. RINGKASAN PENJUALAN
} elseif ($report_type === 'ringkasan_penjualan') {
    $report_data = getRingkasanPenjualan($conn, $id_tps_session, $start_date, $end_date, $filter_jenis_sampah);
    $report_title = 'Laporan Ringkasan Penjualan Sampah';
    $file_prefix = 'Ringkasan_Penjualan';
    $headers = ['Jenis Sampah', 'Total Berat Terjual (KG)', 'Total Penjualan (Rp)'];

// 3. KEUANGAN / PENCAIRAN
} elseif ($report_type === 'keuangan') {
    $report_data = getLaporanPencairan($conn, $id_tps_session, $start_date, $end_date);
    $report_title = 'Laporan Keuangan & Pencairan';
    $file_prefix = 'Laporan_Pencairan';
    $headers = ['Tanggal', 'No. Rekening', 'Nama Nasabah', 'Keterangan', 'Jumlah Cair (Rp)'];

// 4. DAFTAR SALDO NASABAH (Checklist massal tanpa detail)
} elseif ($report_type === 'daftar_nasabah') {
    $report_data = getDaftarSaldoNasabah($conn, $id_tps_session, $filter_rt, $filter_rw);
    $report_title = 'Daftar Saldo Nasabah';
    $file_prefix = 'Daftar_Saldo';
    $headers = ['No. Rekening', 'Nama Nasabah', 'RT', 'RW', 'Saldo Aktif (Rp)'];

// 5. DETAIL TRANSAKSI NASABAH (Buku Tabungan - Checklist massal atau single)
} elseif ($report_type === 'detail_nasabah') {
    // Jika checklist kosong, tapi filter RT/RW ada, ambil semua di RT/RW itu (Optional logic)
    // Tapi biasanya form mengirim array kosong jika tidak ada yang dicentang.
    // Kita asumsikan user harus mencentang.
    
    if (empty($no_rekening_nasabah)) {
        die("Error: Tidak ada nasabah yang dipilih untuk dicetak detailnya.");
    }

    $report_data = [];
    foreach ($no_rekening_nasabah as $rekening) {
        $report_data[$rekening] = getDetailSetoranNasabah($conn, $rekening, $id_tps_session, $start_date, $end_date);
    }
    
    $report_title = 'Laporan Detail Transaksi Nasabah';
    $file_prefix = 'Buku_Tabungan_Nasabah';
    $headers = [
        'Tanggal', 'Jenis Barang', 'Berat (KG)', 'Harga/KG (Rp)', 'Total (Rp)',
        'UNTUK NASABAH (' . number_format($persen_nasabah_config, 0) . '%)',
        'UNTUK BANK SAMPAH (' . number_format($persen_tps_config, 0) . '%)',
        'UNTUK POS PENIMBANGAN (' . number_format($persen_pengepul_config, 0) . '%)'
    ];
    $is_detail_report = true;

} else {
    die("Tipe laporan tidak valid atau parameter kurang. Debug: Type=" . htmlspecialchars($report_type));
}

// --- EKSEKUSI DOWNLOAD ---
if ($export_format === 'excel') {
    exportToExcel($report_data, $report_title, $headers, $file_prefix, $start_date, $end_date, $is_detail_report, $report_type);
} elseif ($export_format === 'pdf') {
    exportToPdf($report_data, $report_title, $headers, $file_prefix, $start_date, $end_date, $is_detail_report, $report_type);
} else {
    die("Format export tidak valid.");
}

$conn->close();
exit();

// =================================================================================
// FUNGSI EXPORT EXCEL
// =================================================================================
function exportToExcel($data, $title, $headers, $file_prefix, $start_date, $end_date, $is_detail_report, $report_type) {
    global $conn, $id_tps_session;

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($file_prefix, 0, 31));

    $rowNum = 1;

    // Header Judul
    $sheet->setCellValue('A' . $rowNum, strtoupper($title));
    $lastCol = chr(64 + count($headers));
    $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum++;

    $sheet->setCellValue('A' . $rowNum, 'Periode: ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)));
    $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum += 2;

    // Style Umum
    $styleHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    $styleData = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];

    if ($is_detail_report) {
        // LOOP PER NASABAH
        foreach ($data as $rekening => $rows) {
            $info = getNasabahInfo($conn, $rekening, $id_tps_session);
            
            $sheet->setCellValue('A' . $rowNum, "Nama: " . ($info['nama_nasabah'] ?? '-'));
            $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
            $rowNum++;
            $sheet->setCellValue('A' . $rowNum, "Rekening: " . $rekening . " | RT/RW: " . ($info['rt']??'-')."/".($info['rw']??'-'));
            $rowNum++;

            // Header Tabel Nasabah
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $rowNum, $h);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $col++;
            }
            $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray($styleHeader);
            $rowNum++;

            if (empty($rows)) {
                $sheet->setCellValue('A' . $rowNum, "Tidak ada transaksi.");
                $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
                $sheet->getStyle('A'.$rowNum)->applyFromArray($styleData);
                $rowNum += 2;
                continue;
            }

            $startRow = $rowNum;
            foreach ($rows as $r) {
                $sheet->setCellValue('A' . $rowNum, $r['tanggal']);
                $sheet->setCellValue('B' . $rowNum, $r['jenis_barang']);
                $sheet->setCellValue('C' . $rowNum, $r['berat_kg']);
                $sheet->setCellValue('D' . $rowNum, $r['harga_per_kg']);
                $sheet->setCellValue('E' . $rowNum, $r['total']);
                $sheet->setCellValue('F' . $rowNum, $r['nominal_70_persen']); // Bank Sampah
                $sheet->setCellValue('G' . $rowNum, $r['pos_penimbangan_5_persen']);
                $sheet->setCellValue('H' . $rowNum, $r['tps3r_25_persen']);// Pos
                
                // Format Currency
                $sheet->getStyle('D'.$rowNum.':H'.$rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $rowNum++;
            }
            // Border Data
            $sheet->getStyle('A' . $startRow . ':H' . ($rowNum - 1))->applyFromArray($styleData);

            // Subtotal
            $sheet->setCellValue('A' . $rowNum, 'TOTAL');
            $sheet->mergeCells('A' . $rowNum . ':B' . $rowNum);
            $sheet->setCellValue('C' . $rowNum, "=SUM(C$startRow:C".($rowNum-1).")");
            $sheet->setCellValue('E' . $rowNum, "=SUM(E$startRow:E".($rowNum-1).")");
            $sheet->setCellValue('F' . $rowNum, "=SUM(F$startRow:F".($rowNum-1).")");
            $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->getFont()->setBold(true);
            $sheet->getStyle('E' . $rowNum . ':H' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0');
            
            $rowNum += 3; // Spasi
        }

    } else {
        // LAPORAN RINGKASAN / KEUANGAN (TABEL TUNGGAL)
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . $rowNum, $h);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        $sheet->getStyle('A' . $rowNum . ':' . chr(64 + count($headers)-1) . $rowNum)->applyFromArray($styleHeader);
        $rowNum++;

        if (empty($data)) {
            $sheet->setCellValue('A' . $rowNum, "Tidak ada data.");
            $sheet->mergeCells('A' . $rowNum . ':' . chr(64 + count($headers)-1) . $rowNum);
        } else {
            $startRow = $rowNum;
            foreach ($data as $row) {
                $colIndex = 'A';
                
                if ($report_type === 'ringkasan_setoran' || $report_type === 'ringkasan') {
                    $sheet->setCellValue('A' . $rowNum, $row['jenis_sampah']);
                    $sheet->setCellValue('B' . $rowNum, $row['total_berat_kg']);
                    $sheet->setCellValue('C' . $rowNum, $row['total_nilai_sampah']);
                    $sheet->setCellValue('D' . $rowNum, $row['total_masuk_nasabah']);
                    $sheet->setCellValue('E' . $rowNum, $row['total_operasional']); 
                    $sheet->setCellValue('F' . $rowNum, $row['total_tps']);
                    $sheet->getStyle('C'.$rowNum.':F'.$rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0');

                } elseif ($report_type === 'keuangan') {
                    $sheet->setCellValue('A' . $rowNum, $row['tanggal_transfer']);
                    $sheet->setCellValue('B' . $rowNum, $row['no_rekening']);
                    $sheet->setCellValue('C' . $rowNum, $row['nama_nasabah']);
                    $sheet->setCellValue('D' . $rowNum, $row['keterangan']);
                    $sheet->setCellValue('E' . $rowNum, $row['jumlah_cair']);
                    $sheet->getStyle('E'.$rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0');

                } elseif ($report_type === 'ringkasan_penjualan') {
                    $sheet->setCellValue('A' . $rowNum, $row['jenis_sampah']);
                    $sheet->setCellValue('B' . $rowNum, $row['total_berat_jual']);
                    $sheet->setCellValue('C' . $rowNum, $row['total_penjualan_rupiah']);
                    $sheet->getStyle('C'.$rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0');
                } elseif ($report_type === 'daftar_nasabah') {
                    $sheet->setCellValue('A' . $rowNum, $row['no_rekening']);
                    $sheet->setCellValue('B' . $rowNum, $row['nama_nasabah']);
                    $sheet->setCellValue('C' . $rowNum, $row['rt']);
                    $sheet->setCellValue('D' . $rowNum, $row['rw']);
                    $sheet->setCellValue('E' . $rowNum, $row['saldo_current']);
                    $sheet->getStyle('E'.$rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0');
                }

                $rowNum++;
            }
            // Apply border to all data
            $lastColChar = chr(64 + count($headers));
            $sheet->getStyle('A' . $startRow . ':' . $lastColChar . ($rowNum - 1))->applyFromArray($styleData);
        }
    }

    // Output Excel
    ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $file_prefix . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// =================================================================================
// FUNGSI EXPORT PDF
// =================================================================================
function exportToPdf($data, $title, $headers, $file_prefix, $start_date, $end_date, $is_detail_report, $report_type) {
    global $conn, $id_tps_session;
    
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    $html = '<html><head><style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        h2 { text-align: center; margin: 5px 0; color: #333; }
        .meta { text-align: center; font-size: 9pt; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #444; padding: 6px; font-size: 9pt; }
        th { background-color: #4F81BD; color: white; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .sub-header { background-color: #eee; font-weight: bold; }
        .page-break { page-break-before: always; }
        .nasabah-info { margin-bottom: 10px; padding: 10px; background: #f4f4f4; border: 1px solid #ddd; }
    </style></head><body>';

    $html .= '<h2>'.htmlspecialchars($title).'</h2>';
    $html .= '<div class="meta">Periode: '.date('d/m/Y', strtotime($start_date)).' - '.date('d/m/Y', strtotime($end_date)).'</div>';

    if ($is_detail_report) {
        $count = 0;
        foreach ($data as $rekening => $rows) {
            if ($count > 0) $html .= '<div class="page-break"></div>';
            $count++;

            $info = getNasabahInfo($conn, $rekening, $id_tps_session);
            
            $html .= '<div class="nasabah-info">
                        <b>Nama:</b> '.htmlspecialchars($info['nama_nasabah']??'-').'<br>
                        <b>Rekening:</b> '.htmlspecialchars($rekening).'<br>
                        <b>RT/RW:</b> '.htmlspecialchars($info['rt']??'-').'/'.htmlspecialchars($info['rw']??'-').'
                      </div>';

            $html .= '<table><thead><tr>';
            foreach($headers as $h) $html .= '<th>'.htmlspecialchars($h).'</th>';
            $html .= '</tr></thead><tbody>';

            if (empty($rows)) {
                $html .= '<tr><td colspan="'.count($headers).'" class="text-center">Tidak ada transaksi.</td></tr>';
            } else {
                $t_nasabah = 0;
                foreach ($rows as $r) {
                    $html .= '<tr>
                        <td class="text-center">'.date('d/m/y', strtotime($r['tanggal'])).'</td>
                        <td>'.htmlspecialchars($r['jenis_barang']).'</td>
                        <td class="text-right">'.number_format($r['berat_kg'], 2, ',', '.').'</td>
                        <td class="text-right">Rp '.number_format($r['harga_per_kg'], 0, ',', '.').'</td>
                        <td class="text-right">Rp '.number_format($r['total'], 0, ',', '.').'</td>
                        <td class="text-right">Rp '.number_format($r['nominal_70_persen'], 0, ',', '.').'</td>
                        <td class="text-right">Rp '.number_format($r['pos_penimbangan_5_persen'], 0, ',', '.').'</td>
                        <td class="text-right">Rp '.number_format($r['tps3r_25_persen'], 0, ',', '.').'</td>
                        
                    </tr>';
                    $t_nasabah += $r['nominal_70_persen'];
                }
                // Total Row
                $html .= '<tr style="background-color:#FFFFE0; font-weight:bold;">
                            <td colspan="5" class="text-right">TOTAL NASABAH TERIMA</td>
                            <td class="text-right">Rp '.number_format($t_nasabah, 0, ',', '.').'</td>
                            <td colspan="2"></td>
                          </tr>';
            }
            $html .= '</tbody></table>';
        }
    } else {
        // Report Tunggal
        $html .= '<table><thead><tr>';
        foreach($headers as $h) $html .= '<th>'.htmlspecialchars($h).'</th>';
        $html .= '</tr></thead><tbody>';

        if(empty($data)) {
            $html .= '<tr><td colspan="'.count($headers).'" class="text-center">Tidak ada data.</td></tr>';
        } else {
            foreach($data as $row) {
                $html .= '<tr>';
                if ($report_type === 'ringkasan' || $report_type === 'ringkasan_setoran') {
                    $html .= '<td>'.htmlspecialchars($row['jenis_sampah']).'</td>';
                    $html .= '<td class="text-right">'.number_format($row['total_berat_kg'], 2, ',', '.').'</td>';
                    $html .= '<td class="text-right">Rp '.number_format($row['total_nilai_sampah'], 0, ',', '.').'</td>';
                    $html .= '<td class="text-right">Rp '.number_format($row['total_masuk_nasabah'], 0, ',', '.').'</td>';
                    $html .= '<td class="text-right">Rp '.number_format($row['total_operasional'], 0, ',', '.').'</td>';
                    $html .= '<td class="text-right">Rp '.number_format($row['total_tps'], 0, ',', '.').'</td>';
                } elseif ($report_type === 'keuangan') {
                    $html .= '<td>'.date('d/m/Y', strtotime($row['tanggal_transfer'])).'</td>';
                    $html .= '<td>'.htmlspecialchars($row['no_rekening']).'</td>';
                    $html .= '<td>'.htmlspecialchars($row['nama_nasabah']).'</td>';
                    $html .= '<td>'.htmlspecialchars($row['keterangan']).'</td>';
                    $html .= '<td class="text-right">Rp '.number_format($row['jumlah_cair'], 0, ',', '.').'</td>';
                } elseif ($report_type === 'ringkasan_penjualan') {
                     $html .= '<td>'.htmlspecialchars($row['jenis_sampah']).'</td>';
                     $html .= '<td class="text-right">'.number_format($row['total_berat_jual'], 2, ',', '.').'</td>';
                     $html .= '<td class="text-right">Rp '.number_format($row['total_penjualan_rupiah'], 0, ',', '.').'</td>';
                } elseif ($report_type === 'daftar_nasabah') {
                     $html .= '<td>'.htmlspecialchars($row['no_rekening']).'</td>';
                     $html .= '<td>'.htmlspecialchars($row['nama_nasabah']).'</td>';
                     $html .= '<td>'.htmlspecialchars($row['rt']).'</td>';
                     $html .= '<td>'.htmlspecialchars($row['rw']).'</td>';
                     $html .= '<td class="text-right">Rp '.number_format($row['saldo_current'], 0, ',', '.').'</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';
    }

    $html .= '</body></html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', $is_detail_report ? 'landscape' : 'portrait');
    $dompdf->render();
    
    ob_clean();
    $dompdf->stream($file_prefix . "_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
    exit();
}
?>