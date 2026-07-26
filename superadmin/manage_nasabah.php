<?php
// superadmin/manage_nasabah.php
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
function redirect_with_message($msg, $type, $params = []) {
    $queryString = http_build_query(array_merge(['msg' => $msg, 'type' => $type], $params));
    header("Location: manage_nasabah.php?" . $queryString);
    exit();
}

// --- LOGIKA UTAMA UNTUK MENGELOLA NASABAH ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_nasabah') {
            $no_rekening = trim($_POST['no_rekening']);
            $nama_nasabah = trim($_POST['nama_nasabah']);
            $id_tps = intval($_POST['id_tps']);
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $role = 'nasabah';
            $status = 'aktif';

            if (empty($no_rekening) || empty($nama_nasabah) || empty($username) || empty($password) || $id_tps <= 0) {
                $message = "Semua field bertanda (*) harus diisi.";
                $message_type = "danger";
            } else {
                $sql_check = "SELECT COUNT(*) FROM tb_nasabah WHERE no_rekening = ? OR username = ?";
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
                    $sql_insert = "INSERT INTO tb_nasabah (no_rekening, nama_nasabah, id_tps, username, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ssisss", $no_rekening, $nama_nasabah, $id_tps, $username, $hashed_password, $role, $status);

                    if ($stmt_insert->execute()) {
                        redirect_with_message("Nasabah berhasil ditambahkan.", "success");
                    } else {
                        $message = "Gagal menambahkan nasabah: " . $stmt_insert->error;
                        $message_type = "danger";
                    }
                    $stmt_insert->close();
                }
            }
        } elseif ($_POST['action'] === 'edit_nasabah') {
            $no_rekening_lama = trim($_POST['no_rekening_lama']);
            $nama_nasabah = trim($_POST['nama_nasabah']);
            $id_tps = intval($_POST['id_tps']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $status = trim($_POST['status']);

            if (empty($nama_nasabah) || empty($username) || $id_tps <= 0) {
                $message = "Nama, Username, dan bank sampah harus diisi.";
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
                    redirect_with_message("Nasabah berhasil diperbarui.", "success");
                } else {
                    $message = "Gagal memperbarui nasabah: " . $stmt_update->error;
                    $message_type = "danger";
                }
                $stmt_update->close();
            }
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'delete_nasabah' && isset($_GET['no_rekening'])) {
    $no_rekening = trim($_GET['no_rekening']);
    
    if (!empty($no_rekening)) {
        $sql_delete = "DELETE FROM tb_nasabah WHERE no_rekening = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("s", $no_rekening);
        
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                redirect_with_message("Nasabah berhasil dihapus.", "success");
            } else {
                redirect_with_message("Nasabah tidak ditemukan.", "danger");
            }
        } else {
            $message = "Gagal menghapus nasabah: " . $stmt_delete->error;
            $message_type = "danger";
        }
        $stmt_delete->close();
    } else {
        redirect_with_message("No. Rekening tidak valid.", "danger");
    }
}

