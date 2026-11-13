<?php
/* Cabecera: logo, navegación, cambio de tema y salida. Usa $user, $nav y $brand si existen. */
$user = isset($user) ? $user : null;
$nav = isset($nav) ? $nav : '';
$isAgency = $user && $user['type'] === 'agency';
$brandSlug = isset($brand) && isset($brand['slug']) ? $brand['slug'] : ($user && $user['type'] === 'brand' ? $user['slug'] : null);
?>
<header class="bar">
  <a class="logo" href="<?= e(url('/')) ?>" aria-label="Dando de Más©, inicio del panel"><span class="logo-mark"></span></a>

  <nav class="nav" aria-label="Principal">
<?php if ($user && $brandSlug): ?>
<?php if ($isAgency): ?>
    <a href="<?= e(url('/agencia')) ?>" class="nav-back">← Agencia</a>
<?php endif; ?>
    <a href="<?= e(url('/b/' . $brandSlug . '/tablero')) ?>"<?= $nav === 'tablero' ? ' class="on" aria-current="page"' : '' ?>>Tablero</a>
    <a href="<?= e(url('/b/' . $brandSlug . '/calendario')) ?>"<?= $nav === 'calendario' ? ' class="on" aria-current="page"' : '' ?>>Calendario</a>
<?php if ($isAgency): ?>
    <a href="<?= e(url('/b/' . $brandSlug . '/insights')) ?>"<?= $nav === 'insights' ? ' class="on" aria-current="page"' : '' ?>>Insights</a>
<?php endif; ?>
<?php elseif ($isAgency): ?>
    <a href="<?= e(url('/agencia')) ?>"<?= $nav === 'marcas' ? ' class="on" aria-current="page"' : '' ?>>Marcas</a>
    <a href="<?= e(url('/agencia/sistema')) ?>"<?= $nav === 'sistema' ? ' class="on" aria-current="page"' : '' ?>>Sistema</a>
<?php endif; ?>
  </nav>

  <div class="meta">
    <button type="button" class="theme" data-theme-toggle aria-label="Cambiar entre tema claro y oscuro">
      <span class="trk"><i></i></span><span class="theme-label" data-theme-label>Claro</span>
    </button>
<?php if ($user): ?>
    <form method="post" action="<?= e(url('/logout')) ?>" class="logout">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="cta cta-ghost">Salir</button>
    </form>
<?php endif; ?>
  </div>
</header>
