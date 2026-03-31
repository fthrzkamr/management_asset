<?php

include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

// Initialize auth helper
$auth = new AuthHelper($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has permission to update budget
if (!$auth->canUpdateBudget()) {
    header("Location: ../index.php");
    exit();
}

// Get budget ID
$id_budget = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_budget == 0) {
    header("Location: monthly_budget_list.php");
    exit();
}

// Get current user's division name for filtering
$user_id = $_SESSION['id_user'];
$user_query = "SELECT d.nama_divisi 
               FROM user_dfm u 
               LEFT JOIN divisi d ON u.divisi = d.id_divisi 
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_division = $user_data['nama_divisi'];

// Get budget detail with division check
$budget_query = "SELECT mb.*, creator_div_name.nama_divisi as creator_division 
                 FROM ppx_monthly_budget mb
                 LEFT JOIN user_dfm creator_user ON mb.created_by = creator_user.id_user
                 LEFT JOIN divisi creator_div_name ON creator_user.divisi = creator_div_name.id_divisi
                 WHERE mb.id_budget = ?";
$stmt = mysqli_prepare($conn, $budget_query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$budget = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$budget) {
    header("Location: monthly_budget_list.php");
    exit();
}

// Check if user can access this budget based on division
$can_access = false;
$creator_division = $budget['creator_division'];

if (stripos($user_division, 'HR') !== false || stripos($user_division, 'IT') !== false || stripos($user_division, 'GA') !== false) {
    // HR, IT, GA can see each other's budgets
    if (stripos($creator_division, 'HR') !== false || stripos($creator_division, 'IT') !== false || stripos($creator_division, 'GA') !== false) {
        $can_access = true;
    }
} else if (stripos($user_division, 'Finance AP') !== false) {
    // Finance AP can only see Finance AP budgets
    if (stripos($creator_division, 'Finance AP') !== false) {
        $can_access = true;
    }
} else if (stripos($user_division, 'Finance') !== false) {
    // Finance can see Finance budgets
    if (stripos($creator_division, 'Finance') !== false) {
        $can_access = true;
    }
} else {
    // Other divisions can only see their own
    if ($creator_division == $user_division) {
        $can_access = true;
    }
}

if (!$can_access) {
    header("Location: monthly_budget_list.php");
    exit();
}

// Check if budget is already approved - redirect with message
// Super Admins can bypass this check and edit if necessary
// 'Revisi' status bypasses this check because approved_by is filled by the rejecting manager
$is_approved = ($budget['approved_by'] !== null || in_array($budget['status'], ['Active', 'Closed']));
$is_revisi = $budget['status'] === 'Revisi';

if ($is_approved && !$is_revisi && !$auth->isSuperAdmin()) {
    header("Location: monthly_budget_detail.php?id=$id_budget&error=" . urlencode("Budget yang sudah disetujui/aktif tidak dapat diubah"));
    exit();
}

// Get budget items with details
$items_query = "SELECT 
    mbid.*,
    bc.category_code,
    bc.category_name
FROM ppx_monthly_budget_item_detail mbid
JOIN ppx_budget_category bc ON mbid.id_category = bc.id_category
WHERE mbid.id_budget = ?
ORDER BY bc.category_name, mbid.id";

