<?php
// admin/penjualan_sampah.php

// Pastikan session dimulai di awal file
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set halaman aktif untuk sidebar
$current_page = 'penjualan_sampah';

// Memuat file-file yang dibutuhkan
require_once '../includes/header.php';
require_once '../config/db.php';

// --- Autentikasi & Otorisasi ---
// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id_tps_admin = $_SESSION['id_tps'];
$message = '';
$message_type = '';

function redirect_with_message($msg, $type) {
    $queryString = http_build_query(['msg' => $msg, 'type' => $type]);
    header("Location: penjualan_sampah.php?" . $queryString);
    exit();
}

function get_config_value($conn_obj, $setting_name, $id_tps, $default_value = null) {
    $sql = "SELECT nilai FROM tb_konfigurasi WHERE nama_setting = ? AND id_tps = ?";
    $stmt = $conn_obj->prepare($sql);
    if ($stmt === FALSE) {
        error_log("Error preparing get_config_value: " . $conn_obj->error);
        return $default_value;
    }
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

$persen_nasabah_config = get_config_value($conn, 'persen_nasabah', $id_tps_admin, 70.00);
$persen_tps_config = get_config_value($conn, 'persen_tps', $id_tps_admin, 25.00);
$persen_pengepul_config = get_config_value($conn, 'persen_pengepul', $id_tps_admin, 5.00);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'finalize_penjualan') {
    $kode_sampah_to_finalize = trim($_POST['kode_sampah_final']);
    $harga_per_kg_final = filter_var($_POST['harga_per_kg_final'], FILTER_VALIDATE_FLOAT);
    $berat_jual = filter_var($_POST['berat_jual'], FILTER_VALIDATE_FLOAT);

    if (empty($kode_sampah_to_finalize) || $harga_per_kg_final === FALSE || $harga_per_kg_final <= 0 || $berat_jual === FALSE || $berat_jual <= 0) {
        $message = "Input tidak valid. Pastikan semua kolom diisi dengan angka positif.";
        $message_type = "danger";
    } else {
        $conn->begin_transaction();
        try {
            // Ambil total berat pending untuk jenis sampah ini di TPS yang sama
            $sql_total_pending = "SELECT SUM(berat_kg) as total_berat_kg FROM tb_setorsampah WHERE kode_sampah = ? AND status_setoran = 'pending_harga' AND id_tps = ?";
            $stmt_total_pending = $conn->prepare($sql_total_pending);
            $stmt_total_pending->bind_param("si", $kode_sampah_to_finalize, $id_tps_admin);
            $stmt_total_pending->execute();
            $result_total_pending = $stmt_total_pending->get_result()->fetch_assoc();
            $total_berat_pending = $result_total_pending['total_berat_kg'] ?? 0;
            $stmt_total_pending->close();

            if ($berat_jual > $total_berat_pending) {
                throw new Exception("Berat jual ({$berat_jual} KG) tidak boleh melebihi total berat pending ({$total_berat_pending} KG).");
            }

            // Hitung persentase penjualan dari total berat pending
            $persentase_penjualan = ($berat_jual / $total_berat_pending);

            // --- PERBAIKAN DI SINI: tambahkan `kode_sampah` di kueri SELECT ---
            $sql_get_pending = "SELECT id_transaksi, no_rekening, tanggal_pengambilan, jenis_sampah, kategori, kode_sampah, berat_kg, keterangan FROM tb_setorsampah WHERE kode_sampah = ? AND status_setoran = 'pending_harga' AND id_tps = ? ORDER BY id_transaksi ASC";
            $stmt_get_pending = $conn->prepare($sql_get_pending);
            $stmt_get_pending->bind_param("si", $kode_sampah_to_finalize, $id_tps_admin);
            $stmt_get_pending->execute();
            $result_pending = $stmt_get_pending->get_result();
            $items_to_process = $result_pending->fetch_all(MYSQLI_ASSOC);
            $stmt_get_pending->close();

            if (empty($items_to_process)) {
                throw new Exception("Tidak ada setoran pending untuk jenis sampah ini.");
            }

            $finalized_count = 0;
            $total_dana_nasabah = 0;
            
            // Perulangan untuk setiap transaksi pending
            foreach ($items_to_process as $item) {
                $berat_setoran_ini = $item['berat_kg'];
                $berat_yg_dijual = $berat_setoran_ini * $persentase_penjualan;
                $berat_sisa = $berat_setoran_ini - $berat_yg_dijual;

                // Hitung nilai dan pembagian untuk porsi yang dijual
                $total_nilai_dijual = $berat_yg_dijual * $harga_per_kg_final;
                $tabungan_share = $total_nilai_dijual * ($persen_nasabah_config / 100);
                $pos_share = $total_nilai_dijual * ($persen_tps_config / 100);
                $tps_share = $total_nilai_dijual * ($persen_pengepul_config / 100);

                // Perbarui total dana untuk ringkasan
                $total_dana_nasabah += $tabungan_share;

                // Buat transaksi baru untuk porsi yang dijual
                $sql_insert_final = "INSERT INTO tb_setorsampah (tanggal_pengambilan, no_rekening, id_tps, nama_nasabah, kode_sampah, jenis_sampah, kategori, berat_kg, status_setoran, harga_per_kg, total, tabungan_nasabah, pos_penimbangan, tps3r, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'final', ?, ?, ?, ?, ?, ?)";
                $stmt_insert_final = $conn->prepare($sql_insert_final);
                
                // Perlu diambil lagi nama nasabah, karena tidak ada di SELECT awal
                $nama_nasabah_from_db = "SELECT nama_nasabah FROM tb_nasabah WHERE no_rekening = ? LIMIT 1";
                $stmt_nasabah_name = $conn->prepare($nama_nasabah_from_db);
                $stmt_nasabah_name->bind_param("s", $item['no_rekening']);
                $stmt_nasabah_name->execute();
                $nama_nasabah_row = $stmt_nasabah_name->get_result()->fetch_assoc();
                $nama_nasabah = $nama_nasabah_row['nama_nasabah'] ?? 'Nasabah Tidak Ditemukan';
                $stmt_nasabah_name->close();

                // bind_param yang telah diperbaiki
                $stmt_insert_final->bind_param(
                    "ssissssdddddds",
                    $item['tanggal_pengambilan'],
                    $item['no_rekening'],
                    $id_tps_admin,
                    $nama_nasabah,
                    $item['kode_sampah'],
                    $item['jenis_sampah'],
                    $item['kategori'],
                    $berat_yg_dijual,
                    $harga_per_kg_final,
                    $total_nilai_dijual,
                    $tabungan_share,
                    $pos_share,
                    $tps_share,
                    $item['keterangan']
                );
                $stmt_insert_final->execute();
                $stmt_insert_final->close();

                // Catat mutasi saldo untuk porsi yang dijual
                $sql_mutasi = "INSERT INTO tb_tabungan_nasabah (no_rekening, id_tps, tanggal_mutasi, tipe_mutasi, jumlah_mutasi, keterangan) VALUES (?, ?, ?, 'masuk', ?, ?)";
                $stmt_mutasi = $conn->prepare($sql_mutasi);
                $keterangan_mutasi = "Pendapatan dari penjualan sampah " . htmlspecialchars($item['jenis_sampah']) . " (" . number_format($berat_yg_dijual, 2, ',', '.') . " kg).";
                $tanggal_mutasi = date('Y-m-d');
                $stmt_mutasi->bind_param("sisds", $item['no_rekening'], $id_tps_admin, $tanggal_mutasi, $tabungan_share, $keterangan_mutasi);
                $stmt_mutasi->execute();
                $stmt_mutasi->close();

                // Update transaksi asli dengan sisa berat
                $sql_update_original = "UPDATE tb_setorsampah SET berat_kg = ? WHERE id_transaksi = ?";
                $stmt_update_original = $conn->prepare($sql_update_original);
                $stmt_update_original->bind_param("di", $berat_sisa, $item['id_transaksi']);
                $stmt_update_original->execute();
                $stmt_update_original->close();
                
                $finalized_count++;
            }

            // Catat transaksi penjualan global ke pengepul
            $sql_insert_penjualan_pengepul = "INSERT INTO tb_transaksi_penjualan (tanggal_jual, id_tps, kode_sampah, berat_kg, harga_jual_per_kg, nama_pengepul, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert_penjualan_pengepul = $conn->prepare($sql_insert_penjualan_pengepul);
            if ($stmt_insert_penjualan_pengepul === FALSE) {
                throw new Exception("Error preparing tb_transaksi_penjualan insertion: " . $conn->error);
            }
            $tanggal_jual = date('Y-m-d');
            $nama_pengepul = 'Pengepul';
            $keterangan_penjualan = "Penjualan sampah " . htmlspecialchars($items_to_process[0]['jenis_sampah']) . " sebesar " . number_format($berat_jual, 2, ',', '.') . " kg.";
            $stmt_insert_penjualan_pengepul->bind_param("sisddss", $tanggal_jual, $id_tps_admin, $kode_sampah_to_finalize, $berat_jual, $harga_per_kg_final, $nama_pengepul, $keterangan_penjualan);
            $stmt_insert_penjualan_pengepul->execute();
            $stmt_insert_penjualan_pengepul->close();

            $conn->commit();
            $final_message = "Berhasil memfinalisasi penjualan untuk jenis sampah '" . htmlspecialchars($items_to_process[0]['jenis_sampah']) . "'. Total berat yang terjual: " . number_format($berat_jual, 2, ',', '.') . " KG. Total dana nasabah: Rp " . number_format($total_dana_nasabah, 2, ',', '.');
            redirect_with_message($final_message, "success");

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Finalize transaction failed in penjualan_sampah.php: " . $e->getMessage());
            $message = "Terjadi kesalahan sistem saat finalisasi penjualan: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Mengambil Ringkasan Setoran Pending untuk Tampilan
$pending_summary = [];
$sql_summary = "SELECT s.kode_sampah, s.jenis_sampah, s.kategori, SUM(s.berat_kg) as total_berat_kg, COUNT(s.id_transaksi) as total_setoran
                FROM tb_setorsampah s
                WHERE s.id_tps = ? AND s.status_setoran = 'pending_harga'
                GROUP BY s.kode_sampah, s.jenis_sampah, s.kategori
                ORDER BY s.jenis_sampah ASC";
$stmt_summary = $conn->prepare($sql_summary);
$stmt_summary->bind_param("i", $id_tps_admin);
$stmt_summary->execute();
$result_summary = $stmt_summary->get_result();
if ($result_summary) {
    while ($row = $result_summary->fetch_assoc()) {
        $pending_summary[] = $row;
    }
}
$stmt_summary->close();

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
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Penjualan Sampah ke Pengepul</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type == 'success') ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-<?php echo ($message_type == 'success') ? 'purple-500' : 'red-500'; ?> rounded-lg focus:ring-2 focus:ring-<?php echo ($message_type == 'success') ? 'purple-400' : 'red-400'; ?> p-1.5 hover:bg-<?php echo ($message_type == 'success') ? 'purple-200' : 'red-200'; ?> inline-flex h-8 w-8" onclick="this.parentElement.style.display='none';" aria-label="Close">
                <span class="sr-only">Close</span>
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Setoran Pending Siap Jual</h2>

        <div class="mb-4">
            <button type="button" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center"
                data-modal-target="finalizeModal" data-modal-toggle="finalizeModal">
                <i class="fas fa-money-bill-wave mr-1"></i> Jual Sampah
            </button>
        </div>

        <?php if (empty($pending_summary)): ?>
            <div class="p-4 text-sm text-blue-800 rounded-lg bg-blue-100 dark:bg-gray-800 dark:text-blue-400" role="alert">
                Belum ada setoran sampah nasabah yang menunggu penjualan.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="pendingSummaryTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Sampah</th>
                            
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Berat (KG)</th>
                        
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pending_summary as $summary): ?>
                            <tr data-kode_sampah="<?php echo htmlspecialchars($summary['kode_sampah']); ?>"
                                data-jenis_sampah="<?php echo htmlspecialchars($summary['jenis_sampah']); ?>"
                                data-total_berat_kg="<?php echo htmlspecialchars($summary['total_berat_kg']); ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($summary['jenis_sampah']); ?></td>
                               
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo number_format($summary['total_berat_kg'], 2, ',', '.'); ?></td>
                               
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="finalizeModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative p-4 w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t bg-purple-600 dark:border-gray-600">
                <h3 class="text-lg font-semibold text-white dark:text-white">
                    Finalisasi Penjualan Sampah
                </h3>
                <button type="button" class="text-gray-200 bg-transparent hover:bg-purple-700 hover:text-white rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="finalizeModal">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
            </div>
            <form action="penjualan_sampah.php" method="POST">
                <input type="hidden" name="action" value="finalize_penjualan">
                <input type="hidden" id="kode_sampah_final" name="kode_sampah_final">
                <div class="p-4 md:p-5 space-y-4">
                    <div class="mb-4">
                        <label for="select_jenis_sampah" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Pilih Jenis Sampah</label>
                        <select id="select_jenis_sampah" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <option value="" disabled selected>-- Pilih Jenis Sampah --</option>
                        </select>
                    </div>

                    <p class="text-base leading-relaxed text-gray-700 dark:text-gray-400">
                        Total berat sampah pending: <strong><span id="display_total_berat_kg">0.00</span> KG</strong>
                    </p>

                    <hr class="my-4 border-gray-200 dark:border-gray-600">

                    <div class="mb-4">
                        <label for="berat_jual" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Berat yang Dijual (KG) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" id="berat_jual" name="berat_jual" required min="0.01">
                        <small class="text-red-500 text-xs mt-1 block" id="berat_jual_error"></small>
                    </div>

                    <div class="mb-4">
                        <label for="harga_per_kg_final" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Harga per KG dari Pengepul (Rp) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" id="harga_per_kg_final" name="harga_per_kg_final" required min="0.01">
                    </div>

                    <hr class="my-4 border-gray-200 dark:border-gray-600">
                    
                    <div class="mb-4">
                        <label for="total_penjualan_display" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Estimasi Total Penjualan (Rp)</label>
                        <input type="text" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 cursor-not-allowed dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" id="total_penjualan_display" readonly>
                    </div>
                    <div class="mb-4">
                        <label for="total_nasabah_display" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Estimasi Total untuk Nasabah (Rp) (<?php echo number_format($persen_nasabah_config, 0); ?>%)</label>
                        <input type="text" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5 cursor-not-allowed dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white" id="total_nasabah_display" readonly>
                    </div>
                    
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-600">
                    <button type="submit" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-purple-600 dark:hover:bg-purple-700 dark:focus:ring-blue-800" id="submitFinalizeBtn" disabled>Finalisasi Penjualan</button>
                    <button data-modal-hide="finalizeModal" type="button" class="ms-3 text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const finalizeModalEl = document.getElementById('finalizeModal');
    const selectJenisSampah = document.getElementById('select_jenis_sampah');
    const hargaPerKgFinalInput = document.getElementById('harga_per_kg_final');
    const beratJualInput = document.getElementById('berat_jual');
    const beratJualErrorEl = document.getElementById('berat_jual_error');
    const submitFinalizeBtn = document.getElementById('submitFinalizeBtn');
    
    let totalBeratPending = 0;
    const persenNasabah = parseFloat(<?php echo json_encode($persen_nasabah_config); ?>);
    
    // Ambil data dari tabel HTML dan simpan di objek JS
    const pendingData = {};
    const tableRows = document.querySelectorAll('#pendingSummaryTable tbody tr');
    tableRows.forEach(row => {
        const kode = row.getAttribute('data-kode_sampah');
        pendingData[kode] = {
            jenis_sampah: row.getAttribute('data-jenis_sampah'),
            total_berat_kg: parseFloat(row.getAttribute('data-total_berat_kg'))
        };
    });

    // Populate dropdown saat modal dibuka
    const modalShowButton = document.querySelector('[data-modal-toggle="finalizeModal"]');
    modalShowButton.addEventListener('click', function() {
        // Reset dropdown
        selectJenisSampah.innerHTML = '<option value="" disabled selected>-- Pilih Jenis Sampah --</option>';
        for (const kode in pendingData) {
            const option = document.createElement('option');
            option.value = kode;
            option.textContent = pendingData[kode].jenis_sampah;
            selectJenisSampah.appendChild(option);
        }
        
        // Reset form
        document.getElementById('display_total_berat_kg').textContent = '0.00';
        beratJualInput.value = '';
        hargaPerKgFinalInput.value = '';
        beratJualInput.disabled = true;
        hargaPerKgFinalInput.disabled = true;
        beratJualErrorEl.textContent = '';
        submitFinalizeBtn.disabled = true;
        calculateAndDisplay();
    });

    // Event listener untuk dropdown
    selectJenisSampah.addEventListener('change', function() {
        const kodeSampah = this.value;
        if (kodeSampah && pendingData[kodeSampah]) {
            totalBeratPending = pendingData[kodeSampah].total_berat_kg;
            document.getElementById('kode_sampah_final').value = kodeSampah;
            document.getElementById('display_total_berat_kg').textContent = totalBeratPending.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Aktifkan input dan isi berat jual otomatis
            beratJualInput.disabled = false;
            hargaPerKgFinalInput.disabled = false;
            beratJualInput.value = totalBeratPending.toFixed(2);
            hargaPerKgFinalInput.value = '';
            beratJualErrorEl.textContent = '';
            submitFinalizeBtn.disabled = false;

            calculateAndDisplay();
            hargaPerKgFinalInput.focus();

        } else {
            totalBeratPending = 0;
            document.getElementById('kode_sampah_final').value = '';
            document.getElementById('display_total_berat_kg').textContent = '0.00';
            beratJualInput.value = '';
            hargaPerKgFinalInput.value = '';
            beratJualInput.disabled = true;
            hargaPerKgFinalInput.disabled = true;
            beratJualErrorEl.textContent = '';
            submitFinalizeBtn.disabled = true;

            calculateAndDisplay();
        }
    });

    function calculateAndDisplay() {
        const beratJual = parseFloat(beratJualInput.value) || 0;
        const hargaPerKg = parseFloat(hargaPerKgFinalInput.value) || 0;
        const totalPenjualan = beratJual * hargaPerKg;
        const totalNasabah = totalPenjualan * (persenNasabah / 100);

        const formatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        document.getElementById('total_penjualan_display').value = formatter.format(totalPenjualan);
        document.getElementById('total_nasabah_display').value = formatter.format(totalNasabah);
    }

    hargaPerKgFinalInput.addEventListener('input', calculateAndDisplay);
    beratJualInput.addEventListener('input', function() {
        const beratJual = parseFloat(this.value);
        if (isNaN(beratJual) || beratJual <= 0) {
            beratJualErrorEl.textContent = "Berat jual harus lebih dari 0.";
            submitFinalizeBtn.disabled = true;
        } else if (beratJual > totalBeratPending) {
            beratJualErrorEl.textContent = "Berat jual tidak boleh melebihi total berat pending.";
            submitFinalizeBtn.disabled = true;
        } else {
            beratJualErrorEl.textContent = "";
            submitFinalizeBtn.disabled = false;
        }
        calculateAndDisplay();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>