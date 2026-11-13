<?php
/* Fotos de las piezas: subida optimizada, entrega protegida y borrado.
   Las fotos viven en storage/ (fuera del alcance del navegador) y solo se sirven con sesión iniciada.
   Caducan solas pasados cfg('photo_days') días desde que se suben (ver cleanup.php). */

function photo_dir()
{
    return rtrim((string) cfg('storage', PANEL_ROOT . '/storage'), '/') . '/uploads';
}

function photo_days()
{
    $d = (int) cfg('photo_days', 60);
    return $d > 0 ? $d : 60;
}

/** Qué puede hacer la librería de imágenes de este servidor. */
function photo_capabilities()
{
    return [
        'gd' => extension_loaded('gd'),
        'jpeg' => function_exists('imagecreatefromjpeg'),
        'png' => function_exists('imagecreatefrompng'),
        'webp_read' => function_exists('imagecreatefromwebp'),
        'webp_write' => function_exists('imagewebp'),
        'exif' => function_exists('exif_read_data'),
        'finfo' => class_exists('finfo'),
    ];
}

function photo_decoder($type)
{
    if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        return 'imagecreatefromjpeg';
    }
    if ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        return 'imagecreatefrompng';
    }
    if ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        return 'imagecreatefromwebp';
    }
    return null;
}

/** Formato de salida: WebP si se puede, si no JPEG, si no PNG. */
function photo_encoder()
{
    if (function_exists('imagewebp')) {
        return ['ext' => 'webp', 'mime' => 'image/webp'];
    }
    if (function_exists('imagejpeg')) {
        return ['ext' => 'jpg', 'mime' => 'image/jpeg'];
    }
    return ['ext' => 'png', 'mime' => 'image/png'];
}

function photo_write($img, $enc, $path)
{
    if ($enc['ext'] === 'webp') {
        return imagewebp($img, $path, 82);
    }
    if ($enc['ext'] === 'jpg') {
        // JPEG no tiene transparencia: se rellena de blanco.
        $w = imagesx($img);
        $h = imagesy($img);
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
        return imagejpeg($flat, $path, 84);
    }
    return imagepng($img, $path, 6);
}

