<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Ambil no_ppx dari parameter
$no_ppx = $_GET['no_ppx'] ?? '';

if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data PPX header
$query_header = "SELECT 
    pr.no_ppx,
    pr.tanggal_pengajuan,
    pr.nama_pic,
    pr.jabatan,
    pr.divisi,
    d.nama_divisi,
    pr.departemen,
    dep.nama_departemen,
    pr.cabang,
    pr.nama_bank,
    pr.nama_rek,
    pr.no_rek,
    pr.nominal_ppx,
    pr.uang_muka,
    pr.status,
    pr.user_id,
    u.nama_user
FROM ppx_request pr
LEFT JOIN user_dfm u ON pr.user_id = u.id_user
LEFT JOIN divisi d ON pr.divisi = d.id_divisi
LEFT JOIN departemen dep ON pr.departemen = dep.id_departemen
WHERE pr.no_ppx = ? AND pr.user_id = ?";

$stmt = mysqli_prepare($conn, $query_header);
mysqli_stmt_bind_param($stmt, 'si', $no_ppx, $_SESSION['id_user']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan atau Anda tidak memiliki akses!';
    header("Location: dashboard.php");
    exit();
}

$ppx_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Cek status harus Uang Muka
if ($ppx_data['status'] !== 'Uang Muka') {
    $_SESSION['error'] = 'PPX ini tidak dalam status Uang Muka!';
    header("Location: dashboard.php");
    exit();
}

// Ambil daftar akun untuk select No. Akun pada realisasi
$akun_options_html = '';
$qAkun = "SELECT no_akun, nama_akun FROM master_akun ORDER BY no_akun";
$rAkun = mysqli_query($conn, $qAkun);
if ($rAkun) {
    while ($rowA = mysqli_fetch_assoc($rAkun)) {
        $no = htmlspecialchars($rowA['no_akun']);
        $nama = htmlspecialchars($rowA['nama_akun']);
        $akun_options_html .= "<option value=\"{$no}\">{$no} - {$nama}</option>";
    }
}

// Ambil detail items
$query_detail = "SELECT 
    no_ppx_detail,
    no_akun,
    rincian,
    jumlah,
    satuan,
    harga_satuan,
    total_harga,
    deskripsi
FROM ppx_request_detail 
WHERE no_ppx = ? 
ORDER BY no_ppx_detail";

$stmt_detail = mysqli_prepare($conn, $query_detail);
mysqli_stmt_bind_param($stmt_detail, 's', $no_ppx);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

$items = [];
$total_nominal = 0;
$items_map = []; // Map no_ppx_detail => item data
while ($row = mysqli_fetch_assoc($result_detail)) {
    $items[] = $row;
    $total_nominal += $row['total_harga'];
    $items_map[$row['no_ppx_detail']] = $row;
}
mysqli_stmt_close($stmt_detail);

// Query untuk mendapatkan item yang SUDAH direalisasikan sebelumnya
$query_realisasi = "SELECT detail_barang, total_realisasi FROM ppx_realisasi WHERE no_ppx = ? ORDER BY created_at";
$stmt_realisasi = mysqli_prepare($conn, $query_realisasi);
mysqli_stmt_bind_param($stmt_realisasi, 's', $no_ppx);
mysqli_stmt_execute($stmt_realisasi);
$result_realisasi = mysqli_stmt_get_result($stmt_realisasi);

// Kumpulkan semua item yang sudah direalisasikan berdasarkan no_ppx_detail
$paid_items = []; // Array of no_ppx_detail yang sudah dibayar
$total_sudah_direalisasi = 0;

while ($row_real = mysqli_fetch_assoc($result_realisasi)) {
    $total_sudah_direalisasi += floatval($row_real['total_realisasi']);

    if (!empty($row_real['detail_barang'])) {
        $detail_barang = json_decode($row_real['detail_barang'], true);
        if (is_array($detail_barang)) {
            foreach ($detail_barang as $item) {
                $detail_id = $item['no_ppx_detail'] ?? null;
                if ($detail_id) {
                    // Tandai item ini sudah dibayar
                    $paid_items[$detail_id] = true;
                }
            }
        }
    }
}
mysqli_stmt_close($stmt_realisasi);

// Hitung sisa yang belum direalisasi
$sisa_belum_direalisasi = $total_nominal - $total_sudah_direalisasi;

