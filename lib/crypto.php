<?php
/* Cifrado reversible de las contraseñas de las marcas (AES-256-GCM).
   Formato «iv.tag.datos» en base64, idéntico al del panel anterior, para poder migrar las contraseñas tal cual.
   La agencia necesita poder consultarlas, por eso no se guardan como hash. */

function cred_key()
{
    $secret = (string) cfg('credentials_key', '');
    if ($secret === '') {
        throw new RuntimeException('Falta credentials_key en config.php');
    }
    return hash('sha256', $secret, true);
}

function encrypt_password($plain)
{
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', cred_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) {
        throw new RuntimeException('No se pudo cifrar la contraseña');
    }
    return base64_encode($iv) . '.' . base64_encode($tag) . '.' . base64_encode($ct);
}

/** Devuelve la contraseña en claro, o null si el dato está dañado o la clave no coincide. */
function decrypt_password($payload)
{
    $parts = explode('.', (string) $payload);
    if (count($parts) !== 3) {
        return null;
    }
    $iv = base64_decode($parts[0], true);
    $tag = base64_decode($parts[1], true);
    $ct = base64_decode($parts[2], true);
    if ($iv === false || $tag === false || $ct === false) {
        return null;
    }
    $plain = openssl_decrypt($ct, 'aes-256-gcm', cred_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function password_matches_encrypted($plain, $payload)
{
    $actual = decrypt_password($payload);
    return $actual !== null && hash_equals($actual, (string) $plain);
}
