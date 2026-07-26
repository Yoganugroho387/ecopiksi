<?php
ob_start(); // Start output buffering

session_start();
$current_page = 'nasabah';
require_once '../config/db.php';
require_once '../includes/header.php'; // Pindahkan ke sini agar tidak ada output sebelum ob_start()

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id_tps_admin = $_SESSION['id_tps'];

$message = '';
$message_type = ''; // 'success' or 'danger'
$search_query = '';
$filter_status = '';
$filter_rt = '';
$filter_rw = '';

// Ambil daftar RT dan RW unik untuk dropdown filter
$rt_options = [];
$rw_options = [];

$sql_rt = "SELECT DISTINCT rt FROM tb_nasabah WHERE id_tps = ? AND rt IS NOT NULL AND rt != '' ORDER BY rt ASC";
$stmt_rt = $conn->prepare($sql_rt);
if ($stmt_rt) {
    $stmt_rt->bind_param("i", $id_tps_admin);
    $stmt_rt->execute();
    $result_rt = $stmt_rt->get_result();
    while ($row = $result_rt->fetch_assoc()) {
        $rt_options[] = htmlspecialchars($row['rt']);
    }
    $stmt_rt->close();
}

$sql_rw = "SELECT DISTINCT rw FROM tb_nasabah WHERE id_tps = ? AND rw IS NOT NULL AND rw != '' ORDER BY rw ASC";
$stmt_rw = $conn->prepare($sql_rw);
if ($stmt_rw) {
    $stmt_rw->bind_param("i", $id_tps_admin);
    $stmt_rw->execute();
    $result_rw = $stmt_rw->get_result();
    while ($row = $result_rw->fetch_assoc()) {
        $rw_options[] = htmlspecialchars($row['rw']);
    }
    $stmt_rw->close();
}


