<?php
/* Piezas de contenido: validación, consultas y movimientos. */

/** Valida los datos del formulario. Devuelve ['ok'=>true,'data'=>[…]] o ['ok'=>false,'error'=>'…']. */
function item_validate($in)
{
    $title = trim((string) (isset($in['title']) ? $in['title'] : ''));
    if ($title === '') {
        return ['ok' => false, 'error' => 'El título es obligatorio.'];
    }
    if (str_len($title) > 200) {
        return ['ok' => false, 'error' => 'El título es demasiado largo (máximo 200 caracteres).'];
    }

    $notes = trim((string) (isset($in['notes']) ? $in['notes'] : ''));
    if (str_len($notes) > 2000) {
        return ['ok' => false, 'error' => 'Las notas son demasiado largas (máximo 2000 caracteres).'];
    }

    $creative = trim((string) (isset($in['creativeUrl']) ? $in['creativeUrl'] : ''));
    if ($creative !== '') {
        if (!preg_match('#^https?://.+#i', $creative)) {
            return ['ok' => false, 'error' => 'El enlace debe empezar por http:// o https://'];
        }
        if (strlen($creative) > 2000) {
            return ['ok' => false, 'error' => 'El enlace es demasiado largo.'];
        }
    }

    $platform = trim((string) (isset($in['platform']) ? $in['platform'] : ''));
    if ($platform === '') {
        return ['ok' => false, 'error' => 'Indica una plataforma.'];
    }
    if (str_len($platform) > 60) {
        return ['ok' => false, 'error' => 'La plataforma es demasiado larga.'];
    }

    $type = isset($in['contentType']) ? (string) $in['contentType'] : '';
    if (!in_array($type, TYPE_ORDER, true)) {
        return ['ok' => false, 'error' => 'Tipo de contenido no válido.'];
    }

    $status = isset($in['status']) ? (string) $in['status'] : '';
    if (!in_array($status, STATUS_ORDER, true)) {
        return ['ok' => false, 'error' => 'Estado no válido.'];
    }

    $date = isset($in['date']) ? trim((string) $in['date']) : '';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return ['ok' => false, 'error' => 'Fecha inválida.'];
    }

    $time = isset($in['time']) ? trim((string) $in['time']) : '';
    if ($time === '') {
        $time = '00:00';
    }
    if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $t) || (int) $t[1] > 23 || (int) $t[2] > 59) {
        return ['ok' => false, 'error' => 'Hora inválida.'];
    }
    // El selector solo ofrece medias horas: si llega otro minuto se redondea a la media hora más cercana.
    $h = (int) $t[1];
    $mi = (int) $t[2];
    $total = $h * 60 + ($mi < 15 ? 0 : ($mi < 45 ? 30 : 60));
    $time = sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);

    return ['ok' => true, 'data' => [
        'title' => $title,
        'notes' => $notes === '' ? null : $notes,
        'creative_url' => $creative === '' ? null : $creative,
        'platform' => $platform,
        'content_type' => $type,
        'status' => $status,
        'date_time' => $date . ' ' . $time . ':00',
    ]];
}

function item_next_pos($brandId, $status)
{
    $max = db_val('SELECT MAX(sort_pos) FROM content_items WHERE brand_id = ? AND status = ?', [$brandId, $status]);
    return $max === null ? 0 : ((int) $max + 1);
}

function item_create($brandId, $d)
{
    $id = new_id();
    $now = now_utc();
    db_exec(
        'INSERT INTO content_items (id, brand_id, title, notes, creative_url, platform, content_type, date_time, status, sort_pos, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $brandId, $d['title'], $d['notes'], $d['creative_url'], $d['platform'], $d['content_type'], $d['date_time'], $d['status'], item_next_pos($brandId, $d['status']), $now, $now]
    );
    return $id;
}

function item_find($brandId, $id)
{
    return db_one('SELECT * FROM content_items WHERE id = ? AND brand_id = ?', [$id, $brandId]);
}