$stmt = mysqli_prepare($conn, $items_query);
if (!$stmt) {
    die("Error preparing statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

// Group items by category
$items_by_category = [];
if ($items_result) {
    while ($row = mysqli_fetch_assoc($items_result)) {
        $cat_id = $row['id_category'];
        if (!isset($items_by_category[$cat_id])) {
            $items_by_category[$cat_id] = [
                'category_name' => $row['category_name'],
                'category_code' => $row['category_code'],
                'items' => []
            ];
        }
        $items_by_category[$cat_id]['items'][] = $row;
    }
}

// Get all categories
$categories_query = "SELECT * FROM ppx_budget_category WHERE is_active = 1 ORDER BY category_name";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
if ($categories_result) {
    while ($r = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $r;
    }
}

$categoryOptionsHtml = '';
foreach ($categories as $cat) {
    $categoryOptionsHtml .= '<option value="' . htmlspecialchars($cat['id_category']) . '">' . htmlspecialchars($cat['category_name']) .'</option>';
}

$period_display = date('F Y', strtotime($budget['period_year'] . '-' . $budget['period_month'] . '-01'));
?>

<!DOCTYPE html>
<html lang="id">
<?php include_once('../layouts/navbar.php'); ?>

<div class="page-wrapper">
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Edit Budget: <?= $period_display ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Bulanan</a></li>
                        <li class="breadcrumb-item"><a href="monthly_budget_list.php">Kelola Budget</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Form Edit Budget -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="fas fa-edit"></i> Edit Budget</h4>
                    </div>
                    <div class="card-body">
                        <form action="monthly_budget_update.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_budget" value="<?= $id_budget ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Nama Budget</label>
                                        <input type="text" name="budget_name" class="form-control" 
                                               value="<?= htmlspecialchars($budget['budget_name']) ?>" readonly>
                                        <small class="text-muted">Nama budget tidak dapat diubah</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($budget['status']) ?>" readonly>
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($budget['status']) ?>">
                                        <small class="text-muted">Status budget tidak dapat diubah secara manual</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tipe Anggaran <span class="text-danger">*</span></label>
                                        <select name="tipe_anggaran" id="tipe_anggaran" class="form-control" required>
                                            <option value="">-- Pilih Tipe --</option>
                                            <?php
                                            $tipe_options = [
                                                'Kas Besar BCA', 'Kas Besar Jakarta', 'Kas Kecil Jakarta', 
                                                'Bekasi', 'Cibinong', 'Medan', 'Bali', 'Surabaya', 
                                                'Malang', 'Banjarmasin', 'HRGA', 'Bussines', 'Warehouse'
                                            ];
                                            foreach ($tipe_options as $opt) {
                                                $selected = ($budget['tipe_anggaran'] == $opt) ? 'selected' : '';
                                                echo '<option value="' . $opt . '" ' . $selected . '>' . $opt . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Catatan</label>
                                        <textarea name="notes" class="form-control" rows="1"><?= htmlspecialchars($budget['notes']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Lampiran Excel (Opsional)</label>
                                <?php if (!empty($budget['file_excel'])): ?>
                                    <div class="mb-2">
                                        <a href="../uploads/ppx_excel/<?= htmlspecialchars($budget['file_excel']) ?>" class="btn btn-sm btn-outline-success" target="_blank" download>
                                            <i class="fas fa-file-excel"></i> File saat ini: <?= htmlspecialchars($budget['file_excel']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="file_excel" class="form-control-file" accept=".xls,.xlsx">
                                <small class="text-muted">Upload file Excel baru jika ingin mengganti lampiran sebelumnya. Format: .xls, .xlsx</small>
                            </div>

                            <hr>

                            <h5 class="mb-3">Alokasi per Kategori</h5>
                            <p class="text-muted">Edit item-item dalam setiap kategori. Anda dapat menambah atau menghapus item.</p>
                            
                            <div id="categoriesContainer">
                                <?php if (empty($items_by_category)): ?>
                                    <!-- No items, will add manually -->
                                <?php else: ?>
                                    <?php foreach ($items_by_category as $cat_id => $category): ?>
                                        <div class="border rounded p-3 mb-3 category-block" data-category-id="cat_<?= $cat_id ?>" style="background-color: #f8f9fa;">
                                            <div class="row align-items-center mb-2">
                                                <div class="col-md-3">
                                                    <label class="mb-1" style="font-size: 13px;"><strong>Kategori:</strong></label>
                                                    <select class="form-control form-control-sm category-select" data-category-id="cat_<?= $cat_id ?>" required>
                                                        <option value="">-- Pilih Kategori --</option>
                                                        <?php foreach ($categories as $c): ?>
                                                            <option value="<?= $c['id_category'] ?>" <?= $c['id_category'] == $cat_id ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($c['category_name']) ?> - <?= htmlspecialchars($c['category_code']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="mb-1" style="font-size: 13px;"><strong>Subtotal Kategori:</strong></label><br>
                                                    <span class="category-subtotal text-primary font-weight-bold h5" data-category-id="cat_<?= $cat_id ?>">Rp 0</span>
                                                </div>
                                                <div class="col-md-4 text-right" style="padding-top: 24px;">
                                                    <button type="button" class="btn btn-sm btn-success add-item-btn" data-category-id="cat_<?= $cat_id ?>" title="Tambah Item">
                                                        <i class="fa fa-plus"></i> Item
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger remove-category-btn" title="Hapus Kategori">
                                                        <i class="fa fa-trash"></i> Kategori
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 25%;">Rincian Item</th>
                                                            <th style="width: 8%;">Qty</th>
                                                            <th style="width: 10%;">Satuan</th>
                                                            <th style="width: 10%;">Jml Hari</th>
                                                            <th style="width: 10%;">Jml Minggu</th>
                                                            <th style="width: 16%;">Harga Satuan</th>
                                                            <th style="width: 16%;">Total</th>
                                                            <th style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="items-body" data-category-id="cat_<?= $cat_id ?>">
                                                        <?php foreach ($category['items'] as $item): ?>
                                                            <?php 
                                                                $itemIndex = 'existing_' . $item['id'];
                                                                $itemTotal = $item['qty'] * $item['harga_satuan'];
                                                                ?>
                                                                <tr data-item-id="item_<?= $item['id'] ?>" data-category-id="cat_<?= $cat_id ?>" data-item-index="<?= $itemIndex ?>">
                                                                    <td>
                                                                        <input type="hidden" name="items[<?= $itemIndex ?>][id]" value="<?= $item['id'] ?>">
                                                                        <input type="hidden" name="items[<?= $itemIndex ?>][category]" class="item-category-ref" data-category-id="cat_<?= $cat_id ?>" value="<?= $cat_id ?>">
                                                                        <input type="text" name="items[<?= $itemIndex ?>][rincian]" class="form-control form-control-sm item-rincian" placeholder="Deskripsi item" value="<?= htmlspecialchars($item['rincian']) ?>" required>
                                                                    </td>
                                                                    <td><input type="number" name="items[<?= $itemIndex ?>][qty]" class="form-control form-control-sm item-qty" value="<?= $item['qty'] ?>" min="1"></td>
                                                                    <td>
                                                                    <select name="items[<?= $itemIndex ?>][satuan]" class="form-control form-control-sm item-satuan">
                                                                        <option value="">-</option>
                                                                        <option value="pcs" <?= $item['satuan'] == 'pcs' ? 'selected' : '' ?>>pcs</option>
                                                                        <option value="unit" <?= $item['satuan'] == 'unit' ? 'selected' : '' ?>>unit</option>
                                                                        <option value="kg" <?= $item['satuan'] == 'kg' ? 'selected' : '' ?>>kg</option>
                                                                        <option value="meter" <?= $item['satuan'] == 'meter' ? 'selected' : '' ?>>meter</option>
                                                                        <option value="box" <?= $item['satuan'] == 'box' ? 'selected' : '' ?>>box</option>
                                                                        <option value="set" <?= $item['satuan'] == 'set' ? 'selected' : '' ?>>set</option>
                                                                        <option value="ltr" <?= $item['satuan'] == 'ltr' ? 'selected' : '' ?>>ltr</option>
                                                                        <option value="dll" <?= $item['satuan'] == 'dll' ? 'selected' : '' ?>>dll</option>
                                                                    </select>
                                                                </td>
                                                                    <td><input type="number" name="items[<?= $itemIndex ?>][jumlah_hari]" class="form-control form-control-sm" placeholder="0" value="<?= $item['jumlah_hari'] ?>" min="0"></td>
                                                                    <td><input type="number" name="items[<?= $itemIndex ?>][jumlah_minggu]" class="form-control form-control-sm" placeholder="0" value="<?= $item['jumlah_minggu'] ?>" min="0"></td>
                                                                    <td><input type="text" name="items[<?= $itemIndex ?>][harga]" class="form-control form-control-sm item-price" value="<?= number_format($item['harga_satuan'], 0, ',', '.') ?>" placeholder="0"></td>
                                                                    <td><input type="text" name="items[<?= $itemIndex ?>][total]" class="form-control form-control-sm item-total" value="<?= number_format($itemTotal, 0, ',', '.') ?>" placeholder="0"></td>
                                                                <td><button type="button" class="btn btn-sm btn-danger remove-item-btn">&times;</button></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" id="addCategory" class="btn btn-primary mt-3">
                                <i class="fa fa-plus"></i> Tambah Kategori Baru
                            </button>

                            <hr class="mt-4">
                            <div class="row">
                                <div class="col-md-6 offset-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="text-right mb-0"><strong>Total Alokasi:</strong></h5>
                                            <h4 class="text-primary text-right mb-0" id="totalAllocation">Rp 0</h4>
                                            <input type="hidden" name="total_allocation" id="total_allocation" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                                <a href="monthly_budget_detail.php?id=<?= $id_budget ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include_once('../layouts/footer.php'); ?>

<script>
$(document).ready(function() {
    // Format Rupiah function
    function formatRupiah(angka) {
        if (!angka) return '';
        let number_string = angka.toString().replace(/[^,\d]/g, '');
        let split = number_string.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return rupiah;
    }

    // Parse Rupiah to number
    function parseRupiah(rupiah) {
        if (!rupiah) return 0;
        return parseInt(rupiah.toString().replace(/\./g, '').replace(/,/g, '')) || 0;
    }

    // Category and Item management
    const categoryOptions = `<?= str_replace("`", "\\`", $categoryOptionsHtml) ?>`;
    let categoryCounter = 1000; // Start high to avoid conflicts with existing IDs
    let categoryMap = {};

    function addCategory() {
        categoryCounter++;
        const catId = 'cat_new_' + Date.now() + '_' + categoryCounter;
        
        const categoryBlock = `
            <div class="border rounded p-3 mb-3 category-block" data-category-id="${catId}" style="background-color: #f8f9fa;">
                <div class="row align-items-center mb-2">
                    <div class="col-md-3">
                        <label class="mb-1" style="font-size: 13px;"><strong>Kategori:</strong></label>
                        <select class="form-control form-control-sm category-select" data-category-id="${catId}" required>
                            <option value="">-- Pilih Kategori --</option>
                            ${categoryOptions}
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="mb-1" style="font-size: 13px;"><strong>Subtotal Kategori:</strong></label><br>
                        <span class="category-subtotal text-primary font-weight-bold h5" data-category-id="${catId}">Rp 0</span>
                    </div>
                    <div class="col-md-4 text-right" style="padding-top: 24px;">
                        <button type="button" class="btn btn-sm btn-success add-item-btn" data-category-id="${catId}" title="Tambah Item">
                            <i class="fa fa-plus"></i> Item
                        </button>
                        <button type="button" class="btn btn-sm btn-danger remove-category-btn" title="Hapus Kategori">
                            <i class="fa fa-trash"></i> Kategori
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 28%;">Rincian Item</th>
                                <th style="width: 8%;">Qty</th>
                                <th style="width: 9%;">Satuan</th>
                                <th style="width: 9%;">Jml Hari</th>
                                <th style="width: 9%;">Jml Minggu</th>
                                <th style="width: 16%;">Harga Satuan</th>
                                <th style="width: 16%;">Total</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody class="items-body" data-category-id="${catId}">
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        $('#categoriesContainer').append(categoryBlock);
        bindCategoryEvents();
        addItemToCategory(catId);
        updateCategoryForItems(catId);
    }

    function addItemToCategory(categoryId) {
        const itemId = 'item_' + Date.now() + '_' + Math.random();
        const itemIndex = Date.now() + '_' + Math.floor(Math.random() * 10000);
        
        const itemRow = `
            <tr data-item-id="${itemId}" data-category-id="${categoryId}" data-item-index="${itemIndex}">
                <td>
                    <input type="hidden" name="items[${itemIndex}][category]" class="item-category-ref" data-category-id="${categoryId}" value="">
                    <input type="text" name="items[${itemIndex}][rincian]" class="form-control form-control-sm item-rincian" placeholder="Deskripsi item" required>
                </td>
                <td><input type="number" name="items[${itemIndex}][qty]" class="form-control form-control-sm item-qty" value="1" min="1"></td>
                <td>
                    <select name="items[${itemIndex}][satuan]" class="form-control form-control-sm item-satuan">
                        <option value="">-</option>
                        <option value="pcs">pcs</option>
                        <option value="unit">unit</option>
                        <option value="kg">kg</option>
                        <option value="meter">meter</option>
                        <option value="box">box</option>
                        <option value="set">set</option>
                        <option value="ltr">ltr</option>
                        <option value="dll">dll</option>
                    </select>
                </td>
                <td><input type="number" name="items[${itemIndex}][jumlah_hari]" class="form-control form-control-sm" placeholder="0" min="0"></td>
                <td><input type="number" name="items[${itemIndex}][jumlah_minggu]" class="form-control form-control-sm" placeholder="0" min="0"></td>
                <td><input type="text" name="items[${itemIndex}][harga]" class="form-control form-control-sm item-price" value="" placeholder="0"></td>
                <td><input type="text" name="items[${itemIndex}][total]" class="form-control form-control-sm item-total" value="" placeholder="0"></td>
                <td><button type="button" class="btn btn-sm btn-danger remove-item-btn">&times;</button></td>
            </tr>
        `;
        
        $(`.items-body[data-category-id="${categoryId}"]`).append(itemRow);
        bindItemEvents();
        updateCategoryForItems(categoryId);
    }

    function updateCategoryForItems(categoryId) {
        const selectedValue = $(`.category-select[data-category-id="${categoryId}"]`).val();
        if (selectedValue) {
            $(`.items-body[data-category-id="${categoryId}"]`).find('.item-category-ref').val(selectedValue);
        }
    }

    function bindCategoryEvents() {
        $('.add-item-btn').off('click').on('click', function() {
            const catId = $(this).data('category-id');
            addItemToCategory(catId);
        });
        
        $('.remove-category-btn').off('click').on('click', function() {
            if (confirm('Hapus kategori ini beserta semua itemnya?')) {
                $(this).closest('.category-block').remove();
                updateTotals();
            }
        });
        
        $('.category-select').off('change').on('change', function() {
            $(this).removeClass('is-invalid');
            const catId = $(this).data('category-id');
            const selectedValue = $(this).val();
            categoryMap[catId] = selectedValue;
            updateCategoryForItems(catId);
        });
    }

    function bindItemEvents() {
        $('.item-price').off('keyup input').on('keyup input', function() {
            $(this).removeClass('is-invalid');
            const num = parseRupiah($(this).val());
            $(this).val(formatRupiah(num));
            updateItemTotal($(this).closest('tr'));
        });
        
        $('.item-qty').off('change input').on('change input', function() {
            updateItemTotal($(this).closest('tr'));
        });
        
        $('.remove-item-btn').off('click').on('click', function() {
            const $tbody = $(this).closest('tbody');
            $(this).closest('tr').remove();
            
            if ($tbody.find('tr').length === 0) {
                const catId = $tbody.data('category-id');
                addItemToCategory(catId);
            }
            
            updateTotals();
        });
        
        // Custom manual total edit
        $('.item-total').off('keyup input').on('keyup input', function() {
            $(this).removeClass('is-invalid');
            const num = parseRupiah($(this).val());
            $(this).val(formatRupiah(num));
            
            // Limit to category subtotal
            const catId = $(this).closest('tr').data('category-id');
            updateCategorySubtotal(catId);
        });
        
        $('.item-rincian, .item-price, .item-qty, .item-total').off('focus').on('focus', function() {
            $(this).removeClass('is-invalid');
        });
    }

    function updateItemTotal($row) {
        const qty = parseInt($row.find('.item-qty').val()) || 0;
        const price = parseRupiah($row.find('.item-price').val()) || 0;
        const total = qty * price;
        $row.find('.item-total').val(formatRupiah(total));
        
        const catId = $row.data('category-id');
        updateCategorySubtotal(catId);
    }

    function updateCategorySubtotal(categoryId) {
        let subtotal = 0;
        $(`.items-body[data-category-id="${categoryId}"] tr`).each(function() {
            const manualTotal = parseRupiah($(this).find('.item-total').val()) || 0;
            subtotal += manualTotal;
        });
        
        $(`.category-subtotal[data-category-id="${categoryId}"]`).text('Rp ' + subtotal.toLocaleString('id-ID'));
        updateTotals();
    }

    function updateTotals() {
        let total = 0;
        $('.items-body tr').each(function() {
            const manualTotal = parseRupiah($(this).find('.item-total').val()) || 0;
            total += manualTotal;
        });
        $('#totalAllocation').text('Rp ' + total.toLocaleString('id-ID'));
        $('#total_allocation').val(total);
    }

    // Add category button
    $('#addCategory').on('click', function() {
        addCategory();
    });

    // Bind events for existing items
    bindCategoryEvents();
    bindItemEvents();
    
    // If no category blocks exist (empty budget), create one so user can add items
    if ($('.category-block').length === 0) {
        addCategory();
    }

    // Calculate initial totals
    $('.items-body tr').each(function() {
        const catId = $(this).data('category-id');
        updateCategorySubtotal(catId);
    });
    updateTotals();

    // Form validation before submit
    $('form').submit(function(e) {
        let hasErrors = false;
        let errorMessages = [];
        
        // Force category sync
        $('.category-block').each(function() {
            const $block = $(this);
            const categoryVal = $block.find('.category-select').val();
            const catId = $block.data('category-id');
            
            if (categoryVal && categoryVal !== '0') {
                $block.find('.items-body tr').each(function() {
                    $(this).find('.item-category-ref').val(categoryVal);
                });
            }
        });
        
        // Remove empty categories
        $('.category-block').each(function() {
            const $block = $(this);
            const categoryVal = $block.find('.category-select').val();
            
            if (!categoryVal || categoryVal === '0') {
                let hasValidItems = false;
                $block.find('.items-body tr').each(function() {
                    const rincian = $(this).find('.item-rincian').val().trim();
                    const price = parseRupiah($(this).find('.item-price').val());
                    if (rincian || price > 0) {
                        hasValidItems = true;
                    }
                });
                if (!hasValidItems) {
                    $block.remove();
                }
            }
        });
        
        // Validate categories
        $('.category-block').each(function(catIndex) {
            const $block = $(this);
            const categoryVal = $block.find('.category-select').val();
            
            if (!categoryVal || categoryVal === '0') {
                hasErrors = true;
                errorMessages.push(`Kategori ke-${catIndex + 1}: Belum dipilih`);
                $block.find('.category-select').addClass('is-invalid');
                return;
            }
            
            // Remove empty items
            $block.find('.items-body tr').each(function() {
                const rincian = $(this).find('.item-rincian').val().trim();
                const price = parseRupiah($(this).find('.item-price').val());
                
                if (!rincian && price <= 0) {
                    $(this).remove();
                }
            });
            
            const remainingItems = $block.find('.items-body tr').length;
            
            if (remainingItems === 0) {
                hasErrors = true;
                errorMessages.push(`Kategori ke-${catIndex + 1}: Tidak ada item yang diisi`);
                return;
            }
            
            // Validate items
            $block.find('.items-body tr').each(function(itemIndex) {
                const rincian = $(this).find('.item-rincian').val().trim();
                const priceField = $(this).find('.item-price');
                const priceValue = priceField.val();
                const price = parseRupiah(priceValue);
                
                if (!rincian) {
                    hasErrors = true;
                    errorMessages.push(`Kategori ${catIndex + 1}, Item ${itemIndex + 1}: Rincian belum diisi`);
                    $(this).find('.item-rincian').addClass('is-invalid');
                }
                
                if (!priceValue || price <= 0) {
                    hasErrors = true;
                    errorMessages.push(`Kategori ${catIndex + 1}, Item ${itemIndex + 1}: Harga harus > 0`);
                    priceField.addClass('is-invalid');
                }
            });
        });
        
        if (hasErrors) {
            e.preventDefault();
            alert('Form tidak valid:\n\n' + errorMessages.join('\n'));
            return false;
        }
    });
});
</script>
</html>
