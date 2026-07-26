<?php
// admin/tambah_harga_baru.php
// Set halaman aktif untuk sidebar (agar Dashboard tetap aktif saat di halaman ini)
$current_page = 'dashboard';
require_once '../includes/header.php'; // Includes session_start() and auth check
require_once '../config/db.php'; // Database connection

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil id_tps dari sesi admin yang sudah login
$id_tps_admin = $_SESSION['id_tps'];

$message = '';
$message_type = ''; // 'success' or 'danger'

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Memastikan aksi yang dijalankan adalah 'add_sampah'
    if (isset($_POST['action']) && $_POST['action'] === 'add_sampah') {
        $jenis_sampah = trim($_POST['jenis_sampah']);
        
        // Mengganti koma dengan titik untuk harga, lalu validasi
        $harga_per_kg_str = str_replace(',', '.', trim($_POST['harga_add']));
        $harga_per_kg = floatval($harga_per_kg_str);

        // Validasi input
        if (empty($jenis_sampah)) {
            $message = "Jenis sampah harus diisi.";
            $message_type = "danger";
        } elseif (!is_numeric($harga_per_kg) || $harga_per_kg <= 0) {
            $message = "Harga harus berupa angka positif.";
            $message_type = "danger";
        } else {
            // Cek apakah jenis sampah sudah ada di TPS yang sama
            $check_sql = "SELECT id FROM tb_harga_sampah WHERE jenis_sampah = ? AND id_tps = ?";
            $check_stmt = $conn->prepare($check_sql);
            if ($check_stmt) {
                $check_stmt->bind_param("si", $jenis_sampah, $id_tps_admin);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $message = "Jenis sampah '{$jenis_sampah}' sudah ada dalam daftar harga di TPS Anda.";
                    $message_type = "danger";
                } else {
                    // Masukkan jenis sampah dan harga baru ke database, tambahkan id_tps
                    $insert_sql = "INSERT INTO tb_harga_sampah (jenis_sampah, harga_per_kg, id_tps) VALUES (?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("sdi", $jenis_sampah, $harga_per_kg, $id_tps_admin);
                        if ($insert_stmt->execute()) {
                            // Redirect dengan pesan sukses
                            header("Location: index.php?msg=" . urlencode("Jenis sampah '{$jenis_sampah}' berhasil ditambahkan!") . "&type=success#manajemen-harga");
                            exit();
                        } else {
                            $message = "Gagal menambahkan jenis sampah. Silakan coba lagi. Error: " . $insert_stmt->error;
                            $message_type = "danger";
                        }
                        $insert_stmt->close();
                    } else {
                        $message = "Gagal menyiapkan statement database.";
                        $message_type = "danger";
                    }
                }
                $check_stmt->close();
            } else {
                $message = "Gagal menyiapkan statement database.";
                $message_type = "danger";
            }
        }
    }
}

// Menampilkan pesan dari parameter GET jika ada
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
// Tidak perlu menutup koneksi di sini karena header.php/footer.php mungkin masih menggunakannya
// $conn->close();

?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Tambah Harga Sampah Baru</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type === 'success' ? 'bg-purple-100 text-purple-800 border border-purple-400' : 'bg-red-100 text-red-800 border border-red-400'); ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-<?php echo ($message_type === 'success' ? 'purple-500' : 'red-500'); ?> rounded-lg focus:ring-2 focus:ring-<?php echo ($message_type === 'success' ? 'purple-400' : 'red-400'); ?> p-1.5 hover:bg-<?php echo ($message_type === 'success' ? 'purple-200' : 'red-200'); ?> inline-flex h-8 w-8" onclick="this.parentElement.style.display='none';" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md mx-auto">
        <form action="tambah_harga_baru.php" method="POST">
            <input type="hidden" name="action" value="add_sampah">
            <div class="mb-4">
                <label for="jenis_sampah" class="block mb-2 text-sm font-medium text-gray-900">Jenis Sampah</label>
                <input type="text" id="jenis_sampah" name="jenis_sampah" placeholder="Contoh: Plastik, Kertas, Logam" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required value="<?php echo htmlspecialchars($_POST['jenis_sampah'] ?? ''); ?>">
            </div>
            <div class="mb-4">
                <div class="relative">
                    <label for="harga_add" class="block mb-2 text-sm font-medium text-gray-900">Harga per KG (Rp)</label>
                    <input type="text" id="harga_add" name="harga_add" placeholder="Contoh: 15.000 atau 15000" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required value="<?php echo htmlspecialchars($_POST['harga_add'] ?? ''); ?>">
                </div>
            </div>
            <div class="flex items-center mt-6 border-t border-gray-200 pt-4">
                <button type="submit" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah Harga</button>
                <a href="index.php#manajemen-harga" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-purple-700 focus:z-10 focus:ring-4 focus:ring-gray-100">Batal</a>
            </div>
        </form>
    </div>
</div>
<?php
// Tutup koneksi di akhir halaman
if (isset($conn) && $conn) {
    $conn->close();
}
?>
</body>
</html>