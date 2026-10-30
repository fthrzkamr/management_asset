<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('user_management', 'create', 'dashboard.php');

// Ambil data role, divisi, departemen, dan cabang untuk dropdown
$roles = mysqli_query($conn, "SELECT * FROM roles ORDER BY nama_role");
$divisi = mysqli_query($conn, "SELECT * FROM divisi ORDER BY nama_divisi");
$departemen = mysqli_query($conn, "SELECT * FROM departemen ORDER BY nama_departemen");
$cabang = mysqli_query($conn, "SELECT * FROM master_cabang ORDER BY nama_cabang");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_user = trim($_POST['nama_user']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role_id = $_POST['role_id'];
    $divisi_id = $_POST['divisi_id'];
    $departemen_id = $_POST['departemen_id'];
    $status = $_POST['status'] ?? 'Aktif';

    // Validasi sederhana
    if ($nama_user && $username && $password && $role_id) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = mysqli_query($conn, "INSERT INTO user_dfm (nama_user, username, password, role_id, divisi, departemen, status) 
            VALUES ('$nama_user', '$username', '$password_hash', '$role_id', '$divisi_id', '$departemen_id', '$status')");
        if ($insert) {
            $_SESSION['success'] = "User berhasil ditambahkan!";
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Gagal menambah user!";
        }
    } else {
        $error = "Semua field wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah User</title>
</head>
<body>
    <div class="main-wrapper">
        <?php include('../layouts/navbar.php'); ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Tambah User Baru</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">User</a></li>
                                <li class="breadcrumb-item active">Tambah User</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="insert.php">
                                    <div class="form-group">
                                        <label>Nama User <span class="text-danger">*</span></label>
                                        <input type="text" name="nama_user" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Username <span class="text-danger">*</span></label>
                                        <input type="text" name="username" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Password <span class="text-danger">*</span></label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Role <span class="text-danger">*</span></label>
                                        <select name="id_role" class="form-control" required>
                                            <option value="">-- Pilih Role --</option>
                                            <?php mysqli_data_seek($roles, 0); while ($r = mysqli_fetch_assoc($roles)): ?>
                                                <option value="<?= $r['id_role'] ?>"><?= htmlspecialchars($r['nama_role']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Divisi</label>
                                        <select name="divisi" class="form-control">
                                            <option value="">-- Pilih Divisi --</option>
                                            <?php mysqli_data_seek($divisi, 0); while ($d = mysqli_fetch_assoc($divisi)): ?>
                                                <option value="<?= $d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Departemen</label>
                                        <select name="departemen" class="form-control">
                                            <option value="">-- Pilih Departemen --</option>
                                            <?php mysqli_data_seek($departemen, 0); while ($dep = mysqli_fetch_assoc($departemen)): ?>
                                                <option value="<?= $dep['id_departemen'] ?>"><?= htmlspecialchars($dep['nama_departemen']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Cabang</label>
                                        <select name="id_cabang" class="form-control">
                                            <option value="">-- Pilih Cabang --</option>
                                            <?php mysqli_data_seek($cabang, 0); while ($cab = mysqli_fetch_assoc($cabang)): ?>
                                                <option value="<?= $cab['id_cabang'] ?>"><?= htmlspecialchars($cab['nama_cabang']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Jabatan</label>
                                        <select name="jabatan" class="form-control">
                                            <option value="">-- Pilih Jabatan --</option>
                                            <option value="Direktur">Direktur</option>
                                            <option value="HR">HR</option>
                                            <option value="Manager">Manager</option>
                                            <option value="Supervisor">Supervisor</option>
                                            <option value="Kepala Cabang">Kepala Cabang</option>
                                            <option value="Staff">Staff</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Koordinator">Koordinator</option>
                                            <option value="Team Leader">Team Leader</option>
                                            <option value="Operasional">Operasional</option>
                                            <option value="Bisnis">Bisnis</option>
                                            <option value="Manager Finance">Manager Finance</option>
                                            <option value="Finance">Finance</option>
                                            <option value="Finance AP">Finance AP</option>
                                            <option value="General Affairs">General Affairs</option>
                                            <option value="Staff IT">Staff IT</option>
                                            <option value="Asmen Finance">Asmen Finance</option>
                                        </select>
                                    </div>
                                    <div class="text-right">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                                        <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('../layouts/footer.php'); ?>
            </div>
        </div>
    </div>
</body>
</html>