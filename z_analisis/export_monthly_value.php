<?php
// Export HTML/XLS — Excel-friendly layout & styles
include('../dist/config/config.php');

if (!isset($conn) || !$conn) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Database connection not available.";
    exit;
}

$year = intval($_GET['year'] ?? date('Y'));
$filename = "monthly_asset_values_{$year}_" . date('Ymd_His') . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

// BOM UTF-8 (agar karakter tampil benar di Excel)
echo "\xEF\xBB\xBF";

// Siapkan bulan Jan..Dec
$months = [];
for ($m = 1; $m <= 12; $m++) $months[] = sprintf('%04d-%02d', $year, $m);
$start_date = "$year-01-01";
$end_date = ($year + 1) . '-01-01';

// Query agregasi per bulan
$start_esc = mysqli_real_escape_string($conn, $start_date);
$end_esc   = mysqli_real_escape_string($conn, $end_date);
$q = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
           SUM(total_nilai_pengadaan) AS total_value
    FROM asset_dfm
    WHERE created_at >= '$start_esc'
      AND created_at  < '$end_esc'
    GROUP BY ym
";
$res = mysqli_query($conn, $q);
$map = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) $map[$r['ym']] = (float)$r['total_value'];
    mysqli_free_result($res);
}

// Build values & stats
$values = [];
$grand = 0.0;
foreach ($months as $ym) {
    $v = $map[$ym] ?? 0.0;
    $values[] = $v;
    $grand   += $v;
}
$avg     = count($values) ? ($grand / count($values)) : 0;
$max     = max($values) ?: 1; // hindari div by zero
$min     = min($values);
$max_idx = array_search($max, $values);
$min_idx = array_search($min, $values);

// Helpers
function fmtRpDisplay($v)
{
    return 'Rp ' . number_format($v, 0, ',', '.');
}
function monthLabel($ym)
{
    $d = DateTime::createFromFormat('!Y-m', $ym);
    return $d ? $d->format('M') : $ym;
}

$title     = "Laporan: Total Nilai Pengadaan Aset per Bulan - $year";
$company   = "Asset DFM";
$generated = date('d M Y H:i');
?>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?= htmlspecialchars($title) ?></title>
</head>

<body style="font-family:Calibri, Arial, Helvetica, sans-serif; color:#222; margin:12px;">
    <!-- HEADER -->
    <table border="0" cellpadding="0" cellspacing="0" style="width:100%; margin-bottom:12px;">
        <tr>
            <td style="width:60px;">
                <table border="0" cellpadding="0" cellspacing="0" style="width:60px; height:60px; border-radius:8px; background:#0b5ed7; color:#fff; text-align:center; font-weight:bold; font-size:22px;">
                    <tr>
                        <td><?= htmlspecialchars(substr($company, 0, 1)) ?></td>
                    </tr>
                </table>
            </td>
            <td style="padding-left:10px; vertical-align:middle;">
                <div style="font-size:18px; font-weight:700; color:#0b5ed7;"><?= htmlspecialchars($title) ?></div>
                <div style="font-size:12px; color:#666; margin-top:4px;"><?= htmlspecialchars($company) ?> — Generated: <?= htmlspecialchars($generated) ?></div>
            </td>
        </tr>
    </table>

    <!-- KPI CARDS (tabel agar aman untuk Excel) -->
    <table border="0" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:separate; border-spacing:8px; margin-bottom:10px;">
        <tr>
            <td style="border:1px solid #e6eefc; background:#ffffff; border-radius:6px; width:25%;">
                <div style="font-size:13px; color:#0b5ed7; font-weight:700;"><?= htmlspecialchars(fmtRpDisplay($grand)) ?></div>
                <div style="font-size:11px; color:#555;">Total Nilai Pengadaan (12 bulan)</div>
            </td>
            <td style="border:1px solid #e6eefc; background:#ffffff; border-radius:6px; width:25%;">
                <div style="font-size:13px; color:#0b5ed7; font-weight:700;"><?= htmlspecialchars(fmtRpDisplay($avg)) ?></div>
                <div style="font-size:11px; color:#555;">Rata-rata / bulan</div>
            </td>
            <td style="border:1px solid #e6eefc; background:#ffffff; border-radius:6px; width:25%;">
                <div style="font-size:13px; color:#0b5ed7; font-weight:700;"><?= htmlspecialchars(fmtRpDisplay($max)) ?></div>
                <div style="font-size:11px; color:#555;">Tertinggi: <?= htmlspecialchars(monthLabel($months[$max_idx])) ?></div>
            </td>
            <td style="border:1px solid #e6eefc; background:#ffffff; border-radius:6px; width:25%;">
                <div style="font-size:13px; color:#0b5ed7; font-weight:700;"><?= htmlspecialchars(fmtRpDisplay($min)) ?></div>
                <div style="font-size:11px; color:#555;">Terendah: <?= htmlspecialchars(monthLabel($months[$min_idx])) ?></div>
            </td>
        </tr>
    </table>

    <!-- TABEL UTAMA -->
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th style="background:#0b5ed7; color:#fff; border:1px solid #0a53bf; text-align:left;">Bulan</th>
                <th style="background:#0b5ed7; color:#fff; border:1px solid #0a53bf; text-align:right;">Total Nilai</th>
                <th style="background:#0b5ed7; color:#fff; border:1px solid #0a53bf; text-align:left;">Visual</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($months as $i => $ym):
                $label = monthLabel($ym) . " $year";
                $val   = (int)($values[$i] ?? 0);
                $pct   = $max > 0 ? round(($val / $max) * 100, 2) : 0;
                $isMax = ($val === (int)$max);
                $isMin = ($val === (int)$min);
                $rowBg = $isMax ? '#f0f7ff' : ($isMin ? '#fff5f5' : (($i % 2 === 0) ? '#ffffff' : '#fbfdff'));
            ?>
                <tr>
                    <td style="background:<?= $rowBg ?>; font-weight:600;"><?= htmlspecialchars($label) ?></td>
                    <!-- numeric cell: Excel-friendly -->
                    <td style="background:<?= $rowBg ?>; text-align:right; mso-number-format:'\#\,\#\#0'; font-weight:700; color:#0b5ed7;"><?= fmtRpDisplay($val) ?></td>
                    <td style="background:<?= $rowBg ?>;">
                        <!-- progress bar sederhana (tabel agar aman) -->
                        <table border="0" cellpadding="0" cellspacing="0" style="width:100%; height:14px; background:#eef5ff; border-radius:8px;">
                            <tr>
                                <td style="width:<?= max(0, min(100, $pct)) ?>%; background:#7aa7ff;"></td>
                                <td style="width:<?= 100 - max(0, min(100, $pct)) ?>%;"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td style="background:#e9f2ff; font-weight:700;">TOTAL</td>
                <td style="background:#e9f2ff; text-align:right; mso-number-format:'\#\,\#\#0'; font-weight:700;"><?= fmtRpDisplay($grand) ?></td>
                <td style="background:#e9f2ff;">
                    <table border="0" cellpadding="0" cellspacing="0" style="width:100%; height:14px; background:#eef5ff; border-radius:8px;">
                        <tr>
                            <td style="width:100%; background:#7aa7ff;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <div style="font-size:12px; color:#444; margin-top:10px;">
        Catatan: Angka menunjukkan jumlah unit aset yang ditambahkan pada bulan tersebut.
    </div>
</body>

</html>
<?php
exit;
?>