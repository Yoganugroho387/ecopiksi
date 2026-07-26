<?php
// nasabah/export_mutasi_excel.php
require_once '../vendor/autoload.php';
require_once '../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$static_prefix_length = strlen('Mutasi ');
$max_name_length = 31 - $static_prefix_length;
$short_nama_nasabah = mb_substr($nama_nasabah_session, 0, $max_name_length, 'UTF-8');
if (empty($short_nama_nasabah) && !empty($nama_nasabah_session)) {
    $short_nama_nasabah = $nama_nasabah_session;
    if (mb_strlen($short_nama_nasabah, 'UTF-8') > $max_name_length) {
        $short_nama_nasabah = mb_substr($short_nama_nasabah, 0, $max_name_length - 1, 'UTF-8') . '~';
    }
} elseif (empty($nama_nasabah_session)) {
    $short_nama_nasabah = 'Nasabah';
}
$sheet->setTitle('Mutasi ' . $short_nama_nasabah);

// --- Header Laporan ---
$sheet->setCellValue('A1', 'LAPORAN MUTASI TABUNGAN NASABAH');
$sheet->mergeCells('A1:D1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Nama Nasabah: ' . $nama_nasabah_session);
$sheet->setCellValue('A4', 'No. Rekening: ' . $no_rekening_nasabah);
$sheet->setCellValue('A5', 'Tanggal Cetak: ' . date('d-m-Y H:i:s'));
$sheet->getStyle('A3:A5')->getFont()->setBold(true);

$rowNum = 7;

// --- Header Tabel Mutasi ---
$headers = ['No.', 'Tanggal', 'Tipe', 'Jumlah (Rp)'];
$sheet->fromArray($headers, NULL, 'A' . $rowNum);

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF3B82F6']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A' . $rowNum . ':' . 'D' . $rowNum)->applyFromArray($headerStyle);
$sheet->freezePane('A' . ($rowNum + 1)); // Membekukan baris header

$rowNum++;

// --- Data Tabel Mutasi + Ringkasan Bulanan ---
$current_month = null;
$monthly_total_masuk = 0;
$no = 1;

foreach ($histori_mutasi as $mutasi) {
    $mutasi_month = date('Y-m', strtotime($mutasi['tanggal_mutasi']));
    if ($current_month !== $mutasi_month && $current_month !== null) {
        $sheet->mergeCells('A' . $rowNum . ':C' . $rowNum);
        $sheet->setCellValue('A' . $rowNum, 'Total Pendapatan (' . date('F Y', strtotime($current_month . '-01')) . ')');
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD4EDDA');
        $sheet->setCellValue('D' . $rowNum, $monthly_total_masuk);
        $sheet->getStyle('D' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
        $rowNum++;
        $monthly_total_masuk = 0;
    }
    $current_month = $mutasi_month;

    if ($mutasi['tipe_mutasi'] == 'masuk') {
        $monthly_total_masuk += $mutasi['jumlah_mutasi'];
    }

    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $mutasi['tanggal_mutasi']);
    $sheet->setCellValue('C' . $rowNum, ucfirst($mutasi['tipe_mutasi']));
    $sheet->setCellValue('D' . $rowNum, $mutasi['jumlah_mutasi']);
    $sheet->getStyle('D' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
    $rowNum++;
}

// Cetak total untuk bulan terakhir
if ($current_month !== null) {
    $sheet->mergeCells('A' . $rowNum . ':C' . $rowNum);
    $sheet->setCellValue('A' . $rowNum, 'Total Pendapatan (' . date('F Y', strtotime($current_month . '-01')) . ')');
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD4EDDA');
    $sheet->setCellValue('D' . $rowNum, $monthly_total_masuk);
    $sheet->getStyle('D' . $rowNum)->getNumberFormat()->setFormatCode('"Rp "#,##0.00');
    $rowNum++;
}

// Atur lebar kolom otomatis
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);
$sheet->getColumnDimension('C')->setAutoSize(true);
$sheet->getColumnDimension('D')->setAutoSize(true);

$filename = 'Mutasi_Tabungan_' . $nama_nasabah_session . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();