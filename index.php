<?php
session_start();
require_once 'config/db.php';

/**
 * ==========================================
 * INDEX (Landing) — EcoPiksi
 * Style diseragamkan sesuai referensi:
 * - Palet ungu–emerald
 * - Glass / gradient / animasi
 * - Komponen tombol & section konsisten
 * - Prepared statements + sanitasi output
 * ==========================================
 */

$nama_banksampah    = "EcoPiksi";
$moto_banksampah    = "Menabung Sampah, Panen Manfaat, Lestarikan Bumi!";
$deskripsi_banksampah = "Bank Sampah EcoPiksi adalah inisiatif yang dibuat oleh mahasiswa Politeknik Piksi Ganesha Indonesia sebagai tugas akhir untuk mempromosikan pengelolaan sampah yang berkelanjutan. Kami berkomitmen untuk memberdayakan masyarakat dan memberikan nilai ekonomi dari sampah yang dipilah.";
$visi_banksampah    = "Menjadi pelopor pengelolaan sampah berbasis masyarakat yang lestari dan memberikan manfaat ekonomi bagi anggota.";
$misi_banksampah    = [
    "Mendorong partisipasi aktif masyarakat dalam pengurangan dan pemilahan sampah.",
    "Mengembangkan sistem pengelolaan sampah yang efektif, efisien, dan berkelanjutan.",
    "Menciptakan lingkungan yang bersih, sehat, dan nyaman melalui pengelolaan sampah yang bertanggung jawab."
];

/** ---------------------------
 * Ambil Daftar TPS (aktif)
 * ---------------------------*/
$tps_list = [];
$sql_tps  = "SELECT id_tps, nama_tps, alamat_tps FROM tb_tps WHERE status_tps = 'aktif' ORDER BY nama_tps ASC";
if ($res_tps = $conn->query($sql_tps)) {
    while ($row = $res_tps->fetch_assoc()) {
        $tps_list[] = $row;
    }
    $res_tps->free();
}

/** ---------------------------
 * Total Nasabah
 * ---------------------------*/
$total_nasabah = 0;
$sql_count_nasabah = "SELECT COUNT(no_rekening) AS total_nasabah FROM tb_nasabah WHERE role = 'nasabah'";
if ($res_count = $conn->query($sql_count_nasabah)) {
    if ($row_count = $res_count->fetch_assoc()) {
        $total_nasabah = (int) $row_count['total_nasabah'];
    }
    $res_count->free();
}

/** ---------------------------------------
 * Daftar Harga Sampah (global) + Search
 * - Prepared statements (LIKE %term%)
 * - Pagination
 * ---------------------------------------*/
$items_per_page       = 5;
$current_page_harga  = isset($_GET['page_harga']) ? (int) $_GET['page_harga'] : 1;
if ($current_page_harga < 1) {
    $current_page_harga = 1;
}

$search_query_harga  = isset($_GET['search_harga']) ? trim($_GET['search_harga']) : '';
$where_clause        = '';
$params              = [];
$types               = '';

if ($search_query_harga !== '') {
    $where_clause = " WHERE jenis_sampah LIKE ? ";
    $params[]     = "%{$search_query_harga}%";
    $types       .= 's';
}

