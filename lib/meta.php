<?php
/* Conexión con Meta (API Graph de Instagram) para leer insights. Solo la usa la agencia.
   Se guarda cifrada (con credentials_key) la conexión: las páginas con su cuenta de Instagram y su clave de página.
   Las claves de página obtenidas desde una clave de usuario de larga duración no caducan. */

const META_GRAPH = 'https://graph.facebook.com/v24.0/';
const META_APP_ID = '1155119243907931';

function meta_http($method, $url, $params)
{
    if (strpos($url, 'https://graph.facebook.com/') !== 0) {
        throw new RuntimeException('URL no permitida.');
    }
    $ch = curl_init($method === 'GET' ? $url . '?' . http_build_query($params) : $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['User-Agent: dandodemas-panel'],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('No se pudo conectar con Meta (' . $err . ').');
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respuesta no válida de Meta.');
    }
    if (isset($data['error'])) {
        $m = isset($data['error']['message']) ? $data['error']['message'] : 'error desconocido';
        throw new MetaApiError($m, isset($data['error']['code']) ? (int) $data['error']['code'] : 0);
    }
    return $data;
}

class MetaApiError extends RuntimeException
{
}

function meta_get($path, $params, $token)
{
    $params['access_token'] = $token;
    return meta_http('GET', META_GRAPH . ltrim($path, '/'), $params);
}

/** Conexión guardada: ['pages' => [['id','name','token','ig_id','ig_username'], …], 'saved_at' => …] o null. */
function meta_connection()
{
    $enc = setting_get('meta_conn');
    if (!$enc) {
        return null;
    }
    $plain = decrypt_password($enc);
    $d = $plain === null ? null : json_decode($plain, true);
    return is_array($d) ? $d : null;
}

function meta_connection_save($conn)
{
    setting_set('meta_conn', encrypt_password(json_encode($conn, JSON_UNESCAPED_UNICODE)));
}

