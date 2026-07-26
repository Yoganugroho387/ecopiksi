<?php
// admin/jadwal.php
ob_start();

// Set halaman aktif untuk sidebar
$current_page = 'jadwal';

require_once '../includes/header.php'; // Includes session_start() dan cek autentikasi
require_once '../config/db.php'; // Koneksi database

// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil id_tps dari sesi admin yang sudah login
$id_tps_admin = $_SESSION['id_tps'];

$message = '';
$message_type = ''; // 'success' atau 'danger'

// Tangani pengiriman formulir POST (Tambah dan Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add_jadwal') {
        $tanggal_jadwal = $conn->real_escape_string($_POST['tanggal_jadwal']);
        $rt = $conn->real_escape_string($_POST['rt']);
        $rw = $conn->real_escape_string($_POST['rw']);
        $keterangan = $conn->real_escape_string($_POST['keterangan']);

        // Validasi input
        if (empty($tanggal_jadwal)) {
            $message = "Tanggal Jadwal harus diisi.";
            $message_type = "danger";
        } else {
            // Modifikasi kueri INSERT untuk menyertakan id_tps
            $sql = "INSERT INTO tb_jadwal_pengambilan (tanggal_jadwal, rt, rw, keterangan, id_tps) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssi", $tanggal_jadwal, $rt, $rw, $keterangan, $id_tps_admin);

                if ($stmt->execute()) {
                    $message = "Jadwal pengambilan berhasil ditambahkan.";
                    $message_type = "success";
                } else {
                    $message = "Error: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Error persiapan statement: " . $conn->error;
                $message_type = "danger";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit_jadwal') {
        $id_jadwal = intval($_POST['id_jadwal']);
        $tanggal_jadwal = $conn->real_escape_string($_POST['tanggal_jadwal']);
        $rt = $conn->real_escape_string($_POST['rt']);
        $rw = $conn->real_escape_string($_POST['rw']);
        $keterangan = $conn->real_escape_string($_POST['keterangan']);

        // Validasi input
        if (empty($tanggal_jadwal)) {
            $message = "Tanggal Jadwal harus diisi.";
            $message_type = "danger";
        } else {
            // Modifikasi kueri UPDATE untuk menyertakan id_tps sebagai kondisi WHERE
            $sql = "UPDATE tb_jadwal_pengambilan SET tanggal_jadwal = ?, rt = ?, rw = ?, keterangan = ? WHERE id_jadwal = ? AND id_tps = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssii", $tanggal_jadwal, $rt, $rw, $keterangan, $id_jadwal, $id_tps_admin);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $message = "Jadwal pengambilan berhasil diperbarui.";
                        $message_type = "success";
                    } else {
                        $message = "Tidak ada perubahan data, atau jadwal tidak ditemukan di TPS Anda.";
                        $message_type = "warning";
                    }
                } else {
                    $message = "Error: " . $stmt->error;
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Error persiapan statement: " . $conn->error;
                $message_type = "danger";
            }
        }
    }
    // LAKUKAN REDIRECT SETELAH SEMUA OPERASI POST SELESAI
    header("Location: jadwal.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}

// Ambil pesan dari parameter GET
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

// Ambil data jadwal untuk ditampilkan, hanya dari TPS admin yang sedang login
$jadwal_data = [];
$sql_fetch = "SELECT * FROM tb_jadwal_pengambilan WHERE id_tps = ? ORDER BY tanggal_jadwal DESC";
$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $id_tps_admin);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($result_fetch) {
        while ($row = $result_fetch->fetch_assoc()) {
            $jadwal_data[] = $row;
        }
    }
    $stmt_fetch->close();
}

$conn->close();

