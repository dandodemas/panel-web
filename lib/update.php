<?php
/* Actualización del código desde GitHub, solo para el acceso de agencia (Agencia → Sistema → Actualizar).
   Descarga la última versión del repositorio público y sustituye ÚNICAMENTE el código.
   Nunca toca: config.php, storage/ (base de datos, fotos, sesiones, copias) ni install/.
   Antes de escribir guarda una copia del código actual en storage/backups/. */

const UPDATE_REPO = 'dandodemas/panel-web';
const UPDATE_BRANCH = 'main';
const UPDATE_MAX_BYTES = 12 * 1024 * 1024;

/** Qué hace falta en este servidor y si está disponible. */
function update_requirements()
{
    $storage = rtrim((string) cfg('storage', PANEL_ROOT . '/storage'), '/');
    return [
        'Descomprimir (ZipArchive)' => class_exists('ZipArchive'),
        'Conexión a GitHub' => function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
        'Código escribible' => is_writable(PANEL_ROOT) && is_writable(PANEL_ROOT . '/lib'),
        'Carpeta de copias escribible' => is_dir($storage) && is_writable($storage),
    ];
}

function update_http_get($url, $accept = '*/*')
{
    if (strpos($url, 'https://') !== 0) {
        throw new RuntimeException('URL no permitida.');
    }
    $headers = ['User-Agent: dandodemas-panel-updater', 'Accept: ' . $accept];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code !== 200) {
            throw new RuntimeException('GitHub no responde (' . ($code ?: $err) . ').');
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 40, 'follow_location' => 1, 'max_redirects' => 4,
            'header' => implode("\r\n", $headers),
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new RuntimeException('No se pudo conectar con GitHub.');
        }
    }
    if (strlen($body) > UPDATE_MAX_BYTES) {
        throw new RuntimeException('La descarga es demasiado grande.');
    }
    return $body;
}

function update_latest_sha()
{
    $sha = trim(update_http_get(
        'https://api.github.com/repos/' . UPDATE_REPO . '/commits/' . UPDATE_BRANCH,
        'application/vnd.github.sha'
    ));
    if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
        throw new RuntimeException('Respuesta inesperada de GitHub.');
    }
    return $sha;
}

/** Ruta relativa segura dentro del panel, o null si debe ignorarse. */
function update_target_path($zipName)
{
    $name = str_replace('\\', '/', $zipName);
    $pos = strpos($name, '/');
    if ($pos === false) {
        return null;
    }
    $rel = substr($name, $pos + 1);            // quita la carpeta raíz «panel-web-<sha>/»
    if ($rel === '' || substr($rel, -1) === '/') {
        return null;
    }
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') {
            return null;
        }
    }
    $protected = ['config.php', '.gitignore', 'VERSION'];
    if (in_array($rel, $protected, true) || strpos($rel, 'storage/') === 0 || strpos($rel, 'install/') === 0 || strpos($rel, '.git') === 0) {
        return null;
    }
    $base = basename($rel);
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    $ok = ['php', 'css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'txt', 'md', 'json'];
    if (strpos($base, '.htaccess') === 0 || in_array($ext, $ok, true)) {
        return $rel;
    }
    return null;
}

function update_backup($storage)
{
    $dir = $storage . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/codigo-' . gmdate('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('No se pudo crear la copia de seguridad.');
    }
    foreach (['app', 'lib', 'views', 'assets', 'cron'] as $d) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PANEL_ROOT . '/' . $d, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen(PANEL_ROOT) + 1));
            }
        }
    }
    foreach (['index.php', '.htaccess'] as $f) {
        if (is_file(PANEL_ROOT . '/' . $f)) {
            $zip->addFile(PANEL_ROOT . '/' . $f, $f);
        }
    }
    $zip->close();
    $old = glob($dir . '/codigo-*.zip') ?: [];
    sort($old);
    foreach (array_slice($old, 0, max(0, count($old) - 3)) as $f) {
        @unlink($f);
    }
    return basename($file);
}

/** Devuelve ['status' => 'ok'|'same', 'sha' => ..., 'files' => n, 'backup' => nombre]. Lanza RuntimeException si algo falla. */
function update_apply()
{
    foreach (update_requirements() as $label => $ok) {
        if (!$ok) {
            throw new RuntimeException('Este servidor no permite actualizar desde aquí: falta «' . $label . '».');
        }
    }
    @set_time_limit(90);
    $storage = rtrim((string) cfg('storage', PANEL_ROOT . '/storage'), '/');
    $sha = update_latest_sha();
    if (setting_get('code_version') === $sha) {
        return ['status' => 'same', 'sha' => $sha, 'files' => 0, 'backup' => null];
    }

    $tmpDir = $storage . '/update';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0700, true);
    }
    $zipFile = $tmpDir . '/' . $sha . '.zip';
    file_put_contents($zipFile, update_http_get('https://codeload.github.com/' . UPDATE_REPO . '/zip/' . $sha, 'application/zip'));

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        @unlink($zipFile);
        throw new RuntimeException('El archivo descargado no es válido.');
    }
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $rel = update_target_path($name);
        if ($rel !== null) {
            $files[$rel] = $i;
        }
    }
    if (!isset($files['index.php']) || !isset($files['lib/bootstrap.php']) || !isset($files['lib/update.php'])) {
        $zip->close();
        @unlink($zipFile);
        throw new RuntimeException('La versión descargada no parece el panel (faltan archivos). No se ha cambiado nada.');
    }

    $backup = update_backup($storage);

    // Se escribe todo primero en archivos temporales y solo después se renombran (menos riesgo a medias).
    $staged = [];
    foreach ($files as $rel => $idx) {
        $data = $zip->getFromIndex($idx);
        if ($data === false) {
            $zip->close();
            throw new RuntimeException('No se pudo leer «' . $rel . '» del archivo. No se ha cambiado nada.');
        }
        $dest = PANEL_ROOT . '/' . $rel;
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $zip->close();
            throw new RuntimeException('No se pudo crear la carpeta «' . dirname($rel) . '». No se ha cambiado nada.');
        }
        $tmp = $dest . '.nuevo';
        if (file_put_contents($tmp, $data) === false) {
            $zip->close();
            foreach ($staged as $t) {
                @unlink($t[0]);
            }
            throw new RuntimeException('No se pudo escribir «' . $rel . '». No se ha cambiado nada.');
        }
        $staged[] = [$tmp, $dest];
    }
    $zip->close();
    @unlink($zipFile);
    foreach ($staged as $t) {
        if (!@rename($t[0], $t[1])) {
            @copy($t[0], $t[1]);
            @unlink($t[0]);
        }
    }
    setting_set('code_version', $sha);
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    return ['status' => 'ok', 'sha' => $sha, 'files' => count($staged), 'backup' => $backup];
}
