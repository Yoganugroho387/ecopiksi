<?php
// admin/edit_sampah.php
// Set halaman aktif untuk sidebar (sesuaikan jika ada menu khusus untuk harga)
$current_page = 'edit_harga_sampah'; // Atau 'dashboard' jika tidak ada menu terpisah
require_once '../includes/header.php'; // Includes session_start() and auth check, and sets up the HTML head and body start.
require_once '../config/db.php'; // Database connection

// Ensure only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = ''; // Pesan sukses atau error

// --- Ambil data harga yang akan diedit ---
$jenis_sampah_to_edit = '';
$harga_per_kg_current = 0.00;
$sampah_id = 0; // Untuk mengambil berdasarkan ID jika lebih mudah

// Pastikan ada parameter 'id' atau 'jenis_sampah' di URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $sampah_id = (int)$_GET['id'];
    $sql_fetch_item = "SELECT id, jenis_sampah, harga_per_kg FROM tb_harga_sampah WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_item);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $sampah_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows > 0) {
            $item = $result_fetch->fetch_assoc();
            $jenis_sampah_to_edit = $item['jenis_sampah'];
            $harga_per_kg_current = $item['harga_per_kg'];
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Data sampah tidak ditemukan.</div>';
            $sampah_id = 0; // Setel ulang ID jika tidak ditemukan
        }
        $stmt_fetch->close();
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error mempersiapkan query.</div>';
    }
} elseif (isset($_GET['jenis_sampah'])) {
    // Alternatif jika Anda mengirim 'jenis_sampah' via GET
    $jenis_sampah_param = $conn->real_escape_string($_GET['jenis_sampah']);
    $sql_fetch_item = "SELECT id, jenis_sampah, harga_per_kg FROM tb_harga_sampah WHERE jenis_sampah = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_item);
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("s", $jenis_sampah_param);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows > 0) {
            $item = $result_fetch->fetch_assoc();
            $sampah_id = $item['id'];
            $jenis_sampah_to_edit = $item['jenis_sampah'];
            $harga_per_kg_current = $item['harga_per_kg'];
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Data sampah tidak ditemukan.</div>';
        }
        $stmt_fetch->close();
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error mempersiapkan query.</div>';
    }
} else {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">ID atau Jenis Sampah tidak ditentukan.</div>';
}

// Redirect jika ID tidak valid atau tidak ada data
if ($sampah_id === 0 && empty($message)) {
    header("Location: index.php"); // Kembali ke dashboard jika tidak ada ID atau data
    exit();
}

// --- Proses update harga ketika form disubmit ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_price_form'])) {
    $updated_id = $_POST['id'];
    $updated_jenis_sampah = $_POST['jenis_sampah']; // Ini readonly, hanya untuk referensi
    $new_price_input = $_POST['new_price'];

    // Sanitasi input harga
    $cleaned_price = str_replace('.', '', $new_price_input); // Hapus pemisah ribuan (titik)
    $cleaned_price = str_replace(',', '.', $cleaned_price); // Ganti koma desimal jadi titik

    if (empty($updated_jenis_sampah) || $updated_id === 0) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">ID atau Jenis Sampah tidak valid untuk update.</div>';
    } elseif (!is_numeric($cleaned_price)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Harga baru harus berupa angka.</div>';
    } else {
        $new_price = (float)$cleaned_price;
        if ($new_price < 0) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Harga tidak boleh negatif.</div>';
        } else {
            $sql_update = "UPDATE tb_harga_sampah SET harga_per_kg = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("di", $new_price, $updated_id);
                if ($stmt_update->execute()) {
                    $message = '<div class="bg-purple-100 border border-purple-400 text-purple-700 px-4 py-3 rounded relative" role="alert">Harga berhasil diperbarui!</div>';
                    // Update current values for display after successful update
                    $harga_per_kg_current = $new_price; 
                    // Optional: Redirect back to dashboard after a short delay
                    // header("Location: index.php?admin_page=" . (isset($_GET['admin_page']) ? $_GET['admin_page'] : 1) . "&admin_search=" . (isset($_GET['admin_search']) ? $_GET['admin_search'] : '') . "#manajemen-harga&status=success_update");
                    // exit();
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Gagal memperbarui harga: ' . $stmt_update->error . '</div>';
                }
                $stmt_update->close();
            } else {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error mempersiapkan statement update.</div>';
            }
        }
    }
}

$conn->close();
?>

<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Edit Harga Sampah</h1>
        <a href="index.php#manajemen-harga" class="text-blue-600 hover:text-blue-800 font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
        </a>
    </div>

    <?php echo $message; ?>

    <?php if ($sampah_id !== 0): // Tampilkan form hanya jika data ditemukan ?>
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-lg mx-auto">
        <form action="edit_sampah.php?id=<?php echo htmlspecialchars($sampah_id); ?>" method="POST" class="space-y-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($sampah_id); ?>">
            <input type="hidden" name="update_price_form" value="1">

            <div>
                <label for="jenis_sampah" class="block text-sm font-medium text-gray-700">Jenis Sampah</label>
                <input type="text" id="jenis_sampah" name="jenis_sampah" value="<?php echo htmlspecialchars($jenis_sampah_to_edit); ?>" readonly
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2">
            </div>

            <div>
                <label for="new_price" class="block text-sm font-medium text-gray-700">Harga per Kg (Rp)</label>
                <input type="number" step="0.01" id="new_price" name="new_price" value="<?php echo htmlspecialchars($harga_per_kg_current); ?>"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2" placeholder="Cth: 2500">
            </div>

            <div class="flex justify-end space-x-3">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    Simpan Perubahan
                </button>
                <a href="index.php#manajemen-harga" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Batal
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php
// Bagian penutup dari layout yang dimulai di header.php
?>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>

    <script>
        // No specific modal JS needed for this page, as it's a direct form.
        // Keep desktop warning if desired, or remove if not applicable to this page.
        // It's good practice to keep common JS in a separate file or always include it in header/footer.
        const desktopWarningModal = document.getElementById('desktop-warning-modal');
        const minDesktopWidth = 768; // Tailwind's 'md' breakpoint

        const showPopup = <?php echo json_encode(isset($_SESSION['show_desktop_warning']) && $_SESSION['show_desktop_warning'] === true); ?>;

        if (showPopup && window.innerWidth < minDesktopWidth) {
            const modal = new Flowbite.Modal(desktopWarningModal);
            modal.show();
            <?php unset($_SESSION['show_desktop_warning']); // Clear the flag after showing ?>
        }

        // Sidebar toggle script (copy from admin/index.php if not in a global JS file)
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar-wrapper');
            const pageContent = document.getElementById('page-content-wrapper');
            const body = document.body;

            if (sidebarToggle && body) {
                sidebarToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                });
            }

            if (pageContent && sidebar && sidebarToggle && body) {
                pageContent.addEventListener('click', (event) => {
                    if (window.innerWidth < minDesktopWidth && !body.classList.contains('sidebar-collapsed')) {
                        if (!sidebar.contains(event.target) && event.target !== sidebarToggle && !sidebarToggle.contains(event.target)) {
                            body.classList.add('sidebar-collapsed');
                        }
                    }
                });
            }

            function setSidebarState() {
                if (window.innerWidth >= minDesktopWidth) {
                    body.classList.remove('sidebar-collapsed');
                } else {
                    body.classList.add('sidebar-collapsed');
                }
            }

            window.addEventListener('load', setSidebarState);
            window.addEventListener('resize', setSidebarState);
        });
    </script>
<?php require_once '../includes/footer.php'; ?>