/** Convierte una clave de usuario en una de larga duración y guarda las páginas con Instagram. Devuelve cuántas cuentas de Instagram hay. */
function meta_connect($userToken, $appSecret, $extraPageIds = [])
{
    $userToken = trim($userToken);
    $appSecret = trim($appSecret);
    if ($userToken === '' || $appSecret === '') {
        throw new InvalidArgumentException('Rellena la clave de acceso y el secreto de la app.');
    }
    $ll = meta_http('GET', META_GRAPH . 'oauth/access_token', [
        'grant_type' => 'fb_exchange_token', 'client_id' => META_APP_ID, 'client_secret' => $appSecret, 'fb_exchange_token' => $userToken,
    ]);
    if (empty($ll['access_token'])) {
        throw new RuntimeException('Meta no devolvió la clave de larga duración.');
    }
    $acc = meta_get('me/accounts', ['fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 100], $ll['access_token']);
    $pages = [];
    foreach (isset($acc['data']) ? $acc['data'] : [] as $p) {
        if (!empty($p['instagram_business_account']['id']) && !empty($p['access_token'])) {
            $pages[] = [
                'id' => $p['id'], 'name' => $p['name'], 'token' => $p['access_token'],
                'ig_id' => $p['instagram_business_account']['id'],
                'ig_username' => isset($p['instagram_business_account']['username']) ? $p['instagram_business_account']['username'] : '',
            ];
        }
    }
    // Páginas que no salen en «mis páginas» (están en un portfolio): se leen por su número.
    $known = array_column($pages, 'id');
    foreach ($extraPageIds as $pid) {
        $pid = preg_replace('/\D/', '', (string) $pid);
        if ($pid === '' || in_array($pid, $known, true)) {
            continue;
        }
        try {
            $pg = meta_get($pid, ['fields' => 'id,name,access_token,instagram_business_account{id,username}'], $ll['access_token']);
        } catch (MetaApiError $e) {
            continue;
        }
        if (!empty($pg['instagram_business_account']['id'])) {
            $pages[] = [
                'id' => $pg['id'], 'name' => $pg['name'],
                // Si Meta no da clave de página se usa la de usuario (dura unos 60 días).
                'token' => !empty($pg['access_token']) ? $pg['access_token'] : $ll['access_token'],
                'ig_id' => $pg['instagram_business_account']['id'],
                'ig_username' => isset($pg['instagram_business_account']['username']) ? $pg['instagram_business_account']['username'] : '',
            ];
            $known[] = $pid;
        }
    }
    if (!$pages) {
        throw new RuntimeException('Esa clave no da acceso a ninguna cuenta de Instagram profesional vinculada a una página.');
    }
    meta_connection_save(['pages' => $pages, 'saved_at' => now_utc()]);
    return count($pages);
}

function meta_brand_account($brandId)
{
    $igId = setting_get('ig_' . $brandId);
    $conn = meta_connection();
    if (!$igId || !$conn) {
        return null;
    }
    foreach ($conn['pages'] as $p) {
        if ($p['ig_id'] === $igId) {
            return $p;
        }
    }
    return null;
}

function meta_kind($m)
{
    $prod = isset($m['media_product_type']) ? strtoupper($m['media_product_type']) : '';
    $type = isset($m['media_type']) ? strtoupper($m['media_type']) : '';
    if ($prod === 'REELS') {
        return 'reel';
    }
    if ($prod === 'STORY') {
        return 'story';
    }
    return $type === 'CAROUSEL_ALBUM' ? 'carousel' : ($type === 'VIDEO' ? 'video' : 'image');
}

function meta_media_insights($mediaId, $token)
{
    foreach (['reach,saved,shares,views', 'reach,saved,shares', 'reach'] as $metrics) {
        try {
            $r = meta_get($mediaId . '/insights', ['metric' => $metrics], $token);
            $out = [];
            foreach (isset($r['data']) ? $r['data'] : [] as $row) {
                $out[$row['name']] = isset($row['values'][0]['value']) ? (int) $row['values'][0]['value'] : null;
            }
            return $out;
        } catch (MetaApiError $e) {
            continue;
        }
    }
    return [];
}

/** Lee la cuenta y sus últimas publicaciones y las guarda. Devuelve un resumen de texto. */
function meta_refresh_brand($brand)
{
    $acc = meta_brand_account($brand['id']);
    if (!$acc) {
        throw new InvalidArgumentException('Esta marca no tiene una cuenta de Instagram elegida.');
    }
    @set_time_limit(120);
    $t = $acc['token'];
    $ig = $acc['ig_id'];
    $profile = meta_get($ig, ['fields' => 'username,followers_count,follows_count,media_count'], $t);

    $snap = [
        'day' => madrid_today(), 'platform' => 'instagram',
        'followers' => isset($profile['followers_count']) ? $profile['followers_count'] : null,
        'following' => isset($profile['follows_count']) ? $profile['follows_count'] : null,
        'posts' => isset($profile['media_count']) ? $profile['media_count'] : null,
    ];
    $notes = ['@' . (isset($profile['username']) ? $profile['username'] : $acc['ig_username'])];
    try {
        $since = time() - 28 * 86400;
        $ins = meta_get($ig . '/insights', [
            'metric' => 'reach,views,profile_views,total_interactions', 'period' => 'day', 'metric_type' => 'total_value',
            'since' => $since, 'until' => time(),
        ], $t);
        foreach (isset($ins['data']) ? $ins['data'] : [] as $row) {
            $v = isset($row['total_value']['value']) ? (int) $row['total_value']['value'] : null;
            if ($row['name'] === 'reach') {
                $snap['reach'] = $v;
            } elseif ($row['name'] === 'views') {
                $snap['impressions'] = $v;
            } elseif ($row['name'] === 'profile_views') {
                $snap['profileVisits'] = $v;
            } elseif ($row['name'] === 'total_interactions') {
                $snap['interactions'] = $v;
            }
        }
        $notes[] = 'alcance, visualizaciones, visitas e interacciones de los últimos 28 días';
    } catch (MetaApiError $e) {
        $notes[] = 'sin métricas de 28 días (' . mb_substr($e->getMessage(), 0, 80) . ')';
    }
    $snap['notes'] = implode(' · ', $notes);
    insights_snapshot_save($brand['id'], $snap, 'meta');

    $media = meta_get($ig . '/media', ['fields' => 'id,caption,media_type,media_product_type,permalink,timestamp,like_count,comments_count,media_url,thumbnail_url', 'limit' => 30], $t);
    $n = 0;
    $thumbs = 0;
    foreach (isset($media['data']) ? $media['data'] : [] as $m) {
        $ins = meta_media_insights($m['id'], $t);
        $posted = isset($m['timestamp']) ? (new DateTime($m['timestamp']))->setTimezone(new DateTimeZone('Europe/Madrid'))->format('Y-m-d\TH:i:s') : null;
        insights_post_save($brand['id'], [
            'ref' => isset($m['permalink']) ? $m['permalink'] : $m['id'], 'postedAt' => $posted, 'kind' => meta_kind($m),
            'caption' => isset($m['caption']) ? $m['caption'] : null,
            'likes' => isset($m['like_count']) ? $m['like_count'] : null, 'comments' => isset($m['comments_count']) ? $m['comments_count'] : null,
            'views' => isset($ins['views']) ? $ins['views'] : null, 'saves' => isset($ins['saved']) ? $ins['saved'] : null,
            'shares' => isset($ins['shares']) ? $ins['shares'] : null, 'reach' => isset($ins['reach']) ? $ins['reach'] : null,
        ]);
        $n++;
        $ref = isset($m['permalink']) ? $m['permalink'] : $m['id'];
        $row = db_one('SELECT id, thumb FROM insights_posts WHERE brand_id = ? AND post_ref = ?', [$brand['id'], mb_substr($ref, 0, 190)]);
        if ($row && empty($row['thumb']) && $thumbs < 30) {
            $img = !empty($m['thumbnail_url']) ? $m['thumbnail_url'] : (isset($m['media_type']) && $m['media_type'] !== 'VIDEO' && !empty($m['media_url']) ? $m['media_url'] : null);
            $rel = $img ? insights_thumb_store($brand['id'], $row['id'], $img) : null;
            if ($rel) {
                db_exec('UPDATE insights_posts SET thumb = ? WHERE id = ?', [$rel, $row['id']]);
                $thumbs++;
            }
        }
    }
    return 'Actualizado: ' . number_format((int) $snap['followers'], 0, ',', '.') . ' seguidores y ' . $n . ' publicaciones.';
}
