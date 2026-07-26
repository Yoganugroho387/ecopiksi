<?php
// admin/setor_sampah.php

// Pastikan session dimulai di awal file
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set halaman aktif untuk sidebar
$current_page = 'setor_sampah';

// Memuat file-file yang dibutuhkan
require_once '../includes/header.php';
require_once '../config/db.php';

// --- Autentikasi & Otorisasi ---
// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil id_tps dari sesi admin yang sudah login
$id_tps_admin = $_SESSION['id_tps'];
$message = '';
$message_type = '';

// --- FUNGSI HELPER: Redirect dengan Pesan ---
function redirect_with_message($msg, $type) {
    $queryString = http_build_query(['msg' => $msg, 'type' => $type]);
    header("Location: setor_sampah.php?" . $queryString);
    exit();
}

// --- Mengambil Daftar Nasabah untuk Dropdown, difilter per TPS ---
$nasabah_list = [];
$sql_nasabah = "SELECT no_rekening, nama_nasabah FROM tb_nasabah WHERE role = 'nasabah' AND id_tps = ? ORDER BY nama_nasabah ASC";
$stmt_nasabah = $conn->prepare($sql_nasabah);
$stmt_nasabah->bind_param("i", $id_tps_admin);
$stmt_nasabah->execute();
$result_nasabah = $stmt_nasabah->get_result();

if ($result_nasabah === FALSE) {
    error_log("Error fetching nasabah data in setor_sampah.php: " . $conn->error);
    $message = "Terjadi kesalahan saat memuat daftar nasabah. Silakan coba lagi nanti.";
    $message_type = "danger";
} else {
    while ($row = $result_nasabah->fetch_assoc()) {
        $nasabah_list[$row['no_rekening']] = $row;
    }
}
$stmt_nasabah->close();

// --- Mengambil Daftar Jenis Sampah untuk Dropdown, difilter per TPS ---
$sampah_list = [];
$sql_sampah = "SELECT kode_sampah, jenis_sampah, kategori_sampah FROM tb_sampah WHERE id_tps = ? ORDER BY jenis_sampah ASC";
$stmt_sampah = $conn->prepare($sql_sampah);
$stmt_sampah->bind_param("i", $id_tps_admin);
$stmt_sampah->execute();
$result_sampah = $stmt_sampah->get_result();

if ($result_sampah === FALSE) {
    error_log("Error fetching sampah data in setor_sampah.php: " . $conn->error);
    if (empty($message)) {
        $message = "Terjadi kesalahan saat memuat daftar jenis sampah. Silakan coba lagi nanti.";
        $message_type = "danger";
    }
} else {
    if ($result_sampah->num_rows > 0) {
        while ($row = $result_sampah->fetch_assoc()) {
            $sampah_list[$row['kode_sampah']] = [
                'jenis_sampah' => $row['jenis_sampah'],
                'kategori_sampah' => $row['kategori_sampah']
            ];
        }
    }
}
$stmt_sampah->close();