// --- START: LOGIKA PROSES FORM POST (Tambah & Edit) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add_nasabah') {
        $no_rekening = $conn->real_escape_string($_POST['no_rekening']);
        $nama_nasabah = $conn->real_escape_string($_POST['nama_nasabah']);
        $rt = $conn->real_escape_string($_POST['rt']);
        $rw = $conn->real_escape_string($_POST['rw']);
        $alamat = $conn->real_escape_string($_POST['alamat']);
        $no_hp = $conn->real_escape_string($_POST['no_hp']);
        $no_rek_bank = $conn->real_escape_string($_POST['no_rek_bank']);
        $nama_bank = $conn->real_escape_string($_POST['nama_bank']);
        $nama_pemilik_rekening = $conn->real_escape_string($_POST['nama_pemilik_rekening']);
        $username = $conn->real_escape_string($_POST['username']);
        $password_plain = $_POST['password'];
        $status = $conn->real_escape_string($_POST['status']);
        $role = 'nasabah';

        $check_sql = "SELECT no_rekening FROM tb_nasabah WHERE (no_rekening = ? OR username = ?) AND id_tps = ?";
        $check_stmt = $conn->prepare($check_sql);
        if ($check_stmt) {
            $check_stmt->bind_param("ssi", $no_rekening, $username, $id_tps_admin);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "Nomor Rekening atau Username sudah terdaftar di TPS Anda.";
                $message_type = "danger";
            } else {
                $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
                $sql = "INSERT INTO tb_nasabah (no_rekening, nama_nasabah, rt, rw, alamat, no_hp, no_rek_bank, nama_bank, nama_pemilik_rekening, username, password, status, role, id_tps) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssssssssssi", $no_rekening, $nama_nasabah, $rt, $rw, $alamat, $no_hp, $no_rek_bank, $nama_bank, $nama_pemilik_rekening, $username, $hashed_password, $status, $role, $id_tps_admin);
                    if ($stmt->execute()) {
                        $message = "Nasabah berhasil ditambahkan.";
                        $message_type = "success";
                    } else {
                        $message = "Error: " . $stmt->error;
                        $message_type = "danger";
                    }
                    $stmt->close();
                } else {
                    $message = "Error persiapan statement (add): " . $conn->error;
                    $message_type = "danger";
                }
            }
            $check_stmt->close();
        } else {
            $message = "Error persiapan statement (check): " . $conn->error;
            $message_type = "danger";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit_nasabah') {
        $no_rekening = $conn->real_escape_string($_POST['no_rekening_edit']);
        $nama_nasabah = $conn->real_escape_string($_POST['nama_nasabah']);
        $rt = $conn->real_escape_string($_POST['rt']);
        $rw = $conn->real_escape_string($_POST['rw']);
        $alamat = $conn->real_escape_string($_POST['alamat']);
        $no_hp = $conn->real_escape_string($_POST['no_hp']);
        $no_rek_bank = $conn->real_escape_string($_POST['no_rek_bank']);
        $nama_bank = $conn->real_escape_string($_POST['nama_bank']);
        $nama_pemilik_rekening = $conn->real_escape_string($_POST['nama_pemilik_rekening']);
        $username = $conn->real_escape_string($_POST['username']);
        $password_plain = $_POST['password'];
        $status = $conn->real_escape_string($_POST['status']);

        $sql = "UPDATE tb_nasabah SET nama_nasabah = ?, rt = ?, rw = ?, alamat = ?, no_hp = ?, no_rek_bank = ?, nama_bank = ?, nama_pemilik_rekening = ?, username = ?, status = ?";
        $params = "ssssssssss";
        $values = [$nama_nasabah, $rt, $rw, $alamat, $no_hp, $no_rek_bank, $nama_bank, $nama_pemilik_rekening, $username, $status];

        if (!empty($password_plain)) {
            $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params .= "s";
            $values[] = $hashed_password;
        }

        $sql .= " WHERE no_rekening = ? AND id_tps = ?";
        $params .= "si";
        $values[] = $no_rekening;
        $values[] = $id_tps_admin;

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($params, ...$values);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = "Data Nasabah berhasil diperbarui.";
                    $message_type = "success";
                } else {
                    $message = "Tidak ada perubahan data, atau nasabah tidak ditemukan di TPS Anda.";
                    $message_type = "warning";
                }
            } else {
                $message = "Error: " . $stmt->error;
                $message_type = "danger";
            }
            $stmt->close();
        } else {
            $message = "Error persiapan statement (edit): " . $conn->error;
            $message_type = "danger";
        }
    }
    // Lakukan redirect setelah semua operasi POST selesai
    $redirect_params = '';
    if (isset($_GET['search'])) {
        $redirect_params .= '&search=' . urlencode($_GET['search']);
    }
    if (isset($_GET['page'])) {
        $redirect_params .= '&page=' . urlencode($_GET['page']);
    }
    if (isset($_GET['filter_status'])) {
        $redirect_params .= '&filter_status=' . urlencode($_GET['filter_status']);
    }
    if (isset($_GET['filter_rt'])) {
        $redirect_params .= '&filter_rt=' . urlencode($_GET['filter_rt']);
    }
    if (isset($_GET['filter_rw'])) {
        $redirect_params .= '&filter_rw=' . urlencode($_GET['filter_rw']);
    }

    header("Location: nasabah.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type) . $redirect_params);
    exit();
}
// --- END: LOGIKA PROSES FORM POST ---

// Ambil kata kunci pencarian dan filter status, RT, dan RW dari GET request
if (isset($_GET['search'])) {
    $search_query = $conn->real_escape_string($_GET['search']);
}
if (isset($_GET['filter_status'])) {
    $filter_status = $conn->real_escape_string($_GET['filter_status']);
}
if (isset($_GET['filter_rt'])) {
    $filter_rt = $conn->real_escape_string($_GET['filter_rt']);
}
if (isset($_GET['filter_rw'])) {
    $filter_rw = $conn->real_escape_string($_GET['filter_rw']);
}

// --- LOGIKA PAGINATION DAN PENCARIAN UNTUK DAFTAR NASABAH ---
$nasabah_per_page = 10;
$current_page_nasabah = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page_nasabah < 1) $current_page_nasabah = 1;

$where_conditions = ["role = 'nasabah'", "id_tps = " . $id_tps_admin];
if (!empty($search_query)) {
    $where_conditions[] = "(nama_nasabah LIKE '%" . $search_query . "%' OR no_rekening LIKE '%" . $search_query . "%')";
}
if (!empty($filter_status)) {
    $where_conditions[] = "status = '" . $filter_status . "'";
}
if (!empty($filter_rt)) {
    $where_conditions[] = "rt = '" . $filter_rt . "'";
}
if (!empty($filter_rw)) {
    $where_conditions[] = "rw = '" . $filter_rw . "'";
}
$where_clause = " WHERE " . implode(' AND ', $where_conditions);

