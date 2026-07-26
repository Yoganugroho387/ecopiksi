<?php
session_start();
require_once 'config/db.php';

$nama_banksampah = "EcoPiksi";

$id_tps = isset($_GET['id_tps']) ? intval($_GET['id_tps']) : 0;

if ($id_tps <= 0) {
    die("ID TPS tidak valid.");
}

// Ambil detail TPS
$sql_tps_detail = "SELECT * FROM tb_tps WHERE id_tps = ?";
$stmt_tps_detail = $conn->prepare($sql_tps_detail);
$stmt_tps_detail->bind_param("i", $id_tps);
$stmt_tps_detail->execute();
$result_tps_detail = $stmt_tps_detail->get_result();

if ($result_tps_detail && $row = $result_tps_detail->fetch_assoc()) {
    $nama_tps = $row['nama_tps'];
    $alamat_tps = $row['alamat_tps'];
    $kontak_tps = $row['kontak_tps'];
    // Jika ada kolom deskripsi di tb_tps, Anda bisa ambil di sini
    // $deskripsi_tps = $row['deskripsi_tps'];
    
} else {
    die("TPS tidak ditemukan atau tidak aktif.");
}
$stmt_tps_detail->close();


// Ambil data dinamis dari database untuk TPS ini
$total_nasabah = 0;
$sql_count_nasabah = "SELECT COUNT(no_rekening) AS total_nasabah FROM tb_nasabah WHERE role = 'nasabah' AND id_tps = ?";
$stmt_count_nasabah = $conn->prepare($sql_count_nasabah);
$stmt_count_nasabah->bind_param("i", $id_tps);
$stmt_count_nasabah->execute();
$result_count = $stmt_count_nasabah->get_result();
if ($result_count && $result_count->num_rows > 0) {
    $row_count = $result_count->fetch_assoc();
    $total_nasabah = $row_count['total_nasabah'];
}
$stmt_count_nasabah->close();


// Ambil Daftar Harga Sampah dengan Pagination dan Pencarian
$items_per_page = 5;
$current_page_harga = isset($_GET['page_harga']) ? (int)$_GET['page_harga'] : 1;
if ($current_page_harga < 1) $current_page_harga = 1;

$search_query_harga = isset($_GET['search_harga']) ? $conn->real_escape_string($_GET['search_harga']) : '';
$search_condition_harga = '';
$params_harga = [$id_tps];
$param_types_harga = 'i';

if (!empty($search_query_harga)) {
    $search_condition_harga = " AND jenis_sampah LIKE ? ";
    $params_harga[] = "%" . $search_query_harga . "%";
    $param_types_harga .= 's';
}

$sql_total_items_harga = "SELECT COUNT(id) AS total_items FROM tb_harga_sampah WHERE id_tps = ?" . $search_condition_harga;
$stmt_total_items_harga = $conn->prepare($sql_total_items_harga);
$stmt_total_items_harga->bind_param($param_types_harga, ...$params_harga);
$stmt_total_items_harga->execute();
$total_items_harga = $stmt_total_items_harga->get_result()->fetch_assoc()['total_items'];
$total_pages_harga = ceil($total_items_harga / $items_per_page);
$stmt_total_items_harga->close();

if ($total_pages_harga > 0 && $current_page_harga > $total_pages_harga) $current_page_harga = $total_pages_harga;
if ($total_items_harga == 0) $current_page_harga = 1;

$offset_harga = ($current_page_harga - 1) * $items_per_page;
if ($offset_harga < 0) $offset_harga = 0;

$daftar_harga = [];
$sql_harga = "SELECT jenis_sampah, harga_per_kg FROM tb_harga_sampah WHERE id_tps = ?" . $search_condition_harga . " ORDER BY jenis_sampah ASC LIMIT ? OFFSET ?";
$stmt_harga = $conn->prepare($sql_harga);
$params_harga[] = $items_per_page;
$params_harga[] = $offset_harga;
$param_types_harga .= 'ii';
$stmt_harga->bind_param($param_types_harga, ...$params_harga);
$stmt_harga->execute();
$result_harga = $stmt_harga->get_result();

