<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



// Buat instance auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Unauthorized!';
    header("Location: dashboard.php");
    exit();
}

// Cek permission - hanya yang punya akses approval_budget_finance
if (!$auth->hasPermission('approval_budget_finance', 'approve')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budget_id = isset($_POST['budget_id']) ? intval($_POST['budget_id']) : 0;
    $tanggal_realisasi = $_POST['tanggal_realisasi'] ?? '';
    $nominal = isset($_POST['nominal']) ? floatval($_POST['nominal']) : 0;
    $keterangan = $_POST['keterangan'] ?? '';

    // Validasi
    if ($budget_id <= 0) {
        $_SESSION['error'] = 'Budget ID tidak valid!';
        header("Location: dashboard.php");
        exit();
    }

    if (empty($tanggal_realisasi)) {
        $_SESSION['error'] = 'Tanggal pembayaran harus diisi!';
        header("Location: dashboard.php");
        exit();
    }

    if ($nominal <= 0) {
        $_SESSION['error'] = 'Nominal pembayaran harus diisi!';
        header("Location: dashboard.php");
        exit();
    }

    // Validasi file upload
    if (!isset($_FILES['bukti_tf']) || $_FILES['bukti_tf']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Bukti pembayaran harus diupload!';
        header("Location: dashboard.php");
        exit();
    }

    $file = $_FILES['bukti_tf'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = 'Format file tidak valid! Gunakan JPG, JPEG, PNG, atau PDF.';
        header("Location: dashboard.php");
        exit();
    }

    if ($file['size'] > $max_size) {
        $_SESSION['error'] = 'Ukuran file terlalu besar! Maksimal 2MB.';
        header("Location: dashboard.php");
        exit();
    }

    // Upload file
    $upload_dir = __DIR__ . '/../uploads/ppx_tf/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = 'TF_' . $budget_id . '_' . date('YmdHis') . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $_SESSION['error'] = 'Gagal upload file!';
        header("Location: dashboard.php");
        exit();
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Verify budget exists and is Pending (menunggu realisasi)
        $check_query = "SELECT id_budget, budget_name, total_remaining, status FROM ppx_monthly_budget 
                       WHERE id_budget = ? AND status IN ('Finance (Realisasi)', 'Active')";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, 'i', $budget_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 0) {
            throw new Exception('Budget tidak ditemukan atau belum disetujui Manager Finance!');
        }

        $budget = mysqli_fetch_assoc($result);
        $current_remaining = floatval($budget['total_remaining']);
        $current_status = $budget['status'];

        // Check if nominal exceeds remaining
        if ($nominal > $current_remaining) {
            throw new Exception('Nominal pembayaran melebihi sisa anggaran!');
        }

        // Update main budget: decrease total_remaining dan aktifkan jika masih Pending
        if ($current_status === 'Finance (Realisasi)') {
            $update_budget = "UPDATE ppx_monthly_budget 
                             SET total_remaining = total_remaining - ?,
                                 status = 'Active',
                                 notes = CONCAT(COALESCE(notes, ''), '\n\nAktif setelah realisasi pertama oleh Finance.')
                             WHERE id_budget = ?";
        } else {
            $update_budget = "UPDATE ppx_monthly_budget 
                             SET total_remaining = total_remaining - ?
                             WHERE id_budget = ?";
        }
        
        $stmt_budget = mysqli_prepare($conn, $update_budget);
        mysqli_stmt_bind_param($stmt_budget, 'di', $nominal, $budget_id);

        if (!mysqli_stmt_execute($stmt_budget)) {
            throw new Exception('Gagal update budget: ' . mysqli_error($conn));
        }

        // Insert realisasi record
        $insert_realisasi = "INSERT INTO ppx_budget_realisasi 
                            (id_budget, tanggal_realisasi, nominal, bukti_tf, keterangan, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt_real = mysqli_prepare($conn, $insert_realisasi);
        mysqli_stmt_bind_param($stmt_real, 'isdssi', 
            $budget_id, $tanggal_realisasi, $nominal, $new_filename, $keterangan, $_SESSION['id_user']);

        if (!mysqli_stmt_execute($stmt_real)) {
            throw new Exception('Gagal insert realisasi record: ' . mysqli_error($conn));
        }

        // Commit transaction
        mysqli_commit($conn);

        $_SESSION['success'] = 'Bukti pembayaran berhasil diupload! Nominal: Rp ' . number_format($nominal, 0, ',', '.');
        header("Location: dashboard.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        
        // Delete uploaded file if transaction fails
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        
        $_SESSION['error'] = $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
} else {
    $_SESSION['error'] = 'Invalid request method!';
    header("Location: dashboard.php");
    exit();
}
