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

$user_id = (int)$_SESSION['id_user'];
$user_name = $_SESSION['nama_user'] ?? 'Petugas';

// Ambil cabang PPX
$cab_q = mysqli_query($conn, "SELECT id_cabang_ppx, kode_cabang, nama_cabang FROM ppx_master_cabang WHERE status = 'Aktif' ORDER BY nama_cabang ASC");
$all_cabang = [];
while ($c = mysqli_fetch_assoc($cab_q)) $all_cabang[] = $c;

// Akun kas COA
$kas_coa_q = mysqli_query($conn, "SELECT id_coa, kode_akun, nama_akun FROM acc_coa WHERE kode_akun LIKE '1101%' AND is_header = 0 ORDER BY kode_akun ASC");
$all_kas_coa = [];
while ($kc = mysqli_fetch_assoc($kas_coa_q)) $all_kas_coa[] = $kc;

// Default periode minggu
$day = (int)date('d');
$week_num = ceil($day / 7);
$month_name = date('F Y');
$default_week = "Minggu ke-{$week_num} ({$month_name})";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form Cash Opname Mingguan – Accounting PT DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .denom-card { background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 16px; margin-bottom: 20px; }
        .denom-header { font-weight: 700; font-size: 14px; color: #1e293b; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
        .denom-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .denom-label { font-weight: 600; font-size: 13.5px; width: 140px; color: #334155; }
        .denom-input { width: 100px; text-align: right; font-weight: 700; }
        .denom-subtotal { font-weight: 700; font-size: 13.5px; width: 160px; text-align: right; color: #0f172a; }
        
        .summary-box { background: #f8fafc; border-radius: 12px; border: 2px dashed #cbd5e1; padding: 20px; text-align: center; }
        .status-banner { border-radius: 8px; padding: 12px 16px; font-weight: 700; font-size: 14px; margin-top: 14px; display: none; }
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
                            <i class="fas fa-coins text-warning mr-2"></i> Form Pemeriksaan Fisik Kas (Cash Opname)
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Cash Opname</a></li>
                            <li class="breadcrumb-item active">Buat Laporan Baru</li>
                        </ul>
                    </div>
                    <div class="col-sm-4 text-right">
                        <a href="index.php" class="btn btn-light border font-weight-bold">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>
            </div>

            <form action="action.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_opname">
                <input type="hidden" name="id_opname" value="0">

                <div class="row">
                    <!-- Kolom Kiri: Informasi Pemeriksaan & Denominasi Fisik -->
                    <div class="col-lg-8">
                        <!-- Informasi Umum -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h5 class="card-title font-weight-bold mb-0 text-dark">
                                    <i class="fas fa-info-circle text-primary mr-1"></i> Data Pemeriksaan Kas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="small font-weight-bold">Cabang yang Diperiksa <span class="text-danger">*</span></label>
                                        <select name="id_cabang_ppx" class="form-control font-weight-bold" required>
                                            <option value="">-- Pilih Cabang --</option>
                                            <?php foreach ($all_cabang as $cb): ?>
                                                <option value="<?= $cb['id_cabang_ppx'] ?>">
                                                    [<?= $cb['kode_cabang'] ?>] <?= htmlspecialchars($cb['nama_cabang']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="small font-weight-bold">Akun Kas yang Diperiksa</label>
                                        <select name="id_coa_kas" class="form-control">
                                            <option value="">-- Pilih Akun Kas COA --</option>
                                            <?php foreach ($all_kas_coa as $kc): ?>
                                                <option value="<?= $kc['id_coa'] ?>">
                                                    [<?= $kc['kode_akun'] ?>] <?= htmlspecialchars($kc['nama_akun']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="small font-weight-bold">Tanggal Opname <span class="text-danger">*</span></label>
                                        <input type="date" name="tanggal_opname" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="small font-weight-bold">Periode Minggu <span class="text-danger">*</span></label>
                                        <input type="text" name="periode_minggu" class="form-control" value="<?= htmlspecialchars($default_week) ?>" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="small font-weight-bold">Saldo Buku Kas (Sistem) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend"><span class="input-group-text font-weight-bold">Rp</span></div>
                                            <input type="text" name="saldo_buku" id="saldoBuku" class="form-control font-weight-bold text-right" placeholder="0" value="0" oninput="formatRupiahInput(this); calculateDenominations();" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kalkulator Rincian Pecahan Uang Fisik -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="card-title font-weight-bold mb-0 text-dark">
                                    <i class="fas fa-calculator text-success mr-1"></i> Penghitungan Fisik Uang di Brankas (Denominasi)
                                </h5>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDenominations()">
                                    <i class="fas fa-undo mr-1"></i> Reset Nol
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Uang Kertas -->
                                    <div class="col-md-6">
                                        <div class="denom-card">
                                            <div class="denom-header text-primary">
                                                <i class="fas fa-money-bill-wave mr-1"></i> Uang Kertas
                                            </div>
                                            <?php
                                            $kertas = [
                                                ['key' => 'k_100k', 'val' => 100000, 'label' => 'Rp 100.000'],
                                                ['key' => 'k_50k',  'val' => 50000,  'label' => 'Rp 50.000'],
                                                ['key' => 'k_20k',  'val' => 20000,  'label' => 'Rp 20.000'],
                                                ['key' => 'k_10k',  'val' => 10000,  'label' => 'Rp 10.000'],
                                                ['key' => 'k_5k',   'val' => 5000,   'label' => 'Rp 5.000'],
                                                ['key' => 'k_2k',   'val' => 2000,   'label' => 'Rp 2.000'],
                                                ['key' => 'k_1k',   'val' => 1000,   'label' => 'Rp 1.000'],
                                            ];
                                            foreach ($kertas as $item): ?>
                                                <div class="denom-row">
                                                    <span class="denom-label"><?= $item['label'] ?></span>
                                                    <span class="text-muted small mr-1">&times;</span>
                                                    <input type="number" min="0" name="<?= $item['key'] ?>" id="<?= $item['key'] ?>" class="form-control form-control-sm denom-input" value="0" data-value="<?= $item['val'] ?>" oninput="calculateDenominations()">
                                                    <span class="denom-subtotal" id="sub_<?= $item['key'] ?>">Rp 0</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Uang Logam / Koin -->
                                    <div class="col-md-6">
                                        <div class="denom-card">
                                            <div class="denom-header text-warning">
                                                <i class="fas fa-coins mr-1"></i> Uang Logam (Koin)
                                            </div>
                                            <?php
                                            $koin = [
                                                ['key' => 'c_1000', 'val' => 1000, 'label' => 'Rp 1.000 (Koin)'],
                                                ['key' => 'c_500',  'val' => 500,  'label' => 'Rp 500 (Koin)'],
                                                ['key' => 'c_200',  'val' => 200,  'label' => 'Rp 200 (Koin)'],
                                                ['key' => 'c_100',  'val' => 100,  'label' => 'Rp 100 (Koin)'],
                                            ];
                                            foreach ($koin as $item): ?>
                                                <div class="denom-row">
                                                    <span class="denom-label"><?= $item['label'] ?></span>
                                                    <span class="text-muted small mr-1">&times;</span>
                                                    <input type="number" min="0" name="<?= $item['key'] ?>" id="<?= $item['key'] ?>" class="form-control form-control-sm denom-input" value="0" data-value="<?= $item['val'] ?>" oninput="calculateDenominations()">
                                                    <span class="denom-subtotal" id="sub_<?= $item['key'] ?>">Rp 0</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Summary Selisih & Pengesahan -->
                    <div class="col-lg-4">
                        <!-- Summary Selisih Box -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h5 class="card-title font-weight-bold mb-0 text-dark">
                                    <i class="fas fa-balance-scale text-info mr-1"></i> Hasil Perbandingan Kas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="summary-box">
                                    <div class="small font-weight-bold text-muted text-uppercase">Total Fisik Kas di Brankas</div>
                                    <div class="h3 font-weight-bold text-dark mt-1 mb-3" id="displayTotalFisik">Rp 0</div>

                                    <div class="small font-weight-bold text-muted text-uppercase">Saldo Buku Kas (Sistem)</div>
                                    <div class="h4 font-weight-bold text-secondary mt-1 mb-3" id="displayTotalBuku">Rp 0</div>

                                    <hr class="my-3">

                                    <div class="small font-weight-bold text-muted text-uppercase">Selisih Kas (Fisik - Buku)</div>
                                    <div class="h3 font-weight-bold mt-1" id="displaySelisih">Rp 0</div>

                                    <div id="statusBannerKlop" class="status-banner bg-success text-white">
                                        <i class="fas fa-check-circle mr-1"></i> KAS KLOP / SESUAI (Rp 0)
                                    </div>
                                    <div id="statusBannerKurang" class="status-banner bg-danger text-white">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> SELISIH KURANG (Fisik < Buku)
                                    </div>
                                    <div id="statusBannerLebih" class="status-banner bg-info text-white">
                                        <i class="fas fa-info-circle mr-1"></i> SELISIH LEBIH (Fisik > Buku)
                                    </div>
                                </div>

                                <div class="form-group mt-3">
                                    <label class="small font-weight-bold">Penjelasan Selisih (Jika Ada)</label>
                                    <textarea name="keterangan_selisih" class="form-control" rows="2" placeholder="Jelaskan alasan jika terdapat selisih kurang atau lebih..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Petugas & Bukti Fisik -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h5 class="card-title font-weight-bold mb-0 text-dark">
                                    <i class="fas fa-user-check text-secondary mr-1"></i> Pemeriksa & Bukti Fisik
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Nama Petugas Pemeriksa <span class="text-danger">*</span></label>
                                    <input type="text" name="petugas_opname" class="form-control font-weight-bold" value="<?= htmlspecialchars($user_name) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="small font-weight-bold">Nama Saksi Pemeriksa</label>
                                    <input type="text" name="saksi_opname" class="form-control" placeholder="Nama saksi / staff lain">
                                </div>
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold">Foto Fisik Uang / Berita Acara</label>
                                    <input type="file" name="bukti_foto" class="form-control-file border p-2 rounded" accept=".jpg,.jpeg,.png,.pdf">
                                    <small class="text-muted">Foto fisik uang di brankas atau scan berita acara bertanda tangan.</small>
                                </div>
                            </div>
                            <div class="card-footer bg-light p-3">
                                <button type="submit" name="submit_action" value="draft" class="btn btn-outline-primary btn-block font-weight-bold mb-2">
                                    <i class="fas fa-save mr-1"></i> Simpan Sebagai Draft
                                </button>
                                <button type="submit" name="submit_action" value="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm" style="background:#1e3a8a; border-color:#1e3a8a;">
                                    <i class="fas fa-check-circle mr-1"></i> Simpan & Sahkan Hasil Opname
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
<script>
function formatRupiahInput(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (val === '') {
        input.value = '0';
        return;
    }
    input.value = new Intl.NumberFormat('id-ID').format(val);
}

function calculateDenominations() {
    let totalFisik = 0;
    const inputs = document.querySelectorAll('.denom-input');
    inputs.forEach(inp => {
        const qty = parseInt(inp.value) || 0;
        const val = parseInt(inp.getAttribute('data-value')) || 0;
        const sub = qty * val;
        totalFisik += sub;
        const subEl = document.getElementById('sub_' + inp.id);
        if (subEl) {
            subEl.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(sub);
        }
    });

    const saldoBukuRaw = document.getElementById('saldoBuku').value.replace(/\./g, '');
    const saldoBuku = parseFloat(saldoBukuRaw) || 0;
    const selisih = totalFisik - saldoBuku;

    document.getElementById('displayTotalFisik').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalFisik);
    document.getElementById('displayTotalBuku').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(saldoBuku);

    const selisihEl = document.getElementById('displaySelisih');
    const bannerKlop = document.getElementById('statusBannerKlop');
    const bannerKurang = document.getElementById('statusBannerKurang');
    const bannerLebih = document.getElementById('statusBannerLebih');

    bannerKlop.style.display = 'none';
    bannerKurang.style.display = 'none';
    bannerLebih.style.display = 'none';

    if (Math.abs(selisih) < 0.01) {
        selisihEl.textContent = 'Rp 0';
        selisihEl.className = 'h3 font-weight-bold mt-1 text-success';
        bannerKlop.style.display = 'block';
    } else if (selisih < 0) {
        selisihEl.textContent = '- Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(selisih));
        selisihEl.className = 'h3 font-weight-bold mt-1 text-danger';
        bannerKurang.style.display = 'block';
    } else {
        selisihEl.textContent = '+ Rp ' + new Intl.NumberFormat('id-ID').format(selisih);
        selisihEl.className = 'h3 font-weight-bold mt-1 text-info';
        bannerLebih.style.display = 'block';
    }
}

function resetDenominations() {
    document.querySelectorAll('.denom-input').forEach(inp => inp.value = 0);
    calculateDenominations();
}

$(document).ready(function() {
    calculateDenominations();
});
</script>
</body>
</html>
