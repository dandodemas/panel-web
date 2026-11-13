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

function photo_unlink_files($row)
{
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

/* ───── Foto traída desde el enlace de la creatividad (Google Drive) ───── */

/** Id de archivo de un enlace de Google Drive, o null si el enlace no es de Drive. */
function drive_file_id($url)
{
    $url = (string) $url;
    if (!preg_match('#^https://(drive|docs)\.google\.com/#', $url)) {
        return null;
    }
    if (preg_match('#/d/([A-Za-z0-9_-]{10,})#', $url, $m) || preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', $url, $m)) {
        return $m[1];
    }
    return null;
}

/** Descarga por https con tope de tamaño. Devuelve el contenido o null si no se puede. */
function drive_http_get($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'dandodemas-panel',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_MAXFILESIZE => PHOTO_MAX_BYTES,
        ]);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 40, 'follow_location' => 1, 'max_redirects' => 5, 'header' => 'User-Agent: dandodemas-panel']]);
    $body = @file_get_contents($url, false, $ctx, 0, PHOTO_MAX_BYTES + 1);
    return ($body !== false && strlen($body) <= PHOTO_MAX_BYTES) ? $body : null;
}

/**
 * Trae el archivo enlazado en la pieza (Drive, público) y lo guarda como foto.
 * Si el archivo no es una imagen (un vídeo, por ejemplo), usa la miniatura que genera Drive.
 * No repite una foto que ya se haya traído de ese mismo archivo.
 */
function photo_from_drive($url, $brandId, $itemId, $slug)
{
    $fileId = drive_file_id($url);
    if ($fileId === null) {
        return ['ok' => false, 'error' => 'El enlace no es de Google Drive.'];
    }
    $name = 'drive-' . $fileId;
    if (db_val('SELECT COUNT(*) FROM photos WHERE item_id = ? AND original_name = ? AND expired_at IS NULL', [$itemId, $name])) {
        return ['ok' => false, 'error' => 'Esta foto ya está en la pieza.'];
    }
    $candidatas = [
        'https://drive.usercontent.google.com/download?id=' . $fileId . '&export=download&confirm=t',
        'https://drive.google.com/thumbnail?id=' . $fileId . '&sz=w2000',
    ];
    $tmp = tempnam(sys_get_temp_dir(), 'ddm');
    try {
        foreach ($candidatas as $u) {
            $body = drive_http_get($u);
            if ($body === null) {
                continue;
            }
            file_put_contents($tmp, $body);
            if (@getimagesize($tmp)) {
                return photo_store($tmp, $name, $brandId, $itemId, $slug);
            }
        }
    } finally {
        @unlink($tmp);
    }
    return ['ok' => false, 'error' => 'No se ha podido traer la imagen. Comprueba que el archivo de Drive sea público.'];
}
