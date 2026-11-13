<?php
/* Insights de cada marca: solo para el acceso de agencia (los clientes no ven ni pueden abrir esta página). */

function insights_agency_brand($slug)
{
    $user = require_agency();
    $brand = db_one('SELECT id, slug, name FROM brands WHERE slug = ?', [$slug]);
    if (!$brand) {
        not_found();
    }
    insights_ensure();
    return [$user, $brand];
}

function h_insights($slug)
{
    list($user, $brand) = insights_agency_brand($slug);
    $bid = $brand['id'];

    $snaps = db_all('SELECT * FROM insights_snapshots WHERE brand_id = ? ORDER BY day ASC', [$bid]);
    $series = [];
    foreach ($snaps as $s) {
        if ($s['followers'] !== null) {
            $series[] = ['day' => $s['day'], 'v' => (int) $s['followers']];
        }
    }
    $latest = $series ? end($series) : null;
    $delta = null;
    if ($latest && count($series) > 1) {
        $target = strtotime($latest['day'] . ' -30 days');
        $ref = $series[0];
        foreach ($series as $p) {
            if (strtotime($p['day']) <= $target) {
                $ref = $p;
            }
        }
        $delta = ['n' => $latest['v'] - $ref['v'], 'since' => $ref['day']];
    }

    $posts = db_all(
        'SELECT p.*, i.title AS item_title FROM insights_posts p LEFT JOIN content_items i ON i.id = p.item_id
         WHERE p.brand_id = ? ORDER BY p.posted_at DESC',
        [$bid]
    );
    $kinds = [];
    $totalInter = 0;
    $best = null;
    foreach ($posts as $p) {
        $inter = (int) $p['likes'] + (int) $p['comments'] + (int) $p['saves'] + (int) $p['shares'];
        $k = $p['kind'] ?: 'otro';
        if (!isset($kinds[$k])) {
            $kinds[$k] = ['n' => 0, 'inter' => 0, 'views' => 0, 'viewsN' => 0];
        }
        $kinds[$k]['n']++;
        $kinds[$k]['inter'] += $inter;
        if ($p['views'] !== null) {
            $kinds[$k]['views'] += (int) $p['views'];
            $kinds[$k]['viewsN']++;
        }
        $totalInter += $inter;
        if (!$best || $inter > $best['inter']) {
            $best = ['inter' => $inter, 'post' => $p];
        }
    }
    $postSeries = [];
    foreach (array_reverse($posts) as $p) {
        if ($p['posted_at'] && ($p['kind'] ?: '') !== 'story') {
            $postSeries[] = ['day' => substr($p['posted_at'], 0, 10), 'v' => (int) $p['likes'] + (int) $p['comments'] + (int) $p['saves'] + (int) $p['shares']];
        }
    }
    uasort($kinds, function ($a, $b) {
        return ($b['inter'] / max(1, $b['n'])) <=> ($a['inter'] / max(1, $a['n']));
    });

    // ── Gráfica elegible: métrica y periodo ──
    $metricDefs = [
        'seguidores' => ['Seguidores', 'snap', 'followers'],
        'p_inter' => ['Interacciones', 'post', 'inter'],
        'p_likes' => ['Me gusta', 'post', 'likes'],
        'p_coment' => ['Comentarios', 'post', 'comments'],
        'p_vistas' => ['Visualizaciones', 'post', 'views'],
        'p_guard' => ['Guardados', 'post', 'saves'],
        'p_alcance' => ['Alcance', 'post', 'reach'],
    ];
    $mKey = isset($_GET['m']) && isset($metricDefs[$_GET['m']]) ? $_GET['m'] : 'seguidores';
    $rangeOpts = ['7' => '7 días', '30' => '30 días', '90' => '90 días', '365' => '1 año', '0' => 'Todo'];
    $rKey = '90';
    if (isset($_GET['r']) && preg_match('/^\d{1,4}$/', (string) $_GET['r'])) {
        $rKey = (string) (int) $_GET['r'];
    }
    $since = $rKey === '0' ? '0000-00-00' : date('Y-m-d', strtotime(madrid_today() . ' -' . (int) $rKey . ' days'));
    $def = $metricDefs[$mKey];
    $chartSeries = [];
    if ($def[1] === 'snap') {
        foreach ($snaps as $sn) {
            if ($sn[$def[2]] !== null && $sn['day'] >= $since) {
                $chartSeries[] = ['day' => $sn['day'], 'v' => (int) $sn[$def[2]]];
            }
        }
    } else {
        foreach (array_reverse($posts) as $p) {
            if (!$p['posted_at'] || ($p['kind'] ?: '') === 'story' || substr($p['posted_at'], 0, 10) < $since) {
                continue;
            }
            if ($def[2] === 'inter') {
                $v = (int) $p['likes'] + (int) $p['comments'] + (int) $p['saves'] + (int) $p['shares'];
            } else {
                if ($p[$def[2]] === null) {
                    continue;
                }
                $v = (int) $p[$def[2]];
            }
            $chartSeries[] = [
                'day' => substr($p['posted_at'], 0, 10), 'v' => $v, 'kind' => $p['kind'], 'title' => $p['item_title'] ?: '',
                'caption' => $p['caption'], 'link' => preg_match('#^https://#', $p['post_ref']) ? $p['post_ref'] : '',
                'time' => substr($p['posted_at'], 11, 5),
                'thumb' => !empty($p['thumb']) ? url('/b/' . $slug . '/insights/miniatura/' . $p['id']) : '',
                'stats' => ['likes' => $p['likes'], 'comments' => $p['comments'], 'views' => $p['views'], 'saves' => $p['saves'], 'shares' => $p['shares'], 'reach' => $p['reach']],
            ];
        }
    }

    render('insights', [
        'title' => $brand['name'] . ' · Insights',
        'user' => $user,
        'nav' => 'insights',
        'brand' => $brand,
        'slug' => $slug,
        'flash' => flash_take(),
        'snaps' => array_reverse($snaps),
        'series' => $series,
        'metricDefs' => $metricDefs,
        'rangeOpts' => $rangeOpts,
        'mKey' => $mKey,
        'rKey' => $rKey,
        'chartSeries' => $chartSeries,
        'chartType' => $def[1] === 'post' ? 'bars' : 'line',
        'latest' => $latest,
        'delta' => $delta,
        'posts' => array_slice($posts, 0, 60),
        'postCount' => count($posts),
        'avgInter' => count($posts) ? round($totalInter / count($posts), 1) : null,
        'best' => $best,
        'kinds' => $kinds,
        'today' => madrid_today(),
        'metaConn' => meta_connection(),
        'metaAcc' => meta_brand_account($bid),
        'noteAuto' => db_one('SELECT body, created_at FROM insights_notes WHERE brand_id = ? AND source = ? ORDER BY created_at DESC, sig DESC LIMIT 1', [$bid, 'auto']),
        'noteIa' => db_one('SELECT body, created_at FROM insights_notes WHERE brand_id = ? AND source = ? ORDER BY created_at DESC LIMIT 1', [$bid, 'ia']),
    ]);
}

