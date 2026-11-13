<?php
/* Insights de las cuentas (solo agencia): registros de métricas por día y métricas por publicación.
   Los datos entran a mano o por la API (/api/{marca}/insights), p. ej. desde una conversación de Claude. */

function insights_ensure()
{
    static $done = false;
    if ($done) {
        return;
    }
    db()->exec('CREATE TABLE IF NOT EXISTS insights_snapshots (
        id VARCHAR(32) NOT NULL PRIMARY KEY,
        brand_id VARCHAR(32) NOT NULL,
        platform VARCHAR(20) NOT NULL,
        day VARCHAR(10) NOT NULL,
        followers INT NULL,
        following INT NULL,
        posts INT NULL,
        reach INT NULL,
        impressions INT NULL,
        interactions INT NULL,
        profile_visits INT NULL,
        source VARCHAR(20) NOT NULL,
        notes TEXT NULL,
        created_at VARCHAR(19) NOT NULL,
        UNIQUE (brand_id, platform, day)
    )');
    db()->exec('CREATE TABLE IF NOT EXISTS insights_posts (
        id VARCHAR(32) NOT NULL PRIMARY KEY,
        brand_id VARCHAR(32) NOT NULL,
        platform VARCHAR(20) NOT NULL,
        post_ref VARCHAR(190) NOT NULL,
        item_id VARCHAR(32) NULL,
        posted_at VARCHAR(19) NULL,
        kind VARCHAR(20) NULL,
        caption TEXT NULL,
        likes INT NULL,
        comments INT NULL,
        views INT NULL,
        saves INT NULL,
        shares INT NULL,
        reach INT NULL,
        captured_at VARCHAR(19) NOT NULL,
        UNIQUE (brand_id, post_ref)
    )');
    db()->exec('CREATE TABLE IF NOT EXISTS insights_notes (
        id VARCHAR(32) NOT NULL PRIMARY KEY,
        brand_id VARCHAR(32) NOT NULL,
        source VARCHAR(10) NOT NULL,
        body TEXT NOT NULL,
        sig VARCHAR(40) NULL,
        created_at VARCHAR(19) NOT NULL
    )');
    try {
        db()->exec('ALTER TABLE insights_posts ADD COLUMN thumb VARCHAR(120) NULL');
    } catch (Throwable $e) {
        // ya existe
    }
    $done = true;
}

function insights_int($v)
{
    if ($v === null || $v === '') {
        return null;
    }
    $v = preg_replace('/[^\d\-]/', '', (string) $v);
    return $v === '' || $v === '-' ? null : max(0, (int) $v);
}

function insights_platform($p)
{
    $p = strtolower(trim((string) $p));
    return in_array($p, ['instagram', 'facebook', 'tiktok'], true) ? $p : 'instagram';
}

/** Guarda (o sustituye) el registro de un día. Devuelve el id. */
function insights_snapshot_save($brandId, $d, $source)
{
    insights_ensure();
    $day = isset($d['day']) ? (string) $d['day'] : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        throw new InvalidArgumentException('Fecha no válida (usa AAAA-MM-DD).');
    }
    $platform = insights_platform(isset($d['platform']) ? $d['platform'] : 'instagram');
    $cols = ['followers', 'following', 'posts', 'reach', 'impressions', 'interactions'];
    $vals = [];
    foreach ($cols as $c) {
        $vals[$c] = insights_int(isset($d[$c]) ? $d[$c] : null);
    }
    $vals['profile_visits'] = insights_int(isset($d['profileVisits']) ? $d['profileVisits'] : (isset($d['profile_visits']) ? $d['profile_visits'] : null));
    if (count(array_filter($vals, function ($x) { return $x !== null; })) === 0) {
        throw new InvalidArgumentException('Indica al menos una cifra.');
    }
    $notes = isset($d['notes']) ? mb_substr(trim((string) $d['notes']), 0, 500) : '';
    db_exec('DELETE FROM insights_snapshots WHERE brand_id = ? AND platform = ? AND day = ?', [$brandId, $platform, $day]);
    $id = new_id();
    db_exec(
        'INSERT INTO insights_snapshots (id, brand_id, platform, day, followers, following, posts, reach, impressions, interactions, profile_visits, source, notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $brandId, $platform, $day, $vals['followers'], $vals['following'], $vals['posts'], $vals['reach'], $vals['impressions'], $vals['interactions'], $vals['profile_visits'], $source, $notes, now_utc()]
    );
    return $id;
}

