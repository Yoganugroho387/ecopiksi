<?php
// admin/pembagian.php
$current_page = 'pembagian';

require_once '../includes/header.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil id_tps dari sesi admin yang sudah login
$id_tps_admin = $_SESSION['id_tps'];

$message = '';
$message_type = '';

// Fungsi untuk mendapatkan nilai persentase dari database
function get_percentage($conn, $setting_name, $id_tps) {
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $setting_name, $id_tps);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return floatval($row['nilai']);
    }
    $stmt->close();
    return null; // Mengembalikan null jika data tidak ditemukan
}

// Fungsi untuk membuat entri konfigurasi default jika belum ada
function create_default_config($conn, $id_tps) {
    $default_configs = [
        ['nama_setting' => 'persen_nasabah', 'nilai' => 60.00, 'deskripsi' => 'Persentase hasil untuk Nasabah'],
        ['nama_setting' => 'persen_tps', 'nilai' => 20.00, 'deskripsi' => 'Persentase hasil untuk TPS'],
        ['nama_setting' => 'persen_pengepul', 'nilai' => 20.00, 'deskripsi' => 'Persentase hasil untuk Pengepul']
    ];

    // Perbaikan: Loop untuk setiap konfigurasi dan cek keberadaannya
    $sql_insert = "INSERT INTO tb_konfigurasi (nama_setting, nilai, deskripsi, id_tps) VALUES (?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    
    foreach ($default_configs as $config) {
        $sql_check = "SELECT COUNT(*) FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $config['nama_setting'], $id_tps);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_row();
        $stmt_check->close();

        // Jika entri belum ada, baru tambahkan
        if ($row_check[0] == 0) {
            $stmt_insert->bind_param("sdsi", $config['nama_setting'], $config['nilai'], $config['deskripsi'], $id_tps);
            $stmt_insert->execute();
        }
    }
    $stmt_insert->close();
}

// Panggil fungsi create_default_config() sebelum memuat data
create_default_config($conn, $id_tps_admin);

// Tangani POST request untuk menyimpan perubahan persentase
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $persen_nasabah = floatval($_POST['persen_nasabah']);
    $persen_tps = floatval($_POST['persen_tps']);
    $persen_pengepul = floatval($_POST['persen_pengepul']);

    // Validasi: Pastikan totalnya 100%
    if (($persen_nasabah + $persen_tps + $persen_pengepul) != 100) {
        $message = "Total persentase harus 100%. Mohon periksa kembali.";
        $message_type = "danger";
    } elseif ($persen_nasabah < 0 || $persen_tps < 0 || $persen_pengepul < 0) {
        $message = "Persentase tidak boleh negatif.";
        $message_type = "danger";
    } else {
        $conn->begin_transaction();

        try {
            $sql_nasabah = "UPDATE tb_konfigurasi SET nilai = ?, terakhir_diubah = NOW() WHERE nama_setting = 'persen_nasabah' AND id_tps = ?";
            $stmt_nasabah = $conn->prepare($sql_nasabah);
            $stmt_nasabah->bind_param("di", $persen_nasabah, $id_tps_admin);
            $stmt_nasabah->execute();

            $sql_tps = "UPDATE tb_konfigurasi SET nilai = ?, terakhir_diubah = NOW() WHERE nama_setting = 'persen_tps' AND id_tps = ?";
            $stmt_tps = $conn->prepare($sql_tps);
            $stmt_tps->bind_param("di", $persen_tps, $id_tps_admin);
            $stmt_tps->execute();

            $sql_pengepul = "UPDATE tb_konfigurasi SET nilai = ?, terakhir_diubah = NOW() WHERE nama_setting = 'persen_pengepul' AND id_tps = ?";
            $stmt_pengepul = $conn->prepare($sql_pengepul);
            $stmt_pengepul->bind_param("di", $persen_pengepul, $id_tps_admin);
            $stmt_pengepul->execute();

            $conn->commit();
            $message = "Persentase pembagian berhasil diperbarui.";
            $message_type = "success";
            
            header("Location: pembagian.php?msg=" . urlencode($message) . "&type=" . $message_type);
            exit();

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        } finally {
            if (isset($stmt_nasabah)) $stmt_nasabah->close();
            if (isset($stmt_tps)) $stmt_tps->close();
            if (isset($stmt_pengepul)) $stmt_pengepul->close();
        }
    }
}

