<?php
$kindLabel = ['reel' => 'Reel', 'video' => 'Vídeo', 'story' => 'Historia', 'carousel' => 'Carrusel', 'carrusel' => 'Carrusel', 'image' => 'Imagen', 'post' => 'Publicación', 'otro' => 'Otro'];
$fmt = function ($n) { return $n === null ? '—' : number_format((int) $n, 0, ',', '.'); };
?>
<section class="head">
  <div>
    <p class="eyebrow">Solo agencia · no lo ve el cliente</p>
    <h1 class="display"><?= e($brand['name']) ?> <em>insights</em></h1>
  </div>
  <div class="head-actions">
<?php if ($metaAcc): ?>
    <form method="post" action="<?= e(url('/b/' . $slug . '/insights/actualizar')) ?>">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="cta">Actualizar insights <span class="x" aria-hidden="true"></span></button>
    </form>
<?php elseif (!$metaConn): ?>
    <a class="cta cta-ghost" href="<?= e(url('/agencia/sistema')) ?>">Conectar con Meta</a>
<?php endif; ?>
  </div>
</section>

<?php if ($metaConn && !$metaAcc): ?>
<form method="post" action="<?= e(url('/b/' . $slug . '/insights/cuenta')) ?>" class="card-box acct-box">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label class="field"><span>Cuenta de Instagram de esta marca</span>
    <select name="ig_id" required>
      <option value="">Elige una cuenta…</option>
<?php foreach ($metaConn['pages'] as $pg): ?>
      <option value="<?= e($pg['ig_id']) ?>">@<?= e($pg['ig_username']) ?> · <?= e($pg['name']) ?></option>
<?php endforeach; ?>
    </select>
  </label>
  <div class="actions"><button type="submit" class="cta">Vincular cuenta <span class="x" aria-hidden="true"></span></button></div>
</form>
<?php endif; ?>

<?php if ($metaAcc): ?>
<p class="mute small acct-line">Conectado a @<?= e($metaAcc['ig_username']) ?> · <form method="post" action="<?= e(url('/b/' . $slug . '/insights/cuenta')) ?>" class="inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="ig_id" value=""><button type="submit" class="link">cambiar cuenta</button></form></p>
<?php endif; ?>

<?php if (!empty($flash)): ?>
<p class="msg <?= (isset($flash['t']) && $flash['t'] === 'err') ? 'msg-err' : 'msg-ok' ?>" role="status"><?= e($flash['m']) ?></p>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span class="stat-k">Seguidores</span><strong class="stat-v"><?= $latest ? e($fmt($latest['v'])) : '—' ?></strong>
    <span class="stat-s"><?= $delta ? e(($delta['n'] >= 0 ? '+' : '') . $fmt($delta['n']) . ' desde el ' . fmt_short_date($delta['since'])) : ($latest ? 'último: ' . e(fmt_short_date($latest['day'])) : 'sin registros') ?></span></div>
  <div class="stat"><span class="stat-k">Publicaciones medidas</span><strong class="stat-v"><?= e($fmt($postCount)) ?></strong><span class="stat-s">con likes y comentarios</span></div>
  <div class="stat"><span class="stat-k">Interacciones por publicación</span><strong class="stat-v"><?= $avgInter !== null ? e(number_format($avgInter, 1, ',', '.')) : '—' ?></strong><span class="stat-s">likes + comentarios + guardados + compartidos</span></div>
  <div class="stat"><span class="stat-k">Mejor publicación</span><strong class="stat-v"><?= $best ? e($fmt($best['inter'])) : '—' ?></strong>
    <span class="stat-s"><?= $best ? e(($best['post']['item_title'] ?: ($best['post']['caption'] ? mb_substr($best['post']['caption'], 0, 40) : 'sin título'))) : '' ?></span></div>
</div>

<?php
$chartCards = [];
if (count($series) > 1) {
    $chartCards[] = ['title' => 'Seguidores', 'series' => $series, 'fmt' => true];
}
foreach ($charts as $c) {
    $chartCards[] = ['title' => $c['title'], 'series' => $c['series']];
}
if (count($postSeries) > 1) {
    $chartCards[] = ['title' => 'Interacciones por publicación (cada punto, una publicación)', 'series' => $postSeries];
}
?>
<?php if ($chartCards): ?>
<div class="charts">
<?php foreach ($chartCards as $cc):
    $vals = array_column($cc['series'], 'v');
    $first = $vals[0];
    $lastv = end($vals);
    $diff = $lastv - $first;
    $pct = $first > 0 ? round($diff / $first * 100) : null;
?>
  <div class="card-box chart-box">
    <h2 class="box-title"><?= e($cc['title']) ?></h2>
    <p class="chart-trend <?= $diff >= 0 ? 'up' : 'down' ?>"><?= $diff >= 0 ? '▲' : '▼' ?> <?= e(($diff >= 0 ? '+' : '') . $fmt($diff)) ?><?= $pct !== null ? ' (' . ($pct >= 0 ? '+' : '') . $pct . ' %)' : '' ?></p>
    <?= insights_chart_svg($cc['series'], $cc['title']) ?>
    <p class="mute small"><?= e(fmt_short_date($cc['series'][0]['day'])) ?> → <?= e(fmt_short_date(end($cc['series'])['day'])) ?> · de <?= e($fmt($first)) ?> a <?= e($fmt($lastv)) ?></p>
  </div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="mute small">Las gráficas aparecen cuando hay al menos dos registros o dos publicaciones medidas. Cada vez que pulses «Actualizar insights» se añade un punto nuevo.</p>
