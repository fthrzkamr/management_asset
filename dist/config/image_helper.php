<?php
/**
 * Image Optimization Helper
 * Mengoptimasi, me-resize, dan mengompres foto aset ke format WebP
 * Menghemat ruang penyimpanan server hingga 85-95% tanpa mengurangi ketajaman visual.
 */

if (!function_exists('optimizeAndSaveAssetPhoto')) {
    /**
     * Memproses file upload: resize dimensi, auto-orientasi EXIF, dan kompresi WebP
     *
     * @param array  $fileInput   Array $_FILES['foto_asset']
     * @param string $uploadDir   Path folder tujuan (contoh: '../uploads/asset/')
     * @param string $prefix      Prefix nama file (default: 'asset_')
     * @param int    $maxWidth    Lebar maksimal dalam piksel (default: 1200)
     * @param int    $maxHeight   Tinggi maksimal dalam piksel (default: 1200)
     * @param int    $quality     Kualitas kompresi 1-100 (default: 80 - kualitas visual terbaik & ukuran hemat)
     * @return array              ['success' => bool, 'filename' => string, 'error' => string]
     */
    function optimizeAndSaveAssetPhoto($fileInput, $uploadDir, $prefix = 'asset_', $maxWidth = 1200, $maxHeight = 1200, $quality = 80)
    {
        $result = [
            'success'  => false,
            'filename' => '',
            'error'    => ''
        ];

        if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK || empty($fileInput['tmp_name'])) {
            $result['error'] = 'Tidak ada file yang diunggah atau terjadi kesalahan upload.';
            return $result;
        }

        // Pastikan folder upload ada
        $uploadDir = rtrim($uploadDir, '/\\') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tmpFile = $fileInput['tmp_name'];
        $origName = $fileInput['name'];
        $origSize = $fileInput['size'] ?? 0;
        $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // Validasi ukuran file (default maksimal 10MB)
        $maxBytes = 10 * 1024 * 1024;
        if ($origSize > $maxBytes) {
            $result['error'] = 'Ukuran file terlalu besar. Maksimal 10MB.';
            return $result;
        }

        // Validasi tipe file
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($origExt, $allowedExts)) {
            $result['error'] = 'Format file tidak didukung. Gunakan: JPG, JPEG, PNG, GIF, atau WEBP.';
            return $result;
        }

        // Cek info gambar
        $imgInfo = @getimagesize($tmpFile);
        if (!$imgInfo) {
            $result['error'] = 'File yang diunggah bukan format gambar yang valid.';
            return $result;
        }

        $width  = $imgInfo[0];
        $height = $imgInfo[1];
        $mime   = $imgInfo['mime'];

        // Cek ketersediaan GD extension
        $hasGD = extension_loaded('gd');
        $hasWebP = $hasGD && function_exists('imagewebp');

        // Tentukan ekstensi output (prioritaskan WebP, fallback ke JPG/ekstensi asli jika tidak support)
        $outExt = $hasWebP ? 'webp' : ($origExt === 'png' ? 'png' : 'jpg');
        $timestamp = date('YmdHis');
        $random = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $newFilename = "{$prefix}{$timestamp}_{$random}.{$outExt}";
        $destPath = $uploadDir . $newFilename;

        // Jika GD tidak ada, fallback simpan langsung
        if (!$hasGD) {
            if (move_uploaded_file($tmpFile, $destPath)) {
                $result['success'] = true;
                $result['filename'] = $newFilename;
            } else {
                $result['error'] = 'Gagal menyimpan file di server.';
            }
            return $result;
        }

        // Buat image resource berdasarkan MIME
        $srcImg = null;
        switch ($mime) {
            case 'image/jpeg':
                $srcImg = @imagecreatefromjpeg($tmpFile);
                break;
            case 'image/png':
                $srcImg = @imagecreatefrompng($tmpFile);
                break;
            case 'image/gif':
                $srcImg = @imagecreatefromgif($tmpFile);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $srcImg = @imagecreatefromwebp($tmpFile);
                }
                break;
        }

        if (!$srcImg) {
            // Jika gagal membuat resource, move file asli sebagai fallback
            if (move_uploaded_file($tmpFile, $destPath)) {
                $result['success'] = true;
                $result['filename'] = $newFilename;
            } else {
                $result['error'] = 'Gagal memproses gambar.';
            }
            return $result;
        }

        // Auto-orientasi EXIF (khusus JPEG dari smartphone)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($tmpFile);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $srcImg = imagerotate($srcImg, 180, 0);
                            break;
                        case 6:
                            $srcImg = imagerotate($srcImg, -90, 0);
                            $temp = $width;
                            $width = $height;
                            $height = $temp;
                            break;
                        case 8:
                            $srcImg = imagerotate($srcImg, 90, 0);
                            $temp = $width;
                            $width = $height;
                            $height = $temp;
                            break;
                    }
                }
            } catch (\Exception $e) {
                // Abaikan jika EXIF tidak dapat dibaca
            }
        }

        // Hitung skala baru (maksimal maxWidth x maxHeight, mempertahankan rasio)
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1.0);
        $newWidth = max(1, (int)round($width * $ratio));
        $newHeight = max(1, (int)round($height * $ratio));

        // Buat canvas truecolor baru
        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Penanganan transparansi jika sumber PNG/GIF/WEBP
        if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            // Background putih untuk JPEG jika ada padding
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
        }

        // Resample dengan kualitas tinggi (halus dan tidak pecah)
        imagecopyresampled($canvas, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Simpan hasil kompresi
        $saved = false;
        if ($hasWebP) {
            $saved = imagewebp($canvas, $destPath, $quality);
        } elseif ($outExt === 'jpg') {
            $saved = imagejpeg($canvas, $destPath, $quality);
        } elseif ($outExt === 'png') {
            $saved = imagepng($canvas, $destPath, 8);
        } else {
            $saved = imagejpeg($canvas, $destPath, $quality);
        }

        // Bebaskan memory GD
        imagedestroy($srcImg);
        imagedestroy($canvas);

        if ($saved && file_exists($destPath)) {
            $result['success']  = true;
            $result['filename'] = $newFilename;
        } else {
            // Fallback terakhir: move uploaded file
            if (move_uploaded_file($tmpFile, $destPath)) {
                $result['success']  = true;
                $result['filename'] = $newFilename;
            } else {
                $result['error'] = 'Gagal menyimpan file hasil optimasi ke server.';
            }
        }

        return $result;
    }
}

