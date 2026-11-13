<?php $scripts = ['agency.js']; ?>
<section class="head">
  <div>
    <a class="back" href="<?= e(url('/agencia')) ?>">← Marcas</a>
    <h1 class="display"><?= $created ? 'Marca <em>creada</em>' : 'Nueva marca' ?></h1>
  </div>
</section>

<?php if ($created): ?>
<div class="card-box narrow">
  <p>Apunta estas credenciales y pásaselas a la marca. Luego podrás volver a verlas desde «Copiar contraseña» o «Editar».</p>
  <dl class="creds">
    <dt>Usuario</dt><dd><?= e($created['slug']) ?></dd>
    <dt>Contraseña</dt><dd><?= e($created['password']) ?></dd>
  </dl>
  <div class="actions">
    <button type="button" class="pill" data-copy-text="<?= e("Usuario: " . $created['slug'] . "\nContraseña: " . $created['password']) ?>">Copiar usuario y contraseña</button>
    <a class="cta" href="<?= e(url('/agencia')) ?>">Continuar <span class="x" aria-hidden="true"></span></a>
  </div>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('/agencia/nueva')) ?>" class="form narrow" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label class="field">
    <span>Nombre de la marca</span>
    <input name="name" required maxlength="100" placeholder="Keik" value="<?= e($old['name']) ?>">
  </label>
  <label class="field">
    <span>Usuario de acceso</span>
    <input name="slug" required maxlength="40" pattern="[a-z0-9-]{2,40}" placeholder="keik" autocapitalize="none" value="<?= e($old['slug']) ?>">
    <small class="mute">Minúsculas, números y guiones. Es lo que se escribe para entrar y aparece en la dirección.</small>
  </label>
  <label class="field">
    <span class="field-line"><span>Contraseña (generada automáticamente)</span><button type="button" class="link" data-generate-for="password">↻ Generar otra</button></span>
    <input name="password" id="password" required minlength="8" class="mono" value="<?= e($old['password']) ?>">
  </label>
<?php if ($error): ?>
  <p class="msg msg-err" role="alert"><?= e($error) ?></p>
<?php endif; ?>
  <button type="submit" class="cta cta-lg">Crear marca <span class="x" aria-hidden="true"></span></button>
</form>
<?php endif; ?>
