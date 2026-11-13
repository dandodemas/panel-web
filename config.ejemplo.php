<?php
/* Copia este archivo como «config.php» en el servidor (misma carpeta) y rellénalo.
   Ese archivo no debe subirse a ningún repositorio ni compartirse. */
return [
  // Ruta del panel dentro de la web, sin barra final. En producción: '/panel'.
  'base' => '/panel',

  // Base de datos. En Hostinger: hPanel → Bases de datos → Gestión → crear base MySQL.
  'db' => [
    'driver' => 'mysql',          // 'mysql' en producción; 'sqlite' solo para pruebas en local
    'host'   => 'localhost',
    'name'   => '',
    'user'   => '',
    'pass'   => '',
    // 'file' => __DIR__ . '/storage/panel.sqlite',   // solo con driver sqlite
  ],

  // Frase secreta con la que se cifran las contraseñas de las marcas.
  // Para conservar las contraseñas ya existentes debe ser la MISMA que usaba el panel anterior.
  'credentials_key' => '',

  // Carpeta de fotos subidas (debe poder escribir PHP). Por defecto, dentro del panel y protegida.
  'storage' => __DIR__ . '/storage',

  // Días que se conserva cada foto desde que se sube. Pasado ese plazo se borra sola.
  'photo_days' => 60,

  // true solo en local para ver los errores en pantalla.
  'debug' => false,
];
