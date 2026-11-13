<?php
/* Columnas del tablero, tipos de contenido y plataformas. */

const STATUS_ORDER = ['IDEA', 'EN_DISENO', 'REVISION_CLIENTE', 'PROGRAMADO', 'PUBLICADO'];

const STATUS_LABEL = [
    'IDEA' => 'Idea',
    'EN_DISENO' => 'En diseño',
    'REVISION_CLIENTE' => 'Revisión cliente',
    'PROGRAMADO' => 'Programado',
    'PUBLICADO' => 'Publicado',
];

const TYPE_ORDER = ['POST', 'STORY', 'REEL', 'CARRUSEL', 'VIDEO'];

const TYPE_LABEL = [
    'POST' => 'Publicación',
    'STORY' => 'Historia',
    'REEL' => 'Reel',
    'CARRUSEL' => 'Carrusel',
    'VIDEO' => 'Vídeo',
];

const PLATFORM_SUGGESTIONS = ['Instagram', 'TikTok', 'LinkedIn', 'X', 'YouTube', 'Facebook', 'Email'];

const PHOTO_MAX_BYTES = 15 * 1024 * 1024;   // 15 MB por foto subida
const PHOTO_MAX_PER_ITEM = 20;
const PHOTO_MAX_SIDE = 2000;                // lado mayor tras optimizar
const PHOTO_THUMB_SIDE = 480;
const PHOTO_MAX_PIXELS = 60000000;          // 60 megapíxeles: por encima no se puede procesar con seguridad
