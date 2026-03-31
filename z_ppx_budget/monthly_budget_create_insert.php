<?php

include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
require_once __DIR__ . '/../dist/config/ApprovalHelper.php';

// Initialize auth helper
$auth = new AuthHelper($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has permission to create budget
if (!$auth->canCreateBudget()) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../z_master_budget/MasterBudgetService.php';
    $helper = new ApprovalHelper($conn);
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get POST data
        $period_year = intval($_POST['period_year']);
        $period_month = intval($_POST['period_month']);
        $budget_name = trim($_POST['budget_name']);
        $notes = trim($_POST['notes']);
        $tipe_anggaran = trim($_POST['tipe_anggaran'] ?? '');
        $created_by = $_SESSION['id_user'];
        $master_id = trim($_POST['master_id']);
        
        // New UI sends items[] rows: items[][category], items[][rincian], items[][qty], items[][satuan], items[][harga]
        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
        // Backwards compatibility: legacy categories/allocation arrays
        $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
        $allocations = isset($_POST['allocation']) ? $_POST['allocation'] : [];
        $allow_carryovers = isset($_POST['allow_carryover']) ? $_POST['allow_carryover'] : [];
        $item_notes = isset($_POST['item_notes']) ? $_POST['item_notes'] : [];
        
        // Validate
        if (empty($period_year) || empty($period_month)) {
            throw new Exception("Periode tahun dan bulan harus diisi");
        }
        
        // master_id is optional in the new flow: branches may submit without selecting a Master Anggaran
        if (empty($master_id)) {
            $master_id = null;
        }
        
        if (empty($categories) && empty($items)) {
            throw new Exception("Minimal isi satu item atau pilih satu kategori");
        }
        
        // Validate we have valid items (if using new UI)
        if (!empty($items) && $validItemCount === 0) {
            throw new Exception("Tidak ada item yang valid. Pastikan setiap item memiliki kategori dan harga yang valid.");
        }
        
        // Prevent the same user from creating another budget in the same period if they
        // still have remaining allocation (total_remaining > 0).
        // This allows different users to create budgets for the same period and same master,
        // as long as the master balance is sufficient. It also allows the same user to create
        // a new budget for the same period if their previous budget has been fully used (remaining == 0).
        $check_query = "SELECT id_budget, created_by, master_id, total_remaining FROM ppx_monthly_budget 
                WHERE period_year = ? AND period_month = ? AND created_by = ? AND total_remaining > 0";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 'iii', $period_year, $period_month, $created_by);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $existing = mysqli_fetch_assoc($result);
            $existing_master = $existing['master_id'] ?? '';
            $existing_remain = isset($existing['total_remaining']) ? floatval($existing['total_remaining']) : 0;
            throw new Exception("Anda masih memiliki budget aktif untuk periode ini (Sisa: Rp " . number_format($existing_remain,0,',','.') . "). Silakan gunakan budget tersebut atau selesaikan realisasi sebelum membuat budget baru untuk periode yang sama.");
        }
        
        // Auto generate budget name if empty
        if (empty($budget_name)) {
            $month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                           'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            $budget_name = "Anggaran PPX " . $month_names[$period_month] . " " . $period_year;
        }
        
        // Calculate totals
        $total_allocation = 0;
        $categoryAllocations = []; // aggregated per category for summary table
        $allCategories = []; // all categories that need summary rows
        $validItemCount = 0;

        if (!empty($items)) {
            foreach ($items as $it) {
                // Get and validate category
                $cat = 0;
                if (isset($it['category']) && $it['category'] !== '' && $it['category'] !== null) {
                    $cat = intval($it['category']);
                }
                
                $rincian = isset($it['rincian']) ? trim($it['rincian']) : '';
                $qty = isset($it['qty']) ? max(1, intval($it['qty'])) : 1;
                $satuan = isset($it['satuan']) ? trim($it['satuan']) : '';
                $harga_raw = isset($it['harga']) ? $it['harga'] : '0';
                $total_raw = isset($it['total']) ? $it['total'] : '0';
                
                // Parse harga & manual total - remove ALL non-numeric characters
                $harga_clean = str_replace(['Rp', 'rp', 'RP', '.', ',', ' '], '', $harga_raw);
                $harga = intval($harga_clean);
                
                $total_clean = str_replace(['Rp', 'rp', 'RP', '.', ',', ' '], '', $total_raw);
                $manual_total = intval($total_clean);
                
                // Gunakan manual total jika diisi, jika tidak fallback ke qty * harga
                $line_total = $manual_total > 0 ? $manual_total : ($qty * $harga);
                
                // IMPROVED SKIP LOGIC: Skip rows that are not meaningfully filled
                // A row is considered empty/incomplete if:
                // - No rincian AND no valid price (even if category is selected by accident)
                // - OR all fields are empty
                if ((empty($rincian) && $line_total <= 0) || ($cat <= 0 && empty($rincian) && $line_total <= 0)) {
                    continue; // Skip silently - likely an empty/incomplete row
                }
                
                // If row has rincian or price, validate it's complete
                if ($cat <= 0) {
                    throw new Exception("Ada item dengan rincian '$rincian' yang tidak memiliki kategori. Pastikan semua item memiliki kategori yang dipilih.");
                }
                
                if (empty($rincian)) {
                    throw new Exception("Ada item pada kategori ID $cat yang tidak memiliki rincian. Pastikan semua item memiliki deskripsi/rincian.");
                }
                
                if ($line_total <= 0) {
                    throw new Exception("Item '$rincian' (kategori ID: $cat) tidak memiliki harga valid. Harga diterima: '$harga_raw'. Pastikan semua item memiliki harga > 0.");
                }
                
                $validItemCount++;
                $total_allocation += $line_total;

                if (!isset($categoryAllocations[$cat])) {
                    $categoryAllocations[$cat] = 0;
                    $allCategories[] = $cat;
                }
                $categoryAllocations[$cat] += $line_total;
            }
        } else {
            // legacy per-category allocation
            foreach ($categories as $cat_id) {
                $allocation = isset($allocations[$cat_id]) ? floatval($allocations[$cat_id]) : 0;
                $total_allocation += $allocation;
                $categoryAllocations[$cat_id] = $allocation;
                $allCategories[] = $cat_id;
            }
        }
        
        // NOTE: New flow — do NOT deduct from master when branch/user submits a monthly budget.
        // Finance will commit allocations later which will deduct master balance in one operation.
        // Keep this check commented for now; if you want early validation against master balance,
        // implement a non-destructive check (optional).
        
        // Determine initial status based on routing
        $id_cabang_session = $_SESSION['id_cabang'] ?? $_SESSION['cabang'] ?? '';
        $id_divisi_session = $_SESSION['id_divisi'] ?? $_SESSION['divisi'] ?? null;
        
        // TRANSITION LOGIC: Bypass routing for March 2026 as per user request
        if ($period_year == 2026 && $period_month == 3) {
            $initial_status = 'Active';
        } else {
            $initial_status = $helper->getInitialStatus($id_cabang_session, $id_divisi_session);
        }
        
        // Safety fallback if status is empty (should not happen with updated helper, but for robustness)
        if (empty($initial_status)) {
            $initial_status = 'Pending';
        }

        // Upload file Excel logic
        $file_excel_name = null;
        if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['file_excel']['tmp_name'];
            $file_name = $_FILES['file_excel']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['xls', 'xlsx'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $upload_dir = __DIR__ . '/../uploads/ppx_excel/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = 'BUDGET_' . date('YmdHis') . '_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                    $file_excel_name = $new_filename;
                }
            }
        }

        // Insert header budget dengan status dinamis sesuai routing
        // Build INSERT: if master_id is null, insert NULL explicitly to avoid inserting empty string or 0
        if (is_null($master_id)) {
            $insert_budget = "INSERT INTO ppx_monthly_budget 
                             (master_id, period_year, period_month, budget_name, tipe_anggaran, total_allocation, 
                              master_allocation, total_remaining, status, created_by, notes, file_excel) 
                             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_budget);
            mysqli_stmt_bind_param($stmt, 'iisdddsssss', 
                $period_year, $period_month, $budget_name, $tipe_anggaran,
                $total_allocation, $total_allocation, $total_allocation, $initial_status, $created_by, $notes, $file_excel_name);
        } else {
            $insert_budget = "INSERT INTO ppx_monthly_budget 
                             (master_id, period_year, period_month, budget_name, tipe_anggaran, total_allocation, 
                              master_allocation, total_remaining, status, created_by, notes, file_excel) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_budget);
            mysqli_stmt_bind_param($stmt, 'siisdddsssss', 
                $master_id, $period_year, $period_month, $budget_name, $tipe_anggaran,
                $total_allocation, $total_allocation, $total_allocation, $initial_status, $created_by, $notes, $file_excel_name);
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal insert budget header: " . mysqli_error($conn));
        }
        
        $id_budget = mysqli_insert_id($conn);
        $id_budget = mysqli_insert_id($conn);

        // Insert summary rows per category first so detail rows can reference id_budget_item
        $categoryItemIds = []; // map category_id => id_budget_item
        if (!empty($categoryAllocations)) {
            $insert_item = "INSERT INTO ppx_monthly_budget_item 
                           (id_budget, id_category, allocation, remaining, allow_carryover, notes) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item = mysqli_prepare($conn, $insert_item);
            foreach ($categoryAllocations as $cat_id => $allocation) {
                if ($allocation <= 0) continue;
                $allow_carryover = 0;
                $item_note = '';
                mysqli_stmt_bind_param($stmt_item, 'iiddis', 
                    $id_budget, $cat_id, $allocation, $allocation, $allow_carryover, $item_note);
                if (!mysqli_stmt_execute($stmt_item)) {
                    throw new Exception("Gagal insert budget item (summary): " . mysqli_error($conn));
                }
                $new_id = mysqli_insert_id($conn);
                if ($new_id) $categoryItemIds[$cat_id] = $new_id;
            }
        }

        // NEW APPROACH: Insert detail items directly and link to summary via id_budget_item when available
        if (!empty($items)) {
            foreach ($items as $it) {
                // Parse fields - SAME logic as calculation above
                $cat = 0;
                if (isset($it['category']) && $it['category'] !== '' && $it['category'] !== null) {
                    $cat = intval($it['category']);
                }
                
                $rincian = isset($it['rincian']) ? trim($it['rincian']) : '';
                $qty = isset($it['qty']) ? max(1, intval($it['qty'])) : 1;
                $satuan = isset($it['satuan']) ? trim($it['satuan']) : '';
                $harga_raw = isset($it['harga']) ? $it['harga'] : '0';
                $total_raw = isset($it['total']) ? $it['total'] : '0';
                
                // Parse harga - same method
                $harga_clean = str_replace(['Rp', 'rp', 'RP', '.', ',', ' '], '', $harga_raw);
                $harga = intval($harga_clean);
                
                $total_clean = str_replace(['Rp', 'rp', 'RP', '.', ',', ' '], '', $total_raw);
                $manual_total = intval($total_clean);
                
                $line_total = $manual_total > 0 ? $manual_total : ($qty * $harga);
                
                // SAME skip logic as above - skip incomplete rows
                if ((empty($rincian) && $line_total <= 0) || ($cat <= 0 && empty($rincian) && $line_total <= 0)) {
                    continue;
                }
                
                // Skip if still incomplete (safety check - should not happen after validation)
                if ($cat <= 0 || empty($rincian) || $line_total <= 0) {
                    continue;
                }

                // Insert directly to detail table; link to summary id if exists
                $id_budget_item_val = isset($categoryItemIds[$cat]) ? intval($categoryItemIds[$cat]) : 'NULL';
                $rincian_esc = mysqli_real_escape_string($conn, $rincian);
                $satuan_esc = mysqli_real_escape_string($conn, $satuan);
                $jumlah_hari   = intval($it['jumlah_hari']   ?? 0);
                $jumlah_minggu = intval($it['jumlah_minggu'] ?? 0);
                $jumlah_hari_val   = $jumlah_hari   > 0 ? $jumlah_hari   : 'NULL';
                $jumlah_minggu_val = $jumlah_minggu > 0 ? $jumlah_minggu : 'NULL';
                $sql_detail = "INSERT INTO ppx_monthly_budget_item_detail 
                    (id_budget_item, id_budget, id_category, rincian, qty, satuan, jumlah_hari, jumlah_minggu, harga_satuan, total, created_by) 
                    VALUES ($id_budget_item_val, $id_budget, $cat, '$rincian_esc', $qty, '$satuan_esc', $jumlah_hari_val, $jumlah_minggu_val, $harga, $line_total, $created_by)";
                if (!mysqli_query($conn, $sql_detail)) {
                    throw new Exception("Gagal insert budget detail: " . mysqli_error($conn));
                }
            }
        } else if (!empty($categories)) {
            // LEGACY SUPPORT: Old UI that sends categories[] array
            // Insert to summary table for backward compatibility
            $insert_item = "INSERT INTO ppx_monthly_budget_item 
                           (id_budget, id_category, allocation, remaining, allow_carryover, notes) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item = mysqli_prepare($conn, $insert_item);

            foreach ($categoryAllocations as $cat_id => $allocation) {
                if ($allocation <= 0) continue;
                $allow_carryover = isset($allow_carryovers[$cat_id]) ? intval($allow_carryovers[$cat_id]) : 0;
                $item_note = isset($item_notes[$cat_id]) ? trim($item_notes[$cat_id]) : '';
                
                mysqli_stmt_bind_param($stmt_item, 'iiddis', 
                    $id_budget, $cat_id, $allocation, $allocation, $allow_carryover, $item_note);

                if (!mysqli_stmt_execute($stmt_item)) {
                    throw new Exception("Gagal insert budget item (legacy): " . mysqli_error($conn));
                }
            }
        }
        
        mysqli_commit($conn);
        
        if (is_null($master_id)) {
            $success_msg = "Budget berhasil dibuat! Rp " . number_format($total_allocation, 0, ',', '.') . 
                           " (Status: Menunggu penetapan Sumber Dana oleh Finance).";
        } else {
            $success_msg = "Budget berhasil dibuat! Rp " . number_format($total_allocation, 0, ',', '.') . 
                           " telah dialokasikan dari Master Anggaran " . $master_id;
        }
        header("Location: monthly_budget_list.php?id=$id_budget&success=" . urlencode($success_msg));
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $msg = $e->getMessage();
        // Detect duplicate-period DB error and provide migration hint
        if (stripos($msg, 'Duplicate entry') !== false) {
            if (stripos($msg, 'unique_period') !== false) {
                $msg .= " — Detected unique index 'unique_period' on table ppx_monthly_budget.\n" .
                        "Jika Anda ingin mengizinkan beberapa budget per periode untuk master yang berbeda, jalankan migrasi: z_ppx_budget/migrate_unique_period_include_master.sql";
            } elseif (stripos($msg, 'unique_period_master') !== false) {
                $msg .= " — Detected unique index 'unique_period_master' on table ppx_monthly_budget.\n" .
                        "Jika Anda ingin mengizinkan beberapa pengguna membuat budget untuk periode dan sumber dana yang sama, jalankan migrasi: z_ppx_budget/migrate_unique_period_include_master_and_user.sql";
            }
        }
        header("Location: monthly_budget_create.php?error=" . urlencode($msg));
        exit();
    }
}

header("Location: monthly_budget_create.php");
exit();
?>
