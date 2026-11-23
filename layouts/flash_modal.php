<?php
// Menampilkan pesan session (success/error) sebagai modal (showAlert), bukan alert() bawaan browser.
// Include file ini setelah layouts/footer.php pada halaman tujuan redirect.
if (!empty($_SESSION['success']) || !empty($_SESSION['error'])):
?>
<script>
$(function() {
    <?php if (!empty($_SESSION['success'])): ?>
        showAlert(<?= json_encode($_SESSION['success']) ?>, 'success');
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        showAlert(<?= json_encode($_SESSION['error']) ?>, 'error');
    <?php endif; ?>
});
</script>
<?php
unset($_SESSION['success']);
unset($_SESSION['error']);
endif;
?>