// --- LOGIKA FILTER, PENCARIAN, DAN PAGINASI ---
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$filter_tps = isset($_GET['filter_tps']) ? intval($_GET['filter_tps']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = ["tn.role = 'nasabah'"];
$params = [];
$param_types = '';

if ($filter_tps > 0) {
    $where_clauses[] = "tn.id_tps = ?";
    $params[] = $filter_tps;
    $param_types .= 'i';
}

if (!empty($search_query)) {
    $where_clauses[] = "(tn.nama_nasabah LIKE ? OR tn.no_rekening LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Ambil total data untuk paginasi
$sql_count = "SELECT COUNT(tn.no_rekening) as total FROM tb_nasabah tn" . $where_sql;
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result()->fetch_assoc();
$total_data = $count_result['total'];
$total_pages = ceil($total_data / $limit);
$stmt_count->close();

// Ambil data nasabah dengan paginasi
$nasabah_data = [];
$sql_fetch = "SELECT tn.*, tt.nama_tps FROM tb_nasabah tn LEFT JOIN tb_tps tt ON tn.id_tps = tt.id_tps " . $where_sql . " ORDER BY tn.id_tps, tn.nama_nasabah LIMIT ? OFFSET ?";
$stmt_fetch = $conn->prepare($sql_fetch);

$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$stmt_fetch->bind_param($param_types, ...$params);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch) {
    while ($row = $result_fetch->fetch_assoc()) {
        $nasabah_data[] = $row;
    }
}
$stmt_fetch->close();

// Ambil data TPS untuk dropdown di modal dan filter
$tps_list = [];
$sql_tps = "SELECT id_tps, nama_tps FROM tb_tps ORDER BY nama_tps";
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
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Nasabah </h1>
    
    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-<?php echo ($message_type == 'success') ? 'purple-500' : 'red-500'; ?> rounded-lg focus:ring-2 focus:ring-<?php echo ($message_type == 'success') ? 'purple-400' : 'red-400'; ?> p-1.5 hover:bg-<?php echo ($message_type == 'success') ? 'purple-200' : 'red-200'; ?> inline-flex h-8 w-8" onclick="this.parentElement.style.display='none';" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
        <form action="manage_nasabah.php" method="GET" class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
            <div class="flex-grow">
                <select name="filter_tps" class="border border-gray-300 rounded-lg p-2 w-full">
                    <option value="0">Semua Bank Sampah</option>
                    <?php foreach ($tps_list as $tps): ?>
                        <option value="<?php echo htmlspecialchars($tps['id_tps']); ?>" <?php echo ($filter_tps == $tps['id_tps']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tps['nama_tps']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-grow">
                <input type="text" name="search" placeholder="Cari nama atau no. rekening..."
                       class="border border-gray-300 rounded-lg p-2 w-full"
                       value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600">Filter</button>
                <a href="manage_nasabah.php" class="bg-gray-400 text-white px-4 py-2 rounded-lg hover:bg-gray-500 text-center">Reset</a>
            </div>
        </form>
        
        <button type="button" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center w-full md:w-auto justify-center" data-modal-toggle="addNasabahModal">
            <i class="fas fa-plus mr-2"></i> Tambah Nasabah Baru
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Semua Nasabah</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Rekening</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Nasabah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TBank Sampah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($nasabah_data)): ?>
                        <?php foreach ($nasabah_data as $nasabah): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($nasabah['no_rekening']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['nama_nasabah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['nama_tps'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['username']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($nasabah['status'] === 'aktif') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($nasabah['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <button type="button" class="text-blue-600 hover:text-blue-900 mr-3" data-modal-toggle="editNasabahModal"
                                            data-no_rekening="<?php echo htmlspecialchars($nasabah['no_rekening']); ?>"
                                            data-nama_nasabah="<?php echo htmlspecialchars($nasabah['nama_nasabah']); ?>"
                                            data-id_tps="<?php echo htmlspecialchars($nasabah['id_tps']); ?>"
                                            data-username="<?php echo htmlspecialchars($nasabah['username']); ?>"
                                            data-status="<?php echo htmlspecialchars($nasabah['status']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="manage_nasabah.php?action=delete_nasabah&no_rekening=<?php echo urlencode($nasabah['no_rekening']); ?>"
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus nasabah ini? Semua data terkait (setoran, tabungan) akan ikut terhapus!');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada data nasabah yang terdaftar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="flex justify-center items-center space-x-2 text-gray-700 mt-6">
            <?php if ($total_pages > 1): ?>
                <?php
                    $pagination_base_url = 'manage_nasabah.php?';
                    if ($filter_tps > 0) { $pagination_base_url .= 'filter_tps=' . urlencode($filter_tps) . '&'; }
                    if (!empty($search_query)) { $pagination_base_url .= 'search=' . urlencode($search_query) . '&'; }
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $page - 1; ?>" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Previous</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Previous</span>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) echo '<span class="px-3 py-2">...</span>';
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $i; ?>"
                        class="px-3 py-2 border border-gray-300 rounded-md <?php echo ($i == $page) ? 'bg-purple-500 text-white' : 'hover:bg-gray-200'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) echo '<span class="px-3 py-2">...</span>';
                ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $page + 1; ?>" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Next</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Next</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <p class="text-sm text-gray-600 mt-4 text-center">Menampilkan <?php echo count($nasabah_data); ?> dari <?php echo $total_data; ?> nasabah.</p>

    </div>
</div>

<div id="addNasabahModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Tambah Nasabah Baru</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="addNasabahModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_nasabah.php" method="POST">
                <input type="hidden" name="action" value="add_nasabah">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="add_no_rekening" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening <span class="text-red-500">*</span></label>
                        <input type="text" id="add_no_rekening" name="no_rekening" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="add_nama_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Nama Nasabah <span class="text-red-500">*</span></label>
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
                    <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah Nasabah</button>
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="addNasabahModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editNasabahModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-lg max-h-full">
        <div class="relative bg-white rounded-lg shadow-lg">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                <h3 class="text-lg font-semibold">Edit Nasabah</h3>
                <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="editNasabahModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>
                </button>
            </div>
            <form action="manage_nasabah.php" method="POST">
                <input type="hidden" name="action" value="edit_nasabah">
                <input type="hidden" id="edit_no_rekening_lama" name="no_rekening_lama">
                <div class="p-4 md:p-5 space-y-4">
                    <div>
                        <label for="edit_no_rekening" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening</label>
                        <input type="text" id="edit_no_rekening" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 cursor-not-allowed" readonly>
                    </div>
                    <div>
                        <label for="edit_nama_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Nama Nasabah <span class="text-red-500">*</span></label>
                        <input type="text" id="edit_nama_nasabah" name="nama_nasabah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="edit_id_tps" class="block mb-2 text-sm font-medium text-gray-900">Pilih Bank Sampah <span class="text-red-500">*</span></label>
                        <select id="edit_id_tps" name="id_tps" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="">Pilih Bank Sampah</option>
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
                    <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100" data-modal-hide="editNasabahModal">Batal</button>
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
    document.querySelectorAll('button[data-modal-toggle="editNasabahModal"]').forEach(button => {
        button.addEventListener('click', function() {
            const no_rekening = this.getAttribute('data-no_rekening');
            const nama_nasabah = this.getAttribute('data-nama_nasabah');
            const id_tps = this.getAttribute('data-id_tps');
            const username = this.getAttribute('data-username');
            const status = this.getAttribute('data-status');

            const modal = document.getElementById('editNasabahModal');
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