require_once '../includes/header.php';
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Penjadwalan Pengambilan Sampah</h1>

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

    <div class="flex justify-end mb-4">
        <button type="button" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center" data-modal-toggle="addJadwalModal">
            <i class="fas fa-plus mr-2"></i> Tambah Jadwal Pengambilan
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Jadwal Pengambilan</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RT/RW</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($jadwal_data)): ?>
                        <?php foreach ($jadwal_data as $jadwal): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($jadwal['id_jadwal']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['tanggal_jadwal']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['rt'] ?? '-') . '/' . htmlspecialchars($jadwal['rw'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($jadwal['keterangan'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button type="button" class="text-blue-600 hover:text-blue-900 mr-3" data-modal-toggle="editJadwalModal"
                                        data-id_jadwal="<?php echo htmlspecialchars($jadwal['id_jadwal']); ?>"
                                        data-tanggal_jadwal="<?php echo htmlspecialchars($jadwal['tanggal_jadwal']); ?>"
                                        data-rt="<?php echo htmlspecialchars($jadwal['rt']); ?>"
                                        data-rw="<?php echo htmlspecialchars($jadwal['rw']); ?>"
                                        data-keterangan="<?php echo htmlspecialchars($jadwal['keterangan']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="delete_jadwal.php?action=delete&id_jadwal=<?php echo urlencode($jadwal['id_jadwal']); ?>&id_tps=<?php echo urlencode($id_tps_admin); ?>"
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Belum ada jadwal pengambilan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addJadwalModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-md max-h-full"> <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">
                    Tambah Jadwal Pengambilan
                </h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="addJadwalModal" onclick="document.getElementById('addJadwalModal').classList.add('hidden');">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>
            <form action="jadwal.php" method="POST">
                <div class="p-4 md:p-5 space-y-4">
                    <input type="hidden" name="action" value="add_jadwal">
                    <div>
                        <label for="add_tanggal_jadwal" class="block mb-2 text-sm font-medium text-gray-900">Tanggal Jadwal <span class="text-red-500">*</span></label>
                        <input type="date" id="add_tanggal_jadwal" name="tanggal_jadwal" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="add_rt" class="block mb-2 text-sm font-medium text-gray-900">RT</label>
                            <input type="text" id="add_rt" name="rt" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Cth: 001">
                        </div>
                        <div>
                            <label for="add_rw" class="block mb-2 text-sm font-medium text-gray-900">RW</label>
                            <input type="text" id="add_rw" name="rw" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Cth: 002">
                        </div>
                    </div>
                    <div>
                        <label for="add_keterangan" class="block mb-2 text-sm font-medium text-gray-900">Keterangan (Opsional)</label>
                        <textarea id="add_keterangan" name="keterangan" rows="3" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Informasi tambahan untuk operator..."></textarea>
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah Jadwal</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100" data-modal-hide="addJadwalModal" onclick="document.getElementById('addJadwalModal').classList.add('hidden');">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editJadwalModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full"> <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">
                    Edit Jadwal Pengambilan
                </h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="editJadwalModal" onclick="document.getElementById('editJadwalModal').classList.add('hidden');">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>
            <form action="jadwal.php" method="POST">
                <div class="p-4 md:p-5 space-y-4">
                    <input type="hidden" name="action" value="edit_jadwal">
                    <input type="hidden" id="edit_id_jadwal" name="id_jadwal">
                    <div>
                        <label for="edit_tanggal_jadwal" class="block mb-2 text-sm font-medium text-gray-900">Tanggal Jadwal <span class="text-red-500">*</span></label>
                        <input type="date" id="edit_tanggal_jadwal" name="tanggal_jadwal" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_rt" class="block mb-2 text-sm font-medium text-gray-900">RT</label>
                            <input type="text" id="edit_rt" name="rt" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label for="edit_rw" class="block mb-2 text-sm font-medium text-gray-900">RW</label>
                            <input type="text" id="edit_rw" name="rw" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>
                    <div>
                        <label for="edit_keterangan" class="block mb-2 text-sm font-medium text-gray-900">Keterangan (Opsional)</label>
                        <textarea id="edit_keterangan" name="keterangan" rows="3" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Simpan Perubahan</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100" data-modal-hide="editJadwalModal" onclick="document.getElementById('editJadwalModal').classList.add('hidden');">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Fungsi untuk membuka modal
    document.querySelectorAll('[data-modal-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const modalId = button.getAttribute('data-modal-toggle');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex'); // Add flex to center
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('role', 'dialog');
                document.body.classList.add('overflow-hidden'); // Disable body scroll
            }
        });
    });

    // Fungsi untuk menutup modal
    document.querySelectorAll('[data-modal-hide]').forEach(button => {
        button.addEventListener('click', () => {
            const modalId = button.getAttribute('data-modal-hide');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
                document.body.classList.remove('overflow-hidden'); // Enable body scroll
            }
        });
    });

    // Fungsi untuk mengisi data di modal Edit Jadwal
    document.addEventListener('DOMContentLoaded', function () {
        var editJadwalModal = document.getElementById('editJadwalModal');
        // Gunakan event delegation atau pastikan tombol ada saat DOMContentLoaded
        document.querySelectorAll('button[data-modal-toggle="editJadwalModal"]').forEach(button => {
            button.addEventListener('click', function() {
                var idJadwal = this.getAttribute('data-id_jadwal');
                var tanggalJadwal = this.getAttribute('data-tanggal_jadwal');
                var rt = this.getAttribute('data-rt');
                var rw = this.getAttribute('data-rw');
                var keterangan = this.getAttribute('data-keterangan');

                editJadwalModal.querySelector('#edit_id_jadwal').value = idJadwal;
                editJadwalModal.querySelector('#edit_tanggal_jadwal').value = tanggalJadwal;
                editJadwalModal.querySelector('#edit_rt').value = rt;
                editJadwalModal.querySelector('#edit_rw').value = rw;
                editJadwalModal.querySelector('#edit_keterangan').value = keterangan;
            });
        });
    });
</script>

<?php
// Bagian penutup dari layout yang dimulai di includes/header.php
// Ini harus ada di setiap file halaman setelah konten utamanya.
?>
            </main>
        </div>
    </div>

    <script>
        // Pastikan ini hanya ada sekali di seluruh aplikasi, idealnya di header.php
        // Tapi untuk memastikan berfungsi, saya ulangi di sini untuk konteks admin/jadwal.php

        // Toggle Sidebar Functionality (from header.php)
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar-wrapper');
        const pageContent = document.getElementById('page-content-wrapper');
        const body = document.body;

        if(sidebarToggle) { // Check if the toggle button exists
            sidebarToggle.addEventListener('click', () => {
                body.classList.toggle('sidebar-collapsed');
            });
        }
        
        // Close sidebar on outside click on mobile
        if(pageContent) {
            pageContent.addEventListener('click', (event) => {
                // Corrected logic: if sidebar is OPEN (NOT sidebar-collapsed) on mobile, close it if click is outside.
                if (window.innerWidth <= 768 && !body.classList.contains('sidebar-collapsed')) {
                    if (!sidebar.contains(event.target) && event.target !== sidebarToggle && !sidebarToggle.contains(event.target)) {
                        body.classList.add('sidebar-collapsed');
                    }
                }
            });
        }

        // Set sidebar state on load and resize
        function setSidebarState() {
            if (window.innerWidth > 768) {
                body.classList.remove('sidebar-collapsed'); // Desktop: sidebar open
            } else {
                body.classList.add('sidebar-collapsed'); // Mobile: sidebar collapsed
            }
        }
        window.addEventListener('load', setSidebarState);
        window.addEventListener('resize', setSidebarState);

    </script>
<?php require_once '../includes/footer.php'; ?>
<?php
ob_end_flush(); // <<< PASTIKAN INI ADA DI AKHIR FILE
?>