<?php
/**
 * Realisasi Pengajuan PPX (Non Budget) – Standar Transaksi Kas BKK & BKM
 * ─────────────────────────────────────────────────────────────────────────────
 */
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$no_ppx = trim($_GET['no_ppx'] ?? '');
if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$user_id  = (int)$_SESSION['id_user'];
$is_admin = ($auth->hasRole('admin') || $auth->hasRole('finance'));

// Ambil header data PPX
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
WHERE pr.no_ppx = ? " . ($is_admin ? "" : "AND pr.user_id = ?");

$stmt = mysqli_prepare($conn, $query_header);
if ($is_admin) {
    mysqli_stmt_bind_param($stmt, 's', $no_ppx);
} else {
    mysqli_stmt_bind_param($stmt, 'si', $no_ppx, $user_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan atau Anda tidak memiliki akses!';
    header("Location: dashboard.php");
    exit();
}
$ppx_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Ambil daftar detail items rencana PPX
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
ORDER BY no_ppx_detail ASC";

$stmt_detail = mysqli_prepare($conn, $query_detail);
mysqli_stmt_bind_param($stmt_detail, 's', $no_ppx);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

$items = [];
$total_nominal_rencana = 0;
while ($row = mysqli_fetch_assoc($result_detail)) {
    $items[] = $row;
    $total_nominal_rencana += (float)$row['total_harga'];
}
mysqli_stmt_close($stmt_detail);

// Ambil riwayat transaksi kas realisasi untuk PPX ini
$q_reals = mysqli_query($conn, "SELECT * FROM ppx_realisasi WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "' ORDER BY tanggal_realisasi DESC, id_realisasi DESC");
$realisasi_list = [];
$total_bkk = 0;
$total_bkm = 0;
$item_realized_counts = [];

if ($q_reals) {
    while ($r = mysqli_fetch_assoc($q_reals)) {
        $realisasi_list[] = $r;
        $j = $r['jenis_transaksi'] ?? 'BKK';
        if ($j === 'BKM') {
            $total_bkm += (float)($r['uang_kembali'] ?: $r['total_realisasi']);
        } else {
            $total_bkk += (float)$r['total_realisasi'];
        }

        // Hitung pemakaian item rencana dari JSON detail_barang
        if (!empty($r['detail_barang'])) {
            $dt = json_decode($r['detail_barang'], true);
            if (is_array($dt)) {
                foreach ($dt as $d_it) {
                    $pid = (int)($d_it['no_ppx_detail'] ?? 0);
                    if ($pid > 0) {
                        $item_realized_counts[$pid] = ($item_realized_counts[$pid] ?? 0) + (float)($d_it['total_harga'] ?? 0);
                    }
                }
            }
        }
    }
}

$uang_muka = (float)$ppx_data['uang_muka'];
// Sisa uang muka = Uang Muka - Pengeluaran (BKK) + Pengembalian (BKM)
$sisa_uang_muka = $uang_muka - $total_bkk + $total_bkm;

// Ambil daftar master akun untuk dropdown
$q_akun = mysqli_query($conn, "SELECT no_akun, nama_akun FROM master_akun ORDER BY no_akun ASC");
$master_akun_list = [];
if ($q_akun) {
    while ($ra = mysqli_fetch_assoc($q_akun)) {
        $master_akun_list[] = $ra;
    }
}

// Data items untuk JavaScript dropdown
$js_ppx_items = array_map(function($it) use ($item_realized_counts) {
    $real_amt = $item_realized_counts[$it['no_ppx_detail']] ?? 0;
    $sisa_item = max(0, (float)$it['total_harga'] - $real_amt);
    return [
        'id'       => 'ppx_' . $it['no_ppx_detail'],
        'rincian'  => $it['rincian'],
        'no_akun'  => $it['no_akun'],
        'total'    => (float)$it['total_harga'],
        'sisa'     => $sisa_item,
        'group'    => 'Rencana Item PPX'
    ];
}, $items);

$js_master_akun = array_map(function($a) {
    return [
        'id'       => 'akun_' . $a['no_akun'],
        'rincian'  => $a['no_akun'] . ' - ' . $a['nama_akun'],
        'no_akun'  => $a['no_akun'],
        'total'    => 0,
        'sisa'     => 0,
        'group'    => 'Akun Biaya Lainnya'
    ];
}, $master_akun_list);

$all_select_items = array_merge($js_ppx_items, $js_master_akun);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Realisasi Transaksi Kas — <?= htmlspecialchars($no_ppx) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ── 4 Summary Cards Bersih & Kontras Tinggi ── */
        .sc-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-left: 4px solid #cbd5e1 !important;
            border-radius: 10px;
            padding: 14px 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            height: 100%;
            transition: transform .2s, box-shadow .2s;
        }
        .sc-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .sc-card.card-alokasi   { border-left-color: #2563eb !important; background: #f8faff !important; }
        .sc-card.card-realisasi { border-left-color: #059669 !important; background: #f6fdf9 !important; }
        .sc-card.card-menunggu  { border-left-color: #d97706 !important; background: #fffdf5 !important; }
        .sc-card.card-sisa      { border-left-color: #0284c7 !important; background: #f6fbfe !important; }

        .sc-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b !important;
            margin-bottom: 4px;
        }
        .sc-value {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 4px;
        }
        .card-alokasi   .sc-value { color: #1e3a8a !important; }
        .card-realisasi .sc-value { color: #047857 !important; }
        .card-menunggu  .sc-value { color: #b45309 !important; }
        .card-sisa      .sc-value { color: #0369a1 !important; }

        .sc-sub {
            font-size: 11.5px;
            color: #64748b;
        }
        .sc-icon {
            opacity: 0.8;
            padding-left: 8px;
        }

        .info-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 22px;
        }

        .section-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .section-head {
            background: #f8fafc;
            padding: 12px 18px;
            font-weight: 700;
            font-size: 14px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ── Modal BKK/BKM Styling (Persis sesuai gambar standar) ── */
        .modal-kas-title { font-weight: 700; font-size: 18px; color: #0f172a; }
        .modal-kas-sub   { font-size: 12px; color: #64748b; font-weight: 600; }
        
        .kas-tab-group   { display: flex; gap: 12px; margin-bottom: 18px; }
        .kas-tab-btn     { flex: 1; padding: 12px 16px; border-radius: 8px; font-weight: 700; font-size: 13.5px;
                           border: 2px solid #cbd5e1; background: #fff; color: #334155; text-align: center; cursor: pointer; transition: all .2s; }
        .kas-tab-btn.active-bkk { background: #1e3a8a; border-color: #1e3a8a; color: #fff; box-shadow: 0 2px 6px rgba(30,58,138,0.25); }
        .kas-tab-btn.active-bkm { background: #047857; border-color: #047857; color: #fff; box-shadow: 0 2px 6px rgba(4,120,87,0.25); }

        .drop-zone {
            border: 2px dashed #94a3b8; border-radius: 8px; padding: 22px 16px; text-align: center;
            background: #f8fafc; cursor: pointer; transition: background .2s, border-color .2s;
        }
        .drop-zone:hover { background: #f1f5f9; border-color: #3b82f6; }
        
        .rincian-table th { background: #1e3a8a; color: #fff; padding: 9px 12px; font-size: 12px; font-weight: 600; border: none; }
        .rincian-table td { padding: 8px 10px; vertical-align: middle; background: #fff; }
        .rincian-table.table-bkm th { background: #047857; }

        /* ── Select2 Custom Styling for Table Cell ── */
        .select2-container { width: 100% !important; min-width: 0 !important; }
        .select2-container--default .select2-selection--single {
            height: 31px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background-color: #fff;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b;
            font-size: 12px;
            line-height: 29px;
            padding-left: 8px;
            padding-right: 24px;
            width: 100%;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px;
            right: 6px;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 18px;
            color: #94a3b8;
            font-size: 13px;
        }
        .select2-dropdown {
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            font-size: 12px;
            z-index: 1065 !important;
        }
        .select2-container--default .select2-search--dropdown {
            padding: 6px 8px;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 5px 8px;
            font-size: 12px;
            outline: none;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #1e3a8a;
            color: #fff;
        }
        .select2-container--default .select2-results__group {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #f8fafc;
            padding: 4px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .prev-item { background: #e0f2fe; border-radius: 5px; padding: 4px 10px; font-size: 11.5px; color: #0369a1; display: inline-block; margin: 3px; }
        .bukti-thumb { width: 44px; height: 38px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1; cursor: pointer; }
    </style>
</head>
<body style="background: #f4f6f9;">
<div class="main-wrapper">
    <?php include __DIR__ . '/../layouts/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-1">
                            <i class="fa-solid fa-receipt text-primary mr-1"></i> Realisasi Transaksi Kas (BKK / BKM)
                        </h3>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan Non Budget</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($no_ppx) ?></li>
                        </ul>
                    </div>
                    <div class="col-auto d-flex" style="gap:8px;">
                        <button type="button" class="btn btn-primary font-weight-bold" onclick="openKasModal('BKK')">
                            <i class="fas fa-plus-circle mr-1"></i> Input Transaksi Kas (BKK / BKM)
                        </button>
                        
                        <?php if (count($realisasi_list) > 0 && $ppx_data['status'] === 'Uang Muka'): ?>
                        <form action="realisasi_submit.php" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menyelesaikan realisasi PPX ini dan meneruskannya ke Finance?');">
                            <input type="hidden" name="no_ppx" value="<?= htmlspecialchars($no_ppx) ?>">
                            <input type="hidden" name="action" value="finalize_ppx">
                            <button type="submit" class="btn btn-success font-weight-bold">
                                <i class="fas fa-check-double mr-1"></i> Selesaikan Realisasi PPX
                            </button>
                        </form>
                        <?php endif; ?>

                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>

            <!-- Flash Alert -->
            <?php foreach (['success', 'error'] as $t): if (isset($_SESSION[$t])): ?>
            <div class="alert alert-<?= $t === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <i class="fas fa-<?= $t === 'success' ? 'check' : 'exclamation' ?>-circle mr-1"></i>
                <?= htmlspecialchars($_SESSION[$t]) ?>
                <button class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION[$t]); endif; endforeach; ?>

            <!-- Info PPX Header -->
            <div class="info-box">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
                    <div>
                        <h5 class="mb-1 font-weight-bold text-dark">
                            <?= htmlspecialchars($no_ppx) ?>
                        </h5>
                        <span class="text-muted small">
                            <i class="far fa-calendar-alt mr-1"></i> Tgl Pengajuan: <strong><?= date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) ?></strong>
                            &nbsp;·&nbsp; Cabang: <strong><?= htmlspecialchars($ppx_data['cabang'] ?? '-') ?></strong>
                            &nbsp;·&nbsp; PIC: <strong><?= htmlspecialchars($ppx_data['nama_pic'] ?? '-') ?> (<?= htmlspecialchars($ppx_data['jabatan'] ?? '-') ?>)</strong>
                            &nbsp;·&nbsp; Divisi: <strong><?= htmlspecialchars($ppx_data['nama_divisi'] ?? '-') ?></strong>
                        </span>
                    </div>
                    <div>
                        <span class="badge p-2 px-3 font-weight-bold" style="font-size:12px; <?= $ppx_data['status'] === 'Uang Muka' ? 'background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe;' : 'background:#dcfce7; color:#15803d; border:1px solid #86efac;' ?>">
                            <i class="fas fa-hand-holding-dollar mr-1"></i> Status PPX: <?= htmlspecialchars($ppx_data['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- 4 Summary Cards Bersih & Kontras Tinggi -->
                <div class="row mt-3">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-alokasi">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Total Pengajuan PPX</div>
                                    <div class="sc-value">Rp <?= number_format($total_nominal_rencana, 0, ',', '.') ?></div>
                                    <div class="sc-sub"><i class="fa-solid fa-layer-group mr-1"></i><?= count($items) ?> Item Rencana</div>
                                </div>
                                <div class="sc-icon" style="color:#2563eb;">
                                    <i class="fa-solid fa-file-invoice fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-sisa">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Uang Muka Diterima</div>
                                    <div class="sc-value">Rp <?= number_format($uang_muka, 0, ',', '.') ?></div>
                                    <div class="sc-sub"><i class="fa-solid fa-building-columns mr-1"></i>Dana dari Finance</div>
                                </div>
                                <div class="sc-icon" style="color:#0284c7;">
                                    <i class="fa-solid fa-hand-holding-dollar fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-realisasi">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Realisasi Belanja (BKK)</div>
                                    <div class="sc-value">Rp <?= number_format($total_bkk, 0, ',', '.') ?></div>
                                    <div class="sc-sub font-weight-bold" style="color:#059669;"><i class="fa-solid fa-receipt mr-1"></i><?= count($realisasi_list) ?> Bukti Kas</div>
                                </div>
                                <div class="sc-icon" style="color:#059669;">
                                    <i class="fa-solid fa-receipt fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="sc-card card-menunggu">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="sc-label">Sisa Uang Muka</div>
                                    <div class="sc-value <?= $sisa_uang_muka < 0 ? 'text-danger' : '' ?>">
                                        <?= $sisa_uang_muka < 0 ? '− Rp ' . number_format(abs($sisa_uang_muka), 0, ',', '.') : 'Rp ' . number_format($sisa_uang_muka, 0, ',', '.') ?>
                                    </div>
                                    <div class="sc-sub" style="color:<?= $sisa_uang_muka < 0 ? '#dc2626' : '#b45309' ?>;">
                                        <i class="fa-solid fa-<?= $sisa_uang_muka < 0 ? 'triangle-exclamation' : 'check-circle' ?> mr-1"></i>
                                        <?= $sisa_uang_muka < 0 ? 'Kurang (Uang Pribadi)' : ($sisa_uang_muka == 0 ? 'Pas / Tuntas' : 'Ada Sisa Uang') ?>
                                    </div>
                                </div>
                                <div class="sc-icon" style="color:<?= $sisa_uang_muka < 0 ? '#dc2626' : '#d97706' ?>;">
                                    <i class="fa-solid fa-scale-balanced fa-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABEL 1: DAFTAR RENCANA ITEM PENGAJUAN PPX -->
            <div class="section-card">
                <div class="section-head">
                    <span style="color:#1e293b; font-weight:700;">
                        <i class="fas fa-list-check mr-2" style="color:#2563eb;"></i> 1. Rencana Item Barang / Biaya yang Diajukan
                    </span>
                    <span class="text-muted small"><?= count($items) ?> Item Rencana</span>
                </div>
                <div class="p-3">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th width="4%">No</th>
                                    <th width="14%">No. Akun</th>
                                    <th>Rincian Barang / Kebutuhan</th>
                                    <th width="12%">Volume</th>
                                    <th width="15%" class="text-right">Harga Satuan</th>
                                    <th width="16%" class="text-right">Total Rencana</th>
                                    <th width="14%" class="text-center">Status Belanja</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $idx => $it): 
                                    $it_spent = $item_realized_counts[$it['no_ppx_detail']] ?? 0;
                                    $is_done  = ($it_spent >= (float)$it['total_harga'] && (float)$it['total_harga'] > 0);
                                ?>
                                <tr>
                                    <td class="text-center"><?= $idx + 1 ?></td>
                                    <td><span class="badge badge-light border"><?= htmlspecialchars($it['no_akun']) ?></span></td>
                                    <td>
                                        <strong style="color:#1e293b;"><?= htmlspecialchars($it['rincian']) ?></strong>
                                        <?php if (!empty($it['deskripsi'])): ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($it['deskripsi']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $it['jumlah'] ?> <?= htmlspecialchars($it['satuan']) ?></td>
                                    <td class="text-right">Rp <?= number_format($it['harga_satuan'], 0, ',', '.') ?></td>
                                    <td class="text-right font-weight-bold" style="color:#1e3a8a;">Rp <?= number_format($it['total_harga'], 0, ',', '.') ?></td>
                                    <td class="text-center">
                                        <?php if ($is_done): ?>
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Terealisasi</span>
                                        <?php elseif ($it_spent > 0): ?>
                                            <span class="badge badge-info px-2 py-1"><i class="fas fa-pie-chart mr-1"></i> Sebagian (Rp <?= number_format($it_spent, 0, ',', '.') ?>)</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary px-2 py-1"><i class="fas fa-clock mr-1"></i> Belum Belanja</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TABEL 2: RIWAYAT TRANSAKSI KAS REALISASI (BKK / BKM) -->
            <div class="section-card">
                <div class="section-head">
                    <span style="color:#1e293b; font-weight:700;">
                        <i class="fas fa-receipt mr-2" style="color:#059669;"></i> 2. Riwayat Transaksi Kas Masuk & Keluar (BKK / BKM)
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="openKasModal('BKK')">
                        <i class="fas fa-plus mr-1"></i> Tambah Transaksi Kas
                    </button>
                </div>
                <div class="p-3">
                    <?php if (empty($realisasi_list)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-file-invoice-dollar fa-3x mb-2" style="color:#cbd5e1;"></i>
                            <p class="mb-2">Belum ada transaksi kas yang dicatat untuk PPX ini.</p>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openKasModal('BKK')">
                                <i class="fas fa-plus mr-1"></i> Input Transaksi Kas Sekarang
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="4%">No</th>
                                        <th width="8%">Jenis</th>
                                        <th width="14%">No. Transaksi</th>
                                        <th width="10%">Tanggal</th>
                                        <th width="18%">Penerima / Vendor</th>
                                        <th width="18%">Keperluan & Rincian</th>
                                        <th width="13%" class="text-right">Nominal (Rp)</th>
                                        <th width="9%">Bukti Nota</th>
                                        <th width="6%" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($realisasi_list as $i => $rl): 
                                        $j = $rl['jenis_transaksi'] ?? 'BKK';
                                        $amt = ($j === 'BKM') ? (float)($rl['uang_kembali'] ?: $rl['total_realisasi']) : (float)$rl['total_realisasi'];
                                        $files = json_decode($rl['bukti_realisasi'] ?? '[]', true);
                                        if (!is_array($files)) {
                                            $files = explode(',', $rl['bukti_realisasi'] ?? '');
                                        }
                                        $files = array_filter(array_map('trim', $files));
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td>
                                            <?php if ($j === 'BKM'): ?>
                                                <span class="badge badge-success px-2 py-1 font-weight-bold" style="background:#047857; color:#fff;">
                                                    <i class="fas fa-arrow-down mr-1"></i> BKM
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-primary px-2 py-1 font-weight-bold" style="background:#1e3a8a; color:#fff;">
                                                    <i class="fas fa-arrow-up mr-1"></i> BKK
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong class="text-dark"><?= htmlspecialchars($rl['no_transaksi'] ?: ('REAL-' . $rl['id_realisasi'])) ?></strong></td>
                                        <td><?= $rl['tanggal_realisasi'] ? date('d/m/Y', strtotime($rl['tanggal_realisasi'])) : '-' ?></td>
                                        <td>
                                            <strong style="color:#1e293b;"><?= htmlspecialchars($rl['dibayar_kepada'] ?: '-') ?></strong>
                                        </td>
                                        <td>
                                            <div style="font-size:12px; color:#334155;"><?= htmlspecialchars($rl['keperluan'] ?: $rl['keterangan_realisasi'] ?: '-') ?></div>
                                            <?php 
                                            // Tampilkan nama-nama item jika ada di detail_barang
                                            if (!empty($rl['detail_barang'])):
                                                $d_arr = json_decode($rl['detail_barang'], true);
                                                if (is_array($d_arr)):
                                            ?>
                                                <div class="mt-1">
                                                    <?php foreach ($d_arr as $d_sub): ?>
                                                        <span class="badge badge-light border text-muted" style="font-size:10.5px;">
                                                            <?= htmlspecialchars($d_sub['nama_barang'] ?? '') ?>: Rp <?= number_format($d_sub['total_harga'] ?? 0, 0, ',', '.') ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; endif; ?>
                                        </td>
                                        <td class="text-right font-weight-bold" style="color:<?= $j === 'BKM' ? '#047857' : '#1e3a8a' ?>;">
                                            <?= $j === 'BKM' ? '+' : '' ?>Rp <?= number_format($amt, 0, ',', '.') ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($files)): ?>
                                                <?php foreach ($files as $f): 
                                                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                                    $is_img = in_array($ext, ['jpg', 'jpeg', 'png']);
                                                    $url = '../uploads/ppx_realisasi/' . htmlspecialchars($f);
                                                ?>
                                                    <?php if ($is_img): ?>
                                                        <img src="<?= $url ?>" class="bukti-thumb mr-1 mb-1" onclick="previewImg('<?= $url ?>', '<?= htmlspecialchars($f) ?>')" title="<?= htmlspecialchars($f) ?>">
                                                    <?php else: ?>
                                                        <a href="<?= $url ?>" target="_blank" class="btn btn-xs btn-outline-danger mr-1 mb-1" style="font-size:11px; padding:2px 6px;">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <form action="realisasi_submit.php" method="POST" style="display:inline;" onsubmit="return confirm('Hapus transaksi ini?');">
                                                <input type="hidden" name="no_ppx" value="<?= htmlspecialchars($no_ppx) ?>">
                                                <input type="hidden" name="id_realisasi" value="<?= $rl['id_realisasi'] ?>">
                                                <input type="hidden" name="action" value="delete_kas">
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Hapus Transaksi">
                                                    <i class="fas fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL INPUT TRANSAKSI KAS (BKK / BKM) — STANDAR IDENTIK        -->
<!-- ============================================================== -->
<div class="modal fade" id="modalInputKas" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 860px;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            
            <!-- Modal Header -->
            <div class="modal-header px-4 py-3" style="background:#fff; border-bottom: 1px solid #e2e8f0;">
                <div>
                    <h5 class="modal-title modal-kas-title" id="modalKasTitle">Input Transaksi Kas</h5>
                </div>
                <div class="text-right">
                    <div class="modal-kas-sub">
                        PT DUA FARMA MAHAKARSA — Cabang <?= htmlspecialchars($ppx_data['cabang'] ?? 'Jakarta') ?>
                    </div>
                    <small class="text-muted font-weight-bold">No. PPX: <?= htmlspecialchars($no_ppx) ?></small>
                </div>
                <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Close" style="outline:none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Modal Form -->
            <form action="realisasi_submit.php" method="POST" enctype="multipart/form-data" id="formTransaksiKas">
                <input type="hidden" name="no_ppx" value="<?= htmlspecialchars($no_ppx) ?>">
                <input type="hidden" name="action" value="save_kas">
                <input type="hidden" name="jenis_transaksi" id="kasJenisTransaksi" value="BKK">

                <div class="modal-body px-4 py-3" style="background: #ffffff;">

                    <!-- 2 Big Tabs BKK / BKM -->
                    <div class="kas-tab-group">
                        <button type="button" class="kas-tab-btn active-bkk" id="btnTabBKK" onclick="switchKasTab('BKK')">
                            BKK — Bukti Kas Keluar (Pengeluaran)
                        </button>
                        <button type="button" class="kas-tab-btn" id="btnTabBKM" onclick="switchKasTab('BKM')">
                            BKM — Bukti Kas Masuk (Pengembalian Uang Muka)
                        </button>
                    </div>

                    <!-- Row 1: Tanggal, Dibayar Kepada / Diterima Dari, Keperluan -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="small font-weight-bold text-dark mb-1">Tanggal</label>
                            <input type="date" name="tanggal_realisasi" id="kasTanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold text-dark mb-1" id="lblDibayar">Dibayar Kepada</label>
                            <input type="text" name="dibayar_kepada" id="kasDibayar" class="form-control" placeholder="Nama toko / vendor / karyawan" required>
                        </div>
                        <div class="col-md-5">
                            <label class="small font-weight-bold text-dark mb-1">Keperluan</label>
                            <input type="text" name="keperluan" id="kasKeperluan" class="form-control" placeholder="Contoh: Pembelian barang operasional PPX" required>
                        </div>
                    </div>

                    <!-- Section 1: Upload Nota / Kwitansi -->
                    <div class="mb-4">
                        <label class="font-weight-bold text-dark mb-2" style="font-size: 13.5px;">
                            1. Upload Nota / Kwitansi
                        </label>
                        <div class="drop-zone" onclick="document.getElementById('kasFileInput').click()">
                            <i class="fas fa-file-invoice text-primary fa-2x mb-2 d-block"></i>
                            <p class="mb-1 text-muted" style="font-size: 13px;">
                                Klik atau tarik file ke sini (foto JPG/PNG atau PDF, boleh lebih dari satu) — nota digabung ke dalam bukti transaksi
                            </p>
                            <input type="file" id="kasFileInput" name="bukti[]" accept=".jpg,.jpeg,.png,.pdf" multiple style="display:none;" onchange="handleKasFiles(this)">
                            <div id="kasFilePreview" class="d-flex flex-wrap justify-content-center mt-2"></div>
                        </div>
                    </div>

                    <!-- Section 2: Rincian Biaya / Akun -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="font-weight-bold text-dark mb-0" style="font-size: 13.5px;">
                                2. Rincian Biaya / Akun
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addKasRow()">
                                <i class="fas fa-plus mr-1"></i> Tambah Baris
                            </button>
                        </div>

                        <div class="table-responsive border rounded" style="overflow-x: auto;">
                            <table class="table table-sm mb-0 rincian-table" id="tableKasRincian">
                                <thead>
                                    <tr>
                                        <th width="45%">Akun (ketik kode / nama untuk mencari)</th>
                                        <th width="32%">Keterangan</th>
                                        <th width="18%" class="text-right">Jumlah (Rp)</th>
                                        <th width="5%" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyKasRincian">
                                    <!-- Dynamic rows -->
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light font-weight-bold">
                                        <td colspan="2" class="text-right">Total Transaksi:</td>
                                        <td class="text-right text-success" id="kasTotalDisplay" style="font-size: 14px;">Rp 0</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer d-flex justify-content-between align-items-center px-4 py-3" style="background:#f8fafc; border-top: 1px solid #e2e8f0;">
                    <div class="small text-muted font-weight-bold" id="footerAkunKas">
                        Akun kas: Kas Operasional <?= htmlspecialchars($ppx_data['cabang'] ?? 'Jakarta') ?>
                    </div>
                    <div class="d-flex" style="gap: 8px;">
                        <button type="button" class="btn btn-light border px-3" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary px-4 font-weight-bold" id="btnSimpanKas" style="background:#1e3a8a; border-color:#1e3a8a;">
                            Simpan Transaksi
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Modal Image Preview Lightbox -->
<div class="modal fade" id="modalPreviewImg" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0">
            <div class="modal-header py-2 px-3 border-0">
                <span class="small text-muted" id="previewImgTitle"></span>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-2 text-center bg-dark">
                <img src="" id="previewImgSrc" style="max-width:100%; max-height:80vh; object-fit:contain; border-radius:4px;">
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>

<!-- Data Items untuk Dropdown Select2 -->
<script>
const selectItems = <?= json_encode($all_select_items) ?>;

let currentJenis = 'BKK';

function switchKasTab(jenis) {
    currentJenis = jenis;
    document.getElementById('kasJenisTransaksi').value = jenis;
    const btnBKK = document.getElementById('btnTabBKK');
    const btnBKM = document.getElementById('btnTabBKM');
    const tbl = document.getElementById('tableKasRincian');
    const lblDibayar = document.getElementById('lblDibayar');
    const btnSimpan = document.getElementById('btnSimpanKas');

    if (jenis === 'BKK') {
        btnBKK.className = 'kas-tab-btn active-bkk';
        btnBKM.className = 'kas-tab-btn';
        tbl.classList.remove('table-bkm');
        lblDibayar.textContent = 'Dibayar Kepada';
        document.getElementById('kasDibayar').placeholder = 'Nama toko / vendor / karyawan';
        document.getElementById('kasKeperluan').placeholder = 'Contoh: Pembelian barang operasional PPX';
        btnSimpan.style.background = '#1e3a8a';
        btnSimpan.style.borderColor = '#1e3a8a';
    } else {
        btnBKM.className = 'kas-tab-btn active-bkm';
        btnBKK.className = 'kas-tab-btn';
        tbl.classList.add('table-bkm');
        lblDibayar.textContent = 'Diterima Dari';
        document.getElementById('kasDibayar').placeholder = 'Nama penyetor sisa uang muka';
        document.getElementById('kasKeperluan').placeholder = 'Contoh: Pengembalian sisa uang muka PPX';
        btnSimpan.style.background = '#047857';
        btnSimpan.style.borderColor = '#047857';
    }
}

function openKasModal(jenis) {
    document.getElementById('formTransaksiKas').reset();
    document.getElementById('kasFilePreview').innerHTML = '';

    $('#tbodyKasRincian .select-akun').each(function() {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
    });
    document.getElementById('tbodyKasRincian').innerHTML = '';
    document.getElementById('kasTanggal').value = '<?= date('Y-m-d') ?>';

    switchKasTab(jenis || 'BKK');
    addKasRow();

    $('#modalInputKas').modal('show');
}

function addKasRow(selectedId, keteranganVal, jumlahVal) {
    const tbody = document.getElementById('tbodyKasRincian');
    const tr = document.createElement('tr');

    let selectHtml = '<select name="item_detail_id[]" class="form-control form-control-sm select-akun" required style="width:100%;">';
    selectHtml += '<option value="">-- Ketik kode / nama untuk mencari --</option>';

    // Group items by group
    const groups = {};
    selectItems.forEach(it => {
        const grp = it.group || 'Umum';
        if (!groups[grp]) groups[grp] = [];
        groups[grp].push(it);
    });

    for (const [grpName, grpItems] of Object.entries(groups)) {
        selectHtml += `<optgroup label="${grpName}">`;
        grpItems.forEach(it => {
            const sel = (selectedId && it.id == selectedId) ? 'selected' : '';
            const sisaTxt = it.sisa > 0 ? ` (Sisa Rencana: Rp ${formatRupiah(it.sisa)})` : '';
            selectHtml += `<option value="${it.id}" ${sel}>${it.rincian}${sisaTxt}</option>`;
        });
        selectHtml += `</optgroup>`;
    }
    selectHtml += '</select>';

    const ketText = keteranganVal || '';
    const jmlText = jumlahVal ? formatRupiah(jumlahVal) : '';

    tr.innerHTML = `
        <td style="min-width: 260px;">${selectHtml}</td>
        <td><input type="text" name="item_keterangan[]" class="form-control form-control-sm" placeholder="Keterangan (opsional)" value="${ketText}"></td>
        <td><input type="text" name="item_jumlah[]" class="form-control form-control-sm rupiah-kas text-right font-weight-bold" placeholder="0" value="${jmlText}" oninput="calcKasTotal()" required></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeKasRow(this)" title="Hapus Baris">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    // Inisialisasi Select2
    const $sel = $(tr).find('.select-akun');
    if ($.fn.select2) {
        $sel.select2({
            dropdownParent: $('#modalInputKas'),
            width: '100%',
            placeholder: 'mis. ATK / BBM / Laptop',
            allowClear: true
        });
    }

    calcKasTotal();
}

function removeKasRow(btn) {
    const tbody = document.getElementById('tbodyKasRincian');
    if (tbody.children.length <= 1) {
        alert('Minimal harus ada 1 baris rincian akun biaya!');
        return;
    }
    const tr = btn.closest('tr');
    const $sel = $(tr).find('.select-akun');
    if ($sel.length && $sel.data('select2')) {
        $sel.select2('destroy');
    }
    tr.remove();
    calcKasTotal();
}

function calcKasTotal() {
    let total = 0;
    document.querySelectorAll('.rupiah-kas').forEach(inp => {
        let val = inp.value.replace(/[^0-9]/g, '');
        inp.value = val ? formatRupiah(parseInt(val)) : '';
        total += parseInt(val || 0);
    });
    document.getElementById('kasTotalDisplay').textContent = 'Rp ' + formatRupiah(total);
}

function handleKasFiles(input) {
    const prev = document.getElementById('kasFilePreview');
    prev.innerHTML = '';
    if (!input.files || input.files.length === 0) return;

    if (input.files.length > 10) {
        alert('Maksimal 10 file!');
        input.value = '';
        return;
    }

    Array.from(input.files).forEach(file => {
        const ext = file.name.split('.').pop().toLowerCase();
        const ic = ['jpg','jpeg','png'].includes(ext) ? 'fa-image' : 'fa-file-pdf';
        const span = document.createElement('span');
        span.className = 'prev-item';
        span.innerHTML = `<i class="fas ${ic}"></i> ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
        prev.appendChild(span);
    });
}

function previewImg(src, name) {
    document.getElementById('previewImgSrc').src = src;
    document.getElementById('previewImgTitle').textContent = name || 'Bukti Nota';
    $('#modalPreviewImg').modal('show');
}

function formatRupiah(num) {
    return (num || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Auto-focus kotak pencarian saat Select2 dibuka
$(document).on('select2:open', () => {
    setTimeout(() => {
        const searchField = document.querySelector('.select2-container--open .select2-search__field');
        if (searchField) {
            searchField.focus();
        }
    }, 50);
});
</script>
</body>
</html>
