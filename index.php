<?php
/* Punto de entrada único del panel: todas las URLs pasan por aquí (ver .htaccess). */

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/agency.php';
require __DIR__ . '/app/brand.php';
require __DIR__ . '/app/api.php';
require __DIR__ . '/app/insights.php';

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method === 'HEAD') {
    $method = 'GET';
}
$path = request_path();

$S = '([a-z0-9-]{2,40})';      // slug de marca
$ID = '([a-z0-9]{10,32})';     // id de pieza, foto o marca

$routes = [
    ['GET',  '#^/$#',                                  'h_home'],
    ['GET',  '#^/login$#',                             'h_login_get'],
    ['POST', '#^/login$#',                             'h_login_post'],
    ['POST', '#^/logout$#',                            'h_logout_post'],

    ['GET',  '#^/agencia$#',                           'h_agency_list'],
    ['GET',  '#^/agencia/nueva$#',                     'h_agency_new_get'],
    ['POST', '#^/agencia/nueva$#',                     'h_agency_new_post'],
    ['GET',  '#^/agencia/sistema$#',                   'h_agency_system'],
    ['POST', '#^/agencia/sistema/limpiar$#',           'h_agency_cleanup'],
    ['POST', '#^/agencia/sistema/actualizar$#',        'h_agency_update'],
    ['POST', '#^/agencia/sistema/clave-api$#',         'h_agency_api_key'],
    ['GET',  "#^/api/$S/piezas$#",                    'h_api_items'],
    ['POST', "#^/api/$S/piezas/$ID/fotos$#",           'h_api_photos'],
    ['GET',  "#^/agencia/$ID$#",                       'h_agency_edit_get'],
    ['POST', "#^/agencia/$ID$#",                       'h_agency_edit_post'],
    ['POST', "#^/agencia/$ID/borrar$#",                'h_agency_delete'],
    ['POST', "#^/agencia/$ID/clave$#",                 'h_agency_password_json'],

    ['GET',  "#^/b/$S$#",                              'h_brand_home'],
    ['GET',  "#^/b/$S/tablero$#",                      'h_brand_board'],
    ['GET',  "#^/b/$S/calendario$#",                   'h_brand_calendar'],
    ['POST', "#^/b/$S/items$#",                        'h_item_create'],
    ['POST', "#^/b/$S/items/$ID$#",                    'h_item_update'],
    ['POST', "#^/b/$S/items/$ID/borrar$#",             'h_item_delete'],
    ['POST', "#^/b/$S/items/$ID/mover$#",              'h_item_move'],
    ['POST', "#^/b/$S/items/$ID/fotos$#",              'h_photos_upload'],
    ['POST', "#^/b/$S/fotos/$ID/borrar$#",             'h_photo_delete'],
    ['GET',  "#^/b/$S/fotos/$ID$#",                    'h_photo_get'],
    ['GET',  "#^/b/$S/fotos/$ID/descargar$#",          'h_photo_download'],
    ['GET',  "#^/b/$S/insights$#",                     'h_insights'],
    ['POST', "#^/b/$S/insights/registro$#",            'h_insights_save'],
    ['POST', "#^/b/$S/insights/cuenta$#",              'h_insights_account'],
    ['POST', "#^/b/$S/insights/actualizar$#",          'h_insights_refresh'],
    ['GET',  "#^/b/$S/insights/miniatura/$ID$#",       'h_insights_thumb'],
    ['POST', '#^/agencia/sistema/meta$#',              'h_agency_meta'],
    ['POST', "#^/b/$S/insights/$ID/borrar$#",          'h_insights_delete'],
    ['GET',  "#^/api/$S/insights$#",                   'h_api_insights_get'],
    ['POST', "#^/api/$S/insights$#",                   'h_api_insights_post'],
];

foreach ($routes as $r) {
    if ($r[0] === $method && preg_match($r[1], $path, $m)) {
        array_shift($m);
        call_user_func_array($r[2], $m);
        exit;
    }
}

not_found();
