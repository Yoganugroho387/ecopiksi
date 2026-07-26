<?php
// nasabah/request_pencairan.php
// Set halaman aktif untuk sidebar nasabah
$current_page = 'request_pencairan'; // Pastikan ini cocok dengan nilai di sidebar_nasabah.php

require_once '../includes/header.php'; // Termasuk session_start() dan cek autentikasi
require_once '../config/db.php'; // Koneksi database

// Pastikan hanya nasabah yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = ''; // 'success' atau 'danger'
$no_rekening_nasabah = $_SESSION['no_rekening']; // Ambil nomor rekening nasabah yang login
$id_tps_nasabah = $_SESSION['id_tps']; // Ambil id_tps dari sesi nasabah

// Hitung saldo terkini nasabah
$current_balance = 0;
// Perbaikan: Tambahkan id_tps ke kueri saldo
$sql_balance = "SELECT
                    SUM(CASE WHEN tipe_mutasi = 'masuk' THEN jumlah_mutasi ELSE 0 END) -
                    SUM(CASE WHEN tipe_mutasi = 'keluar' THEN jumlah_mutasi ELSE 0 END) AS current_balance
                FROM tb_tabungan_nasabah
                WHERE no_rekening = ? AND id_tps = ?";
$stmt_balance = $conn->prepare($sql_balance);
$stmt_balance->bind_param("si", $no_rekening_nasabah, $id_tps_nasabah);
$stmt_balance->execute();
$result_balance = $stmt_balance->get_result();

if ($result_balance && $result_balance->num_rows > 0) {
    $row = $result_balance->fetch_assoc();
    $current_balance = $row['current_balance'] ?? 0;
}
$stmt_balance->close();

