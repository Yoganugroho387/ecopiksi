<?php
// admin/index.php
// Set halaman aktif untuk sidebar
$current_page = 'dashboard';
require_once '../includes/header.php'; // Includes session_start() and auth check, and sets up the HTML head and body start.
require_once '../config/db.php'; // Database connection

// Ensure only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil id_tps dari sesi admin yang sudah login
$id_tps_admin = $_SESSION['id_tps'];

// --- FUNGSI HELPER: Redirect dengan Pesan ---
function redirect_with_message($msg, $type, $params = [], $anchor = '') {
    $queryString = http_build_query(array_merge(['msg' => $msg, 'type' => $type], $params));
    header("Location: index.php?" . $queryString . ($anchor ? '#' . $anchor : ''));
    exit();
}

// --- PHP Logic for Dashboard Cards and Price Management (CRUD tb_harga_sampah) ---
$price_message = ''; // For price delete messages

// --- HANDLE FORM SUBMISSIONS (Hanya DELETE tb_harga_sampah yang tersisa di sini) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil parameter filter yang mungkin ada di URL sebelum redirect, untuk dipertahankan
    $current_filter_params = [];
    if (isset($_GET['admin_search'])) $current_filter_params['admin_search'] = $_GET['admin_search'];
    if (isset($_GET['admin_page'])) $current_filter_params['admin_page'] = $_GET['admin_page'];

    // Jika ada pesan yang belum di-redirect, tampilkan di halaman ini
    if ($price_message) {
        // No redirect, let message display on current page
    } else {
        // Fallback: Jika ada POST tapi tidak ada action yang cocok atau redirect, 
        // bisa tambahkan redirect default untuk membersihkan POST request.
        // redirect_with_message("Permintaan tidak valid.", "danger", $current_filter_params);
    }
}
// --- Action: Delete Price (GET Request) ---
elseif (isset($_GET['action']) && $_GET['action'] == 'delete_price' && isset($_GET['id'])) {
    $id_harga_delete = intval($_GET['id']);
    $id_tps_admin = $_SESSION['id_tps']; // Pastikan id_tps_admin sudah tersedia

    if ($id_harga_delete <= 0) {
        redirect_with_message("ID Harga tidak valid untuk dihapus.", "danger", [], 'manajemen-harga');
    } else {
        // Ambil jenis_sampah untuk pesan konfirmasi
        $jenis_sampah_info = "Data Harga"; // Default jika tidak ditemukan
        $sql_info = "SELECT jenis_sampah FROM tb_harga_sampah WHERE id = ? AND id_tps = ?";
        $stmt_info = $conn->prepare($sql_info);
        $stmt_info->bind_param("ii", $id_harga_delete, $id_tps_admin);
        $stmt_info->execute();
        $result_info = $stmt_info->get_result();
        if ($result_info->num_rows > 0) {
            $jenis_sampah_info = $result_info->fetch_assoc()['jenis_sampah'];
        }
        $stmt_info->close();

        // Tambahkan kondisi id_tps untuk memastikan admin tidak bisa menghapus harga dari TPS lain
        $sql_delete_price = "DELETE FROM tb_harga_sampah WHERE id = ? AND id_tps = ?";
        $stmt_delete_price = $conn->prepare($sql_delete_price);
        if ($stmt_delete_price) {
            $stmt_delete_price->bind_param("ii", $id_harga_delete, $id_tps_admin);
            if ($stmt_delete_price->execute()) {
                if ($stmt_delete_price->affected_rows > 0) {
                    redirect_with_message("Harga untuk '{$jenis_sampah_info}' berhasil dihapus.", "success", [], 'manajemen-harga');
                } else {
                    redirect_with_message("Gagal menghapus: Data harga tidak ditemukan atau Anda tidak memiliki izin.", "danger", [], 'manajemen-harga');
                }
            } else {
                // Check for foreign key constraint violation (if any)
                if ($conn->errno == 1451) {
                    redirect_with_message("Gagal menghapus: Data harga ini terkait dengan transaksi lain.", "danger", [], 'manajemen-harga');
                } else {
                    redirect_with_message("Gagal menghapus harga: " . $stmt_delete_price->error, "danger", [], 'manajemen-harga');
                }
            }
            $stmt_delete_price->close();
        } else {
            redirect_with_message("Error preparing delete statement.", "danger", [], 'manajemen-harga');
        }
    }
}


