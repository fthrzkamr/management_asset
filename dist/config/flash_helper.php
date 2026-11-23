<?php
/**
 * Pengganti `echo "<script>alert('..'); history.back();</script>"` pada halaman proses (tanpa layout).
 * Menampilkan modal Bootstrap mandiri, lalu OK -> redirect / kembali.
 *
 * flashModalPage($pesan, $tipe = 'error', $redirect = 'back');  // exit otomatis
 *   $tipe     : success | error | warning | info
 *   $redirect : 'back' (history.back) atau URL tujuan
 */
if (!function_exists('flashModalPage')) {
    function flashModalPage($message, $type = 'error', $redirect = 'back')
    {
        $presets = [
            'success' => ['Berhasil',   'bg-success', 'fa-check-circle'],
            'error'   => ['Error',      'bg-danger',  'fa-times-circle'],
            'warning' => ['Peringatan', 'bg-warning', 'fa-exclamation-triangle'],
            'info'    => ['Informasi',  'bg-info',    'fa-info-circle'],
        ];
        $p = $presets[$type] ?? $presets['info'];
        $msgHtml = nl2br(htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8'));
        $go = ($redirect === 'back')
            ? 'history.back();'
            : 'window.location.href=' . json_encode($redirect) . ';';
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>' . $p[0] . '</title>'
           . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css">'
           . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">'
           . '<style>body{background:#f4f6f9;font-family:Poppins,Arial,sans-serif}</style></head><body>'
           . '<div class="modal fade" id="flashModal" tabindex="-1" data-backdrop="static" data-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
           . '<div class="modal-header ' . $p[1] . ' text-white"><h5 class="modal-title"><i class="fas ' . $p[2] . '"></i> ' . $p[0] . '</h5></div>'
           . '<div class="modal-body"><p class="mb-0">' . $msgHtml . '</p></div>'
           . '<div class="modal-footer"><button type="button" class="btn btn-primary" id="flashOk"><i class="fas fa-check"></i> OK</button></div>'
           . '</div></div></div>'
           . '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>'
           . '<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>'
           . '<script>$(function(){$("#flashModal").modal("show");$("#flashOk").on("click",function(){' . $go . '});});</script>'
           . '</body></html>';
        exit;
    }
}