$sql_total_nasabah = "SELECT COUNT(no_rekening) AS total_nasabah FROM tb_nasabah" . $where_clause;
$result_total_nasabah = $conn->query($sql_total_nasabah);
$total_nasabah_count = 0;
if ($result_total_nasabah) {
    $total_nasabah_count = $result_total_nasabah->fetch_assoc()['total_nasabah'];
}

$total_pages = ceil($total_nasabah_count / $nasabah_per_page);

if ($current_page_nasabah > $total_pages && $total_pages > 0) $current_page_nasabah = $total_pages;

$offset = ($current_page_nasabah - 1) * $nasabah_per_page;
if ($offset < 0) $offset = 0;

$nasabah_data = [];

// --- PERBAIKAN QUERY: Menambahkan Subquery Saldo ---
$sql_fetch = "SELECT *,
                (
                    COALESCE((SELECT SUM(CASE WHEN tipe_mutasi = 'masuk' THEN jumlah_mutasi ELSE 0 END) FROM tb_tabungan_nasabah WHERE no_rekening = tb_nasabah.no_rekening AND id_tps = tb_nasabah.id_tps), 0) -
                    COALESCE((SELECT SUM(CASE WHEN tipe_mutasi = 'keluar' THEN jumlah_mutasi ELSE 0 END) FROM tb_tabungan_nasabah WHERE no_rekening = tb_nasabah.no_rekening AND id_tps = tb_nasabah.id_tps), 0)
                ) as saldo
              FROM tb_nasabah" . $where_clause . " ORDER BY nama_nasabah ASC LIMIT ? OFFSET ?";

$stmt_fetch = $conn->prepare($sql_fetch);
if ($stmt_fetch) {
    $stmt_fetch->bind_param("ii", $nasabah_per_page, $offset);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();

    if ($result_fetch) {
        while ($row = $result_fetch->fetch_assoc()) {
            $nasabah_data[] = $row;
        }
    } else {
        error_log("Error fetching nasabah data with pagination/search: " . $conn->error);
        $message = "Terjadi kesalahan saat memuat daftar nasabah.";
        $message_type = "danger";
    }
    $stmt_fetch->close();
} else {
    $message = "Error persiapan statement (fetch nasabah): " . $conn->error;
    $message_type = "danger";
}


