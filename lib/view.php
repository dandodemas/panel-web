<?php
/* Plantillas: cada vista se pinta dentro de views/layout.php. */

function render($view, $vars = [], $layout = 'layout')
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include PANEL_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    include PANEL_ROOT . '/views/' . $layout . '.php';
}

function not_found()
{
    http_response_code(404);
    $user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    render('404', ['title' => 'No encontrado', 'user' => $user]);
    exit;
}

/** Datos de arranque para el JavaScript de la página (JSON seguro dentro de HTML). */
function boot_json($data)
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** «14 sept» a partir de «2026-09-14». */
function fmt_short_date($date)
{
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $p = explode('-', $date);
    if (count($p) !== 3) {
        return $date;
    }
    return (int) $p[2] . ' ' . $months[(int) $p[1] - 1];
}

function flash_set($message, $type = 'ok')
{
    $_SESSION['flash'] = ['m' => $message, 't' => $type];
}

function flash_take()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}