// Ambil persentase saat ini dari database untuk ditampilkan
$persen_nasabah_current = get_percentage($conn, 'persen_nasabah', $id_tps_admin) ?? 60.00;
$persen_tps_current = get_percentage($conn, 'persen_tps', $id_tps_admin) ?? 20.00;
$persen_pengepul_current = get_percentage($conn, 'persen_pengepul', $id_tps_admin) ?? 20.00;

// Ambil pesan dari parameter GET setelah redirect
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Pengaturan Pembagian Hasil</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg
            <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-<?php echo ($message_type == 'success') ? 'purple-500' : 'red-500'; ?> rounded-lg focus:ring-2 focus:ring-<?php echo ($message_type == 'success') ? 'purple-400' : 'red-400'; ?> p-1.5 hover:bg-<?php echo ($message_type == 'success') ? 'purple-200' : 'red-200'; ?> inline-flex h-8 w-8" onclick="this.parentElement.style.display='none';" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 max-w-md mx-auto">
        <p class="text-gray-700 mb-4">Atur persentase pembagian hasil dari transaksi penjualan sampah.</p>
        <form action="pembagian.php" method="POST">
            <div class="mb-4">
                <label for="persen_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Persentase untuk Nasabah (%)</label>
                <input type="number" step="0.01" id="persen_nasabah" name="persen_nasabah" value="<?php echo htmlspecialchars($persen_nasabah_current); ?>"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        min="0" required oninput="calculateTotal()">
            </div>
            <div class="mb-4">
                <label for="persen_tps" class="block mb-2 text-sm font-medium text-gray-900">Persentase untuk Bank Sampah (%)</label>
                <input type="number" step="0.01" id="persen_tps" name="persen_tps" value="<?php echo htmlspecialchars($persen_tps_current); ?>"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        min="0" required oninput="calculateTotal()">
            </div>
            <div class="mb-6">
                <label for="persen_pengepul" class="block mb-2 text-sm font-medium text-gray-900">Persentase untuk Pos Penimbangan (%)</label>
                <input type="number" step="0.01" id="persen_pengepul" name="persen_pengepul" value="<?php echo htmlspecialchars($persen_pengepul_current); ?>"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        min="0" required oninput="calculateTotal()">
            </div>
            <div class="mb-6 p-3 bg-gray-100 rounded-lg">
                <p class="text-sm font-medium text-gray-900">Total Persentase: <span id="totalPersentase" class="font-semibold text-lg">0</span>%</p>
                <p id="totalWarning" class="text-red-600 text-xs mt-1 hidden">Total persentase harus tepat 100%.</p>
            </div>
            <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 w-full">
                Simpan Pengaturan
            </button>
        </form>
    </div>
</div>

<script>
    // JavaScript untuk menghitung total persentase secara real-time
    function calculateTotal() {
        const persenNasabah = parseFloat(document.getElementById('persen_nasabah').value) || 0;
        const persenTps = parseFloat(document.getElementById('persen_tps').value) || 0;
        const persenPengepul = parseFloat(document.getElementById('persen_pengepul').value) || 0;

        const total = persenNasabah + persenTps + persenPengepul;
        document.getElementById('totalPersentase').textContent = total.toFixed(2);

        const totalWarning = document.getElementById('totalWarning');
        if (Math.abs(total - 100) > 0.01) { // Use a small epsilon for floating-point comparison
            totalWarning.classList.remove('hidden');
            totalWarning.textContent = `Total persentase saat ini ${total.toFixed(2)}%. Harus tepat 100%.`;
        } else {
            totalWarning.classList.add('hidden');
        }
    }

    // Panggil saat halaman dimuat untuk inisialisasi total
    document.addEventListener('DOMContentLoaded', calculateTotal);
</script>
<?php require_once '../includes/footer.php'; ?>