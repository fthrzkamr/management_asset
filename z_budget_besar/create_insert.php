<?php
include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
require_once __DIR__ . '/../dist/config/ApprovalHelper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('budget_besar', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create.php"); exit();
}

$helper = new ApprovalHelper($conn);
mysqli_begin_transaction($conn);

try {
    $period_year  = intval($_POST['period_year']);
    $period_month = intval($_POST['period_month']);
    $budget_name  = trim($_POST['budget_name']);
    $notes        = trim($_POST['notes']);
    $created_by   = $_SESSION['id_user'];
    $items        = (isset($_POST['items']) && is_array($_POST['items'])) ? $_POST['items'] : [];

    if (!$period_year || !$period_month) throw new Exception("Periode harus diisi.");

    // Hitung total & validasi items
    $total_allocation   = 0;
    $categoryAllocations = [];
    $validItemCount      = 0;

    foreach ($items as $it) {
        $cat    = intval($it['category'] ?? 0);
        $rincian = trim($it['rincian'] ?? '');
        $qty    = max(1, intval($it['qty'] ?? 1));
        $satuan = trim($it['satuan'] ?? '');
        $harga  = intval(str_replace(['Rp','rp','RP','.',' ',','], '', $it['harga'] ?? '0'));
        $subtotal = $qty * $harga;

        if (empty($rincian) && $subtotal <= 0) continue;
        if ($cat <= 0) throw new Exception("Item '$rincian' tidak memiliki kategori.");
        if (empty($rincian)) throw new Exception("Ada item tanpa rincian pada kategori $cat.");
        if ($subtotal <= 0) throw new Exception("Item '$rincian' harus memiliki harga > 0.");

        $validItemCount++;
        $total_allocation += $subtotal;
        $categoryAllocations[$cat] = ($categoryAllocations[$cat] ?? 0) + $subtotal;
    }

    if ($validItemCount === 0) throw new Exception("Minimal ada 1 item yang valid.");

    if (empty($budget_name)) {
        $mns = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $budget_name = "Anggaran Besar " . $mns[$period_month] . " " . $period_year;
    }

    // Status awal sesuai routing
    $id_cabang = $_SESSION['id_cabang'] ?? $_SESSION['cabang'] ?? '';
    $id_divisi = $_SESSION['id_divisi'] ?? $_SESSION['divisi'] ?? null;
    
    // TRANSITION LOGIC: Bypass routing for March 2026 as per user request
    if ($period_year == 2026 && $period_month == 3) {
        $initial_status = 'Active';
    } else {
        $initial_status = $helper->getInitialStatus($id_cabang, $id_divisi);
    }
    
    if (empty($initial_status)) {
        $initial_status = 'Pending';
    }

    $budget_type = 'besar'; // Selalu 'besar' untuk modul ini

    // Insert header — budget_type = 'besar'
    $stmt = mysqli_prepare($conn, "INSERT INTO ppx_monthly_budget
        (period_year, period_month, budget_name, total_allocation, master_allocation,
         total_remaining, status, created_by, notes, budget_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iisdddsiss',
        $period_year, $period_month, $budget_name,
        $total_allocation, $total_allocation, $total_allocation,
        $initial_status, $created_by, $notes, $budget_type);

    if (!mysqli_stmt_execute($stmt)) throw new Exception("Gagal insert header: " . mysqli_error($conn));
    $id_budget = mysqli_insert_id($conn);

    // Insert summary per kategori
    $catItemIds = [];
    $stmt_item = mysqli_prepare($conn, "INSERT INTO ppx_monthly_budget_item
        (id_budget, id_category, allocation, remaining, allow_carryover, notes)
        VALUES (?, ?, ?, ?, 0, '')");
    foreach ($categoryAllocations as $cat_id => $alloc) {
        if ($alloc <= 0) continue;
        mysqli_stmt_bind_param($stmt_item, 'iidd', $id_budget, $cat_id, $alloc, $alloc);
        if (!mysqli_stmt_execute($stmt_item)) throw new Exception("Gagal insert item: " . mysqli_error($conn));
        $catItemIds[$cat_id] = mysqli_insert_id($conn);
    }

    // Insert detail items
    foreach ($items as $it) {
        $cat    = intval($it['category'] ?? 0);
        $rincian = trim($it['rincian'] ?? '');
        $qty    = max(1, intval($it['qty'] ?? 1));
        $satuan = trim($it['satuan'] ?? '');
        $harga  = intval(str_replace(['Rp','rp','RP','.',' ',','], '', $it['harga'] ?? '0'));
        $subtotal = $qty * $harga;
        $jumlah_hari   = intval($it['jumlah_hari']   ?? 0);
        $jumlah_minggu = intval($it['jumlah_minggu'] ?? 0);
        if ($cat <= 0 || empty($rincian) || $subtotal <= 0) continue;

        $id_item = $catItemIds[$cat] ?? 'NULL';
        $rincian_esc = mysqli_real_escape_string($conn, $rincian);
        $satuan_esc  = mysqli_real_escape_string($conn, $satuan);
        $jumlah_hari_val   = $jumlah_hari   > 0 ? $jumlah_hari   : 'NULL';
        $jumlah_minggu_val = $jumlah_minggu > 0 ? $jumlah_minggu : 'NULL';
        mysqli_query($conn, "INSERT INTO ppx_monthly_budget_item_detail
            (id_budget_item, id_budget, id_category, rincian, qty, satuan, jumlah_hari, jumlah_minggu, harga_satuan, total, created_by)
            VALUES ($id_item, $id_budget, $cat, '$rincian_esc', $qty, '$satuan_esc', $jumlah_hari_val, $jumlah_minggu_val, $harga, $subtotal, $created_by)");
    }

    mysqli_commit($conn);
    $_SESSION['success'] = "Anggaran Besar berhasil diajukan! Total: Rp " . number_format($total_allocation, 0, ',', '.');
    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Location: create.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