function h_insights_save($slug)
{
    list($user, $brand) = insights_agency_brand($slug);
    require_csrf();
    try {
        insights_snapshot_save($brand['id'], $_POST, 'manual');
        flash_set('Registro guardado.');
    } catch (InvalidArgumentException $e) {
        flash_set($e->getMessage(), 'err');
    }
    redirect('/b/' . $slug . '/insights');
}

function h_insights_delete($slug, $id)
{
    list($user, $brand) = insights_agency_brand($slug);
    require_csrf();
    db_exec('DELETE FROM insights_snapshots WHERE id = ? AND brand_id = ?', [$id, $brand['id']]);
    flash_set('Registro borrado.');
    redirect('/b/' . $slug . '/insights');
}

/** POST /api/{marca}/insights  {snapshot:{day,followers,…}, posts:[{ref,postedAt,kind,likes,comments,views,…}]} */
function h_api_insights_post($slug)
{
    $brand = api_brand($slug);
    insights_ensure();
    $in = json_input();
    $out = ['ok' => true, 'snapshot' => false, 'posts' => ['created' => 0, 'updated' => 0], 'errors' => []];
    if (!empty($in['snapshot']) && is_array($in['snapshot'])) {
        try {
            insights_snapshot_save($brand['id'], $in['snapshot'], 'api');
            $out['snapshot'] = true;
        } catch (InvalidArgumentException $e) {
            $out['errors'][] = 'registro: ' . $e->getMessage();
        }
    }
    if (!empty($in['posts']) && is_array($in['posts'])) {
        foreach (array_slice($in['posts'], 0, 300) as $i => $p) {
            try {
                $out['posts'][insights_post_save($brand['id'], is_array($p) ? $p : [])]++;
            } catch (InvalidArgumentException $e) {
                $out['errors'][] = 'publicación ' . ($i + 1) . ': ' . $e->getMessage();
            }
        }
    }
    if (!empty($in['analysis']) && is_string($in['analysis'])) {
        db_exec('INSERT INTO insights_notes (id, brand_id, source, body, sig, created_at) VALUES (?, ?, ?, ?, ?, ?)', [new_id(), $brand['id'], 'ia', mb_substr(trim($in['analysis']), 0, 4000), null, now_utc()]);
        $out['analysis'] = true;
    }
    if ($out['snapshot'] || $out['posts']['created'] + $out['posts']['updated'] > 0) {
        $out['summary_updated'] = insights_update_analysis($brand['id']);
    }
    $out['ok'] = !empty($out['analysis']) || $out['snapshot'] || $out['posts']['created'] + $out['posts']['updated'] > 0;
    json_response($out, $out['ok'] ? 200 : 422);
}

