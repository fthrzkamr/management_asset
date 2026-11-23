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

// Daftar COA postable (non-header)
$coa_q = mysqli_query($conn, "
    SELECT id_coa, kode_akun, nama_akun, kategori_akun, tipe_saldo
    FROM acc_coa
    WHERE status = 'Aktif' AND is_header = 0
    ORDER BY kode_akun ASC
");
$all_coa = [];
while ($c = mysqli_fetch_assoc($coa_q)) $all_coa[] = $c;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input Jurnal Umum Manual – Accounting PT DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-jurnal-input th { background: #f8fafc; font-size: 13px; font-weight: 700; color: #334155; }
        .table-jurnal-input td { vertical-align: middle; }
        .balance-box { border-radius: 8px; padding: 14px 20px; font-weight: 700; }
        .balanced { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
        .unbalanced { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
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
                            <i class="fas fa-edit text-primary mr-2"></i> Input Entri Jurnal Umum Manual (Double-Entry)
                        </h3>
                        <ul class="breadcrumb bg-transparent p-0 mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Jurnal Umum</a></li>
                            <li class="breadcrumb-item active">Buat Jurnal Manual</li>
                        </ul>
                    </div>
                    <div class="col-sm-4 text-right">
                        <a href="index.php" class="btn btn-light border font-weight-bold">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Flash Alert -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <?= $_SESSION['error'] ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form action="action.php" method="POST" id="formJurnal">
                <input type="hidden" name="action" value="save_jurnal">

                <!-- Header Jurnal Card -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title font-weight-bold mb-0 text-dark">
                            <i class="fas fa-info-circle text-primary mr-1"></i> Header Transaksi Jurnal
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">Tanggal Jurnal <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_jurnal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">Tipe Jurnal <span class="text-danger">*</span></label>
                                <select name="tipe_jurnal" class="form-control font-weight-bold" required>
                                    <option value="Umum">Jurnal Umum (JU)</option>
                                    <option value="Penyesuaian">Jurnal Penyesuaian</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">No. Dokumen / Referensi</label>
                                <input type="text" name="referensi_no" class="form-control" placeholder="mis. INV-2026/01, NO-MEMO">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">Keterangan / Uraian Jurnal <span class="text-danger">*</span></label>
                                <input type="text" name="keterangan" class="form-control" required placeholder="Contoh: Pencatatan depresiasi aset gedung...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Multi-Baris Card -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:10px;">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title font-weight-bold mb-0 text-dark">
                            <i class="fas fa-list-ol text-secondary mr-1"></i> Rincian Akun & Nominal (Debit / Kredit)
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" onclick="addJurnalRow()">
                            <i class="fas fa-plus mr-1"></i> Tambah Baris Akun
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 table-jurnal-input" id="tableJurnal">
                                <thead>
                                    <tr>
                                        <th width="42%">Akun COA (Bagan Akun)</th>
                                        <th width="14%" class="text-center">Posisi</th>
                                        <th width="20%" class="text-right">Nominal (Rp)</th>
                                        <th width="20%">Memo / Catatan Baris</th>
                                        <th width="4%" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyJurnal">
                                    <!-- Dynamic rows -->
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light font-weight-bold">
                                        <td class="text-right" colspan="2">TOTAL DEBIT:</td>
                                        <td class="text-right text-info" id="displayTotalDebit" style="font-size: 15px;">Rp 0</td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr class="bg-light font-weight-bold">
                                        <td class="text-right" colspan="2">TOTAL KREDIT:</td>
                                        <td class="text-right text-primary" id="displayTotalKredit" style="font-size: 15px;">Rp 0</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white p-3">
                        <div id="balanceBox" class="balance-box unbalanced d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span id="balanceText">Jurnal Belum Seimbang (Selisih: Rp 0)</span>
                            </div>
                            <div>
                                Total Debit harus persis sama dengan Total Kredit untuk dapat dibukukan.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-right mb-5">
                    <a href="index.php" class="btn btn-light border px-4 mr-2">Batal</a>
                    <button type="submit" id="btnSubmitJurnal" class="btn btn-primary px-5 font-weight-bold" disabled>
                        <i class="fas fa-save mr-1"></i> Posting Jurnal ke Buku Besar
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php
if ($layout_dir && file_exists($layout_dir . '/footer.php'))    include $layout_dir . '/footer.php';
if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) include $layout_dir . '/footer_js.php';
?>
<script src="../assets/plugins/select2/js/select2.min.js"></script>
<script>
const coaList = <?= json_encode($all_coa) ?>;

function getCoaOptions(selectedId) {
    let html = '<option value="">-- Ketik kode / nama akun --</option>';
    coaList.forEach(c => {
        const sel = (selectedId && c.id_coa == selectedId) ? 'selected' : '';
        html += `<option value="${c.id_coa}" ${sel}>[${c.kode_akun}] ${c.nama_akun} (${c.kategori_akun})</option>`;
    });
    return html;
}

function addJurnalRow(posisiDefault, presetCoaId) {
    const tbody = document.getElementById('tbodyJurnal');
    const tr = document.createElement('tr');
    const pos = posisiDefault || (tbody.children.length % 2 === 0 ? 'Debit' : 'Kredit');

    tr.innerHTML = `
        <td>
            <select name="id_coa[]" class="form-control form-control-sm select2-row" required style="width:100%;">
                ${getCoaOptions(presetCoaId)}
            </select>
        </td>
        <td class="text-center">
            <select name="posisi[]" class="form-control form-control-sm font-weight-bold" onchange="calcBalance()">
                <option value="Debit" ${pos === 'Debit' ? 'selected' : ''}>Debit</option>
                <option value="Kredit" ${pos === 'Kredit' ? 'selected' : ''}>Kredit</option>
            </select>
        </td>
        <td>
            <input type="text" name="nominal[]" class="form-control form-control-sm font-weight-bold text-right row-nominal" placeholder="0" oninput="formatRupiahInput(this); calcBalance();" required>
        </td>
        <td>
            <input type="text" name="memo[]" class="form-control form-control-sm" placeholder="Catatan baris (opsional)">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeJurnalRow(this)" title="Hapus Baris">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);

    if ($.fn.select2) {
        $(tr).find('.select2-row').select2({
            width: '100%',
            placeholder: '-- Cari Akun COA --'
        });
    }

    calcBalance();
}

function removeJurnalRow(btn) {
    const tbody = document.getElementById('tbodyJurnal');
    if (tbody.children.length <= 2) {
        showAlert('Minimal harus ada 2 baris akun (Debit dan Kredit)!', 'warning');
        return;
    }
    btn.closest('tr').remove();
    calcBalance();
}

function formatRupiahInput(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (val === '') {
        input.value = '';
        return;
    }
    input.value = new Intl.NumberFormat('id-ID').format(val);
}

function calcBalance() {
    let totalDebit = 0;
    let totalKredit = 0;

    const rows = document.querySelectorAll('#tbodyJurnal tr');
    rows.forEach(r => {
        const pos = r.querySelector('select[name="posisi[]"]').value;
        const nomRaw = r.querySelector('.row-nominal').value.replace(/\./g, '');
        const nom = parseFloat(nomRaw) || 0;

        if (pos === 'Debit') {
            totalDebit += nom;
        } else {
            totalKredit += nom;
        }
    });

    document.getElementById('displayTotalDebit').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalDebit);
    document.getElementById('displayTotalKredit').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalKredit);

    const diff = Math.abs(totalDebit - totalKredit);
    const box = document.getElementById('balanceBox');
    const text = document.getElementById('balanceText');
    const btnSubmit = document.getElementById('btnSubmitJurnal');

    if (totalDebit > 0 && totalKredit > 0 && diff < 0.01) {
        box.className = 'balance-box balanced d-flex justify-content-between align-items-center';
        text.innerHTML = '<i class="fas fa-check-circle mr-2"></i> JURNAL SEIMBANG / BALANCED (Rp ' + new Intl.NumberFormat('id-ID').format(totalDebit) + ')';
        btnSubmit.disabled = false;
    } else {
        box.className = 'balance-box unbalanced d-flex justify-content-between align-items-center';
        text.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i> JURNAL BELUM SEIMBANG (Selisih: Rp ' + new Intl.NumberFormat('id-ID').format(diff) + ')';
        btnSubmit.disabled = true;
    }
}

$(document).ready(function() {
    // Inisialisasi awal 2 baris (Debit & Kredit)
    addJurnalRow('Debit');
    addJurnalRow('Kredit');
});
</script>
</body>
</html>
