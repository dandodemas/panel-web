<?php
/* Borrado automático de fotos caducadas (2 meses desde que se subieron).
   Se ejecuta por cron (cron/limpiar.php) y, como red de seguridad, también de forma ocasional
   mientras alguien usa el panel, para que funcione aunque no se configure ningún cron. */

function run_cleanup()
{
    $now = now_utc();
    $rows = db_all('SELECT * FROM photos WHERE expired_at IS NULL AND expires_at <= ?', [$now]);
    $freed = 0;
    foreach ($rows as $r) {
        $freed += (int) $r['bytes'];
        photo_unlink_files($r);
        db_exec('UPDATE photos SET expired_at = ? WHERE id = ?', [$now, $r['id']]);
    }
    // Los avisos de «foto caducada» se conservan 6 meses y luego se eliminan.
    db_exec('DELETE FROM photos WHERE expired_at IS NOT NULL AND expired_at < ?', [gmdate('Y-m-d H:i:s', time() - 180 * 86400)]);
    setting_set('last_cleanup', $now);
    setting_set('last_cleanup_deleted', (string) count($rows));
    return ['deleted' => count($rows), 'freed' => $freed];
}

function maybe_cleanup()
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET' || mt_rand(1, 8) !== 1) {
        return;
    }
    try {
        $last = setting_get('last_cleanup');
        if (!$last || strtotime($last . ' UTC') < time() - 6 * 3600) {
            run_cleanup();
        }
    } catch (Throwable $e) {
        error_log('[panel] limpieza: ' . $e->getMessage());
    }
}