// ----------------------------------------------------
// FETCHING PRICES FOR ADMIN (with Pagination and Search)
// ----------------------------------------------------
$admin_items_per_page = 5; // Display 5 items per page for admin view
$admin_current_page = isset($_GET['admin_page']) ? (int)$_GET['admin_page'] : 1;
if ($admin_current_page < 1) $admin_current_page = 1;

$admin_search_query = isset($_GET['admin_search']) ? $conn->real_escape_string($_GET['admin_search']) : '';
$admin_search_condition = " WHERE id_tps = " . $id_tps_admin;
if (!empty($admin_search_query)) {
    $admin_search_condition .= " AND jenis_sampah LIKE '%" . $admin_search_query . "%'";
}

// Count total items for admin price list pagination
$sql_admin_total_items = "SELECT COUNT(id) AS total_items FROM tb_harga_sampah" . $admin_search_condition;
$result_admin_total_items = $conn->query($sql_admin_total_items);
$admin_total_items = $result_admin_total_items->fetch_assoc()['total_items'];
$admin_total_pages = ceil($admin_total_items / $admin_items_per_page);

// Pastikan current page tidak melebihi total pages yang ada
if ($admin_total_pages > 0 && $admin_current_page > $admin_total_pages) $admin_current_page = $admin_total_pages;
// Jika tidak ada item sama sekali, pastikan current_page=1 untuk mencegah offset negatif
if ($admin_total_items == 0) $admin_current_page = 1;

$admin_offset = ($admin_current_page - 1) * $admin_items_per_page;
if ($admin_offset < 0) $admin_offset = 0; // Pastikan offset tidak negatif

$daftar_harga_admin = [];
$sql_fetch_prices = "SELECT id, jenis_sampah, harga_per_kg FROM tb_harga_sampah" . $admin_search_condition . " ORDER BY jenis_sampah ASC LIMIT ? OFFSET ?";
$stmt_fetch_prices = $conn->prepare($sql_fetch_prices);
$stmt_fetch_prices->bind_param("ii", $admin_items_per_page, $admin_offset);
$stmt_fetch_prices->execute();
$result_fetch_prices = $stmt_fetch_prices->get_result();

if ($result_fetch_prices && $result_fetch_prices->num_rows > 0) {
    while ($row = $result_fetch_prices->fetch_assoc()) {
        $daftar_harga_admin[] = $row;
    }
}
$stmt_fetch_prices->close();


// --- Fetch Dashboard Data (Existing Logic - keep this as is) ---
$totalNasabah = 0;
$totalPencairanPending = 0;
$totalPenjualanBulanIni = 0;
$totalStokSampahPending = 0;

// Get total nasabah
$sql_nasabah = "SELECT COUNT(no_rekening) AS total_nasabah FROM tb_nasabah WHERE role = 'nasabah' AND id_tps = ?";
$stmt_nasabah = $conn->prepare($sql_nasabah);
$stmt_nasabah->bind_param("i", $id_tps_admin);
$stmt_nasabah->execute();
$result_nasabah = $stmt_nasabah->get_result();
if ($result_nasabah) {
    $row = $result_nasabah->fetch_assoc();
    $totalNasabah = $row['total_nasabah'];
}
$stmt_nasabah->close();

