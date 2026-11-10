<?php
// z_admin_permissions/sync_standard_roles.php — Standarisasi Default Permission Role sesuai RBAC Standar
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
include('permissions_config.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$DEFAULT_ROLE_DESCRIPTIONS = [
    'super_admin'       => 'Akses penuh ke seluruh modul dan pengaturan sistem',
    'hr'                => 'Human Resources — Approval Aset (Global), Renovasi, Service, Peminjaman & PPX HR',
    'koordinator'       => 'Koordinator / Staff — Pengajuan Aset, Renovasi, Service & PPX',
    'GA'                => 'General Affair — Approval & Manajemen Aset GA, Renovasi, Service, PPX GA, Inventory ATK & Master Vendor',
    'it'                => 'IT — Approval & Manajemen Aset IT, Service Aset & Pengajuan PPX',
    'kacab'             => 'Kepala Cabang — Pengajuan & Approval PPX / Anggaran Cabang',
    'finance'           => 'Finance HO — Verifikasi & Pembayaran PPX, Master Anggaran, Anggaran Besar & Approval Realisasi',
    'finance_ap'        => 'Finance AP Cabang — Pengajuan Aset, Renovasi, PPX, Anggaran Bulanan & Realisasi Anggaran',
    'asmen_finance'     => 'Asisten Manager Finance — Approval PPX, Anggaran & Realisasi Anggaran',
    'manag_finance'     => 'Manager Finance — Approval PPX Manager, Master Anggaran, Kategori & Approval Anggaran Bulanan',
    'manag_operasional' => 'Manager Operasional — Approval PPX Operasional & Approval Anggaran Operasional',
    'direktur'          => 'Direktur — Approval PPX Direktur, Monitoring Anggaran & Laporan Eksekutif',
    'gudang'            => 'Gudang — Pengajuan Aset, PPX & Anggaran Bulanan',
];

$updated_roles = 0;
$cleaned_users = 0;

// 1. Standarisasi tabel roles
$roles_res = mysqli_query($conn, "SELECT id_role, nama_role, deskripsi, permissions FROM roles");
if ($roles_res) {
    $stmt_role = mysqli_prepare($conn, "UPDATE roles SET permissions = ?, deskripsi = ? WHERE id_role = ?");
    while ($r = mysqli_fetch_assoc($roles_res)) {
        $id_role   = (int)$r['id_role'];
        $nama_role = $r['nama_role'];
        $desc      = trim($r['deskripsi'] ?? '');

        if (isset($DEFAULT_ROLE_PERMISSIONS[$nama_role])) {
            $clean_perms = [];
            foreach ($DEFAULT_ROLE_PERMISSIONS[$nama_role] as $mod => $acts) {
                if (isset($PERMISSION_MODULES[$mod])) {
                    $valid = array_values(array_intersect($acts, $PERMISSION_MODULES[$mod]['actions']));
                    if (!empty($valid)) {
                        $clean_perms[$mod] = $valid;
                    }
                }
            }
            if ($desc === '' && isset($DEFAULT_ROLE_DESCRIPTIONS[$nama_role])) {
                $desc = $DEFAULT_ROLE_DESCRIPTIONS[$nama_role];
            } elseif (isset($DEFAULT_ROLE_DESCRIPTIONS[$nama_role])) {
                $desc = $DEFAULT_ROLE_DESCRIPTIONS[$nama_role];
            }
        } else {
            // Role tambahan kustom: bersihkan key modul/aksi yang sudah tidak dipakai
            $existing = json_decode($r['permissions'] ?? '{}', true) ?: [];
            $clean_perms = [];
            foreach ($existing as $mod => $acts) {
                if (isset($PERMISSION_MODULES[$mod]) && is_array($acts)) {
                    $valid = array_values(array_intersect($acts, $PERMISSION_MODULES[$mod]['actions']));
                    if (!empty($valid)) {
                        $clean_perms[$mod] = $valid;
                    }
                }
            }
        }

        $json = json_encode($clean_perms, JSON_UNESCAPED_UNICODE);
        mysqli_stmt_bind_param($stmt_role, 'ssi', $json, $desc, $id_role);
        if (mysqli_stmt_execute($stmt_role)) {
            $updated_roles++;
        }
    }
    mysqli_stmt_close($stmt_role);
}

// 2. Bersihkan hanya key usang pada user_dfm.custom_permissions (TANPA menimpa hak akses custom user)
$users_res = mysqli_query($conn, "SELECT id_user, custom_permissions FROM user_dfm WHERE custom_permissions IS NOT NULL AND custom_permissions != '' AND custom_permissions != '{}'");
if ($users_res) {
    $stmt_user = mysqli_prepare($conn, "UPDATE user_dfm SET custom_permissions = ? WHERE id_user = ?");
    while ($u = mysqli_fetch_assoc($users_res)) {
        $uid = (int)$u['id_user'];
        $cp  = json_decode($u['custom_permissions'], true);
        if (!is_array($cp)) continue;

        $clean_cp = [];
        $changed  = false;
        foreach ($cp as $mod => $acts) {
            if (!isset($PERMISSION_MODULES[$mod]) || !is_array($acts)) {
                $changed = true;
                continue;
            }
            $valid = array_values(array_intersect($acts, $PERMISSION_MODULES[$mod]['actions']));
            if ($valid !== $acts) {
                $changed = true;
            }
            if (!empty($valid)) {
                $clean_cp[$mod] = $valid;
            }
        }

        if ($changed) {
            $new_json = empty($clean_cp) ? null : json_encode($clean_cp, JSON_UNESCAPED_UNICODE);
            mysqli_stmt_bind_param($stmt_user, 'si', $new_json, $uid);
            if (mysqli_stmt_execute($stmt_user)) {
                $cleaned_users++;
            }
        }
    }
    mysqli_stmt_close($stmt_user);
}

$_SESSION['success'] = "Berhasil menstandarisasi default permission untuk <strong>$updated_roles role</strong> sesuai standar modul terbaru"
    . ($cleaned_users > 0 ? " dan membersihkan key usang pada <strong>$cleaned_users user</strong>" : "")
    . " (hak akses custom per-user tetap aman terjaga).";

header('Location: dashboard.php');
exit();

