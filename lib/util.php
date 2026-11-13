<?php
/* Utilidades generales: configuración, URLs, escape, JSON, CSRF. */

function cfg($key, $default = null)
{
    $c = isset($GLOBALS['CFG']) ? $GLOBALS['CFG'] : [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($c) || !array_key_exists($part, $c)) {
            return $default;
        }
        $c = $c[$part];
    }
    return $c;
}

function base()
{
    return rtrim((string) cfg('base', ''), '/');
}

function url($path = '/')
{
    return base() . ($path === '' ? '/' : $path);
}

/** URL de un recurso estático con versión (cambia al editar el archivo, así no se queda en caché). */
function asset($file)
{
    $p = PANEL_ROOT . '/assets/' . $file;
    $v = is_file($p) ? filemtime($p) : 0;
    return url('/assets/' . $file) . '?v=' . $v;
}

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function new_id()
{
    return bin2hex(random_bytes(12));
}

function now_utc()
{
    return gmdate('Y-m-d H:i:s');
}

function madrid_today()
{
    $d = new DateTime('now', new DateTimeZone('Europe/Madrid'));
    return $d->format('Y-m-d');
}

function json_response($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error($message, $status = 400)
{
    json_response(['ok' => false, 'error' => $message], $status);
}

function redirect($path, $code = 302)
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)), true, $code);
    exit;
}

/** Ruta de la petición sin la carpeta base: «/b/keik/tablero». */
function request_path()
{
    $path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    $path = rawurldecode((string) $path);
    $base = base();
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . trim($path, '/');
    return $path === '' ? '/' : $path;
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '0.0.0.0';
}

/* ───── CSRF ───── */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_valid()
{
    $sent = '';
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $sent = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (!empty($_POST['_csrf'])) {
        $sent = (string) $_POST['_csrf'];
    }
    return $sent !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** Corta la petición si el token CSRF no es válido. Para formularios devuelve a la página anterior con un aviso. */
function require_csrf($asJson = false)
{
    if (csrf_valid()) {
        return;
    }
    if ($asJson) {
        json_error('Sesión caducada. Recarga la página e inténtalo de nuevo.', 419);
    }
    http_response_code(419);
    echo 'Sesión caducada. Recarga la página e inténtalo de nuevo.';
    exit;
}

/** Cuerpo JSON de la petición como array. */
function json_input()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function str_len($s)
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function generate_password($length = 12)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function month_label($month)
{
    $labels = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $labels[$month - 1];
}

function human_bytes($b)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $b = (float) $b;
    while ($b >= 1024 && $i < count($units) - 1) {
        $b /= 1024;
        $i++;
    }
    return ($i === 0 ? (int) $b : round($b, 1)) . ' ' . $units[$i];
}
