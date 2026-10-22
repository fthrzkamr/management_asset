<?php
// Restore original config that creates DB connection
include_once(__DIR__ . '/../dist/config/config.php');

// Auth helper might not exist, so check basic session
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

// Check basic permissions (adjust based on your role system)
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['ADMIN', 'FINANCE', 'MANAGER', 'LEADER'];
// Allow all authenticated users for now - adjust as needed
// if (!in_array($user_role, $allowed_roles)) {
//     header("Location: ../index.php");
//     exit();
// }

// Get error messages
$error_message = isset($_GET['error']) ? $_GET['error'] : '';

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

// Get user information with proper JOIN
$user_id = $_SESSION['id_user'];
$user_info_query = "SELECT 
    u.nama_user,
    u.jabatan,
    mc.nama_cabang,
    d.nama_divisi,
    dept.nama_departemen
FROM user_dfm u
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN divisi d ON u.divisi = d.id_divisi
LEFT JOIN departemen dept ON u.departemen = dept.id_departemen
WHERE u.id_user = ?";

$stmt = mysqli_prepare($conn, $user_info_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_info_result = mysqli_stmt_get_result($stmt);
$user_info = mysqli_fetch_assoc($user_info_result);
mysqli_stmt_close($stmt);

// Master budgets will be loaded dynamically via AJAX based on selected period
// No need to load initially
$master_budgets_result = null;
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
                    <h3 class="page-title">Buat Budget Bulanan Baru</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Anggaran Bulanan</a></li>
                        <li class="breadcrumb-item"><a href="monthly_budget_list.php">Kelola Budget</a></li>
                        <li class="breadcrumb-item active">Buat Baru</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Form Create Budget -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0"><i class="fas fa-plus-circle"></i> Form Budget Baru</h4>
                    </div>
                    <div class="card-body">
                        <!-- <div class="alert alert-info">
                            <strong>Catatan alur:</strong> Cabang dapat mengajukan budget tanpa memilih Sumber Dana (Master Anggaran). Finance akan meninjau dan melakukan commit/penetapan Master Anggaran untuk periode tersebut kemudian.
                        </div> -->
                        <form action="monthly_budget_create_insert.php" method="POST" id="budgetForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Bulan <span class="text-danger">*</span></label>
                                        <select name="period_month" id="period_month" class="form-control" required>
                                            <option value="">-- Pilih Bulan --</option>
                                            <?php
                                            $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                                                      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                            for ($i = 1; $i <= 12; $i++) {
                                                echo '<option value="' . $i . '">' . $months[$i] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tahun <span class="text-danger">*</span></label>
                                        <?php $current_year = date('Y'); ?>
                                        <select name="period_year" id="period_year" class="form-control bg-light" required style="pointer-events: none;">
                                            <option value="<?= $current_year ?>" selected><?= $current_year ?></option>
                                        </select>
                                        <small class="text-muted">Tahun berjalan</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tipe Kas <span class="text-danger">*</span></label>
                                        <select name="tipe_anggaran" id="tipe_anggaran" class="form-control" required>
                                            <option value="">-- Pilih Tipe --</option>
                                            <option value="Kas Besar BCA">Kas Besar BCA</option>
                                            <option value="Kas Besar Jakarta">Kas Besar Jakarta</option>
                                            <option value="Kas Kecil Jakarta">Kas Kecil Jakarta</option>
                                            <option value="Bekasi">Bekasi</option>
                                            <option value="Cibinong">Cibinong</option>
                                            <option value="Medan">Medan</option>
                                            <option value="Bali">Bali</option>
                                            <option value="Surabaya">Surabaya</option>
                                            <option value="Malang">Malang</option>
                                            <option value="Banjarmasin">Banjarmasin</option>
                                            <option value="HRGA">HRGA</option>
                                            <option value="Bussines">Bussines</option>
                                            <option value="Warehouse">Warehouse</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="master_id" value="">
                            <!-- <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Sumber Dana (Master Anggaran)</label>
                                        <select name="master_id" id="master_id" class="form-control" disabled>
                                            <option value="">-- Pilih Bulan dan Tahun terlebih dahulu --</option>
                                        </select>
                                        <small class="text-muted">Opsional. Jika tidak dipilih, Finance akan menentukan sumber dana nanti.</small>
                                    </div>
                                </div>
                            </div> -->

                            <!-- Master Balance Info -->
                            <!-- <div id="masterBalanceInfo" class="alert alert-info" style="display:none;">
                                <strong>Info Saldo Master Anggaran:</strong><br>
                                <strong>Saldo Tersedia: </strong><span id="availableBalance">Rp 0</span><br>
                            </div> -->

                            <!-- <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Cabang</label>
                                        <input type="text" class="form-control" 
                                               value="<?= htmlspecialchars($user_info['nama_cabang'] ?? '-') ?>" 
                                               readonly>
                                        <small class="text-muted">Cabang pengguna</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Divisi</label>
                                        <input type="text" class="form-control" 
                                               value="<?= htmlspecialchars($user_info['nama_divisi'] ?? '-') ?>" 
                                               readonly>
                                        <small class="text-muted">Divisi pengguna</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Departemen</label>
                                        <input type="text" class="form-control" 
                                               value="<?= htmlspecialchars($user_info['nama_departemen'] ?? '-') ?>" 
                                               readonly>
                                        <small class="text-muted">Departemen pengguna</small>
                                    </div>
                                </div>
                            </div> -->

                            <hr>

                            <!-- LOAD DARI BULAN LALU (Fitur Utama) -->
                            <div id="prevBudgetCard" class="alert alert-info d-flex align-items-center justify-content-between mb-3" style="display:none !important;">
                                <div>
                                    <i class="fas fa-history text-primary mr-2"></i>
                                    <strong>Muat Pengajuan Terakhir:</strong>
                                    <span id="prevBudgetInfo" class="ml-1 text-muted">Pilih Bulan terlebih dahulu.</span>
                                </div>
                                <button type="button" id="btnLoadPrev" class="btn btn-primary btn-sm ml-3" style="white-space:nowrap;" disabled>
                                    <i class="fas fa-file-import"></i> Muat
                                </button>
                            </div>
                            <div id="loadStatus" style="display:none;" class="mb-3"></div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Catatan</label>
                                        <textarea name="notes" class="form-control" rows="3" 
                                                  placeholder="Catatan tambahan tentang budget ini"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fas fa-file-excel text-success"></i> Upload Lampiran Excel (Opsional)</label>
                                        <div class="custom-file mb-1">
                                            <input type="file" name="file_excel" class="custom-file-input" id="file_excel" accept=".xls,.xlsx">
                                            <label class="custom-file-label" for="file_excel">Pilih file Excel...</label>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-info-circle"></i> File Excel ini akan disimpan sebagai lampiran dokumen pengajuan budget Anda.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">Alokasi per Kategori</h5>
                            <p class="text-muted">Pilih kategori, lalu tambahkan item-item yang ingin dibeli dalam kategori tersebut.</p>
                            
                            <div id="categoriesContainer">
                                <!-- Category groups will be added here -->
                            </div>
                            
                            <button type="button" id="addCategory" class="btn btn-primary btn-sm mt-3" style="width: auto; min-width: 150px;">
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

                             <div class="text-right mt-3">
                                 <a href="monthly_budget_list.php" class="btn btn-secondary">Batal</a>
                                 <button type="submit" name="action" value="draft" class="btn btn-warning">
                                     <i class="fas fa-edit"></i> Simpan sebagai Draft
                                 </button>
                                 <button type="submit" name="action" value="submit" class="btn btn-success">
                                     <i class="fas fa-save"></i> Simpan Budget
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

<!-- SheetJS Library untuk baca Excel di browser -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>


<script>
$(document).ready(function() {
    let masterBalance = 0;

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

    // Category and Item management (nested structure: 1 category -> many items)
    const categoryOptions = `<?= str_replace("`", "\\`", $categoryOptionsHtml) ?>`;
    let categoryCounter = 0;
    let categoryMap = {}; // Maps catId (string) -> actual category ID (int)

    function addCategory() {
        categoryCounter++;
        const catId = 'cat_' + Date.now() + '_' + categoryCounter;
        
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
                            <!-- Items will be added here -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8" class="p-2">
                                    <button type="button" class="btn btn-xs btn-success add-item-btn" data-category-id="${catId}">
                                        <i class="fa fa-plus"></i> Item Baru
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        `;
        
        $('#categoriesContainer').append(categoryBlock);
        bindCategoryEvents();
        
        // Auto add first item
        addItemToCategory(catId);
        
        // Trigger initial category value update for first item
        updateCategoryForItems(catId);
    }

    function addItemToCategory(categoryId) {
        const itemId = 'item_' + Date.now() + '_' + Math.random();
        // Generate unique index for this item row - using timestamp for consistency
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
        // Update category value for this new item
        updateCategoryForItems(categoryId);
    }

    function updateCategoryForItems(categoryId) {
        const selectedValue = $(`.category-select[data-category-id="${categoryId}"]`).val();
        if (selectedValue) {
            $(`.items-body[data-category-id="${categoryId}"]`).find('.item-category-ref').val(selectedValue);
        }
    }

    function bindCategoryEvents() {
        // Add item to category
        $('.add-item-btn').off('click').on('click', function() {
            const catId = $(this).data('category-id');
            addItemToCategory(catId);
        });
        
        // Remove category
        $('.remove-category-btn').off('click').on('click', function() {
            if (confirm('Hapus kategori ini beserta semua itemnya?')) {
                $(this).closest('.category-block').remove();
                updateTotals();
            }
        });
        
        // Category selection change
        $('.category-select').off('change').on('change', function() {
            $(this).removeClass('is-invalid');
            const catId = $(this).data('category-id');
            const selectedValue = $(this).val();
            categoryMap[catId] = selectedValue;
            // Update all hidden inputs for items in this category
            updateCategoryForItems(catId);
        });
    }

    function bindItemEvents() {
        // Price formatting
        $('.item-price').off('keyup input').on('keyup input', function() {
            $(this).removeClass('is-invalid');
            const num = parseRupiah($(this).val());
            $(this).val(formatRupiah(num));
            updateItemTotal($(this).closest('tr'));
        });
        
        // Qty change
        $('.item-qty').off('change input').on('change input', function() {
            updateItemTotal($(this).closest('tr'));
        });
        
        // Remove item
        $('.remove-item-btn').off('click').on('click', function() {
            const $tbody = $(this).closest('tbody');
            $(this).closest('tr').remove();
            
            // If no items left in category, keep at least one
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
        
        // Remove validation on focus
        $('.item-rincian, .item-price, .item-qty, .item-total').off('focus').on('focus', function() {
            $(this).removeClass('is-invalid');
        });
    }

    function updateItemTotal($row) {
        const qty = parseInt($row.find('.item-qty').val()) || 0;
        const price = parseRupiah($row.find('.item-price').val()) || 0;
        const total = qty * price;
        $row.find('.item-total').val(formatRupiah(total));
        
        // Update category subtotal
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
        updateBalanceInfo();
    }

    // Add category button
    $('#addCategory').on('click', function() {
        addCategory();
    });

    // Auto-check and AUTO-LOAD previous month budget
    function checkPrevBudget() {
        const month = $('#period_month').val();
        const year  = $('#period_year').val();
        const tipe  = $('#tipe_anggaran').val();

        if (!month) {
            $('#prevBudgetCard').hide();
            return;
        }

        // Hitung nama bulan lalu
        const monthNames = ['','Januari','Februari','Maret','April','Mei','Juni',
                            'Juli','Agustus','September','Oktober','November','Desember'];
        let prevMonth = parseInt(month) - 1;
        let prevYear  = parseInt(year);
        if (prevMonth < 1) { prevMonth = 12; prevYear--; }

        $('#prevBudgetInfo').text('Mengecek data terakhir...');
        $('#prevBudgetCard').show().css('display','flex');
        $('#btnLoadPrev').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.getJSON('get_prev_month_budget.php', { month: month, year: year, tipe: tipe })
            .done(function(res) {
                if (res.success && res.categories && res.categories.length > 0) {
                    $('#prevBudgetInfo').html('Ditemukan data <strong>' + res.period + '</strong> (' + res.categories.length + ' kategori). Klik Muat untuk mengisi form.');
                    $('#btnLoadPrev').prop('disabled', false)
                        .html('<i class="fas fa-file-import"></i> Muat')
                        .data('budgetData', res);
                } else {
                    $('#prevBudgetInfo').text('Tidak ada data dari pengajuan sebelumnya untuk Tipe Kas ini.');
                    $('#btnLoadPrev').prop('disabled', true).html('<i class="fas fa-file-import"></i> Muat');
                }
            })
            .fail(function() {
                $('#prevBudgetInfo').text('Gagal mengecek data sebelumnya.');
                $('#btnLoadPrev').prop('disabled', true).html('<i class="fas fa-file-import"></i> Muat');
            });
    }

    // Handler tombol Muat manual
    $('#btnLoadPrev').on('click', function() {
        const res = $(this).data('budgetData');
        if (!res) return;
        if (confirm('Muat data dari ' + res.period + '?\nSemua item yang sedang diisi akan diganti.')) {
            populateFromData(res);
            $('#loadStatus').html('<div class="alert alert-success py-2"><i class="fas fa-check-circle"></i> Data dari <strong>' + res.period + '</strong> berhasil dimuat!</div>').show();
            setTimeout(function(){ $('#loadStatus').fadeOut(); }, 4000);
        }
    });

    function populateFromData(budgetData) {
        // Clear existing categories
        $('#categoriesContainer').empty();
        categoryCounter = 0;

        budgetData.categories.forEach(function(cat) {
            categoryCounter++;
            const catId = 'cat_auto_' + Date.now() + '_' + categoryCounter;

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
                            <tfoot>
                                <tr>
                                    <td colspan="8" class="p-2">
                                        <button type="button" class="btn btn-xs btn-success add-item-btn" data-category-id="${catId}">
                                            <i class="fa fa-plus"></i> Item Baru
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            `;
            $('#categoriesContainer').append(categoryBlock);
            bindCategoryEvents();

            $(`.category-select[data-category-id="${catId}"]`).val(cat.category_id);
            updateCategoryForItems(catId);

            cat.items.forEach(function(item) {
                const itemIndex = Date.now() + '_' + Math.floor(Math.random() * 100000);
                const itemId    = 'item_auto_' + itemIndex;

                const satuanOptions = ['', 'pcs', 'unit', 'kg', 'meter', 'box', 'set', 'ltr', 'dll']
                    .map(s => `<option value="${s}" ${s === item.satuan ? 'selected' : ''}>${s || '-'}</option>`)
                    .join('');

                const hargaFormatted = formatRupiah(parseInt(item.harga_satuan) || 0);
                const totalFormatted = formatRupiah(parseInt(item.total_amount) || 0);

                const itemRow = `
                    <tr data-item-id="${itemId}" data-category-id="${catId}" data-item-index="${itemIndex}">
                        <td>
                            <input type="hidden" name="items[${itemIndex}][category]" class="item-category-ref" data-category-id="${catId}" value="${cat.category_id}">
                            <input type="text" name="items[${itemIndex}][rincian]" class="form-control form-control-sm item-rincian" value="${item.rincian}" placeholder="Deskripsi item" required>
                        </td>
                        <td><input type="number" name="items[${itemIndex}][qty]" class="form-control form-control-sm item-qty" value="${item.qty || 1}" min="1"></td>
                        <td>
                            <select name="items[${itemIndex}][satuan]" class="form-control form-control-sm item-satuan">${satuanOptions}</select>
                        </td>
                        <td><input type="number" name="items[${itemIndex}][jumlah_hari]" class="form-control form-control-sm" value="${item.jumlah_hari || ''}" placeholder="0" min="0"></td>
                        <td><input type="number" name="items[${itemIndex}][jumlah_minggu]" class="form-control form-control-sm" value="${item.jumlah_minggu || ''}" placeholder="0" min="0"></td>
                        <td><input type="text" name="items[${itemIndex}][harga]" class="form-control form-control-sm item-price" value="${hargaFormatted}" placeholder="0"></td>
                        <td><input type="text" name="items[${itemIndex}][total]" class="form-control form-control-sm item-total" value="${totalFormatted}" placeholder="0"></td>
                        <td><button type="button" class="btn btn-sm btn-danger remove-item-btn">&times;</button></td>
                    </tr>
                `;

                $(`.items-body[data-category-id="${catId}"]`).append(itemRow);
                bindItemEvents();
            });

            updateCategorySubtotal(catId);
        });

        updateTotals();
    }

    // Add one category on load
    addCategory();


    // Load master budgets logic removed as per user request (Finance will handle commit later)
    // No longer using master_id dropdown in this form
    
    // Calculate total allocation from items

    // Calculate total allocation from items
    function calculateTotal() {
        let total = 0;
        $('.items-body tr').each(function() {
            const qty = parseInt($(this).find('.item-qty').val()) || 0;
            const price = parseRupiah($(this).find('.item-price').val()) || 0;
            total += qty * price;
        });
        return total;
    }

    // Update balance info
    function updateBalanceInfo() {
        let total = calculateTotal();
        let remaining = masterBalance - total;

        $('#availableBalance').text('Rp ' + masterBalance.toLocaleString('id-ID'));
        $('#allocatedAmount').text('Rp ' + total.toLocaleString('id-ID'));
        $('#remainingBalance').text('Rp ' + remaining.toLocaleString('id-ID'));
        
        // Warning jika melebihi saldo
        let infoBox = $('#masterBalanceInfo .alert');
        if (remaining < 0) {
            $('#masterBalanceInfo').removeClass('alert-info alert-warning').addClass('alert-danger');
            $('#remainingBalance').parent().html('<strong class="text-danger">Sisa Setelah Alokasi: </strong><span class="text-danger">' + 
                'Rp ' + remaining.toLocaleString('id-ID') + ' (MELEBIHI SALDO!)</span>');
        } else if (remaining < masterBalance * 0.2) {
            $('#masterBalanceInfo').removeClass('alert-info alert-danger').addClass('alert-warning');
        } else {
            $('#masterBalanceInfo').removeClass('alert-warning alert-danger').addClass('alert-info');
        }
    }

    // Validate form before submit
    $('#budgetForm').submit(function(e) {
        let hasErrors = false;
        let errorMessages = [];
        
        // CRITICAL FIX: Ensure ALL items have category value from their parent block
        $('.category-block').each(function() {
            const $block = $(this);
            const categoryVal = $block.find('.category-select').val();
            const catId = $block.data('category-id');
            
            // Force set category value to ALL items in this block
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
            
            // Remove category if not selected and no items with data
            if (!categoryVal || categoryVal === '0') {
                let hasValidItems = false;
                $block.find('.items-body tr').each(function() {
                    const rincianVal = $(this).find('.item-rincian').val();
                    const rincian = rincianVal ? rincianVal.trim() : '';
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
        
        // Validate each category
        $('.category-block').each(function(catIndex) {
            const $block = $(this);
            const categoryVal = $block.find('.category-select').val();
            const catId = $block.data('category-id');
            
            if (!categoryVal || categoryVal === '0') {
                hasErrors = true;
                errorMessages.push(`Kategori ke-${catIndex + 1}: Belum dipilih`);
                $block.find('.category-select').addClass('is-invalid');
                return; // skip to next category
            }
            
            // Remove completely empty item rows in this category
            $block.find('.items-body tr').each(function() {
                const rincianVal = $(this).find('.item-rincian').val();
                const rincian = rincianVal ? rincianVal.trim() : '';
                const price = parseRupiah($(this).find('.item-price').val());
                
                if (!rincian && price <= 0) {
                    $(this).remove();
                }
            });
            
            // Validate remaining items
            const remainingItems = $block.find('.items-body tr').length;
            
            if (remainingItems === 0) {
                hasErrors = true;
                errorMessages.push(`Kategori ke-${catIndex + 1}: Tidak ada item yang diisi`);
                return;
            }
            
            $block.find('.items-body tr').each(function(itemIndex) {
                const rincianVal = $(this).find('.item-rincian').val();
                const rincian = rincianVal ? rincianVal.trim() : '';
                const priceField = $(this).find('.item-price');
                const priceValue = priceField.val() || '';
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
        
        let total = calculateTotal();
        // If a Master is selected, validate against its balance. If no Master selected, allow pending submission.
        if ($('#master_id').val()) {
            if (total > masterBalance) {
                e.preventDefault();
                alert('Total alokasi (Rp ' + total.toLocaleString('id-ID') + ') melebihi saldo Master Anggaran (Rp ' + masterBalance.toLocaleString('id-ID') + ')!\n\nSilakan kurangi alokasi atau pilih Master Anggaran lain.');
                return false;
            }
        }
        
        if (total === 0) {
            e.preventDefault();
            alert('Silakan isi minimal satu item dengan alokasi!');
            return false;
        }
        
        // master_id is optional in the new flow — allow submit when not selected (Finance will commit later)
    });

    // Jalankan pengecekan otomatis saat Bulan atau Tipe Kas diubah
    $('#period_month, #tipe_anggaran').on('change', function() {
        checkPrevBudget();
    });

    // ============================================================
    // EXCEL ATTACHMENT HANDLER (Opsional)
    // ============================================================
    $('#file_excel').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').text(fileName || 'Pilih file Excel...');
    });
    // ============================================================
    // END EXCEL ATTACHMENT HANDLER
    // ============================================================



});
</script>

</body>
</html>
