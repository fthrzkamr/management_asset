<?php
require __DIR__ . '/../../dist/config/config.php';
require __DIR__ . '/../../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('inventory_atk_po', 'create');

$page_title = "Pengajuan PO ATK";

// Ambil data barang dari master inventory_atk
$q_barang = mysqli_query($conn, "SELECT * FROM inventory_atk ORDER BY nama_barang ASC");

// Ambil ID User (dummy atau ambil dari session sesuai standar app)
$id_user = $_SESSION['id_user'] ?? 1;

// Proses Form Submit PO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_po'])) {
    $items = $_POST['items'] ?? [];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan_po']);
    $nama_supplier = mysqli_real_escape_string($conn, $_POST['nama_supplier']);

    if (count($items) > 0 && !empty($nama_supplier)) {
        mysqli_begin_transaction($conn);
        try {
            // Pastikan tabel bst_po dapat menampung nama_supplier
            // Jika belum ada, kita bisa tambahkan. Untuk saat ini kita coba tangani di db jika sudah di-alter.
            // Opsional: kita alter on-the-fly untuk safety
            mysqli_query($conn, "ALTER TABLE bst_po ADD COLUMN IF NOT EXISTS nama_supplier VARCHAR(255) NULL AFTER tanggal_po");

            // 1. Generate Nomor PO
            $tahun = date('Y');
            $bulan = date('m');
            $q_urut = mysqli_query($conn, "SELECT COUNT(*) as total FROM bst_po WHERE MONTH(tanggal_po) = '$bulan' AND YEAR(tanggal_po) = '$tahun'");
            $r_urut = mysqli_fetch_assoc($q_urut);
            $urut = str_pad($r_urut['total'] + 1, 3, "0", STR_PAD_LEFT);
            $nomor_po = "PO/ATK/$tahun/$bulan/$urut";

            // 2. Insert Header PO
            $tanggal_po = date('Y-m-d');
            $q_po = "INSERT INTO bst_po (nomor_po, tanggal_po, nama_supplier, status, keterangan, created_by)
                     VALUES ('$nomor_po', '$tanggal_po', '$nama_supplier', 'Pending', '$keterangan', $id_user)";
            mysqli_query($conn, $q_po);
            $id_po = mysqli_insert_id($conn);

            // 3. Insert Detail PO
            foreach ($items as $id_atk => $qty) {
                if ($qty > 0) {
                    $qty_clean = intval($qty);
                    // Kita insert barang ke bst_po_detail merujuk id_barang (master atk)
                    // (Note: kolom sebelumnya bernamakan id_barang_bst, di aslinya itu merujuk ke id_barang master kalo di ATK)
                    $q_detail = "INSERT INTO bst_po_detail (id_po, id_barang_bst, qty_pesan, qty_dikirim, qty_diterima)
                                 VALUES ($id_po, $id_atk, $qty_clean, 0, 0)";
                    mysqli_query($conn, $q_detail);
                }
            }

            mysqli_commit($conn);
            echo "<script>alert('Berhasil! PO $nomor_po kepada $nama_supplier telah diajukan.'); window.location.href='dashboard.php';</script>";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<script>alert('Terjadi kesalahan database: " . addslashes($e->getMessage()) . "');</script>";
        }
    } else {
        echo "<script>alert('Nama Supplier harus diisi dan minimal 1 barang harus dipilih!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title><?= $page_title ?> - Aplikasi Asset Management</title>
    
    <link rel="icon" href="../../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Buat Purchase Order (PO) ATK</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../inventory_list.php">Inventory ATK</a></li>
                                <li class="breadcrumb-item active">Pengajuan PO Baru</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="../penerimaan/dashboard.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-truck-loading"></i> Lihat Riwayat PO Saya
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Keranjang Belanja PO</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="formPO">
                                    <div class="row mb-4">
                                        <div class="col-md-8">
                                            <label>Nama Supplier Pengadaan <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nama_supplier" placeholder="Cth: PT BST, Tokopedia, Shopee..." required>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row mb-4">
                                        <div class="col-md-9">
                                            <label>Cari & Pilih Barang (Dari Master ATK)</label>
                                            <select class="form-control select2" id="select_barang">
                                                <option value="">-- Ketikkan nama barang --</option>
                                                <?php while($b = mysqli_fetch_assoc($q_barang)): ?>
                                                    <option value="<?= $b['id_barang'] ?>" 
                                                            data-nama="<?= htmlspecialchars($b['nama_barang']) ?>"
                                                            data-kode="<?= htmlspecialchars($b['kode_barang']) ?>"
                                                            data-satuan="<?= htmlspecialchars($b['satuan']) ?>">
                                                        <?= htmlspecialchars($b['kode_barang']) ?> - <?= htmlspecialchars($b['nama_barang']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label>&nbsp;</label><br>
                                            <button type="button" class="btn btn-secondary btn-block" onclick="tambahKeDaftar()">
                                                <i class="fas fa-plus"></i> Tambah Item
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered table-striped" id="tabel_item">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th width="5%" class="text-center">No</th>
                                                    <th width="15%">Kode</th>
                                                    <th width="40%">Nama Barang</th>
                                                    <th width="15%" class="text-center">Kuantitas Pesan</th>
                                                    <th width="15%" class="text-center">Satuan</th>
                                                    <th width="10%" class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr id="row_kosong">
                                                    <td colspan="6" class="text-center text-muted py-4">Belum ada barang di keranjang PO.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-form-label col-md-2">Keterangan PO</label>
                                        <div class="col-md-10">
                                            <textarea class="form-control" name="keterangan_po" rows="3" placeholder="Catatan opsional ..."></textarea>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    <div class="text-right">
                                        <button type="submit" name="submit_po" class="btn btn-success btn-lg" id="btnSubmit" disabled>
                                            <i class="fas fa-paper-plane"></i> Kirim Pengajuan PO
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include __DIR__ . '/../../layouts/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/plugins/slimscroll/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/js/script.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "-- Cari Barang --",
                width: '100%'
            });
        });

        let itemCount = 0;

        function tambahKeDaftar() {
            const select = $('#select_barang');
            const selectedOption = select.find('option:selected');
            
            if(!selectedOption.val()) {
                alert("Pilih barang terlebih dahulu!");
                return;
            }

            const id = selectedOption.val();
            const nama = selectedOption.data('nama');
            const kode = selectedOption.data('kode');
            const satuan = selectedOption.data('satuan');

            if($('#row_' + id).length > 0) {
                alert("Barang ini sudah ada di keranjang. Silakan sesuaikan kuantitas di tabel.");
                return;
            }

            itemCount++;
            $('#row_kosong').hide();
            $('#btnSubmit').prop('disabled', false);

            const tr = `
                <tr id="row_${id}">
                    <td class="idx text-center">${itemCount}</td>
                    <td><span class="badge badge-info">${kode}</span></td>
                    <td><strong>${nama}</strong></td>
                    <td>
                        <input type="number" class="form-control form-control-sm" name="items[${id}]" value="1" min="1" required>
                    </td>
                    <td>${satuan}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm" onclick="hapusBaris(${id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;

            $('#tabel_item tbody').append(tr);
            select.val('').trigger('change');
        }

        function hapusBaris(id) {
            $('#row_' + id).remove();
            
            const rows = $('#tabel_item tbody tr:not(#row_kosong)');
            if(rows.length === 0) {
                $('#row_kosong').show();
                $('#btnSubmit').prop('disabled', true);
                itemCount = 0;
            } else {
                rows.each(function(index) {
                    $(this).find('.idx').text(index + 1);
                });
                itemCount = rows.length;
            }
        }
    </script>
</body>
</html>
