<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 1. Ambil data dari database, hanya kolom yang dibutuhkan
$sql = "SELECT tn.nama_nasabah, tt.nama_tps, tn.username 
        FROM tb_nasabah tn 
        LEFT JOIN tb_tps tt ON tn.id_tps = tt.id_tps 
        WHERE tn.role = 'admin'
        ORDER BY tn.nama_nasabah";
$result = $conn->query($sql);

// 2. Buat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Admin');

// 3. Tambahkan header kolom baru
$header_row = ['Nama Admin', 'Nama TPS', 'Username'];
$sheet->fromArray($header_row, NULL, 'A1');

// 4. Tambahkan data dari database ke spreadsheet
if ($result && $result->num_rows > 0) {
    $row_index = 2; // Mulai dari baris kedua setelah header
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row_index, $row['nama_nasabah']);
        $sheet->setCellValue('B' . $row_index, $row['nama_tps'] ?? '-');
        $sheet->setCellValue('C' . $row_index, $row['username']);
        $row_index++;
    }
} else {
    // Jika tidak ada data, tambahkan baris kosong
    $sheet->setCellValue('A2', 'Tidak ada data admin.');
}

// 5. PENAMBAHAN STYLING DI SINI
$total_rows = $sheet->getHighestRow();
$total_cols = $sheet->getHighestColumn();
$full_range = 'A1:' . $total_cols . $total_rows;
$header_range = 'A1:' . $total_cols . '1';

// Atur style header: tebal, warna latar, dan warna font
$sheet->getStyle($header_range)->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'], // Putih
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF800080'], // Ungu
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
]);

// Tambahkan garis tepi (border) untuk seluruh tabel
$sheet->getStyle($full_range)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
]);

// Atur lebar kolom otomatis
foreach (range('A', $total_cols) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// 6. Siapkan file untuk diunduh
$filename = "data_admin_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit();
?>