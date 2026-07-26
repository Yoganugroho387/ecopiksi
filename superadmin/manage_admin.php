<?php
session_start();
require_once '../config/db.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'superadmin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

function redirect_with_message($msg, $type, $params = []) {
    $queryString = http_build_query(array_merge(['msg' => $msg, 'type' => $type], $params));
    header("Location: manage_admin.php?" . $queryString);
    exit();
}

// --- LOGIKA UTAMA UNTUK MENGELOLA ADMIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_admin') {
            $no_rekening = trim($_POST['no_rekening']);
            $nama_nasabah = trim($_POST['nama_nasabah']);
            $id_tps = intval($_POST['id_tps']);
            $username = trim($_POST['username']);
            $password = $_POST['password'];

            if (empty($no_rekening) || empty($nama_nasabah) || empty($username) || empty($password) || $id_tps <= 0) {
                $message = "Semua field bertanda (*) harus diisi.";
                $message_type = "danger";
            } else {
                $sql_check = "SELECT COUNT(*) FROM tb_nasabah WHERE (no_rekening = ? OR username = ?)";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("ss", $no_rekening, $username);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $row = $result_check->fetch_row();
                $stmt_check->close();

                if ($row[0] > 0) {
                    $message = "Nomor Rekening atau Username sudah terdaftar.";
                    $message_type = "danger";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql_insert = "INSERT INTO tb_nasabah (no_rekening, nama_nasabah, id_tps, username, password, role, status) VALUES (?, ?, ?, ?, ?, 'admin', 'aktif')";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ssiss", $no_rekening, $nama_nasabah, $id_tps, $username, $hashed_password);

                    if ($stmt_insert->execute()) {
                        redirect_with_message("Admin berhasil ditambahkan.", "success");
                    } else {
                        $message = "Gagal menambahkan admin: " . $stmt_insert->error;
                        $message_type = "danger";
                    }
                    $stmt_insert->close();
                }
            }
        } elseif ($_POST['action'] === 'edit_admin') {
            $no_rekening_lama = trim($_POST['no_rekening_lama']);
            $nama_nasabah = trim($_POST['nama_nasabah']);
            $id_tps = intval($_POST['id_tps']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $status = trim($_POST['status']);

            if (empty($nama_nasabah) || empty($username)) {
                $message = "Nama dan Username harus diisi.";
                $message_type = "danger";
            } elseif ($id_tps <= 0) {
                $message = "TPS harus dipilih.";
                $message_type = "danger";
            } else {
                $sql_update = "UPDATE tb_nasabah SET nama_nasabah = ?, id_tps = ?, username = ?, status = ?";
                $params = "siss";
                $values = [$nama_nasabah, $id_tps, $username, $status];
                
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql_update .= ", password = ?";
                    $params .= "s";
                    $values[] = $hashed_password;
                }
                
                $sql_update .= " WHERE no_rekening = ?";
                $params .= "s";
                $values[] = $no_rekening_lama;

                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param($params, ...$values);

                if ($stmt_update->execute()) {
                    redirect_with_message("Admin berhasil diperbarui.", "success");
                } else {
                    $message = "Gagal memperbarui admin: " . $stmt_update->error;
                    $message_type = "danger";
                }
                $stmt_update->close();
            }
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete_admin' && isset($_GET['no_rekening'])) {
    $no_rekening = trim($_GET['no_rekening']);
    
    if (!empty($no_rekening)) {
        $sql_delete = "DELETE FROM tb_nasabah WHERE no_rekening = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("s", $no_rekening);
        
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                redirect_with_message("Admin berhasil dihapus.", "success");
            } else {
                redirect_with_message("Admin tidak ditemukan.", "danger");
            }
        } else {
            $message = "Gagal menghapus admin: " . $stmt_delete->error;
            $message_type = "danger";
        }
        $stmt_delete->close();
    } else {
        redirect_with_message("No. Rekening tidak valid.", "danger");
    }
}

// Ambil data semua admin untuk ditampilkan, termasuk nama TPS
$admin_data = [];
$sql_fetch = "SELECT tn.*, tt.nama_tps FROM tb_nasabah tn LEFT JOIN tb_tps tt ON tn.id_tps = tt.id_tps WHERE tn.role = 'admin' ORDER BY tn.id_tps, tn.nama_nasabah";
$result_fetch = $conn->query($sql_fetch);

if ($result_fetch) {
    while ($row = $result_fetch->fetch_assoc()) {
        $admin_data[] = $row;
    }
}

// Ambil data TPS untuk dropdown di modal
$tps_list = [];
$sql_tps = "SELECT id_tps, nama_tps FROM tb_tps WHERE status_tps = 'aktif' ORDER BY nama_tps";
$result_tps = $conn->query($sql_tps);
if ($result_tps) {
    while ($row = $result_tps->fetch_assoc()) {
        $tps_list[] = $row;
    }
}