if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Nasabah</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg
            <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800 border border-purple-400' : 'bg-red-100 text-red-800 border border-red-400'; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-<?php echo ($message_type == 'success') ? 'purple-500' : 'red-500'; ?> rounded-lg focus:ring-2 focus:ring-<?php echo ($message_type == 'success') ? 'purple-400' : 'red-400'; ?> p-1.5 hover:bg-<?php echo ($message_type == 'success') ? 'purple-200' : 'red-200'; ?> inline-flex h-8 w-8" onclick="this.parentElement.style.display='none';" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
        <div class="w-full md:w-2/3">
            <form action="nasabah.php" method="GET" class="relative flex flex-col md:flex-row gap-2">
                <div class="relative flex-grow">
                    <input type="text" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm" id="search_input" name="search" placeholder="Cari nama atau nomor rekening nasabah..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <?php if (!empty($search_query)): ?>
                    <a href="nasabah.php<?php echo (!empty($filter_status) ? '?filter_status=' . urlencode($filter_status) : ''); ?>" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-red-500"
                        onclick="document.getElementById('search_input').value=''; this.closest('form').submit();">
                        <i class="fas fa-times-circle"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="flex flex-row gap-2 w-full md:w-auto">
                    <select id="rt_filter" name="filter_rt" class="w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                        <option value="">Semua RT</option>
                        <?php foreach ($rt_options as $rt_opt): ?>
                            <option value="<?php echo $rt_opt; ?>" <?php echo ($filter_rt == $rt_opt) ? 'selected' : ''; ?>>
                                <?php echo $rt_opt; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="rw_filter" name="filter_rw" class="w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                        <option value="">Semua RW</option>
                        <?php foreach ($rw_options as $rw_opt): ?>
                            <option value="<?php echo $rw_opt; ?>" <?php echo ($filter_rw == $rw_opt) ? 'selected' : ''; ?>>
                                <?php echo $rw_opt; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="status_filter" name="filter_status" class="w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo ($filter_status == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                        <option value="tidak aktif" <?php echo ($filter_status == 'tidak aktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                    </select>
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 w-full md:w-auto">Filter</button>
                    <?php if (!empty($search_query) || !empty($filter_status) || !empty($filter_rt) || !empty($filter_rw)): ?>
                        <a href="nasabah.php" class="bg-gray-400 text-white px-4 py-2 rounded-lg hover:bg-gray-500 w-full md:w-auto text-center">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="w-full md:w-1/3 flex justify-start md:justify-end gap-2">
            <a href="import_nasabah.php">
                <button type="button" class="bg-emerald-500 text-white px-3 py-2 rounded-lg shadow-md hover:bg-emerald-700 transition-colors duration-200 flex items-center text-sm">
                    <i class="fas fa-file-import mr-2"></i> Import
                </button>
            </a>
            <button type="button" class="bg-purple-600 text-white px-3 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center text-sm" data-modal-toggle="addNasabahModal">
                <i class="fas fa-plus mr-2"></i> Tambah Nasabah
            </button>
        </div>
    </div>


    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Nasabah
            <?php if (!empty($search_query)): ?>
                <small class="text-sm text-gray-500 ml-2">(Hasil pencarian untuk: "<?php echo htmlspecialchars($search_query); ?>")</small>
            <?php endif; ?>
            <?php if (!empty($filter_status)): ?>
                <small class="text-sm text-gray-500 ml-2">(Status: "<?php echo htmlspecialchars(ucfirst($filter_status)); ?>")</small>
            <?php endif; ?>
            <?php if (!empty($filter_rt) || !empty($filter_rw)): ?>
                <small class="text-sm text-gray-500 ml-2">(RT: "<?php echo htmlspecialchars($filter_rt); ?>", RW: "<?php echo htmlspecialchars($filter_rw); ?>")</small>
            <?php endif; ?>
        </h2>
       <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Rekening</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Nasabah</th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RT/RW</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alamat</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. HP</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($nasabah_data)): ?>
                    <?php foreach ($nasabah_data as $nasabah): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($nasabah['no_rekening']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['nama_nasabah']); ?></td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php echo htmlspecialchars($nasabah['username']); ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-emerald-600">Rp <?php echo number_format($nasabah['saldo'], 0, ',', '.'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['rt'] . '/' . $nasabah['rw']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['alamat']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($nasabah['no_hp']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($nasabah['status'] == 'aktif') ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($nasabah['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button type="button" class="text-purple-600 hover:text-purple-900 mr-3" data-modal-toggle="editNasabahModal"
                                    data-no_rekening="<?php echo htmlspecialchars($nasabah['no_rekening']); ?>"
                                    data-nama_nasabah="<?php echo htmlspecialchars($nasabah['nama_nasabah']); ?>"
                                    data-rt="<?php echo htmlspecialchars($nasabah['rt']); ?>"
                                    data-rw="<?php echo htmlspecialchars($nasabah['rw']); ?>"
                                    data-alamat="<?php echo htmlspecialchars($nasabah['alamat']); ?>"
                                    data-no_hp="<?php echo htmlspecialchars($nasabah['no_hp']); ?>"
                                    data-no_rek_bank="<?php echo htmlspecialchars($nasabah['no_rek_bank']); ?>"
                                    data-nama_bank="<?php echo htmlspecialchars($nasabah['nama_bank']); ?>"
                                    data-nama_pemilik_rekening="<?php echo htmlspecialchars($nasabah['nama_pemilik_rekening']); ?>"
                                    data-username="<?php echo htmlspecialchars($nasabah['username']); ?>"
                                    data-status="<?php echo htmlspecialchars($nasabah['status']); ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="delete_nasabah.php?action=delete&no_rekening=<?php echo urlencode($nasabah['no_rekening']); ?>"
                                    class="text-red-600 hover:text-red-900"
                                    onclick="return confirm('Apakah Anda yakin ingin menghapus nasabah ini? Tindakan ini akan menghapus semua data terkait seperti setoran dan tabungan.');">
                                    <i class="fas fa-trash-alt"></i> Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                            <?php if (!empty($search_query) || !empty($filter_status) || !empty($filter_rt) || !empty($filter_rw)): ?>
                                Tidak ada nasabah yang cocok dengan kriteria pencarian/filter.
                            <?php else: ?>
                                Belum ada data nasabah.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
        <div class="flex justify-center items-center space-x-2 text-gray-700 mt-6">
            <?php if ($total_pages > 1): ?>
                <?php
                    $pagination_base_url = 'nasabah.php?';
                    if (!empty($search_query)) {
                        $pagination_base_url .= 'search=' . urlencode($search_query) . '&';
                    }
                    if (!empty($filter_status)) {
                        $pagination_base_url .= 'filter_status=' . urlencode($filter_status) . '&';
                    }
                    if (!empty($filter_rt)) {
                        $pagination_base_url .= 'filter_rt=' . urlencode($filter_rt) . '&';
                    }
                    if (!empty($filter_rw)) {
                        $pagination_base_url .= 'filter_rw=' . urlencode($filter_rw) . '&';
                    }
                ?>

                <?php if ($current_page_nasabah > 1): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $current_page_nasabah - 1; ?>" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Previous</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Previous</span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page_nasabah - 2);
                $end_page = min($total_pages, $current_page_nasabah + 2);

                if ($end_page - $start_page < 4) {
                    $end_page = min($total_pages, $start_page + 4);
                }
                if ($end_page - $start_page < 4) {
                    $start_page = max(1, $end_page - 4);
                }


                if ($start_page > 1) echo '<span class="px-3 py-2">...</span>';
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $i; ?>"
                        class="px-3 py-2 border border-gray-300 rounded-md <?php echo ($i == $current_page_nasabah) ? 'bg-purple-500 text-white' : 'hover:bg-gray-200'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) echo '<span class="px-3 py-2">...</span>';
                ?>

                <?php if ($current_page_nasabah < $total_pages): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $current_page_nasabah + 1; ?>" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Next</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Next</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <p class="text-sm text-gray-600 mt-4 text-center">Menampilkan <?php echo count($nasabah_data); ?> dari <?php echo $total_nasabah_count; ?> nasabah.</p>

    </div>

    <div id="addNasabahModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-600 text-white">
                    <h3 class="text-lg font-semibold">
                        Tambah Nasabah Baru
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-purple-700 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="addNasabahModal" onclick="document.getElementById('addNasabahModal').classList.add('hidden');">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <form action="nasabah.php" method="POST">
                    <div class="p-4 md:p-5 space-y-4">
                        <input type="hidden" name="action" value="add_nasabah">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="add_no_rekening" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening <span class="text-red-500">*</span></label>
                                <input type="text" id="add_no_rekening" name="no_rekening" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div>
                                <label for="add_nama_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Nama Nasabah <span class="text-red-500">*</span></label>
                                <input type="text" id="add_nama_nasabah" name="nama_nasabah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="add_rt" class="block mb-2 text-sm font-medium text-gray-900">RT</label>
                                <input type="text" id="add_rt" name="rt" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="add_rw" class="block mb-2 text-sm font-medium text-gray-900">RW</label>
                                <input type="text" id="add_rw" name="rw" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="add_alamat" class="block mb-2 text-sm font-medium text-gray-900">Alamat</label>
                                <input type="text" id="add_alamat" name="alamat" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="add_no_hp" class="block mb-2 text-sm font-medium text-gray-900">No. HP</label>
                                <input type="text" id="add_no_hp" name="no_hp" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5">
                            </div>
                            <div class="col-span-1">
                                <label for="add_no_rek_bank" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening Bank</label>
                                <input type="text" id="add_no_rek_bank" name="no_rek_bank" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5"required>
                            </div>
                            <div class="col-span-1">
                                <label for="add_nama_bank" class="block mb-2 text-sm font-medium text-gray-900">Nama Bank</label>
                                <input type="text" id="add_nama_bank" name="nama_bank" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="add_nama_pemilik_rekening" class="block mb-2 text-sm font-medium text-gray-900">Nama Pemilik Rekening Bank</label>
                                <input type="text" id="add_nama_pemilik_rekening" name="nama_pemilik_rekening" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5"required>
                            </div>
                            <div class="col-span-1">
                                <label for="add_username" class="block mb-2 text-sm font-medium text-gray-900">Username <span class="text-red-500">*</span></label>
                                <input type="text" id="add_username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                           <div class="col-span-1">
                                <label for="add_password" class="block mb-2 text-sm font-medium text-gray-900">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="password" id="add_password" name="password" 
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5 pr-10" 
                                            required>
                                    <button type="button" id="togglePassword" 
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                        <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" 
                                            class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M3 3l18 18M9.88 9.88a3 3 0 104.24 4.24M6.62 6.62A10.97 10.97 0 003 12c1.5 3.5 5.36 6 9 6 1.82 0 3.5-.5 4.95-1.35M17.38 17.38A10.97 10.97 0 0021 12c-1.5-3.5-5.36-6-9-6-1.82 0-3.5.5-4.95 1.35"/>
                                        </svg>
                                        <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" 
                                            class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Password akan di-hash untuk keamanan.</p>
                            </div>
                            <div class="col-span-1">
                                <label for="add_status" class="block mb-2 text-sm font-medium text-gray-900">Status Akun <span class="text-red-500">*</span></label>
                                <select id="add_status" name="status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="tidak aktif">Tidak Aktif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                        <button type="submit" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah Nasabah</button>
                        <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-purple-700 focus:z-10 focus:ring-4 focus:ring-gray-100" data-modal-hide="addNasabahModal" onclick="document.getElementById('addNasabahModal').classList.add('hidden');">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editNasabahModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                    <h3 class="text-lg font-semibold">
                        Edit Data Nasabah
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="editNasabahModal" onclick="document.getElementById('editNasabahModal').classList.add('hidden');">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <form action="nasabah.php<?php echo !empty($_GET['page']) ? '?page=' . htmlspecialchars($_GET['page']) : ''; echo !empty($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; echo !empty($_GET['filter_status']) ? '&filter_status=' . htmlspecialchars($_GET['filter_status']) : ''; ?>" method="POST">
                    <div class="p-4 md:p-5 space-y-4">
                        <input type="hidden" name="action" value="edit_nasabah">
                        <input type="hidden" id="edit_no_rekening_original" name="no_rekening_edit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="edit_no_rekening" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening</label>
                                <input type="text" id="edit_no_rekening" name="no_rekening" class="bg-gray-100 border border-gray-300 text-gray-600 text-sm rounded-lg block w-full p-2.5 cursor-not-allowed" readonly>
                            </div>
                            <div>
                                <label for="edit_nama_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Nama Nasabah <span class="text-red-500">*</span></label>
                                <input type="text" id="edit_nama_nasabah" name="nama_nasabah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="edit_rt" class="block mb-2 text-sm font-medium text-gray-900">RT</label>
                                <input type="text" id="edit_rt" name="rt" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="edit_rw" class="block mb-2 text-sm font-medium text-gray-900">RW</label>
                                <input type="text" id="edit_rw" name="rw" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="edit_alamat" class="block mb-2 text-sm font-medium text-gray-900">Alamat</label>
                                <input type="text" id="edit_alamat" name="alamat" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="edit_no_hp" class="block mb-2 text-sm font-medium text-gray-900">No. HP</label>
                                <input type="text" id="edit_no_hp" name="no_hp" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5">
                            </div>
                            <div class="col-span-1">
                                <label for="edit_no_rek_bank" class="block mb-2 text-sm font-medium text-gray-900">No. Rekening Bank</label>
                                <input type="text" id="edit_no_rek_bank" name="no_rek_bank" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="edit_nama_bank" class="block mb-2 text-sm font-medium text-gray-900">Nama Bank</label>
                                <input type="text" id="edit_nama_bank" name="nama_bank" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="edit_nama_pemilik_rekening" class="block mb-2 text-sm font-medium text-gray-900">Nama Pemilik Rekening Bank</label>
                                <input type="text" id="edit_nama_pemilik_rekening" name="nama_pemilik_rekening" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                            <div class="col-span-1">
                                <label for="edit_username" class="block mb-2 text-sm font-medium text-gray-900">Username <span class="text-red-500">*</span></label>
                                <input type="text" id="edit_username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                            </div>
                          <div class="col-span-1">
    <label for="edit_password" class="block mb-2 text-sm font-medium text-gray-900">
        Password (isi jika ingin mengubah)
    </label>
    <div class="relative">
        <input type="password" id="edit_password" name="password" 
               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5 pr-10">
        
        <button type="button" id="toggleEditPassword" 
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
            <i class="fas fa-eye"></i>
        </button>
    </div>
    <p class="mt-1 text-sm text-gray-500">Biarkan kosong jika tidak ingin mengubah password. Akan di-hash jika diisi.</p>
