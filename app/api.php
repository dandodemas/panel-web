<?php
/* API para subir fotos de forma automática (la usa un script local o una conversación de Claude).
   Se autentica con una clave que solo genera la agencia (Agencia → Sistema). En la base de datos se guarda
   únicamente su huella (sha256), nunca la clave. Cabecera: X-Panel-Token: <clave>. */

function api_token_hash()
{
    return setting_get('api_token_hash');
}

function api_auth()
{
    $hash = api_token_hash();
    if (!$hash) {
        json_error('La subida automática no está activada. Genera la clave en Agencia → Sistema.', 403);
    }
    $since = gmdate('Y-m-d H:i:s', time() - 900);
    $fails = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND username = ? AND created_at >= ?', [client_ip(), 'api-token', $since]);
    if ($fails >= 20) {
        json_error('Demasiados intentos. Espera unos minutos.', 429);
    }
    $given = isset($_SERVER['HTTP_X_PANEL_TOKEN']) ? trim((string) $_SERVER['HTTP_X_PANEL_TOKEN']) : '';
    if ($given === '' || !hash_equals($hash, hash('sha256', $given))) {
        login_record_failure('api-token');
        json_error('Clave no válida.', 401);
    }
}

function api_brand($slug)
{
    api_auth();
    $brand = db_one('SELECT id, slug, name FROM brands WHERE slug = ?', [$slug]);
    if (!$brand) {
        json_error('Marca no encontrada.', 404);
    }
    return $brand;
}

/** GET /api/{marca}/piezas?desde=AAAA-MM-DD&hasta=AAAA-MM-DD&titulo=texto → lista de piezas con su id y nº de fotos. */
function h_api_items($slug)
{
    $brand = api_brand($slug);
    $from = isset($_GET['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde']) ? $_GET['desde'] : '2000-01-01';
    $to = isset($_GET['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta']) ? $_GET['hasta'] : '2100-01-01';
    $title = isset($_GET['titulo']) ? mb_strtolower(trim((string) $_GET['titulo'])) : '';
    $items = items_public(items_between($brand['id'], $from, $to), $slug);
    $out = [];
    foreach ($items as $it) {
        if ($title !== '' && mb_strpos(mb_strtolower($it['title']), $title) === false) {
            continue;
        }
        $live = array_filter($it['photos'], function ($p) {
            return empty($p['expired']);
        });
        $out[] = [
            'id' => $it['id'], 'date' => $it['date'], 'time' => $it['time'], 'title' => $it['title'],
            'type' => $it['contentType'], 'platform' => $it['platform'], 'status' => $it['status'],
            'photos' => count($live),
        ];
    }
    json_response(['ok' => true, 'brand' => $brand['slug'], 'items' => $out]);
}

/** POST /api/{marca}/piezas/{id}/fotos (multipart, campo «photos[]») → sube las fotos a esa pieza; omite las ya subidas con el mismo nombre. */
function h_api_photos($slug, $id)
{
    $brand = api_brand($slug);
    photos_receive($brand, $slug, $id, true);
}

/** POST /agencia/sistema/clave-api  (acción = generar | desactivar) */
function h_agency_api_key()
{
    require_agency();
    require_csrf();
    $action = isset($_POST['accion']) ? $_POST['accion'] : 'generar';
    if ($action === 'desactivar') {
        db_exec('DELETE FROM settings WHERE k = ?', ['api_token_hash']);
        flash_set('Subida automática desactivada. La clave anterior ya no funciona.');
    } else {
        $token = 'ddm_' . bin2hex(random_bytes(20));
        setting_set('api_token_hash', hash('sha256', $token));
        flash_set('Clave nueva (cópiala ahora, no se vuelve a mostrar): ' . $token);
    }
    redirect('/agencia/sistema');
}