$conn->close();

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Admin</h1>
    
    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-<?php echo ($message_type == 'success') ? 'purple-500' : 'red-500'; ?> rounded-lg focus:ring-2 focus:ring-<?php echo ($message_type == 'success') ? 'purple-400' : 'red-400'; ?> p-1.5 hover:bg-<?php echo ($message_type == 'success') ? 'purple-200' : 'red-200'; ?> inline-flex h-8 w-8" onclick="this.parentElement.style.display='none';" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
    <?php endif; ?>

   <div class="flex justify-end mb-4 gap-2">
    <a href="export_user.php" class="bg-green-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-green-700 transition-colors duration-200 flex items-center">
        <i class="fas fa-file-excel mr-2"></i> Ekspor Data Pengguna
    </a>
    <button type="button" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center" data-modal-toggle="addAdminModal">
        <i class="fas fa-user-plus mr-2"></i> Tambah Admin Baru
    </button>
</div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Semua Admin</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Rekening</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Admin</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank Sampah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($admin_data)): ?>
                        <?php foreach ($admin_data as $admin): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['no_rekening']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($admin['nama_nasabah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($admin['nama_tps'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($admin['status'] === 'aktif') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($admin['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <button type="button" class="text-blue-600 hover:text-blue-900 mr-3" data-modal-toggle="editAdminModal"
                                            data-no_rekening="<?php echo htmlspecialchars($admin['no_rekening']); ?>"
                                            data-nama_nasabah="<?php echo htmlspecialchars($admin['nama_nasabah']); ?>"
                                            data-id_tps="<?php echo htmlspecialchars($admin['id_tps']); ?>"
                                            data-username="<?php echo htmlspecialchars($admin['username']); ?>"
                                            data-status="<?php echo htmlspecialchars($admin['status']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="manage_admin.php?action=delete_admin&no_rekening=<?php echo urlencode($admin['no_rekening']); ?>"
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus akun admin ini? Tindakan ini tidak dapat dibatalkan.');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada data admin yang terdaftar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addAdminModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Tambah Admin Baru</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="addAdminModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_admin.php" method="POST">
                <input type="hidden" name="action" value="add_admin">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="add_no_rekening" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening <span class="text-red-500">*</span></label>
                        <input type="text" id="add_no_rekening" name="no_rekening" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_nama_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Nama Admin <span class="text-red-500">*</span></label>
                        <input type="text" id="add_nama_nasabah" name="nama_nasabah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_id_tps" class="block mb-2 text-sm font-medium text-gray-900">Pilih Bank Sampah <span class="text-red-500">*</span></label>
                        <select id="add_id_tps" name="id_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="">Pilih Bank Sampah</option>
                            <?php foreach ($tps_list as $tps): ?>
                                <option value="<?php echo htmlspecialchars($tps['id_tps']); ?>"><?php echo htmlspecialchars($tps['nama_tps']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="add_username" class="block mb-2 text-sm font-medium text-gray-900">Username <span class="text-red-500">*</span></label>
                        <input type="text" id="add_username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_password" class="block mb-2 text-sm font-medium text-gray-900">Password <span class="text-red-500">*</span></label>
                        <input type="password" id="add_password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah Admin</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="addAdminModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editAdminModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Edit Admin</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="editAdminModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_admin.php" method="POST">
                <input type="hidden" name="action" value="edit_admin">
                <input type="hidden" id="edit_no_rekening_lama" name="no_rekening_lama">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="edit_no_rekening" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening</label>
                        <input type="text" id="edit_no_rekening" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 cursor-not-allowed" readonly>
                    </div>
                    <div>
                        <label for="edit_nama_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Nama Admin <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_nama_nasabah" name="nama_nasabah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="edit_id_tps" class="block mb-2 text-sm font-medium text-gray-900">Pilih Bank Sampah <span class="text-red-500">*</span></label>
                        <select id="edit_id_tps" name="id_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="">Pilih TPS</option>
                            <?php foreach ($tps_list as $tps): ?>
                                <option value="<?php echo htmlspecialchars($tps['id_tps']); ?>"><?php echo htmlspecialchars($tps['nama_tps']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit_username" class="block mb-2 text-sm font-medium text-gray-900">Username <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="edit_password" class="block mb-2 text-sm font-medium text-gray-900">Password (Isi jika ingin diubah)</label>
                        <input type="password" id="edit_password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label for="edit_status" class="block mb-2 text-sm font-medium text-gray-900">Status Akun</label>
                        <select id="edit_status" name="status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="aktif">Aktif</option>
                            <option value="tidak aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Simpan Perubahan</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="editAdminModal">Batal</button>
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
    document.querySelectorAll('button[data-modal-toggle="editAdminModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const no_rekening = this.getAttribute('data-no_rekening');
            const nama_nasabah = this.getAttribute('data-nama_nasabah');
            const id_tps = this.getAttribute('data-id_tps');
            const username = this.getAttribute('data-username');
            const status = this.getAttribute('data-status');

            const modal = document.getElementById('editAdminModal');
            modal.querySelector('#edit_no_rekening_lama').value = no_rekening;
            modal.querySelector('#edit_no_rekening').value = no_rekening;
            modal.querySelector('#edit_nama_nasabah').value = nama_nasabah;
            modal.querySelector('#edit_id_tps').value = id_tps;
            modal.querySelector('#edit_username').value = username;
            modal.querySelector('#edit_status').value = status;
            modal.querySelector('#edit_password').value = ''; // Kosongkan password
        });
    });
</script>

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
