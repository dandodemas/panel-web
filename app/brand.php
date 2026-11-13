<?php
/* Páginas de cada marca (tablero y calendario) y su API de piezas y fotos. */

function api_input()
{
    $ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    return stripos($ct, 'application/json') !== false ? json_input() : $_POST;
}

function h_brand_home($slug)
{
    brand_context($slug);
    redirect('/b/' . $slug . '/tablero');
}

function h_brand_board($slug)
{
    list($user, $brand) = brand_context($slug);
    $items = items_public(items_for_board($brand['id']), $slug);
    render('board', [
        'title' => $brand['name'] . ' · Tablero',
        'user' => $user,
        'nav' => 'tablero',
        'brand' => $brand,
        'items' => $items,
        'slug' => $slug,
    ]);
}

function h_brand_calendar($slug)
{
    list($user, $brand) = brand_context($slug);
    $utc = new DateTimeZone('UTC');

    $today = madrid_today();
    $param = isset($_GET['month']) ? (string) $_GET['month'] : '';
    if (preg_match('/^(\d{4})-(\d{2})$/', $param, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
        $year = (int) $m[1];
        $month = (int) $m[2];
    } else {
        $year = (int) substr($today, 0, 4);
        $month = (int) substr($today, 5, 2);
    }

    $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $utc);
    $start = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
    $last = $first->modify('last day of this month');
    $end = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');

    $items = items_public(items_between($brand['id'], $start->format('Y-m-d'), $end->format('Y-m-d')), $slug);
    $byDay = [];
    foreach ($items as $it) {
        $byDay[$it['date']][] = $it;
    }

    $days = [];
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
        $key = $d->format('Y-m-d');
        $days[] = [
            'key' => $key,
            'num' => (int) $d->format('j'),
            'inMonth' => (int) $d->format('n') === $month,
            'today' => $key === $today,
            'items' => isset($byDay[$key]) ? $byDay[$key] : [],
        ];
    }

    $prev = $first->modify('-1 month');
    $next = $first->modify('+1 month');
    render('calendar', [
        'title' => $brand['name'] . ' · Calendario',
        'user' => $user,
        'nav' => 'calendario',
        'brand' => $brand,
        'slug' => $slug,
        'days' => $days,
        'items' => $items,
        'monthLabel' => month_label($month) . ' ' . $year,
        'prevHref' => url('/b/' . $slug . '/calendario?month=' . $prev->format('Y-m')),
        'nextHref' => url('/b/' . $slug . '/calendario?month=' . $next->format('Y-m')),
        'todayHref' => url('/b/' . $slug . '/calendario'),
    ]);
}

/* ───── API JSON ───── */

function h_item_create($slug)
{
    list($u, $brand) = brand_context_api($slug);
    require_csrf(true);
    $v = item_validate(api_input());
    if (!$v['ok']) {
        json_error($v['error'], 422);
    }
    json_response(['ok' => true, 'id' => item_create($brand['id'], $v['data'])]);
}

function h_item_update($slug, $id)
{
    list($u, $brand) = brand_context_api($slug);
    require_csrf(true);
    $v = item_validate(api_input());
    if (!$v['ok']) {
        json_error($v['error'], 422);
    }
    if (!item_update($brand['id'], $id, $v['data'])) {
        json_error('No encontrado.', 404);
    }
    json_response(['ok' => true]);
}

function h_item_delete($slug, $id)
{
    list($u, $brand) = brand_context_api($slug);
    require_csrf(true);
    if (!item_delete($brand['id'], $id)) {
        json_error('No encontrado.', 404);
    }
    json_response(['ok' => true]);
}

function h_item_move($slug, $id)
{
    list($u, $brand) = brand_context_api($slug);
    require_csrf(true);
    $in = api_input();
    if (isset($in['date'])) {
        $ok = item_move_date($brand['id'], $id, (string) $in['date']);
    } else {
        $ok = item_move_status($brand['id'], $id, isset($in['status']) ? (string) $in['status'] : '', isset($in['position']) ? (int) $in['position'] : 0);
    }
    if (!$ok) {
        json_error('No se ha podido mover.', 422);
    }
    json_response(['ok' => true]);
}

function upload_error_message($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'La foto pesa demasiado.';
        case UPLOAD_ERR_PARTIAL:
            return 'La subida se ha cortado. Inténtalo de nuevo.';
        case UPLOAD_ERR_NO_FILE:
            return 'No se ha recibido ningún archivo.';
        default:
            return 'No se ha podido recibir la foto.';
    }
}

function h_photos_upload($slug, $id)
{
    list($u, $brand) = brand_context_api($slug);
    require_csrf(true);
    photos_receive($brand, $slug, $id, false);
}

/** Recibe las fotos de $_FILES['photos'] para una pieza y responde en JSON. $skipRepeated evita duplicar un archivo con el mismo nombre. */
function photos_receive($brand, $slug, $id, $skipRepeated)
{
    if (!item_find($brand['id'], $id)) {
        json_error('No encontrado.', 404);
    }
    if (empty($_FILES['photos'])) {
        json_error('No se ha recibido ninguna foto (puede que pese demasiado).', 422);
    }
    $f = $_FILES['photos'];
    $names = is_array($f['name']) ? $f['name'] : [$f['name']];
    $tmps = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
    $errs = is_array($f['error']) ? $f['error'] : [$f['error']];

    $ok = [];
    $errors = [];
    $skipped = [];
    foreach ($names as $i => $name) {
        if ($errs[$i] !== UPLOAD_ERR_OK) {
            $errors[] = $name . ': ' . upload_error_message($errs[$i]);
            continue;
        }
        if (!is_uploaded_file($tmps[$i])) {
            $errors[] = $name . ': archivo no válido.';
            continue;
        }
        if ($skipRepeated && db_val('SELECT COUNT(*) FROM photos WHERE item_id = ? AND original_name = ? AND expired_at IS NULL', [$id, substr($name, 0, 200)])) {
            $skipped[] = $name;
            continue;
        }
        $r = photo_store($tmps[$i], $name, $brand['id'], $id, $slug);
        if ($r['ok']) {
            $ok[] = $r['photo'];
        } else {
            $errors[] = $name . ': ' . $r['error'];
        }
    }
    $good = count($ok) > 0 || (count($skipped) > 0 && !$errors);
    json_response(['ok' => $good, 'photos' => $ok, 'skipped' => $skipped, 'errors' => $errors], $good ? 200 : 422);
}

function h_photo_delete($slug, $pid)
{
    list($u, $brand) = brand_context_api($slug);
    require_csrf(true);
    if (!photo_delete($brand['id'], $pid)) {
        json_error('No encontrada.', 404);
    }
    json_response(['ok' => true]);
}

function h_photo_get($slug, $pid)
{
    list($u, $brand) = brand_context_api($slug);
    $row = db_one('SELECT * FROM photos WHERE id = ? AND brand_id = ?', [$pid, $brand['id']]);
    if (!$row) {
        http_response_code(404);
        exit;
    }
    photo_stream($row, !empty($_GET['t']));
}