// Get total pending withdrawal requests
$sql_pencairan_pending = "SELECT COUNT(id_pencairan) AS total_pending FROM tb_pencairan_dana WHERE status = 'pending' AND id_tps = ?";
$stmt_pencairan = $conn->prepare($sql_pencairan_pending);
$stmt_pencairan->bind_param("i", $id_tps_admin);
$stmt_pencairan->execute();
$result_pencairan_pending = $stmt_pencairan->get_result();
if ($result_pencairan_pending) {
    $row = $result_pencairan_pending->fetch_assoc();
    $totalPencairanPending = $row['total_pending'];
}
$stmt_pencairan->close();

// Get total sales this month (example)


// Get total pending trash stock (from 'pending_harga' status)
$sql_stok_sampah_pending = "SELECT SUM(berat_kg) AS total_stok FROM tb_setorsampah WHERE status_setoran = 'pending_harga' AND id_tps = ?";
$stmt_stok = $conn->prepare($sql_stok_sampah_pending);
$stmt_stok->bind_param("i", $id_tps_admin);
$stmt_stok->execute();
$result_stok_sampah_pending = $stmt_stok->get_result();
if ($result_stok_sampah_pending) {
    $row = $result_stok_sampah_pending->fetch_assoc();
    $totalStokSampahPending = $row['total_stok'] ?? 0;
}
$stmt_stok->close();

$conn->close(); // Close database connection after all operations


// --- PHP logic to check and clear session flag for the popup ---
// Check if the welcome popup flag is set from login.php
$show_welcome_popup = false;
if (isset($_SESSION['show_welcome_popup']) && $_SESSION['show_welcome_popup'] === true) {
    $show_welcome_popup = true;
    unset($_SESSION['show_welcome_popup']); // Clear the flag after reading it
}