// --- Tangani Pengiriman Form (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_setoran_awal') {
    $tanggal_pengambilan = trim($_POST['tanggal_pengambilan_awal']);
    $no_rekening = trim($_POST['no_rekening_awal']);
    $kode_sampah_arr = $_POST['kode_sampah_awal'] ?? [];
    $berat_kg_arr = $_POST['berat_kg_awal'] ?? [];
    $keterangan_awal_arr = $_POST['keterangan_awal'] ?? [];

    $form_valid = true;
    $error_messages = [];
    $setoran_items = [];

    if (empty($tanggal_pengambilan) || empty($no_rekening)) {
        $error_messages[] = "Tanggal Pengambilan dan Nasabah harus diisi.";
        $form_valid = false;
    }

    if (!isset($nasabah_list[$no_rekening])) {
        $error_messages[] = "Nomor rekening nasabah tidak valid atau tidak terdaftar di TPS Anda.";
        $form_valid = false;
    } else {
        $nama_nasabah = $nasabah_list[$no_rekening]['nama_nasabah'];
    }

    $has_valid_item = false;
    foreach ($kode_sampah_arr as $index => $ks) {
        $current_berat_kg_str = $berat_kg_arr[$index] ?? '0';
        $berat_kg_item = filter_var($current_berat_kg_str, FILTER_VALIDATE_FLOAT);
        $keterangan_item = trim($keterangan_awal_arr[$index] ?? '');

        if (empty($ks)) {
            continue;
        }

        if (!isset($sampah_list[$ks])) {
            $error_messages[] = "Jenis sampah dengan kode '{$ks}' tidak valid atau tidak terdaftar di TPS Anda.";
            $form_valid = false;
        } elseif ($berat_kg_item === FALSE || $berat_kg_item <= 0) {
            $jenis_sampah_display = isset($sampah_list[$ks]['jenis_sampah']) ? $sampah_list[$ks]['jenis_sampah'] : $ks;
            $error_messages[] = "Berat untuk jenis sampah '{$jenis_sampah_display}' harus berupa angka positif lebih dari 0.";
            $form_valid = false;
        } else {
            $setoran_items[] = [
                'kode_sampah' => $ks,
                'jenis_sampah' => $sampah_list[$ks]['jenis_sampah'],
                'kategori_sampah' => $sampah_list[$ks]['kategori_sampah'],
                'berat_kg' => $berat_kg_item,
                'keterangan' => $keterangan_item
            ];
            $has_valid_item = true;
        }
    }

    if (!$has_valid_item) {
        $error_messages[] = "Minimal satu jenis sampah harus diisi dengan benar (jenis sampah, berat > 0).";
        $form_valid = false;
    }

    if (!empty($error_messages)) {
        $message = "Terjadi kesalahan input:<br>" . implode("<br>", $error_messages);
        $message_type = "danger";
    }

    if ($form_valid && $has_valid_item) {
        $conn->begin_transaction();
        try {
            $sql_insert_awal = "INSERT INTO tb_setorsampah (tanggal_pengambilan, no_rekening, id_tps, nama_nasabah, kode_sampah, jenis_sampah, kategori, berat_kg, status_setoran, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_harga', ?)";
            $stmt_insert_awal = $conn->prepare($sql_insert_awal);

            if ($stmt_insert_awal === FALSE) {
                throw new Exception("Error preparing insert statement: " . $conn->error);
            }

            foreach ($setoran_items as $item) {
                $stmt_insert_awal->bind_param(
                    "ssisssdss",
                    $tanggal_pengambilan,
                    $no_rekening,
                    $id_tps_admin,
                    $nama_nasabah,
                    $item['kode_sampah'],
                    $item['jenis_sampah'],
                    $item['kategori_sampah'],
                    $item['berat_kg'],
                    $item['keterangan']
                );

                if (!$stmt_insert_awal->execute()) {
                    throw new Exception("Error mencatat setoran awal untuk '{$item['jenis_sampah']}': " . $stmt_insert_awal->error);
                }
            }

            $conn->commit();
            redirect_with_message("Setoran awal sampah berhasil dicatat untuk " . count($setoran_items) . " jenis sampah. Status: menunggu penjualan ke pengepul.", "success");

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Add initial deposit transaction failed: " . $e->getMessage());
            $message = "Terjadi kesalahan sistem saat mencatat setoran awal: " . $e->getMessage();
            $message_type = "danger";
        } finally {
            if ($stmt_insert_awal) $stmt_insert_awal->close();
        }
    }
}

