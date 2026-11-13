<?php $scripts = ['agency.js']; ?>
<section class="head">
  <div>
    <a class="back" href="<?= e(url('/agencia')) ?>">← Marcas</a>
    <h1 class="display"><?= e($brand['name']) ?></h1>
  </div>
  <div class="row-actions">
    <a class="pill" href="<?= e(url('/b/' . $brand['slug'] . '/tablero')) ?>">Ver tablero</a>
    <a class="pill" href="<?= e(url('/b/' . $brand['slug'] . '/calendario')) ?>">Ver calendario</a>
  </div>
</section>

<form method="post" action="<?= e(url('/agencia/' . $brand['id'])) ?>" class="form narrow" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label class="field">
    <span>Nombre de la marca</span>
    <input name="name" required maxlength="100" value="<?= e($brand['name']) ?>">
  </label>
  <label class="field">
    <span>Usuario de acceso</span>
    <input name="slug" required maxlength="40" pattern="[a-z0-9-]{2,40}" autocapitalize="none" value="<?= e($brand['slug']) ?>">
    <small class="mute">Si lo cambias, avisa a la marca: su usuario y su dirección cambian.</small>
  </label>

  <div class="field">
    <span>Contraseña actual</span>
    <div class="reveal">
      <output id="current-pass" class="mono mute">••••••••••</output>
      <button type="button" class="pill" data-reveal-pass="<?= e($brand['id']) ?>">Mostrar</button>
      <button type="button" class="pill" data-copy-pass="<?= e($brand['id']) ?>">Copiar</button>
    </div>
  </div>

  <label class="field">
    <span class="field-line"><span>Nueva contraseña (opcional)</span><button type="button" class="link" data-generate-for="password">↻ Generar</button></span>
    <input name="password" id="password" minlength="8" class="mono" placeholder="Dejar en blanco para no cambiarla" data-suggest="<?= e($newPassword) ?>">
    <small class="mute">Si la cambias, la anterior deja de funcionar. Se guarda cifrada y desde aquí siempre se puede consultar.</small>
  </label>

<?php if ($error): ?>
  <p class="msg msg-err" role="alert"><?= e($error) ?></p>
<?php endif; ?>
  <button type="submit" class="cta cta-lg">Guardar cambios <span class="x" aria-hidden="true"></span></button>
</form>

<form method="post" action="<?= e(url('/agencia/' . $brand['id'] . '/borrar')) ?>" class="danger narrow" data-confirm-delete="<?= e($brand['name']) ?>">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <button type="submit" class="link link-danger">Borrar marca y todo su contenido</button>
</form>
