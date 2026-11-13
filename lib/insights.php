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

/** Gráfica de línea (SVG sin JavaScript) de una serie [['day' => 'AAAA-MM-DD', 'v' => n], …]. */
function insights_chart_svg($series, $label = 'Evolución')
{
    $n = count($series);
    if ($n < 2) {
        return '';
    }
    $w = 640; $h = 220; $pad = 12;
    $vals = array_column($series, 'v');
    $min = min($vals); $max = max($vals);
    if ($max === $min) { $max = $min + 1; }
    $pts = [];
    foreach ($series as $i => $s) {
        $x = $pad + ($w - 2 * $pad) * $i / ($n - 1);
        $y = $h - $pad - ($h - 2 * $pad) * ($s['v'] - $min) / ($max - $min);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
    $svg .= '<polyline class="chart-line" fill="none" points="' . implode(' ', $pts) . '"/>';
    $last = explode(',', end($pts));
    $svg .= '<circle class="chart-dot" cx="' . $last[0] . '" cy="' . $last[1] . '" r="4"/></svg>';
    return $svg;
}
