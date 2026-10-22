<?php
// filepath: c:\xampp\htdocs\asset_dfm\pengajuan_asset\detail.php
include('../dist/config/config.php');


if (!isset($_SESSION['id_user']) || !isset($_GET['no_pengajuan'])) {
    exit('Access denied');
}

$no_pengajuan = mysqli_real_escape_string($conn, $_GET['no_pengajuan']);

$query = "SELECT 
    ar.*,
    ka.kategori_asset,
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
WHERE ar.no_pengajuan = '$no_pengajuan'";

$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    exit('Data tidak ditemukan');
}
?>

<style>
.detail-table td {
    padding: 8px;
    border: none;
    vertical-align: top;
}
.detail-section {
    margin-bottom: 20px;
}
.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 5px;
}
.text-content {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 4px;
    border-left: 3px solid #e9ecef;
    font-size: 13px;
    line-height: 1.5;
}
</style>

<div class="text-right mb-3">
    <a href="print.php?id=<?= urlencode($data['no_pengajuan']) ?>" target="_blank" class="btn btn-info btn-sm">
        <i class="fas fa-print fa-sm"></i> Print Form Pengajuan
    </a>
</div>

<div class="detail-section">
    <div class="section-title">Informasi Pengajuan</div>
    <div class="row">
        <div class="col-md-6">
            <table class="table table-sm detail-table">
                <tr>
                    <td width="35%">No. Pengajuan</td>
                    <td>: <strong><?= htmlspecialchars($data['no_pengajuan']) ?></strong></td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>: <span class="badge badge-<?= $data['status'] == 'Pending' ? 'warning' : ($data['status'] == 'Disetujui' ? 'success' : 'danger') ?>"><?= htmlspecialchars($data['status']) ?></span></td>
                </tr>
                <tr>
                    <td>Tanggal Pengajuan</td>
                    <td>: <?= date('d/m/Y H:i', strtotime($data['created_at'])) ?></td>
                </tr>
                <tr>
                    <td>Tanggal Dibutuhkan</td>
                    <td>: <?= date('d/m/Y', strtotime($data['tanggal_dibutuhkan'])) ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm detail-table">
                <tr>
                    <td width="35%">Nama PIC</td>
                    <td>: <?= htmlspecialchars($data['nama_pic']) ?></td>
                </tr>
                <tr>
                    <td>Jabatan</td>
                    <td>: <?= htmlspecialchars($data['jabatan']) ?></td>
                </tr>
                <tr>
                    <td>Divisi</td>
                    <td>: <?= htmlspecialchars($data['nama_divisi'] ?? $data['divisi']) ?></td>
                </tr>
                <tr>
                    <td>Prioritas</td>
                    <td>: <span class="badge badge-<?= $data['tingkat_kepentingan'] == 'Urgent' ? 'danger' : ($data['tingkat_kepentingan'] == 'Tinggi' ? 'warning' : 'secondary') ?>"><?= htmlspecialchars($data['tingkat_kepentingan']) ?></span></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="detail-section">
    <div class="section-title">Detail Asset</div>
    <div class="row">
        <div class="col-md-6">
            <table class="table table-sm detail-table">
                <tr>
                    <td width="35%">Kategori</td>
                    <td>: <?= htmlspecialchars($data['kategori_asset']) ?></td>
                </tr>
                <tr>
                    <td>Jenis Pengadaan</td>
                    <td>: <?= htmlspecialchars($data['jenis_pengadaan']) ?></td>
                </tr>
                <tr>
                    <td>Jumlah</td>
                    <td>: <?= $data['jumlah'] ?> <?= htmlspecialchars($data['satuan']) ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm detail-table">
                <tr>
                    <td width="35%">Lokasi</td>
                    <td>: <?= htmlspecialchars($data['lokasi_asset']) ?></td>
                </tr>
                <tr>
                    <td>Detail Lokasi</td>
                    <td>: <?= htmlspecialchars($data['detail_lokasi'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td>Estimasi Harga</td>
                    <td>: <?php if ($data['estimasi_harga'] > 0): ?>
                            Rp <?= number_format($data['estimasi_harga'], 0, ',', '.') ?>
                         <?php else: ?>
                            <span class="text-muted">-</span>
                         <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="detail-section">
    <div class="section-title">Deskripsi Asset</div>
    <div class="text-content">
        <?= nl2br(htmlspecialchars($data['deskripsi_asset'])) ?>
    </div>
</div>

<?php if ($data['spesifikasi_merek']): ?>
<div class="detail-section">
    <div class="section-title">Spesifikasi Merek/Model</div>
    <div class="text-content">
        <?= nl2br(htmlspecialchars($data['spesifikasi_merek'])) ?>
    </div>
</div>
<?php endif; ?>

<div class="detail-section">
    <div class="section-title">Alasan Pengajuan</div>
    <div class="text-content">
        <?= nl2br(htmlspecialchars($data['alasan_pengajuan'])) ?>
    </div>
</div>

<?php if ($data['catatan_tambahan']): ?>
<div class="detail-section">
    <div class="section-title">Catatan Tambahan</div>
    <div class="text-content">
        <?= nl2br(htmlspecialchars($data['catatan_tambahan'])) ?>
    </div>
</div>
<?php endif; ?>

<?php if ($data['file_attachment']): ?>
<div class="detail-section">
    <div class="section-title">File Lampiran</div>
    <a href="../uploads/pengajuan/<?= htmlspecialchars($data['file_attachment']) ?>" 
       class="btn btn-outline-secondary btn-sm" target="_blank">
        <i class="fas fa-download"></i> Download File
    </a>
</div>
<?php endif; ?>

<?php if ($data['status'] != 'Pending'): ?>
<div class="detail-section">
    <div class="section-title">Informasi Approval</div>
    <table class="table table-sm detail-table">
        <tr>
            <td width="25%">Diproses oleh</td>
            <td>: <?= htmlspecialchars($data['nama_approver'] ?: '-') ?></td>
        </tr>
        <tr>
            <td>Tanggal Approval</td>
            <td>: <?= $data['approved_at'] ? date('d/m/Y H:i', strtotime($data['approved_at'])) : '-' ?></td>
        </tr>
        <?php if ($data['catatan_approval']): ?>
        <tr>
            <td>Catatan</td>
            <td>: 
                <div class="text-content mt-1">
                    <?= nl2br(htmlspecialchars($data['catatan_approval'])) ?>
                </div>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>
<?php endif; ?>

<!-- Setelah informasi status distribusi -->
<div class="row mb-2">
    <div class="col-md-4">
        <h6 class="text-secondary">Status PPX:</h6>
    </div>
    <div class="col-md-8">
        <?php
        // Query untuk cek apakah asset sudah dibuatkan PPX
        $check_ppx_query = "SELECT no_ppx, status FROM ppx_request WHERE no_pengajuan_asset = '" . $data['no_pengajuan'] . "' LIMIT 1";
        $check_ppx_result = mysqli_query($conn, $check_ppx_query);
        
        if (mysqli_num_rows($check_ppx_result) > 0) {
            $ppx_data = mysqli_fetch_assoc($check_ppx_result);
            ?>
            <h6>
                <span class="badge badge-success">
                    <i class="fas fa-check-circle"></i> Sudah Dibuatkan PPX
                </span>
            </h6>
            <small>
                No. PPX: 
                <a href="../z_pengajuan_ppx/detail.php?no_ppx=<?= $ppx_data['no_ppx'] ?>" 
                   class="text-primary" target="_blank">
                    <?= htmlspecialchars($ppx_data['no_ppx']) ?> (<?= htmlspecialchars($ppx_data['status']) ?>)
                </a>
            </small>
        <?php } else { ?>
            <?php if ($data['status'] == 'Disetujui' && $data['status_pendistribusian'] == 'Sudah diorder') { ?>
                <h6>
                    <span class="badge badge-warning">
                        <i class="fas fa-file-invoice-dollar"></i> Belum Dibuatkan PPX
                    </span>
                </h6>
                <small>
                    <a href="../z_pengajuan_ppx/add.php?asset=<?= $data['no_pengajuan'] ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus"></i> Buat PPX Sekarang
                    </a>
                </small>
            <?php } else { ?>
                <h6>
                    <span class="badge badge-secondary">
                        <i class="fas fa-times-circle"></i> Belum Bisa Dibuat PPX
                    </span>
                </h6>
                <small class="text-muted">
                    <?php if ($data['status'] != 'Disetujui') { ?>
                        Pengajuan asset harus disetujui terlebih dahulu.
                    <?php } elseif ($data['status_pendistribusian'] != 'Sudah diorder') { ?>
                        Status distribusi harus "Sudah diorder" terlebih dahulu.
                    <?php } ?>
                </small>
            <?php } ?>
        <?php } ?>
    </div>
</div>