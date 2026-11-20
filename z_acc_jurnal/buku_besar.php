<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_budget', 'read');

init_acc_fase3_db($conn);

$id_coa_selected = isset($_GET['id_coa']) ? (int)$_GET['id_coa'] : 0;
$start_date = trim($_GET['start_date'] ?? date('Y-01-01'));
$end_date   = trim($_GET['end_date'] ?? date('Y-m-d'));

// Ambil list COA postable untuk dropdown
$coa_list_q = mysqli_query($conn, "SELECT id_coa, kode_akun, nama_akun, kategori_akun, tipe_saldo FROM acc_coa WHERE status = 'Aktif' AND is_header = 0 ORDER BY kode_akun ASC");
$all_coa = [];
while ($c = mysqli_fetch_assoc($coa_list_q)) {
    $all_coa[] = $c;
}

// Jika belum memilih akun, default ke akun pertama
if ($id_coa_selected <= 0 && !empty($all_coa)) {
    $id_coa_selected = (int)$all_coa[0]['id_coa'];
}

// Ambil detail akun terpilih
$selected_coa = null;
foreach ($all_coa as $c) {
    if ($c['id_coa'] == $id_coa_selected) {
        $selected_coa = $c;
        break;
    }
}

$saldo_awal = 0;
$ledger_rows = [];
$total_debit_periode = 0;
$total_kredit_periode = 0;

