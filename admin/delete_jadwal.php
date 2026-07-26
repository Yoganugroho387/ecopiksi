<?php
// admin/delete_jadwal.php
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

// Pastikan aksi delete dan id_jadwal diterima
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id_jadwal'])) {
    $id_jadwal_to_delete = intval($_GET['id_jadwal']);

    // Persiapkan dan eksekusi query DELETE
    $sql = "DELETE FROM tb_jadwal_pengambilan WHERE id_jadwal = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id_jadwal_to_delete);

        if ($stmt->execute()) {
            $message = "Jadwal pengambilan berhasil dihapus.";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error . " (Kode: " . $stmt->errno . ")"; // Tambahkan kode error DB
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Error saat menyiapkan query: " . $conn->error;
        $message_type = "danger";
    }
} else {
    $message = "Aksi tidak valid atau ID jadwal tidak ditemukan.";
    $message_type = "danger";
}

$conn->close(); // Tutup koneksi database setelah semua operasi selesai

// Redirect kembali ke halaman manajemen jadwal dengan pesan status
header("Location: jadwal.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();

ob_end_flush(); // <<< PENTING: KIRIMKAN OUTPUT BUFFER KE BROWSER
?>