if ($result_harga && $result_harga->num_rows > 0) {
    while ($row = $result_harga->fetch_assoc()) {
        $daftar_harga[] = $row;
    }
}
$stmt_harga->close();


// Ambil Persentase Pembagian dari Database (jika ada, kalau tidak pakai default)
$persen_nasabah_public = get_config_value_public($conn, 'persen_nasabah', $id_tps, 60.00);
$persen_tps_public     = get_config_value_public($conn, 'persen_tps', $id_tps, 20.00);
$persen_pengepul_public= get_config_value_public($conn, 'persen_pengepul', $id_tps, 20.00);

$conn->close();

function get_config_value_public($conn_obj, $setting_name, $id_tps, $default_value = null) {
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
    $stmt = $conn_obj->prepare($sql);
    if ($stmt === FALSE) { return $default_value; }
    $stmt->bind_param("si", $setting_name, $id_tps);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return floatval($row['nilai']);
    }
    $stmt->close();
    return $default_value;
}

$visi_banksampah = "Menjadi pelopor pengelolaan sampah berbasis masyarakat yang lestari dan memberikan manfaat ekonomi bagi anggota.";
$misi_banksampah = [
    "Mendorong partisipasi aktif masyarakat dalam pengurangan dan pemilahan sampah.",
    "Mengembangkan sistem pengelolaan sampah yang efektif, efisien, dan berkelanjutan.",
    "Menciptakan lingkungan yang bersih, sehat, dan nyaman melalui pengelolaan sampah yang bertanggung jawab."
];
$moto_banksampah = "Menabung Sampah, Panen Manfaat, Lestarikan Bumi!";

$whatsapp_float_link = "https://wa.me/" . $kontak_tps . "?text=" . urlencode("Halo admin {$nama_tps}, saya ingin bertanya tentang bank sampah.");
$email_kontak = "info@ecopiksi.biz.id"; // Contoh email default, bisa diganti dari DB jika ada
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bank Sampah EcoPiksi - <?php echo htmlspecialchars($nama_tps); ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-purple: #7c3aed;
            --secondary-purple: #8b5cf6;
            --light-purple: #c4b5fd;
            --dark-purple: #5b21b6;
            --emerald: #10b981;
            --emerald-light: #34d399;
        }
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, 
                var(--primary-purple) 0%, 
                var(--dark-purple) 25%, 
                var(--secondary-purple) 50%, 
                var(--emerald) 75%, 
                var(--emerald-light) 100%);
            background-size: 400% 400%;
            animation: gradientShift 10s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .glass-effect {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .floating-icon {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .pulse-glow {
            animation: pulseGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 20px rgba(124, 58, 237, 0.5); }
            100% { box-shadow: 0 0 40px rgba(124, 58, 237, 0.8), 0 0 60px rgba(16, 185, 129, 0.4); }
        }
        
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-purple), var(--emerald));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-blur {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .section-padding {
            padding: 5rem 1rem;
        }
        
        @media (max-width: 768px) {
            .section-padding {
                padding: 3rem 1rem;
            }
        }
        
        .percentage-circle {
            position: relative;
            overflow: hidden;
        }
        
        .percentage-circle::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, var(--primary-purple), var(--emerald), var(--primary-purple));
            animation: rotate 6s linear infinite;
            z-index: -1;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-purple': '#7c3aed',
                        'secondary-purple': '#8b5cf6',
                        'light-purple': '#c4b5fd',
                        'dark-purple': '#5b21b6',
                    }
                }
            }
        }
    </script>
</head>
<body class="leading-relaxed text-gray-800 bg-gradient-to-br from-purple-50 via-white to-emerald-50 min-h-screen">

    <!-- Enhanced Header -->
    <header class="fixed w-full top-0 z-50 nav-blur shadow-lg transition-all duration-300">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <img src="assets/img/logo.jpg" alt="<?php echo $nama_banksampah; ?> Logo" class="h-12 w-12 rounded-full shadow-lg">
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-400 rounded-full animate-pulse"></div>
                </div>
                <div>
                    <a href="index.php" class="text-xl font-bold text-gradient"><?php echo $nama_banksampah; ?></a>
                    <p class="text-xs text-gray-600 font-medium"><?php echo htmlspecialchars($nama_tps); ?></p>
                </div>
            </div>
            <nav class="flex items-center space-x-3">
                <a href="auth/login.php" class="text-purple-600 hover:text-purple-800 font-semibold px-6 py-2 rounded-full transition-all duration-300 hover:bg-purple-50">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </a>
                <a href="auth/register.php" class="bg-gradient-to-r from-purple-600 to-emerald-600 text-white px-6 py-2 rounded-full hover:from-purple-700 hover:to-emerald-700 font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-user-plus mr-2"></i>Daftar
                </a>
            </nav>
        </div>
    </header>

