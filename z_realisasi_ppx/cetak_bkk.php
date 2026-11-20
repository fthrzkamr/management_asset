<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/ppx_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$id_real_detail = (int)($_GET['id'] ?? 0);
$no_realisasi   = trim($_GET['no'] ?? '');

if ($id_real_detail <= 0 && empty($no_realisasi)) {
    die("Parameter transaksi tidak valid.");
}

// Cari transaksi
$where = $id_real_detail > 0 ? "rd.id_real_detail = $id_real_detail" : "rd.no_realisasi = '" . mysqli_real_escape_string($conn, $no_realisasi) . "'";
$q_base = mysqli_query($conn, "
    SELECT rd.*, mb.budget_name, mb.period_month, mb.period_year,
           u.nama_user AS nama_pembuat, mc.nama_cabang,
           app.nama_user AS nama_approver
    FROM ppx_realisasi_detail2 rd
    JOIN ppx_monthly_budget mb ON rd.id_budget = mb.id_budget
    JOIN user_dfm u ON rd.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    LEFT JOIN user_dfm app ON rd.approved_by = app.id_user
    WHERE $where
    LIMIT 1
");

if (!$q_base || mysqli_num_rows($q_base) === 0) {
    die("Transaksi kas tidak ditemukan.");
}

$header_tr = mysqli_fetch_assoc($q_base);
$no_trx    = $header_tr['no_realisasi'];
$jenis     = $header_tr['jenis_transaksi'] ?? 'BKK';
$is_bkm    = ($jenis === 'BKM');

// Ambil semua baris dengan nomor transaksi yang sama (jika multi-item)
if (!empty($no_trx)) {
    $q_lines = mysqli_query($conn, "
        SELECT rd.*, mbd.rincian, mbd.satuan, bc.category_name
        FROM ppx_realisasi_detail2 rd
        LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
        LEFT JOIN ppx_budget_category bc ON mbd.id_category = bc.id_category
        WHERE rd.no_realisasi = '" . mysqli_real_escape_string($conn, $no_trx) . "'
        ORDER BY rd.id_real_detail ASC
    ");
} else {
    $q_lines = mysqli_query($conn, "
        SELECT rd.*, mbd.rincian, mbd.satuan, bc.category_name
        FROM ppx_realisasi_detail2 rd
        LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
        LEFT JOIN ppx_budget_category bc ON mbd.id_category = bc.id_category
        WHERE rd.id_real_detail = $id_real_detail
    ");
}

$items = [];
$total_nominal = 0;
while ($it = mysqli_fetch_assoc($q_lines)) {
    $items[] = $it;
    $nom = $is_bkm ? (float)($it['uang_kembali'] ?: $it['nominal_realisasi']) : (float)$it['nominal_realisasi'];
    $total_nominal += $nom;
}

// Helper Terbilang
function terbilang($angka) {
    $angka = abs((float)$angka);
    $baca = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    $terbilang = '';

    if ($angka < 12) {
        $terbilang = ' ' . $baca[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . ' Belas';
    } elseif ($angka < 100) {
        $terbilang = terbilang((int)($angka / 10)) . ' Puluh' . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = ' Seratus' . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang((int)($angka / 100)) . ' Ratus' . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = ' Seribu' . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang((int)($angka / 1000)) . ' Ribu' . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang((int)($angka / 1000000)) . ' Juta' . terbilang($angka % 1000000);
    } elseif ($angka < 1000000000000) {
        $terbilang = terbilang((int)($angka / 1000000000)) . ' Miliar' . terbilang($angka % 1000000000);
    }
    return trim($terbilang);
}

$bulan_names = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$tgl_obj     = !empty($header_tr['tanggal_realisasi']) ? strtotime($header_tr['tanggal_realisasi']) : time();
$tgl_indo    = date('d', $tgl_obj) . ' ' . $bulan_names[(int)date('m', $tgl_obj)] . ' ' . date('Y', $tgl_obj);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $is_bkm ? 'BKM' : 'BKK' ?> – <?= htmlspecialchars($no_trx ?: 'Transaksi') ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
        }
        .sheet {
            background: #ffffff;
            width: 210mm;
            min-height: 140mm;
            margin: 0 auto;
            padding: 24px 30px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            box-sizing: border-box;
            border-radius: 6px;
        }
        .header-tbl {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .comp-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 18px;
            font-weight: 800;
            text-align: right;
            color: <?= $is_bkm ? '#047857' : '#1e3a8a' ?>;
            letter-spacing: 0.5px;
        }
        .meta-tbl {
            width: 100%;
            margin-bottom: 18px;
            font-size: 13px;
        }
        .meta-tbl td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .meta-lbl {
            font-weight: 700;
            color: #475569;
            width: 140px;
        }
        .rincian-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .rincian-tbl th {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            border: 1px solid #94a3b8;
            padding: 8px 10px;
            font-size: 12px;
            text-align: left;
        }
        .rincian-tbl td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 12.5px;
        }
        .terbilang-box {
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            padding: 10px 14px;
            font-style: italic;
            border-radius: 4px;
            margin-bottom: 24px;
            font-size: 12.5px;
        }
        .sign-tbl {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-top: 24px;
        }
        .sign-tbl th {
            font-weight: 600;
            font-size: 12px;
            color: #475569;
            padding-bottom: 70px;
        }
        .sign-tbl td {
            font-weight: 700;
            font-size: 12.5px;
            color: #0f172a;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            width: 33.33%;
        }
        .btn-bar {
            width: 210mm;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-act {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-print:hover { background: #1d4ed8; }
        .btn-back  { background: #e2e8f0; color: #334155; }
        .btn-back:hover  { background: #cbd5e1; }

        @media print {
            body { background: #fff; padding: 0; }
            .btn-bar { display: none; }
            .sheet {
                border: none;
                box-shadow: none;
                width: 100%;
                padding: 10mm 15mm;
            }
        }
    </style>
</head>
<body>

<div class="btn-bar">
    <a href="detail.php?id_budget=<?= $header_tr['id_budget'] ?>" class="btn-act btn-back">
        <i class="fas fa-arrow-left"></i> Kembali ke Detail Realisasi
    </a>
    <button onclick="window.print()" class="btn-act btn-print">
        <i class="fas fa-print"></i> Cetak / Simpan PDF
    </button>
</div>

<div class="sheet">
    <!-- Header -->
    <table class="header-tbl">
        <tr>
            <td style="vertical-align: middle;">
                <div class="comp-name">PT DUA FARMA MAHAKARSA</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                    Cabang: <strong><?= htmlspecialchars($header_tr['nama_cabang'] ?: 'Pusat') ?></strong>
                    &nbsp;·&nbsp; Tahun Anggaran: <?= $header_tr['period_year'] ?>
                </div>
            </td>
            <td style="vertical-align: middle; text-align: right;">
                <div class="doc-title"><?= $is_bkm ? 'BUKTI KAS MASUK (BKM)' : 'BUKTI KAS KELUAR (BKK)' ?></div>
                <div style="font-size: 13px; font-weight: 700; color: #334155; margin-top: 3px; font-family: monospace;">
                    <?= htmlspecialchars($no_trx ?: '-') ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- Meta Info -->
    <table class="meta-tbl">
        <tr>
            <td class="meta-lbl">Tanggal Transaksi</td>
            <td width="2%">:</td>
            <td><strong><?= $tgl_indo ?></strong></td>
            <td class="meta-lbl">Anggaran / PPX</td>
            <td width="2%">:</td>
            <td><?= htmlspecialchars($header_tr['budget_name']) ?></td>
        </tr>
        <tr>
            <td class="meta-lbl"><?= $is_bkm ? 'Diterima Dari' : 'Dibayar Kepada' ?></td>
            <td>:</td>
            <td><strong><?= htmlspecialchars($header_tr['dibayar_kepada'] ?: '—') ?></strong></td>
            <td class="meta-lbl">Status Verifikasi</td>
            <td>:</td>
            <td>
                <?php if ($header_tr['status'] === 'Disetujui'): ?>
                    <span style="color:#15803d; font-weight:700;"><i class="fas fa-check-circle"></i> Disetujui</span>
                <?php else: ?>
                    <span style="color:#b45309; font-weight:700;"><i class="fas fa-clock"></i> <?= htmlspecialchars($header_tr['status']) ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="meta-lbl">Untuk Keperluan</td>
            <td>:</td>
            <td colspan="4"><?= htmlspecialchars($header_tr['keperluan'] ?: '—') ?></td>
        </tr>
    </table>

    <!-- Rincian Biaya -->
    <table class="rincian-tbl">
        <thead>
            <tr>
                <th width="6%" style="text-align: center;">No</th>
                <th width="34%">Akun / Item Anggaran</th>
                <th width="38%">Keterangan Transaksi</th>
                <th width="22%" style="text-align: right;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $row): 
                $nom_line = $is_bkm ? (float)($row['uang_kembali'] ?: $row['nominal_realisasi']) : (float)$row['nominal_realisasi'];
            ?>
            <tr>
                <td style="text-align: center;"><?= $i + 1 ?></td>
                <td>
                    <strong><?= htmlspecialchars($row['rincian'] ?: 'Item Operasional') ?></strong>
                    <?php if (!empty($row['category_name'])): ?>
                        <br><small style="color:#64748b;"><?= htmlspecialchars($row['category_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['catatan'] ?: ($header_tr['keperluan'] ?: '—')) ?></td>
                <td style="text-align: right; font-weight: 700;">
                    Rp <?= number_format($nom_line, 0, ',', '.') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f8fafc; font-weight: 800;">
                <td colspan="3" style="text-align: right; padding: 10px;">TOTAL TRANSAKSI :</td>
                <td style="text-align: right; font-size: 14px; color: <?= $is_bkm ? '#047857' : '#1e3a8a' ?>; padding: 10px;">
                    Rp <?= number_format($total_nominal, 0, ',', '.') ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Terbilang -->
    <div class="terbilang-box">
        <strong>Terbilang:</strong> <?= terbilang($total_nominal) ?> Rupiah
    </div>

    <!-- Tanda Tangan -->
    <table class="sign-tbl">
        <tr>
            <th>Dibuat Oleh,</th>
            <th>Mengetahui / Menyetujui,</th>
            <th><?= $is_bkm ? 'Diserahkan Oleh,' : 'Diterima Oleh,' ?></th>
        </tr>
        <tr>
            <td>( <?= htmlspecialchars($header_tr['nama_pembuat'] ?: 'Pemohon') ?> )</td>
            <td>( <?= htmlspecialchars($header_tr['nama_approver'] ?: 'Asmen Finance') ?> )</td>
            <td>( <?= htmlspecialchars($header_tr['dibayar_kepada'] ?: 'Penerima / Vendor') ?> )</td>
        </tr>
    </table>
</div>

</body>
</html>
