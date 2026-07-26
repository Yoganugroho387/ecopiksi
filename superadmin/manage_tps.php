<?php
// superadmin/manage_tps.php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php';

// Periksa apakah pengguna adalah Superadmin
if ($_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

// --- FUNGSI HELPER UNTUK REDIRECT DENGAN PESAN ---
function redirect_with_message($msg, $type) {
    header("Location: manage_tps.php?msg=" . urlencode($msg) . "&type=" . urlencode($type));
    exit();
}

// --- LOGIKA UTAMA UNTUK MENGELOLA TPS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_tps') {
            $nama_tps = trim($_POST['nama_tps']);
            $alamat_tps = trim($_POST['alamat_tps']);
            $kontak_tps = trim($_POST['kontak_tps']);

            if (empty($nama_tps) || empty($alamat_tps)) {
                $message = "Nama dan alamat bank sampah harus diisi.";
                $message_type = "danger";
            } else {
                $sql_insert = "INSERT INTO tb_tps (nama_tps, alamat_tps, kontak_tps) VALUES (?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("sss", $nama_tps, $alamat_tps, $kontak_tps);

                if ($stmt_insert->execute()) {
                    redirect_with_message("Bank sampah berhasil ditambahkan.", "success");
                } else {
                    $message = "Gagal menambahkan bank sampah: " . $stmt_insert->error;
                    $message_type = "danger";
                }
                $stmt_insert->close();
            }
        } elseif ($_POST['action'] === 'edit_tps') {
            $id_tps = intval($_POST['id_tps']);
            $nama_tps = trim($_POST['nama_tps']);
            $alamat_tps = trim($_POST['alamat_tps']);
            $kontak_tps = trim($_POST['kontak_tps']);
            $status_tps = trim($_POST['status_tps']);

            if (empty($nama_tps) || empty($alamat_tps)) {
                $message = "Nama dan alamat bank sampah harus diisi.";
                $message_type = "danger";
            } else {
                $sql_update = "UPDATE tb_tps SET nama_tps = ?, alamat_tps = ?, kontak_tps = ?, status_tps = ? WHERE id_tps = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("ssssi", $nama_tps, $alamat_tps, $kontak_tps, $status_tps, $id_tps);

                if ($stmt_update->execute()) {
                    redirect_with_message("TPS berhasil diperbarui.", "success");
                } else {
                    $message = "Gagal memperbarui bank sampah: " . $stmt_update->error;
                    $message_type = "danger";
                }
                $stmt_update->close();
            }
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete_tps' && isset($_GET['id'])) {
    $id_tps = intval($_GET['id']);
    
    // Pastikan ID valid
    if ($id_tps > 0) {
        $sql_delete = "DELETE FROM tb_tps WHERE id_tps = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id_tps);
        
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                redirect_with_message("bank sampah berhasil dihapus.", "success");
            } else {
                redirect_with_message("bank sampah tidak ditemukan.", "danger");
            }
        } else {
            $message = "Gagal menghapus TPS: " . $stmt_delete->error;
            if ($conn->errno == 1451) { // Error kode untuk foreign key constraint
                $message = "bank sampahS tidak dapat dihapus karena masih memiliki data terkait (admin, nasabah, dll).";
            }
            $message_type = "danger";
        }
        $stmt_delete->close();
    } else {
        redirect_with_message("ID bank sampah tidak valid.", "danger");
    }
}


// Ambil data semua TPS untuk ditampilkan
$tps_data = [];
$sql_fetch = "SELECT * FROM tb_tps ORDER BY id_tps ASC";
$result_fetch = $conn->query($sql_fetch);

if ($result_fetch) {
    while ($row = $result_fetch->fetch_assoc()) {
        $tps_data[] = $row;
    }
}