/** Busca la pieza del calendario de ese día; solo la enlaza si es única (o única de ese tipo). */
function insights_match_item($brandId, $postedAt, $kind)
{
    if (!$postedAt) {
        return null;
    }
    $day = substr($postedAt, 0, 10);
    $rows = db_all('SELECT id, content_type FROM content_items WHERE brand_id = ? AND date_time >= ? AND date_time <= ?', [$brandId, $day . ' 00:00:00', $day . ' 23:59:59']);
    if (count($rows) === 1) {
        return $rows[0]['id'];
    }
    $map = ['reel' => 'REEL', 'video' => 'REEL', 'story' => 'STORY', 'carousel' => 'CARRUSEL', 'carrusel' => 'CARRUSEL', 'image' => 'POST', 'post' => 'POST'];
    $want = isset($map[strtolower((string) $kind)]) ? $map[strtolower((string) $kind)] : null;
    if ($want) {
        $same = array_values(array_filter($rows, function ($r) use ($want) { return $r['content_type'] === $want; }));
        if (count($same) === 1) {
            return $same[0]['id'];
        }
    }
    return null;
}

function insights_post_save($brandId, $p)
{
    insights_ensure();
    $ref = isset($p['ref']) ? trim((string) $p['ref']) : (isset($p['url']) ? trim((string) $p['url']) : '');
    if ($ref === '') {
        throw new InvalidArgumentException('Cada publicación necesita «ref» (enlace o código).');
    }
    $ref = mb_substr($ref, 0, 190);
    $platform = insights_platform(isset($p['platform']) ? $p['platform'] : 'instagram');
    $posted = isset($p['postedAt']) ? (string) $p['postedAt'] : '';
    $posted = preg_match('/^\d{4}-\d{2}-\d{2}/', $posted) ? str_replace('T', ' ', substr($posted, 0, 19)) : null;
    $kind = isset($p['kind']) ? mb_substr(strtolower(trim((string) $p['kind'])), 0, 20) : null;
    $fields = [
        insights_int(isset($p['likes']) ? $p['likes'] : null), insights_int(isset($p['comments']) ? $p['comments'] : null),
        insights_int(isset($p['views']) ? $p['views'] : null), insights_int(isset($p['saves']) ? $p['saves'] : null),
        insights_int(isset($p['shares']) ? $p['shares'] : null), insights_int(isset($p['reach']) ? $p['reach'] : null),
    ];
    $caption = isset($p['caption']) ? mb_substr(trim((string) $p['caption']), 0, 400) : null;
    $item = insights_match_item($brandId, $posted, $kind);
    $exists = db_one('SELECT id, item_id FROM insights_posts WHERE brand_id = ? AND post_ref = ?', [$brandId, $ref]);
    if ($exists) {
        db_exec(
            'UPDATE insights_posts SET platform = ?, item_id = ?, posted_at = ?, kind = ?, caption = ?, likes = ?, comments = ?, views = ?, saves = ?, shares = ?, reach = ?, captured_at = ? WHERE id = ?',
            array_merge([$platform, $item ?: $exists['item_id'], $posted, $kind, $caption], $fields, [now_utc(), $exists['id']])
        );
        return 'updated';
    }
    db_exec(
        'INSERT INTO insights_posts (id, brand_id, platform, post_ref, item_id, posted_at, kind, caption, likes, comments, views, saves, shares, reach, captured_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        array_merge([new_id(), $brandId, $platform, $ref, $item, $posted, $kind, $caption], $fields, [now_utc()])
    );
    return 'created';
}

function insights_num($n)
{
    return number_format((int) $n, 0, ',', '.');
}

function insights_short($n)
{
    $n = (int) $n;
    if ($n >= 10000) {
        return rtrim(rtrim(number_format($n / 1000, 1, ',', ''), '0'), ',') . ' mil';
    }
    return insights_num($n);
}

function insights_kind_label($k)
{
    $m = ['reel' => 'Reel', 'video' => 'Vídeo', 'story' => 'Historia', 'carousel' => 'Carrusel', 'carrusel' => 'Carrusel', 'image' => 'Imagen', 'post' => 'Publicación'];
    return isset($m[$k]) ? $m[$k] : ($k ?: 'Publicación');
}

