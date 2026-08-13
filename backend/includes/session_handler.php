<?php
// Simple session initializer used by backend pages
if (session_status() === PHP_SESSION_NONE) {
    $secure = false; // set to true if using HTTPS
    $httponly = true;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);
    session_start();
}
