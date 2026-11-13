<?php
/* Utilidad de línea de comandos para preparar la base de datos.
     php install/instalar.php                         crea las tablas en la base configurada en config.php
     php install/instalar.php --dump=mysql            imprime el SQL de las tablas (para importarlo en phpMyAdmin)
     php install/instalar.php --import=ruta/datos.sql importa los datos (instrucciones separadas por «-- @@»)
   En producción lo habitual es importar schema.mysql.sql y datos.mysql.sql desde phpMyAdmin. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$dump = null;
$import = null;
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--dump=') === 0) {
        $dump = substr($a, 7);
    } elseif (strpos($a, '--import=') === 0) {
        $import = substr($a, 9);
    }
}

if ($dump !== null) {
    // Solo hace falta el esquema, no la base de datos.
    require __DIR__ . '/../lib/db.php';
    function cfg($k, $d = null) { return $d; }
    echo "-- Panel de contenido · Dando de Más© · esquema $dump\n";
    echo "SET NAMES utf8mb4;\n\n";
    foreach (schema_statements($dump) as $sql) {
        echo preg_replace('/\n\s{8,}/', "\n    ", trim($sql)) . ";\n\n";
    }
    exit;
}

require __DIR__ . '/../lib/bootstrap.php';
ensure_schema();
echo 'Tablas listas en ' . db_driver() . ".\n";

if ($import !== null) {
    if (!is_file($import)) {
        fwrite(STDERR, "No existe $import\n");
        exit(1);
    }
    $parts = preg_split("/\n-- @@\n/", file_get_contents($import));
    $n = 0;
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || strpos($stmt, '--') === 0 && strpos($stmt, "\n") === false) {
            continue;
        }
        db()->exec($stmt);
        $n++;
    }
    echo "Importadas $n instrucciones.\n";
}