<!-- Pengumuman Berjalan (di bawah header) -->
<div class="mt-[80px] bg-gradient-to-r from-purple-600/90 via-emerald-500/90 to-purple-600/90
            backdrop-blur-sm text-white py-3 overflow-hidden relative shadow-lg border-b border-white/10">

  <!-- Fade mask kiri/kanan biar lebih halus -->
  <div class="pointer-events-none absolute inset-y-0 left-0 w-16 bg-gradient-to-r from-purple-600/90 via-transparent to-transparent"></div>
  <div class="pointer-events-none absolute inset-y-0 right-0 w-16 bg-gradient-to-l from-purple-600/90 via-transparent to-transparent"></div>

  <div class="marquee">
    <div class="marquee-track">
      <!-- Konten 1 -->
      <div class="marquee-content">
        📢 Pendaftaran Mahasiswa Baru TA 2025/2026 Telah Dibuka!
        🎓 Beasiswa KIP Kuliah & Program VIP tersedia
        🌱 Bergabunglah dengan Politeknik Piksi Ganesha Indonesia
        📍 Jl. Letjend Suprapto No. 73, Kebumen
      </div>
      <!-- Konten 2 (duplikat) -->
      <div class="marquee-content" aria-hidden="true">
        📢 Pendaftaran Mahasiswa Baru TA 2025/2026 Telah Dibuka!
        🎓 Beasiswa KIP Kuliah & Program VIP tersedia
        🌱 Bergabunglah dengan Politeknik Piksi Ganesha Indonesia
        📍 Jl. Letjend Suprapto No. 73, Kebumen
      </div>
    </div>
  </div>
</div>

<style>
  /* Container marquee */
  .marquee { position: relative; overflow: hidden; }
  .marquee-track {
    display: flex;
    animation: marqueeSlide 35s linear infinite; /* Lebih lambat */
  }
  .marquee-content {
    flex-shrink: 0;
    min-width: 100%; /* Pastikan satu blok penuh layar */
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4rem; /* Jarak antar teks biar nggak numbruk */
    font-weight: 700;
    font-size: 1.125rem; /* text-lg */
    white-space: nowrap;
  }

  /* Animasi */
  @keyframes marqueeSlide {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-100%); }
  }

  /* Desktop: teks lebih besar */
  @media (min-width: 768px) {
    .marquee-content { font-size: 1.25rem; }
  }

  /* Mobile: lebih lambat */
  @media (max-width: 640px) {
    .marquee-track { animation-duration: 50s; }
  }

  /* Reduce motion */
  @media (prefers-reduced-motion: reduce) {
    .marquee-track { animation: none; }
  }
