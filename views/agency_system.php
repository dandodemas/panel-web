<?php $scripts = ['agency.js']; ?>
<section class="head">
  <div>
    <p class="eyebrow">Agencia</p>
    <h1 class="display">Sistema</h1>
  </div>
  <div class="head-actions">
    <form method="post" action="<?= e(url('/agencia/sistema/actualizar')) ?>">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="cta"<?= in_array(false, $updateReq, true) ? ' disabled' : '' ?>>Actualizar el panel <span class="x" aria-hidden="true"></span></button>
    </form>
    <form method="post" action="<?= e(url('/agencia/sistema/limpiar')) ?>">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="cta cta-ghost">Ejecutar limpieza ahora <span class="x" aria-hidden="true"></span></button>
    </form>
  </div>
</section>

<?php if (!empty($flash) && isset($flash['t']) && $flash['t'] === 'token'): ?>
<div class="msg msg-ok token-msg" role="status">
  <p>Clave nueva. Cópiala ahora: no se vuelve a mostrar.</p>
  <div class="token-row">
    <code class="mono token-val"><?= e($flash['m']) ?></code>
    <button type="button" class="cta" data-copy-text="<?= e($flash['m']) ?>">Copiar clave</button>
  </div>
</div>
<?php elseif (!empty($flash)): ?>
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

<?php if (in_array(false, $updateReq, true)): ?>
<p class="msg msg-err">Este servidor no permite actualizar desde aquí: <?php foreach ($updateReq as $label => $ok) { if (!$ok) { echo e($label) . '. '; } } ?></p>
<?php endif; ?>

<div class="cols-2 tools">
<div class="card-box update-box">
  <h2 class="box-title">Subida automática de fotos</h2>
  <p class="mute small">Permite que una conversación de Claude suba fotos a la pieza de cada día sin que tú hagas nada. Genera una clave, guárdala en el archivo <span class="mono">~/.dandodemas-panel-token</span> de tu Mac y listo. Solo se guarda su huella: si la pierdes, genera otra.</p>
  <dl class="kv"><dt>Estado</dt><dd><?= $apiOn ? 'activada' : 'desactivada' ?></dd></dl>
  <form method="post" action="<?= e(url('/agencia/sistema/clave-api')) ?>" class="actions">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button type="submit" name="accion" value="generar" class="cta"><?= $apiOn ? 'Generar clave nueva' : 'Activar y generar clave' ?> <span class="x" aria-hidden="true"></span></button>
<?php if ($apiOn): ?>
    <button type="submit" name="accion" value="desactivar" class="link link-danger">Desactivar</button>
<?php endif; ?>
  </form>
</div>

<div class="card-box update-box meta-box" id="meta">
  <h2 class="box-title">Conexión con Meta (insights)</h2>
  <p class="mute small">Permite leer los insights reales de Instagram (alcance, visualizaciones, guardados…) y rellenar la pestaña Insights de cada marca con un botón.</p>
<?php if (!empty($metaConn)): ?>
  <dl class="kv"><dt>Estado</dt><dd>conectado</dd><dt>Cuentas</dt><dd><?php foreach ($metaConn['pages'] as $pg): ?>@<?= e($pg['ig_username']) ?> <?php endforeach; ?></dd></dl>
<?php endif; ?>
  <form method="post" action="<?= e(url('/agencia/sistema/meta')) ?>" class="form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label class="field"><span>Clave de acceso de Meta (la que genera el Explorador de la API Graph)</span><input type="password" name="token" autocomplete="off" required></label>
    <label class="field"><span>Secreto de la app (Configuración → Información básica)</span><input type="password" name="secret" autocomplete="off" required></label>
    <label class="field"><span>Números de página de las marcas (uno por línea). Ya puestos: A'Napule y LaBuena</span><textarea name="page_ids" rows="3"><?= e(isset($metaPageIds) ? $metaPageIds : "220867863290002\n1391488477385569") ?></textarea></label>
    <div class="actions">
      <button type="submit" name="accion" value="conectar" class="cta"><?= !empty($metaConn) ? 'Volver a conectar' : 'Conectar' ?> <span class="x" aria-hidden="true"></span></button>
<?php if (!empty($metaConn)): ?>
      <button type="submit" name="accion" value="desconectar" class="link link-danger" formnovalidate>Desconectar</button>
<?php endif; ?>
    </div>
  </form>
</div>
</div>
