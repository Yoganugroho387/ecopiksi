<?php
// nasabah/edit_profil.php

// Set halaman aktif untuk sidebar nasabah
$current_page = 'edit_profil';

require_once '../includes/header.php'; // Termasuk session_start() dan cek autentikasi
require_once '../config/db.php'; // Koneksi database

// Pastikan hanya nasabah yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

$no_rekening_nasabah = $_SESSION['no_rekening'];
$message = '';
$message_type = ''; // 'success' atau 'danger'

// --- Ambil data profil nasabah saat ini ---
$sql_fetch_profile = "SELECT * FROM tb_nasabah WHERE no_rekening = ?";
$stmt_fetch_profile = $conn->prepare($sql_fetch_profile);
$stmt_fetch_profile->bind_param("s", $no_rekening_nasabah);
$stmt_fetch_profile->execute();
$result_fetch_profile = $stmt_fetch_profile->get_result();
$nasabah = $result_fetch_profile->fetch_assoc();
$stmt_fetch_profile->close();

if (!$nasabah) {
    header("Location: ../auth/logout.php");
    exit();
}

// --- Tangani POST request untuk update profil ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $nama_nasabah = $_POST['nama_nasabah'] ?? $nasabah['nama_nasabah'];
    $rt = $_POST['rt'] ?? $nasabah['rt'];
    $rw = $_POST['rw'] ?? $nasabah['rw'];
    $alamat = $_POST['alamat'] ?? $nasabah['alamat'];
    $no_hp = $_POST['no_hp'] ?? $nasabah['no_hp'];
    $no_rek_bank = $_POST['no_rek_bank'] ?? $nasabah['no_rek_bank'];
    $nama_bank = $_POST['nama_bank'] ?? $nasabah['nama_bank'];
    $nama_pemilik_rekening = $_POST['nama_pemilik_rekening'] ?? $nasabah['nama_pemilik_rekening'];

    $sql_update_profile = "UPDATE tb_nasabah SET nama_nasabah = ?, rt = ?, rw = ?, alamat = ?, no_hp = ?, no_rek_bank = ?, nama_bank = ?, nama_pemilik_rekening = ? WHERE no_rekening = ?";
    $stmt_update_profile = $conn->prepare($sql_update_profile);
    $stmt_update_profile->bind_param("sssssssss", $nama_nasabah, $rt, $rw, $alamat, $no_hp, $no_rek_bank, $nama_bank, $nama_pemilik_rekening, $no_rekening_nasabah);

    if ($stmt_update_profile->execute()) {
        $message = "Profil berhasil diperbarui!";
        $message_type = "success";
        $_SESSION['nama_nasabah'] = $nama_nasabah;
        header("Location: edit_profil.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
        exit();
    } else {
        $message = "Gagal memperbarui profil: " . $stmt_update_profile->error;
        $message_type = "danger";
    }
    $stmt_update_profile->close();
}

$conn->close();

// Dapatkan pesan dari parameter GET setelah redirect
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">

<?php include '../includes/sidebar_nasabah.php'; ?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Profil Anda</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Profil</h2>
        <form action="edit_profil.php" method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-1">Nomor Rekening</label>
                <input type="text" value="<?php echo htmlspecialchars($nasabah['no_rekening']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-200" disabled>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-1">Username</label>
                <input type="text" value="<?php echo htmlspecialchars($nasabah['username']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-200" disabled>
            </div>
            <div class="mb-4">
                <label for="nama_nasabah" class="block text-gray-700 font-medium mb-1">Nama Nasabah</label>
                <input type="text" id="nama_nasabah" name="nama_nasabah" value="<?php echo htmlspecialchars($nasabah['nama_nasabah']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300">
            </div>
            <div class="mb-4">
                <label for="rt" class="block text-gray-700 font-medium mb-1">RT</label>
                <input type="text" id="rt" name="rt" value="<?php echo htmlspecialchars($nasabah['rt']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300">
            </div>
            <div class="mb-4">
                <label for="rw" class="block text-gray-700 font-medium mb-1">RW</label>
                <input type="text" id="rw" name="rw" value="<?php echo htmlspecialchars($nasabah['rw']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300">
            </div>
            <div class="mb-4">
                <label for="alamat" class="block text-gray-700 font-medium mb-1">Alamat</label>
                <textarea id="alamat" name="alamat" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300"><?php echo htmlspecialchars($nasabah['alamat']); ?></textarea>
            </div>
            <div class="mb-4">
                <label for="no_hp" class="block text-gray-700 font-medium mb-1">Nomor HP</label>
                <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($nasabah['no_hp']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300">
            </div>
            <div class="mb-4">
                <label for="no_rek_bank" class="block text-gray-700 font-medium mb-1">Nomor Rekening Bank</label>
                <input type="text" id="no_rek_bank" name="no_rek_bank" value="<?php echo htmlspecialchars($nasabah['no_rek_bank']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300">
            </div>
            <div class="mb-4">
                <label for="nama_bank" class="block text-gray-700 font-medium mb-1">Nama Bank</label>
                <input type="text" id="nama_bank" name="nama_bank" value="<?php echo htmlspecialchars($nasabah['nama_bank']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300">
            </div>
            <div class="mb-4">
                <label for="nama_pemilik_rekening" class="block text-gray-700 font-medium mb-1">Nama Pemilik Rekening</label>
                <input type="text" id="nama_pemilik_rekening" name="nama_pemilik_rekening" value="<?php echo htmlspecialchars($nasabah['nama_pemilik_rekening']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:border-blue-300">
            </div>
            <div class="mt-6 text-right">
                <button type="submit" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 font-semibold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>