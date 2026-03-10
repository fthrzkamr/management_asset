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

// TODO: Process insert kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_code = strtoupper(trim($_POST['category_code']));
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    
    // Validate
    if (empty($category_code) || empty($category_name)) {
        header("Location: category_manage.php?error=Kode dan nama kategori harus diisi");
        exit();
    }
    
    // Check duplicate code
    $check_query = "SELECT id_category FROM ppx_budget_category WHERE category_code = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 's', $category_code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        header("Location: category_manage.php?error=Kode kategori sudah ada");
        exit();
    }
    
    // Insert
    $insert_query = "INSERT INTO ppx_budget_category (category_code, category_name, description, is_active) 
                     VALUES (?, ?, ?, 1)";
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, 'sss', $category_code, $category_name, $description);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: category_manage.php?success=Kategori berhasil ditambahkan");
    } else {
        header("Location: category_manage.php?error=Gagal menambahkan kategori: " . mysqli_error($conn));
    }
    exit();
}

header("Location: category_manage.php");
exit();
?>
