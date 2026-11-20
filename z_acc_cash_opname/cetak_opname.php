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
    die("ID Cash Opname tidak valid.");
}

$q = mysqli_query($conn, "
    SELECT co.*, pmc.nama_cabang, pmc.kode_cabang, pmc.alamat,
           kc.kode_akun AS kode_kas, kc.nama_akun AS nama_kas,
           u1.nama_user AS pembuat, u2.nama_user AS approver
    FROM acc_cash_opname co
    JOIN ppx_master_cabang pmc ON co.id_cabang_ppx = pmc.id_cabang_ppx
    LEFT JOIN acc_coa kc ON co.id_coa_kas = kc.id_coa
    LEFT JOIN user_dfm u1 ON co.created_by = u1.id_user
    LEFT JOIN user_dfm u2 ON co.approved_by = u2.id_user
    WHERE co.id_opname = $id_opname
    LIMIT 1
");

if (!$q || mysqli_num_rows($q) === 0) {
    die("Laporan Cash Opname tidak ditemukan.");
}
$op = mysqli_fetch_assoc($q);
$pecahan = json_decode($op['rincian_pecahan_json'] ?? '[]', true) ?: [];
$sel = (float)$op['selisih'];

$bulan_names = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$tgl_obj = strtotime($op['tanggal_opname']);
$tgl_indo = date('d', $tgl_obj) . ' ' . $bulan_names[(int)date('m', $tgl_obj)] . ' ' . date('Y', $tgl_obj);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berita Acara Cash Opname – <?= htmlspecialchars($op['no_opname']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
        }
        .sheet {
            background: #fff;
            max-width: 820px;
            margin: 0 auto;
            padding: 35px 40px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
        }
        .header-table { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .company-name { font-size: 18px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: .5px; }
        .doc-title { font-size: 16px; font-weight: 800; color: #1e3a8a; text-align: right; text-transform: uppercase; }
        .doc-no { font-size: 13px; font-weight: 700; color: #475569; text-align: right; }

        .meta-table { width: 100%; margin-bottom: 20px; }
        .meta-table td { padding: 4px 6px; font-size: 13px; }
        .meta-label { font-weight: 700; width: 160px; color: #334155; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table th, .data-table td { border: 1px solid #cbd5e1; padding: 7px 10px; font-size: 12.5px; }
        .data-table th { background: #f8fafc; font-weight: 700; text-align: left; }
        .data-table td.text-right { text-align: right; }
        .data-table td.text-center { text-align: center; }

        .box-summary { background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px 16px; border-radius: 6px; margin-bottom: 25px; }

        .sign-table { width: 100%; margin-top: 35px; border-collapse: collapse; }
        .sign-table td { width: 33.33%; text-align: center; vertical-align: top; font-size: 12.5px; }
        .sign-space { height: 70px; }

        .print-btn-bar { max-width: 820px; margin: 0 auto 15px auto; text-align: right; }
        .btn-print { background: #1e3a8a; color: #fff; border: none; padding: 8px 16px; font-size: 13px; font-weight: 700; border-radius: 6px; cursor: pointer; }
        .btn-print:hover { background: #172554; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: none; box-shadow: none; padding: 10px 15px; }
            .print-btn-bar { display: none; }
        }
    </style>
</head>
<body>

<div class="print-btn-bar">
    <button class="btn-print" onclick="window.print()">
        &#128438; Cetak Berita Acara
    </button>
</div>

<div class="sheet">
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: middle;">
                <div class="company-name">PT DUA FARMA MAHAKARSA</div>
                <div style="font-size: 12px; color: #475569; margin-top: 2px;">
                    Cabang: <?= htmlspecialchars($op['nama_cabang']) ?> (<?= htmlspecialchars($op['kode_cabang']) ?>)
                </div>
            </td>
            <td style="width: 45%; vertical-align: middle; text-align: right;">
                <div class="doc-title">BERITA ACARA CASH OPNAME</div>
                <div class="doc-no">No: <?= htmlspecialchars($op['no_opname']) ?></div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Tanggal Pemeriksaan</td>
            <td style="width: 10px;">:</td>
            <td><strong><?= $tgl_indo ?></strong></td>
            <td class="meta-label">Periode Minggu</td>
            <td style="width: 10px;">:</td>
            <td><strong><?= htmlspecialchars($op['periode_minggu']) ?></strong></td>
        </tr>
        <tr>
            <td class="meta-label">Akun Kas Diperiksa</td>
            <td>:</td>
            <td><?= htmlspecialchars($op['nama_kas'] ?: 'Kas Operasional Cabang') ?> (<?= htmlspecialchars($op['kode_kas'] ?: '1101') ?>)</td>
            <td class="meta-label">Petugas Pemeriksa</td>
            <td>:</td>
            <td><?= htmlspecialchars($op['petugas_opname']) ?></td>
        </tr>
    </table>

    <h4 style="font-size: 13.5px; font-weight: 700; margin-bottom: 8px; color: #0f172a; text-transform: uppercase;">
        I. Rincian Penghitungan Fisik Uang di Brankas (Denominasi)
    </h4>

    <table class="data-table">
        <thead>
            <tr>
                <th width="8%" class="text-center">No</th>
                <th width="42%">Pecahan Uang</th>
                <th width="22%" class="text-center">Jumlah (Lembar / Keping)</th>
                <th width="28%" class="text-right">Total Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $denoms = [
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
            $no = 1;
            foreach ($denoms as $d):
                $sub = $d['qty'] * $d['val'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= $d['label'] ?></td>
                <td class="text-center font-weight-bold"><?= number_format($d['qty']) ?></td>
                <td class="text-right font-weight-bold"><?= ($sub > 0) ? 'Rp ' . number_format($sub, 0, ',', '.') : 'Rp 0' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f8fafc; font-weight:bold;">
                <td colspan="3" class="text-right">TOTAL FISIK KAS (A):</td>
                <td class="text-right" style="font-size: 13.5px;">Rp <?= number_format((float)$op['saldo_fisik'], 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>

    <h4 style="font-size: 13.5px; font-weight: 700; margin-bottom: 8px; color: #0f172a; text-transform: uppercase;">
        II. Rekonsiliasi & Hasil Selisih
    </h4>

    <div class="box-summary">
        <table style="width: 100%;">
            <tr>
                <td style="width: 70%; padding: 3px 0;">Saldo Kas Fisik Hasil Perhitungan (A)</td>
                <td style="width: 30%; text-align: right; font-weight: 700;">Rp <?= number_format((float)$op['saldo_fisik'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td style="padding: 3px 0;">Saldo Kas Menurut Buku / Catatan Sistem (B)</td>
                <td style="text-align: right; font-weight: 700;">Rp <?= number_format((float)$op['saldo_buku'], 0, ',', '.') ?></td>
            </tr>
            <tr style="border-top: 1px solid #cbd5e1; font-weight: 800; font-size: 13.5px;">
                <td style="padding-top: 6px;">SELISIH KAS (A - B)</td>
                <td style="padding-top: 6px; text-align: right; color: <?= (abs($sel) < 0.01) ? '#15803d' : ($sel < 0 ? '#b91c1c' : '#1d4ed8') ?>;">
                    <?php if (abs($sel) < 0.01): ?>
                        Rp 0 (KLOP / SESUAI)
                    <?php elseif ($sel < 0): ?>
                        - Rp <?= number_format(abs($sel), 0, ',', '.') ?> (SELISIH KURANG)
                    <?php else: ?>
                        + Rp <?= number_format($sel, 0, ',', '.') ?> (SELISIH LEBIH)
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php if (!empty($op['keterangan_selisih'])): ?>
            <div style="margin-top: 10px; font-size: 12px; color: #475569; font-style: italic;">
                <strong>Penjelasan Selisih:</strong> <?= htmlspecialchars($op['keterangan_selisih']) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tanda Tangan -->
    <table class="sign-table">
        <tr>
            <td>
                <div>Petugas Pemeriksa,</div>
                <div class="sign-space"></div>
                <div style="font-weight: 700; text-decoration: underline;"><?= htmlspecialchars($op['petugas_opname']) ?></div>
                <div style="font-size: 11px; color: #64748b;">Pemegang Kas Cabang</div>
            </td>
            <td>
                <div>Saksi Pemeriksaan,</div>
                <div class="sign-space"></div>
                <div style="font-weight: 700; text-decoration: underline;"><?= htmlspecialchars($op['saksi_opname'] ?: '( ................................ )') ?></div>
                <div style="font-size: 11px; color: #64748b;">Staf / Internal Audit</div>
            </td>
            <td>
                <div>Disetujui Oleh,</div>
                <div class="sign-space"></div>
                <div style="font-weight: 700; text-decoration: underline;"><?= htmlspecialchars($op['approver'] ?: '( ................................ )') ?></div>
                <div style="font-size: 11px; color: #64748b;">Kepala Cabang / Finance Pusat</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