function photo_resized($img, $maxSide)
{
    $w = imagesx($img);
    $h = imagesy($img);
    $long = max($w, $h);
    if ($long <= $maxSide) {
        return $img;
    }
    $ratio = $maxSide / $long;
    $nw = max(1, (int) round($w * $ratio));
    $nh = max(1, (int) round($h * $ratio));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** Procesa una foto subida y la guarda. Devuelve ['ok'=>true,'photo'=>[…]] o ['ok'=>false,'error'=>'…']. */
function photo_store($tmpPath, $originalName, $brandId, $itemId, $slug)
{
    $bytes = @filesize($tmpPath);
    if (!$bytes) {
        return ['ok' => false, 'error' => 'El archivo está vacío.'];
    }
    if ($bytes > PHOTO_MAX_BYTES) {
        return ['ok' => false, 'error' => 'La foto pesa más de 15 MB.'];
    }
    $info = @getimagesize($tmpPath);
    if (!$info) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
    }
    $w = (int) $info[0];
    $h = (int) $info[1];
    $type = $info[2];
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return ['ok' => false, 'error' => 'Formato no admitido. Usa JPG, PNG o WebP.'];
    }
    if ($w * $h > PHOTO_MAX_PIXELS) {
        return ['ok' => false, 'error' => 'La foto tiene demasiados píxeles. Reduce su tamaño y vuelve a subirla.'];
    }
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
        }
    }
    $count = (int) db_val('SELECT COUNT(*) FROM photos WHERE item_id = ? AND expired_at IS NULL', [$itemId]);
    if ($count >= PHOTO_MAX_PER_ITEM) {
        return ['ok' => false, 'error' => 'Esta pieza ya tiene el máximo de ' . PHOTO_MAX_PER_ITEM . ' fotos.'];
    }

    $dir = photo_dir() . '/' . $brandId;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return ['ok' => false, 'error' => 'No se pueden guardar fotos: la carpeta no es escribible.'];
    }

    $id = new_id();
    $decoder = photo_decoder($type);
    $thumbRel = null;

    if ($decoder !== null) {
        @ini_set('memory_limit', '512M');
        $img = @$decoder($tmpPath);
        if (!$img) {
            return ['ok' => false, 'error' => 'No se ha podido leer la imagen.'];
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmpPath);
            $o = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
            $angle = $o === 3 ? 180 : ($o === 6 ? -90 : ($o === 8 ? 90 : 0));
            if ($angle !== 0 && function_exists('imagerotate')) {
                $rot = imagerotate($img, $angle, 0);
                if ($rot) {
                    $img = $rot;
                }
            }
        }
        $enc = photo_encoder();
        $main = photo_resized($img, PHOTO_MAX_SIDE);
        $mainRel = $brandId . '/' . $id . '.' . $enc['ext'];
        if (!photo_write($main, $enc, photo_dir() . '/' . $mainRel)) {
            return ['ok' => false, 'error' => 'No se ha podido guardar la foto.'];
        }
        $thumb = photo_resized($img, PHOTO_THUMB_SIDE);
        $thumbRel = $brandId . '/' . $id . '_t.' . $enc['ext'];
        if (!photo_write($thumb, $enc, photo_dir() . '/' . $thumbRel)) {
            $thumbRel = null;
        }
        $outW = imagesx($main);
        $outH = imagesy($main);
        $outMime = $enc['mime'];
    } else {
        // Sin soporte de este formato en el servidor: se guarda tal cual (ya validada).
        $ext = $type === IMAGETYPE_JPEG ? 'jpg' : ($type === IMAGETYPE_PNG ? 'png' : 'webp');
        $mainRel = $brandId . '/' . $id . '.' . $ext;
        if (!@copy($tmpPath, photo_dir() . '/' . $mainRel)) {
            return ['ok' => false, 'error' => 'No se ha podido guardar la foto.'];
        }
        $outW = $w;
        $outH = $h;
        $outMime = $ext === 'jpg' ? 'image/jpeg' : 'image/' . $ext;
    }

    // Se conserva también el archivo original tal cual se subió, para que se pueda descargar en su calidad.
    $origExt = $type === IMAGETYPE_JPEG ? 'jpg' : ($type === IMAGETYPE_PNG ? 'png' : 'webp');
    if (@copy($tmpPath, photo_dir() . '/' . $brandId . '/' . $id . '.orig.' . $origExt)) {
        @chmod(photo_dir() . '/' . $brandId . '/' . $id . '.orig.' . $origExt, 0640);
    }

    @chmod(photo_dir() . '/' . $mainRel, 0640);
    $size = (int) @filesize(photo_dir() . '/' . $mainRel);
    $now = now_utc();
    $expires = gmdate('Y-m-d H:i:s', time() + photo_days() * 86400);
    $name = substr(preg_replace('/[^\p{L}\p{N}\-_. ]/u', '', (string) basename($originalName)), 0, 200);

    db_exec(
        'INSERT INTO photos (id, item_id, brand_id, file, thumb, mime, width, height, bytes, original_name, created_at, expires_at, expired_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)',
        [$id, $itemId, $brandId, $mainRel, $thumbRel, $outMime, $outW, $outH, $size, $name, $now, $expires]
    );

    $row = db_one('SELECT * FROM photos WHERE id = ?', [$id]);
    return ['ok' => true, 'photo' => photo_public($row, $slug)];
}

function photo_public($r, $slug)
{
    $expired = !empty($r['expired_at']);
    return [
        'id' => $r['id'],
        'url' => $expired ? '' : url('/b/' . $slug . '/fotos/' . $r['id']),
        'download' => $expired ? '' : url('/b/' . $slug . '/fotos/' . $r['id'] . '/descargar'),
        'thumb' => $expired ? '' : url('/b/' . $slug . '/fotos/' . $r['id'] . '?t=1'),
        'name' => (string) $r['original_name'],
        'expired' => $expired,
        'expiresAt' => substr($r['expires_at'], 0, 10),
        'daysLeft' => $expired ? 0 : max(0, (int) ceil((strtotime($r['expires_at'] . ' UTC') - time()) / 86400)),
    ];
}