</style>

    <!-- Enhanced Hero Section -->
    <section class="hero-gradient flex flex-col items-center justify-center min-h-screen text-center px-6 pt-20 relative overflow-hidden">
        <!-- Floating Elements -->
         
        <div class="absolute top-20 left-10 floating-icon opacity-30">
            <i class="fas fa-leaf text-6xl text-white"></i>
        </div>
        <div class="absolute top-40 right-10 floating-icon opacity-30" style="animation-delay: -1s;">
            <i class="fas fa-recycle text-5xl text-white"></i>
        </div>
        <div class="absolute bottom-40 left-20 floating-icon opacity-30" style="animation-delay: -2s;">
            <i class="fas fa-seedling text-4xl text-white"></i>
        </div>
        
        <div class="glass-effect rounded-3xl p-12 max-w-5xl mx-auto">
            <h1 class="font-black mb-6 leading-tight text-4xl sm:text-5xl md:text-6xl lg:text-7xl text-white">
                Selamat Datang di <span class="text-emerald-300 animate-pulse"><?php echo $nama_banksampah; ?></span>
            </h1>
            <p class="text-xl md:text-2xl max-w-4xl mx-auto mb-8 text-white/90 font-light leading-relaxed">
                Kelola sampah Anda di <span class="font-bold text-emerald-300"><?php echo htmlspecialchars($nama_tps); ?></span>, panen rupiah, lestarikan bumi untuk generasi mendatang!
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center mt-12">
                <a href="#tentang-kami" class="pulse-glow bg-white text-purple-700 px-10 py-4 rounded-full shadow-xl hover:bg-gray-50 font-bold text-lg transition-all duration-300 transform hover:-translate-y-2 hover:scale-105">
                    <i class="fas fa-info-circle mr-2"></i>Pelajari Lebih Lanjut
                </a>
                <a href="auth/register.php" class="bg-emerald-500 text-white px-10 py-4 rounded-full shadow-xl hover:bg-emerald-600 font-bold text-lg transition-all duration-300 transform hover:-translate-y-2 hover:scale-105">
                    <i class="fas fa-rocket mr-2"></i>Daftar Sekarang
                </a>
            </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
            <i class="fas fa-chevron-down text-white text-2xl opacity-70"></i>
        </div>
    </section>

    <!-- Enhanced Distribution Section -->