// --- Penghapusan Setoran (GET) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id_transaksi'])) {
    $id_transaksi_to_delete = filter_var($_GET['id_transaksi'], FILTER_VALIDATE_INT);
    if ($id_transaksi_to_delete === FALSE || $id_transaksi_to_delete <= 0) {
        $message = "ID Transaksi tidak valid untuk dihapus.";
        $message_type = "danger";
    } else {
        $conn->begin_transaction();
        try {
            // Hapus setoran hanya jika statusnya 'pending_harga' dan milik TPS admin
            $sql_delete_setor = "DELETE FROM tb_setorsampah WHERE id_transaksi = ? AND id_tps = ? AND status_setoran = 'pending_harga'";
            $stmt_delete_setor = $conn->prepare($sql_delete_setor);
            if ($stmt_delete_setor === FALSE) {
                throw new Exception("Error preparing delete statement: " . $conn->error);
            }
            $stmt_delete_setor->bind_param("ii", $id_transaksi_to_delete, $id_tps_admin);
            if (!$stmt_delete_setor->execute()) {
                throw new Exception("Error menghapus setoran sampah: " . $stmt_delete_setor->error);
            }

            if ($stmt_delete_setor->affected_rows === 0) {
                throw new Exception("Transaksi setoran tidak ditemukan, status bukan 'pending harga', atau Anda tidak memiliki izin untuk menghapusnya.");
            }

            $conn->commit();
            redirect_with_message("Setoran sampah berhasil dihapus.", "success");

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Delete transaction failed: " . $e->getMessage());
            $message = "Terjadi kesalahan saat menghapus setoran: " . $e->getMessage();
            $message_type = "danger";
        } finally {
            if ($stmt_delete_setor) $stmt_delete_setor->close();
        }
    }
}

