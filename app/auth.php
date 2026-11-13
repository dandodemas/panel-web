<?php
/* Inicio y cierre de sesión. */

function h_home()
{
    $u = current_user();
    if (!$u) {
        redirect('/login');
    }
    redirect(home_for($u));
}

function h_login_get()
{
    $u = current_user();
    if ($u) {
        redirect(home_for($u));
    }
    render('login', ['title' => 'Entrar', 'user' => null, 'error' => null, 'bodyClass' => 'is-login']);
}

function h_login_post()
{
    require_csrf();
    $res = auth_login(isset($_POST['usuario']) ? $_POST['usuario'] : '', isset($_POST['password']) ? $_POST['password'] : '');
    if (!$res['ok']) {
        http_response_code(401);
        render('login', ['title' => 'Entrar', 'user' => null, 'error' => $res['error'], 'bodyClass' => 'is-login']);
        return;
    }
    redirect(home_for($res['user']));
}

function h_logout_post()
{
    require_csrf();
    auth_logout();
    redirect('/login');
}
