<?php
/* Sesión, login con límite de intentos y comprobación de permisos por marca. */

const SESSION_IDLE_SECONDS = 43200;   // 12 h sin actividad

function current_user()
{
    if (empty($_SESSION['user'])) {
        return null;
    }
    $last = isset($_SESSION['last']) ? (int) $_SESSION['last'] : 0;
    if ($last > 0 && time() - $last > SESSION_IDLE_SECONDS) {
        auth_logout();
        return null;
    }
    $_SESSION['last'] = time();
    return $_SESSION['user'];
}

function login_blocked($username)
{
    $since = gmdate('Y-m-d H:i:s', time() - 900);
    $byIp = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at >= ?', [client_ip(), $since]);
    $byUser = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND created_at >= ?', [$username, $since]);
    return $byIp >= 12 || $byUser >= 8;
}

function login_record_failure($username)
{
    db_exec(
        'INSERT INTO login_attempts (id, ip, username, created_at) VALUES (?, ?, ?, ?)',
        [new_id(), client_ip(), substr($username, 0, 80), now_utc()]
    );
    db_exec('DELETE FROM login_attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
}

/** Devuelve ['ok'=>true] o ['ok'=>false,'error'=>'…']. */
function auth_login($username, $password)
{
    $username = strtolower(trim((string) $username));
    $password = (string) $password;
    $fail = ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];

    if ($username === '' || $password === '' || strlen($username) > 80 || strlen($password) > 200) {
        return $fail;
    }
    if (login_blocked($username)) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'];
    }

    $user = null;
    $brand = db_one('SELECT id, slug, name, password_encrypted FROM brands WHERE slug = ?', [$username]);
    if ($brand && password_matches_encrypted($password, $brand['password_encrypted'])) {
        $user = ['type' => 'brand', 'id' => $brand['id'], 'slug' => $brand['slug'], 'name' => $brand['name']];
    } else {
        $admin = db_one('SELECT id, username, password_hash FROM agency_admins WHERE username = ?', [$username]);
        // Si el usuario no existe se verifica contra un hash falso para que tarde igual.
        $hash = $admin ? preg_replace('/^\$2[abx]\$/', '$2y$', $admin['password_hash']) : password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
        if (password_verify($password, $hash) && $admin) {
            $user = ['type' => 'agency', 'id' => $admin['id'], 'slug' => null, 'name' => $admin['username']];
        }
    }

    if (!$user) {
        login_record_failure($username);
        return $fail;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['last'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    db_exec('DELETE FROM login_attempts WHERE username = ?', [$username]);
    return ['ok' => true, 'user' => $user];
}

function auth_logout()
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function home_for($user)
{
    if ($user['type'] === 'agency') {
        return '/agencia';
    }
    return '/b/' . $user['slug'] . '/tablero';
}

function require_login()
{
    $u = current_user();
    if (!$u) {
        redirect('/login');
    }
    return $u;
}

function require_agency()
{
    $u = require_login();
    if ($u['type'] !== 'agency') {
        not_found();
    }
    return $u;
}

/** Marca a la que da acceso la sesión: la agencia entra a cualquiera; una marca, solo a la suya. */
function brand_context($slug)
{
    $u = require_login();
    $brand = db_one('SELECT id, slug, name FROM brands WHERE slug = ?', [$slug]);
    if (!$brand) {
        not_found();
    }
    if ($u['type'] === 'brand' && $u['id'] !== $brand['id']) {
        not_found();
    }
    return [$u, $brand];
}

/** Igual que brand_context pero para llamadas JSON: errores en JSON en vez de redirecciones. */
function brand_context_api($slug)
{
    $u = current_user();
    if (!$u) {
        json_error('Sesión caducada. Vuelve a entrar.', 401);
    }
    $brand = db_one('SELECT id, slug, name FROM brands WHERE slug = ?', [$slug]);
    if (!$brand || ($u['type'] === 'brand' && $u['id'] !== $brand['id'])) {
        json_error('No encontrado.', 404);
    }
    return [$u, $brand];
}

function agency_api()
{
    $u = current_user();
    if (!$u || $u['type'] !== 'agency') {
        json_error('No autorizado.', 403);
    }
    return $u;
}