function item_update($brandId, $id, $d)
{
    $row = item_find($brandId, $id);
    if (!$row) {
        return false;
    }
    $pos = (int) $row['sort_pos'];
    $statusChanged = $row['status'] !== $d['status'];
    if ($statusChanged) {
        $pos = item_next_pos($brandId, $d['status']);
    }
    db_exec(
        'UPDATE content_items SET title = ?, notes = ?, creative_url = ?, platform = ?, content_type = ?, date_time = ?, status = ?, sort_pos = ?, updated_at = ?
         WHERE id = ? AND brand_id = ?',
        [$d['title'], $d['notes'], $d['creative_url'], $d['platform'], $d['content_type'], $d['date_time'], $d['status'], $pos, now_utc(), $id, $brandId]
    );
    if ($statusChanged) {
        item_reindex($brandId, $row['status']);
    }
    return true;
}

function item_delete($brandId, $id)
{
    $row = item_find($brandId, $id);
    if (!$row) {
        return false;
    }
    photos_delete_for_item($id);
    db_exec('DELETE FROM content_items WHERE id = ? AND brand_id = ?', [$id, $brandId]);
    item_reindex($brandId, $row['status']);
    return true;
}

/** Deja las posiciones de una columna como 0..n-1. */
function item_reindex($brandId, $status)
{
    $ids = db_all('SELECT id FROM content_items WHERE brand_id = ? AND status = ? ORDER BY sort_pos ASC, updated_at ASC', [$brandId, $status]);
    foreach ($ids as $i => $r) {
        db_exec('UPDATE content_items SET sort_pos = ? WHERE id = ?', [$i, $r['id']]);
    }
}

/** Mueve una tarjeta del tablero a otra columna / posición. */
function item_move_status($brandId, $id, $status, $pos)
{
    if (!in_array($status, STATUS_ORDER, true)) {
        return false;
    }
    $row = item_find($brandId, $id);
    if (!$row) {
        return false;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $dest = db_all(
            'SELECT id FROM content_items WHERE brand_id = ? AND status = ? AND id <> ? ORDER BY sort_pos ASC, updated_at ASC',
            [$brandId, $status, $id]
        );
        $ids = array_map(function ($r) {
            return $r['id'];
        }, $dest);
        $pos = max(0, min((int) $pos, count($ids)));
        array_splice($ids, $pos, 0, [$id]);
        $now = now_utc();
        foreach ($ids as $i => $cid) {
            if ($cid === $id) {
                db_exec('UPDATE content_items SET status = ?, sort_pos = ?, updated_at = ? WHERE id = ?', [$status, $i, $now, $cid]);
            } else {
                db_exec('UPDATE content_items SET sort_pos = ? WHERE id = ?', [$i, $cid]);
            }
        }
        if ($row['status'] !== $status) {
            item_reindex($brandId, $row['status']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return true;
}

/** Cambia solo el día de una pieza (calendario), conservando la hora. */
function item_move_date($brandId, $id, $dateKey)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $dateKey, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return false;
    }
    $row = item_find($brandId, $id);
    if (!$row) {
        return false;
    }
    $time = substr($row['date_time'], 11, 8);
    db_exec('UPDATE content_items SET date_time = ?, updated_at = ? WHERE id = ? AND brand_id = ?', [$dateKey . ' ' . $time, now_utc(), $id, $brandId]);
    return true;
}

/** Todas las piezas de la marca (tablero), con sus fotos. */
function items_for_board($brandId)
{
    return db_all('SELECT * FROM content_items WHERE brand_id = ? ORDER BY sort_pos ASC, updated_at ASC', [$brandId]);
}

/** Piezas entre dos fechas incluidas (calendario). */
function items_between($brandId, $fromDate, $toDate)
{
    return db_all(
        'SELECT * FROM content_items WHERE brand_id = ? AND date_time >= ? AND date_time <= ? ORDER BY date_time ASC, sort_pos ASC',
        [$brandId, $fromDate . ' 00:00:00', $toDate . ' 23:59:59']
    );
}

/** Convierte filas de BD en los arrays que usan las vistas y el JavaScript. */
function items_public($rows, $slug)
{
    $ids = array_map(function ($r) {
        return $r['id'];
    }, $rows);
    $photos = photos_for_items($ids, $slug);
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => $r['id'],
            'title' => $r['title'],
            'notes' => (string) $r['notes'],
            'creativeUrl' => (string) $r['creative_url'],
            'platform' => $r['platform'],
            'contentType' => $r['content_type'],
            'status' => $r['status'],
            'date' => substr($r['date_time'], 0, 10),
            'time' => substr($r['date_time'], 11, 5),
            'photos' => isset($photos[$r['id']]) ? $photos[$r['id']] : [],
        ];
    }
    return $out;
}
