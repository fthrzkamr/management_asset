<?php
include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
require_once __DIR__ . '/../dist/config/ApprovalHelper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('budget_besar', 'update');

$id_budget = $_GET['id'] ?? 0;
$error_message = $_GET['error'] ?? '';

// Load Budget Header
$query_header = "SELECT * FROM ppx_monthly_budget WHERE id_budget = ?";
$stmt = mysqli_prepare($conn, $query_header);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$budget = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$budget) {
    die("Anggaran tidak ditemukan.");
}

// Cek ownership / status (tidak boleh diedit jika sudah final)
if (!in_array($budget['status'], ['Draft', 'Revisi', 'Pending', 'Asmen Finance', 'Kacab', 'Manager', 'Manager Finance', 'Manager Operasional', 'Active'])) {
    if (!$auth->isSuperAdmin()) {
        die("Anggaran dengan status ini tidak dapat diedit.");
    }
}

// Kategori
$categories = [];
$cr = mysqli_query($conn, "SELECT * FROM ppx_budget_category WHERE is_active = 1 ORDER BY category_name");
if ($cr) while ($r = mysqli_fetch_assoc($cr)) $categories[] = $r;

$categoryOptionsHtml = '';
foreach ($categories as $cat) {
    $categoryOptionsHtml .= '<option value="' . htmlspecialchars($cat['id_category']) . '">' . htmlspecialchars($cat['category_name']) . '</option>';
}

// Info user
$user_id = $_SESSION['id_user'];
$stmt2 = mysqli_prepare($conn, "SELECT u.nama_user, u.jabatan, mc.nama_cabang, d.nama_divisi
    FROM user_dfm u
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    LEFT JOIN divisi d ON u.divisi = d.id_divisi
    WHERE u.id_user = ?");
mysqli_stmt_bind_param($stmt2, 'i', $user_id);
mysqli_stmt_execute($stmt2);
$user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));

// Load Items
$query_items = "SELECT pid.*, pc.category_name 
                FROM ppx_monthly_budget_item_detail pid
                JOIN ppx_budget_category pc ON pid.id_category = pc.id_category
                WHERE pid.id_budget = ?";
$stmt_items = mysqli_prepare($conn, $query_items);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$result_items = mysqli_stmt_get_result($stmt_items);

// Convert items grouping
$grouped_items = [];
while ($row = mysqli_fetch_assoc($result_items)) {
    $catId = $row['id_category'];
    if (!isset($grouped_items[$catId])) {
        $grouped_items[$catId] = [];
    }
    $grouped_items[$catId][] = $row;
}

