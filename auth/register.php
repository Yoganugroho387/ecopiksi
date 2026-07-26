<?php
session_start();
require_once '../config/db.php';

$nama_banksampah = "EcoPiksi";

// Ambil semua TPS aktif
$tps_data = [];
$sql = "SELECT id_tps, nama_tps, alamat_tps, kontak_tps 
        FROM tb_tps 
        WHERE status_tps = 'aktif' 
        ORDER BY nama_tps ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tps_data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Cari TPS | <?php echo htmlspecialchars($nama_banksampah); ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        body{font-family:'Inter',sans-serif;}
        .card-hover{transition: all .3s ease;}
        .card-hover:hover{transform: translateY(-5px);}
        .text-gradient{background: linear-gradient(135deg,#7c3aed,#10b981);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header -->
    <header class="bg-white shadow fixed top-0 left-0 w-full z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center gap-3">
                <img src="../assets/img/logo.jpg" alt="Logo" class="h-10 w-10 rounded-full">
                <span class="font-bold text-gradient text-lg"><?php echo htmlspecialchars($nama_banksampah); ?></span>
            </div>
            <a href="login.php" class="bg-gradient-to-r from-purple-600 to-emerald-600 text-white px-4 py-2 rounded-full shadow hover:from-purple-700 hover:to-emerald-700 transition-all">
                <i class="fas fa-sign-in-alt mr-1"></i> Login
            </a>
        </div>
    </header>

    <!-- Konten -->
    <main class="pt-28 pb-16 px-6 max-w-7xl mx-auto">
        <div class="bg-white rounded-2xl p-6 shadow-lg">
            <h1 class="text-2xl font-bold text-gradient mb-6">Cari Lokasi Bank Sampah</h1>
            
            <!-- Input pencarian -->
            <div class="mb-6">
                <input type="text" id="searchInput" placeholder="Ketik nama Bank Sampah atau alamat..."
                    class="w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-purple-500 outline-none shadow-sm"/>
            </div>

            <!-- List TPS -->
            <div id="tpsList" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($tps_data as $tps): 
                    $nama_tps_safe   = htmlspecialchars($tps['nama_tps']);
                    $alamat_tps_safe = htmlspecialchars($tps['alamat_tps']);
                    $kontak          = preg_replace('/\D+/', '', $tps['kontak_tps']);
                    $wa_text = urlencode("Halo admin " . $nama_banksampah . " di Bank Sampah {$tps['nama_tps']}, saya ingin mendaftar sebagai nasabah.");
                    $wa_link = "https://wa.me/{$kontak}?text={$wa_text}";
                ?>
                <div class="card-hover bg-gray-50 rounded-xl p-5 shadow border border-gray-100 tps-card"
                    data-nama="<?= strtolower($nama_tps_safe); ?>" data-alamat="<?= strtolower($alamat_tps_safe); ?>">
                    <h3 class="text-lg font-bold text-gray-900 mb-2"><?= $nama_tps_safe; ?></h3>
                    <p class="text-sm text-gray-600 mb-4"><?= $alamat_tps_safe; ?></p>
                    <a href="<?= $wa_link; ?>" target="_blank"
                       class="inline-flex items-center gap-2 bg-emerald-500 text-white px-4 py-2 rounded-lg hover:bg-emerald-600 transition-all">
                        <i class="fab fa-whatsapp"></i> Daftar via WhatsApp
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tidak ada hasil -->
            <p id="noResult" class="hidden text-center text-gray-500 mt-6">Tidak ada Bank Sampah yang cocok dengan pencarian Anda.</p>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById("searchInput");
        const tpsCards = document.querySelectorAll(".tps-card");
        const noResult = document.getElementById("noResult");

        searchInput.addEventListener("input", function() {
            const keyword = this.value.toLowerCase();
            let visibleCount = 0;

            tpsCards.forEach(card => {
                const nama = card.getAttribute("data-nama");
                const alamat = card.getAttribute("data-alamat");

                if (nama.includes(keyword) || alamat.includes(keyword)) {
                    card.classList.remove("hidden");
                    visibleCount++;
                } else {
                    card.classList.add("hidden");
                }
            });

            noResult.classList.toggle("hidden", visibleCount > 0);
        });
    </script>
  <footer class="bg-gradient-to-r from-gray-900 via-purple-900 to-emerald-900 text-white py-8 text-center">
        <p>© <?php echo date("Y"); ?> <?php echo htmlspecialchars($nama_banksampah); ?>. Dikembangkan oleh mahasiswa Politeknik Piksi Ganesha Indonesia</p>
    </footer>
</body>
</html>