/* Hitung total items */
$total_items_harga = 0;
$sql_total = "SELECT COUNT(id) AS total_items FROM tb_harga_sampah" . $where_clause;
$stmt_total = $conn->prepare($sql_total);
if ($stmt_total) {
    if ($types !== '') {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    if ($result_total && $row_total = $result_total->fetch_assoc()) {
        $total_items_harga = (int) $row_total['total_items'];
    }
    $stmt_total->close();
}
$total_pages_harga = (int) ceil($total_items_harga / $items_per_page);
if ($total_pages_harga > 0 && $current_page_harga > $total_pages_harga) {
    $current_page_harga = $total_pages_harga;
}
if ($total_items_harga === 0) {
    $current_page_harga = 1;
}

$offset_harga = ($current_page_harga - 1) * $items_per_page;
if ($offset_harga < 0) {
    $offset_harga = 0;
}

/* Ambil data harga */
$daftar_harga = [];
$sql_harga    = "SELECT jenis_sampah, harga_per_kg 
                 FROM tb_harga_sampah 
                 {$where_clause}
                 ORDER BY jenis_sampah ASC 
                 LIMIT ? OFFSET ?";
$stmt_harga = $conn->prepare($sql_harga);
if ($stmt_harga) {
    $bind_types = $types . 'ii';
    $bind_vals  = $params;
    $bind_vals[] = $items_per_page;
    $bind_vals[] = $offset_harga;
    $stmt_harga->bind_param($bind_types, ...$bind_vals);
    $stmt_harga->execute();
    $res_harga = $stmt_harga->get_result();
    if ($res_harga && $res_harga->num_rows > 0) {
        while ($row = $res_harga->fetch_assoc()) {
            $daftar_harga[] = $row;
        }
    }
    $stmt_harga->close();
}

/** ---------------------------------------
 * Konfigurasi Persentase (global)
 * (tanpa id_tps — setting umum)
 * ---------------------------------------*/
function get_config_value_public(mysqli $conn_obj, string $setting_name, $default_value = null)
{
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? LIMIT 1";
    $stmt = $conn_obj->prepare($sql);
    if ($stmt === false) {
        return $default_value;
    }
    $stmt->bind_param("s", $setting_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $value  = $default_value;
    if ($result && $result->num_rows > 0) {
        $row  = $result->fetch_assoc();
        $value = (float) $row['nilai'];
    }
    $stmt->close();
    return $value;
}

$persen_nasabah_public   = get_config_value_public($conn, 'persen_nasabah', 60.00);
$persen_tps_public       = get_config_value_public($conn, 'persen_tps', 20.00);
$persen_pengepul_public  = get_config_value_public($conn, 'persen_pengepul', 20.00);

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($nama_banksampah); ?> - Kelola Sampah, Panen Rupiah</title>

    <meta name="description" content="EcoPiksi: kelola sampah berkelanjutan, transparan, dan menguntungkan. Daftar, setor sampah, dan panen manfaatnya."/>
    <meta name="keywords" content="Bank Sampah, EcoPiksi, Daur Ulang, Lingkungan, Tabungan Sampah"/>
    <meta name="author" content="Mahasiswa Politeknik Piksi Ganesha Indonesia"/>
    <link rel="canonical" href="index.php"/>

    <script src="https://cdn.tailwindcss.com"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root{
            --primary-purple:#7c3aed;
            --secondary-purple:#8b5cf6;
            --light-purple:#c4b5fd;
            --dark-purple:#5b21b6;
            --emerald:#10b981;
            --emerald-light:#34d399;
        }
        body{font-family: 'Inter', sans-serif;}

        /* Diringankan: Animasi gradien dihilangkan, diganti gradien statis */
        .hero-gradient{
            background: linear-gradient(135deg,
                var(--primary-purple) 0%,
                var(--emerald) 100%);
        }

        /* Diringankan: 'backdrop-filter: blur()' dihilangkan. Ini SANGAT berat. */
        .glass-effect{
            background: rgba(255,255,255,.1);
            border:1px solid rgba(255,255,255,.2)
        }

        .card-hover{transition: transform .3s ease, box-shadow .3s ease}
        /* Diringankan: 'box-shadow' dibuat lebih simpel */
        .card-hover:hover{
            transform: translateY(-6px); 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1);
        }
        
        .text-gradient{background: linear-gradient(135deg, var(--primary-purple), var(--emerald)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;}
        
        /* Diringankan: 'backdrop-filter: blur()' dihilangkan. */
        .nav-blur{
            background: rgba(255,255,255,.98); /* Dibuat nyaris solid */
            border-bottom: 1px solid rgba(0,0,0,.05);
        }
        
        .section-padding{padding:5rem 1rem}
        @media (max-width:768px){.section-padding{padding:3rem 1rem}}
        
        /* Efek 'float' ini ringan, jadi tidak masalah */
        .floating-icon{animation: float 3s ease-in-out infinite}
        @keyframes float{0%,100%{transform: translateY(0)}50%{transform: translateY(-10px)}}
        
        /* Diringankan: Animasi 'box-shadow' (pulseGlow) SANGAT berat. Dihilangkan. */
        .pulse-glow{
            transition: transform .2s ease;
        }
        /* Diganti dengan hover scale sederhana yang murah */
        .pulse-glow:hover {
            transform: scale(1.05);
        }
        
        /* Keyframes 'pulseGlow' dan 'gradientShift' sudah tidak dipakai */
    </style>

    <script>
        tailwind.config = {
            theme:{
                extend:{
                    colors:{
                        'primary-purple':'#7c3aed',
                        'secondary-purple':'#8b5cf6',
                        'dark-purple':'#5b21b6'
                    }
                }
            }
        }
    </script>
</head>
<body class="leading-relaxed text-gray-800 bg-gradient-to-br from-purple-50 via-white to-emerald-50 min-h-screen">

<header class="fixed w-full top-0 z-50 nav-blur shadow-lg">
    <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
        <div class="flex items-center space-x-3">
            <div class="relative">
                <img src="assets/img/logo.jpg" alt="<?php echo htmlspecialchars($nama_banksampah); ?> Logo" class="h-12 w-12 rounded-full shadow-lg">
                <div class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-400 rounded-full animate-pulse"></div>
            </div>
            <div>
                <a href="index.php" class="text-xl font-bold text-gradient"><?php echo htmlspecialchars($nama_banksampah); ?></a>
                <p class="text-xs text-gray-600 font-medium">Sustainable Future</p>
            </div>
        </div>
        <nav class="hidden md:flex items-center space-x-6">
            <a href="#tentang" class="text-purple-600 hover:text-purple-800 font-semibold">Tentang</a>
            <a href="#tps" class="text-purple-600 hover:text-purple-800 font-semibold">Bank Sampah</a>
            
        </nav>
        <div class="flex items-center space-x-3">
            <a href="auth/login.php" class="text-purple-700 hover:text-purple-900 font-semibold px-5 py-2 rounded-full hover:bg-purple-50 transition-all">
                <i class="fas fa-sign-in-alt mr-2"></i>Login
            </a>
            <a href="auth/register.php" class="bg-gradient-to-r from-purple-600 to-emerald-600 text-white px-5 py-2 rounded-full hover:from-purple-700 hover:to-emerald-700 font-semibold shadow-lg transition-all">
                <i class="fas fa-user-plus mr-2"></i>Daftar
            </a>
        </div>
    </div>
</header>

<main class="pt-20">

    <section class="hero-gradient flex flex-col items-center justify-center min-h-screen text-center px-6 relative overflow-hidden">
        <div class="absolute top-20 left-10 floating-icon opacity-30">
            <i class="fas fa-leaf text-6xl text-white"></i>
        </div>
        <div class="absolute top-40 right-10 floating-icon opacity-30" style="animation-delay:-1s">
            <i class="fas fa-recycle text-5xl text-white"></i>
        </div>
        <div class="absolute bottom-40 left-20 floating-icon opacity-30" style="animation-delay:-2s">
            <i class="fas fa-seedling text-4xl text-white"></i>
        </div>

        <div class="glass-effect rounded-3xl p-10 md:p-12 max-w-5xl mx-auto">
            <h1 class="font-black mb-6 leading-tight text-4xl sm:text-5xl md:text-6xl lg:text-7xl text-white">
                Selamat Datang di <span class="text-emerald-300 animate-pulse"><?php echo htmlspecialchars($nama_banksampah); ?></span>
            </h1>
            <p class="text-xl md:text-2xl max-w-4xl mx-auto mb-8 text-white/90 font-light leading-relaxed">
                <?php echo htmlspecialchars($moto_banksampah); ?>
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="#tentang" class="pulse-glow bg-white text-purple-700 px-8 py-3 rounded-full shadow-xl hover:bg-gray-50 font-bold transition-all transform hover:-translate-y-1">
                    <i class="fas fa-info-circle mr-2"></i>Tentang Kami
                </a>
                <a href="#tps" class="bg-emerald-500 text-white px-8 py-3 rounded-full shadow-xl hover:bg-emerald-600 font-bold transition-all transform hover:-translate-y-1 hover:scale-105">
                    <i class="fas fa-map-marker-alt mr-2"></i>Cari Bank Sampah
                </a>
            </div>
        </div>

        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
            <i class="fas fa-chevron-down text-white text-2xl opacity-80"></i>
        </div>
    </section>

    <section id="tentang" class="section-padding bg-gradient-to-br from-white to-gray-50">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="font-black text-gradient text-4xl sm:text-5xl md:text-6xl mb-4">Kenali EcoPiksi</h2>
                <p class="text-lg md:text-xl text-gray-700 max-w-4xl mx-auto"><?php echo htmlspecialchars($deskripsi_banksampah); ?></p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-3xl p-8 shadow-2xl card-hover">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-emerald-500 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-eye text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-800">Visi</h3>
                    <p class="text-gray-700 leading-relaxed"><?php echo htmlspecialchars($visi_banksampah); ?></p>
                </div>
                <div class="bg-white rounded-3xl p-8 shadow-2xl card-hover">
                    <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-purple-500 rounded-2xl flex items-center justify-center mb-6">
                        <i class="fas fa-target text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-gray-800">Misi</h3>
                    <div class="space-y-3">
                        <?php foreach ($misi_banksampah as $i => $m): ?>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-emerald-500 rounded-full text-white font-bold flex items-center justify-center flex-shrink-0"><?php echo $i + 1; ?></div>
                                <p class="text-gray-700 leading-relaxed"><?php echo htmlspecialchars($m); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding bg-gradient-to-r from-purple-700 via-purple-600 to-emerald-600 text-white relative overflow-hidden">
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <h2 class="font-black mb-8 text-4xl sm:text-5xl md:text-6xl">Bergabung dengan Komunitas</h2>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="auth/register.php" class="bg-emerald-400 text-purple-800 px-10 py-4 rounded-full shadow-2xl hover:bg-emerald-300 font-bold text-lg transition-all transform hover:-translate-y-1 hover:scale-105">
                    <i class="fas fa-user-plus mr-2"></i>Daftar Sekarang
                </a>
            </div>
        </div>
    </section>

    <section id="tps" class="section-padding bg-gradient-to-br from-gray-50 to-purple-50">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="font-black text-gradient text-4xl sm:text-5xl md:text-6xl mb-4">Pilih Bank Sampah Terdekat</h2>
                <p class="text-lg md:text-xl text-gray-700 max-w-3xl mx-auto">Temukan Bank Sampah aktif untuk mulai menabung sampah.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (!empty($tps_list)): ?>
                    <?php foreach ($tps_list as $idx => $tps): ?>
                        <a href="landing_tps.php?id_tps=<?php echo htmlspecialchars($tps['id_tps']); ?>" class="group block bg-white rounded-3xl p-8 shadow-2xl card-hover border border-gray-100 relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-500/10 to-emerald-500/10 rounded-full -translate-y-16 translate-x-16"></div>
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-emerald-500 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-recycle text-white text-2xl"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-emerald-600 transition-colors">
                                    <?php echo htmlspecialchars($tps['nama_tps']); ?>
                                </h3>
                                <p class="text-gray-600 mb-6 leading-relaxed">
                                    <?php echo htmlspecialchars($tps['alamat_tps']); ?>
                                </p>
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center text-emerald-600 font-semibold group-hover:text-emerald-700 transition-colors">
                                        Mulai Menabung
                                        <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                                    </span>
                                    <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
                                        <i class="fas fa-chevron-right text-emerald-600 text-sm"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full">
                        <div class="bg-white rounded-3xl p-12 shadow-2xl text-center">
                            <div class="text-6xl text-gray-300 mb-4"><i class="fas fa-search"></i></div>
                            <h3 class="text-2xl font-bold text-gray-700 mb-2">Belum Ada Bank Sampah Aktif</h3>
                            <p class="text-gray-500">Silakan hubungi superadmin untuk menambahkan Bank Sampah aktif.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

</main>

<footer class="bg-gradient-to-r from-gray-900 via-purple-900 to-emerald-900 text-white py-12">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-12">

        <div>
            <img src="https://politeknik-kebumen.ac.id/assets/img/icon_PPGI.png" alt="Logo Piksi Ganesha" class="w-20 h-20 mx-auto md:mx-0 mb-4 rounded-full shadow-lg">
            <h3 class="text-xl font-bold mb-2 text-center md:text-left">Politeknik Piksi Ganesha Indonesia</h3>
            <p class="text-gray-300 text-center md:text-left text-sm leading-relaxed">
                Jl. Letjend Suprapto No. 73, Kebumen, Jawa Tengah.
            </p>
        </div>

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

<button id="scrollToTop" class="fixed bottom-6 right-6 w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-full shadow-2xl hover:shadow-lg transition-all transform hover:scale-110 opacity-0 pointer-events-none z-50">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
    // Smooth scroll anchor
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
        a.addEventListener('click',e=>{
            const href=a.getAttribute('href');
            if(href && href.length>1){
                e.preventDefault();
                const target=document.querySelector(href);
                if(target){ target.scrollIntoView({behavior:'smooth', block:'start'}); }
            }
        });
    });

    // Scroll to top visibility
    const scrollBtn=document.getElementById('scrollToTop');
    window.addEventListener('scroll',()=>{
        if(window.pageYOffset>300){
            scrollBtn.style.opacity='1';
            scrollBtn.style.pointerEvents='auto';
        }else{
            scrollBtn.style.opacity='0';
            scrollBtn.style.pointerEvents='none';
        }
    });
    scrollBtn.addEventListener('click',()=>window.scrollTo({top:0, behavior:'smooth'}));
</script>

</body>
</html>