/** Gráfica clara y sin JavaScript. $series = [['day','v', opcional 'kind','title'], …]. $type: 'line' (evolución) o 'bars' (una barra por publicación). */
function insights_chart_html($series, $type, $label)
{
    $n = count($series);
    if ($n < 2) {
        return '';
    }
    $w = 640; $h = 240; $padX = 14; $padT = 14; $padB = 10;
    $vals = array_column($series, 'v');
    $max = max($vals); $min = $type === 'bars' ? 0 : min($vals);
    if ($type === 'line') {
        $span = max(1, $max - $min);
        $min = max(0, $min - $span * 0.15);
        $max = $max + $span * 0.15;
    }
    if ($max <= $min) { $max = $min + 1; }
    $innerH = $h - $padT - $padB;
    $yOf = function ($v) use ($min, $max, $padT, $innerH) {
        return $padT + $innerH - $innerH * ($v - $min) / ($max - $min);
    };
    $e = function ($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); };

    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . $e($label) . '">';
    foreach ([0, 0.5, 1] as $g) {
        $y = round($padT + $innerH * $g, 1);
        $svg .= '<line class="chart-grid" x1="0" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '"/>';
    }
    if ($type === 'bars') {
        $slot = ($w - 2 * $padX) / $n;
        $bw = min(46, max(6, $slot * 0.7));
        foreach ($series as $i => $p) {
            $x = $padX + $slot * $i + ($slot - $bw) / 2;
            $y = $yOf($p['v']);
            $kind = isset($p['kind']) && $p['kind'] ? preg_replace('/[^a-z]/', '', $p['kind']) : 'otro';
            $tip = fmt_short_date($p['day']) . ' · ' . insights_kind_label($p['kind'] ?? '') . ' · ' . insights_num($p['v']);
            $data = ' data-day="' . $e(fmt_short_date($p['day'])) . '" data-time="' . $e($p['time'] ?? '') . '" data-kind="' . $e(insights_kind_label($p['kind'] ?? '')) . '" data-value="' . $e(insights_num($p['v'])) . '" data-title="' . $e($p['title'] ?? '') . '" data-caption="' . $e(mb_substr((string) ($p['caption'] ?? ''), 0, 160)) . '" data-thumb="' . $e($p['thumb'] ?? '') . '" data-link="' . $e($p['link'] ?? '') . '" data-stats="' . $e(json_encode($p['stats'] ?? new stdClass())) . '" tabindex="0"';
            $svg .= '<rect class="chart-bar k-' . $kind . '"' . $data . ' x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round(max(1, $padT + $innerH - $y), 1) . '" rx="3"><title>' . $e($tip) . '</title></rect>';
        }
    } else {
        $pts = [];
        foreach ($series as $i => $p) {
            $pts[] = [round($padX + ($w - 2 * $padX) * $i / ($n - 1), 1), round($yOf($p['v']), 1)];
        }
        $line = implode(' ', array_map(function ($q) { return $q[0] . ',' . $q[1]; }, $pts));
        $base = round($padT + $innerH, 1);
        $svg .= '<polygon class="chart-area" points="' . $pts[0][0] . ',' . $base . ' ' . $line . ' ' . end($pts)[0] . ',' . $base . '"/>';
        $svg .= '<polyline class="chart-line" fill="none" points="' . $line . '"/>';
        $step = max(1, (int) ceil($n / 40));
        foreach ($pts as $i => $q) {
            $isLast = $i === $n - 1;
            if ($isLast || $i % $step === 0) {
                $tip = fmt_short_date($series[$i]['day']) . ' · ' . insights_num($series[$i]['v']);
                $svg .= '<circle class="chart-dot' . ($isLast ? ' last' : '') . '" cx="' . $q[0] . '" cy="' . $q[1] . '" r="' . ($isLast ? 6 : 3.5) . '"><title>' . $e($tip) . '</title></circle>';
            }
        }
    }
    $svg .= '</svg>';

    $yl = '<div class="chart-y" aria-hidden="true"><span>' . $e(insights_short($max)) . '</span><span>' . $e(insights_short(($max + $min) / 2)) . '</span><span>' . $e(insights_short($min)) . '</span></div>';
    $mid = $series[(int) floor(($n - 1) / 2)]['day'];
    $xl = '<div class="chart-x" aria-hidden="true"><span>' . $e(fmt_short_date($series[0]['day'])) . '</span><span>' . $e(fmt_short_date($mid)) . '</span><span>' . $e(fmt_short_date(end($series)['day'])) . '</span></div>';
    return '<div class="chart-wrap">' . $yl . '<div class="chart-plot">' . $svg . $xl . '</div></div>';
}