$conn->close();

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen bank sampah</h1>
    
    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-<?php echo ($message_type == 'success') ? 'purple-500' : 'red-500'; ?> rounded-lg focus:ring-2 focus:ring-<?php echo ($message_type == 'success') ? 'purple-400' : 'red-400'; ?> p-1.5 hover:bg-<?php echo ($message_type == 'success') ? 'purple-200' : 'red-200'; ?> inline-flex h-8 w-8" onclick="this.parentElement.style.display='none';" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
    <?php endif; ?>

    <div class="flex justify-end mb-4">
        <button type="button" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center" data-modal-toggle="addTPSModal">
            <i class="fas fa-plus mr-2"></i> Tambah bank sampah Baru
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Semua bank sampah</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID bank sampah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama bank sampah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alamat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($tps_data)): ?>
                        <?php foreach ($tps_data as $tps): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($tps['id_tps']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($tps['nama_tps']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($tps['alamat_tps']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($tps['kontak_tps']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($tps['status_tps'] === 'aktif') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($tps['status_tps'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <button type="button" class="text-blue-600 hover:text-blue-900 mr-3" data-modal-toggle="editTPSModal"
                                            data-id_tps="<?php echo htmlspecialchars($tps['id_tps']); ?>"
                                            data-nama_tps="<?php echo htmlspecialchars($tps['nama_tps']); ?>"
                                            data-alamat_tps="<?php echo htmlspecialchars($tps['alamat_tps']); ?>"
                                            data-kontak_tps="<?php echo htmlspecialchars($tps['kontak_tps']); ?>"
                                            data-status_tps="<?php echo htmlspecialchars($tps['status_tps']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="manage_tps.php?action=delete_tps&id=<?php echo urlencode($tps['id_tps']); ?>"
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus TPS ini? Semua data terkait (admin, nasabah, dll) akan ikut terhapus!');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada data bank sampah yang terdaftar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addTPSModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Tambah bank sampah Baru</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="addTPSModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_tps.php" method="POST">
                <input type="hidden" name="action" value="add_tps">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="add_nama_tps" class="block mb-2 text-sm font-medium text-gray-900">Nama bank sampah <span class="text-red-500">*</span></label>
                        <input type="text" id="add_nama_tps" name="nama_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_alamat_tps" class="block mb-2 text-sm font-medium text-gray-900">Alamat bank sampah <span class="text-red-500">*</span></label>
                        <input type="text" id="add_alamat_tps" name="alamat_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_kontak_tps" class="block mb-2 text-sm font-medium text-gray-900">Kontak bank sampah</label>
                        <input type="text" id="add_kontak_tps" name="kontak_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah TPS</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="addTPSModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editTPSModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Edit bank sampah</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="editTPSModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_tps.php" method="POST">
                <input type="hidden" name="action" value="edit_tps">
                <input type="hidden" id="edit_id_tps" name="id_tps">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="edit_nama_tps" class="block mb-2 text-sm font-medium text-gray-900">Nama bank sampah <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_nama_tps" name="nama_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="edit_alamat_tps" class="block mb-2 text-sm font-medium text-gray-900">Alamat bank sampah <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_alamat_tps" name="alamat_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="edit_kontak_tps" class="block mb-2 text-sm font-medium text-gray-900">Kontak bank sampah</label>
                        <input type="text" id="edit_kontak_tps" name="kontak_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label for="edit_status_tps" class="block mb-2 text-sm font-medium text-gray-900">Status bank sampah</label>
                        <select id="edit_status_tps" name="status_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="aktif">Aktif</option>
                            <option value="tidak aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Simpan Perubahan</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="editTPSModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Fungsionalitas Modal (untuk buka/tutup)
    document.querySelectorAll('[data-modal-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const modalId = button.getAttribute('data-modal-toggle');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        });
    });
    document.querySelectorAll('[data-modal-hide]').forEach(button => {
        button.addEventListener('click', () => {
            const modalId = button.getAttribute('data-modal-hide');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    });
    
    // Fungsionalitas untuk mengisi data ke modal Edit
    document.querySelectorAll('button[data-modal-toggle="editTPSModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const id_tps = this.getAttribute('data-id_tps');
            const nama_tps = this.getAttribute('data-nama_tps');
            const alamat_tps = this.getAttribute('data-alamat_tps');
            const kontak_tps = this.getAttribute('data-kontak_tps');
            const status_tps = this.getAttribute('data-status_tps');

            const modal = document.getElementById('editTPSModal');
            modal.querySelector('#edit_id_tps').value = id_tps;
            modal.querySelector('#edit_nama_tps').value = nama_tps;
            modal.querySelector('#edit_alamat_tps').value = alamat_tps;
            modal.querySelector('#edit_kontak_tps').value = kontak_tps;
            modal.querySelector('#edit_status_tps').value = status_tps;
        });
    });
</script>

       </main>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
<script>
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOpen = document.getElementById('sidebarOpen'); // Asumsi ada tombol pembuka dengan ID ini
    const sidebar = document.getElementById('sidebar-wrapper');
    const body = document.body;
    const minDesktopWidth = 768;

    // Fungsionalitas tombol "x" untuk menutup sidebar di mobile
    if (sidebarClose) {
        sidebarClose.addEventListener('click', () => {
            body.classList.add('sidebar-collapsed');
        });
    }

    // Fungsionalitas tombol pembuka sidebar (misalnya, hamburger icon)
    if (sidebarOpen) {
        sidebarOpen.addEventListener('click', () => {
            body.classList.remove('sidebar-collapsed');
        });
    }

    // Tutup sidebar saat klik di luar area pada tampilan mobile
    document.addEventListener('click', (event) => {
        if (window.innerWidth < minDesktopWidth && !body.classList.contains('sidebar-collapsed')) {
            if (!sidebar.contains(event.target) && event.target !== sidebarOpen && !sidebarOpen.contains(event.target)) {
                body.classList.add('sidebar-collapsed');
            }
        }
    });

</script>
</body>
</html>
<!-- 
╔══════════════════════════════════════════════════════════╗
║  Copyright © 2025                                        ║
║  Website ini dibuat oleh: Yoga Nugroho                   ║
║  Instagram: @yogaaszs                                    ║
║  Semua hak dilindungi undang-undang.                     ║
╚══════════════════════════════════════════════════════════╝
-->
