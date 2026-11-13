<?php
/* Borra las fotos caducadas. Programar a diario en hPanel → Avanzado → Cron jobs:
     php /ruta/al/panel/cron/limpiar.php
   Solo se puede ejecutar desde la línea de comandos. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../lib/bootstrap.php';
$r = run_cleanup();
echo 'Fotos caducadas borradas: ' . $r['deleted'] . ' (' . human_bytes($r['freed']) . ' liberados)' . PHP_EOL;
