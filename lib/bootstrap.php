<?php
/* Arranque común: configuración, errores, cabeceras de seguridad y sesión. */

define('PANEL_ROOT', dirname(__DIR__));

$cfgFile = PANEL_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    echo 'Falta config.php: copia config.ejemplo.php como config.php y rellénalo.';
    exit;
}
$GLOBALS['CFG'] = require $cfgFile;

date_default_timezone_set('UTC');

require_once __DIR__ . '/util.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/items.php';
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/cleanup.php';
require_once __DIR__ . '/update.php';

/* ───── Errores: nunca se muestran al visitante, van a un log privado ───── */
if (cfg('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}
$storageDir = rtrim((string) cfg('storage', PANEL_ROOT . '/storage'), '/');
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0750, true);
}
ini_set('log_errors', '1');
ini_set('error_log', $storageDir . '/panel-errors.log');

function panel_fatal($e)
{
    error_log('[panel] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $wantsJson = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']));
    $hint = '';
    if (stripos($e->getMessage(), 'no such table') !== false || stripos($e->getMessage(), "doesn't exist") !== false) {
        $hint = ' Faltan las tablas de la base de datos: importa schema.mysql.sql desde phpMyAdmin.';
    }
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Error interno.' . $hint]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><p style="font:16px/1.5 sans-serif;padding:2rem">Ha habido un error interno.' . htmlspecialchars($hint) . '</p>';
    }
    exit;
}
set_exception_handler('panel_fatal');

if (PHP_SAPI !== 'cli') {
    /* ───── Cabeceras de seguridad ───── */
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

    /* ───── Sesión (carpeta propia para que nadie más la limpie antes de tiempo) ───── */
    $sessDir = $storageDir . '/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0700, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    ini_set('session.gc_maxlifetime', (string) (SESSION_IDLE_SECONDS + 3600));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ddm_panel');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (base() === '' ? '/' : base() . '/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => is_https(),
    ]);
    // Las llamadas de la API (/api/...) se autentican con clave, no con sesión: no crean cookies ni archivos de sesión.
    $isApi = strpos(request_path(), '/api/') === 0;
    if (!$isApi) {
        session_start();
    }
}

register_shutdown_function('maybe_cleanup');
