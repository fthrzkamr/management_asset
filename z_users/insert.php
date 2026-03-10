<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_user = trim($_POST['nama_user']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $id_role = $_POST['id_role'];
    $divisi = $_POST['divisi'];
    $departemen = $_POST['departemen'];
    $jabatan = trim($_POST['jabatan']);
    $id_cabang = $_POST['id_cabang'] ?? '';

    // Validasi sederhana
    if ($nama_user && $username && $password && $id_role) {
        // Generate ID user berikutnya
        $query_max_id = mysqli_query($conn, "SELECT MAX(CAST(id_user AS UNSIGNED)) as max_id FROM user_dfm");
        $row = mysqli_fetch_assoc($query_max_id);
        $next_id = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;
        
        // Password tidak di-hash, langsung simpan
        $insert = mysqli_query($conn, "INSERT INTO user_dfm (id_user, nama_user, username, password, id_role, divisi, departemen, jabatan, id_cabang) 
            VALUES ('$next_id', '$nama_user', '$username', '$password', '$id_role', '$divisi', '$departemen', '$jabatan', '$id_cabang')");
        if ($insert) {
            $_SESSION['success'] = "User berhasil ditambahkan dengan ID: $next_id";
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Gagal menambah user: " . mysqli_error($conn);
            header("Location: add.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Semua field wajib diisi!";
        header("Location: add.php");
        exit();
    }
} else {
    header("Location: add.php");
    exit();
}
?>