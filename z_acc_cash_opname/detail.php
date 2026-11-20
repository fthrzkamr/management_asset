<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';
require_once __DIR__ . '/../dist/config/ppx_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('realisasi_ppx', 'read');

init_acc_fase3_db($conn);
init_ppx_fase2_db($conn);

$id_opname = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_opname <= 0) {
    header("Location: index.php");
    exit();
}

$q = mysqli_query($conn, "
    SELECT co.*, pmc.nama_cabang, pmc.kode_cabang,
           kc.kode_akun AS kode_kas, kc.nama_akun AS nama_kas,
           u1.nama_user AS pembuat, u2.nama_user AS approver,
           jh.no_jurnal
    FROM acc_cash_opname co
    JOIN ppx_master_cabang pmc ON co.id_cabang_ppx = pmc.id_cabang_ppx
    LEFT JOIN acc_coa kc ON co.id_coa_kas = kc.id_coa
    LEFT JOIN user_dfm u1 ON co.created_by = u1.id_user
    LEFT JOIN user_dfm u2 ON co.approved_by = u2.id_user
    LEFT JOIN acc_jurnal_header jh ON co.id_jurnal_penyesuaian = jh.id_jurnal
    WHERE co.id_opname = $id_opname
    LIMIT 1
");

if (!$q || mysqli_num_rows($q) === 0) {
    $_SESSION['error'] = 'Laporan Cash Opname tidak ditemukan.';
    header("Location: index.php");
    exit();
}
$opname = mysqli_fetch_assoc($q);
$pecahan = json_decode($opname['rincian_pecahan_json'] ?? '[]', true) ?: [];
$sel = (float)$opname['selisih'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Cash Opname – <?= htmlspecialchars($opname['no_opname']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .badge-klop { background-color: #dcfce7; color: #15803d; font-weight: 700; font-size: 13px; padding: 6px 12px; border-radius: 6px; }
        .badge-kurang { background-color: #fee2e2; color: #b91c1c; font-weight: 700; font-size: 13px; padding: 6px 12px; border-radius: 6px; }
        .badge-lebih { background-color: #dbeafe; color: #1d4ed8; font-weight: 700; font-size: 13px; padding: 6px 12px; border-radius: 6px; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php if ($layout_dir && file_exists($layout_dir . '/navbar.php')) include $layout_dir . '/navbar.php'; ?>

    <div class="page-wrapper">
        <div class="content container-fluid">
            <!-- Breadcrumb -->
            <div class="page-header mb-4">
                <div class="row align-items-center">
                    <div class="col-sm-8">
                        <h3 class="page-title text-dark font-weight-bold">
                            <i class="fas fa-file-invoice-dollar text-primary mr-2"></i> Berita Acara Cash Opname: <?= htmlspecialchars($opname['no_opname']) ?>
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Cash Opname</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($opname['no_opname']) ?></li>
                        </ul>
                    </div>
                    <div class="col-sm-4 text-right">
                        <a href="cetak_opname.php?id=<?= $opname['id_opname'] ?>" target="_blank" class="btn btn-secondary font-weight-bold mr-2 shadow-sm">
                            <i class="fas fa-print mr-1"></i> Cetak Berita Acara
                        </a>
                        <a href="index.php" class="btn btn-light border font-weight-bold">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>

            <!-- Flash Alert -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> <?= $_SESSION['success'] ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="row">
                <!-- Info Header Card -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title font-weight-bold mb-0 text-dark">
                                Rincian Hasil Fisik Kas vs Saldo Buku
                            </h5>
                            <?php if ($opname['status'] === 'Disetujui'): ?>
                                <span class="badge badge-success px-3 py-2" style="font-size: 13px;"><i class="fas fa-check-circle mr-1"></i> Disetujui</span>
                            <?php else: ?>
                                <span class="badge badge-warning px-3 py-2 text-dark" style="font-size: 13px;"><i class="fas fa-clock mr-1"></i> Draft</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <small class="text-muted font-weight-bold d-block">Cabang PPX</small>
                                    <strong class="text-dark">[<?= $opname['kode_cabang'] ?>] <?= htmlspecialchars($opname['nama_cabang']) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted font-weight-bold d-block">Tanggal Opname</small>
                                    <strong class="text-dark"><?= date('d F Y', strtotime($opname['tanggal_opname'])) ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted font-weight-bold d-block">Periode Minggu</small>
                                    <strong class="text-dark"><?= htmlspecialchars($opname['periode_minggu']) ?></strong>
                                </div>
                            </div>

                            <!-- Perbandingan Card -->
                            <div class="p-3 rounded mb-4" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="row text-center align-items-center">
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <div class="small text-muted font-weight-bold text-uppercase">Saldo Buku Kas (Sistem)</div>
                                        <div class="h4 font-weight-bold text-dark mt-1">Rp <?= number_format((float)$opname['saldo_buku'], 0, ',', '.') ?></div>
                                    </div>
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <div class="small text-muted font-weight-bold text-uppercase">Total Fisik di Brankas</div>
                                        <div class="h4 font-weight-bold text-primary mt-1">Rp <?= number_format((float)$opname['saldo_fisik'], 0, ',', '.') ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted font-weight-bold text-uppercase">Hasil Selisih</div>
                                        <div class="mt-1">
                                            <?php if (abs($sel) < 0.01): ?>
                                                <span class="badge-klop"><i class="fas fa-check mr-1"></i> KLOP (Rp 0)</span>
                                            <?php elseif ($sel < 0): ?>
                                                <span class="badge-kurang"><i class="fas fa-minus-circle mr-1"></i> KURANG Rp <?= number_format(abs($sel), 0, ',', '.') ?></span>
                                            <?php else: ?>
                                                <span class="badge-lebih"><i class="fas fa-plus-circle mr-1"></i> LEBIH Rp <?= number_format($sel, 0, ',', '.') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($opname['keterangan_selisih'])): ?>
                                    <div class="alert alert-warning small mt-3 mb-0">
                                        <i class="fas fa-info-circle mr-1"></i> <strong>Keterangan Selisih:</strong> <?= htmlspecialchars($opname['keterangan_selisih']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Rincian Tabel Pecahan -->
                            <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-list-ol mr-1"></i> Rincian Denominasi Uang</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Pecahan</th>
                                            <th class="text-center" width="20%">Banyaknya</th>
                                            <th class="text-right" width="30%">Subtotal (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $rows_p = [
                                            ['label' => 'Uang Kertas Rp 100.000', 'qty' => (int)($pecahan['kertas_100k'] ?? 0), 'val' => 100000],
                                            ['label' => 'Uang Kertas Rp 50.000',  'qty' => (int)($pecahan['kertas_50k'] ?? 0),  'val' => 50000],
                                            ['label' => 'Uang Kertas Rp 20.000',  'qty' => (int)($pecahan['kertas_20k'] ?? 0),  'val' => 20000],
                                            ['label' => 'Uang Kertas Rp 10.000',  'qty' => (int)($pecahan['kertas_10k'] ?? 0),  'val' => 10000],
                                            ['label' => 'Uang Kertas Rp 5.000',   'qty' => (int)($pecahan['kertas_5k'] ?? 0),   'val' => 5000],
                                            ['label' => 'Uang Kertas Rp 2.000',   'qty' => (int)($pecahan['kertas_2k'] ?? 0),   'val' => 2000],
                                            ['label' => 'Uang Kertas Rp 1.000',   'qty' => (int)($pecahan['kertas_1k'] ?? 0),   'val' => 1000],
                                            ['label' => 'Uang Logam Rp 1.000',    'qty' => (int)($pecahan['koin_1000'] ?? 0),    'val' => 1000],
                                            ['label' => 'Uang Logam Rp 500',      'qty' => (int)($pecahan['koin_500'] ?? 0),     'val' => 500],
                                            ['label' => 'Uang Logam Rp 200',      'qty' => (int)($pecahan['koin_200'] ?? 0),     'val' => 200],
                                            ['label' => 'Uang Logam Rp 100',      'qty' => (int)($pecahan['koin_100'] ?? 0),     'val' => 100],
                                        ];
                                        foreach ($rows_p as $rp): 
                                            if ($rp['qty'] <= 0) continue;
                                            $sub = $rp['qty'] * $rp['val'];
                                        ?>
                                        <tr>
                                            <td><?= $rp['label'] ?></td>
                                            <td class="text-center font-weight-bold"><?= number_format($rp['qty']) ?> lembar / keping</td>
                                            <td class="text-right font-weight-bold">Rp <?= number_format($sub, 0, ',', '.') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-light font-weight-bold">
                                            <td colspan="2" class="text-right">TOTAL FISIK KAS:</td>
                                            <td class="text-right text-primary" style="font-size: 15px;">Rp <?= number_format((float)$opname['saldo_fisik'], 0, ',', '.') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Samping: Petugas, Saksi & Jurnal Penyesuaian -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="card-title font-weight-bold mb-0 text-dark">
                                <i class="fas fa-signature text-secondary mr-1"></i> Petugas & Saksi
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted font-weight-bold d-block">Petugas Pemeriksa</small>
                                <strong class="text-dark"><i class="fas fa-user mr-1 text-primary"></i> <?= htmlspecialchars($opname['petugas_opname']) ?></strong>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted font-weight-bold d-block">Saksi Pemeriksaan</small>
                                <strong class="text-dark"><i class="fas fa-user-friends mr-1 text-secondary"></i> <?= htmlspecialchars($opname['saksi_opname'] ?: '–') ?></strong>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted font-weight-bold d-block">Disetujui Oleh</small>
                                <strong class="text-dark"><i class="fas fa-check mr-1 text-success"></i> <?= htmlspecialchars($opname['approver'] ?: '–') ?></strong>
                                <?php if (!empty($opname['approved_at'])): ?>
                                    <small class="text-muted d-block"><?= date('d/m/Y H:i', strtotime($opname['approved_at'])) ?></small>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($opname['no_jurnal'])): ?>
                                <hr>
                                <div class="mb-2">
                                    <small class="text-muted font-weight-bold d-block">Jurnal Penyesuaian Selisih</small>
                                    <a href="../z_acc_jurnal/index.php?q=<?= urlencode($opname['no_jurnal']) ?>" class="btn btn-outline-info btn-sm btn-block font-weight-bold mt-1">
                                        <i class="fas fa-book mr-1"></i> Buka Jurnal: <?= htmlspecialchars($opname['no_jurnal']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($opname['bukti_foto'])): ?>
                                <hr>
                                <div>
                                    <small class="text-muted font-weight-bold d-block mb-2">Lampiran Bukti Fisik / Berita Acara</small>
                                    <a href="../uploads/cash_opname/<?= urlencode($opname['bukti_foto']) ?>" target="_blank" class="btn btn-light border btn-sm btn-block">
                                        <i class="fas fa-image mr-1 text-primary"></i> Buka File Bukti Fisik
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($opname['status'] === 'Draft'): ?>
                        <div class="card-footer bg-light p-3">
                            <form action="action.php" method="POST">
                                <input type="hidden" name="action" value="approve_opname">
                                <input type="hidden" name="id_opname" value="<?= $opname['id_opname'] ?>">
                                <button type="submit" class="btn btn-success btn-block font-weight-bold">
                                    <i class="fas fa-check-circle mr-1"></i> Setujui & Sahkan Cash Opname
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
</body>
</html>
