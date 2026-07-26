<?php
session_start();
require_once '../config/db.php';
// Pastikan hanya admin yang bisa mengakses halaman ini
$current_page = 'ubah_password'; 
// Pastikan hanya admin yang bisa mengakses halaman ini
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$message_type = '';

$username = $_SESSION['username'];
// Ambil id_tps dari sesi admin yang sudah login
$id_tps_admin = $_SESSION['id_tps']; // Ambil username admin dari sesi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Validasi input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "Semua field harus diisi.";
        $message_type = "danger";
    } elseif ($new_password !== $confirm_password) {
        $message = "Kata sandi baru dan konfirmasi kata sandi tidak cocok.";
        $message_type = "danger";
    } else {
        // 2. Ambil hash kata sandi lama dari database
        $sql_fetch = "SELECT password FROM tb_nasabah WHERE username = ?";
        $stmt_fetch = $conn->prepare($sql_fetch);
        $stmt_fetch->bind_param("s", $username);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        $user = $result_fetch->fetch_assoc();
        $stmt_fetch->close();

        if ($user) {
            // 3. Verifikasi kata sandi lama
            if (password_verify($current_password, $user['password'])) {
                // 4. Hash kata sandi baru dan perbarui di database
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE tb_nasabah SET password = ? WHERE username = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("ss", $hashed_password, $username);

                if ($stmt_update->execute()) {
                    $message = "Kata sandi berhasil diubah.";
                    $message_type = "success";
                } else {
                    $message = "Gagal mengubah kata sandi: " . $stmt_update->error;
                    $message_type = "danger";
                }
                $stmt_update->close();
            } else {
                $message = "Kata sandi lama salah.";
                $message_type = "danger";
            }
        } else {
            $message = "Pengguna tidak ditemukan.";
            $message_type = "danger";
        }
    }
}
$conn->close();
?>

<?php require_once '../includes/header.php'; ?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Ubah Kata Sandi</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 max-w-lg mx-auto">
    <form action="ubah_password.php" method="POST">
        <div class="space-y-4">

            <!-- Password Lama -->
            <div>
                <label for="current_password" class="block mb-2 text-sm font-medium text-gray-900">
                    Kata Sandi Lama
                </label>
                <div class="relative">
                    <input type="password" id="current_password" name="current_password" 
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10" 
                           required>
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700" 
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
            <div>
                <label for="new_password" class="block mb-2 text-sm font-medium text-gray-900">
                    Kata Sandi Baru
                </label>
                <div class="relative">
                    <input type="password" id="new_password" name="new_password" 
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10" 
                           required>
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700" 
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
            </div>

            <!-- Konfirmasi Password -->
            <div>
                <label for="confirm_password" class="block mb-2 text-sm font-medium text-gray-900">
                    Konfirmasi Kata Sandi Baru
                </label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10" 
                           required>
                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700" 
                            onclick="togglePassword('confirm_password', this)">
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

        </div>

        <div class="flex justify-end mt-6">
            <button type="submit" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                Ubah Kata Sandi
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
<?php require_once '../includes/footer.php'; ?>

<?php // Sertakan footer atau bagian akhir HTML Anda ?>