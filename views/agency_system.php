<section class="head">
  <div>
    <p class="eyebrow">Agencia</p>
    <h1 class="display">Sistema</h1>
  </div>
  <form method="post" action="<?= e(url('/agencia/sistema/limpiar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button type="submit" class="cta">Ejecutar limpieza ahora <span class="x" aria-hidden="true"></span></button>
  </form>
</section>

<?php if (!empty($flash)): ?>
<p class="msg <?= (isset($flash['t']) && $flash['t'] === 'err') ? 'msg-err' : 'msg-ok' ?>" role="status"><?= e($flash['m']) ?></p>
<?php endif; ?>

<div class="cols-2">
  <div class="card-box">
    <h2 class="box-title">Estado</h2>
    <dl class="kv">
<?php foreach ($info as $k => $v): ?>
      <dt><?= e($k) ?></dt><dd><?= e($v) ?></dd>
<?php endforeach; ?>
    </dl>
  </div>
  <div class="card-box">
    <h2 class="box-title">Imágenes en este servidor</h2>
    <dl class="kv">
<?php
$labels = ['gd' => 'Librería GD', 'jpeg' => 'Leer JPG', 'png' => 'Leer PNG', 'webp_read' => 'Leer WebP', 'webp_write' => 'Guardar WebP (fotos más ligeras)', 'exif' => 'Girar fotos de móvil (EXIF)', 'finfo' => 'Comprobar tipo de archivo'];
foreach ($labels as $k => $label): ?>
      <dt><?= e($label) ?></dt><dd><?= !empty($caps[$k]) ? 'sí' : 'no' ?></dd>
<?php endforeach; ?>
    </dl>
    <p class="mute small">Las fotos caducan solas a los <?= (int) photo_days() ?> días. Además de la limpieza ocasional automática, puedes programar este comando a diario en hPanel → Avanzado → Cron jobs:</p>
    <p class="code"><?= e('php ' . PANEL_ROOT . '/cron/limpiar.php') ?></p>
  </div>
</div>

<div class="card-box update-box">
  <h2 class="box-title">Actualizar el panel</h2>
  <p class="mute small">Descarga la última versión del código desde GitHub y la instala. No toca tus datos, fotos ni la configuración, y guarda antes una copia del código actual.</p>
  <dl class="kv">
<?php foreach ($updateReq as $label => $ok): ?>
    <dt><?= e($label) ?></dt><dd><?= $ok ? 'sí' : 'no' ?></dd>
<?php endforeach; ?>
  </dl>
  <form method="post" action="<?= e(url('/agencia/sistema/actualizar')) ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button type="submit" class="cta"<?= in_array(false, $updateReq, true) ? ' disabled' : '' ?>>Buscar e instalar actualización <span class="x" aria-hidden="true"></span></button>
  </form>
</div>
