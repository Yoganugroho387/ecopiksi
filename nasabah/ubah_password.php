<?php
// nasabah/ubah_password.php
// Set halaman aktif untuk sidebar nasabah
$current_page = 'ubah_password';

require_once '../includes/header.php';
require_once '../config/db.php';

// Pastikan hanya nasabah yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'nasabah') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

$no_rekening_nasabah = $_SESSION['no_rekening'];
$id_tps_nasabah = $_SESSION['id_tps']; // Tambahkan: Ambil id_tps dari sesi nasabah

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // 1. Ambil password lama (hash) dari database
    // Perbaikan: Tambahkan id_tps ke klausa WHERE
    $sql_get_password = "SELECT `password` FROM `tb_nasabah` WHERE `no_rekening` = ? AND `id_tps` = ?";
    $stmt_get_password = $conn->prepare($sql_get_password);
    $stmt_get_password->bind_param("si", $no_rekening_nasabah, $id_tps_nasabah);
    $stmt_get_password->execute();
    $result_get_password = $stmt_get_password->get_result();
    $user_data = $result_get_password->fetch_assoc();
    $stmt_get_password->close();

    if (!$user_data) {
        $message = "Akun tidak ditemukan.";
        $message_type = "danger";
    } elseif (!password_verify($current_password, $user_data['password'])) {
        $message = "Password saat ini salah. Mohon periksa kembali.";
        $message_type = "danger";
    } elseif (strlen($new_password) < 6) {
        $message = "Password baru minimal harus 6 karakter.";
        $message_type = "danger";
    } elseif ($new_password !== $confirm_new_password) {
        $message = "Konfirmasi password baru tidak cocok.";
        $message_type = "danger";
    } else {
        // 2. Hash password baru
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 3. Update password di database
        // Perbaikan: Tambahkan id_tps ke klausa WHERE
        $sql_update_password = "UPDATE `tb_nasabah` SET `password` = ? WHERE `no_rekening` = ? AND `id_tps` = ?";
        $stmt_update_password = $conn->prepare($sql_update_password);
        $stmt_update_password->bind_param("ssi", $hashed_new_password, $no_rekening_nasabah, $id_tps_nasabah);

        if ($stmt_update_password->execute()) {
            if ($stmt_update_password->affected_rows > 0) {
                $message = "Password berhasil diubah!";
                $message_type = "success";
            } else {
                $message = "Password tidak diubah. Mungkin password baru sama dengan yang lama.";
                $message_type = "info";
            }
        } else {
            $message = "Error mengubah password: " . $stmt_update_password->error;
            $message_type = "danger";
        }
        $stmt_update_password->close();
    }
    // Redirect untuk membersihkan data POST
    header("Location: ubah_password.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}

$conn->close();

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Ubah Password</h1>

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

   <div class="bg-white rounded-lg shadow-md p-6 dark:bg-gray-800">
    <h2 class="text-lg font-semibold text-gray-800 mb-4 dark:text-white">Form Ubah Password</h2>
    <form action="ubah_password.php" method="POST">
        
        <!-- Password Saat Ini -->
        <div class="mb-5">
            <label for="current_password" class="block mb-2 font-medium text-gray-900 dark:text-white">
                Password Saat Ini <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="password" id="current_password" name="current_password"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                    required>
                <button type="button"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    onclick="togglePassword('current_password', this)">
                    <!-- Mata tertutup -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 eye-closed" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3l18 18M9.88 9.88a3 3 0 104.24 4.24M6.62 6.62A10.97 10.97 0 003 12c1.5 3.5 5.36 6 9 6 1.82 0 3.5-.5 4.95-1.35M17.38 17.38A10.97 10.97 0 0021 12c-1.5-3.5-5.36-6-9-6-1.82 0-3.5.5-4.95 1.35"/>
                    </svg>
                    <!-- Mata terbuka -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 eye-open hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Password Baru -->
        <div class="mb-5">
            <label for="new_password" class="block mb-2 font-medium text-gray-900 dark:text-white">
                Password Baru <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="password" id="new_password" name="new_password" minlength="6"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                    required>
                <button type="button"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    onclick="togglePassword('new_password', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 eye-closed" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3l18 18M9.88 9.88a3 3 0 104.24 4.24M6.62 6.62A10.97 10.97 0 003 12c1.5 3.5 5.36 6 9 6 1.82 0 3.5-.5 4.95-1.35M17.38 17.38A10.97 10.97 0 0021 12c-1.5-3.5-5.36-6-9-6-1.82 0-3.5.5-4.95 1.35"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 eye-open hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Minimal 6 karakter.</p>
        </div>

        <!-- Konfirmasi Password Baru -->
        <div class="mb-5">
            <label for="confirm_new_password" class="block mb-2 font-medium text-gray-900 dark:text-white">
                Konfirmasi Password Baru <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="password" id="confirm_new_password" name="confirm_new_password"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                    required>
                <button type="button"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    onclick="togglePassword('confirm_new_password', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 eye-closed" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 3l18 18M9.88 9.88a3 3 0 104.24 4.24M6.62 6.62A10.97 10.97 0 003 12c1.5 3.5 5.36 6 9 6 1.82 0 3.5-.5 4.95-1.35M17.38 17.38A10.97 10.97 0 0021 12c-1.5-3.5-5.36-6-9-6-1.82 0-3.5.5-4.95 1.35"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 eye-open hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="text-right">
            <button type="submit"
                class="bg-purple-600 text-white px-5 py-2 rounded-lg shadow-md hover:bg-purple-700 transition-colors duration-200 font-semibold">
                Ubah Password
            </button>
        </div>
    </form>
</div>

<script>
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const eyeClosed = button.querySelector('.eye-closed');
    const eyeOpen = button.querySelector('.eye-open');

    input.type = input.type === 'password' ? 'text' : 'password';
    eyeClosed.classList.toggle('hidden');
    eyeOpen.classList.toggle('hidden');
}
</script>

</div>

<?php require_once '../includes/footer.php'; ?>