// Ambil pesan dari parameter GET setelah redirect (jika ada)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    // Menampilkan pesan alert dari redirect dengan kelas Tailwind yang sesuai
    $price_message = '
        <div class="p-4 mb-4 text-sm rounded-lg ' . 
            (htmlspecialchars($_GET['type']) == 'success' ? 'bg-purple-100 text-purple-800 border border-purple-400' : 'bg-red-100 text-red-800 border border-red-400') . 
            '" role="alert">
            ' . htmlspecialchars($_GET['msg']) . '
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-' . 
            (htmlspecialchars($_GET['type']) == 'success' ? 'purple-500' : 'red-500') . 
            ' rounded-lg focus:ring-2 focus:ring-' . 
            (htmlspecialchars($_GET['type']) == 'success' ? 'purple-400' : 'red-400') . 
            ' p-1.5 hover:bg-' . 
            (htmlspecialchars($_GET['type']) == 'success' ? 'purple-200' : 'red-200') . 
            ' inline-flex h-8 w-8" onclick="this.parentElement.style.display=\'none\';" aria-label="Close">
            <span class="sr-only">Close</span>
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>';
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Dashboard Admin</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-purple-700 text-white rounded-lg shadow-lg overflow-hidden transform transition duration-300 hover:scale-105 hover:shadow-xl">
            <div class="p-5 flex items-center justify-between">
                <div class="flex-shrink-0">
                    <i class="fas fa-users text-4xl opacity-75"></i>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase font-bold mb-1 opacity-80">Total Nasabah</div>
                    <div class="text-4xl font-extrabold"><?php echo $totalNasabah; ?></div>
                </div>
            </div>
            <div class="bg-purple-800 px-5 py-3 text-right">
                <a href="nasabah.php" class="text-white text-sm hover:underline flex items-center justify-end">
                    Lihat Detail <i class="fas fa-arrow-circle-right ml-2"></i>
                </a>
            </div>
        </div>

        <div class="bg-purple-600 text-white rounded-lg shadow-lg overflow-hidden transform transition duration-300 hover:scale-105 hover:shadow-xl">
            <div class="p-5 flex items-center justify-between">
                <div class="flex-shrink-0">
                    <i class="fas fa-hourglass-half text-4xl opacity-75"></i>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase font-bold mb-1 opacity-80">Permintaan Pencairan Pending</div>
                    <div class="text-4xl font-extrabold"><?php echo $totalPencairanPending; ?></div>
                </div>
            </div>
            <div class="bg-purple-700 px-5 py-3 text-right">
                <a href="pencairan.php" class="text-white text-sm hover:underline flex items-center justify-end">
                    Kelola Pencairan <i class="fas fa-arrow-circle-right ml-2"></i>
                </a>
            </div>
        </div>
        <div class="bg-purple-200 text-gray-900 rounded-lg shadow-lg overflow-hidden transform transition duration-300 hover:scale-105 hover:shadow-xl">
            <div class="p-5 flex items-center justify-between">
                <div class="flex-shrink-0">
                    <i class="fas fa-box-open text-4xl opacity-75"></i>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase font-bold mb-1 opacity-80">Stok Sampah Pending</div>
                    <div class="text-3xl font-extrabold"><?php echo number_format($totalStokSampahPending, 2, ',', '.'); ?> KG</div>
                </div>
            </div>
            <div class="bg-purple-300 px-5 py-3 text-right">
                <a href="stok_sampah.php" class="text-gray-900 text-sm hover:underline flex items-center justify-end">
                    Lihat Detail Stok <i class="fas fa-arrow-circle-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>

    <div id="manajemen-harga" class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <h2 class="text-lg font-bold text-gray-800 mb-6">Manajemen Harga Sampah</h2>
        <?php echo $price_message; ?>

        <div class="flex justify-between items-center mb-6">
            <div class="flex-grow">
                <form action="#manajemen-harga" method="GET" class="w-full max-w-sm">
                    <div class="relative">
                        <input type="text" name="admin_search" placeholder="Cari jenis sampah..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                               value="<?php echo htmlspecialchars($admin_search_query); ?>">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <?php if (!empty($admin_search_query)): ?>
                        <a href="index.php?admin_page=<?php echo $admin_current_page; ?>#manajemen-harga" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-red-500" 
                           onclick="document.querySelector('input[name=admin_search]').value=''; this.closest('form').submit();">
                            <i class="fas fa-times-circle"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <a href="tambah_harga_baru.php" class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 flex items-center ml-4">
                <i class="fas fa-plus mr-2"></i> Tambah Harga Baru
            </a>
        </div>


        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Jenis Sampah</th>
                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Harga per Kg (Rp)</th>
                        <th class="py-3 px-4 text-center text-sm font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (!empty($daftar_harga_admin)): ?>
                        <?php foreach ($daftar_harga_admin as $item): ?>
                            <tr>
                                <td class="py-3 px-4 text-gray-800"><?php echo htmlspecialchars($item['jenis_sampah']); ?></td>
                                <td class="py-3 px-4 text-gray-800">
                                    <span id="current_price_<?php echo str_replace(' ', '_', $item['jenis_sampah']); ?>">
                                        Rp <?php echo number_format($item['harga_per_kg'], 0, ',', '.'); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="edit_sampah.php?id=<?php echo htmlspecialchars($item['id']); ?>" 
                                       class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors duration-200 text-sm mr-2">
                                        Edit Harga
                                    </a>
                                    <a href="index.php?action=delete_price&id=<?php echo htmlspecialchars($item['id']); ?>&admin_page=<?php echo $admin_current_page; ?>&admin_search=<?php echo htmlspecialchars($admin_search_query); ?>#manajemen-harga"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus harga untuk jenis sampah <?php echo htmlspecialchars($item['jenis_sampah']); ?>? Ini tidak akan menghapus jenis sampah itu sendiri.');"
                                       class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition-colors duration-200 text-sm">
                                        Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-600">Tidak ada data harga sampah yang ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-center items-center space-x-2 text-gray-700 mt-6">
            <?php if ($admin_total_pages > 1): ?>
                <?php
                    // Base URL for pagination links, preserving search query
                    $admin_pagination_base_url = '?';
                    if (!empty($admin_search_query)) {
                        $admin_pagination_base_url .= 'admin_search=' . urlencode($admin_search_query) . '&';
                    }
                ?>

                <?php if ($admin_current_page > 1): ?>
                    <a href="<?php echo $admin_pagination_base_url; ?>admin_page=<?php echo $admin_current_page - 1; ?>#manajemen-harga" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Previous</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Previous</span>
                <?php endif; ?>

                <?php
                $admin_start_page = max(1, $admin_current_page - 2);
                $admin_end_page = min($admin_total_pages, $admin_current_page + 2);

                if ($admin_end_page - $admin_start_page < 4) {
                    $admin_end_page = min($admin_total_pages, $admin_start_page + 4);
                }
                if ($admin_end_page - $admin_start_page < 4) { // Re-check after first adjustment
                    $admin_start_page = max(1, $admin_end_page - 4);
                }

                if ($admin_start_page > 1) echo '<span class="px-3 py-2">...</span>';
                for ($i = $admin_start_page; $i <= $admin_end_page; $i++): ?>
                    <a href="<?php echo $admin_pagination_base_url; ?>admin_page=<?php echo $i; ?>#manajemen-harga"
                       class="px-3 py-2 border border-gray-300 rounded-md <?php echo ($i == $admin_current_page) ? 'bg-purple-500 text-white' : 'hover:bg-gray-200'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($admin_end_page < $admin_total_pages) echo '<span class="px-3 py-2">...</span>';
                ?>

                <?php if ($admin_current_page < $admin_total_pages): ?>
                    <a href="<?php echo $admin_pagination_base_url; ?>admin_page=<?php echo $admin_current_page + 1; ?>#manajemen-harga" class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-200">Next</a>
                <?php else: ?>
                    <span class="px-3 py-2 border border-gray-300 rounded-md text-gray-400 cursor-not-allowed">Next</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <p class="text-sm text-gray-600 mt-4 text-center">Menampilkan <?php echo count($daftar_harga_admin); ?> dari <?php echo $admin_total_items; ?> jenis sampah.</p>
    </div>

