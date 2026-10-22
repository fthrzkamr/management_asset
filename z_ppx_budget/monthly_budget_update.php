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

// Check if user has permission to update budget
if (!$auth->canUpdateBudget()) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_begin_transaction($conn);
    
    try {
        // Get POST data
        $id_budget = intval($_POST['id_budget']);
        $budget_name = trim($_POST['budget_name']);
        $notes = trim($_POST['notes']);
        $tipe_anggaran = trim($_POST['tipe_anggaran'] ?? '');
        $submitted_action = $_POST['action'] ?? 'submit';
        $user_id = $_SESSION['id_user'];
        
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        
        if ($id_budget <= 0) {
            throw new Exception("ID Budget tidak valid");
        }
        
        // Get current budget data including master_id and old allocation
        $current_query = "SELECT master_id, total_allocation, status, approved_by, period_year, period_month FROM ppx_monthly_budget WHERE id_budget = ?";
        $stmt_cur = mysqli_prepare($conn, $current_query);
        mysqli_stmt_bind_param($stmt_cur, 'i', $id_budget);
        mysqli_stmt_execute($stmt_cur);
        $current_budget = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cur));
        
        if (!$current_budget) {
            throw new Exception("Budget tidak ditemukan");
        }
        
        // Determine status: if action=draft, keep as Draft
        if ($submitted_action === 'draft') {
            $status = 'Draft';
        } else if ($current_budget['status'] === 'Draft') {
            // Transitioning from Draft to Submit: use initial routing status
            $helper = new ApprovalHelper($conn);
            $id_cabang_session = $_SESSION['id_cabang'] ?? $_SESSION['cabang'] ?? '';
            $id_divisi_session = $_SESSION['id_divisi'] ?? $_SESSION['divisi'] ?? null;
            
            // Bypass logic for March 2026
            if ($current_budget['period_year'] == 2026 && $current_budget['period_month'] == 3) {
                $status = 'Active';
            } else {
                $status = $helper->getInitialStatus($id_cabang_session, $id_divisi_session);
            }
            if (empty($status)) $status = 'Pending';
        } else {
            // Keep current status unless it's Revisi being resubmitted
            $status = $_POST['status'] ?? $current_budget['status'];
        }
        
        // Check if budget is already approved - cannot edit
        $is_approved = ($current_budget['approved_by'] !== null || in_array($current_budget['status'], ['Active', 'Closed']));
        $is_revisi = $current_budget['status'] === 'Revisi';
        
        if ($is_approved && !$is_revisi) {
            throw new Exception("Budget yang sudah disetujui/aktif tidak dapat diubah");
        }
        
        // For Draft action, allow empty items (partial save)
        if ($submitted_action === 'draft' && empty($items)) {
            // Update header only, no items required
            $update_budget = "UPDATE ppx_monthly_budget SET budget_name = ?, status = ?, notes = ?, tipe_anggaran = ? WHERE id_budget = ?";
            $stmt = mysqli_prepare($conn, $update_budget);
            mysqli_stmt_bind_param($stmt, 'ssssi', $budget_name, $status, $notes, $tipe_anggaran, $id_budget);
            if (!mysqli_stmt_execute($stmt)) throw new Exception("Gagal update budget: " . mysqli_error($conn));
            mysqli_commit($conn);
            header("Location: monthly_budget_edit.php?id=$id_budget&success=Draft berhasil disimpan");
            exit();
        }
        
        if (empty($items) && $submitted_action !== 'draft') {
            throw new Exception("Tidak ada item yang dikirim");
        }
        
        $master_id = $current_budget['master_id'];
        $old_allocation = floatval($current_budget['total_allocation']);
        
        // Calculate total allocation from items
        $total_allocation = 0;
        $categoryAllocations = [];
        
        foreach ($items as $itemIndex => $item) {
            $cat = isset($item['category']) ? intval($item['category']) : 0;
            $rincian = isset($item['rincian']) ? trim($item['rincian']) : '';
            $qty = isset($item['qty']) ? intval($item['qty']) : 0;
            $harga_raw = isset($item['harga']) ? $item['harga'] : '0';
            $total_raw = isset($item['total']) ? $item['total'] : '0';
            
            // Parse harga (remove Rupiah formatting)
            $harga = intval(preg_replace('/[^\d]/', '', $harga_raw));
            $manual_total = intval(preg_replace('/[^\d]/', '', $total_raw));
            
            // Skip empty items
            if (empty($rincian) && $harga <= 0) {
                continue;
            }
            
            // Validate
            if ($cat <= 0) {
                throw new Exception("Ada item dengan rincian '$rincian' yang tidak memiliki kategori");
            }
            if (empty($rincian)) {
                throw new Exception("Ada item yang tidak memiliki rincian");
            }
            if ($harga <= 0) {
                $postCount = count($_POST, COUNT_RECURSIVE);
                throw new Exception("Harga untuk '$rincian' harus lebih dari 0 (Diterima: '$harga_raw', Hasil parse: '$harga', Total Post Vars: $postCount)");
            }
            
            $calculated_total = $qty * $harga;
            $line_total = $manual_total > 0 ? $manual_total : $calculated_total;
            $total_allocation += $line_total;
            
            // Aggregate by category for summary table
            if (!isset($categoryAllocations[$cat])) {
                $categoryAllocations[$cat] = 0;
            }
            $categoryAllocations[$cat] += $line_total;
        }
        
        if ($total_allocation <= 0 && $submitted_action !== 'draft') {
            throw new Exception("Total alokasi harus lebih dari 0");
        }
        
        // Calculate allocation difference
        $allocation_diff = $total_allocation - $old_allocation;
        
        // If allocation increased, check master budget balance
        if ($allocation_diff > 0 && $master_id) {
            $balance_query = "SELECT balance FROM vw_master_budget_summary WHERE master_id = ?";
            $stmt_bal = mysqli_prepare($conn, $balance_query);
            mysqli_stmt_bind_param($stmt_bal, 's', $master_id);
            mysqli_stmt_execute($stmt_bal);
            $balance_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bal));
            
            $available_balance = $balance_result ? floatval($balance_result['balance']) : 0;
            // Add back old allocation since it will be replaced
            $available_balance += $old_allocation;
            
            if ($total_allocation > $available_balance) {
                throw new Exception("Total alokasi (Rp " . number_format($total_allocation, 0, ',', '.') . 
                    ") melebihi saldo master anggaran yang tersedia (Rp " . number_format($available_balance, 0, ',', '.') . ")");
            }
        }
        
        // Handle File Upload
        $file_excel = '';
        if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/ppx_excel/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_info = pathinfo($_FILES['file_excel']['name']);
            $ext = strtolower($file_info['extension']);
            if (in_array($ext, ['xls', 'xlsx'])) {
                $new_filename = 'budget_' . time() . '_' . uniqid() . '.' . $ext;
                $target_file = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['file_excel']['tmp_name'], $target_file)) {
                    $file_excel = $new_filename;
                }
            } else {
                throw new Exception("Format file Excel tidak valid. Harus .xls atau .xlsx");
            }
        }
        
        // Update budget header
        if ($file_excel !== '') {
            $update_budget = "UPDATE ppx_monthly_budget 
                             SET budget_name = ?, status = ?, notes = ?, tipe_anggaran = ?,
                                 total_allocation = ?, total_remaining = ?, master_allocation = ?, file_excel = ?
                             WHERE id_budget = ?";
            $stmt = mysqli_prepare($conn, $update_budget);
            if (!$stmt) {
                throw new Exception("Gagal prepare update budget: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, 'ssssdddsi', 
                $budget_name, $status, $notes, $tipe_anggaran,
                $total_allocation, $total_allocation, $total_allocation, $file_excel, $id_budget);
        } else {
            $update_budget = "UPDATE ppx_monthly_budget 
                             SET budget_name = ?, status = ?, notes = ?, tipe_anggaran = ?,
                                 total_allocation = ?, total_remaining = ?, master_allocation = ?
                             WHERE id_budget = ?";
            $stmt = mysqli_prepare($conn, $update_budget);
            if (!$stmt) {
                throw new Exception("Gagal prepare update budget: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, 'ssssdddi', 
                $budget_name, $status, $notes, $tipe_anggaran,
                $total_allocation, $total_allocation, $total_allocation, $id_budget);
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal update budget header: " . mysqli_error($conn));
        }
        
        // Update master budget balance if there's a difference
        if ($allocation_diff != 0 && $master_id) {
            // Get current master budget data
            $bal_query = "SELECT amount_in, amount_out, balance FROM master_budget WHERE master_id = ?";
            $stmt_b = mysqli_prepare($conn, $bal_query);
            mysqli_stmt_bind_param($stmt_b, 's', $master_id);
            mysqli_stmt_execute($stmt_b);
            $bal_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_b));
            
            if ($bal_row) {
                $current_amount_in = floatval($bal_row['amount_in']);
                $current_amount_out = floatval($bal_row['amount_out']);
                $current_balance = floatval($bal_row['balance']);
                
                $abs_diff = abs($allocation_diff);
                
                if ($allocation_diff > 0) {
                    // Allocation increased = add to amount_out
                    $new_amount_out = $current_amount_out + $abs_diff;
                    $new_balance = $current_amount_in - $new_amount_out;
                    
                    $update_master = "UPDATE master_budget SET amount_out = ?, balance = ?, updated_at = NOW() WHERE master_id = ?";
                    $stmt_upd = mysqli_prepare($conn, $update_master);
                    mysqli_stmt_bind_param($stmt_upd, 'dds', $new_amount_out, $new_balance, $master_id);
                } else {
                    // Allocation decreased = reduce amount_out (return to balance)
                    $new_amount_out = $current_amount_out - $abs_diff;
                    if ($new_amount_out < 0) $new_amount_out = 0; // Prevent negative
                    $new_balance = $current_amount_in - $new_amount_out;
                    
                    $update_master = "UPDATE master_budget SET amount_out = ?, balance = ?, updated_at = NOW() WHERE master_id = ?";
                    $stmt_upd = mysqli_prepare($conn, $update_master);
                    mysqli_stmt_bind_param($stmt_upd, 'dds', $new_amount_out, $new_balance, $master_id);
                }
                
                if (!mysqli_stmt_execute($stmt_upd)) {
                    throw new Exception("Gagal update saldo master: " . mysqli_error($conn));
                }
            }
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal update budget header: " . mysqli_error($conn));
        }
        
        // Get existing item IDs to track what to delete
        $existing_ids = [];
        $check_query = "SELECT id FROM ppx_monthly_budget_item_detail WHERE id_budget = ?";
        $stmt_check = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt_check, 'i', $id_budget);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        while ($row = mysqli_fetch_assoc($result_check)) {
            $existing_ids[$row['id']] = true;
        }
        
        // STEP 1: Create/update summary items first to get id_budget_item
        $category_to_budget_item = [];
        foreach ($categoryAllocations as $cat_id => $allocation) {
            // Check if summary exists
            $check_summary = "SELECT id_budget_item FROM ppx_monthly_budget_item WHERE id_budget = ? AND id_category = ?";
            $stmt_cs = mysqli_prepare($conn, $check_summary);
            mysqli_stmt_bind_param($stmt_cs, 'ii', $id_budget, $cat_id);
            mysqli_stmt_execute($stmt_cs);
            $result_cs = mysqli_stmt_get_result($stmt_cs);
            
            if ($row_cs = mysqli_fetch_assoc($result_cs)) {
                // Update existing summary
                $update_summary = "UPDATE ppx_monthly_budget_item 
                                  SET allocation = ?, remaining = ?
                                  WHERE id_budget_item = ?";
                $stmt_us = mysqli_prepare($conn, $update_summary);
                mysqli_stmt_bind_param($stmt_us, 'ddi', $allocation, $allocation, $row_cs['id_budget_item']);
                mysqli_stmt_execute($stmt_us);
                $category_to_budget_item[$cat_id] = $row_cs['id_budget_item'];
            } else {
                // Insert new summary
                $insert_summary = "INSERT INTO ppx_monthly_budget_item 
                                  (id_budget, id_category, allocation, remaining) 
                                  VALUES (?, ?, ?, ?)";
                $stmt_is = mysqli_prepare($conn, $insert_summary);
                mysqli_stmt_bind_param($stmt_is, 'iidd', $id_budget, $cat_id, $allocation, $allocation);
                if (!mysqli_stmt_execute($stmt_is)) {
                    throw new Exception("Gagal insert summary kategori: " . mysqli_error($conn));
                }
                $category_to_budget_item[$cat_id] = mysqli_insert_id($conn);
            }
        }
        
        // Delete summary items for categories that no longer have items
        if (!empty($categoryAllocations)) {
            $cat_ids_str = implode(',', array_map('intval', array_keys($categoryAllocations)));
            $delete_orphan = "DELETE FROM ppx_monthly_budget_item 
                             WHERE id_budget = $id_budget 
                             AND id_category NOT IN ($cat_ids_str)";
            mysqli_query($conn, $delete_orphan);
        }
        
        $processed_ids = [];
        
        // STEP 2: Process items - update existing or insert new (NOW with id_budget_item)
        foreach ($items as $itemIndex => $item) {
            $item_id = isset($item['id']) ? intval($item['id']) : 0;
            $cat = isset($item['category']) ? intval($item['category']) : 0;
            $rincian = isset($item['rincian']) ? trim($item['rincian']) : '';
            $qty = isset($item['qty']) ? intval($item['qty']) : 1;
            $satuan = isset($item['satuan']) ? trim($item['satuan']) : '';
            $harga_raw = isset($item['harga']) ? $item['harga'] : '0';
            $total_raw = isset($item['total']) ? $item['total'] : '0';
            
            // Parse harga
            $harga = intval(preg_replace('/[^\d]/', '', $harga_raw));
            $manual_total = intval(preg_replace('/[^\d]/', '', $total_raw));
            
            // Skip empty items
            if (empty($rincian) && $harga <= 0) {
                continue;
            }
            
            if ($cat <= 0 || empty($rincian) || $harga <= 0) {
                continue;
            }
            
            $calculated_total = $qty * $harga;
            $line_total = $manual_total > 0 ? $manual_total : $calculated_total;
            
            // Get id_budget_item for this category
            $id_budget_item = isset($category_to_budget_item[$cat]) ? $category_to_budget_item[$cat] : null;
            
            if (!$id_budget_item) {
                throw new Exception("ID budget item tidak ditemukan untuk kategori: $cat");
            }
            
            if ($item_id > 0 && isset($existing_ids[$item_id])) {
                // Update existing item
                $jumlah_hari = intval($item['jumlah_hari'] ?? 0);
                $jumlah_minggu = intval($item['jumlah_minggu'] ?? 0);
                $update_item = "UPDATE ppx_monthly_budget_item_detail 
                               SET id_budget_item = ?, id_category = ?, rincian = ?, qty = ?, satuan = ?, 
                                   jumlah_hari = ?, jumlah_minggu = ?, harga_satuan = ?, total = ?
                               WHERE id = ? AND id_budget = ?";
                $stmt_item = mysqli_prepare($conn, $update_item);
                if (!$stmt_item) {
                    throw new Exception("Gagal prepare update item: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_item, 'iisisiidiii', 
                    $id_budget_item, $cat, $rincian, $qty, $satuan, 
                    $jumlah_hari, $jumlah_minggu, $harga, $line_total, $item_id, $id_budget);
                
                if (!mysqli_stmt_execute($stmt_item)) {
                    throw new Exception("Gagal update item: " . mysqli_error($conn));
                }
                
                $processed_ids[] = $item_id;
            } else {
                // Insert new item WITH id_budget_item
                $jumlah_hari = intval($item['jumlah_hari'] ?? 0);
                $jumlah_minggu = intval($item['jumlah_minggu'] ?? 0);
                $insert_item = "INSERT INTO ppx_monthly_budget_item_detail 
                               (id_budget, id_budget_item, id_category, rincian, qty, satuan, jumlah_hari, jumlah_minggu, harga_satuan, total, created_by) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_item = mysqli_prepare($conn, $insert_item);
                if (!$stmt_item) {
                    throw new Exception("Gagal prepare insert item: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_item, 'iiisisiiidi', 
                    $id_budget, $id_budget_item, $cat, $rincian, $qty, $satuan, 
                    $jumlah_hari, $jumlah_minggu, $harga, $line_total, $user_id);
                
                if (!mysqli_stmt_execute($stmt_item)) {
                    throw new Exception("Gagal insert item baru: " . mysqli_error($conn));
                }
                
                $processed_ids[] = mysqli_insert_id($conn);
            }
        }
        
        // Delete items that were removed (not in processed_ids)
        foreach ($existing_ids as $old_id => $val) {
            if (!in_array($old_id, $processed_ids)) {
                $delete_item = "DELETE FROM ppx_monthly_budget_item_detail WHERE id = ? AND id_budget = ?";
                $stmt_del = mysqli_prepare($conn, $delete_item);
                mysqli_stmt_bind_param($stmt_del, 'ii', $old_id, $id_budget);
                mysqli_stmt_execute($stmt_del);
            }
        }
        
        mysqli_commit($conn);
        
        if ($submitted_action === 'draft') {
            header("Location: monthly_budget_edit.php?id=$id_budget&success=" . urlencode("Draft berhasil disimpan"));
        } else {
            header("Location: monthly_budget_detail.php?id=$id_budget&success=Budget berhasil diupdate");
        }
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $id = isset($_POST['id_budget']) ? intval($_POST['id_budget']) : 0;
        header("Location: monthly_budget_edit.php?id=$id&error=" . urlencode($e->getMessage()));
        exit();
    }
}

header("Location: monthly_budget_list.php");
exit();
?>