<?php endif; ?>

<div class="cols-2 tools">
  <div class="card-box">
    <h2 class="box-title">Qué formato funciona mejor</h2>
<?php if ($kinds): ?>
    <table class="tbl">
      <thead><tr><th>Formato</th><th>Piezas</th><th>Interacciones / pieza</th><th>Vistas / pieza</th></tr></thead>
      <tbody>
<?php foreach ($kinds as $k => $v): ?>
        <tr><td><?= e(isset($kindLabel[$k]) ? $kindLabel[$k] : $k) ?></td><td><?= (int) $v['n'] ?></td><td><?= e(number_format($v['inter'] / max(1, $v['n']), 1, ',', '.')) ?></td><td><?= $v['viewsN'] ? e($fmt($v['views'] / $v['viewsN'])) : '—' ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php else: ?>
    <p class="mute">Aún no hay publicaciones medidas. Pulsa «Actualizar insights» o súbelas desde la conversación de la marca.</p>
<?php endif; ?>
  </div>

  <div class="card-box" id="config">
    <h2 class="box-title">Añadir registro a mano</h2>
    <p class="mute small">Cifras del día, por ejemplo copiadas de Meta Business Suite. También se pueden cargar desde una conversación de Claude con la clave de subida automática.</p>
    <form method="post" action="<?= e(url('/b/' . $slug . '/insights/registro')) ?>" class="form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="grid-2">
        <label class="field"><span>Día</span><input type="date" name="day" value="<?= e($today) ?>" required></label>
        <label class="field"><span>Red</span><select name="platform"><option value="instagram">Instagram</option><option value="facebook">Facebook</option><option value="tiktok">TikTok</option></select></label>
        <label class="field"><span>Seguidores</span><input type="number" name="followers" min="0" inputmode="numeric"></label>
        <label class="field"><span>Alcance</span><input type="number" name="reach" min="0" inputmode="numeric"></label>
        <label class="field"><span>Impresiones</span><input type="number" name="impressions" min="0" inputmode="numeric"></label>
        <label class="field"><span>Interacciones</span><input type="number" name="interactions" min="0" inputmode="numeric"></label>
        <label class="field"><span>Visitas al perfil</span><input type="number" name="profileVisits" min="0" inputmode="numeric"></label>
        <label class="field"><span>Publicaciones</span><input type="number" name="posts" min="0" inputmode="numeric"></label>
      </div>
      <label class="field"><span>Nota (opcional)</span><input type="text" name="notes" maxlength="500"></label>
      <div class="actions"><button type="submit" class="cta">Guardar registro <span class="x" aria-hidden="true"></span></button></div>
    </form>
  </div>
</div>

<?php if ($posts): ?>
<h2 class="box-title section-title">Publicaciones</h2>
<div class="tbl-wrap">
<table class="tbl">
  <thead><tr><th>Fecha</th><th>Formato</th><th>Pieza del calendario</th><th>♥</th><th>💬</th><th>Vistas</th><th>Guardados</th><th></th></tr></thead>
  <tbody>
<?php foreach ($posts as $p): ?>
    <tr>
      <td><?= $p['posted_at'] ? e(fmt_short_date(substr($p['posted_at'], 0, 10))) : '—' ?></td>
      <td><?= e(isset($kindLabel[$p['kind']]) ? $kindLabel[$p['kind']] : ($p['kind'] ?: '—')) ?></td>
      <td><?= e($p['item_title'] ?: ($p['caption'] ? mb_substr($p['caption'], 0, 50) : '—')) ?></td>
      <td><?= e($fmt($p['likes'])) ?></td><td><?= e($fmt($p['comments'])) ?></td><td><?= e($fmt($p['views'])) ?></td><td><?= e($fmt($p['saves'])) ?></td>
      <td><?php if (preg_match('#^https://#', $p['post_ref'])): ?><a class="link" href="<?= e($p['post_ref']) ?>" target="_blank" rel="noopener">Ver</a><?php endif; ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($snaps): ?>
<h2 class="box-title section-title">Registros</h2>
<div class="tbl-wrap">
<table class="tbl">
  <thead><tr><th>Día</th><th>Red</th><th>Seguidores</th><th>Alcance</th><th>Impresiones</th><th>Interacciones</th><th>Origen</th><th></th></tr></thead>
  <tbody>
<?php foreach (array_slice($snaps, 0, 40) as $s): ?>
    <tr>
      <td><?= e(fmt_short_date($s['day'])) ?></td><td><?= e($s['platform']) ?></td><td><?= e($fmt($s['followers'])) ?></td><td><?= e($fmt($s['reach'])) ?></td><td><?= e($fmt($s['impressions'])) ?></td><td><?= e($fmt($s['interactions'])) ?></td><td><?= e($s['source']) ?></td>
      <td><form method="post" action="<?= e(url('/b/' . $slug . '/insights/' . $s['id'] . '/borrar')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button type="submit" class="link link-danger">Borrar</button></form></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
