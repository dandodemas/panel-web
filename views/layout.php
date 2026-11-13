<?php
/* Plantilla general. Variables: $title, $content, $user (o null), $nav, $brand (opcional), $bodyClass (opcional), $scripts (opcional) */
$bodyClass = isset($bodyClass) ? $bodyClass : '';
$scripts = isset($scripts) ? $scripts : [];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> · Dando de Más©</title>
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<script src="<?= e(asset('theme.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(asset('panel.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>" data-base="<?= e(base()) ?>">
<?php include PANEL_ROOT . '/views/bar.php'; ?>
<main class="page">
<?= $content ?>
</main>
<script src="<?= e(asset('app.js')) ?>"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
