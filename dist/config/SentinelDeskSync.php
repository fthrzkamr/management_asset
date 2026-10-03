<?php
/**
 * Pushes one asset_dfm record to SentinelDesk's asset-matching endpoint.
 *
 * Called right after a successful insert/update to the asset_dfm table
 * (see z_tambah_asset/aset_insert.php and aset_update.php). Fire-and-forget
 * by design: if SentinelDesk is unreachable or rejects the payload, this
 * silently logs it and returns rather than breaking the asset save that
 * already succeeded in this system - the sync is best-effort, not something
 * the asset_dfm user should ever see fail as an error on their screen.
 *
 * Only sends the fields SentinelDesk actually uses (see its Asset model):
 * the code itself, physical condition, owned/rented status, and the raw
 * "who holds this" / "which branch" text it uses to auto-match against its
 * own Device/Employee records. Everything else asset_dfm tracks (specs,
 * photo, purchase value, etc.) stays local to this system - SentinelDesk
 * already has more reliable, real-time versions of that itself.
 */

// Change this to SentinelDesk's actual reachable address once deployed
// somewhere other than this same machine (e.g. http://192.168.99.110:8000).
define('SENTINELDESK_SYNC_URL', 'http://localhost:8000/api/assets/sync/');

// Must match ASSET_SYNC_API_KEY in SentinelDesk's own .env — copy that
// exact value here.
define('SENTINELDESK_SYNC_API_KEY', 'h+CWnRy+xRdMrH+xMnFxWnni5u6TYU/deOKRosT9zC8=');

function sentineldesk_sync_asset($kode_aset, $kondisi, $status_barang, $penanggung_jawab, $lokasi) {
    $payload = json_encode([
        'asset_code' => $kode_aset,
        'kondisi' => $kondisi,
        'status_barang' => $status_barang,
        'penanggung_jawab' => (string)$penanggung_jawab,
        'lokasi' => (string)$lokasi,
    ]);

    $ch = curl_init(SENTINELDESK_SYNC_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Api-Key: ' . SENTINELDESK_SYNC_API_KEY,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Short timeout - this runs inline in the user's save request, so a
    // down/slow SentinelDesk must never make asset_dfm feel like it hung.
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_code !== 200) {
        error_log(sprintf(
            '[SentinelDeskSync] Failed to sync asset %s (HTTP %s): %s %s',
            $kode_aset,
            $http_code,
            $curl_error,
            $response
        ));
        return false;
    }

    return true;
}
