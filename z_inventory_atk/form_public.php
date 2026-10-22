<?php
// Public Form - Self Service ATK Request (No Login Required)
require_once __DIR__ . '/../dist/config/config.php';

// Ambil semua barang dari master inventory, susun per kategori
$sql_items = "SELECT i.id_barang, i.nama_barang, i.satuan, i.kategori, COALESCE(s.quantity, 0) as stok
              FROM inventory_atk i
              LEFT JOIN inventory_stok s ON i.id_barang = s.id_barang
              ORDER BY i.kategori, i.nama_barang";
$res = mysqli_query($conn, $sql_items);

$barang_by_kategori = [];
$all_kategori       = [];
while ($item = mysqli_fetch_assoc($res)) {
    $kat = $item['kategori'];
    if (!in_array($kat, $all_kategori)) $all_kategori[] = $kat;
    $barang_by_kategori[$kat][] = [
        'id'     => $item['id_barang'],
        'nama'   => $item['nama_barang'],
        'satuan' => $item['satuan'] ?? '-',
        'stok'   => $item['stok'] ?? 0,
    ];
}
$barang_json   = json_encode($barang_by_kategori, JSON_UNESCAPED_UNICODE);
$kategori_list = json_encode($all_kategori, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Form Pengambilan ATK - DFM</title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/dua.png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* Override Select2 to fit table cells */
        .select2-container { min-width: 0 !important; }
        .select2-container .select2-selection--single {
            height: 34px;
            border: 1.5px solid #e2e8f0;
            border-radius: 7px;
            font-size: 13px;
        }
        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
            padding-left: 8px;
            color: #2d3748;
        }
        .select2-container .select2-selection--single .select2-selection__arrow {
            height: 32px;
        }
        .select2-dropdown {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-size: 13px;
        }
        .select2-search--dropdown .select2-search__field {
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 13px;
        }
        .select2-results__option--highlighted { background: #667eea !important; }
        .select2-results__option--disabled { color: #bbb; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 15px;
        }

        .form-container { max-width: 960px; margin: 0 auto; }

        .form-header {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .form-header h1 {
            color: #1a202c; font-size: 24px; font-weight: 700;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .form-header p { color: #718096; font-size: 14px; margin: 0; }

        .form-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 15px; font-weight: 600; color: #2d3748;
            margin-bottom: 18px; padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title i { color: #667eea; font-size: 17px; }

        .form-label { font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 6px; display: block; }
        .required { color: #e53e3e; }

        .form-control, select.form-control {
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            padding: 9px 12px; font-size: 14px; width: 100%;
            transition: border-color 0.2s;
        }
        .form-control:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }

        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .form-group { margin-bottom: 12px; }

        /* ── Kategori block ── */
        .kategori-block {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 14px;
            overflow: hidden;
        }

        .kategori-header {
            background: #f7fafc;
            border-bottom: 1.5px solid #e2e8f0;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .kategori-header .kat-label {
            font-size: 13px; font-weight: 600; color: #4a5568;
            white-space: nowrap;
        }
        .kategori-header select {
            flex: 1; min-width: 140px; max-width: 240px;
        }
        .kat-actions { margin-left: auto; display: flex; gap: 7px; }

        .btn-add-item-in {
            background: #48bb78; color: white;
            padding: 6px 13px; font-size: 12px; font-weight: 600;
            border-radius: 7px; border: none; cursor: pointer;
        }
        .btn-add-item-in:hover { background: #38a169; }

        .btn-del-kat {
            background: #f56565; color: white;
            padding: 6px 13px; font-size: 12px; font-weight: 600;
            border-radius: 7px; border: none; cursor: pointer;
        }
        .btn-del-kat:hover { background: #e53e3e; }

        /* ── Items table ── */
        .items-table {
            width: 100%; border-collapse: collapse;
            font-size: 13px;
        }
        .items-table thead tr { background: #edf2f7; }
        .items-table th {
            padding: 9px 14px; font-weight: 600; color: #4a5568;
            text-align: left; border-bottom: 1.5px solid #e2e8f0;
        }
        .items-table th:last-child { width: 46px; text-align: center; }
        .items-table td {
            padding: 9px 14px; border-bottom: 1px solid #f0f4f8;
            vertical-align: middle;
        }
        .items-table tr:last-child td { border-bottom: none; }
        .items-table td:nth-child(2) { width: 90px; }
        .items-table td:last-child { text-align: center; width: 46px; }

        input.qty-input {
            width: 75px; text-align: center;
            padding: 6px 8px; font-size: 13px;
            border: 1.5px solid #e2e8f0; border-radius: 7px;
        }
        input.qty-input:focus { border-color: #667eea; outline: none; }

        .btn-del-item {
            background: #f56565; color: white; border: none;
            border-radius: 6px; width: 28px; height: 28px;
            font-size: 11px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-del-item:hover { background: #e53e3e; }

        /* ── Add Kategori ── */
        .btn-add-kat {
            width: 100%; background: white;
            border: 2px dashed #cbd5e0; color: #718096;
            border-radius: 10px; padding: 11px;
            font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            margin-bottom: 20px;
        }
        .btn-add-kat:hover { border-color: #667eea; color: #667eea; background: #f0f0ff; }

        /* ── Submit ── */
        .btn-submit {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white; border: none; border-radius: 9px;
            padding: 12px 32px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: box-shadow 0.2s;
        }
        .btn-submit:hover { box-shadow: 0 4px 12px rgba(72,187,120,0.4); }

        /* ── Alert ── */
        .alert {
            border-radius: 10px; padding: 13px 16px; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px; font-size: 14px;
        }
        .alert-success { background: #edf7ed; color: #1e4620; border-left: 4px solid #48bb78; }
        .alert-danger  { background: #fef2f2; color: #742a2a; border-left: 4px solid #f56565; }

        .form-footer { text-align: center; color: #718096; padding: 16px; font-size: 12px; margin-top: 16px; }

        @media (max-width: 600px) {
            .kategori-header { flex-direction: column; align-items: flex-start; }
            .kat-actions { margin-left: 0; }
        }
    </style>
</head>

<body>
<div class="form-container">

    <!-- Header -->
    <div class="form-header">
        <h1><i class="fas fa-clipboard-list"></i> Form Pengambilan Barang</h1>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
        <?php unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_message'] ?>
        <?php unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <form id="formPengambilan" method="POST" action="form_public_process.php">

            <!-- Informasi Pemohon -->
            <div class="section-title">
                <i class="fas fa-user"></i> Informasi Pemohon
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" name="nama_pengambil" class="form-control" required
                           placeholder="Masukkan nama lengkap">
                </div>
                <div class="form-group">
                    <label class="form-label">Divisi <span class="required">*</span></label>
                    <select name="divisi" class="form-control" required>
                        <option value="">-- Pilih Divisi --</option>
                        <?php foreach (['Apoteker','HR','IT','Finance','Sales','Warehouse','GA','Operasional','Kurir','Bisnis','Other'] as $d): ?>
                            <option value="<?= $d ?>"><?= $d == 'Other' ? 'Lainnya' : $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Daftar Barang per Kategori -->
            <div class="section-title mt-4">
                <i class="fas fa-box-open"></i> Daftar Barang yang Diminta
            </div>

            <div id="kategoriContainer">
                <!-- Blok kategori pertama ditambah via JS setelah DOM ready -->
            </div>

            <button type="button" class="btn-add-kat" onclick="addKategoriBlock()">
                <i class="fas fa-plus"></i> Tambah Kategori
            </button>

            <!-- Submit -->
            <div class="text-center">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Kirim Pengajuan
                </button>
            </div>

        </form>
    </div>

    <div class="form-footer">
        <p>© <?= date('Y') ?> DF GROUP. All Rights Reserved.</p>
    </div>
</div>

<script src="../assets/js/jquery-3.6.0.min.js"></script>
<script src="../assets/js/popper.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../assets/js/script.js"></script>

<script>
const barangData  = <?= $barang_json ?>;
const kategoriList = <?= $kategori_list ?>;

let katCounter  = 0;
let itemCounter = 0;

// ── Helpers ──────────────────────────────────────────

function buildKategoriOptions(selectedKat) {
    let html = '<option value="">-- Pilih Kategori --</option>';
    kategoriList.forEach(k => {
        html += `<option value="${k}" ${k === selectedKat ? 'selected' : ''}>${k}</option>`;
    });
    return html;
}

function buildBarangOptions(kat) {
    const list = barangData[kat] || [];
    let html = '<option value="">-- Pilih Barang --</option>';
    list.forEach(b => {
        html += `<option value="${b.id}" data-satuan="${b.satuan}" data-stok="${b.stok}">${b.nama}</option>`;
    });
    return html;
}

// Kumpulkan ID barang yang sudah dipilih di blok ini (kecuali selectEl itu sendiri)
function getSelectedInBlock(kId, exceptEl) {
    const block = document.getElementById('kat-block-' + kId);
    const selected = [];
    block.querySelectorAll('.barang-select').forEach(sel => {
        if (sel !== exceptEl && sel.value) selected.push(sel.value);
    });
    return selected;
}

// Update satuan display saat barang dipilih
function updateSatuanDisplay(sel) {
    const selectedOpt = [...sel.options].find(o => o.value === sel.value);
    const satuan = selectedOpt ? (selectedOpt.getAttribute('data-satuan') || '-') : '-';
    const stok = selectedOpt ? (selectedOpt.getAttribute('data-stok') || '-') : '-';
    const row = sel.closest('tr');
    if (row) {
        const spanSat = row.querySelector('.satuan-display');
        if (spanSat) spanSat.textContent = satuan;
        const spanStok = row.querySelector('.stok-display');
        if (spanStok) spanStok.textContent = stok;
    }
}

// Disable opsi yang sudah dipilih di dropdown lain dalam blok yang sama
function updateMutualExclusion(kId) {
    const block = document.getElementById('kat-block-' + kId);
    if (!block) return;
    block.querySelectorAll('.barang-select').forEach(sel => {
        const selectedOthers = getSelectedInBlock(kId, sel);
        const currentVal = sel.value;
        [...sel.options].forEach(opt => {
            if (opt.value === '') return;
            opt.disabled = selectedOthers.includes(opt.value);
        });
        sel.value = currentVal;
        $(sel).trigger('change.select2');
        updateSatuanDisplay(sel);
    });
}

function initSelect2ForSelect(sel, kId) {
    $(sel).select2({
        width: '100%',
        placeholder: '-- Pilih Barang --',
        allowClear: true,
        language: {
            noResults: () => 'Barang tidak ditemukan',
            searching: () => 'Mencari...'
        }
    }).on('change', function() {
        updateSatuanDisplay(this);
        updateMutualExclusion(kId);
    });
}

function refreshBarangDropdown(katBlockId) {
    const block = document.getElementById('kat-block-' + katBlockId);
    const kat   = block.querySelector('.kat-select').value;
    block.querySelectorAll('.barang-select').forEach(sel => {
        $(sel).select2('destroy');
        sel.innerHTML = buildBarangOptions(kat);
        initSelect2ForSelect(sel, katBlockId);
    });
    updateMutualExclusion(katBlockId);
}

// ── Kategori Block ────────────────────────────────────

function addKategoriBlock(selectedKat) {
    katCounter++;
    const kId = katCounter;
    itemCounter++;
    const iId = itemCounter;

    const block = document.createElement('div');
    block.className = 'kategori-block';
    block.id = 'kat-block-' + kId;

    const kat = selectedKat || '';
    const barangOpts = buildBarangOptions(kat);

    block.innerHTML = `
        <div class="kategori-header">
            <span class="kat-label">Kategori:</span>
            <select class="form-control kat-select" onchange="refreshBarangDropdown(${kId})" required>
                ${buildKategoriOptions(kat)}
            </select>
            <div class="kat-actions">
                <button type="button" class="btn-add-item-in" onclick="addItemRow(${kId})">
                    <i class="fas fa-plus"></i> Item
                </button>
                <button type="button" class="btn-del-kat" onclick="removeKategoriBlock(${kId})">
                    <i class="fas fa-trash"></i> Kategori
                </button>
            </div>
        </div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Rincian Item</th>
                    <th width="70">Stok</th>
                    <th width="80">Qty</th>
                    <th width="90">Satuan</th>
                    <th width="40"></th>
                </tr>
            </thead>
            <tbody id="tbody-${kId}">
                <tr id="item-row-${iId}" data-item="${iId}">
                    <td>
                        <select name="items[${iId}][id_barang]" class="form-control barang-select" required>
                            ${barangOpts}
                        </select>
                    </td>
                    <td>
                        <span class="stok-display" style="font-size:13px;color:#4a5568;font-weight:bold;">-</span>
                    </td>
                    <td>
                        <input type="number" name="items[${iId}][qty_diminta]"
                               class="qty-input" min="1" value="1" required>
                    </td>
                    <td>
                        <span class="satuan-display" style="font-size:13px;color:#4a5568;">-</span>
                    </td>
                    <td>
                        <button type="button" class="btn-del-item" onclick="removeItemRow(${iId}, ${kId})" title="Hapus item">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    `;

    document.getElementById('kategoriContainer').appendChild(block);
    initSelect2ForSelect(block.querySelector('.barang-select'), kId);
    updateDelKatButtons();
    updateDelItemButtons(kId);
}

function removeKategoriBlock(kId) {
    const block = document.getElementById('kat-block-' + kId);
    if (block) {
        block.querySelectorAll('.barang-select').forEach(sel => $(sel).select2('destroy'));
        block.remove();
    }
    updateDelKatButtons();
}

function updateDelKatButtons() {
    const blocks = document.querySelectorAll('.kategori-block');
    blocks.forEach(b => {
        const btn = b.querySelector('.btn-del-kat');
        btn.disabled = blocks.length === 1;
        btn.style.opacity = blocks.length === 1 ? '0.5' : '1';
    });
}

// ── Item Row ──────────────────────────────────────────

function addItemRow(kId) {
    itemCounter++;
    const iId   = itemCounter;
    const block = document.getElementById('kat-block-' + kId);
    const kat   = block.querySelector('.kat-select').value;
    const tbody = document.getElementById('tbody-' + kId);

    const tr = document.createElement('tr');
    tr.id = 'item-row-' + iId;
    tr.setAttribute('data-item', iId);
    tr.innerHTML = `
        <td>
            <select name="items[${iId}][id_barang]" class="form-control barang-select" required>
                ${buildBarangOptions(kat)}
            </select>
        </td>
        <td>
            <span class="stok-display" style="font-size:13px;color:#4a5568;font-weight:bold;">-</span>
        </td>
        <td>
            <input type="number" name="items[${iId}][qty_diminta]"
                   class="qty-input" min="1" value="1" required>
        </td>
        <td>
            <span class="satuan-display" style="font-size:13px;color:#4a5568;">-</span>
        </td>
        <td>
            <button type="button" class="btn-del-item" onclick="removeItemRow(${iId}, ${kId})" title="Hapus item">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    initSelect2ForSelect(tr.querySelector('.barang-select'), kId);
    updateMutualExclusion(kId);
    updateDelItemButtons(kId);
}

function removeItemRow(iId, kId) {
    const row = document.getElementById('item-row-' + iId);
    if (row) {
        const sel = row.querySelector('.barang-select');
        if (sel) $(sel).select2('destroy');
        row.remove();
    }
    updateMutualExclusion(kId);
    updateDelItemButtons(kId);
}

function updateDelItemButtons(kId) {
    const tbody = document.getElementById('tbody-' + kId);
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(r => {
        const btn = r.querySelector('.btn-del-item');
        if (btn) {
            btn.disabled = rows.length === 1;
            btn.style.opacity = rows.length === 1 ? '0.4' : '1';
        }
    });
}

// ── Init ──────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
    addKategoriBlock(); // Mulai dengan satu blok kategori
});

// ── Validation ────────────────────────────────────────

document.getElementById('formPengambilan').addEventListener('submit', function(e) {
    // Pastikan setiap blok kategori sudah memilih kategori
    let valid = true, msg = '';
    document.querySelectorAll('.kat-select').forEach(sel => {
        if (!sel.value) { valid = false; msg = 'Pilih kategori untuk setiap blok.'; }
    });
    document.querySelectorAll('.barang-select').forEach(sel => {
        if (!sel.value) { valid = false; msg = 'Pilih barang untuk semua item.'; }
    });
    document.querySelectorAll('.qty-input').forEach(inp => {
        if (!inp.value || parseInt(inp.value) < 1) { valid = false; msg = 'Masukkan jumlah yang valid.'; }
    });
    if (!valid) { e.preventDefault(); alert(msg); }
});
</script>
</body>
</html>
