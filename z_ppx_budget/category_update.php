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

// TODO: Process update kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_category = intval($_POST['id_category']);
    $category_code = strtoupper(trim($_POST['category_code']));
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $is_active = intval($_POST['is_active']);
    
    // Validate
    if (empty($category_code) || empty($category_name)) {
        header("Location: category_manage.php?error=Kode dan nama kategori harus diisi");
        exit();
    }
    
    // Check duplicate code (exclude current)
    $check_query = "SELECT id_category FROM ppx_budget_category WHERE category_code = ? AND id_category != ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 'si', $category_code, $id_category);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        header("Location: category_manage.php?error=Kode kategori sudah digunakan kategori lain");
        exit();
    }
    
    // Update
    $update_query = "UPDATE ppx_budget_category 
                     SET category_code = ?, category_name = ?, description = ?, is_active = ?
                     WHERE id_category = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, 'sssii', $category_code, $category_name, $description, $is_active, $id_category);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: category_manage.php?success=Kategori berhasil diupdate");
    } else {
        header("Location: category_manage.php?error=Gagal update kategori: " . mysqli_error($conn));
    }
    exit();
}

header("Location: category_manage.php");
exit();
?>
