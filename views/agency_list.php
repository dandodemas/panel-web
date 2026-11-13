<?php $scripts = ['agency.js']; ?>
<section class="head">
  <div>
    <p class="eyebrow">Agencia</p>
    <h1 class="display">Marcas</h1>
  </div>
  <a class="cta" href="<?= e(url('/agencia/nueva')) ?>">Nueva marca <span class="x" aria-hidden="true"></span></a>
</section>

<?php if (!empty($flash)): ?>
<p class="msg msg-ok" role="status"><?= e($flash['m']) ?></p>
<?php endif; ?>

<p class="mute count"><?= count($brands) ?> <?= count($brands) === 1 ? 'marca dada de alta' : 'marcas dadas de alta' ?></p>

<div class="rows" id="brand-rows">
<?php foreach ($brands as $b): ?>
  <article class="row">
    <div class="row-main">
      <h2 class="row-title"><?= e($b['name']) ?></h2>
      <p class="mute small"><?= e($b['slug']) ?> · <?= (int) $b['n'] ?> <?= (int) $b['n'] === 1 ? 'pieza' : 'piezas' ?></p>
    </div>
    <div class="row-actions">
      <a class="pill" href="<?= e(url('/b/' . $b['slug'] . '/tablero')) ?>">Tablero</a>
      <a class="pill" href="<?= e(url('/b/' . $b['slug'] . '/calendario')) ?>">Calendario</a>
      <button type="button" class="pill" data-copy-pass="<?= e($b['id']) ?>">Copiar contraseña</button>
      <a class="pill pill-solid" href="<?= e(url('/agencia/' . $b['id'])) ?>">Editar</a>
    </div>
  </article>
<?php endforeach; ?>
<?php if (!$brands): ?>
  <p class="empty">Todavía no hay marcas. Crea la primera.</p>
<?php endif; ?>
</div>