<section class="section-padding bg-gradient-to-r from-purple-700 via-purple-600 to-emerald-600 text-white relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-10 left-10 text-8xl"><i class="fas fa-users"></i></div>
            <div class="absolute top-20 right-20 text-6xl"><i class="fas fa-heart"></i></div>
            <div class="absolute bottom-20 left-20 text-7xl"><i class="fas fa-globe-asia"></i></div>
        </div>
        
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <h2 class="font-black mb-8 text-4xl sm:text-5xl md:text-6xl">Bergabunglah dengan Komunitas Kami!</h2>
            
            <div class="glass-effect rounded-3xl p-12 mb-10">
                <div class="text-8xl md:text-9xl font-black text-emerald-300 leading-none mb-4 animate-pulse">
                    <?php echo $total_nasabah; ?>
                </div>
                <p class="text-2xl md:text-3xl font-light mb-2">Nasabah Terdaftar</p>
                <p class="text-lg opacity-80">di Bank Sampah <?php echo htmlspecialchars($nama_tps); ?></p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                <a href="auth/register.php" class="bg-emerald-400 text-purple-800 px-12 py-4 rounded-full shadow-2xl hover:bg-emerald-300 font-bold text-xl transition-all duration-300 transform hover:-translate-y-2 hover:scale-105">
                    <i class="fas fa-star mr-2"></i>Daftar Sekarang
                </a>
              
            </div>
        </div>
    </section>

    <!-- Enhanced Price List Section -->

    <!-- Enhanced Contact Section -->
    <section id="kontak" class="section-padding bg-gradient-to-br from-purple-50 via-white to-emerald-50">
        <div class="max-w-6xl mx-auto text-center">
            <div class="mb-16">
                <h2 class="font-black text-gradient mb-6 text-4xl sm:text-5xl md:text-6xl">Hubungi Kami</h2>
                <p class="text-xl text-gray-700 max-w-3xl mx-auto">Kami siap membantu Anda memulai perjalanan menabung sampah</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Contact Info -->
                <div class="space-y-8">
                    <div class="bg-white rounded-3xl p-8 shadow-2xl card-hover">
                        <div class="flex items-center space-x-6">
                            <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-emerald-500 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-2xl text-white"></i>
                            </div>
                            <div class="text-left">
                                <h3 class="text-xl font-bold text-gray-800 mb-2">Alamat Lokasi</h3>
                                <p class="text-gray-600 leading-relaxed"><?php echo htmlspecialchars($alamat_tps); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-3xl p-8 shadow-2xl card-hover">
                        <div class="flex items-center space-x-6">
                            <div class="w-16 h-16 bg-gradient-to-r from-emerald-500 to-purple-500 rounded-2xl flex items-center justify-center">
                                <i class="fab fa-whatsapp text-2xl text-white"></i>
                            </div>
                            <div class="text-left">
                                <h3 class="text-xl font-bold text-gray-800 mb-2">WhatsApp</h3>
                                <a href="https://wa.me/<?php echo htmlspecialchars($kontak_tps); ?>" target="_blank" 
                                   class="text-emerald-600 hover:text-emerald-700 font-semibold text-lg transition-colors duration-300">
                                    <?php echo htmlspecialchars($kontak_tps); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center lg:text-left">
                        <a href="auth/register.php" 
                           class="inline-flex items-center bg-gradient-to-r from-purple-600 to-emerald-600 text-white px-10 py-4 rounded-full shadow-2xl hover:from-purple-700 hover:to-emerald-700 font-bold text-lg transition-all duration-300 transform hover:-translate-y-1 hover:scale-105">
                            <i class="fas fa-rocket mr-3"></i>
                            Gabung Sekarang!
                        </a>
                    </div>
                </div>
                
                <!-- Call to Action Card -->
                <div class="bg-gradient-to-br from-purple-600 to-emerald-600 rounded-3xl p-12 text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12"></div>
                    <div class="relative z-10">
                        <div class="text-6xl mb-6">
                            <i class="fas fa-heart floating-icon"></i>
                        </div>
                        <h3 class="text-3xl font-bold mb-6">Mulai Perubahan Hari Ini!</h3>
                        <p class="text-lg leading-relaxed mb-8 opacity-90">
                            Bergabunglah dengan komunitas peduli lingkungan dan dapatkan manfaat ekonomi dari sampah Anda. 
                            Setiap langkah kecil menciptakan dampak besar untuk planet kita.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="auth/register.php" 
                               class="bg-white text-purple-700 px-8 py-3 rounded-full font-bold hover:bg-gray-100 transition-all duration-300 transform hover:scale-105">
                                <i class="fas fa-user-plus mr-2"></i>Daftar Gratis
                            </a>
                            <a href="https://wa.me/<?php echo htmlspecialchars($kontak_tps); ?>?text=<?php echo urlencode("Halo admin " . $nama_tps . ", saya ingin bertanya tentang bank sampah."); ?>" 
                               target="_blank"
                               class="border-2 border-white text-white px-8 py-3 rounded-full font-bold hover:bg-white hover:text-purple-700 transition-all duration-300 transform hover:scale-105">
                                <i class="fab fa-whatsapp mr-2"></i>Chat Admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Floating WhatsApp Button -->
    <a href="<?php echo "https://wa.me/" . htmlspecialchars($kontak_tps) . "?text=" . urlencode("Halo admin " . $nama_tps . ", saya ingin bertanya tentang bank sampah."); ?>" 
       target="_blank" 
       class="fixed bottom-6 right-6 z-50 flex items-center bg-gradient-to-r from-emerald-500 to-green-500 text-white px-6 py-4 rounded-full shadow-2xl hover:from-emerald-600 hover:to-green-600 transition-all duration-300 transform hover:scale-110 hover:-translate-y-1 no-underline font-bold pulse-glow">
        <svg class="w-8 h-8 mr-3" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M12.04 2C6.58 2 2.04 6.54 2.04 12c0 1.77.46 3.45 1.25 4.93L2 22l4.23-1.25c1.47.78 3.15 1.25 4.93 1.25 5.46 0 9.96-4.46 9.96-10S17.5 2 12.04 2zm5.54 14.83c-.15.08-.88.44-1.25.46-.37.02-.64.03-.92-.15-.28-.18-1.07-.35-2.04-1.26-.97-.9-1.61-2.1-1.8-2.37-.19-.27-.04-.42.12-.58.14-.15.34-.37.52-.56.17-.19.23-.32.33-.53.1-.2.05-.37-.02-.5-.07-.13-.7-.93-.96-1.27-.26-.34-.21-.29-.37-.6-.16-.3-.32-.26-.49-.26-.16 0-.35-.02-.53-.02-.19 0-.5.06-.77.34-.27.28-1.04 1.01-1.04 2.46 0 1.45 1.07 2.85 1.22 3.03.15.18 2.09 3.2 5.07 4.45 2.47 1.01 2.96.87 3.48.81.5-.06 1.48-.6 1.7-1.18.22-.58.22-1.07.15-1.18-.08-.1-.28-.15-.59-.29z"/>
        </svg>
        <span class="hidden sm:inline">Chat Admin</span>
    </a>

    <!-- Enhanced Footer -->
    <hr>
