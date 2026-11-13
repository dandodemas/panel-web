<?php
/* Panel de agencia: marcas (alta, edición, borrado, contraseñas) y estado del sistema. */

function agency_validate_brand($in, $ignoreId = null)
{
    $name = trim((string) (isset($in['name']) ? $in['name'] : ''));
    $slug = strtolower(trim((string) (isset($in['slug']) ? $in['slug'] : '')));
    $password = (string) (isset($in['password']) ? $in['password'] : '');

    if ($name === '' || str_len($name) > 100) {
        return ['ok' => false, 'error' => 'El nombre es obligatorio (máximo 100 caracteres).'];
    }
    if (!preg_match('/^[a-z0-9-]{2,40}$/', $slug)) {
        return ['ok' => false, 'error' => 'El usuario usa minúsculas, números y guiones (2-40 caracteres).'];
    }
    if ($password !== '' && strlen($password) < 8) {
        return ['ok' => false, 'error' => 'La contraseña necesita al menos 8 caracteres.'];
    }
    if ($password !== '' && strlen($password) > 200) {
        return ['ok' => false, 'error' => 'La contraseña es demasiado larga.'];
    }

    $clash = db_one('SELECT id FROM brands WHERE slug = ?' . ($ignoreId ? ' AND id <> ?' : ''), $ignoreId ? [$slug, $ignoreId] : [$slug]);
    if ($clash) {
        return ['ok' => false, 'error' => 'Ya existe otra marca con ese usuario.'];
    }
    if (db_one('SELECT id FROM agency_admins WHERE username = ?', [$slug])) {
        return ['ok' => false, 'error' => 'Ese usuario está reservado para la agencia.'];
    }
    return ['ok' => true, 'data' => ['name' => $name, 'slug' => $slug, 'password' => $password]];
}

function h_agency_list()
{
    $user = require_agency();
    $brands = db_all(
        'SELECT b.id, b.slug, b.name, (SELECT COUNT(*) FROM content_items c WHERE c.brand_id = b.id) AS n
         FROM brands b ORDER BY b.name ASC'
    );
    render('agency_list', [
        'title' => 'Marcas',
        'user' => $user,
        'nav' => 'marcas',
        'brands' => $brands,
        'flash' => flash_take(),
    ]);
}

function h_agency_new_get()
{
    $user = require_agency();
    render('agency_new', [
        'title' => 'Nueva marca',
        'user' => $user,
        'nav' => 'marcas',
        'error' => null,
        'created' => null,
        'old' => ['name' => '', 'slug' => '', 'password' => generate_password()],
    ]);
}

function h_agency_new_post()
{
    $user = require_agency();
    require_csrf();
    $in = [
        'name' => isset($_POST['name']) ? $_POST['name'] : '',
        'slug' => isset($_POST['slug']) ? $_POST['slug'] : '',
        'password' => isset($_POST['password']) ? $_POST['password'] : '',
    ];
    if ($in['password'] === '') {
        $in['password'] = generate_password();
    }
    $v = agency_validate_brand($in);
    if (!$v['ok']) {
        render('agency_new', ['title' => 'Nueva marca', 'user' => $user, 'nav' => 'marcas', 'error' => $v['error'], 'created' => null, 'old' => $in]);
        return;
    }
    $d = $v['data'];
    $now = now_utc();
    db_exec(
        'INSERT INTO brands (id, slug, name, password_encrypted, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
        [new_id(), $d['slug'], $d['name'], encrypt_password($d['password']), $now, $now]
    );
    render('agency_new', [
        'title' => 'Marca creada',
        'user' => $user,
        'nav' => 'marcas',
        'error' => null,
        'created' => ['slug' => $d['slug'], 'name' => $d['name'], 'password' => $d['password']],
        'old' => $in,
    ]);
}

function agency_brand_or_404($id)
{
    $brand = db_one('SELECT id, slug, name FROM brands WHERE id = ?', [$id]);
    if (!$brand) {
        not_found();
    }
    return $brand;
}

function h_agency_edit_get($id)
{
    $user = require_agency();
    $brand = agency_brand_or_404($id);
    render('agency_edit', [
        'title' => 'Editar ' . $brand['name'],
        'user' => $user,
        'nav' => 'marcas',
        'brand' => $brand,
        'error' => null,
        'newPassword' => generate_password(),
    ]);
}

