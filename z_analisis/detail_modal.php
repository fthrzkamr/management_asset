<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_analisis\detail_modal.php
include('../dist/config/config.php');

$kategori = mysqli_real_escape_string($conn, $_GET['kategori'] ?? '');
$cabang = mysqli_real_escape_string($conn, $_GET['cabang'] ?? '');
$dept = mysqli_real_escape_string($conn, $_GET['dept'] ?? 'it');

$table = ($dept === 'ga') ? 'asset_ga' : 'asset_dfm';
$where = "WHERE kategori_aset = '$kategori'";
if ($cabang !== 'Semua Cabang' && !empty($cabang)) {
    $where .= " AND lokasi = '$cabang'";
}

$query = mysqli_query($conn, "SELECT * FROM $table $where ORDER BY nama_aset ASC");
?>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover" style="font-size: 13px;">
        <thead class="bg-light">
            <tr>
                <th>No</th>
                <th>Kode</th>
                <th>Nama Aset</th>
                <th>User</th>
                <th>Status (Kesehatan)</th>
                <th>Kondisi (Baru/Second)</th>
                <th>Informasi Pembelian</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($row = mysqli_fetch_assoc($query)): 
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><small><?= htmlspecialchars($row['kode_aset']) ?></small></td>
                <td><?= htmlspecialchars($row['nama_aset']) ?></td>
                <td><?= htmlspecialchars($row['user'] ?: '-') ?></td>
                <td>
                    <?php
                    $badge = 'badge-secondary';
                    if($row['kondisi'] == 'Baik') $badge = 'badge-success';
                    elseif($row['kondisi'] == 'Rusak Ringan') $badge = 'badge-warning';
                    elseif($row['kondisi'] == 'Rusak Berat') $badge = 'badge-danger';
                    ?>
                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($row['kondisi']) ?></span>
                </td>
                <td><?= htmlspecialchars($row['informasi_barang'] ?: '-') ?></td>
                <td class="text-right text-info font-weight-bold">
                    Rp <?= number_format($row['total_nilai_pengadaan'], 0, ',', '.') ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
