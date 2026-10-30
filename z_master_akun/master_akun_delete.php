<?php
include '../dist/config/config.php';
include_once(__DIR__ . '/../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header('Location: ../login.php'); exit(); }
$auth->requirePermission('master_akun', 'delete', 'dashboard.php'); // Ensure the database connection is included

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Delete data from the master_akun table
    $query = "DELETE FROM master_akun WHERE id_ma = '$id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        // Successfully deleted
        header("Location: dashboard.php?status=deleted");
    } else {
        // Failed to delete
        echo "Failed to delete data: " . mysqli_error($conn);
    }
} else {
    echo "ID not found.";
}
?>