// Convert grouped_items to JSON so JS can parse and auto-inject it
$grouped_items_json = json_encode($grouped_items);
?>
<!DOCTYPE html>
<html lang="id">
<?php include_once('../layouts/navbar.php'); ?>
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title"><i class="fas fa-edit text-warning mr-2"></i>Edit Anggaran Bulanan Besar</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Besar</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h4 class="card-title mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Form Edit Anggaran Besar</h4>
                    </div>
                    <div class="card-body">
                        <form action="edit_update.php" method="POST" id="budgetForm">
                            <input type="hidden" name="id_budget" value="<?= htmlspecialchars($id_budget) ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Bulan <span class="text-danger">*</span></label>
                                        <select name="period_month" id="period_month" class="form-control" required>
                                            <option value="">-- Pilih Bulan --</option>
                                            <?php
                                            $months = ['','Januari','Februari','Maret','April','Mei','Juni',
                                                       'Juli','Agustus','September','Oktober','November','Desember'];
                                            for ($i=1; $i<=12; $i++) {
                                                $selected = ($budget['period_month'] == $i) ? 'selected' : '';
                                                echo '<option value="'.$i.'" '.$selected.'>'.$months[$i].'</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tahun <span class="text-danger">*</span></label>
                                        <select name="period_year" id="period_year" class="form-control" required>
                                            <?php
                                            $cy = date('Y');
                                            for ($y=$cy-2; $y<=$cy+2; $y++) {
                                                $selected = ($budget['period_year'] == $y) ? 'selected' : '';
                                                echo "<option value=\"$y\" $selected>$y</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Nama Anggaran</label>
                                        <input type="text" name="budget_name" class="form-control"
                                               value="<?= htmlspecialchars($budget['budget_name']) ?>" placeholder="Otomatis jika kosong">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Cabang</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($user_info['nama_cabang'] ?? '-') ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Divisi</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($user_info['nama_divisi'] ?? '-') ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Jabatan</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($user_info['jabatan'] ?? '-') ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Catatan</label>
                                <textarea required name="notes" class="form-control" rows="3"><?= htmlspecialchars($budget['notes']) ?></textarea>
                            </div>

                            <hr>
                            <h5 class="mb-3">Rincian Anggaran</h5>
                            <p class="text-muted">Edit kategori dan item-item anggaran.</p>

                            <div id="categoriesContainer"></div>

                            <button type="button" id="addCategory" class="btn btn-primary mt-3">
                                <i class="fa fa-plus"></i> Tambah Kategori
                            </button>

                            <hr class="mt-4">
                            <div class="row">
                                <div class="col-md-6 offset-md-6">
                                    <div class="card bg-warning text-dark">
                                        <div class="card-body text-right">
                                            <h5 class="mb-0"><strong>Total Alokasi:</strong></h5>
                                            <h4 class="mb-0 text-dark" id="totalAllocation">Rp 0</h4>
                                            <input type="hidden" name="total_allocation" id="total_allocation" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right mt-3">
                                <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn btn-warning shadow-sm">
                                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include_once('../layouts/footer.php'); ?>
    </div>
</div>

<script>
$(document).ready(function() {
    const categoryOptions = `<?= str_replace('`', '\`', $categoryOptionsHtml) ?>`;
    const existingData = <?= $grouped_items_json ?>;

    let categoryCounter = 0;

    function formatRupiah(n) {
        if (!n) return '';
        let s = n.toString().replace(/[^\d]/g,'');
        return s.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    }
    function parseRupiah(s) {
        if (!s) return 0;
        return parseInt(s.toString().replace(/\./g,'')) || 0;
    }

    function addCategory(catIdValue = null) {
        categoryCounter++;
        const catId = 'cat_' + Date.now() + '_' + categoryCounter;
        
        let selectOptions = `<option value="">-- Pilih Kategori --</option>`;
        // Injeksi option yang selected (jika dari database)
        let modifiedOptions = categoryOptions.replace(`value="${catIdValue}"`, `value="${catIdValue}" selected`);
        if (catIdValue) selectOptions += modifiedOptions;
        else selectOptions += categoryOptions;

        const html = `
        <div class="border rounded p-3 mb-3 category-block" data-category-id="${catId}" style="background:#f8f9fa">
            <div class="row align-items-center mb-2">
                <div class="col-md-4">
                    <label><strong>Kategori:</strong></label>
                    <select class="form-control form-control-sm category-select" data-category-id="${catId}" required>
                        ${selectOptions}
                    </select>
                </div>
                <div class="col-md-4">
                    <label><strong>Subtotal:</strong></label><br>
                    <span class="category-subtotal text-success font-weight-bold h5" data-category-id="${catId}">Rp 0</span>
                </div>
                <div class="col-md-4 text-right" style="padding-top:24px">
                    <button type="button" class="btn btn-sm btn-success add-item-btn" data-category-id="${catId}">
                        <i class="fa fa-plus"></i> Item
                    </button>
                    <button type="button" class="btn btn-sm btn-danger remove-category-btn">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:30%">Rincian Item</th>
                            <th style="width:8%">Qty</th>
                            <th style="width:9%">Satuan</th>
                            <th style="width:9%">Jml Hari</th>
                            <th style="width:9%">Jml Minggu</th>
                            <th style="width:17%">Harga Satuan</th>
                            <th style="width:13%">Total</th>
                            <th style="width:5%"></th>
                        </tr>
                    </thead>
                    <tbody class="items-body" data-category-id="${catId}"></tbody>
                </table>
            </div>
        </div>`;
        $('#categoriesContainer').append(html);
        bindEvents();
        return catId; // mengembalikan ID render DOM
    }

    function addItem(catId, itemData = {}) {
        const idx = Date.now() + '_' + Math.floor(Math.random()*9999);
        const rincian = itemData.rincian || '';
        const qty = itemData.qty || 1;
        const satuan = itemData.satuan || '';
        const jmlHari = itemData.jumlah_hari || '';
        const jmlMinggu = itemData.jumlah_minggu || '';
        const harga = itemData.harga_satuan ? formatRupiah(itemData.harga_satuan) : '';
        const actualCatId = $(`.category-select[data-category-id="${catId}"]`).val();

        // Satuan selections
        const satuans = ['pcs','unit','kg','meter','box','set','ltr'];
        let satuanOptions = `<option value="">-</option>`;
        satuans.forEach(s => {
            satuanOptions += `<option value="${s}" ${s === satuan ? 'selected' : ''}>${s}</option>`;
        });

        const row = `
        <tr data-category-id="${catId}" data-item-index="${idx}">
            <td>
                <input type="hidden" name="items[${idx}][category]" class="item-category-ref" data-category-id="${catId}" value="${actualCatId}">
                <input type="text" name="items[${idx}][rincian]" class="form-control form-control-sm item-rincian" placeholder="Deskripsi item" value="${rincian}" required>
            </td>
            <td><input type="number" name="items[${idx}][qty]" class="form-control form-control-sm item-qty" value="${qty}" min="1"></td>
            <td>
                <select name="items[${idx}][satuan]" class="form-control form-control-sm">
                    ${satuanOptions}
                </select>
            </td>
            <td><input type="number" name="items[${idx}][jumlah_hari]" class="form-control form-control-sm" placeholder="0" min="0" value="${jmlHari}"></td>
            <td><input type="number" name="items[${idx}][jumlah_minggu]" class="form-control form-control-sm" placeholder="0" min="0" value="${jmlMinggu}"></td>
            <td><input type="text" name="items[${idx}][harga]" class="form-control form-control-sm item-price" placeholder="0" value="${harga}"></td>
            <td class="item-total">Rp 0</td>
            <td><button type="button" class="btn btn-sm btn-danger remove-item-btn">&times;</button></td>
        </tr>`;
        
        $(`.items-body[data-category-id="${catId}"]`).append(row);
        bindEvents();
        
        // Calculate newly added item
        calcItemTotal($(`.items-body[data-category-id="${catId}"] tr`).last());
    }

    function updateCategoryItems(catId) {
        const val = $(`.category-select[data-category-id="${catId}"]`).val();
        if (val) $(`.items-body[data-category-id="${catId}"] .item-category-ref`).val(val);
    }

    function calcItemTotal($row) {
        const qty = parseInt($row.find('.item-qty').val()) || 0;
        const price = parseRupiah($row.find('.item-price').val()) || 0;
        const total = qty * price;
        $row.find('.item-total').text('Rp ' + total.toLocaleString('id-ID'));
        
        // Timeout ensures DOM update before summing
        setTimeout(() => updateSubtotal($row.data('category-id')), 10);
    }

    function updateSubtotal(catId) {
        let sub = 0;
        $(`.items-body[data-category-id="${catId}"] tr`).each(function() {
            sub += (parseInt($(this).find('.item-qty').val())||0) * (parseRupiah($(this).find('.item-price').val())||0);
        });
        $(`.category-subtotal[data-category-id="${catId}"]`).text('Rp ' + sub.toLocaleString('id-ID'));
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        $('.items-body tr').each(function() {
            total += (parseInt($(this).find('.item-qty').val())||0) * (parseRupiah($(this).find('.item-price').val())||0);
        });
        $('#totalAllocation').text('Rp ' + total.toLocaleString('id-ID'));
        $('#total_allocation').val(total);
    }

    function bindEvents() {
        $('.add-item-btn').off('click').on('click', function() { addItem($(this).data('category-id')); });
        $('.remove-category-btn').off('click').on('click', function() {
            if (confirm('Hapus kategori ini?')) { $(this).closest('.category-block').remove(); updateTotal(); }
        });
        $('.category-select').off('change').on('change', function() {
            updateCategoryItems($(this).data('category-id'));
        });
        $('.item-price').off('keyup input').on('keyup input', function() {
            const n = parseRupiah($(this).val());
            $(this).val(formatRupiah(n));
            calcItemTotal($(this).closest('tr'));
        });
        $('.item-qty').off('change input').on('change input', function() {
            calcItemTotal($(this).closest('tr'));
        });
        $('.remove-item-btn').off('click').on('click', function() {
            const $body = $(this).closest('tbody');
            const catId = $body.data('category-id');
            $(this).closest('tr').remove();
            if ($body.find('tr').length === 0) addItem(catId);
            updateTotal();
        });
    }

    $('#addCategory').on('click', () => {
        const DOMCatId = addCategory();
        addItem(DOMCatId);
    });
    
    // Initial Load Data from Database
    if (Object.keys(existingData).length > 0) {
        // Iterate through grouped items and spawn them
        for (const [dbCatId, items] of Object.entries(existingData)) {
            const domCatId = addCategory(dbCatId);
            items.forEach(item => {
                addItem(domCatId, item);
            });
        }
    } else {
        // Fallback jika budget kosong tapi bisa di-edit
        const domId = addCategory(); 
        addItem(domId);
    }

    $('#budgetForm').on('submit', function(e) {
        $('.category-block').each(function() {
            const val = $(this).find('.category-select').val();
            if (val) $(this).find('.item-category-ref').val(val);
        });

        let total = 0;
        $('.items-body tr').each(function() {
            total += (parseInt($(this).find('.item-qty').val())||0) * (parseRupiah($(this).find('.item-price').val())||0);
        });
        if (total <= 0) { e.preventDefault(); alert('Total alokasi harus lebih dari 0!'); }
    });
});
</script>
</body>
</html>