/** Descarga una imagen de Instagram/Facebook, la reduce y la guarda en storage/insights/{marca}/. Devuelve la ruta relativa o null. */
function insights_thumb_store($brandId, $postId, $url)
{
    if (!function_exists('curl_init') || !function_exists('imagecreatefromstring') || !preg_match('#^https://[^/]*(cdninstagram\.com|fbcdn\.net|facebook\.com)/#i', (string) $url)) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_MAXFILESIZE => 6 * 1024 * 1024]);
    $data = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $code !== 200 || strlen($data) > 6 * 1024 * 1024) {
        return null;
    }
    $img = @imagecreatefromstring($data);
    if (!$img) {
        return null;
    }
    $small = photo_resized($img, 360);
    $enc = photo_encoder();
    $storage = rtrim((string) cfg('storage', PANEL_ROOT . '/storage'), '/');
    $dir = $storage . '/insights/' . $brandId;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return null;
    }
    $rel = $brandId . '/' . $postId . '.' . $enc['ext'];
    if (!photo_write($small, $enc, $storage . '/insights/' . $rel)) {
        return null;
    }
    return $rel;
}

function insights_thumb_path($rel)
{
    $storage = rtrim((string) cfg('storage', PANEL_ROOT . '/storage'), '/');
    $p = $storage . '/insights/' . $rel;
    return (preg_match('#^[a-z0-9]+/[a-z0-9]+\.(webp|jpg|png)$#', (string) $rel) && is_file($p)) ? $p : null;
}


