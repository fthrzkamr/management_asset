<?php
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst_po_pembelian', 'create');

// Auto Generate No PO (Format: PO/BST/YYYY/MM/XXX)
$thn = date('Y');
$bln = date('m');
$q_no = mysqli_query($conn, "SELECT nomor_po FROM bst_pembelian WHERE nomor_po LIKE 'PO/BST/$thn/$bln/%' ORDER BY id_pembelian DESC LIMIT 1");
$no_urut = "001";
if (mysqli_num_rows($q_no) > 0) {
    $row_no = mysqli_fetch_assoc($q_no);
    $last_no = substr($row_no['nomor_po'], -3);
    $no_urut = sprintf("%03d", intval($last_no) + 1);
}
$auto_nopo = "PO/BST/$thn/$bln/$no_urut";

// Get Master Barang ATK
$q_brg = mysqli_query($conn, "SELECT id_barang, kode_barang, nama_barang, satuan, kategori FROM inventory_atk ORDER BY nama_barang");
$barang_options = "";
while($b = mysqli_fetch_assoc($q_brg)){
    $barang_options .= "<option value='{$b['id_barang']}' data-satuan='{$b['satuan']}'>[{$b['kode_barang']}] {$b['nama_barang']}</option>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat PO Pembelian BST</title>
    <link rel="icon" href="../../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/plugins/select2/css/select2.min.css">
</head>
<body>
<?php include_once('../../layouts/navbar.php'); ?>
<div class="page-wrapper">
<div class="content container-fluid">

    <div class="page-header">
        <h3 class="page-title"><i class="fas fa-cart-plus text-primary"></i> Buat PO Pembelian (Restock)</h3>
    </div>

    <form action="insert.php" method="POST" id="formPO">
        <!-- Informasi PO -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Informasi Supplier & PO</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nomor PO / Referensi <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_po" class="form-control" value="<?= $auto_nopo ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Tanggal Pembelian <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_po" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nama Supplier (Mitra/Toko) <span class="text-danger">*</span></label>
                            <input type="text" name="nama_supplier" class="form-control" placeholder="Contoh: Tokopedia Bintang, Pasar, dsb" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Keranjang Pembelian -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Item Barang</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item"><i class="fas fa-plus"></i> Tambah Baris Barang</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="table-items">
                        <thead class="thead-light">
                            <tr>
                                <th width="30%">Pilih Barang (Dari Master ATK)</th>
                                <th width="10%">Satuan</th>
                                <th width="12%">Kuantitas Beli</th>
                                <th width="20%">Harga Satuan (Rp)</th>
                                <th width="20%">Harga Total (Rp)</th>
                                <th width="8%" class="text-center"><i class="fas fa-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody id="item-container">
                            <!-- JS Will Populate -->
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <th colspan="4" class="text-right align-middle">GRAND TOTAL:</th>
                                <th class="text-right">
                                    <h4 class="text-success mb-0 font-weight-bold" id="grandVal">Rp 0</h4>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <a href="dashboard.php" class="btn btn-secondary mr-2">Batal</a>
                    <button type="submit" class="btn btn-primary" id="btn-submit"><i class="fas fa-save"></i> Simpan PO Barang</button>
                </div>
            </div>
        </div>
    </form>

    <?php include_once('../../layouts/footer.php'); ?>
</div>
</div>

<script src="../../assets/plugins/select2/js/select2.min.js"></script>
<script>
    const optBarang = `<?= $barang_options ?>`;

    // Format Number to Ribuan (Rupiah without text)
    function formatRibuan(number) {
        if (!number) return '0';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(number);
    }

    // Parse String Ribuan to int
    function removeRibuan(str) {
        if (!str) return 0;
        return parseFloat(str.toString().replace(/[^0-9]/g, "")) || 0;
    }

    function calculateGrandTotal() {
        let gTotal = 0;
        $('#item-container tr').each(function() {
            let sub = removeRibuan($(this).find('.row-total-tampil').val());
            gTotal += sub;
        });
        $('#grandVal').text('Rp ' + formatRibuan(gTotal));
    }

    function addItemRow() {
        const rowId = Date.now();
        const html = `
            <tr id="row-${rowId}">
                <td>
                    <select name="id_barang[]" class="form-control select2-item row-barang" required>
                        <option value="">-- Pilih Barang --</option>
                        ${optBarang}
                    </select>
                </td>
                <td><input type="text" class="form-control row-satuan" readonly></td>
                <td><input type="number" name="qty[]" class="form-control row-qty" min="1" required value="1"></td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                        <input type="text" name="harga_satuan[]" class="form-control row-harga bg-white" value="0">
                    </div>
                </td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                        <input type="text" class="form-control text-success font-weight-bold row-total-tampil bg-white" value="0">
                    </div>
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-danger btn-remove" tabindex="-1"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        $('#item-container').append(html);

        const newSelect = $(`#row-${rowId} select.select2-item`);
        newSelect.select2({width: '100%'});

        newSelect.on('change', function() {
            let selectedVal = $(this).val();
            let currentRow = $(this).closest('tr');
            
            // Periksa apakah barang sudah dipilih di baris lain
            let isDuplicate = false;
            $('.select2-item').not(this).each(function() {
                if ($(this).val() == selectedVal && selectedVal !== '') {
                    isDuplicate = true;
                    return false; // break loop
                }
            });

            if (isDuplicate) {
                alert('Barang ini sudah dimasukkan di baris lain! Silakan pilih barang lain.');
                $(this).val('').trigger('change.select2'); // Reset value melalui select2
                currentRow.find('.row-satuan').val('');
                return;
            }

            let sat = $(this).find(':selected').data('satuan');
            currentRow.find('.row-satuan').val(sat || '');
        });

        // Event: Harga Satuan diedit manual -> Update Harga Total
        $(`#row-${rowId} .row-harga`).on('input keyup', function() {
            let rawStr = $(this).val();
            let cleanVal = removeRibuan(rawStr);
            $(this).val(formatRibuan(cleanVal)); // Format live

            let row = $(this).closest('tr');
            let q = parseFloat(row.find('.row-qty').val()) || 0;
            let sub = q * cleanVal;
            row.find('.row-total-tampil').val(formatRibuan(sub));
            calculateGrandTotal();
        });

        // Event: Qty diubah -> Update Harga Total
        $(`#row-${rowId} .row-qty`).on('input change', function() {
            let row = $(this).closest('tr');
            let q = parseFloat(row.find('.row-qty').val()) || 0;
            let h = removeRibuan(row.find('.row-harga').val());
            let sub = q * h;
            row.find('.row-total-tampil').val(formatRibuan(sub));
            calculateGrandTotal();
        });

        // Event: Harga Total diedit manual -> biarkan saja (jangan ubah harga satuan), tapi live format tetap jalan
        $(`#row-${rowId} .row-total-tampil`).on('input keyup', function(e) {
            let rawStr = $(this).val();
            let cleanVal = removeRibuan(rawStr);
            $(this).val(formatRibuan(cleanVal)); // Format live
            calculateGrandTotal();
        });

        checkRemoveBtns();
        calculateGrandTotal();
    }

    function checkRemoveBtns() {
        if ($('#item-container tr').length > 1) {
            $('.btn-remove').show();
        } else {
            $('.btn-remove').hide();
        }
    }

    $(document).ready(function() {
        addItemRow(); // First row default

        $('#btn-add-item').click(function() {
            addItemRow();
        });

        $(document).on('click', '.btn-remove', function() {
            $(this).closest('tr').remove();
            checkRemoveBtns();
            calculateGrandTotal();
        });

        $('#formPO').submit(function(e) {
            if ($('#item-container tr').length === 0) {
                e.preventDefault();
                alert('Pilih minimal satu barang untuk dibeli!');
                return false;
            }
            
            // Cegah Double Submit
            $('#btn-submit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
        });
    });
</script>
</body>
</html>
