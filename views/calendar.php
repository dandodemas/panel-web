<?php
$scripts = ['modal.js', 'calendar.js'];
$weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
?>
<section class="head">
  <div>
    <p class="eyebrow"><?= e($brand['name']) ?> · Calendario</p>
    <h1 class="display"><?= e($monthLabel) ?></h1>
  </div>
  <div class="row-actions">
    <a class="round" href="<?= e($prevHref) ?>" aria-label="Mes anterior">←</a>
    <a class="pill" href="<?= e($todayHref) ?>">Hoy</a>
    <a class="round" href="<?= e($nextHref) ?>" aria-label="Mes siguiente">→</a>
    <button type="button" class="cta" data-new-item>Nueva pieza <span class="x" aria-hidden="true"></span></button>
  </div>
</section>

<div class="cal-wrap">
  <div class="cal" id="calendar" data-slug="<?= e($slug) ?>">
<?php foreach ($weekdays as $w): ?>
    <div class="wd"><?= e($w) ?></div>
<?php endforeach; ?>
<?php foreach ($days as $d): ?>
    <div class="day<?= $d['inMonth'] ? '' : ' out' ?><?= $d['today'] ? ' today' : '' ?>" data-date="<?= e($d['key']) ?>" data-dropzone>
      <div class="day-head">
        <span class="num"><?= (int) $d['num'] ?></span>
        <button type="button" class="round round-s" data-new-item data-date="<?= e($d['key']) ?>" aria-label="Añadir el <?= e(fmt_short_date($d['key'])) ?>"><span class="x" aria-hidden="true"></span></button>
      </div>
<?php foreach ($d['items'] as $it):
    $live = count(array_filter($it['photos'], function ($p) {
        return !$p['expired'];
    }));
?>
      <div class="chip t-<?= e($it['contentType']) ?>" role="button" tabindex="0" draggable="true" data-id="<?= e($it['id']) ?>">
        <span class="chip-meta"><?= e($it['time']) ?> · <?= e($it['platform']) ?> · <?= e(TYPE_LABEL[$it['contentType']]) ?></span>
        <span class="chip-title"><?= e($it['title']) ?><?php if ($it['creativeUrl'] !== '' || $live): ?> <span class="badges"><?php if ($it['creativeUrl'] !== ''): ?><span title="Tiene enlace" aria-label="Tiene enlace">🔗</span><?php endif; ?><?php if ($live): ?><span title="Tiene fotos" aria-label="Tiene fotos">📷</span><?php endif; ?></span><?php endif; ?></span>
      </div>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
  </div>
</div>

<?php
$wdShort = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$hasAny = false;
?>
<div class="agenda" id="agenda">
<?php foreach ($days as $d):
    if (!$d['inMonth'] || !$d['items']) {
        continue;
    }
    $hasAny = true;
    $dow = (int) (new DateTimeImmutable($d['key'], new DateTimeZone('UTC')))->format('N');
?>
  <section class="ag-day<?= $d['today'] ? ' today' : '' ?>" data-date="<?= e($d['key']) ?>">
    <h3 class="ag-head">
      <span class="ag-num"><?= (int) $d['num'] ?></span>
      <span class="ag-wd"><?= e($wdShort[$dow]) ?></span>
<?php if ($d['today']): ?><span class="ag-today">Hoy</span><?php endif; ?>
      <button type="button" class="round" data-new-item data-date="<?= e($d['key']) ?>" aria-label="Añadir el <?= e(fmt_short_date($d['key'])) ?>"><span class="x" aria-hidden="true"></span></button>
    </h3>
<?php foreach ($d['items'] as $it):
    $livePh = array_values(array_filter($it['photos'], function ($p) {
        return !$p['expired'];
    }));
?>
    <div class="ag-item t-<?= e($it['contentType']) ?>" role="button" tabindex="0" data-id="<?= e($it['id']) ?>">
      <div class="ag-text">
        <span class="chip-meta"><?= e($it['time']) ?> · <?= e(TYPE_LABEL[$it['contentType']]) ?> · <?= e($it['platform']) ?></span>
        <span class="ag-title"><?= e($it['title']) ?><?php if ($it['creativeUrl'] !== '' || $livePh): ?> <span class="badges"><?php if ($it['creativeUrl'] !== ''): ?><span title="Tiene enlace" aria-label="Tiene enlace">🔗</span><?php endif; ?><?php if ($livePh): ?><span title="Tiene fotos" aria-label="Tiene fotos">📷</span><?php endif; ?></span><?php endif; ?></span>
      </div>
<?php if ($livePh): ?>
      <img class="ag-thumb" src="<?= e($livePh[0]['thumb']) ?>" alt="" loading="lazy">
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </section>
<?php endforeach; ?>
<?php if (!$hasAny): ?>
  <p class="empty">No hay piezas este mes. Pulsa «+» para añadir la primera.</p>
<?php endif; ?>
</div>

<button type="button" class="fab" data-new-item aria-label="Nueva pieza"><span class="x" aria-hidden="true"></span></button>

<?php include PANEL_ROOT . '/views/modal.php'; ?>
<script type="application/json" id="boot"><?= boot_json([
    'slug' => $slug,
    'items' => $items,
    'statusLabel' => STATUS_LABEL,
    'statusOrder' => STATUS_ORDER,
    'photoDays' => photo_days(),
    'today' => madrid_today(),
]) ?></script>