<footer class="bg-gradient-to-r from-gray-900 via-purple-900 to-emerald-900 text-white py-12">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-12">

        <!-- Kiri: Logo + Nama Kampus + Alamat -->
        <div>
            <img src="https://politeknik-kebumen.ac.id/assets/img/icon_PPGI.png" alt="Logo Piksi Ganesha" class="w-20 h-20 mx-auto md:mx-0 mb-4 rounded-full shadow-lg">
            <h3 class="text-xl font-bold mb-2 text-center md:text-left">Politeknik Piksi Ganesha Indonesia</h3>
            <p class="text-gray-300 text-center md:text-left text-sm leading-relaxed">
                Jl. Letjend Suprapto No. 73, Kebumen, Jawa Tengah.
            </p>
        </div>

        <!-- Tengah: Kontak & Sosial Media -->
        <div class="space-y-3 text-center md:text-left">
            <a href="mailto:info@politeknik-kebumen.ac.id" class="flex items-center justify-center md:justify-start gap-3 hover:text-emerald-400 transition">
                <i class="fas fa-envelope text-purple-400"></i> info@politeknik-kebumen.ac.id
            </a>
            <a href="" target="_blank" class="flex items-center justify-center md:justify-start gap-3 hover:text-red-500 transition">
                <i class="fab fa-youtube text-red-400"></i> PPGI Kebumen
            </a>
            <a href="https://instagram.com/Piksiganesha_official_kebumen" target="_blank" class="flex items-center justify-center md:justify-start gap-3 hover:text-pink-500 transition">
                <i class="fab fa-instagram text-pink-400"></i> Piksiganesha_official_kebumen
            </a>
            <a href="" target="_blank" class="flex items-center justify-center md:justify-start gap-3 hover:text-blue-500 transition">
                <i class="fab fa-facebook text-blue-400"></i> Piksi Ganesha Indonesia Kebumen
            </a>
            <a href="https://wa.me/6281572255000" target="_blank" class="flex items-center justify-center md:justify-start gap-3 hover:text-green-500 transition">
                <i class="fab fa-whatsapp text-green-400"></i> 0815-7225-5000
            </a>
        </div>

        <!-- Kanan: Prodi & Pendaftaran -->
        <div class="grid grid-cols-2 gap-6 text-sm">
            <div>
                <h4 class="font-bold mb-3 text-gradient">Program Studi</h4>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-emerald-400 transition">D3 Mesin Otomotif</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">D3 Teknik Elektonika</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">D4 ManaJemen SDM</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">D3 Akuntansi</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-3 text-gradient">Pendaftaran</h4>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-emerald-400 transition">Diploma III</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">Diploma IV / Sarjana</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">KIP Kuliah</a></li>
                    <li><a href="#" class="hover:text-emerald-400 transition">Jalur Mandiri</a></li>
                </ul>
            </div>
        </div>

    </div>

    <!-- Garis & Copyright -->
    <div class="border-t border-gray-700 mt-10 pt-6 text-center text-gray-400 text-sm">
        © <?php echo date("Y"); ?> Dikembangkan Oleh Mahasiswa Politeknik Piksi Ganesha Indonesia. 
    </div>
</footer>

<style>
    .text-gradient {
        background: linear-gradient(135deg, var(--primary-purple, #7c3aed), var(--emerald, #10b981));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
</style>

</body>
</html>
<!-- 
╔══════════════════════════════════════════════════════════╗
║  Copyright © 2025                                         ║
║  Website ini dibuat oleh: Yoga Nugroho                    ║
║  Instagram: @yogaaszs                                     ║
║  Semua hak dilindungi undang-undang.                      ║
╚══════════════════════════════════════════════════════════╝
-->