</div>


<div id="info-modal" tabindex="-1" aria-hidden="true" class="hidden flowbite-modal fixed top-0 right-0 left-0 z-50 w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-600 dark:border-gray-600">
                <h3 class="text-lg font-semibold text-white dark:text-white">
                    Sekilas Info Bank Sampah
                </h3>
                <button type="button" class="text-gray-200 bg-transparent hover:bg-purple-700 hover:text-white rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="info-modal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>
            <div class="p-4 md:p-5 text-left">
                <p class="text-base text-gray-700 dark:text-gray-400 mb-3">
                    Bank sampah adalah sebuah konsep pengumpulan sampah yang melibatkan masyarakat. Sampah yang dikumpulkan akan ditimbang dan dinilai berdasarkan jenisnya, lalu hasilnya dicatat sebagai tabungan bagi nasabah.
                </p>
                <p class="text-base text-gray-700 dark:text-gray-400 mb-3">
                    Tujuannya adalah untuk mendorong pengelolaan sampah yang bertanggung jawab dan memberikan nilai ekonomi dari sampah.
                </p>
                <p class="text-base text-gray-700 dark:text-gray-400">
                    Ada dua jenis status setoran:
                    <ul class="list-disc list-inside ml-4 mt-2 text-sm text-gray-600 dark:text-gray-400">
                        <li>**Pending Harga:** Sampah sudah disetor dan ditimbang, namun harganya belum ditentukan.</li>
                        <li>**Final:** Harga sudah ditentukan, dan nilai sampah sudah masuk ke saldo nasabah.</li>
                    </ul>
                </p>
                <button type="button" id="dismiss-info-popup" class="mt-5 w-full text-white bg-purple-500 hover:bg-purple-600 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-purple-600 dark:hover:bg-purple-700 dark:focus:ring-purple-800">
                    Mulai Mengelola
                </button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>



    
<?php require_once '../includes/footer.php'; ?>