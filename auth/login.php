<?php
// auth/login.php (Revisi Tampilan — Backend TIDAK diubah)
session_start();
require_once '../config/db.php';

// Redirect logged-in users based on their role
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SESSION['role'] == 'superadmin') {
        header("Location: ../superadmin/index.php");
        exit();
    } elseif ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/index.php");
        exit();
    } elseif ($_SESSION['role'] == 'nasabah') {
        header("Location: ../nasabah/index.php");
        exit();
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password_input = $_POST['password'];

    // Cek di tabel tb_super_admin terlebih dahulu
    $sql_superadmin = "SELECT * FROM tb_super_admin WHERE username = ?";
    $stmt_superadmin = $conn->prepare($sql_superadmin);
    $stmt_superadmin->bind_param("s", $username);
    $stmt_superadmin->execute();
    $result_superadmin = $stmt_superadmin->get_result();

    if ($result_superadmin->num_rows > 0) {
        $user = $result_superadmin->fetch_assoc();
        if (password_verify($password_input, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'superadmin';
            header("Location: ../superadmin/index.php");
            exit();
        } else {
            $message = "Username atau password salah.";
        }
        $stmt_superadmin->close();
    } else {
        // Jika tidak ditemukan di tb_super_admin, cek di tb_nasabah
        $sql_nasabah = "SELECT * FROM tb_nasabah WHERE username = ?";
        $stmt_nasabah = $conn->prepare($sql_nasabah);
        $stmt_nasabah->bind_param("s", $username);
        $stmt_nasabah->execute();
        $result_nasabah = $stmt_nasabah->get_result();

        if ($result_nasabah->num_rows > 0) {
            $user = $result_nasabah->fetch_assoc();
            if (password_verify($password_input, $user['password'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['no_rekening'] = $user['no_rekening'];
                $_SESSION['nama_nasabah'] = $user['nama_nasabah'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['id_tps'] = $user['id_tps'];
                
                if ($user['role'] == 'admin') {
                    header("Location: ../admin/index.php");
                    exit();
                } elseif ($user['role'] == 'nasabah') {
                    header("Location: ../nasabah/index.php");
                    exit();
                }
            } else {
                $message = "Username atau password salah.";
            }
        } else {
            $message = "Username atau password salah.";
        }
        $stmt_nasabah->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - EcoPiksi</title>

    <!-- Tailwind + Font -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <style>
        :root{
            --primary-purple:#7c3aed;
            --secondary-purple:#8b5cf6;
            --dark-purple:#5b21b6;
            --emerald:#10b981;
            --emerald-light:#34d399;
        }
        body{font-family:'Inter', sans-serif;}

        /* Background gradient dinamis (sesuai referensi) */
        .page-gradient{
            background: linear-gradient(135deg,
              var(--primary-purple) 0%,
              var(--dark-purple) 25%,
              var(--secondary-purple) 50%,
              var(--emerald) 75%,
              var(--emerald-light) 100%);
            background-size: 400% 400%;
            animation: gradientShift 10s ease infinite;
        }
        @keyframes gradientShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

        .glass-effect{
            backdrop-filter: blur(18px);
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.18);
        }
        .text-gradient{
            background: linear-gradient(135deg, var(--primary-purple), var(--emerald));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip:text;
        }
        .card-hover{transition: all .3s cubic-bezier(.4,0,.2,1)}
        .card-hover:hover{transform: translateY(-6px) scale(1.01)}
        .input-icon{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:#9ca3af}
        .password-toggle{position:absolute;right:.9rem;top:50%;transform:translateY(-50%);color:#9ca3af;cursor:pointer}
    </style>
</head>
<body class="min-h-screen page-gradient flex items-center justify-center p-4">

    <!-- Kartu Login (Glass) -->
    <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-2 gap-0 glass-effect rounded-3xl shadow-2xl overflow-hidden">

        <!-- Panel Branding -->
        <div class="hidden md:flex flex-col justify-center items-center p-10 bg-white/5 relative">
            <div class="absolute inset-0 opacity-10 pointer-events-none"
                 style="background: radial-gradient(500px 200px at 10% 10%, #ffffff, transparent),
                                  radial-gradient(500px 200px at 90% 90%, #ffffff, transparent)"></div>

            <img src="../assets/img/logo.jpg" alt="EcoPiksi" class="w-24 h-24 rounded-full shadow-xl mb-6 border-2 border-white/30">
            <h1 class="text-4xl font-extrabold text-white mb-2 tracking-tight">EcoPiksi</h1>
            <p class="text-white/80 text-center max-w-sm">
                Menabung Sampah, Panen Manfaat, <br/>Lestarikan Bumi!
            </p>

            <div class="mt-10 grid grid-cols-3 gap-4 w-full max-w-xs text-center">
                <div class="bg-white/10 rounded-2xl p-4">
                    <i class="fas fa-recycle text-white text-2xl mb-1"></i>
                    <p class="text-white/80 text-xs">Daur Ulang</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-4">
                    <i class="fas fa-seedling text-white text-2xl mb-1"></i>
                    <p class="text-white/80 text-xs">Berkelanjutan</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-4">
                    <i class="fas fa-coins text-white text-2xl mb-1"></i>
                    <p class="text-white/80 text-xs">Bernilai</p>
                </div>
            </div>
        </div>

        <!-- Panel Form -->
        <div class="bg-white/70 backdrop-blur-lg p-8 md:p-10">
            <!-- Logo + Title (mobile/desktop) -->
            <div class="md:hidden flex flex-col items-center mb-6">
                <img src="../assets/img/logo.jpg" alt="EcoPiksi" class="w-16 h-16 rounded-full shadow-md mb-2">
                <h2 class="text-2xl font-extrabold text-gradient">EcoPiksi</h2>
            </div>

            <h3 class="text-2xl md:text-3xl font-black text-gray-900 mb-2">
                Masuk Akun Anda
            </h3>
            <p class="text-gray-600 mb-6 text-sm md:text-base">
                Gunakan kredensial yang terdaftar untuk mengakses dashboard Anda.
            </p>

            <?php if (!empty($message)): ?>
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                    <i class="fas fa-circle-exclamation mt-0.5"></i>
                    <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-5">
                <!-- Username -->
                <div>
                    <label for="username" class="block mb-2 text-sm font-semibold text-gray-700">Username</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            required
                            autocomplete="username"
                            class="w-full pl-10 pr-4 py-3 rounded-2xl border border-gray-200 focus:ring-4 focus:ring-purple-200 focus:border-purple-500 outline-none transition-all bg-white"
                            placeholder="Masukkan username"
                        />
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block mb-2 text-sm font-semibold text-gray-700">Password</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="w-full pl-10 pr-12 py-3 rounded-2xl border border-gray-200 focus:ring-4 focus:ring-purple-200 focus:border-purple-500 outline-none transition-all bg-white"
                            placeholder="Masukkan password"
                        />
                        <button type="button" class="password-toggle" aria-label="Tampilkan/Sembunyikan Password" onclick="togglePassword()">
                            <i id="eyeIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Options -->
                <div class="flex items-center justify-between text-sm flex-wrap gap-2">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" id="remember_me" name="remember_me" class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                        <span class="text-gray-700">Ingat saya</span>
                    </label>
                    <a href="#" class="font-semibold text-purple-700 hover:text-purple-900">Lupa password?</a>
                </div>

                <!-- Submit -->
                <button type="submit"
                    class="w-full bg-gradient-to-r from-purple-600 to-emerald-600 text-white py-3.5 rounded-2xl font-bold shadow-lg hover:from-purple-700 hover:to-emerald-700 transition-all transform hover:-translate-y-0.5">
                    <i class="fas fa-right-to-bracket mr-2"></i> Masuk
                </button>
            </form>

            <!-- Divider -->
            <div class="flex items-center my-6">
                <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent"></div>
                <span class="px-3 text-gray-500 text-sm">atau</span>
                <div class="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent"></div>
            </div>

            <p class="text-center text-gray-700 text-sm">
                Belum punya akun?
                <a href="register.php" class="font-bold text-emerald-700 hover:text-emerald-800 underline decoration-emerald-300">Daftar Sekarang</a>
            </p>

            <!-- Footer mini -->
            <div class="mt-8 text-center text-xs text-gray-500">
                © <?php echo date("Y"); ?> EcoPiksi • Dikembangkan Oleh Mahasiswa Politeknik Piksi Ganesha Indonesia.
            </div>
        </div>
    </div>

    <script>
        function togglePassword(){
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            const isPw = input.type === 'password';
            input.type = isPw ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isPw);
            icon.classList.toggle('fa-eye-slash', isPw);
        }

        // Autofocus ke username saat ada error
        <?php if (!empty($message)): ?>
        window.addEventListener('DOMContentLoaded', ()=> {
            const u = document.getElementById('username');
            if(u) u.focus();
        });
        <?php endif; ?>
    </script>
</body>
</html>
