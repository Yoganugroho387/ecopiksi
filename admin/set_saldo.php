<?php
// admin/set_saldo.php

// 1) Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2) Page marker for sidebar
$current_page = 'set_saldo';

// 3) Includes
require_once '../includes/header.php';
require_once '../config/db.php';

// 4) AuthZ: hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// 5) Context
$id_tps_admin = $_SESSION['id_tps'] ?? null;
$message = '';
$message_type = '';
$search_query = $_GET['search'] ?? '';

// Helper: redirect dengan flash message via query string
function redirect_with_message($path, $msg, $type) {
    $qs = http_build_query(['msg' => $msg, 'type' => $type]);
    header('Location: ' . $path . '?' . $qs);
    exit();
}

// Helper: ambil saldo saat ini (scope TPS) untuk verifikasi transaksi
function get_current_saldo(mysqli $conn, $no_rekening, $id_tps) {
    $sql = "SELECT 
                COALESCE(SUM(CASE WHEN tipe_mutasi='masuk'  THEN jumlah_mutasi ELSE 0 END),0) -
                COALESCE(SUM(CASE WHEN tipe_mutasi='keluar' THEN jumlah_mutasi ELSE 0 END),0) AS saldo
            FROM tb_tabungan_nasabah 
            WHERE no_rekening = ? AND id_tps = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $no_rekening, $id_tps);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($res['saldo'] ?? 0);
}

// --- Ambil daftar nasabah BESERTA SALDO untuk TPS ini (dropdown) ---
$nasabah_list = [];