if ($selected_coa) {
    $is_normal_debit = ($selected_coa['tipe_saldo'] === 'Debit');

    // 1. Hitung Saldo Awal (sebelum start_date)
    $q_awal = mysqli_query($conn, "
        SELECT 
            SUM(CASE WHEN jd.posisi = 'Debit' THEN jd.nominal ELSE 0 END) as tot_deb,
            SUM(CASE WHEN jd.posisi = 'Kredit' THEN jd.nominal ELSE 0 END) as tot_kred
        FROM acc_jurnal_detail jd
        JOIN acc_jurnal_header jh ON jd.id_jurnal = jh.id_jurnal
        WHERE jd.id_coa = $id_coa_selected AND jh.tanggal_jurnal < '$start_date'
    ");
    if ($q_awal && $ra = mysqli_fetch_assoc($q_awal)) {
        $deb_awal = (float)($ra['tot_deb'] ?? 0);
        $kred_awal = (float)($ra['tot_kred'] ?? 0);
        $saldo_awal = $is_normal_debit ? ($deb_awal - $kred_awal) : ($kred_awal - $deb_awal);
    }

    // 2. Ambil transaksi pada periode terpilih
    $q_trans = mysqli_query($conn, "
        SELECT jd.*, jh.no_jurnal, jh.tanggal_jurnal, jh.tipe_jurnal, jh.referensi_no, jh.keterangan AS header_ket
        FROM acc_jurnal_detail jd
        JOIN acc_jurnal_header jh ON jd.id_jurnal = jh.id_jurnal
        WHERE jd.id_coa = $id_coa_selected
          AND jh.tanggal_jurnal >= '$start_date'
          AND jh.tanggal_jurnal <= '$end_date'
        ORDER BY jh.tanggal_jurnal ASC, jh.id_jurnal ASC, jd.id_jurnal_detail ASC
    ");

    $running_balance = $saldo_awal;
    if ($q_trans) {
        while ($t = mysqli_fetch_assoc($q_trans)) {
            $nom = (float)$t['nominal'];
            $pos = $t['posisi'];

            if ($pos === 'Debit') {
                $total_debit_periode += $nom;
                $running_balance += $is_normal_debit ? $nom : -$nom;
            } else {
                $total_kredit_periode += $nom;
                $running_balance += $is_normal_debit ? -$nom : $nom;
            }

            $t['running_balance'] = $running_balance;
            $ledger_rows[] = $t;
        }
    }
}
$saldo_akhir = $selected_coa ? ($is_normal_debit ? ($saldo_awal + $total_debit_periode - $total_kredit_periode) : ($saldo_awal + $total_kredit_periode - $total_debit_periode)) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buku Besar (General Ledger) – Accounting PT DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-ledger th { background: #f8fafc; font-size: 13px; font-weight: 700; color: #334155; }
        .table-ledger td { font-size: 13px; vertical-align: middle; }
        .stat-card { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; padding: 16px 20px; }
        .stat-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #64748b; }
        .stat-val { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        @media print {
            .navbar, .sidebar, .page-header, .filter-box, .btn-print-box { display: none !important; }
            .content { margin: 0; padding: 0; }
            .card { border: none !important; box-shadow: none !important; }
        }
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
                    <div class="col-sm-7">
                        <h3 class="page-title text-dark font-weight-bold">
                            <i class="fas fa-layer-group text-info mr-2"></i> Buku Besar Akun (General Ledger)
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Jurnal Umum</a></li>
                            <li class="breadcrumb-item active">Buku Besar</li>
                        </ul>
                    </div>
                    <div class="col-sm-5 text-right btn-print-box">
                        <button type="button" class="btn btn-secondary font-weight-bold mr-2 shadow-sm" onclick="window.print()">
                            <i class="fas fa-print mr-1"></i> Cetak Ledger
                        </button>
                        <a href="index.php" class="btn btn-light border font-weight-bold">
                            <i class="fas fa-arrow-left mr-1"></i> Daftar Jurnal
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter Box -->
            <div class="card border-0 shadow-sm mb-4 filter-box" style="border-radius:10px;">
                <div class="card-body py-3">
                    <form method="GET" action="buku_besar.php" class="row align-items-center">
                        <div class="col-md-5 col-sm-12 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Pilih Akun Buku Besar (COA)</label>
                            <select name="id_coa" class="form-control select2-ledger" onchange="this.form.submit()">
                                <?php foreach ($all_coa as $c): ?>
                                    <option value="<?= $c['id_coa'] ?>" <?= ($id_coa_selected == $c['id_coa']) ? 'selected' : '' ?>>
                                        [<?= $c['kode_akun'] ?>] <?= htmlspecialchars($c['nama_akun']) ?> (<?= $c['kategori_akun'] ?> &bull; Saldo: <?= $c['tipe_saldo'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Dari Tanggal</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-1">Sampai Tanggal</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-md-1 text-right">
                            <label class="small d-block mb-1">&nbsp;</label>
                            <button type="submit" class="btn btn-sm btn-primary btn-block font-weight-bold"><i class="fas fa-filter"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Header Info Akun & Stats -->
            <?php if ($selected_coa): ?>
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label">Saldo Awal (per <?= date('d/m/Y', strtotime($start_date)) ?>)</div>
                        <div class="stat-val text-secondary">Rp <?= number_format($saldo_awal, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label">Total Mutasi Debit</div>
                        <div class="stat-val text-info">+ Rp <?= number_format($total_debit_periode, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label">Total Mutasi Kredit</div>
                        <div class="stat-val text-primary">- Rp <?= number_format($total_kredit_periode, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card">
                        <div class="stat-label">Saldo Akhir Berjalan</div>
                        <div class="stat-val text-success">Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="card border-0 shadow-sm" style="border-radius:10px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="card-title font-weight-bold mb-0 text-dark">
                            [<?= htmlspecialchars($selected_coa['kode_akun']) ?>] <?= htmlspecialchars($selected_coa['nama_akun']) ?>
                        </h5>
                        <small class="text-muted">
                            Kategori: <?= htmlspecialchars($selected_coa['kategori_akun']) ?> &bull; Saldo Normal: <?= htmlspecialchars($selected_coa['tipe_saldo']) ?>
                        </small>
                    </div>
                    <span class="badge badge-light border text-muted">
                        Periode: <?= date('d M Y', strtotime($start_date)) ?> – <?= date('d M Y', strtotime($end_date)) ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-ledger">
                            <thead>
                                <tr>
                                    <th width="10%">Tanggal</th>
                                    <th width="14%">No Jurnal</th>
                                    <th width="10%">Tipe</th>
                                    <th width="12%">No Ref</th>
                                    <th width="24%">Keterangan & Memo</th>
                                    <th width="10%" class="text-right">Debit (Rp)</th>
                                    <th width="10%" class="text-right">Kredit (Rp)</th>
                                    <th width="10%" class="text-right">Saldo Berjalan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Baris Saldo Awal -->
                                <tr style="background: #f8fafc; font-weight: 700;">
                                    <td><?= date('d/m/Y', strtotime($start_date)) ?></td>
                                    <td colspan="4" class="text-muted font-italic">Saldo Awal Sebelum Periode Ini</td>
                                    <td class="text-right">–</td>
                                    <td class="text-right">–</td>
                                    <td class="text-right text-dark">Rp <?= number_format($saldo_awal, 0, ',', '.') ?></td>
                                </tr>

                                <?php if (empty($ledger_rows)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            Tidak ada mutasi transaksi pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ledger_rows as $lr): 
                                        $deb = ($lr['posisi'] === 'Debit') ? (float)$lr['nominal'] : 0;
                                        $kred = ($lr['posisi'] === 'Kredit') ? (float)$lr['nominal'] : 0;
                                    ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($lr['tanggal_jurnal'])) ?></td>
                                        <td class="font-weight-bold text-dark">
                                            <a href="index.php?q=<?= urlencode($lr['no_jurnal']) ?>" class="text-primary">
                                                <?= htmlspecialchars($lr['no_jurnal']) ?>
                                            </a>
                                        </td>
                                        <td><span class="badge badge-light border"><?= htmlspecialchars($lr['tipe_jurnal']) ?></span></td>
                                        <td><?= htmlspecialchars($lr['referensi_no'] ?: '–') ?></td>
                                        <td>
                                            <?= htmlspecialchars($lr['header_ket']) ?>
                                            <?php if (!empty($lr['memo'])): ?>
                                                <br><small class="text-muted font-italic"><?= htmlspecialchars($lr['memo']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right font-weight-bold text-info">
                                            <?= ($deb > 0) ? number_format($deb, 0, ',', '.') : '–' ?>
                                        </td>
                                        <td class="text-right font-weight-bold text-primary">
                                            <?= ($kred > 0) ? number_format($kred, 0, ',', '.') : '–' ?>
                                        </td>
                                        <td class="text-right font-weight-bold text-dark">
                                            Rp <?= number_format((float)$lr['running_balance'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-light font-weight-bold">
                                    <td colspan="5" class="text-right">TOTAL MUTASI PERIODE INI:</td>
                                    <td class="text-right text-info">Rp <?= number_format($total_debit_periode, 0, ',', '.') ?></td>
                                    <td class="text-right text-primary">Rp <?= number_format($total_kredit_periode, 0, ',', '.') ?></td>
                                    <td class="text-right text-success" style="font-size: 14px;">Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
<script src="../assets/plugins/select2/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2-ledger').select2({
            width: '100%'
        });
    }
});
</script>
</body>
</html>