// Tangani pengiriman formulir permintaan pencairan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'request_pencairan') {
        $jumlah_cair_request = floatval($_POST['jumlah_cair']);
        $tanggal_pencairan = date('Y-m-d'); // Tanggal pengajuan

        if ($jumlah_cair_request <= 0) {
            $message = "Jumlah pencairan harus lebih dari Rp 0.";
            $message_type = "danger";
        } elseif ($jumlah_cair_request > $current_balance) {
            $message = "Jumlah pencairan melebihi saldo Anda saat ini (Rp " . number_format($current_balance, 2, ',', '.') . ").";
            $message_type = "danger";
        } else {
            // Cek apakah ada permintaan pending sebelumnya
            // Perbaikan: Tambahkan id_tps ke kueri pengecekan
            $sql_check_pending = "SELECT COUNT(*) FROM tb_pencairan_dana WHERE no_rekening = ? AND id_tps = ? AND status = 'pending'";
            $stmt_check_pending = $conn->prepare($sql_check_pending);
            $stmt_check_pending->bind_param("si", $no_rekening_nasabah, $id_tps_nasabah);
            $stmt_check_pending->execute();
            $result_check_pending = $stmt_check_pending->get_result();
            $row_pending = $result_check_pending->fetch_row();
            $pending_requests = $row_pending[0];
            $stmt_check_pending->close();

            if ($pending_requests > 0) {
                $message = "Anda sudah memiliki permintaan pencairan yang berstatus 'pending'. Harap tunggu hingga permintaan sebelumnya diproses.";
                $message_type = "danger";
            } else {
                // Masukkan ke tb_pencairan_dana dengan status 'pending'
                // Perbaikan: Tambahkan id_tps ke kueri INSERT
                $sql_insert = "INSERT INTO tb_pencairan_dana (no_rekening, id_tps, tanggal_pencairan, jumlah_cair, status, keterangan) VALUES (?, ?, ?, ?, 'pending', ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $keterangan_ajuan = "Permintaan pencairan dana oleh nasabah.";
                $stmt_insert->bind_param("sisds", $no_rekening_nasabah, $id_tps_nasabah, $tanggal_pencairan, $jumlah_cair_request, $keterangan_ajuan);

                if ($stmt_insert->execute()) {
                    $message = "Permintaan pencairan dana sebesar Rp " . number_format($jumlah_cair_request, 2, ',', '.') . " berhasil diajukan. Mohon tunggu konfirmasi dari admin.";
                    $message_type = "success";
                } else {
                    $message = "Error mengajukan permintaan pencairan: " . $stmt_insert->error;
                    $message_type = "danger";
                }
                $stmt_insert->close();
            }
        }
    }
    // Redirect to clear POST data and show message
    header("Location: request_pencairan.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}

// Ambil riwayat permintaan pencairan untuk nasabah yang sedang login
// Perbaikan: Tambahkan id_tps ke kueri histori
$histori_pencairan = [];
$sql_fetch_pencairan = "SELECT id_pencairan, tanggal_pencairan, jumlah_cair, status, keterangan, tanggal_transfer, bukti_transfer_path
                        FROM tb_pencairan_dana
                        WHERE no_rekening = ? AND id_tps = ?
                        ORDER BY tanggal_pencairan DESC, id_pencairan DESC";
$stmt_fetch_pencairan = $conn->prepare($sql_fetch_pencairan);
$stmt_fetch_pencairan->bind_param("si", $no_rekening_nasabah, $id_tps_nasabah);
$stmt_fetch_pencairan->execute();
$result_fetch_pencairan = $stmt_fetch_pencairan->get_result();

if ($result_fetch_pencairan) {
    while ($row = $result_fetch_pencairan->fetch_assoc()) {
        $histori_pencairan[] = $row;
    }
}
$stmt_fetch_pencairan->close();
$conn->close();

// Get message from GET parameters after redirect
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Ajukan Pencairan Dana</h1>

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

    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Saldo Tabungan Anda</h2>
        <div class="text-center">
            <h2 class="text-5xl md:text-6xl font-extrabold text-purple-600 mb-2">Rp <?php echo number_format($current_balance, 2, ',', '.'); ?></h2>
            <p class="text-base text-gray-700">Jumlah ini adalah saldo Anda yang siap untuk dicairkan.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Formulir Permintaan Pencairan</h2>
        <form action="request_pencairan.php" method="POST">
            <input type="hidden" name="action" value="request_pencairan">
            <div class="mb-5">
                <label for="jumlah_cair" class="block mb-2 font-medium text-gray-900 dark:text-white">Jumlah Dana yang Ingin Dicairkan (Rp) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" id="jumlah_cair" name="jumlah_cair" min="1000" max="<?php echo floor($current_balance * 100) / 100; ?>" required>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Minimal pencairan Rp 1.000. Saldo Anda: <span class="font-bold">Rp <?php echo number_format($current_balance, 2, ',', '.'); ?></span></p>
            </div>
            <div class="text-right">
                <button type="submit" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 font-semibold">Ajukan Pencairan</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Permintaan Pencairan Anda</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Permintaan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Ajuan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Diajukan (Rp)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl. Transfer</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti Transfer</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan Admin</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($histori_pencairan)): ?>
                        <?php foreach ($histori_pencairan as $req): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($req['id_pencairan']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($req['tanggal_pencairan']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">Rp <?php echo number_format($req['jumlah_cair'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php
                                            if ($req['status'] == 'pending') echo 'bg-yellow-100 text-yellow-800';
                                            else if ($req['status'] == 'diterima') echo 'bg-purple-100 text-purple-800';
                                            else echo 'bg-red-100 text-red-800';
                                        ?>">
                                        <?php echo ucfirst($req['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo !empty($req['tanggal_transfer']) ? htmlspecialchars($req['tanggal_transfer']) : '-'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php if (!empty($req['bukti_transfer_path']) && $req['status'] == 'diterima'): ?>
                                        <a href="<?php echo htmlspecialchars($req['bukti_transfer_path']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900">Lihat Bukti</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($req['keterangan'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Anda belum memiliki riwayat permintaan pencairan dana.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>