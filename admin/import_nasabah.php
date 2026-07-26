<?php
ob_start();

// Set halaman aktif untuk sidebar
$current_page = 'nasabah';

// 1. Memuat header, koneksi, dan pustaka
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// 2. Otorisasi: Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil ID TPS dari admin yang sedang login
$id_tps_admin = $_SESSION['id_tps'] ?? null; // Dapatkan id_tps dari sesi

// Jika id_tps tidak ditemukan di sesi, berikan pesan error dan hentikan proses
if ($id_tps_admin === null) {
    $_SESSION['import_message'] = ['type' => 'error', 'text' => 'ID TPS tidak ditemukan di sesi. Mohon login ulang.'];
    header('Location: import_nasabah.php');
    exit;
}

// 3. Logika Proses Impor File Excel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    
    $file_tmp_name = $_FILES['file_excel']['tmp_name'];
    $file_name = $_FILES['file_excel']['name'];
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

    // Validasi ekstensi file
    if (strtolower($file_ext) != 'xlsx') {
        $_SESSION['import_message'] = ['type' => 'error', 'text' => 'Format file tidak valid. Harap unggah file .xlsx'];
        header('Location: import_nasabah.php');
        exit;
    }

    try {
        $spreadsheet = IOFactory::load($file_tmp_name);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        
        if ($highestRow <= 1) {
            $_SESSION['import_message'] = ['type' => 'warning', 'text' => 'File Excel kosong atau hanya berisi header.'];
            header('Location: import_nasabah.php');
            exit;
        }

        // PERUBAHAN DI SINI: Tambahkan kolom id_tps ke query
        $sql = "INSERT INTO tb_nasabah (no_rekening, nama_nasabah, rt, rw, alamat, no_hp, no_rek_bank, nama_bank, nama_pemilik_rekening, username, password, role, id_tps) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        $jumlah_sukses = 0;
        $jumlah_gagal = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            $no_rekening    = $sheet->getCell('A' . $row)->getFormattedValue();
            $nama_nasabah   = $sheet->getCell('B' . $row)->getValue();
            $rt             = $sheet->getCell('C' . $row)->getValue();
            $rw             = $sheet->getCell('D' . $row)->getValue();
            $alamat         = $sheet->getCell('E' . $row)->getValue();
            $no_hp          = $sheet->getCell('F' . $row)->getFormattedValue();
            $no_rek_bank    = $sheet->getCell('G' . $row)->getFormattedValue();
            $nama_bank      = $sheet->getCell('H' . $row)->getValue();
            $nama_pemilik   = $sheet->getCell('I' . $row)->getValue();
            $username       = $sheet->getCell('J' . $row)->getValue();
            $password_plain = $sheet->getCell('K' . $row)->getValue();
            $role           = $sheet->getCell('L' . $row)->getValue();

            if (empty($no_rekening) || empty($password_plain)) continue;

            $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

            // PERUBAHAN DI SINI: Tambahkan parameter untuk id_tps
            $stmt->bind_param('ssssssssssssi', 
                $no_rekening, $nama_nasabah, $rt, $rw, $alamat, $no_hp, 
                $no_rek_bank, $nama_bank, $nama_pemilik, $username, $password_hash, $role, $id_tps_admin
            );
            
            if ($stmt->execute()) {
                $jumlah_sukses++;
            } else {
                $jumlah_gagal++;
            }
        }
        
        $stmt->close();
        $_SESSION['import_message'] = ['type' => 'success', 'text' => "Impor Selesai. Berhasil: <strong>$jumlah_sukses</strong> data. Gagal: <strong>$jumlah_gagal</strong> data."];

    } catch (Exception $e) {
        $_SESSION['import_message'] = ['type' => 'error', 'text' => 'Terjadi kesalahan saat memproses file: ' . $e->getMessage()];
    }
    
    $conn->close();
    header('Location: import_nasabah.php');
    exit;
}

// 4. Logika untuk mengunduh template Excel
if (isset($_GET['action']) && $_GET['action'] == 'download_template') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // PERUBAHAN DI SINI: Tambahkan kolom id_tps ke dalam template
    $headers = [
        'no_rekening', 'nama_nasabah', 'rt', 'rw', 'alamat', 'no_hp', 
        'no_rek_bank', 'nama_bank', 'nama_pemilik_rekening', 'username', 'password', 'role', 'id_tps'
    ];

    $sheet->fromArray([$headers], NULL, 'A1');

    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $fileName = 'template_import_nasabah.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit();
}
?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Impor Data Nasabah</h1>
        <a href="nasabah.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors duration-200 flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Kembali ke Daftar Nasabah
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-6">
        
        <?php
        if (isset($_SESSION['import_message'])) {
            $message = $_SESSION['import_message'];
            $type_class = '';
            if ($message['type'] === 'success') {
                $type_class = 'bg-purple-100 border-purple-400 text-purple-700';
            } elseif ($message['type'] === 'error') {
                $type_class = 'bg-red-100 border-red-400 text-red-700';
            } else {
                $type_class = 'bg-yellow-100 border-yellow-400 text-yellow-700';
            }
            echo '<div class="' . $type_class . ' border px-4 py-3 rounded relative mb-6" role="alert">' . $message['text'] . '</div>';
            unset($_SESSION['import_message']);
        }
        ?>

        <div class="bg-purple-50 border border-blue-200 text-blue-800 p-4 rounded-lg mb-6">
            <h3 class="font-bold text-lg mb-2 flex items-center"><i class="fas fa-info-circle mr-2"></i>Petunjuk Impor</h3>
            <ul class="list-disc list-inside space-y-1 text-sm">
                <li>Gunakan file Excel dengan format **.xlsx**.</li>
                <li>Pastikan baris pertama pada file Excel berisi header kolom (tidak akan diimpor).</li>
                <li>Data nasabah dimulai dari baris kedua.</li>
                <li>Urutan kolom harus sesuai: `no_rekening`, `nama_nasabah`, `rt`, `rw`, `alamat`, `no_hp`, `no_rek_bank`, `nama_bank`, `nama_pemilik_rekening`, `username`, `password`, `role`.</li>
            </ul>
            <div class="mt-4">
                <a href="temp.xlsx" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-900 focus:outline-none focus:border-purple-900 focus:ring ring-purple-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <i class="fas fa-download mr-2"></i>Download Template Excel
                </a>
            </div>
        </div>
        
        <form action="import_nasabah.php" method="post" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="file_excel" class="block mb-2 text-sm font-medium text-gray-900">Pilih File Excel</label>
                <input type="file" name="file_excel" id="file_excel" required accept=".xlsx"
                        class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none
                                 file:mr-4 file:py-2 file:px-4
                                 file:rounded-l-lg file:border-0
                                 file:text-sm file:font-semibold
                                 file:bg-purple-50 file:text-blue-700
                                 hover:file:bg-purple-100">
            </div>

            <button type="submit" class="w-full text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 text-center">
                <i class="fas fa-upload mr-2"></i>Unggah dan Impor Sekarang
            </button>
        </form>
    </div>
</div>