if ($id_tps_admin !== null) {
    // Query dimodifikasi untuk mengambil saldo & mendukung pencarian
    $sql_nasabah = "SELECT 
                        n.no_rekening, 
                        n.nama_nasabah,
                        (
                            COALESCE(SUM(CASE WHEN t.tipe_mutasi = 'masuk' THEN t.jumlah_mutasi ELSE 0 END), 0) -
                            COALESCE(SUM(CASE WHEN t.tipe_mutasi = 'keluar' THEN t.jumlah_mutasi ELSE 0 END), 0)
                        ) AS saldo_saat_ini
                    FROM tb_nasabah n
                    LEFT JOIN tb_tabungan_nasabah t ON n.no_rekening = t.no_rekening AND t.id_tps = n.id_tps
                    WHERE n.role='nasabah' AND n.id_tps = ?";

    $params = ["i", $id_tps_admin];

    // Tambahkan filter pencarian jika ada input
    if (!empty($search_query)) {
        $sql_nasabah .= " AND (n.nama_nasabah LIKE ? OR n.no_rekening LIKE ?)";
        $search_param = "%" . $search_query . "%";
        $params[0] .= "ss"; // Menambahkan tipe string untuk bind_param
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $sql_nasabah .= " GROUP BY n.no_rekening, n.nama_nasabah ORDER BY n.nama_nasabah ASC";

    $stmt_n = $conn->prepare($sql_nasabah);
    $stmt_n->bind_param(...$params);
    $stmt_n->execute();
    $res_n = $stmt_n->get_result();
    
    while ($row = $res_n->fetch_assoc()) {
        // Format tampilan: Nama Nasabah (Rp Saldo)
        $saldo_formatted = number_format($row['saldo_saat_ini'], 0, ',', '.');
        $label = htmlspecialchars($row['nama_nasabah']) . " (Rp " . $saldo_formatted . ")";
        $nasabah_list[$row['no_rekening']] = $label;
    }
    $stmt_n->close();
} else {
    redirect_with_message('set_saldo.php', 'Sesi tidak valid: ID TPS tidak ditemukan. Silakan login ulang.', 'danger');
}

// --- Handle POST: set saldo ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_saldo') {
    $no_rekening = trim($_POST['no_rekening'] ?? '');
    $raw = $_POST['target_saldo'] ?? '';

    // Normalisasi angka: dukung "10.000,50" (ID) dan "10000.50" (EN)
    if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $raw)) {
        // Format Indonesia => hapus titik, ganti koma jadi titik
        $normalized = str_replace('.', '', $raw);
        $normalized = str_replace(',', '.', $normalized);
        $target_saldo_f = (float)$normalized;
    } else {
        // Format umum (tanpa pemisah ribuan atau pakai titik desimal)
        $target_saldo_f = (float)preg_replace('/[^\d.]/', '', $raw);
    }

    // Validasi
    $errors = [];
    // Cek apakah nasabah ada di list (berarti valid dan milik TPS ini)
    if ($no_rekening === '' || !isset($nasabah_list[$no_rekening])) {
        $errors[] = 'Pilih nasabah yang valid.';
    }
    if (!is_finite($target_saldo_f) || $target_saldo_f < 0) {
        $errors[] = 'Nominal saldo harus angka dan tidak boleh negatif.';
    }

    if (!empty($errors)) {
        redirect_with_message('set_saldo.php', implode(' ', $errors), 'danger');
    }

    // Hitung selisih saldo
    $current_saldo = get_current_saldo($conn, $no_rekening, $id_tps_admin);
    $diff = round($target_saldo_f - $current_saldo, 2);

    if (abs($diff) < 0.005) { // Tidak ada perubahan signifikan
        redirect_with_message('set_saldo.php', 'Saldo sudah sesuai. Tidak ada perubahan yang dilakukan.', 'info');
    }

    // Siapkan 1 entri penyesuaian
    $tipe = ($diff > 0) ? 'masuk' : 'keluar';
    $jumlah = abs($diff);
    $keterangan = 'Penyesuaian saldo manual oleh admin ke saldo target Rp ' . number_format($target_saldo_f, 2, ',', '.');

    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO tb_tabungan_nasabah 
                (no_rekening, id_tps, tanggal_mutasi, tipe_mutasi, jumlah_mutasi, keterangan)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $today = date('Y-m-d');
        $stmt->bind_param('sissds', $no_rekening, $id_tps_admin, $today, $tipe, $jumlah, $keterangan);
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan penyesuaian saldo: ' . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        redirect_with_message(
            'set_saldo.php',
            'Berhasil menyesuaikan saldo nasabah. Selisih: ' . ($tipe === 'masuk' ? '+' : '-') . 'Rp ' . number_format($jumlah, 2, ',', '.'),
            'success'
        );
    } catch (Exception $e) {
        $conn->rollback();
        redirect_with_message('set_saldo.php', 'Terjadi kesalahan: ' . $e->getMessage(), 'danger');
    }
}

