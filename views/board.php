<?php
$byStatus = array_fill_keys(STATUS_ORDER, []);
foreach ($items as $it) {
    $byStatus[$it['status']][] = $it;
}
$scripts = ['modal.js', 'board.js'];
?>
<section class="head">
  <div>
    <p class="eyebrow">Tablero de producción</p>
    <h1 class="display"><?= e($brand['name']) ?></h1>
  </div>
  <button type="button" class="cta" data-new-item>Nueva pieza <span class="x" aria-hidden="true"></span></button>
</section>

<div class="board-tabs" id="board-tabs" role="tablist" aria-label="Columnas del tablero">
<?php foreach (STATUS_ORDER as $status): ?>
  <button type="button" role="tab" class="tab" data-tab="<?= e($status) ?>"><?= e(STATUS_LABEL[$status]) ?> <span class="tab-count" data-tab-count><?= count($byStatus[$status]) ?></span></button>
<?php endforeach; ?>
</div>

<div class="board" id="board" data-slug="<?= e($slug) ?>">
<?php foreach (STATUS_ORDER as $i => $status): ?>
  <section class="col" data-status="<?= e($status) ?>" aria-label="<?= e(STATUS_LABEL[$status]) ?>">
    <header class="col-head">
      <h2><span class="col-num"><?= sprintf('%02d', $i + 1) ?></span> <?= e(STATUS_LABEL[$status]) ?> <span class="col-count" data-count><?= count($byStatus[$status]) ?></span></h2>
      <button type="button" class="round" data-new-item="<?= e($status) ?>" aria-label="Añadir en <?= e(STATUS_LABEL[$status]) ?>"><span class="x" aria-hidden="true"></span></button>
    </header>
    <div class="cards" data-dropzone>
<?php foreach ($byStatus[$status] as $it):
    $live = array_values(array_filter($it['photos'], function ($p) {
        return !$p['expired'];
    }));
?>
      <article class="card t-<?= e($it['contentType']) ?>" draggable="true" data-id="<?= e($it['id']) ?>" tabindex="0">
<?php if ($live): ?>
        <div class="cover"><img src="<?= e($live[0]['thumb']) ?>" alt="" loading="lazy" draggable="false"></div>
<?php endif; ?>
        <div class="cmeta"><span class="dot"></span><span><?= e(TYPE_LABEL[$it['contentType']]) ?></span><span class="mute"><?= e($it['platform']) ?></span></div>
        <h3 class="ctitle"><?= e($it['title']) ?></h3>
        <div class="cfoot">
          <span class="when"><?= e(fmt_short_date($it['date'])) ?> · <?= e($it['time']) ?></span>
<?php if ($it['creativeUrl'] !== ''): ?><span class="ico" title="Tiene enlace a la creatividad">↗</span><?php endif; ?>
<?php if (count($live) > 0): ?><span class="ico" title="Fotos"><?= count($live) ?> ▣</span><?php endif; ?>
          <button type="button" class="move-btn" data-move aria-label="Mover a otra columna">Mover ⇄</button>
        </div>
      </article>
<?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
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
