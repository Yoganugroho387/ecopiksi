<?php
// nasabah/harga_lengkap.php
require_once '../includes/header.php'; // Termasuk session_start() dan cek autentikasi
require_once '../config/db.php'; // Koneksi database

// Pastikan hanya nasabah yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil id_tps dari sesi nasabah
$id_tps = $_SESSION['id_tps'];

// Inisialisasi variabel pencarian
$search_query = $_GET['search'] ?? '';

// Ambil daftar harga sampah berdasarkan id_tps dengan filter pencarian
$harga_sampah = [];
$sql_harga_sampah = "SELECT jenis_sampah, harga_per_kg FROM tb_harga_sampah WHERE id_tps = ? AND jenis_sampah LIKE ? ORDER BY jenis_sampah ASC";

$stmt_harga_sampah = $conn->prepare($sql_harga_sampah);
$search_param = '%' . $search_query . '%';
$stmt_harga_sampah->bind_param("is", $id_tps, $search_param);
$stmt_harga_sampah->execute();
$result_harga_sampah = $stmt_harga_sampah->get_result();

if ($result_harga_sampah) {
    while ($row = $result_harga_sampah->fetch_assoc()) {
        $harga_sampah[] = $row;
    }
}
$stmt_harga_sampah->close();

// Tutup koneksi database
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Harga Sampah Lengkap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">

<?php include '../includes/sidebar_nasabah.php'; // Contoh: menyertakan sidebar ?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Daftar Harga Sampah Lengkap</h1>
        <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold text-sm">
            <i class="fas fa-arrow-circle-left mr-1"></i> Kembali ke Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Semua Harga Sampah</h2>
            <form action="" method="get" class="flex items-center">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Cari jenis sampah..." class="px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="ml-2 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">Cari</button>
                <?php if ($search_query): ?>
                    <a href="harga_lengkap.php" class="ml-2 px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (!empty($harga_sampah)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga per Kg (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($harga_sampah as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">Rp <?php echo htmlspecialchars(number_format($item['harga_per_kg'] ?? 0, 2, ',', '.')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-4">Tidak ada daftar harga sampah yang ditemukan.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>