/** GET /api/{marca}/insights → últimos registros y publicaciones guardadas. */
function h_api_insights_get($slug)
{
    $brand = api_brand($slug);
    insights_ensure();
    json_response([
        'ok' => true,
        'notes' => db_all('SELECT source, body, created_at FROM insights_notes WHERE brand_id = ? ORDER BY created_at DESC LIMIT 4', [$brand['id']]),
        'snapshots' => db_all('SELECT day, platform, followers, following, posts, reach, impressions, interactions, profile_visits, source FROM insights_snapshots WHERE brand_id = ? ORDER BY day DESC LIMIT 60', [$brand['id']]),
        'posts' => db_all('SELECT post_ref, posted_at, kind, likes, comments, views, saves, shares, reach, item_id FROM insights_posts WHERE brand_id = ? ORDER BY posted_at DESC LIMIT 100', [$brand['id']]),
    ]);
}

/** POST /b/{marca}/insights/cuenta  (ig_id elegido entre las cuentas de la conexión) */
function h_insights_account($slug)
{
    list($user, $brand) = insights_agency_brand($slug);
    require_csrf();
    $igId = isset($_POST['ig_id']) ? (string) $_POST['ig_id'] : '';
    $conn = meta_connection();
    $ok = false;
    if ($conn) {
        foreach ($conn['pages'] as $p) {
            $ok = $ok || $p['ig_id'] === $igId;
        }
    }
    if ($igId === '') {
        db_exec('DELETE FROM settings WHERE k = ?', ['ig_' . $brand['id']]);
        flash_set('Cuenta desvinculada.');
    } elseif ($ok) {
        setting_set('ig_' . $brand['id'], $igId);
        flash_set('Cuenta vinculada. Ya puedes pulsar «Actualizar insights».');
    } else {
        flash_set('Esa cuenta no está en la conexión con Meta.', 'err');
    }
    redirect('/b/' . $slug . '/insights');
}

function h_insights_refresh($slug)
{
    list($user, $brand) = insights_agency_brand($slug);
    require_csrf();
    try {
        flash_set(meta_refresh_brand($brand));
    } catch (InvalidArgumentException $e) {
        flash_set($e->getMessage(), 'err');
    } catch (Throwable $e) {
        error_log('[panel] meta: ' . $e->getMessage());
        flash_set('Meta ha respondido con un error: ' . mb_substr($e->getMessage(), 0, 220), 'err');
    }
    redirect('/b/' . $slug . '/insights');
}

/** POST /agencia/sistema/meta  (accion = conectar | desconectar) */
function h_agency_meta()
{
    require_agency();
    require_csrf();
    $action = isset($_POST['accion']) ? $_POST['accion'] : 'conectar';
    if ($action === 'desconectar') {
        db_exec('DELETE FROM settings WHERE k = ?', ['meta_conn']);
        flash_set('Conexión con Meta eliminada.');
    } else {
        try {
            $ids = preg_split('/[\s,;]+/', isset($_POST['page_ids']) ? (string) $_POST['page_ids'] : '', -1, PREG_SPLIT_NO_EMPTY);
            $n = meta_connect(isset($_POST['token']) ? $_POST['token'] : '', isset($_POST['secret']) ? $_POST['secret'] : '', $ids);
            flash_set('Conectado con Meta: ' . $n . ' cuenta(s) de Instagram encontradas.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'err');
        } catch (Throwable $e) {
            error_log('[panel] meta connect: ' . $e->getMessage());
            flash_set('Meta ha respondido con un error: ' . mb_substr($e->getMessage(), 0, 220), 'err');
        }
    }
    redirect('/agencia/sistema');
}

function h_insights_thumb($slug, $id)
{
    list($user, $brand) = insights_agency_brand($slug);
    $row = db_one('SELECT thumb FROM insights_posts WHERE id = ? AND brand_id = ?', [$id, $brand['id']]);
    $path = $row ? insights_thumb_path($row['thumb']) : null;
    if (!$path) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($ext === 'webp' ? 'image/webp' : ($ext === 'png' ? 'image/png' : 'image/jpeg')));
    header('Cache-Control: private, max-age=604800');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
