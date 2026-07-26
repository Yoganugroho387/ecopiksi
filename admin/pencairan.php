<?php
// admin/pencairan.php
ob_start();
require_once '../includes/header.php';
require_once '../config/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id_tps_admin = $_SESSION['id_tps'];
$message = '';
$message_type = '';
$search_query = '';

// --- LOGIKA: Tambah Pencairan Manual (Oleh Admin) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'manual_pencairan') {
    $no_rekening = $_POST['no_rekening'];
    $jumlah_cair = floatval($_POST['jumlah_cair']);
    $keterangan = "Pencairan tunai manual oleh Admin via Loket.";
    $tanggal = date('Y-m-d');

    // 1. Cek Saldo Nasabah (Validasi Server Side)
    $sql_saldo = "SELECT 
                    (COALESCE(SUM(CASE WHEN tipe_mutasi='masuk' THEN jumlah_mutasi ELSE 0 END), 0) - 
                     COALESCE(SUM(CASE WHEN tipe_mutasi='keluar' THEN jumlah_mutasi ELSE 0 END), 0)) as saldo_akhir
                  FROM tb_tabungan_nasabah WHERE no_rekening = ? AND id_tps = ?";
    $stmt_cek = $conn->prepare($sql_saldo);
    $stmt_cek->bind_param("si", $no_rekening, $id_tps_admin);
    $stmt_cek->execute();
    $res_cek = $stmt_cek->get_result()->fetch_assoc();
    $saldo_saat_ini = $res_cek['saldo_akhir'] ?? 0;
    $stmt_cek->close();

    if ($jumlah_cair > $saldo_saat_ini) {
        $message = "Gagal! Saldo nasabah tidak cukup. Saldo saat ini: Rp " . number_format($saldo_saat_ini, 0, ',', '.');
        $message_type = "danger";
    } else if ($jumlah_cair <= 0) {
        $message = "Jumlah pencairan tidak valid.";
        $message_type = "danger";
    } else {
        // 2. Insert ke tb_pencairan_dana (Langsung Diterima)
        $conn->begin_transaction();
        try {
            $sql_insert = "INSERT INTO tb_pencairan_dana (no_rekening, tanggal_pencairan, jumlah_cair, status, id_tps, keterangan, tanggal_transfer) VALUES (?, ?, ?, 'diterima', ?, ?, ?)";
            $stmt_ins = $conn->prepare($sql_insert);
            $stmt_ins->bind_param("ssdiss", $no_rekening, $tanggal, $jumlah_cair, $id_tps_admin, $keterangan, $tanggal);
            $stmt_ins->execute();
            $stmt_ins->close();

            // 3. Potong Saldo (Insert Mutasi Keluar)
            $ket_mutasi = "Pencairan Tunai Rp " . number_format($jumlah_cair, 0, ',', '.') . " (Manual Admin)";
            $sql_mutasi = "INSERT INTO tb_tabungan_nasabah (no_rekening, id_tps, tanggal_mutasi, tipe_mutasi, jumlah_mutasi, keterangan) VALUES (?, ?, ?, 'keluar', ?, ?)";
            $stmt_mut = $conn->prepare($sql_mutasi);
            $stmt_mut->bind_param("sisds", $no_rekening, $id_tps_admin, $tanggal, $jumlah_cair, $ket_mutasi);
            $stmt_mut->execute();
            $stmt_mut->close();

            $conn->commit();
            $message = "Pencairan manual berhasil dicatat dan saldo telah dipotong.";
            $message_type = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Gagal memproses pencairan: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    // Redirect agar tidak resubmit form
    header("Location: pencairan.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}

// --- LOGIKA: Update Status Pencairan (Dari Request Nasabah) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status_pencairan') {
    $id_pencairan = intval($_POST['id_pencairan']);
    $new_status = $conn->real_escape_string($_POST['new_status']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);
    $tanggal_transfer = !empty($_POST['tanggal_transfer']) ? $_POST['tanggal_transfer'] : date('Y-m-d');

    // Ambil data lama
    $sql_get = "SELECT no_rekening, jumlah_cair, status FROM tb_pencairan_dana WHERE id_pencairan = ? AND id_tps = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("ii", $id_pencairan, $id_tps_admin);
    $stmt_get->execute();
    $request_data = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if ($request_data && $request_data['status'] == 'pending') {
        $no_rekening = $request_data['no_rekening'];
        $jumlah_cair = $request_data['jumlah_cair'];

        // Cek saldo dulu jika akan disetujui
        if ($new_status == 'diterima') {
             $sql_saldo_cek = "SELECT 
                    (COALESCE(SUM(CASE WHEN tipe_mutasi='masuk' THEN jumlah_mutasi ELSE 0 END), 0) - 
                     COALESCE(SUM(CASE WHEN tipe_mutasi='keluar' THEN jumlah_mutasi ELSE 0 END), 0)) as saldo_akhir
                  FROM tb_tabungan_nasabah WHERE no_rekening = ? AND id_tps = ?";
            $stmt_s = $conn->prepare($sql_saldo_cek);
            $stmt_s->bind_param("si", $no_rekening, $id_tps_admin);
            $stmt_s->execute();
            $res_s = $stmt_s->get_result()->fetch_assoc();
            $saldo_current = $res_s['saldo_akhir'] ?? 0;
            $stmt_s->close();

            if ($jumlah_cair > $saldo_current) {
                $message = "Gagal! Saldo nasabah tidak cukup (Rp " . number_format($saldo_current, 0, ',', '.') . ").";
                $message_type = "danger";
                header("Location: pencairan.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
                exit();
            }
        }

        // Update Status
        $sql_update = "UPDATE tb_pencairan_dana SET status = ?, keterangan = ?";
        $params = "ss";
        $values = [$new_status, $keterangan];

        if ($new_status == 'diterima') {
            $sql_update .= ", tanggal_transfer = ?";
            $params .= "s";
            $values[] = $tanggal_transfer;
        }
        $sql_update .= " WHERE id_pencairan = ? AND id_tps = ?";
        $params .= "ii";
        $values[] = $id_pencairan;
        $values[] = $id_tps_admin;

        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param($params, ...$values);
        
        if ($stmt_update->execute()) {
            if ($new_status == 'diterima') {
                // Potong Saldo
                $ket_mutasi = "Pencairan dana Rp " . number_format($jumlah_cair, 2, ',', '.') . " disetujui.";
                $sql_mutasi = "INSERT INTO tb_tabungan_nasabah (no_rekening, id_tps, tanggal_mutasi, tipe_mutasi, jumlah_mutasi, keterangan) VALUES (?, ?, ?, 'keluar', ?, ?)";
                $stmt_m = $conn->prepare($sql_mutasi);
                $stmt_m->bind_param("sisds", $no_rekening, $id_tps_admin, $tanggal_transfer, $jumlah_cair, $ket_mutasi);
                $stmt_m->execute();
                $stmt_m->close();
                $message = "Permintaan disetujui & saldo dipotong.";
            } else {
                $message = "Permintaan ditolak.";
            }
            $message_type = "success";
        } else {
            $message = "Gagal update status.";
            $message_type = "danger";
        }
        $stmt_update->close();
    } else {
        $message = "Data tidak valid atau status bukan pending.";
        $message_type = "danger";
    }
    header("Location: pencairan.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit();
}

// --- Ambil Daftar Nasabah + HITUNG SALDO (Untuk Form Manual) ---
$nasabah_list = [];
$sql_n = "SELECT 
            n.no_rekening, 
            n.nama_nasabah,
            (
                COALESCE(SUM(CASE WHEN t.tipe_mutasi = 'masuk' THEN t.jumlah_mutasi ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN t.tipe_mutasi = 'keluar' THEN t.jumlah_mutasi ELSE 0 END), 0)
            ) AS saldo_akhir
          FROM tb_nasabah n
          LEFT JOIN tb_tabungan_nasabah t ON n.no_rekening = t.no_rekening AND t.id_tps = n.id_tps
          WHERE n.id_tps = ? AND n.role='nasabah'
          GROUP BY n.no_rekening, n.nama_nasabah
          ORDER BY n.nama_nasabah ASC";

$stmt_n = $conn->prepare($sql_n);
$stmt_n->bind_param("i", $id_tps_admin);
$stmt_n->execute();
$res_n = $stmt_n->get_result();

while($row = $res_n->fetch_assoc()) { 
    // Format label dropdown: "Nama (Saldo: Rp xxx)"
    $saldo_fmt = number_format($row['saldo_akhir'], 0, ',', '.');
    $nasabah_list[] = [
        'val' => $row['no_rekening'],
        'label' => $row['nama_nasabah'] . " (Saldo: Rp " . $saldo_fmt . ")"
    ]; 
}
$stmt_n->close();

// --- Tampilkan Data (Search & List dengan Saldo) ---
if (isset($_GET['search'])) $search_query = $conn->real_escape_string($_GET['search']);
if (isset($_GET['msg'], $_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

$pencairan_data = [];
$limit = 10;
$where = " WHERE pd.id_tps = ?";
$p_types = "i";
$p_vals = [$id_tps_admin];

if (!empty($search_query)) {
    $where .= " AND (n.nama_nasabah LIKE ? OR pd.no_rekening LIKE ?)";
    $p_types .= "ss";
    $like = "%$search_query%";
    $p_vals[] = $like;
    $p_vals[] = $like;
}

// Query mengambil data pencairan + Menghitung saldo nasabah terkini untuk tabel list
$sql_list = "SELECT pd.*, n.nama_nasabah,
             (SELECT 
                (COALESCE(SUM(CASE WHEN t.tipe_mutasi='masuk' THEN t.jumlah_mutasi ELSE 0 END), 0) - 
                 COALESCE(SUM(CASE WHEN t.tipe_mutasi='keluar' THEN t.jumlah_mutasi ELSE 0 END), 0))
              FROM tb_tabungan_nasabah t 
              WHERE t.no_rekening = pd.no_rekening AND t.id_tps = pd.id_tps
             ) as saldo_saat_ini
             FROM tb_pencairan_dana pd 
             JOIN tb_nasabah n ON pd.no_rekening = n.no_rekening 
             $where 
             ORDER BY field(pd.status, 'pending', 'diterima', 'ditolak'), pd.tanggal_pencairan DESC 
             LIMIT ?";
$p_types .= "i";
$p_vals[] = $limit;

$stmt_list = $conn->prepare($sql_list);
$stmt_list->bind_param($p_types, ...$p_vals);
$stmt_list->execute();
$res_list = $stmt_list->get_result();
while($row = $res_list->fetch_assoc()) { $pencairan_data[] = $row; }
$stmt_list->close();
$conn->close();
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Pengelolaan Pencairan Dana</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo ($message_type == 'success') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div class="w-full md:w-1/3">
            <form action="pencairan.php" method="GET" class="relative">
                <input type="text" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" name="search" placeholder="Cari nasabah..." value="<?php echo htmlspecialchars($search_query); ?>">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i class="fas fa-search"></i></div>
            </form>
        </div>
        <button data-modal-target="manualModal" data-modal-toggle="manualModal" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center">
            <i class="fas fa-hand-holding-usd mr-2"></i> Pencairan Manual (Admin)
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nasabah (Saldo)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah Cair</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($pencairan_data)): ?>
                    <?php foreach ($pencairan_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($row['tanggal_pencairan']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 font-medium">
                                <?php echo htmlspecialchars($row['nama_nasabah']); ?><br>
                                <span class="text-xs text-gray-500"><?php echo htmlspecialchars($row['no_rekening']); ?></span><br>
                                <span class="text-xs font-bold text-green-600">Saldo: Rp <?php echo number_format($row['saldo_saat_ini'], 0, ',', '.'); ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 font-bold">Rp <?php echo number_format($row['jumlah_cair'], 0, ',', '.'); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo ($row['status'] == 'pending') ? 'bg-yellow-100 text-yellow-800' : (($row['status'] == 'diterima') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($row['keterangan'] ?? '-'); ?></td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <?php if ($row['status'] == 'pending'): ?>
                                    <button type="button" class="text-blue-600 hover:text-blue-900" 
                                        data-modal-target="updateModal" data-modal-toggle="updateModal"
                                        data-id="<?php echo $row['id_pencairan']; ?>"
                                        data-nasabah="<?php echo htmlspecialchars($row['nama_nasabah']); ?>"
                                        data-jumlah="<?php echo $row['jumlah_cair']; ?>">
                                        Proses
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400">Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada data pencairan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="manualModal" tabindex="-1" class="hidden fixed top-0 left-0 right-0 z-50 w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-full max-h-full justify-center items-center flex">
    <div class="relative w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            <div class="flex items-center justify-between p-4 border-b rounded-t bg-purple-600">
                <h3 class="text-lg font-semibold text-white">Pencairan Manual (Tunai)</h3>
                <button type="button" class="text-gray-200 hover:text-white" data-modal-hide="manualModal"><i class="fas fa-times"></i></button>
            </div>
            <form action="pencairan.php" method="POST" class="p-4 space-y-4">
                <input type="hidden" name="action" value="manual_pencairan">
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Pilih Nasabah</label>
                    <select name="no_rekening" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required>
                        <option value="">-- Pilih Nasabah --</option>
                        <?php foreach($nasabah_list as $n): ?>
                            <option value="<?php echo $n['val']; ?>"><?php echo htmlspecialchars($n['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Jumlah Cair (Rp)</label>
                    <input type="number" name="jumlah_cair" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5" required min="100">
                </div>
                <p class="text-xs text-gray-500">Saldo nasabah akan langsung terpotong saat tombol simpan ditekan.</p>
                <div class="flex justify-end border-t pt-4">
                    <button type="submit" class="text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg text-sm px-5 py-2.5">Cairkan Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="updateModal" tabindex="-1" class="hidden fixed top-0 left-0 right-0 z-50 w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-full max-h-full justify-center items-center flex">
    <div class="relative w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
            <div class="flex items-center justify-between p-4 border-b rounded-t bg-blue-600">
                <h3 class="text-lg font-semibold text-white">Proses Pengajuan</h3>
                <button type="button" class="text-gray-200 hover:text-white" data-modal-hide="updateModal"><i class="fas fa-times"></i></button>
            </div>
            <form action="pencairan.php" method="POST" class="p-4 space-y-4">
                <input type="hidden" name="action" value="update_status_pencairan">
                <input type="hidden" name="id_pencairan" id="up_id">
                
                <div>
                    <label class="block text-sm text-gray-600">Nasabah</label>
                    <input type="text" id="up_nama" class="w-full bg-gray-100 border rounded p-2" readonly>
                </div>
                <div>
                    <label class="block text-sm text-gray-600">Jumlah</label>
                    <input type="text" id="up_jumlah" class="w-full bg-gray-100 border rounded p-2 font-bold" readonly>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Keputusan</label>
                    <select name="new_status" id="up_status" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                        <option value="diterima">Setujui & Cairkan</option>
                        <option value="ditolak">Tolak</option>
                    </select>
                </div>
                <div id="div_tgl_transfer">
                    <label class="block mb-2 text-sm font-medium text-gray-900">Tanggal Cair/Transfer</label>
                    <input type="date" name="tanggal_transfer" value="<?php echo date('Y-m-d'); ?>" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Keterangan (Opsional)</label>
                    <textarea name="keterangan" rows="2" class="bg-gray-50 border border-gray-300 text-sm rounded-lg block w-full p-2.5"></textarea>
                </div>
                <div class="flex justify-end border-t pt-4">
                    <button type="submit" class="text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-sm px-5 py-2.5">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Handle Modal Update Populate Data
    const buttons = document.querySelectorAll('[data-modal-toggle="updateModal"]');
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('up_id').value = this.getAttribute('data-id');
            document.getElementById('up_nama').value = this.getAttribute('data-nasabah');
            document.getElementById('up_jumlah').value = 'Rp ' + parseFloat(this.getAttribute('data-jumlah')).toLocaleString('id-ID');
        });
    });

    // Toggle Tanggal Transfer visibility based on status
    const statusSelect = document.getElementById('up_status');
    const divTgl = document.getElementById('div_tgl_transfer');
    statusSelect.addEventListener('change', function() {
        if(this.value === 'ditolak') {
            divTgl.style.display = 'none';
        } else {
            divTgl.style.display = 'block';
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
<?php ob_end_flush(); ?>