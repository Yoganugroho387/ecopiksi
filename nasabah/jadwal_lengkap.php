<?php
// nasabah/jadwal_lengkap.php
require_once '../includes/header.php'; // Termasuk session_start() dan cek autentikasi
require_once '../config/db.php';     // Koneksi database

// Pastikan hanya nasabah yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

// Set halaman aktif untuk sidebar nasabah (jika ada)
$current_page = 'jadwal_lengkap';

// Ambil id_tps dari sesi nasabah yang sedang login
$id_tps_nasabah = $_SESSION['id_tps'];

// Ambil semua jadwal pengambilan yang terkait dengan TPS nasabah
$all_jadwal = [];
$sql_all_jadwal = "SELECT id_jadwal, tanggal_jadwal, rt, rw, keterangan
                   FROM tb_jadwal_pengambilan
                   WHERE id_tps = ?
                   ORDER BY tanggal_jadwal DESC"; // Urutkan dari tanggal terbaru

$stmt_all_jadwal = $conn->prepare($sql_all_jadwal);
$stmt_all_jadwal->bind_param("i", $id_tps_nasabah);
$stmt_all_jadwal->execute();
$result_all_jadwal = $stmt_all_jadwal->get_result();

if ($result_all_jadwal) {
    while ($row = $result_all_jadwal->fetch_assoc()) {
        $all_jadwal[] = $row;
    }
}
$stmt_all_jadwal->close();
$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Semua Jadwal Pengambilan Sampah</h1>

    <div class="bg-white rounded-lg shadow-md p-6">
        <?php if (!empty($all_jadwal)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Jadwal</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RT</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RW</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($all_jadwal as $jadwal): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($jadwal['id_jadwal']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['tanggal_jadwal']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['rt'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['rw'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['keterangan'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-4">Belum ada jadwal pengambilan sampah yang tersedia.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>