function h_agency_edit_post($id)
{
    $user = require_agency();
    require_csrf();
    $brand = agency_brand_or_404($id);
    $in = [
        'name' => isset($_POST['name']) ? $_POST['name'] : '',
        'slug' => isset($_POST['slug']) ? $_POST['slug'] : '',
        'password' => isset($_POST['password']) ? $_POST['password'] : '',
    ];
    $v = agency_validate_brand($in, $id);
    if (!$v['ok']) {
        $brand['name'] = $in['name'];
        $brand['slug'] = $in['slug'];
        render('agency_edit', ['title' => 'Editar marca', 'user' => $user, 'nav' => 'marcas', 'brand' => $brand, 'error' => $v['error'], 'newPassword' => generate_password()]);
        return;
    }
    $d = $v['data'];
    if ($d['password'] !== '') {
        db_exec(
            'UPDATE brands SET name = ?, slug = ?, password_encrypted = ?, updated_at = ? WHERE id = ?',
            [$d['name'], $d['slug'], encrypt_password($d['password']), now_utc(), $id]
        );
    } else {
        db_exec('UPDATE brands SET name = ?, slug = ?, updated_at = ? WHERE id = ?', [$d['name'], $d['slug'], now_utc(), $id]);
    }
    flash_set('Cambios guardados.');
    redirect('/agencia');
}

function h_agency_delete($id)
{
    require_agency();
    require_csrf();
    $brand = agency_brand_or_404($id);
    foreach (db_all('SELECT * FROM photos WHERE brand_id = ?', [$id]) as $p) {
        photo_unlink_files($p);
    }
    $dir = photo_dir() . '/' . $id;
    if (is_dir($dir)) {
        @rmdir($dir);
    }
    db_exec('DELETE FROM photos WHERE brand_id = ?', [$id]);
    db_exec('DELETE FROM content_items WHERE brand_id = ?', [$id]);
    db_exec('DELETE FROM brands WHERE id = ?', [$id]);
    flash_set('Marca «' . $brand['name'] . '» borrada.');
    redirect('/agencia');
}

/** Devuelve la contraseña actual de una marca (solo agencia, solo bajo petición). */
function h_agency_password_json($id)
{
    agency_api();
    require_csrf(true);
    $row = db_one('SELECT password_encrypted FROM brands WHERE id = ?', [$id]);
    if (!$row) {
        json_error('No encontrado.', 404);
    }
    $plain = decrypt_password($row['password_encrypted']);
    if ($plain === null) {
        json_error('No se puede leer la contraseña (¿clave de cifrado distinta?). Genera una nueva desde Editar.', 500);
    }
    json_response(['ok' => true, 'password' => $plain]);
}

function h_agency_system()
{
    $user = require_agency();
    $caps = photo_capabilities();
    $storage = rtrim((string) cfg('storage', PANEL_ROOT . '/storage'), '/');
    $photoRows = db_one(
        'SELECT COUNT(*) AS n, COALESCE(SUM(bytes), 0) AS bytes, MIN(expires_at) AS next_expiry
         FROM photos WHERE expired_at IS NULL'
    );
    $expired = (int) db_val('SELECT COUNT(*) FROM photos WHERE expired_at IS NOT NULL');
    $free = @disk_free_space($storage);
    render('agency_system', [
        'title' => 'Sistema',
        'user' => $user,
        'nav' => 'sistema',
        'flash' => flash_take(),
        'info' => [
            'PHP' => PHP_VERSION,
            'Base de datos' => db_driver(),
            'Carpeta de fotos' => is_writable($storage) ? 'escribible' : 'NO escribible',
            'Espacio libre en disco' => $free === false ? 'desconocido' : human_bytes($free),
            'Fotos guardadas' => (int) $photoRows['n'] . ' (' . human_bytes($photoRows['bytes']) . ')',
            'Fotos ya caducadas' => $expired,
            'Próxima caducidad' => $photoRows['next_expiry'] ? substr($photoRows['next_expiry'], 0, 10) : '—',
            'Plazo de conservación' => photo_days() . ' días',
            'Última limpieza' => setting_get('last_cleanup', 'nunca'),
        ],
        'caps' => $caps,
    ]);
}

function h_agency_cleanup()
{
    require_agency();
    require_csrf();
    $r = run_cleanup();
    flash_set('Limpieza hecha: ' . $r['deleted'] . ' foto(s) caducada(s) borrada(s), ' . human_bytes($r['freed']) . ' liberados.');
    redirect('/agencia/sistema');
}