function insights_weekday_es($ts)
{
    $d = ['domingos', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábados'];
    return $d[(int) date('w', $ts)];
}

/** «el reel», «el carrusel», «la imagen», «la publicación»… */
function insights_kind_with_article($k)
{
    $label = mb_strtolower(insights_kind_label($k));
    return (in_array($label, ['imagen', 'publicación', 'historia'], true) ? 'la ' : 'el ') . $label;
}

function insights_pct_es($x)
{
    return number_format(abs($x), 0, ',', '.');
}

/** Frases sobre lo notable que hay ahora mismo en los datos de la marca. Devuelve [] si no hay nada que destacar. */
function insights_auto_bullets($brandId)
{
    insights_ensure();
    $bullets = [];
    $snaps = db_all('SELECT day, followers FROM insights_snapshots WHERE brand_id = ? AND followers IS NOT NULL ORDER BY day ASC', [$brandId]);
    if (count($snaps) >= 2) {
        $last = end($snaps);
        $target = strtotime($last['day'] . ' -28 days');
        $ref = null;
        foreach ($snaps as $sn) {
            if (strtotime($sn['day']) <= $target) {
                $ref = $sn;
            }
        }
        if (!$ref) {
            $ref = $snaps[0];
        }
        $days = max(1, (int) round((strtotime($last['day']) - strtotime($ref['day'])) / 86400));
        $diff = (int) $last['followers'] - (int) $ref['followers'];
        $pct = $ref['followers'] > 0 ? $diff / $ref['followers'] * 100 : 0;
        if ($days >= 3 && abs($pct) >= 1) {
            $bullets[] = ($diff >= 0 ? 'Seguidores al alza: ' : 'Seguidores a la baja: ') . ($diff >= 0 ? '+' : '−') . insights_num(abs($diff)) . ' (' . ($pct >= 0 ? '+' : '−') . insights_pct_es($pct) . ' %) en ' . $days . ' días, de ' . insights_num($ref['followers']) . ' a ' . insights_num($last['followers']) . '.';
        }
    }

    $rows = db_all('SELECT posted_at, kind, caption, likes, comments, saves, shares FROM insights_posts WHERE brand_id = ? AND posted_at IS NOT NULL ORDER BY posted_at DESC', [$brandId]);
    $posts = [];
    foreach ($rows as $r) {
        if (($r['kind'] ?: '') === 'story') {
            continue;
        }
        $r['inter'] = (int) $r['likes'] + (int) $r['comments'] + (int) $r['saves'] + (int) $r['shares'];
        $r['ts'] = strtotime($r['posted_at'] . ' UTC');
        $posts[] = $r;
    }
    $n = count($posts);
    if ($n >= 6) {
        $avg = array_sum(array_column($posts, 'inter')) / $n;
        $recent = array_slice($posts, 0, 30);
        $best = null;
        foreach ($recent as $p) {
            if (!$best || $p['inter'] > $best['inter']) {
                $best = $p;
            }
        }
        if ($best && $avg > 0 && $best['inter'] >= 1.8 * $avg && time() - $best['ts'] < 21 * 86400) {
            $bullets[] = 'Destaca ' . insights_kind_with_article($best['kind']) . ' del ' . fmt_short_date(substr($best['posted_at'], 0, 10)) . ': ' . insights_num($best['inter']) . ' interacciones, ' . number_format($best['inter'] / $avg, 1, ',', '.') . ' veces la media (' . insights_num(round($avg)) . ').';
        }
    }
    if ($n >= 10) {
        $a = array_slice($posts, 0, 5);
        $b = array_slice($posts, 5, 5);
        $ma = array_sum(array_column($a, 'inter')) / 5;
        $mb = array_sum(array_column($b, 'inter')) / 5;
        if ($mb > 0 && abs($ma - $mb) / $mb >= 0.25) {
            $ch = ($ma - $mb) / $mb * 100;
            $bullets[] = 'Las últimas 5 publicaciones rinden un ' . insights_pct_es($ch) . ' % ' . ($ch >= 0 ? 'más' : 'menos') . ' que las 5 anteriores (' . insights_num(round($ma)) . ' frente a ' . insights_num(round($mb)) . ' interacciones de media).';
        }
    }
    $kinds = [];
    foreach ($posts as $p) {
        $k = $p['kind'] ?: 'otro';
        $kinds[$k][] = $p['inter'];
    }
    $kinds = array_filter($kinds, function ($v) { return count($v) >= 2; });
    if (count($kinds) >= 2) {
        $means = array_map(function ($v) { return array_sum($v) / count($v); }, $kinds);
        arsort($means);
        $names = array_keys($means);
        $v = array_values($means);
        if ($v[1] > 0 && $v[0] / $v[1] >= 1.3) {
            $bullets[] = 'Tu mejor formato es ' . mb_strtolower(insights_kind_label($names[0])) . ': ' . insights_num(round($v[0])) . ' interacciones de media, un ' . insights_pct_es(($v[0] / $v[1] - 1) * 100) . ' % más que ' . insights_kind_with_article($names[1]) . '.';
        }
    }
    if ($n >= 12) {
        $by = [];
        foreach ($posts as $p) {
            $by[(int) date('w', $p['ts'])][] = $p['inter'];
        }
        $by = array_filter($by, function ($v) { return count($v) >= 2; });
        $overall = array_sum(array_column($posts, 'inter')) / $n;
        if ($by && $overall > 0) {
            $m = array_map(function ($v) { return array_sum($v) / count($v); }, $by);
            arsort($m);
            $wd = array_key_first($m);
            if ($m[$wd] >= 1.3 * $overall) {
                $bullets[] = 'Las publicaciones de los ' . insights_weekday_es(strtotime('Sunday +' . $wd . ' days')) . ' funcionan mejor: ' . insights_num(round($m[$wd])) . ' interacciones de media frente a ' . insights_num(round($overall)) . ' en total.';
            }
        }
    }
    return $bullets;
}

/** Crea una nota automática nueva si hay algo notable y es distinto de la última. Devuelve true si se creó. */
function insights_update_analysis($brandId)
{
    $bullets = insights_auto_bullets($brandId);
    if (!$bullets) {
        return false;
    }
    $body = implode("\n", $bullets);
    $sig = md5(preg_replace('/[\d.,]+/', '#', $body) . count($bullets) . substr(now_utc(), 0, 10));
    $last = db_one('SELECT sig, body FROM insights_notes WHERE brand_id = ? AND source = ? ORDER BY created_at DESC LIMIT 1', [$brandId, 'auto']);
    if ($last && $last['body'] === $body) {
        return false;
    }
    db_exec('INSERT INTO insights_notes (id, brand_id, source, body, sig, created_at) VALUES (?, ?, ?, ?, ?, ?)', [new_id(), $brandId, 'auto', $body, $sig, now_utc()]);
    return true;
}
