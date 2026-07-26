<?php
// 404.php
// This page is displayed when a requested URL is not found.
session_start(); // Start session if not already started, might be useful for branding or dynamic content later

// Basic site information (ensure these paths/values are correct relative to the root)
$nama_banksampah = "EcoPiksi";
$nama_tps = "TPS3R Berkah Panjer"; // Assuming this is still relevant for branding
// 404.php
// ...
$homepage_url = 'https://ecopiksi.my.id/index.php'; // Link back to your main public homepage
$login_url = 'https://ecopiksi.my.id/auth/login.php'; // Link to your login page
$register_url = 'https://ecopiksi.my.id/auth/register.php'; // Link to your register page
// Set HTTP response code to 404
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | <?php echo $nama_banksampah; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5; /* Light gray background */
            color: #333;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem; /* Increased padding for more space */
            text-align: center;
            box-sizing: border-box; /* Ensures padding doesn't cause overflow */
        }
        .text-404 {
            font-size: 6rem; /* Larger 404 text */
            font-weight: 800; /* font-extrabold */
            color: #ef4444; /* red-500 */
            line-height: 1;
            margin-bottom: 1.5rem; /* Increased margin */
        }
        @media (min-width: 768px) {
            .text-404 {
                font-size: 10rem; /* Even larger on desktop */
            }
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 9999px; /* rounded-full */
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            margin: 0.5rem;
            min-width: 150px; /* Ensure buttons have a minimum width */
        }
        .btn-primary {
            background-color: #22c55e; /* purple-500 */
            color: white;
            border: 2px solid #22c55e;
        }
        .btn-primary:hover {
            background-color: #16a34a; /* purple-600 */
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-secondary {
            background-color: #60a5fa; /* blue-400 */
            color: white;
            border: 2px solid #60a5fa;
        }
        .btn-secondary:hover {
            background-color: #3b82f6; /* blue-500 */
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
       .logo-section {
            margin-bottom: 2rem; /* More space below logo */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-section img {
            height: 3.5rem; /* h-14 */
            width: 3.5rem; /* Tambahkan width agar menjadi persegi */
            margin-right: 0.75rem; /* mr-3 */
            border-radius: 50%; /* Membuat gambar lingkaran */
            object-fit: cover; /* Memastikan gambar mengisi lingkaran tanpa distorsi */
            border: 2px solid #22c55e; /* Opsional: border hijau tipis */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Opsional: sedikit bayangan */
        }
        .logo-section span {
            font-size: 2.25rem; /* text-4xl */
            font-weight: 700; /* font-bold */
            color: #10B981; /* purple-500 */
        }
    </style>
</head>
<body>

  
    <div class="logo-section">
        <img src="https://ecopiksi.my.id/assets/img/logo.jpg" alt="<?php echo $nama_banksampah; ?> Logo">
        <span><?php echo $nama_banksampah; ?></span>
    </div>
    
    <div class="text-404">404</div>
    <h1 class="text-3xl md:text-5xl font-bold text-gray-800 mb-4">Halaman Tidak Ditemukan</h1>
    <p class="text-lg text-gray-600 mb-10 max-w-xl">
        Maaf, halaman yang Anda cari mungkin telah dipindahkan, dihapus, atau tidak pernah ada.
    </p>
    
    <div class="flex flex-wrap justify-center gap-4">
        <a href="<?php echo $homepage_url; ?>" class="btn btn-primary">
            <i class="fas fa-home mr-2"></i> Kembali ke Beranda
        </a>
        <a href="<?php echo $login_url; ?>" class="btn btn-secondary">
            <i class="fas fa-sign-in-alt mr-2"></i> Login
        </a>
        <a href="<?php echo $register_url; ?>" class="btn btn-secondary">
            <i class="fas fa-user-plus mr-2"></i> Daftar
        </a>
    </div>

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
