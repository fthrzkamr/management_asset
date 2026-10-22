<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }

if (!$auth->isSuperAdmin() && !$auth->hasPermission('approval_peminjaman', 'read')) {
    header("Location: ../dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_peminjaman = (int)$_POST['id_peminjaman'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        // 1. Get the id_mobil for this request
        $q_get = mysqli_query($conn, "SELECT id_mobil FROM peminjaman_kendaraan WHERE id_peminjaman = $id_peminjaman");
        $data = mysqli_fetch_assoc($q_get);
        $id_mobil = $data['id_mobil'];

        // 2. Check if car is already occupied by another APPROVED request that hasn't expired
        $q_check = mysqli_query($conn, "SELECT COUNT(*) as active_count FROM peminjaman_kendaraan 
                                        WHERE id_mobil = $id_mobil 
                                        AND status_approval = 'Disetujui' 
                                        AND tanggal_selesai > NOW()");
        $check_data = mysqli_fetch_assoc($q_check);

        if ($check_data['active_count'] > 0) {
            $_SESSION['error'] = "Gagal menyetujui: Kendaraan ini sedang dipinjam oleh orang lain. Selesaikan dulu peminjaman sebelumnya.";
        } else {
            $q_update = mysqli_query($conn, "UPDATE peminjaman_kendaraan SET status_approval = 'Disetujui' WHERE id_peminjaman = $id_peminjaman");
            if ($q_update) {
                $_SESSION['success'] = "Peminjaman berhasil disetujui.";
            } else {
                $_SESSION['error'] = "Gagal menyetujui peminjaman: " . mysqli_error($conn);
            }
        }

    } elseif ($action === 'reject') {
        $q_update = mysqli_query($conn, "UPDATE peminjaman_kendaraan SET status_approval = 'Ditolak' WHERE id_peminjaman = $id_peminjaman");
        if ($q_update) {
            $_SESSION['success'] = "Peminjaman telah ditolak.";
        } else {
            $_SESSION['error'] = "Gagal menolak peminjaman: " . mysqli_error($conn);
        }
    } elseif ($action === 'return') {
        $q_update = mysqli_query($conn, "UPDATE peminjaman_kendaraan SET status_approval = 'Selesai' WHERE id_peminjaman = $id_peminjaman");
        if ($q_update) {
            $_SESSION['success'] = "Kendaraan telah dikembalikan (Selesai).";
        } else {
            $_SESSION['error'] = "Gagal memproses pengembalian: " . mysqli_error($conn);
        }
    }

    header("Location: dashboard.php");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>
