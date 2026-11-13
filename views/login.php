<section class="login">
  <div class="login-in">
    <p class="eyebrow">Dando de Más©</p>
    <h1 class="display">Panel de <em>contenido</em></h1>
    <p class="lead">Calendario editorial y tablero de producción de cada marca.</p>

    <form method="post" action="<?= e(url('/login')) ?>" class="form" autocomplete="on">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label class="field">
        <span>Usuario</span>
        <input name="usuario" type="text" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
      </label>
      <label class="field">
        <span>Contraseña</span>
        <input name="password" type="password" autocomplete="current-password" required>
      </label>
<?php if (!empty($error)): ?>
      <p class="msg msg-err" role="alert"><?= e($error) ?></p>
<?php endif; ?>
      <button type="submit" class="cta cta-lg">Entrar <span class="x" aria-hidden="true"></span></button>
    </form>
  </div>
</section>
