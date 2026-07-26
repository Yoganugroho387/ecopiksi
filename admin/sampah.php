<?php
// admin/sampah.php
// Set halaman aktif untuk sidebar
$current_page = 'sampah';
require_once '../includes/header.php';
require_once '../config/db.php';

// Ambil id_tps dari sesi admin yang sudah login
$id_tps_admin = $_SESSION['id_tps'];

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

// --- FUNGSI REDIRECT HELPER ---
function redirect_with_message($msg, $type, $id_tps_admin) {
    header("Location: sampah.php?msg=" . urlencode($msg) . "&type=" . urlencode($type) . "&id_tps=" . urlencode($id_tps_admin));
    exit();
}
// --- AKHIR FUNGSI REDIRECT HELPER ---


// Tangani pengiriman formulir
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add_sampah') {
        $kode_sampah = trim($conn->real_escape_string($_POST['kode_sampah']));
        $jenis_sampah = trim($conn->real_escape_string($_POST['jenis_sampah']));
        $kategori_sampah = trim($conn->real_escape_string($_POST['kategori_sampah']));

        if (empty($kode_sampah) || empty($jenis_sampah)) {
            $message = "Kode Sampah dan Jenis Sampah harus diisi.";
            $message_type = "danger";
        } else {
            // Perbaikan: Cek duplikasi kode_sampah dan jenis_sampah dengan id_tps
            $check_sql = "SELECT kode_sampah FROM tb_sampah WHERE (kode_sampah = ? OR jenis_sampah = ?) AND id_tps = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ssi", $kode_sampah, $jenis_sampah, $id_tps_admin);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "Kode Sampah atau Jenis Sampah sudah terdaftar di TPS Anda.";
                $message_type = "danger";
            } else {
                // Perbaikan: Kueri INSERT sekarang menyertakan id_sampah dan id_tps
                // Kita perlu mendapatkan nilai id_sampah terakhir dan menambahkannya
                $sql_get_last_id = "SELECT MAX(id_sampah) AS max_id FROM tb_sampah";
                $result_last_id = $conn->query($sql_get_last_id);
                $last_id = 0;
                if ($result_last_id && $row = $result_last_id->fetch_assoc()) {
                    $last_id = $row['max_id'];
                }
                $new_id_sampah = $last_id + 1;

                $sql = "INSERT INTO tb_sampah (id_sampah, kode_sampah, jenis_sampah, kategori_sampah, id_tps) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssi", $new_id_sampah, $kode_sampah, $jenis_sampah, $kategori_sampah, $id_tps_admin);

                if ($stmt->execute()) {
                    redirect_with_message("Jenis sampah berhasil ditambahkan.", "success", $id_tps_admin);
                } else {
                    $message = "Error: " . $stmt->error;
                    $message_type = "danger";
                }
            }
            if (isset($check_stmt)) $check_stmt->close();
            if (isset($stmt)) $stmt->close();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit_sampah') {
        $id_sampah_edit = intval($_POST['id_sampah_edit']);
        $kode_sampah = trim($conn->real_escape_string($_POST['kode_sampah']));
        $jenis_sampah = trim($conn->real_escape_string($_POST['jenis_sampah']));
        $kategori_sampah = trim($conn->real_escape_string($_POST['kategori_sampah']));

        if (empty($kode_sampah) || empty($jenis_sampah)) {
            $message = "Kode Sampah dan Jenis Sampah harus diisi.";
            $message_type = "danger";
        } else {
            // Cek duplikasi dengan id_tps dan bukan id_sampah yang sedang diedit
            $check_sql = "SELECT id_sampah FROM tb_sampah WHERE (kode_sampah = ? OR jenis_sampah = ?) AND id_sampah != ? AND id_tps = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ssii", $kode_sampah, $jenis_sampah, $id_sampah_edit, $id_tps_admin);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "Kode atau jenis sampah sudah digunakan oleh item lain di TPS Anda.";
                $message_type = "danger";
            } else {
                // Tambahkan id_tps ke klausa WHERE
                $sql = "UPDATE tb_sampah SET kode_sampah = ?, jenis_sampah = ?, kategori_sampah = ? WHERE id_sampah = ? AND id_tps = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $kode_sampah, $jenis_sampah, $kategori_sampah, $id_sampah_edit, $id_tps_admin);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        redirect_with_message("Data jenis sampah berhasil diperbarui.", "success", $id_tps_admin);
                    } else {
                        redirect_with_message("Tidak ada perubahan data, atau jenis sampah tidak ditemukan di TPS Anda.", "warning", $id_tps_admin);
                    }
                } else {
                    $message = "Error: " . $stmt->error;
                    $message_type = "danger";
                }
            }
            if (isset($check_stmt)) $check_stmt->close();
            if (isset($stmt)) $stmt->close();
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id_sampah'])) {
    $id_sampah_to_delete = intval($_GET['id_sampah']);
    
    // Ambil kode_sampah hanya jika id_tps cocok
    $kode_sampah_info = "";
    $sql_info = "SELECT kode_sampah FROM tb_sampah WHERE id_sampah = ? AND id_tps = ?";
    $stmt_info = $conn->prepare($sql_info);
    $stmt_info->bind_param("ii", $id_sampah_to_delete, $id_tps_admin);
    $stmt_info->execute();
    $result_info = $stmt_info->get_result();
    if ($result_info->num_rows > 0) {
        $kode_sampah_info = $result_info->fetch_assoc()['kode_sampah'];
    }
    $stmt_info->close();

    // Tambahkan id_tps ke klausa WHERE
    $sql = "DELETE FROM tb_sampah WHERE id_sampah = ? AND id_tps = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_sampah_to_delete, $id_tps_admin);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $message = "Jenis sampah (Kode: " . htmlspecialchars($kode_sampah_info) . ") berhasil dihapus.";
            $message_type = "success";
        } else {
             $message = "Gagal menghapus: Jenis sampah tidak ditemukan atau tidak memiliki izin.";
            $message_type = "danger";
        }
    } else {
        if ($conn->errno == 1451) {
            $message = "Error: Jenis sampah ini tidak dapat dihapus karena masih terkait dengan data setoran atau penjualan.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $message_type = "danger";
    }
    if (isset($stmt)) $stmt->close();
    header("Location: sampah.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Ambil pesan dari parameter GET setelah redirect (misalnya setelah delete)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

// --- LOGIKA PAGINATION, FILTER KATEGORI, DAN SEARCH ---
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$kategori_filter = isset($_GET['kategori']) ? $conn->real_escape_string($_GET['kategori']) : '';

$where_clauses = ["id_tps = ?"]; // Tambahkan kondisi wajib id_tps
$params = [$id_tps_admin];
$param_types = 'i';

if (!empty($search_query)) {
    $where_clauses[] = "(kode_sampah LIKE ? OR jenis_sampah LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $param_types .= "ss";
}

if (!empty($kategori_filter)) {
    $where_clauses[] = "kategori_sampah = ?";
    $params[] = $kategori_filter;
    $param_types .= "s";
}

$where_sql = " WHERE " . implode(" AND ", $where_clauses);


// Ambil total data untuk pagination
$sql_count = "SELECT COUNT(id_sampah) AS total FROM tb_sampah" . $where_sql;
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param($param_types, ...$params);
$stmt_count->execute();
$count_result = $stmt_count->get_result()->fetch_assoc();
$total_data = $count_result['total'];
$total_pages = ceil($total_data / $limit);
$stmt_count->close();


// Ambil data sampah untuk ditampilkan dengan pagination, filter, dan search
$sampah_data = [];
$sql_fetch = "SELECT id_sampah, kode_sampah, jenis_sampah, kategori_sampah FROM tb_sampah" . $where_sql . " ORDER BY jenis_sampah ASC LIMIT ? OFFSET ?";
$stmt_fetch = $conn->prepare($sql_fetch);

// Menambahkan parameter limit dan offset ke params
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt_fetch->bind_param($param_types, ...$params);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch) {
    while ($row = $result_fetch->fetch_assoc()) {
        $sampah_data[] = $row;
    }
}
$stmt_fetch->close();


$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Jenis Sampah</h1>

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

    <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
        <button type="button" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center w-full md:w-auto justify-center" data-modal-toggle="addSampahModal">
            <i class="fas fa-plus mr-2"></i> Tambah Jenis Sampah Baru
        </button>

        <form action="sampah.php" method="GET" class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
            <input type="text" name="search" placeholder="Cari kode/jenis sampah..."
                     class="border border-gray-300 rounded-lg p-2 w-full md:w-64"
                     value="<?php echo htmlspecialchars($search_query); ?>">
            <select name="kategori" class="border border-gray-300 rounded-lg p-2 w-full md:w-auto">
                <option value="">Semua Kategori</option>
                <option value="Plastik" <?php echo ($kategori_filter == 'Plastik') ? 'selected' : ''; ?>>Plastik</option>
                <option value="Kertas" <?php echo ($kategori_filter == 'Kertas') ? 'selected' : ''; ?>>Kertas</option>
                <option value="Logam" <?php echo ($kategori_filter == 'Logam') ? 'selected' : ''; ?>>Logam</option>
                <option value="Belin" <?php echo ($kategori_filter == 'Belin') ? 'selected' : ''; ?>>Belin</option>
                <option value="Minyak" <?php echo ($kategori_filter == 'Minyak') ? 'selected' : ''; ?>>Minyak</option>
                <option value="Lain-lain" <?php echo ($kategori_filter == 'Lain-lain') ? 'selected' : ''; ?>>Lain-lain</option>
            </select>
            <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 w-full md:w-auto">Filter</button>
            <?php if (!empty($search_query) || !empty($kategori_filter)): ?>
                <a href="sampah.php" class="bg-gray-400 text-white px-4 py-2 rounded-lg hover:bg-gray-500 w-full md:w-auto text-center">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Daftar Jenis Sampah</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($sampah_data)): ?>
                        <?php foreach ($sampah_data as $sampah): ?>
                            <tr>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($sampah['kode_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($sampah['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($sampah['kategori_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button type="button" class="text-blue-600 hover:text-blue-900 mr-3" data-modal-toggle="editSampahModal"
                                        data-id_sampah="<?php echo htmlspecialchars($sampah['id_sampah']); ?>"
                                        data-kode_sampah="<?php echo htmlspecialchars($sampah['kode_sampah']); ?>"
                                        data-jenis_sampah="<?php echo htmlspecialchars($sampah['jenis_sampah']); ?>"
                                        data-kategori_sampah="<?php echo htmlspecialchars($sampah['kategori_sampah']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="sampah.php?action=delete&id_sampah=<?php echo urlencode($sampah['id_sampah']); ?>"
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus jenis sampah ini? Ini akan mempengaruhi data setoran atau penjualan yang terkait.');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                Belum ada data jenis sampah.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <nav class="flex items-center justify-between pt-4" aria-label="Table navigation">
            <span class="text-sm font-normal text-gray-500">Menampilkan <span class="font-semibold text-gray-900"><?php echo count($sampah_data); ?></span> dari <span class="font-semibold text-gray-900"><?php echo $total_data; ?></span> entri</span>
            <ul class="inline-flex -space-x-px text-sm h-8">
                <li>
                    <a href="?page=<?php echo max(1, $page - 1); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($kategori_filter) ? '&kategori=' . urlencode($kategori_filter) : ''; ?>"
                       class="flex items-center justify-center px-3 h-8 ms-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-s-lg hover:bg-gray-100 hover:text-gray-700">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($kategori_filter) ? '&kategori=' . urlencode($kategori_filter) : ''; ?>"
                           class="flex items-center justify-center px-3 h-8 leading-tight <?php echo ($i == $page) ? 'text-blue-600 bg-purple-50 border-blue-300' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <li>
                    <a href="?page=<?php echo min($total_pages, $page + 1); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($kategori_filter) ? '&kategori=' . urlencode($kategori_filter) : ''; ?>"
                       class="flex items-center justify-center px-3 h-8 leading-tight text-gray-500 bg-white border border-gray-300 rounded-e-lg hover:bg-gray-100 hover:text-gray-700">Next</a>
                </li>
            </ul>
        </nav>
    </div>

    <div id="addSampahModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-lg max-h-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                    <h3 class="text-lg font-semibold">
                        Tambah Jenis Sampah Baru
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="addSampahModal" onclick="document.getElementById('addSampahModal').classList.add('hidden');">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <form action="sampah.php" method="POST">
                    <div class="p-4 md:p-5 space-y-4">
                        <input type="hidden" name="action" value="add_sampah">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="add_kode_sampah" class="block mb-2 text-sm font-medium text-gray-900">Kode Sampah <span class="text-red-500">*</span></label>
                                <input type="text" id="add_kode_sampah" name="kode_sampah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                            <div>
                                <label for="add_jenis_sampah" class="block mb-2 text-sm font-medium text-gray-900">Jenis Sampah <span class="text-red-500">*</span></label>
                                <input type="text" id="add_jenis_sampah" name="jenis_sampah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="add_kategori_sampah" class="block mb-2 text-sm font-medium text-gray-900">Kategori Sampah</label>
                                <select id="add_kategori_sampah" name="kategori_sampah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    <option value="">Pilih Kategori</option>
                                    <option value="Plastik">Plastik</option>
                                    <option value="Kertas">Kertas</option>
                                    <option value="Logam">Logam</option>
                                    <option value="Belin">Belin</option>
                                    <option value="Minyak">Minyak</option>
                                    <option value="Lain-lain">Lain-lain</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                        <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Tambah Jenis Sampah</button>
                        <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100" data-modal-hide="addSampahModal" onclick="document.getElementById('addSampahModal').classList.add('hidden');">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editSampahModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-lg max-h-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-500 text-white">
                    <h3 class="text-lg font-semibold">
                        Edit Data Jenis Sampah
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-purple-600 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="editSampahModal" onclick="document.getElementById('editSampahModal').classList.add('hidden');">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <form action="sampah.php" method="POST">
                    <div class="p-4 md:p-5 space-y-4">
                        <input type="hidden" name="action" value="edit_sampah">
                        <input type="hidden" id="edit_id_sampah" name="id_sampah_edit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="edit_kode_sampah" class="block mb-2 text-sm font-medium text-gray-900">Kode Sampah <span class="text-red-500">*</span></label>
                                <input type="text" id="edit_kode_sampah" name="kode_sampah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                            <div>
                                <label for="edit_jenis_sampah" class="block mb-2 text-sm font-medium text-gray-900">Jenis Sampah <span class="text-red-500">*</span></label>
                                <input type="text" id="edit_jenis_sampah" name="jenis_sampah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                            <div class="md:col-span-2">
                                <label for="edit_kategori_sampah" class="block mb-2 text-sm font-medium text-gray-900">Kategori Sampah</label>
                                <select id="edit_kategori_sampah" name="kategori_sampah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    <option value="">Pilih Kategori</option>
                                    <option value="Plastik">Plastik</option>
                                    <option value="Kertas">Kertas</option>
                                    <option value="Logam">Logam</option>
                                    <option value="Belin">Belin</option>
                                    <option value="Minyak">Minyak</option>
                                    <option value="Lain-lain">Lain-lain</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b">
                        <button type="submit" class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Simpan Perubahan</button>
                        <button type="button" class="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100" data-modal-hide="editSampahModal" onclick="document.getElementById('editSampahModal').classList.add('hidden');">Batal</button>
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

    // Fungsi untuk mengisi data di modal Edit Sampah
    document.addEventListener('DOMContentLoaded', function () {
        var editSampahModal = document.getElementById('editSampahModal');
        document.querySelectorAll('button[data-modal-toggle="editSampahModal"]').forEach(button => {
            button.addEventListener('click', function() {
                var idSampah = this.getAttribute('data-id_sampah');
                var kodeSampah = this.getAttribute('data-kode_sampah');
                var jenisSampah = this.getAttribute('data-jenis_sampah');
                var kategoriSampah = this.getAttribute('data-kategori_sampah');
                
                editSampahModal.querySelector('#edit_id_sampah').value = idSampah;
                editSampahModal.querySelector('#edit_kode_sampah').value = kodeSampah;
                editSampahModal.querySelector('#edit_jenis_sampah').value = jenisSampah;
                editSampahModal.querySelector('#edit_kategori_sampah').value = kategoriSampah; // Set value for select element
            });
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>