</div>

<script>
    const editPasswordInput = document.getElementById('edit_password');
    const toggleEditPassword = document.getElementById('toggleEditPassword');

    if (toggleEditPassword) {
        toggleEditPassword.addEventListener('click', function() {
            // Mengubah tipe input dari password menjadi text
            const type = editPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            editPasswordInput.setAttribute('type', type);
            
            // Mengubah ikon mata
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }
</script>
                            <div class="col-span-1">
                                <label for="edit_status" class="block mb-2 text-sm font-medium text-gray-900">Status Akun <span class="text-red-500">*</span></label>
                                <select id="edit_status" name="status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="tidak aktif">Tidak Aktif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                        <button type="submit" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Simpan Perubahan</button>
                        <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-purple-700 focus:z-10 focus:ring-4 focus:ring-gray-100" data-modal-hide="editNasabahModal" onclick="document.getElementById('editNasabahModal').classList.add('hidden');">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script src="pw.js"></script>
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

    // Fungsi untuk mengisi data di modal Edit Nasabah
    document.addEventListener('DOMContentLoaded', function () {
        var editNasabahModal = document.getElementById('editNasabahModal');
        document.querySelectorAll('button[data-modal-toggle="editNasabahModal"]').forEach(button => {
            button.addEventListener('click', function() {
                var noRekening = this.getAttribute('data-no_rekening');
                var namaNasabah = this.getAttribute('data-nama_nasabah');
                var rt = this.getAttribute('data-rt');
                var rw = this.getAttribute('data-rw');
                var alamat = this.getAttribute('data-alamat');
                var noHp = this.getAttribute('data-no_hp');
                var noRekBank = this.getAttribute('data-no_rek_bank');
                var namaBank = this.getAttribute('data-nama_bank');
                var namaPemilikRekening = this.getAttribute('data-nama_pemilik_rekening');
                var username = this.getAttribute('data-username');
                var status = this.getAttribute('data-status'); // Get status

                editNasabahModal.querySelector('#edit_no_rekening_original').value = noRekening;
                editNasabahModal.querySelector('#edit_no_rekening').value = noRekening;
                editNasabahModal.querySelector('#edit_nama_nasabah').value = namaNasabah;
                editNasabahModal.querySelector('#edit_rt').value = rt;
                editNasabahModal.querySelector('#edit_rw').value = rw;
                editNasabahModal.querySelector('#edit_alamat').value = alamat;
                editNasabahModal.querySelector('#edit_no_hp').value = noHp;
                editNasabahModal.querySelector('#edit_no_rek_bank').value = noRekBank;
                editNasabahModal.querySelector('#edit_nama_bank').value = namaBank;
                editNasabahModal.querySelector('#edit_nama_pemilik_rekening').value = namaPemilikRekening;
                editNasabahModal.querySelector('#edit_username').value = username;
                editNasabahModal.querySelector('#edit_status').value = status; // Set status value
                editNasabahModal.querySelector('#edit_password').value = ''; // Clear password field on open
            });
        });
    });
    
</script>
<script>
    const passwordInput = document.getElementById('add_password');
    const togglePassword = document.getElementById('togglePassword');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            // Mengubah tipe input dari 'password' menjadi 'text' dan sebaliknya
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Menampilkan atau menyembunyikan ikon mata yang sesuai
            if (type === 'text') {
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            } else {
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            }
        });
    }
</script>
<?php require_once '../includes/footer.php'; ?>
<?php
ob_end_flush(); // Flush output
?>