// --- Flash message dari redirect
if (isset($_GET['msg'], $_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

// --- Panel cek saldo + mutasi (GET)
$selected_no_rekening = $_GET['nasabah'] ?? '';
$current_saldo_selected = null;
$mutasi_rows = [];

if ($selected_no_rekening !== '' && isset($nasabah_list[$selected_no_rekening])) {
    $current_saldo_selected = get_current_saldo($conn, $selected_no_rekening, $id_tps_admin);
    $sql_m = "SELECT tanggal_mutasi, tipe_mutasi, jumlah_mutasi, keterangan
              FROM tb_tabungan_nasabah
              WHERE no_rekening=? AND id_tps=?
              ORDER BY tanggal_mutasi DESC, id_mutasi DESC
              LIMIT 10";
    $stmtm = $conn->prepare($sql_m);
    $stmtm->bind_param('si', $selected_no_rekening, $id_tps_admin);
    $stmtm->execute();
    $resm = $stmtm->get_result();
    while ($r = $resm->fetch_assoc()) { $mutasi_rows[] = $r; }
    $stmtm->close();
}
?>
<div class="max-w-6xl mx-auto p-4">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Set Saldo Nasabah</h1>
        <p class="text-sm text-gray-600">Sesuaikan saldo tabungan nasabah dengan membuat entri penyesuaian otomatis.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo ($message_type==='success')?'bg-green-50 text-green-800':(($message_type==='danger')?'bg-red-50 text-red-800':(($message_type==='info')?'bg-blue-50 text-blue-800':'bg-yellow-50 text-yellow-800')); ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow p-5">
            <h2 class="text-lg font-semibold mb-4">Form Penyesuaian Saldo</h2>
            <form method="POST" action="set_saldo.php" class="space-y-4">
                <input type="hidden" name="action" value="set_saldo" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nasabah</label>
                    <select name="no_rekening" class="w-full border rounded-lg p-2 focus:outline-none focus:ring" required>
                        <option value="">-- Pilih Nasabah --</option>
                        <?php foreach ($nasabah_list as $rek => $label): ?>
                            <option value="<?php echo htmlspecialchars($rek); ?>" <?php echo ($selected_no_rekening === $rek) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($nasabah_list)): ?>
                        <p class="text-xs text-red-500 mt-1">Tidak ada nasabah ditemukan. Coba ubah kata kunci pencarian.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Saldo Target (Rp)</label>
                    <input type="text" name="target_saldo" placeholder="cth: 150.000 atau 150000" class="w-full border rounded-lg p-2 focus:outline-none focus:ring" required />
                    <p class="text-xs text-gray-500 mt-1">Masukkan nominal akhir yang diinginkan (Total Saldo). Sistem akan otomatis menghitung selisih.</p>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Simpan</button>
                    <a href="set_saldo.php" class="px-4 py-2 rounded-xl border font-medium hover:bg-gray-50">Reset Form</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow p-5">
            <h2 class="text-lg font-semibold mb-4">Cek Riwayat Mutasi</h2>
            <form method="GET" action="set_saldo.php" class="flex items-end gap-3 mb-4">
                <?php if(!empty($search_query)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                <?php endif; ?>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Nasabah</label>
                    <select name="nasabah" class="w-full border rounded-lg p-2 focus:outline-none focus:ring">
                        <option value="">-- Pilih Nasabah --</option>
                        <?php foreach ($nasabah_list as $rek => $label): ?>
                            <option value="<?php echo htmlspecialchars($rek); ?>" <?php echo ($selected_no_rekening===$rek)?'selected':''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Lihat</button>
            </form>

            <?php if ($selected_no_rekening && isset($nasabah_list[$selected_no_rekening])): ?>
                <div class="mb-4 p-4 bg-gray-50 rounded-lg border">
                    <div class="text-sm text-gray-600">Saldo Saat Ini</div>
                    <div class="text-2xl font-bold text-gray-800">Rp <?php echo number_format(($current_saldo_selected ?? 0), 2, ',', '.'); ?></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-600 border-b">
                                <th class="py-2 pr-4">Tanggal</th>
                                <th class="py-2 pr-4">Tipe</th>
                                <th class="py-2 pr-4">Jumlah</th>
                                <th class="py-2 pr-4">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                        <?php if (!empty($mutasi_rows)): ?>
                            <?php foreach ($mutasi_rows as $m): ?>
                                <tr>
                                    <td class="py-2 pr-4 whitespace-nowrap"><?php echo htmlspecialchars($m['tanggal_mutasi']); ?></td>
                                    <td class="py-2 pr-4">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo ($m['tipe_mutasi']==='masuk'?'bg-green-100 text-green-700':'bg-rose-100 text-rose-700'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($m['tipe_mutasi'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-2 pr-4">Rp <?php echo number_format($m['jumlah_mutasi'], 2, ',', '.'); ?></td>
                                    <td class="py-2 pr-4 text-gray-700 truncate max-w-xs" title="<?php echo htmlspecialchars($m['keterangan']); ?>">
                                        <?php echo htmlspecialchars($m['keterangan']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="py-4 text-center text-gray-500">Belum ada data mutasi untuk nasabah ini.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-10 text-gray-500">
                    <i class="fas fa-user-clock text-4xl mb-2 text-gray-300"></i>
                    <p>Pilih nasabah di atas untuk melihat saldo detail dan 10 riwayat transaksi terakhir.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>