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

// Check if user has permission to manage category
if (!$auth->canManageCategory()) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

$allowed_roles = ['manag_finance', 'super_admin'];
if (!in_array($_SESSION['jabatan'], $allowed_roles)) {
    header("Location: ../index.php");
    exit();
}

// TODO: Toggle kategori active/inactive
$id_category = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id_category > 0 && in_array($action, ['activate', 'deactivate'])) {
    $is_active = ($action === 'activate') ? 1 : 0;
    
    $update_query = "UPDATE ppx_budget_category SET is_active = ? WHERE id_category = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, 'ii', $is_active, $id_category);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = ($action === 'activate') ? 'Kategori berhasil diaktifkan' : 'Kategori berhasil dinonaktifkan';
        header("Location: category_manage.php?success=$message");
    } else {
        header("Location: category_manage.php?error=Gagal mengubah status kategori");
    }
} else {
    header("Location: category_manage.php?error=Parameter tidak valid");
}
exit();
?>
