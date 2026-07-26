<?php
// admin/delete_nasabah.php
ob_start(); // <<< PENTING: MULAI OUTPUT BUFFERING

session_start(); // Pastikan sesi dimulai

// Sertakan file koneksi database
require_once '../config/db.php'; 

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); // Redirect ke halaman login jika tidak valid
    exit();
}

$message = '';
$message_type = '';

// Pastikan aksi delete dan no_rekening diterima
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['no_rekening'])) {
    $no_rekening_to_delete = $conn->real_escape_string($_GET['no_rekening']);

    // Persiapkan dan eksekusi query DELETE
    $sql = "DELETE FROM tb_nasabah WHERE no_rekening = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $no_rekening_to_delete);

        if ($stmt->execute()) {
            // Periksa apakah ada baris yang terpengaruh (nasabah benar-benar dihapus)
            if ($stmt->affected_rows > 0) {
                $message = "Nasabah dengan No. Rekening " . htmlspecialchars($no_rekening_to_delete) . " berhasil dihapus.";
                $message_type = "success";
            } else {
                $message = "Nasabah dengan No. Rekening " . htmlspecialchars($no_rekening_to_delete) . " tidak ditemukan atau sudah dihapus.";
                $message_type = "danger";
            }
        } else {
            // Cek jika error disebabkan oleh foreign key constraint (data terkait)
            if ($conn->errno == 1451) {
                $message = "Error: Nasabah ini tidak dapat dihapus karena masih terkait dengan data lain (misal: setoran atau pencairan).";
            } else {
                $message = "Error saat menghapus nasabah: " . $stmt->error;
            }
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Error saat menyiapkan query: " . $conn->error;
        $message_type = "danger";
    }
} else {
    $message = "Aksi tidak valid atau Nomor Rekening nasabah tidak ditemukan.";
    $message_type = "danger";
}

$conn->close(); // Tutup koneksi database setelah semua operasi selesai

// Redirect kembali ke halaman manajemen nasabah dengan pesan status
header("Location: nasabah.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();

ob_end_flush(); // <<< PENTING: KIRIMKAN OUTPUT BUFFER KE BROWSER
?>