// --- Filtering, Sorting, dan Pagination ---
$filter_nasabah = $_GET['filter_nasabah'] ?? '';
$filter_sampah = $_GET['filter_sampah'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'tanggal_pengambilan';
$sort_order = $_GET['sort_order'] ?? 'DESC';

$setoran_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$where_conditions = ["s.id_tps = ?"];
$params_types = 'i';
$params_values = [$id_tps_admin];

if (!empty($filter_nasabah)) {
    $where_conditions[] = "s.no_rekening = ?";
    $params_types .= "s";
    $params_values[] = $filter_nasabah;
}
if (!empty($filter_sampah)) {
    $where_conditions[] = "s.kode_sampah = ?";
    $params_types .= "s";
    $params_values[] = $filter_sampah;
}

$where_clause = " WHERE " . implode(' AND ', $where_conditions);

$allowed_sort_columns = ['tanggal_pengambilan', 'jenis_sampah', 'nama_nasabah', 'berat_kg', 'status_setoran'];
$sort_by = in_array($sort_by, $allowed_sort_columns) ? $sort_by : 'tanggal_pengambilan';
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

$sql_total_setoran = "SELECT COUNT(s.id_transaksi) AS total_count FROM tb_setorsampah s JOIN tb_nasabah n ON s.no_rekening = n.no_rekening" . $where_clause;
$stmt_total = $conn->prepare($sql_total_setoran);
$stmt_total->bind_param($params_types, ...$params_values);
$stmt_total->execute();
$total_setoran_count = $stmt_total->get_result()->fetch_assoc()['total_count'];
$stmt_total->close();

$total_pages = ceil($total_setoran_count / $setoran_per_page);
$offset = max(0, ($current_page - 1) * $setoran_per_page);

$setoran_data = [];
$sql_fetch_setoran = "SELECT s.id_transaksi, s.tanggal_pengambilan, s.jenis_sampah, s.berat_kg, s.status_setoran, s.keterangan, s.no_rekening, s.harga_per_kg, s.total, s.tabungan_nasabah, s.pos_penimbangan, s.tps3r, n.nama_nasabah
                      FROM tb_setorsampah s
                      JOIN tb_nasabah n ON s.no_rekening = n.no_rekening"
                      . $where_clause .
                      " ORDER BY {$sort_by} {$sort_order}
                      LIMIT ? OFFSET ?";

$stmt_fetch_setoran = $conn->prepare($sql_fetch_setoran);
$params_types_fetch = $params_types . "ii";
$params_values_fetch = array_merge($params_values, [$setoran_per_page, $offset]);
$stmt_fetch_setoran->bind_param($params_types_fetch, ...$params_values_fetch);
$stmt_fetch_setoran->execute();
$result_fetch_setoran = $stmt_fetch_setoran->get_result();

if ($result_fetch_setoran) {
    while ($row = $result_fetch_setoran->fetch_assoc()) {
        $setoran_data[] = $row;
    }
}
$stmt_fetch_setoran->close();

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    </head>
<body>
<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Input Setoran Sampah Nasabah</h1>

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
        <button type="button" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center" data-modal-target="addSetoranAwalModal" data-modal-toggle="addSetoranAwalModal">
            <i class="fas fa-plus mr-2"></i> Catat Setoran
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Setoran Sampah</h2>

        <form action="setor_sampah.php" method="GET" class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="filter_nasabah" class="block mb-2 text-sm font-medium text-gray-900">Filter Nasabah:</label>
                    <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="filter_nasabah" name="filter_nasabah">
                        <option value="">Semua Nasabah</option>
                        <?php foreach ($nasabah_list as $nasabah): ?>
                            <option value="<?php echo htmlspecialchars($nasabah['no_rekening']); ?>" <?php echo (isset($_GET['filter_nasabah']) && $_GET['filter_nasabah'] == $nasabah['no_rekening']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nasabah['nama_nasabah'] . ' (' . $nasabah['no_rekening'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_sampah" class="block mb-2 text-sm font-medium text-gray-900">Filter Jenis Sampah:</label>
                    <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="filter_sampah" name="filter_sampah">
                        <option value="">Semua Jenis Sampah</option>
                        <?php foreach ($sampah_list as $kode => $data): ?>
                            <option value="<?php echo htmlspecialchars($kode); ?>" <?php echo (isset($_GET['filter_sampah']) && $_GET['filter_sampah'] == $kode) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($data['jenis_sampah']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="sort_by" class="block mb-2 text-sm font-medium text-gray-900">Urutkan Berdasarkan:</label>
                    <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="sort_by" name="sort_by">
                        <option value="tanggal_pengambilan" <?php echo ($sort_by == 'tanggal_pengambilan') ? 'selected' : ''; ?>>Tanggal</option>
                        <option value="jenis_sampah" <?php echo ($sort_by == 'jenis_sampah') ? 'selected' : ''; ?>>Jenis Sampah</option>
                        <option value="nama_nasabah" <?php echo ($sort_by == 'nama_nasabah') ? 'selected' : ''; ?>>Nama Nasabah</option>
                        <option value="berat_kg" <?php echo ($sort_by == 'berat_kg') ? 'selected' : ''; ?>>Berat (KG)</option>
                        <option value="status_setoran" <?php echo ($sort_by == 'status_setoran') ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                <div>
                    <label for="sort_order" class="block mb-2 text-sm font-medium text-gray-900">Urutan:</label>
                    <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" id="sort_order" name="sort_order">
                        <option value="DESC" <?php echo ($sort_order == 'DESC') ? 'selected' : ''; ?>>Terbaru / Terbesar</option>
                        <option value="ASC" <?php echo ($sort_order == 'ASC') ? 'selected' : ''; ?>>Terdahulu / Terkecil</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-4">
                <button type="submit" class="bg-purple-600 text-white px-5 py-2.5 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 font-semibold">Terapkan Filter & Urutan</button>
                <a href="setor_sampah.php" class="bg-gray-200 text-gray-800 px-5 py-2.5 rounded-lg shadow-md hover:bg-gray-300 transition-colors duration-200 text-center font-semibold">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transaksi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nasabah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Berat (KG)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai Total</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($setoran_data)): ?>
                        <?php foreach ($setoran_data as $setoran): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($setoran['id_transaksi']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($setoran['tanggal_pengambilan']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($setoran['nama_nasabah'] . ' (' . $setoran['no_rekening'] . ')'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($setoran['jenis_sampah']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($setoran['berat_kg'], 2, ',', '.'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo ($setoran['status_setoran'] == 'pending_harga') ? 'bg-yellow-100 text-yellow-800' : 'bg-purple-100 text-purple-800'; ?>">
                                        <?php echo ($setoran['status_setoran'] == 'pending_harga') ? 'Pending Harga' : 'Final'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo ($setoran['status_setoran'] == 'final' && isset($setoran['total'])) ? "Rp " . number_format($setoran['total'], 2, ',', '.') : "-"; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($setoran['keterangan'] ?? '-'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($setoran['status_setoran'] == 'pending_harga'): ?>
                                    <a href="setor_sampah.php?action=delete&id_transaksi=<?php echo urlencode($setoran['id_transaksi']); ?>"
                                       class="text-red-600 hover:text-red-900"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus setoran ini? Ini hanya bisa dilakukan untuk setoran yang belum difinalisasi.');">
                                        <i class="fas fa-trash-alt mr-1"></i> Hapus
                                    </a>
                                    <?php else: ?>
                                        <span class="text-gray-500">Sudah Difinalisasi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Belum ada riwayat setoran sampah yang cocok dengan filter.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-center items-center space-x-2 text-gray-700 mt-6">
            <?php if ($total_pages > 1): ?>
                <?php
                $pagination_base_url = 'setor_sampah.php?';
                if (!empty($filter_nasabah)) { $pagination_base_url .= 'filter_nasabah=' . urlencode($filter_nasabah) . '&'; }
                if (!empty($filter_sampah)) { $pagination_base_url .= 'filter_sampah=' . urlencode($filter_sampah) . '&'; }
                $pagination_base_url .= 'sort_by=' . urlencode($sort_by) . '&';
                $pagination_base_url .= 'sort_order=' . urlencode($sort_order) . '&';
                ?>
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $current_page - 1; ?>" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Previous</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Previous</span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                if ($end_page - $start_page < 4 && $total_pages > 4) {
                    if ($current_page <= 3) {
                        $end_page = min($total_pages, 5);
                    } elseif ($current_page >= $total_pages - 2) {
                        $start_page = max(1, $total_pages - 4);
                    }
                }
                if ($start_page > 1) echo '<span class="px-3 py-2">...</span>';
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $i; ?>"
                       class="px-3 py-2 border border-gray-300 rounded-md <?php echo ($i == $current_page) ? 'bg-purple-500 text-white' : 'hover:bg-gray-200'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) echo '<span class="px-3 py-2">...</span>';
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo $pagination_base_url; ?>page=<?php echo $current_page + 1; ?>" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Next</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Next</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <p class="text-sm text-gray-600 mt-4 text-center">Menampilkan <?php echo count($setoran_data); ?> dari <?php echo $total_setoran_count; ?> total setoran.</p>

    </div>
</div>

<div id="addSetoranAwalModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-2xl max-h-full">
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-600 dark:border-gray-600">
                <h3 class="text-lg font-semibold text-white dark:text-white">
                    Catat Setoran Sampah Awal
                </h3>
                <button type="button" class="text-gray-200 bg-transparent hover:bg-purple-700 hover:text-white rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="addSetoranAwalModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>
            <form action="setor_sampah.php" method="POST">
                <input type="hidden" name="action" value="add_setoran_awal">
                <div class="p-4 md:p-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="tanggal_pengambilan_awal" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Tanggal Pengambilan <span class="text-red-500">*</span></label>
                            <input type="date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" id="tanggal_pengambilan_awal" name="tanggal_pengambilan_awal" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div>
                            <label for="no_rekening_awal" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nasabah <span class="text-red-500">*</span></label>
                            <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" id="no_rekening_awal" name="no_rekening_awal" required>
                                <option value="">Pilih Nasabah</option>
                                <?php foreach ($nasabah_list as $nasabah): ?>
                                    <option value="<?php echo htmlspecialchars($nasabah['no_rekening']); ?>"><?php echo htmlspecialchars($nasabah['nama_nasabah'] . ' (' . $nasabah['no_rekening'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($nasabah_list) && $message_type !== "danger"): ?>
                                <small class="text-red-500 text-xs mt-1 block">Belum ada data nasabah. Mohon tambahkan nasabah terlebih dahulu.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-4 border-gray-200 dark:border-gray-600">
                    <h5 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Detail Sampah yang Disetor:</h5>
                    <div id="sampah-items-container" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 sampah-item-row hidden" id="sampah-item-template">
                            <div class="col-span-3">
                                <label for="kode_sampah_awal_" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Jenis Sampah <span class="text-red-500">*</span></label>
                                <select class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white kode_sampah_awal_select" id="kode_sampah_awal_" name="kode_sampah_awal[]">
                                    <option value="">Pilih Jenis Sampah</option>
                                    <?php foreach ($sampah_list as $kode => $data): ?>
                                        <option value="<?php echo htmlspecialchars($kode); ?>">
                                            <?php echo htmlspecialchars($data['jenis_sampah']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($sampah_list) && $message_type !== "danger"): ?>
                                    <small class="text-red-500 text-xs mt-1 block">Belum ada data jenis sampah. Mohon tambahkan jenis sampah terlebih dahulu.</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2">
                                <label for="berat_kg_awal_" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Berat (KG) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white berat_kg_awal_input" id="berat_kg_awal_" name="berat_kg_awal[]" min="0.01" value="0.00">
                            </div>
                            <div class="col-span-1 flex items-end">
                                <button type="button" class="w-full text-red-600 hover:text-red-800 bg-transparent border border-red-600 hover:border-red-800 rounded-lg py-2.5 flex items-center justify-center remove-sampah-item">
                                    <i class="fas fa-minus-circle mr-1"></i> Hapus
                                </button>
                            </div>
                            <div class="col-span-full">
                                <label for="keterangan_awal_" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Keterangan (Opsional)</label>
                                <textarea class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white keterangan_awal_textarea" id="keterangan_awal_" name="keterangan_awal[]" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="mt-3 text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700" id="add-sampah-item">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Jenis Sampah
                    </button>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-600">
                    <button type="submit" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-purple-600 dark:hover:bg-purple-700 dark:focus:ring-purple-800">Catat Setoran Awal</button>
                    <button data-modal-hide="addSetoranAwalModal" type="button" class="ms-3 text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const addSetoranAwalModalEl = document.getElementById('addSetoranAwalModal');
    const sampahItemsContainer = document.getElementById('sampah-items-container');
    const addSampahItemButton = document.getElementById('add-sampah-item');
    const sampahItemTemplate = document.getElementById('sampah-item-template');

    let templateHtml = '';
    if (sampahItemTemplate) {
        templateHtml = sampahItemTemplate.outerHTML;
        sampahItemTemplate.remove();
    }

    let itemCounter = 0;

    function addSampahItem() {
        itemCounter++;
        let newItemHtml = templateHtml.replace(/id="kode_sampah_awal_"/g, `id="kode_sampah_awal_${itemCounter}"`)
                                     .replace(/id="berat_kg_awal_"/g, `id="berat_kg_awal_${itemCounter}"`)
                                     .replace(/id="keterangan_awal_"/g, `id="keterangan_awal_${itemCounter}"`)
                                     .replace(/hidden"/g, `"`)
                                     .replace(/sampah-item-row"/g, `sampah-item-row" data-item-id="${itemCounter}"`);

        const newRow = document.createElement('div');
        newRow.innerHTML = newItemHtml;
        const actualNewRow = newRow.querySelector('.sampah-item-row');
        sampahItemsContainer.appendChild(actualNewRow);

        actualNewRow.querySelector('.remove-sampah-item').addEventListener('click', function() {
            if (sampahItemsContainer.children.length > 1) {
                actualNewRow.remove();
            } else {
                alert('Minimal harus ada satu jenis sampah yang disetor.');
            }
        });
    }

    const addSetoranToggle = document.querySelector('[data-modal-toggle="addSetoranAwalModal"]');
    if (addSetoranToggle) {
        addSetoranToggle.addEventListener('click', function() {
            addSetoranAwalModalEl.querySelector('form').reset();
            sampahItemsContainer.innerHTML = '';
            itemCounter = 0;
            addSampahItem();
            document.getElementById('tanggal_pengambilan_awal').value = new Date().toISOString().slice(0,10);
            document.getElementById('no_rekening_awal').value = '';
        });
    }

    if (addSampahItemButton) {
        addSampahItemButton.addEventListener('click', function() {
            addSampahItem();
        });
    }

    // Panggil fungsi untuk menambahkan baris pertama saat DOM dimuat
    if (addSetoranAwalModalEl) {
        addSampahItem();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>