$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Realisasi Uang Muka - <?= htmlspecialchars($no_ppx) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    }
    ?>

    <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"> -->

    <style>
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 20px;
        }

        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .file-preview-item {
            position: relative;
            padding: 8px 12px;
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            font-size: 12px;
        }

        .required-field::after {
            content: " *";
            color: red;
        }

        .detail-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 10px;
            position: relative;
        }

        .btn-remove-item {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            right: 8px;
            z-index: 10;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            padding: 0;
            font-size: 11px;
        }

        .detail-item .form-control-sm {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }

        .detail-item label.small {
            margin-bottom: 0.25rem;
            font-size: 0.8rem;
        }

        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php
        if ($layout_dir && file_exists($layout_dir . '/navbar.php')) {
            include $layout_dir . '/navbar.php';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="page-title">
                                <i class="fas fa-file-invoice-dollar"></i> Realisasi Uang Muka
                            </h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan PPX</a></li>
                                <li class="breadcrumb-item active">Realisasi</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="dashboard.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= $success_message ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <form action="realisasi_submit.php" method="POST" enctype="multipart/form-data" id="realisasiForm">
                    <input type="hidden" name="no_ppx" value="<?= htmlspecialchars($no_ppx) ?>">

                    <!-- Info PPX -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="fas fa-info-circle"></i> Informasi PPX
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row mb-2">
                                    <dt class="col-sm-5">No. PPX:</dt>
                                    <dd class="col-sm-7 font-weight-bold"><?= htmlspecialchars($ppx_data['no_ppx']) ?></dd>

                                    <dt class="col-sm-5">Tanggal Pengajuan:</dt>
                                    <dd class="col-sm-7"><?= date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) ?></dd>

                                    <dt class="col-sm-5">Nama PIC:</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($ppx_data['nama_pic']) ?></dd>

                                    <dt class="col-sm-5">Jabatan:</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($ppx_data['jabatan']) ?></dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row mb-2">
                                    <dt class="col-sm-5">Divisi:</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($ppx_data['nama_divisi'] ?? $ppx_data['divisi']) ?></dd>

                                    <dt class="col-sm-5">Cabang:</dt>
                                    <dd class="col-sm-7"><?= htmlspecialchars($ppx_data['cabang']) ?></dd>

                                    <dt class="col-sm-5">Total Pengajuan:</dt>
                                    <dd class="col-sm-7 font-weight-bold text-primary">
                                        Rp <?= number_format($total_nominal, 0, ',', '.') ?>
                                    </dd>

                                    <dt class="col-sm-5">Uang Muka Diterima:</dt>
                                    <dd class="col-sm-7 font-weight-bold text-success">
                                        Rp <?= number_format($ppx_data['uang_muka'], 0, ',', '.') ?>
                                    </dd>

                                    <?php if ($total_sudah_direalisasi > 0):
                                        $sisa_uang_muka = $ppx_data['uang_muka'] - $total_sudah_direalisasi;
                                    ?>
                                        <dt class="col-sm-5">Sudah Direalisasi:</dt>
                                        <dd class="col-sm-7 font-weight-bold text-warning">
                                            Rp <?= number_format($total_sudah_direalisasi, 0, ',', '.') ?>
                                        </dd>

                                        <dt class="col-sm-5">Sisa Uang Muka:</dt>
                                        <dd class="col-sm-7 font-weight-bold <?= $sisa_uang_muka >= 0 ? 'text-info' : 'text-danger' ?>">
                                            Rp <?= number_format($sisa_uang_muka, 0, ',', '.') ?>
                                        </dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Items Pengajuan Awal (Read Only) -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="fas fa-list"></i> Detail Item Pengajuan Awal (Rencana)
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="3%">
                                            <input type="checkbox" id="selectAllRencana" onchange="toggleAllRencana(this)" title="Pilih Semua">
                                        </th>
                                        <th width="4%">No</th>
                                        <th width="14%">No. Akun</th>
                                        <th>Rincian</th>
                                        <th width="11%">Jumlah</th>
                                        <th width="14%">Harga Satuan</th>
                                        <th width="14%">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $index => $item):
                                        // Cek apakah item ini sudah pernah direalisasikan berdasarkan no_ppx_detail
                                        $is_paid = isset($paid_items[$item['no_ppx_detail']]);
                                    ?>
                                        <tr class="<?= $is_paid ? 'table-success' : '' ?>">
                                            <td class="text-center">
                                                <?php if ($is_paid): ?>
                                                    <i class="fas fa-check-circle text-success" title="Sudah direalisasikan"></i>
                                                <?php else: ?>
                                                    <input type="checkbox" class="checkbox-rencana"
                                                        data-index="<?= $index ?>"
                                                        data-detail-id="<?= $item['no_ppx_detail'] ?>"
                                                        data-no-akun="<?= htmlspecialchars($item['no_akun']) ?>"
                                                        data-rincian="<?= htmlspecialchars($item['rincian']) ?>"
                                                        data-jumlah="<?= $item['jumlah'] ?>"
                                                        data-satuan="<?= htmlspecialchars($item['satuan']) ?>"
                                                        data-harga="<?= $item['harga_satuan'] ?>"
                                                        data-total="<?= $item['total_harga'] ?>">
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($item['no_akun']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($item['rincian']) ?>
                                                <?php if ($item['deskripsi']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($item['deskripsi']) ?></small>
                                                <?php endif; ?>
                                                <?php if ($is_paid): ?>
                                                    <br><small class="badge badge-success">Sudah Dibayar</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?= $item['jumlah'] ?> <?= htmlspecialchars($item['satuan']) ?></td>
                                            <td class="text-right">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                            <td class="text-right font-weight-bold">Rp <?= number_format($item['total_harga'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="6" class="text-right">Total Rencana:</th>
                                        <th class="text-right">Rp <?= number_format($total_nominal, 0, ',', '.') ?></th>
                                    </tr>
                                    <?php if ($total_sudah_direalisasi > 0): ?>
                                        <tr class="table-success">
                                            <th colspan="6" class="text-right">Sudah Direalisasi:</th>
                                            <th class="text-right">Rp <?= number_format($total_sudah_direalisasi, 0, ',', '.') ?></th>
                                        </tr>
                                        <tr class="table-warning">
                                            <th colspan="6" class="text-right"><strong>Sisa Belum Direalisasi:</strong></th>
                                            <th class="text-right"><strong>Rp <?= number_format($sisa_belum_direalisasi, 0, ',', '.') ?></strong></th>
                                        </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Detail Barang yang Dibeli (Realisasi) -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="fas fa-shopping-cart"></i> Detail Barang yang Dibeli (Realisasi)
                        </h5>

                        <div class="info-box">
                            <i class="fas fa-lightbulb"></i> <strong>Petunjuk:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Jika pembelian <strong>sesuai rencana</strong>, klik tombol "Copy dari Rencana" di bawah untuk auto-fill.</li>
                                <li>Jika ada perbedaan harga/jumlah/barang, Anda bisa <strong>edit manual</strong> atau <strong>tambah/hapus item</strong>.</li>
                                <li>Form ini wajib diisi untuk transparansi penggunaan dana.</li>
                            </ul>
                        </div>

                        <!-- Button Copy dari Rencana -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-info btn-sm" onclick="copySelectedFromRencana()">
                                    <i class="fas fa-copy"></i> Copy Item Terpilih dari Rencana
                                </button>
                                <small class="text-muted ml-2">
                                    <i class="fas fa-info-circle"></i> Centang item pada tabel Rencana di atas, kemudian klik tombol ini untuk copy item yang dipilih.
                                </small>
                            </div>
                        </div>

                        <div id="detailRealisasiContainer">
                            <!-- Item pertama -->
                            <div class="detail-item" data-index="0">
                                <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeRealisasiItem(0)" title="Hapus item">
                                    <i class="fas fa-times"></i>
                                </button>

                                <div class="row">
                                    <!-- Nama Barang -->
                                    <div class="col-md-3 mb-2">
                                        <label class="small font-weight-bold">Nama Barang/Item <span class="text-danger">*</span></label>
                                        <input type="text" name="realisasi[0][nama_barang]" class="form-control form-control-sm"
                                            placeholder="Contoh: Laptop" required>
                                    </div>

                                    <!-- No. Akun -->
                                    <div class="col-md-2 mb-2">
                                        <label class="small font-weight-bold">No. Akun</label>
                                        <select name="realisasi[0][no_akun]" class="form-control form-control-sm">
                                            <option value="">-- Pilih No. Akun --</option>
                                            <?= $akun_options_html ?>
                                        </select>
                                    </div>

                                    <!-- Jumlah -->
                                    <div class="col-md-1 mb-2">
                                        <label class="small font-weight-bold">Qty <span class="text-danger">*</span></label>
                                        <input type="number" name="realisasi[0][jumlah]" class="form-control form-control-sm jumlah-realisasi"
                                            min="1" value="1" required onchange="calculateRealisasiTotal(0)">
                                    </div>

                                    <!-- Satuan -->
                                    <div class="col-md-1 mb-2">
                                        <label class="small font-weight-bold">Satuan <span class="text-danger">*</span></label>
                                        <select name="realisasi[0][satuan]" class="form-control form-control-sm" required>
                                            <option value="Pcs">Pcs</option>
                                            <option value="Unit">Unit</option>
                                            <option value="Set">Set</option>
                                            <option value="Buah">Buah</option>
                                            <option value="Paket">Paket</option>
                                            <option value="Kg">Kg</option>
                                            <option value="Meter">Meter</option>
                                            <option value="Liter">Liter</option>
                                            <option value="Box">Box</option>
                                            <option value="Roll">Roll</option>
                                            <option value="Lembar">Lembar</option>
                                            <option value="Dus">Dus</option>
                                        </select>
                                    </div>

                                    <!-- Harga Satuan -->
                                    <div class="col-md-2 mb-2">
                                        <label class="small font-weight-bold">Harga Satuan <span class="text-danger">*</span></label>
                                        <input type="text" name="realisasi[0][harga_satuan]" class="form-control form-control-sm harga-realisasi"
                                            placeholder="Rp 0" required onkeyup="formatRupiahRealisasi(this, 0)">
                                    </div>

                                    <!-- Total Harga -->
                                    <div class="col-md-2 mb-2">
                                        <label class="small font-weight-bold">Total Harga</label>
                                        <input type="text" name="realisasi[0][total_harga]" class="form-control form-control-sm total-harga-realisasi"
                                            placeholder="Rp 0" readonly style="background-color: #e9ecef;">
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- Tombol Tambah Item Realisasi -->
                        <div class="row mt-2">
                            <div class="col-12">
                                <button type="button" class="btn btn-sm btn-success" onclick="addRealisasiItem()">
                                    <i class="fas fa-plus"></i> Tambah Barang
                                </button>
                            </div>
                        </div>

                        <!-- Total Realisasi -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Total Realisasi:</strong>
                                        </div>
                                        <div class="col-md-6 text-right">
                                            <h5 id="grandTotalRealisasi" class="mb-0 text-primary">Rp 0</h5>
                                            <input type="hidden" name="total_realisasi" id="total_realisasi" value="0">
                                            <input type="hidden" name="status_selisih" id="status_selisih" value="pas">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Bukti -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="fas fa-paperclip"></i> Upload Bukti Realisasi
                        </h5>

                        <div class="row">
                            <!-- Upload Bukti Nota -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required-field">
                                        <i class="fas fa-receipt"></i> Upload Bukti Nota/Struk
                                    </label>
                                    <input type="file"
                                        class="form-control"
                                        id="bukti_realisasi"
                                        name="bukti_realisasi[]"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        multiple
                                        required>
                                    <small class="form-text text-muted">
                                        Format: JPG, PNG, PDF. Maksimal 5 file @ 5MB
                                    </small>
                                    <div id="preview_realisasi" class="file-preview"></div>
                                </div>
                            </div>

                            <!-- Uang Kembali / Kekurangan -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-hand-holding-usd"></i> <span id="label_selisih">Uang Kembali ke Finance (Rp)</span>
                                    </label>
                                    <input type="hidden" id="uang_kembali_raw" name="uang_kembali" value="0">
                                    <input type="text" class="form-control" id="uang_kembali_display" value="Rp 0" readonly>
                                    <small class="form-text text-muted" id="help_selisih">
                                        Otomatis terhitung: Uang Muka - Total Realisasi
                                    </small>
                                </div>
                            </div>

                            <!-- Keterangan Realisasi -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>
                                        <i class="fas fa-comment-dots"></i> Keterangan Umum Realisasi
                                    </label>
                                    <textarea class="form-control"
                                        id="keterangan_realisasi"
                                        name="keterangan_realisasi"
                                        rows="3"
                                        placeholder="Catatan umum tentang realisasi ini (opsional)"></textarea>
                                </div>
                            </div>

                            <!-- Alert Kekurangan Dana (Hidden by default) -->
                            <div class="col-md-12" id="alert_kekurangan" style="display: none;">
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Perhatian: Uang Muka Tidak Cukup</h6>
                                    <p class="mb-2">
                                        Total realisasi melebihi uang muka yang diterima sebesar <strong id="nilai_kekurangan">Rp 0</strong>.
                                    </p>
                                    <hr>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="confirm_pakai_uang_pribadi" name="confirm_pakai_uang_pribadi">
                                        <label class="custom-control-label" for="confirm_pakai_uang_pribadi">
                                            <strong>Saya sudah menggunakan uang pribadi untuk menutupi kekurangan dan akan mengajukan penggantian/reimbursement kepada Finance.</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Box -->
                    <div class="form-section">
                        <h5 class="mb-3">
                            <i class="fas fa-calculator"></i> Ringkasan
                        </h5>
                        <div class="alert alert-light border">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <strong>Uang Muka Total:</strong><br>
                                    <span class="text-success h6">Rp <?= number_format($ppx_data['uang_muka'], 0, ',', '.') ?></span>
                                    <?php if ($total_sudah_direalisasi > 0): ?>
                                        <br><small class="text-muted">Sudah: Rp <?= number_format($total_sudah_direalisasi, 0, ',', '.') ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Total Realisasi (Semua):</strong><br>
                                    <span class="text-primary h5">Rp <span id="display_total_realisasi_semua"><?= number_format($total_sudah_direalisasi, 0, ',', '.') ?></span></span>
                                    <?php if ($total_sudah_direalisasi > 0): ?>
                                        <br><small class="text-muted">Sekarang: Rp <span id="display_realisasi_sekarang">0</span></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Sisa Uang Muka:</strong><br>
                                    <span class="<?= $total_sudah_direalisasi > 0 ? 'text-info' : 'text-success' ?> h5">Rp <span id="display_uang_muka"><?= number_format($ppx_data['uang_muka'] - $total_sudah_direalisasi, 0, ',', '.') ?></span></span>
                                </div>
                                <div class="col-md-3">
                                    <strong><span id="label_summary_selisih">Sisa/Kembali:</span></strong><br>
                                    <span class="h5" id="display_sisa_wrapper">
                                        <span class="text-info">Rp <span id="display_sisa">0</span></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Info Kekurangan di Summary -->
                        <div id="summary_kekurangan_info" style="display: none;">
                            <div class="alert alert-danger">
                                <div class="row text-center">
                                    <div class="col-12">
                                        <h6 class="mb-3"><i class="fas fa-exclamation-circle"></i> Terdapat Kekurangan Dana</h6>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Uang Muka</small>
                                        <strong class="h6">Rp <span id="summary_uang_muka">0</span></strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Total Realisasi</small>
                                        <strong class="h6 text-danger">Rp <span id="summary_total_realisasi">0</span></strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Kekurangan (Uang Pribadi)</small>
                                        <strong class="h5 text-danger">Rp <span id="summary_kekurangan">0</span></strong>
                                    </div>
                                </div>
                                <hr>
                                <p class="mb-0 text-center">
                                    <i class="fas fa-info-circle"></i>
                                    Finance akan memproses penggantian/reimbursement sejumlah <strong>Rp <span id="summary_reimbursement">0</span></strong> ke rekening Anda.
                                </p>
                            </div>
                            </div>
                        </div>

                    <!-- Action Buttons -->
                    <div class="form-section">
                        <div class="text-right">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                            <button type="submit" name="submit_realisasi" class="btn btn-success" id="btnSubmit">
                                <i class="fas fa-paper-plane"></i> Submit Realisasi
                            </button>
                        </div>
                    </div>
                    </form>
                    <?php include $layout_dir . '/footer.php'; ?>
            </div>
        </div>
        
    </div>


    <script>
        // Data rencana dari PHP
        const dataRencana = <?= json_encode($items) ?>;
        // Akun options for JS templates
        const akunOptions = <?= json_encode($akun_options_html) ?>;

        // Deklarasikan variable global di luar document.ready
        const uangMukaPenuh = <?= $ppx_data['uang_muka'] ?>;
        const totalSudahDirealisasi = <?= $total_sudah_direalisasi ?>;
        const uangMuka = uangMukaPenuh - totalSudahDirealisasi; // Sisa uang muka yang tersedia
        let realisasiItemIndex = 1;

        // Format Rupiah functions - Global scope
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function parseRupiah(rupiah) {
            if (typeof rupiah === 'number') return rupiah;
            return parseInt(String(rupiah).replace(/[^0-9]/g, '')) || 0;
        }

        // Set formatted and raw uang_kembali
        function setUangKembali(amount) {
            const v = Math.abs(parseInt(amount) || 0);
            // raw numeric for form submission
            document.getElementById('uang_kembali_raw').value = v;
            // formatted display
            document.getElementById('uang_kembali_display').value = 'Rp ' + formatNumber(v);
        }

        // Format input rupiah untuk realisasi - Global scope
        function formatRupiahRealisasi(input, index) {
            let value = parseRupiah(input.value);
            input.value = 'Rp ' + formatNumber(value);
            calculateRealisasiTotal(index);
        }

        // Calculate total per item realisasi - Global scope
        function calculateRealisasiTotal(index) {
            if (typeof jQuery === 'undefined') return;
            const jumlah = parseInt(jQuery(`input[name="realisasi[${index}][jumlah]"]`).val()) || 0;
            const hargaSatuan = parseRupiah(jQuery(`input[name="realisasi[${index}][harga_satuan]"]`).val());
            const total = jumlah * hargaSatuan;

            jQuery(`input[name="realisasi[${index}][total_harga]"]`).val('Rp ' + formatNumber(total));

            calculateGrandTotalRealisasi();
        }

        // Calculate grand total realisasi - Global scope
        function calculateGrandTotalRealisasi() {
            if (typeof jQuery === 'undefined') return;
            let grandTotal = 0;

            jQuery('.total-harga-realisasi').each(function() {
                grandTotal += parseRupiah(jQuery(this).val());
            });

            jQuery('#grandTotalRealisasi').text('Rp ' + formatNumber(grandTotal));
            jQuery('#total_realisasi').val(grandTotal);

            // Update Total Realisasi Semua = Sudah Direalisasi + Realisasi Sekarang
            const totalRealisasiSemua = totalSudahDirealisasi + grandTotal;
            jQuery('#display_total_realisasi_semua').text(formatNumber(totalRealisasiSemua));
            jQuery('#display_realisasi_sekarang').text(formatNumber(grandTotal));

            // Calculate selisih berdasarkan sisa uang muka (bukan uang muka penuh)
            const selisih = uangMuka - grandTotal;

            // Update display
            jQuery('#display_sisa').text(formatNumber(Math.abs(selisih)));

            // Change UI berdasarkan kondisi
            const wrapper = jQuery('#display_sisa_wrapper');
            wrapper.find('span').first().removeClass('text-success text-danger text-warning text-info');

            if (selisih > 0) {
                // KELEBIHAN - Uang Kembali ke Finance
                wrapper.find('span').first().addClass('text-success');
                setUangKembali(selisih);
                jQuery('#status_selisih').val('kembali');
                jQuery('#status_selisih').val('kembali');

                // Update labels
                jQuery('#label_selisih').html('<i class="fas fa-hand-holding-usd"></i> Uang Kembali ke Finance (Rp)');
                jQuery('#help_selisih').text('Sisa uang muka yang harus dikembalikan ke Finance');
                jQuery('#label_summary_selisih').text('Sisa/Kembali:');

                // Hide alert kekurangan
                jQuery('#alert_kekurangan').hide();
                jQuery('#summary_kekurangan_info').hide();
                jQuery('#confirm_pakai_uang_pribadi').prop('checked', false);

            } else if (selisih < 0) {
                // KEKURANGAN - User pakai uang pribadi
                wrapper.find('span').first().addClass('text-danger');
                setUangKembali(Math.abs(selisih)); // Simpan nilai positif
                jQuery('#status_selisih').val('kurang');
                jQuery('#status_selisih').val('kurang');

                // Update labels
                jQuery('#label_selisih').html('<i class="fas fa-exclamation-triangle"></i> Kekurangan Dana (Rp)');
                jQuery('#help_selisih').html('<strong class="text-danger">Total realisasi melebihi uang muka. Anda perlu konfirmasi penggunaan uang pribadi.</strong>');
                jQuery('#label_summary_selisih').text('Kekurangan:');

                // Show alert kekurangan
                jQuery('#alert_kekurangan').show();
                jQuery('#nilai_kekurangan').text('Rp ' + formatNumber(Math.abs(selisih)));

                // Update summary kekurangan
                jQuery('#summary_uang_muka').text(formatNumber(uangMuka));
                jQuery('#summary_total_realisasi').text(formatNumber(grandTotal));
                jQuery('#summary_kekurangan').text(formatNumber(Math.abs(selisih)));
                jQuery('#summary_reimbursement').text(formatNumber(Math.abs(selisih)));
                jQuery('#summary_kekurangan_info').show();

            } else {
                // PAS - Tidak ada kelebihan/kekurangan
                wrapper.find('span').first().addClass('text-info');
                setUangKembali(0);
                jQuery('#status_selisih').val('pas');
                jQuery('#status_selisih').val('pas');

                // Update labels
                jQuery('#label_selisih').html('<i class="fas fa-check-circle"></i> Status Dana');
                jQuery('#help_selisih').text('Total realisasi PAS dengan uang muka yang diterima');
                jQuery('#label_summary_selisih').text('Status:');

                // Hide alert kekurangan
                jQuery('#alert_kekurangan').hide();
                jQuery('#summary_kekurangan_info').hide();
                jQuery('#confirm_pakai_uang_pribadi').prop('checked', false);
            }
        }

        // Toggle all checkboxes
        function toggleAllRencana(checkbox) {
            if (typeof jQuery === 'undefined') return;
            jQuery('.checkbox-rencana').prop('checked', checkbox.checked);
        }

        // FUNGSI BARU: Copy item terpilih dari Rencana
        function copySelectedFromRencana() {
            // Wait for jQuery to be ready
            if (typeof jQuery === 'undefined') {
                setTimeout(copySelectedFromRencana, 50);
                return;
            }
            const selectedCheckboxes = jQuery('.checkbox-rencana:checked');

            if (selectedCheckboxes.length === 0) {
                alert('Pilih minimal 1 item dari Rencana!\n\nCentang checkbox pada item yang ingin direalisasikan.');
                return;
            }

            // Cek apakah ada item kosong (item dengan nama barang kosong)
            const existingItems = jQuery('#detailRealisasiContainer .detail-item');
            let hasEmptyItems = false;

            existingItems.each(function() {
                const namaBarang = jQuery(this).find('input[name*="[nama_barang]"]').val();
                if (!namaBarang || namaBarang.trim() === '') {
                    hasEmptyItems = true;
                    return false; // break loop
                }
            });

            // Jika ada item kosong, hapus semua dan reset index
            if (hasEmptyItems) {
                jQuery('#detailRealisasiContainer').empty();
                realisasiItemIndex = 0;
            }

            const copyMessage = `Copy ${selectedCheckboxes.length} item terpilih dari Rencana?<br><br>${hasEmptyItems ? 'Item kosong akan diganti.' : 'Item akan ditambahkan ke daftar realisasi.'}`;
            showConfirm('Konfirmasi Copy', copyMessage, function() {
                // Confirmed - execute copy
                executeCopyItems(selectedCheckboxes, hasEmptyItems);
            }, null, 'confirm');
        }

        function executeCopyItems(selectedCheckboxes, hasEmptyItems) {
            if (typeof jQuery === 'undefined') return;

            // Loop setiap checkbox yang terpilih
            selectedCheckboxes.each(function() {
                const checkbox = jQuery(this);
                const rencanaIndex = checkbox.data('index');
                const detailId = checkbox.data('detail-id'); // no_ppx_detail
                const item = {
                    no_ppx_detail: detailId,
                    no_akun: checkbox.data('no-akun'),
                    rincian: checkbox.data('rincian'),
                    jumlah: checkbox.data('jumlah'),
                    satuan: checkbox.data('satuan'),
                    harga_satuan: checkbox.data('harga'),
                    total_harga: checkbox.data('total')
                };
                const index = realisasiItemIndex;
                const newItem = `
                    <div class="detail-item" data-index="${index}" data-rencana-index="${rencanaIndex}">
                        <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeRealisasiItem(${index})" title="Hapus item">
                            <i class="fas fa-times"></i>
                        </button>

                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="small font-weight-bold">Nama Barang/Item <span class="text-danger">*</span></label>
                                <input type="text" name="realisasi[${index}][nama_barang]" class="form-control form-control-sm"
                                    value="${item.rincian}" required>
                            </div>

                            <div class="col-md-2 mb-2">
                                <label class="small font-weight-bold">No. Akun</label>
                                <select name="realisasi[${index}][no_akun]" class="form-control form-control-sm">
                                    <option value="">-- Pilih No. Akun --</option>
                                    ${akunOptions}
                                </select>
                            </div>

                            <div class="col-md-1 mb-2">
                                <label class="small font-weight-bold">Qty <span class="text-danger">*</span></label>
                                <input type="number" name="realisasi[${index}][jumlah]" class="form-control form-control-sm jumlah-realisasi"
                                    min="1" value="${item.jumlah}" required onchange="calculateRealisasiTotal(${index})">
                            </div>

                            <div class="col-md-1 mb-2">
                                <label class="small font-weight-bold">Satuan <span class="text-danger">*</span></label>
                                <select name="realisasi[${index}][satuan]" class="form-control form-control-sm" required>
                                    <option value="Pcs" ${item.satuan === 'Pcs' ? 'selected' : ''}>Pcs</option>
                                    <option value="Unit" ${item.satuan === 'Unit' ? 'selected' : ''}>Unit</option>
                                    <option value="Set" ${item.satuan === 'Set' ? 'selected' : ''}>Set</option>
                                    <option value="Buah" ${item.satuan === 'Buah' ? 'selected' : ''}>Buah</option>
                                    <option value="Paket" ${item.satuan === 'Paket' ? 'selected' : ''}>Paket</option>
                                    <option value="Kg" ${item.satuan === 'Kg' ? 'selected' : ''}>Kg</option>
                                    <option value="Meter" ${item.satuan === 'Meter' ? 'selected' : ''}>Meter</option>
                                    <option value="Liter" ${item.satuan === 'Liter' ? 'selected' : ''}>Liter</option>
                                    <option value="Box" ${item.satuan === 'Box' ? 'selected' : ''}>Box</option>
                                    <option value="Roll" ${item.satuan === 'Roll' ? 'selected' : ''}>Roll</option>
                                    <option value="Lembar" ${item.satuan === 'Lembar' ? 'selected' : ''}>Lembar</option>
                                    <option value="Dus" ${item.satuan === 'Dus' ? 'selected' : ''}>Dus</option>
                                </select>
                            </div>

                            <div class="col-md-2 mb-2">
                                <label class="small font-weight-bold">Harga Satuan <span class="text-danger">*</span></label>
                                <input type="text" name="realisasi[${index}][harga_satuan]" class="form-control form-control-sm harga-realisasi"
                                    value="Rp ${formatNumber(item.harga_satuan)}" required onkeyup="formatRupiahRealisasi(this, ${index})">
                            </div>

                            <div class="col-md-2 mb-2">
                                <label class="small font-weight-bold">Total Harga</label>
                                <input type="text" name="realisasi[${index}][total_harga]" class="form-control form-control-sm total-harga-realisasi"
                                    value="Rp ${formatNumber(item.total_harga)}" readonly style="background-color: #e9ecef;">
                            </div>
                            
                            <!-- Hidden field untuk no_ppx_detail -->
                            <input type="hidden" name="realisasi[${index}][no_ppx_detail]" value="${item.no_ppx_detail || ''}">
                        </div>
                    </div>
                `;

                jQuery('#detailRealisasiContainer').append(newItem);
                // set selected no_akun if available in source item
                if (item.no_akun) {
                    jQuery(`select[name="realisasi[${index}][no_akun]"]`).val(item.no_akun);
                }
                realisasiItemIndex++;
            });

            // Recalculate grand total
            calculateGrandTotalRealisasi();

            // Disable and uncheck selected items to prevent duplicate selection
            selectedCheckboxes.prop('checked', false).prop('disabled', true).css('opacity', '0.4');
            jQuery('#selectAllRencana').prop('checked', false);

            // Show success message
            alert(`Berhasil copy ${selectedCheckboxes.length} item terpilih dari Rencana!\n\nAnda bisa edit jika ada perbedaan harga/jumlah.`);
        }

        // Add realisasi item - Global scope
        function addRealisasiItem() {
            if (typeof jQuery === 'undefined') return;
            const index = realisasiItemIndex++;
            const newItem = `
                <div class="detail-item" data-index="${index}">
                    <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeRealisasiItem(${index})" title="Hapus item">
                        <i class="fas fa-times"></i>
                    </button>

                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="small font-weight-bold">Nama Barang/Item <span class="text-danger">*</span></label>
                            <input type="text" name="realisasi[${index}][nama_barang]" class="form-control form-control-sm"
                                placeholder="Contoh: Laptop" required>
                        </div>

                        <div class="col-md-2 mb-2">
                            <label class="small font-weight-bold">No. Akun</label>
                            <select name="realisasi[${index}][no_akun]" class="form-control form-control-sm">
                                <option value="">-- Pilih No. Akun --</option>
                                ${akunOptions}
                            </select>
                        </div>

                        <div class="col-md-1 mb-2">
                            <label class="small font-weight-bold">Qty <span class="text-danger">*</span></label>
                            <input type="number" name="realisasi[${index}][jumlah]" class="form-control form-control-sm jumlah-realisasi"
                                min="1" value="1" required onchange="calculateRealisasiTotal(${index})">
                        </div>

                        <div class="col-md-1 mb-2">
                            <label class="small font-weight-bold">Satuan <span class="text-danger">*</span></label>
                            <select name="realisasi[${index}][satuan]" class="form-control form-control-sm" required>
                                <option value="Pcs">Pcs</option>
                                <option value="Unit">Unit</option>
                                <option value="Set">Set</option>
                                <option value="Buah">Buah</option>
                                <option value="Paket">Paket</option>
                                <option value="Kg">Kg</option>
                                <option value="Meter">Meter</option>
                                <option value="Liter">Liter</option>
                                <option value="Box">Box</option>
                                <option value="Roll">Roll</option>
                                <option value="Lembar">Lembar</option>
                                <option value="Dus">Dus</option>
                            </select>
                        </div>

                        <div class="col-md-2 mb-2">
                            <label class="small font-weight-bold">Harga Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="realisasi[${index}][harga_satuan]" class="form-control form-control-sm harga-realisasi"
                                placeholder="Rp 0" required onkeyup="formatRupiahRealisasi(this, ${index})">
                        </div>

                        <div class="col-md-2 mb-2">
                            <label class="small font-weight-bold">Total Harga</label>
                            <input type="text" name="realisasi[${index}][total_harga]" class="form-control form-control-sm total-harga-realisasi"
                                placeholder="Rp 0" readonly style="background-color: #e9ecef;">
                        </div>

                    </div>
                </div>
            `;

            jQuery('#detailRealisasiContainer').append(newItem);
        }

        // Remove realisasi item - Global scope
        function removeRealisasiItem(index) {
            if (typeof jQuery === 'undefined') return;
            const items = jQuery('.detail-item');
            const itemToRemove = jQuery(`.detail-item[data-index="${index}"]`);
            const rencanaIndex = itemToRemove.data('rencana-index');

            function reenableRencana(idx) {
                if (idx !== undefined && typeof jQuery !== 'undefined') {
                    jQuery(`.checkbox-rencana[data-index="${idx}"]`).prop('disabled', false).css('opacity', '1');
                }
            }

            // If there's only one item, clear its fields but keep the container
            if (items.length <= 1) {
                showConfirm('Konfirmasi Hapus', 'Hapus item realisasi ini?', function() {
                    // Clear fields but keep the container so form still has one blank item
                    itemToRemove.find('input[name$="[nama_barang]"]').val('');
                    itemToRemove.find('select[name$="[no_akun]"]').val('');
                    itemToRemove.find('input[name$="[jumlah]"]').val(1);
                    itemToRemove.find('select[name$="[satuan]"]').val('Pcs');
                    itemToRemove.find('input[name$="[harga_satuan]"]').val('Rp 0');
                    itemToRemove.find('input[name$="[total_harga]"]').val('Rp 0');

                    itemToRemove.removeAttr('data-rencana-index');
                    reenableRencana(rencanaIndex);
                    calculateGrandTotalRealisasi();
                }, null, 'delete');

                return;
            }

            // Multiple items exist: remove the selected one
            showConfirm('Konfirmasi Hapus', 'Hapus item realisasi ini?', function() {
                reenableRencana(rencanaIndex);
                itemToRemove.remove();
                calculateGrandTotalRealisasi();
            }, null, 'delete');
        }

        // Document ready untuk event handlers - use jQuery instead of $
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                // Initialize calculations on page load
                calculateGrandTotalRealisasi();

                // File preview handler
                $('#bukti_realisasi').on('change', function() {
                    const preview = $('#preview_realisasi');
                    preview.empty();

                    const files = this.files;
                    const maxFiles = 5;
                    const maxSize = 5 * 1024 * 1024; // 5MB

                    if (files.length > maxFiles) {
                        alert(`Maksimal ${maxFiles} file!`);
                        this.value = '';
                        return;
                    }

                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];

                        if (file.size > maxSize) {
                            alert(`File "${file.name}" terlalu besar! Maksimal 5MB per file.`);
                            this.value = '';
                            preview.empty();
                            return;
                        }

                        const fileItem = $('<div class="file-preview-item"></div>');
                        fileItem.html(`<i class="fas fa-file"></i> ${file.name} <small>(${(file.size / 1024).toFixed(1)} KB)</small>`);
                        preview.append(fileItem);
                    }
                });

                // Form validation
                $('#realisasiForm').on('submit', function(e) {
                    const buktiRealisasi = $('#bukti_realisasi')[0].files;
                    const totalRealisasi = parseFloat($('#total_realisasi').val()) || 0;
                    const uangKembali = parseFloat($('#uang_kembali_raw').val()) || 0;
                    const confirmPakaiUangPribadi = $('#confirm_pakai_uang_pribadi').is(':checked');

                    if (buktiRealisasi.length === 0) {
                        e.preventDefault();
                        alert('Bukti Nota wajib diupload!');
                        return false;
                    }

                    if (totalRealisasi <= 0) {
                        e.preventDefault();
                        alert('Total Realisasi harus lebih dari 0!\nSilakan isi detail barang yang dibeli.');
                        return false;
                    }

                    // Validasi jika ada kekurangan dana (total realisasi > uang muka)
                    if (totalRealisasi > uangMuka && !confirmPakaiUangPribadi) {
                        e.preventDefault();
                        alert('Anda harus mengkonfirmasi bahwa telah menggunakan uang pribadi untuk menutupi kekurangan!\n\nSilakan centang checkbox konfirmasi.');
                        $('#confirm_pakai_uang_pribadi').focus();
                        return false;
                    }

                    let confirmMessage = 'Apakah Anda yakin data realisasi sudah benar?\n\n';

                    if (totalRealisasi < uangMuka) {
                        // Ada uang kembali
                        confirmMessage += `Uang kembali: Rp ${formatNumber(uangKembali)}\n`;
                        confirmMessage += 'Anda wajib mengembalikan sisa uang muka ke Finance.\n\n';
                    } else if (totalRealisasi > uangMuka) {
                        // Ada kekurangan
                        confirmMessage += `Kekurangan: Rp ${formatNumber(uangKembali)}\n`;
                        confirmMessage += 'Finance akan memproses reimbursement ke rekening Anda.\n\n';
                    } else {
                        // PAS
                        confirmMessage += 'Total realisasi PAS dengan uang muka.\n\n';
                    }

                    confirmMessage += 'Setelah submit, data tidak dapat diubah.';

                    // Use async modal confirm instead of synchronous browser confirm
                    e.preventDefault();
                    showConfirm('Konfirmasi Submit', confirmMessage, function() {
                        // Ensure server receives the submit button name/value
                        if ($('#realisasiForm input[name="submit_realisasi"]').length === 0) {
                            $('<input>').attr({
                                type: 'hidden',
                                name: 'submit_realisasi',
                                value: '1'
                            }).appendTo('#realisasiForm');
                        }

                        // Unbind this handler temporarily to avoid interception
                        $('#realisasiForm').off('submit');
                        $('#realisasiForm')[0].submit();
                    }, null, 'confirm');

                    return false;
                });
            });
        }
    </script>
</body>

</html>