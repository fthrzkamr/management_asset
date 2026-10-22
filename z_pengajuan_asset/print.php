<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\print.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission
if (!$auth->hasPermission('pengajuan_asset', 'read') && 
    !$auth->hasPermission('approval_asset_it', 'read') && 
    !$auth->hasPermission('approval_asset_ga', 'read')) {
    $auth->requirePermission('pengajuan_asset', 'read');
}

$no_pengajuan = $_GET['id'] ?? $_GET['no_pengajuan'] ?? '';

if (!empty($no_pengajuan)) {
    // PRINT SINGLE ASSET REQUEST DETAIL
    $no_pengajuan = trim($no_pengajuan);
    $no_pengajuan_safe = mysqli_real_escape_string($conn, $no_pengajuan);

    $query = "SELECT 
        ar.*,
        ka.kategori_asset,
        ka.sub_kategori,
        la.lokasi_asset,
        u.nama_user as nama_pengaju,
        ua.nama_user as nama_approver,
        d.nama_divisi
    FROM asset_request ar
    LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
    LEFT JOIN lokasi_asset la ON ar.lokasi_penggunaan = la.id_lokasi
    LEFT JOIN user_dfm u ON ar.user_id = u.id_user
    LEFT JOIN user_dfm ua ON ar.approved_by = ua.id_user
    LEFT JOIN divisi d ON ar.divisi = d.id_divisi
    WHERE ar.no_pengajuan = '$no_pengajuan_safe' AND (ar.is_deleted IS NULL OR ar.is_deleted = 0)
    LIMIT 1";

    $result = mysqli_query($conn, $query);

    if (!$result || mysqli_num_rows($result) === 0) {
        echo "<script>alert('Data pengajuan asset tidak ditemukan!'); window.close();</script>";
        exit();
    }

    $data = mysqli_fetch_assoc($result);
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Print Pengajuan Asset - <?= htmlspecialchars($no_pengajuan) ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }
                @page {
                    margin: 1.2cm;
                    size: A4;
                }
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                margin: 20px;
                color: #000;
            }
            .header {
                text-align: center;
                margin-bottom: 25px;
                border-bottom: 2px solid #000;
                padding-bottom: 12px;
            }
            .company-name {
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .document-title {
                font-size: 15px;
                font-weight: bold;
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .print-info {
                font-size: 10px;
                color: #555;
            }
            .info-section {
                margin-bottom: 20px;
            }
            .section-title {
                font-size: 13px;
                font-weight: bold;
                margin-bottom: 10px;
                padding: 6px 8px;
                border-bottom: 1px solid #ccc;
                background-color: #f5f5f5;
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }
            .info-table td {
                border: 1px solid #000;
                padding: 8px;
                vertical-align: top;
            }
            .info-table td table td {
                border: none;
                padding: 4px 6px;
            }
            .info-table td table td:first-child {
                width: 150px;
                font-weight: bold;
                padding-right: 8px;
            }
            .text-content {
                background: #fdfdfd;
                padding: 8px 10px;
                border: 1px solid #ccc;
                border-radius: 3px;
                min-height: 40px;
                font-size: 11px;
                line-height: 1.4;
                white-space: pre-wrap;
            }
            .text-center {
                text-align: center;
            }
            .text-right {
                text-align: right;
            }
            .text-bold {
                font-weight: bold;
            }
            .btn-print {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                margin: 5px;
                font-weight: bold;
            }
            .btn-print:hover {
                background-color: #0056b3;
            }
            .btn-close {
                background-color: #6c757d;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                margin: 5px;
                font-weight: bold;
            }
            .btn-close:hover {
                background-color: #545b62;
            }
            .print-buttons {
                text-align: center;
                margin-bottom: 20px;
            }
            .badge {
                display: inline-block;
                padding: 3px 6px;
                font-size: 10px;
                font-weight: bold;
                border-radius: 3px;
                border: 1px solid #000;
            }
            .signature-section {
                margin-top: 40px;
                width: 100%;
                border-collapse: collapse;
            }
            .signature-section td {
                width: 50%;
                text-align: center;
                vertical-align: top;
                border: none;
                padding: 10px;
            }
            .signature-space {
                height: 75px;
            }
        </style>
    </head>
    <body>
        <div class="print-buttons no-print">
            <button class="btn-print" onclick="window.print();">
                <i class="fas fa-print"></i> Print Pengajuan
            </button>
            <button type="button" class="btn-close" onclick="window.close();">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>

        <div class="header">
            <div class="company-name">DF GROUP</div>
            <div class="document-title">FORM PENGAJUAN ASSET</div>
            <div class="print-info">No. Dokumen: <?= htmlspecialchars($data['no_pengajuan']) ?></div>
        </div>

        <div class="info-section">
            <div class="section-title">INFORMASI PENGAJU</div>
            <table class="info-table">
                <tr>
                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td>Nama Pengaju :</td>
                                <td><?= htmlspecialchars($data['nama_pengaju']) ?></td>
                            </tr>
                            <tr>
                                <td>Nama PIC / User :</td>
                                <td><?= htmlspecialchars($data['nama_pic']) ?></td>
                            </tr>
                            <tr>
                                <td>Jabatan :</td>
                                <td><?= htmlspecialchars($data['jabatan']) ?></td>
                            </tr>
                            <tr>
                                <td>Divisi :</td>
                                <td><?= htmlspecialchars($data['nama_divisi'] ?? $data['divisi']) ?></td>
                            </tr>
                        </table>
                    </td>
                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td>Tanggal Pengajuan :</td>
                                <td><?= date('d/m/Y H:i', strtotime($data['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td>Tanggal Dibutuhkan :</td>
                                <td><?= date('d/m/Y', strtotime($data['tanggal_dibutuhkan'])) ?></td>
                            </tr>
                            <tr>
                                <td>Prioritas :</td>
                                <td>
                                    <span class="badge">
                                        <?= htmlspecialchars($data['tingkat_kepentingan']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Status Pengajuan :</td>
                                <td>
                                    <span class="badge">
                                        <?= htmlspecialchars($data['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="info-section">
            <div class="section-title">DETAIL ASSET YANG DIAJUKAN</div>
            <table class="info-table">
                <tr>
                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td>Kategori Asset :</td>
                                <td><?= htmlspecialchars($data['kategori_asset']) ?> (<?= htmlspecialchars($data['sub_kategori']) ?>)</td>
                            </tr>
                            <tr>
                                <td>Jenis Pengadaan :</td>
                                <td><?= htmlspecialchars($data['jenis_pengadaan']) ?></td>
                            </tr>
                            <tr>
                                <td>Jumlah Barang :</td>
                                <td class="text-bold"><?= htmlspecialchars($data['jumlah']) ?> <?= htmlspecialchars($data['satuan']) ?></td>
                            </tr>
                            <tr>
                                <td>Estimasi Harga :</td>
                                <td class="text-bold">
                                    <?php if ($data['estimasi_harga'] > 0): ?>
                                        Rp <?= number_format($data['estimasi_harga'], 0, ',', '.') ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td>Lokasi Penggunaan :</td>
                                <td><?= htmlspecialchars($data['lokasi_asset']) ?></td>
                            </tr>
                            <tr>
                                <td>Detail Lokasi :</td>
                                <td><?= htmlspecialchars($data['detail_lokasi'] ?: '-') ?></td>
                            </tr>
                            <tr>
                                <td>Status Distribusi :</td>
                                <td>
                                    <span class="badge">
                                        <?= htmlspecialchars($data['status_pendistribusian'] ?: 'Belum diproses') ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="info-section" style="page-break-inside: avoid;">
            <div class="section-title">DESKRIPSI & SPESIFIKASI</div>
            <table class="info-table" style="border: none;">
                <tr style="border: none;">
                    <td style="width: 50%; border: none; padding: 0 10px 0 0;">
                        <div class="text-bold" style="margin-bottom: 5px;">Deskripsi Asset:</div>
                        <div class="text-content"><?= htmlspecialchars($data['deskripsi_asset']) ?></div>
                    </td>
                    <td style="width: 50%; border: none; padding: 0 0 0 10px;">
                        <div class="text-bold" style="margin-bottom: 5px;">Spesifikasi Merek/Model:</div>
                        <div class="text-content"><?= htmlspecialchars($data['spesifikasi_merek'] ?: '-') ?></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="info-section" style="page-break-inside: avoid;">
            <div class="section-title">ALASAN PENGAJUAN & CATATAN</div>
            <table class="info-table" style="border: none;">
                <tr style="border: none;">
                    <td style="width: 50%; border: none; padding: 0 10px 0 0;">
                        <div class="text-bold" style="margin-bottom: 5px;">Alasan Pengajuan:</div>
                        <div class="text-content"><?= htmlspecialchars($data['alasan_pengajuan']) ?></div>
                    </td>
                    <td style="width: 50%; border: none; padding: 0 0 0 10px;">
                        <div class="text-bold" style="margin-bottom: 5px;">Catatan Tambahan:</div>
                        <div class="text-content"><?= htmlspecialchars($data['catatan_tambahan'] ?: '-') ?></div>
                    </td>
                </tr>
            </table>
        </div>

        <?php if ($data['status'] != 'Pending'): ?>
        <div class="info-section" style="page-break-inside: avoid; margin-top: 25px;">
            <div class="section-title">STATUS PERSETUJUAN (APPROVAL)</div>
            <table class="info-table">
                <tr>
                    <td style="width: 25%; font-weight: bold;">Diproses Oleh</td>
                    <td><?= htmlspecialchars($data['nama_approver'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Tanggal Approval</td>
                    <td><?= $data['approved_at'] ? date('d/m/Y H:i', strtotime($data['approved_at'])) : '-' ?></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Catatan Approval</td>
                    <td>
                        <div style="font-size: 11px; white-space: pre-wrap;"><?= htmlspecialchars($data['catatan_approval'] ?: '-') ?></div>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        </script>
    </body>
    </html>
<?php
} else {
    // PRINT ALL ASSET REQUESTS SUMMARY
    // (Aksi print global dari dashboard / filter jika diperlukan)
    
    $isGlobalApprover = $auth->hasPermission('pengajuan_asset', 'approve');
    $canApproveIT = $auth->hasPermission('approval_asset_it', 'approve');
    $canApproveGA = $auth->hasPermission('approval_asset_ga', 'approve');
    $canApproveAsset = $isGlobalApprover || $canApproveIT || $canApproveGA;

    $where_conditions = [];
    $user_id = (int)$_SESSION['id_user'];
    $own_request_condition = "ar.user_id = $user_id";

    if ($auth->isSuperAdmin() || $auth->isHr()) {
        // Super Admin & HR see all
    } elseif ($canApproveAsset) {
        $role_filters = [];
        if ($canApproveIT && !$canApproveGA) {
            $role_filters[] = "ka.sub_kategori = 'IT'";
        } elseif ($canApproveGA && !$canApproveIT) {
            $role_filters[] = "ka.sub_kategori = 'GA'";
        }
        
        if (!empty($role_filters)) {
            if (!$isGlobalApprover) {
                $where_conditions[] = "(" . implode(" OR ", $role_filters) . " OR $own_request_condition)";
            }
        } else {
            if (!$isGlobalApprover) {
                $where_conditions[] = $own_request_condition;
            }
        }
    } else {
        $where_conditions[] = $own_request_condition;
    }

    $where_conditions[] = "(ar.is_deleted IS NULL OR ar.is_deleted = 0)";
    $whereClause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    $query = "SELECT ar.*, ka.kategori_asset, ka.sub_kategori, u.nama_user as nama_pengaju, d.nama_divisi
             FROM asset_request ar
             LEFT JOIN kategori_asset ka ON ar.kategori_asset = ka.id_kategori
             LEFT JOIN user_dfm u ON ar.user_id = u.id_user
             LEFT JOIN divisi d ON ar.divisi = d.id_divisi
             $whereClause
             ORDER BY ar.tanggal_pengajuan DESC";

    $result = mysqli_query($conn, $query);
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan Pengajuan Asset</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }
                @page {
                    margin: 0.8cm;
                    size: A4 landscape;
                }
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                line-height: 1.3;
                margin: 15px;
                color: #000;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #000;
                padding-bottom: 12px;
            }
            .company-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 4px;
            }
            .document-title {
                font-size: 13px;
                font-weight: bold;
                margin-bottom: 6px;
            }
            .print-info {
                font-size: 9px;
                color: #555;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin-top: 10px;
            }
            th, td {
                border: 1px solid #000;
                padding: 5px;
                text-align: left;
            }
            th {
                font-weight: bold;
                background-color: #f2f2f2;
                text-align: center;
            }
            .center {
                text-align: center;
            }
            .right {
                text-align: right;
            }
            .btn-print {
                background-color: #007bff;
                color: white;
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                margin: 5px;
                font-weight: bold;
            }
            .btn-close {
                background-color: #6c757d;
                color: white;
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                margin: 5px;
                font-weight: bold;
            }
            .print-buttons {
                text-align: center;
                margin-bottom: 15px;
            }
            .badge-status {
                display: inline-block;
                padding: 2px 4px;
                font-size: 9px;
                font-weight: bold;
                border-radius: 3px;
                border: 1px solid #777;
            }
        </style>
    </head>
    <body>
        <div class="print-buttons no-print">
            <button class="btn-print" onclick="window.print();">
                <i class="fas fa-print"></i> Print Laporan
            </button>
            <button type="button" class="btn-close" onclick="window.close();">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>

        <div class="header">
            <div class="company-name">DF GROUP</div>
            <div class="document-title">LAPORAN PENGAJUAN ASSET</div>
            <div class="print-info">Dicetak pada: <?= date('d/m/Y H:i:s') ?> oleh: <?= htmlspecialchars($_SESSION['nama_user']) ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="12%">No. Pengajuan</th>
                    <th width="10%">Tanggal</th>
                    <th width="10%">Pengaju</th>
                    <th width="10%">Divisi</th>
                    <th width="10%">PIC / User</th>
                    <th width="12%">Kategori Asset</th>
                    <th>Deskripsi Barang</th>
                    <th width="6%">Jumlah</th>
                    <th width="10%">Estimasi Harga</th>
                    <th width="8%">Status</th>
                    <th width="10%">Distribusi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $total_budget = 0;
                while ($row = mysqli_fetch_assoc($result)): 
                    $total_budget += $row['estimasi_harga'];
                ?>
                    <tr>
                        <td class="center"><?= $no++ ?></td>
                        <td class="text-bold"><?= htmlspecialchars($row['no_pengajuan']) ?></td>
                        <td class="center"><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></td>
                        <td><?= htmlspecialchars($row['nama_pengaju']) ?></td>
                        <td><?= htmlspecialchars($row['nama_divisi'] ?? $row['divisi']) ?></td>
                        <td><?= htmlspecialchars($row['nama_pic']) ?></td>
                        <td>
                            <?= htmlspecialchars($row['kategori_asset']) ?>
                            <span class="badge-status" style="font-size: 8px;"><?= htmlspecialchars($row['sub_kategori']) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars(substr($row['deskripsi_asset'], 0, 50)) ?></strong>
                            <?= strlen($row['deskripsi_asset']) > 50 ? '...' : '' ?>
                        </td>
                        <td class="center"><?= htmlspecialchars($row['jumlah']) ?> <?= htmlspecialchars($row['satuan']) ?></td>
                        <td class="right">
                            <?= $row['estimasi_harga'] > 0 ? 'Rp ' . number_format($row['estimasi_harga'], 0, ',', '.') : '-' ?>
                        </td>
                        <td class="center">
                            <span class="badge-status"><?= htmlspecialchars($row['status']) ?></span>
                        </td>
                        <td class="center">
                            <?= htmlspecialchars($row['status_pendistribusian'] ?: '-') ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <tr>
                    <td colspan="8" class="right text-bold">TOTAL ESTIMASI BUDGET (SEMUA):</td>
                    <td class="right text-bold">Rp <?= number_format($total_budget, 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 30px; font-size: 9px; color: #666;">
            DF GROUP Asset Management System
        </div>

        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        </script>
    </body>
    </html>
<?php
}
mysqli_close($conn);
?>