/** Fotos agrupadas por pieza: [itemId => [foto, …]]. */
function photos_for_items($itemIds, $slug)
{
    if (!$itemIds) {
        return [];
    }
    $out = [];
    foreach (array_chunk($itemIds, 400) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $rows = db_all('SELECT * FROM photos WHERE item_id IN (' . $ph . ') ORDER BY created_at ASC', $chunk);
        foreach ($rows as $r) {
            $out[$r['item_id']][] = photo_public($r, $slug);
        }
    }
    return $out;
}

/** Ruta del archivo original conservado de una foto, o null si no existe (fotos antiguas). */
function photo_original_path($row)
{
    $found = glob(photo_dir() . '/' . $row['brand_id'] . '/' . $row['id'] . '.orig.*');
    return ($found && is_file($found[0])) ? $found[0] : null;
}

function photo_unlink_files($row)
{
    $orig = photo_original_path($row);
    if ($orig) {
        @unlink($orig);
    }
    foreach (['file', 'thumb'] as $k) {
        if (!empty($row[$k])) {
            $path = photo_dir() . '/' . $row[$k];
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}

function photo_delete($brandId, $photoId)
{
    $row = db_one('SELECT * FROM photos WHERE id = ? AND brand_id = ?', [$photoId, $brandId]);
    if (!$row) {
        return false;
    }
    photo_unlink_files($row);
    db_exec('DELETE FROM photos WHERE id = ?', [$photoId]);
    return true;
}

function photos_delete_for_item($itemId)
{
    $rows = db_all('SELECT * FROM photos WHERE item_id = ?', [$itemId]);
    foreach ($rows as $r) {
        photo_unlink_files($r);
    }
    db_exec('DELETE FROM photos WHERE item_id = ?', [$itemId]);
}

/** Envía la foto al navegador (solo para quien tenga acceso a la marca). */
function photo_stream($row, $thumb)
{
    if (!empty($row['expired_at'])) {
        http_response_code(410);
        exit;
    }
    $rel = ($thumb && !empty($row['thumb'])) ? $row['thumb'] : $row['file'];
    $path = photo_dir() . '/' . $rel;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $etag = '"' . md5($rel . filemtime($path) . filesize($path)) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, max-age=86400');
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: ' . $row['mime']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline');
    readfile($path);
    exit;
}

/** Descarga de la foto en su calidad original (o, en fotos antiguas, la guardada; los WebP se entregan como JPG). */
function photo_download($row)
{
    if (!empty($row['expired_at'])) {
        http_response_code(410);
        exit;
    }
    $path = photo_original_path($row);
    $mime = null;
    if ($path === null) {
        $path = photo_dir() . '/' . $row['file'];
        $mime = $row['mime'];
    }
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $mime = $mime ?: (isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream');
    $base = pathinfo((string) $row['original_name'], PATHINFO_FILENAME);
    $base = trim(preg_replace('/[^\p{L}\p{N}\-_. ]/u', '', $base));
    if ($base === '') {
        $base = 'foto-' . substr($row['id'], 0, 8);
    }
    $data = null;
    if ($ext === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagejpeg')) {
        // Instagram y la mayoría de apps no aceptan WebP: se entrega como JPG.
        $img = @imagecreatefromwebp($path);
        if ($img) {
            ob_start();
            imagejpeg($img, null, 95);
            $data = ob_get_clean();
            $ext = 'jpg';
            $mime = 'image/jpeg';
        }
    }
    $filename = $base . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Cache-Control: private, no-store');
    if ($data !== null) {
        header('Content-Length: ' . strlen($data));
        echo $data;
    } else {
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
    exit;
}