if (!function_exists('resizeImage')) {
    /**
     * Fungsi kompatibilitas mundur untuk resizeImage() lama
     */
    function resizeImage($source, $destination, $max_width = 1200, $max_height = 800, $quality = 80)
    {
        $info = @getimagesize($source);
        if (!$info) return false;

        $width  = $info[0];
        $height = $info[1];
        $mime   = $info['mime'];

        $ratio = min($max_width / $width, $max_height / $height, 1.0);
        $new_width  = (int)round($width * $ratio);
        $new_height = (int)round($height * $ratio);

        switch ($mime) {
            case 'image/jpeg':
                $source_img = @imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $source_img = @imagecreatefrompng($source);
                break;
            case 'image/gif':
                $source_img = @imagecreatefromgif($source);
                break;
            case 'image/webp':
                $source_img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : null;
                break;
            default:
                return false;
        }

        if (!$source_img) return false;

        $new_img = imagecreatetruecolor($new_width, $new_height);

        if ($mime == 'image/png' || $mime == 'image/gif' || $mime == 'image/webp') {
            imagealphablending($new_img, false);
            imagesavealpha($new_img, true);
            $transparent = imagecolorallocatealpha($new_img, 255, 255, 255, 127);
            imagefilledrectangle($new_img, 0, 0, $new_width, $new_height, $transparent);
        }

        imagecopyresampled($new_img, $source_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        $result = false;
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($new_img, $destination, $quality);
                break;
            case 'image/png':
                $result = imagepng($new_img, $destination, 8);
                break;
            case 'image/gif':
                $result = imagegif($new_img, $destination);
                break;
            case 'image/webp':
                $result = function_exists('imagewebp') ? imagewebp($new_img, $destination, $quality) : false;
                break;
        }

        imagedestroy($source_img);
        imagedestroy($new_img);

        return $result;
    }
}
