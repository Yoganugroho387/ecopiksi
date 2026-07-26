<?php
// admin/process_import.php
ob_start();
session_start();
require_once '../config/db.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Include Composer's autoloader
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import_nasabah') {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['excel_file']['tmp_name'];
        $file_name = $_FILES['excel_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validasi tipe file
        $allowed_ext = ['xls', 'xlsx'];
        if (!in_array($file_ext, $allowed_ext)) {
            $message = "Hanya file Excel (.xls atau .xlsx) yang diizinkan.";
            $message_type = "danger";
        } else {
            try {
                // Load spreadsheet
                $spreadsheet = IOFactory::load($file_tmp_path);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Skip header row if exists (assuming first row is header)
                $header_skipped = false;
                $imported_count = 0;
                $skipped_count = 0;
                $errors_detail = [];

                foreach ($rows as $index => $row) {
                    if (!$header_skipped) {
                        $header_skipped = true; // Skip the first row (header)
                        continue;
                    }

                    // Asumsi urutan kolom di Excel:
                    // 0: no_rekening
                    // 1: nama_nasabah
                    // 2: rt
                    // 3: rw
                    // 4: alamat
                    // 5: no_hp
                    // 6: no_rek_bank
                    // 7: nama_bank
                    // 8: nama_pemilik_rekening
                    // 9: username
                    // 10: password (plain text, akan di-hash)
                    
                    // Basic check if row is empty
                    if (empty(array_filter($row))) {
                        continue; // Skip empty rows
                    }

                    // Ambil data dan pastikan tidak kosong
                    $no_rekening = trim($row[0]);
                    $nama_nasabah = trim($row[1]);
                    $rt = trim($row[2]);
                    $rw = trim($row[3]);
                    $alamat = trim($row[4]);
                    $no_hp = trim($row[5]);
                    $no_rek_bank = trim($row[6]);
                    $nama_bank = trim($row[7]);
                    $nama_pemilik_rekening = trim($row[8]);
                    $username = trim($row[9]);
                    $password_plain = trim($row[10]); // Password dari Excel

                    // Validasi minimal data
                    if (empty($no_rekening) || empty($nama_nasabah) || empty($username) || empty($password_plain)) {
                        $errors_detail[] = "Baris " . ($index + 1) . ": Data wajib (No. Rekening, Nama, Username, Password) tidak lengkap. Dilewati.";
                        $skipped_count++;
                        continue;
                    }

                    // Cek duplikasi no_rekening atau username sebelum insert
                    $check_sql = "SELECT no_rekening FROM tb_nasabah WHERE no_rekening = ? OR username = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("ss", $no_rekening, $username);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();

                    if ($check_result->num_rows > 0) {
                        $errors_detail[] = "Baris " . ($index + 1) . ": No. Rekening ('" . $no_rekening . "') atau Username ('" . $username . "') sudah terdaftar. Dilewati.";
                        $skipped_count++;
                    } else {
                        // Hash password
                        $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
                        $role = 'nasabah'; // Default role for imported users

                        $insert_sql = "INSERT INTO tb_nasabah (no_rekening, nama_nasabah, rt, rw, alamat, no_hp, no_rek_bank, nama_bank, nama_pemilik_rekening, username, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_sql);

                        if ($insert_stmt) {
                            $insert_stmt->bind_param("ssssssssssss", $no_rekening, $nama_nasabah, $rt, $rw, $alamat, $no_hp, $no_rek_bank, $nama_bank, $nama_pemilik_rekening, $username, $hashed_password, $role);
                            if ($insert_stmt->execute()) {
                                $imported_count++;
                            } else {
                                $errors_detail[] = "Baris " . ($index + 1) . ": Gagal menyimpan ke database - " . $insert_stmt->error . ".";
                                $skipped_count++;
                            }
                            $insert_stmt->close();
                        } else {
                            $errors_detail[] = "Baris " . ($index + 1) . ": Error persiapan statement insert - " . $conn->error . ".";
                            $skipped_count++;
                        }
                    }
                    $check_stmt->close();
                }

                if ($imported_count > 0) {
                    $message = "Import berhasil! " . $imported_count . " nasabah ditambahkan. ";
                    $message_type = "success";
                } else {
                    $message = "Tidak ada nasabah yang berhasil ditambahkan.";
                    $message_type = "danger";
                }

                if ($skipped_count > 0) {
                    $message .= $skipped_count . " nasabah dilewati karena masalah: <br>" . implode("<br>", $errors_detail);
                    if ($imported_count > 0) {
                         $message_type = "warning"; // Set to warning if some succeeded but some skipped
                    }
                }
                
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                $message = "Error membaca file Excel: " . $e->getMessage();
                $message_type = "danger";
            } catch (Exception $e) {
                $message = "Terjadi kesalahan: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    } else {
        $message = "Terjadi kesalahan saat mengunggah file. Kode error: " . $_FILES['excel_file']['error'];
        $message_type = "danger";
    }
} else {
    $message = "Akses tidak sah.";
    $message_type = "danger";
}

$conn->close();
header("Location: nasabah.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>