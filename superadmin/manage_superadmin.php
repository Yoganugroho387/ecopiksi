<?php
// superadmin/manage_superadmin.php
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

function redirect_with_message($msg, $type) {
    header("Location: manage_superadmin.php?msg=" . urlencode($msg) . "&type=" . urlencode($type));
    exit();
}

// --- LOGIKA UTAMA UNTUK MENGELOLA SUPERADMIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_superadmin') {
            $username = trim($_POST['username']);
            $nama = trim($_POST['nama']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            if (empty($username) || empty($nama) || empty($password)) {
                $message = "Username, Nama, dan Password harus diisi.";
                $message_type = "danger";
            } else {
                $sql_check = "SELECT COUNT(*) FROM tb_super_admin WHERE username = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("s", $username);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $row = $result_check->fetch_row();
                $stmt_check->close();

                if ($row[0] > 0) {
                    $message = "Username sudah terdaftar.";
                    $message_type = "danger";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql_insert = "INSERT INTO tb_super_admin (username, password, nama, email) VALUES (?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ssss", $username, $hashed_password, $nama, $email);

                    if ($stmt_insert->execute()) {
                        redirect_with_message("Akun Superadmin berhasil ditambahkan.", "success");
                    } else {
                        $message = "Gagal menambahkan Superadmin: " . $stmt_insert->error;
                        $message_type = "danger";
                    }
                    $stmt_insert->close();
                }
            }
        } elseif ($_POST['action'] === 'edit_superadmin') {
            $id_super_admin = intval($_POST['id_super_admin']);
            $username = trim($_POST['username']);
            $nama = trim($_POST['nama']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            if (empty($username) || empty($nama)) {
                $message = "Username dan Nama harus diisi.";
                $message_type = "danger";
            } else {
                $sql_update = "UPDATE tb_super_admin SET username = ?, nama = ?, email = ?";
                $params = "sss";
                $values = [$username, $nama, $email];
                
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql_update .= ", password = ?";
                    $params .= "s";
                    $values[] = $hashed_password;
                }
                
                $sql_update .= " WHERE id_super_admin = ?";
                $params .= "i";
                $values[] = $id_super_admin;

                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param($params, ...$values);

                if ($stmt_update->execute()) {
                    redirect_with_message("Akun Superadmin berhasil diperbarui.", "success");
                } else {
                    $message = "Gagal memperbarui akun: " . $stmt_update->error;
                    $message_type = "danger";
                }
                $stmt_update->close();
            }
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete_superadmin' && isset($_GET['id'])) {
    $id_super_admin = intval($_GET['id']);
    
    if ($id_super_admin > 0) {
        $sql_delete = "DELETE FROM tb_super_admin WHERE id_super_admin = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id_super_admin);
        
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                redirect_with_message("Akun Superadmin berhasil dihapus.", "success");
            } else {
                redirect_with_message("Akun Superadmin tidak ditemukan.", "danger");
            }
        } else {
            $message = "Gagal menghapus akun: " . $stmt_delete->error;
            $message_type = "danger";
        }
        $stmt_delete->close();
    } else {
        redirect_with_message("ID Superadmin tidak valid.", "danger");
    }
}

// Ambil data semua Superadmin untuk ditampilkan
$superadmin_data = [];
$sql_fetch = "SELECT * FROM tb_super_admin ORDER BY id_super_admin";
$result_fetch = $conn->query($sql_fetch);

if ($result_fetch) {
    while ($row = $result_fetch->fetch_assoc()) {
        $superadmin_data[] = $row;
    }
}

$conn->close();

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Superadmin</h1>
    
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
        <button type="button" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center" data-modal-toggle="addSuperadminModal">
            <i class="fas fa-user-plus mr-2"></i> Tambah Superadmin Baru
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Superadmin</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($superadmin_data)): ?>
                        <?php foreach ($superadmin_data as $sa): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sa['id_super_admin']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($sa['username']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($sa['nama']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($sa['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <button type="button" class="text-blue-600 hover:text-blue-900 mr-3" data-modal-toggle="editSuperadminModal"
                                            data-id_super_admin="<?php echo htmlspecialchars($sa['id_super_admin']); ?>"
                                            data-username="<?php echo htmlspecialchars($sa['username']); ?>"
                                            data-nama="<?php echo htmlspecialchars($sa['nama']); ?>"
                                            data-email="<?php echo htmlspecialchars($sa['email']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="manage_superadmin.php?action=delete_superadmin&id=<?php echo urlencode($sa['id_super_admin']); ?>"
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus akun Superadmin ini?');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">Belum ada data Superadmin yang terdaftar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addSuperadminModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Tambah Superadmin Baru</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="addSuperadminModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_superadmin.php" method="POST">
                <input type="hidden" name="action" value="add_superadmin">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="add_username" class="block mb-2 text-sm font-medium text-gray-900">Username <span class="text-red-500">*</span></label>
                        <input type="text" id="add_username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_nama" class="block mb-2 text-sm font-medium text-gray-900">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" id="add_nama" name="nama" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_email" class="block mb-2 text-sm font-medium text-gray-900">Email</label>
                        <input type="email" id="add_email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label for="add_password" class="block mb-2 text-sm font-medium text-gray-900">Password <span class="text-red-500">*</span></label>
                        <input type="password" id="add_password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah Superadmin</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="addSuperadminModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editSuperadminModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Edit Superadmin</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="editSuperadminModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_superadmin.php" method="POST">
                <input type="hidden" name="action" value="edit_superadmin">
                <input type="hidden" id="edit_id_super_admin" name="id_super_admin">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="edit_username" class="block mb-2 text-sm font-medium text-gray-900">Username <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="edit_nama" class="block mb-2 text-sm font-medium text-gray-900">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_nama" name="nama" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="edit_email" class="block mb-2 text-sm font-medium text-gray-900">Email</label>
                        <input type="email" id="edit_email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label for="edit_password" class="block mb-2 text-sm font-medium text-gray-900">Password (Isi jika ingin diubah)</label>
                        <input type="password" id="edit_password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Simpan Perubahan</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="editSuperadminModal">Batal</button>
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
    document.querySelectorAll('button[data-modal-toggle="editSuperadminModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id_super_admin');
            const username = this.getAttribute('data-username');
            const nama = this.getAttribute('data-nama');
            const email = this.getAttribute('data-email');

            const modal = document.getElementById('editSuperadminModal');
            modal.querySelector('#edit_id_super_admin').value = id;
            modal.querySelector('#edit_username').value = username;
            modal.querySelector('#edit_nama').value = nama;
            modal.querySelector('#edit_email').value = email;
            modal.querySelector('#edit_password').value = ''; // Kosongkan password
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
