<?php

ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$config = require dirname(__DIR__) . '/config/example.php';
$local = dirname(__DIR__) . '/config/local.php';
if (is_file($local)) {
    $config = array_replace($config, require $local);
}
$base = parse_url($config['api_base_url']);
$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
$localHost = isset($base['host']) && in_array($base['host'], ['api-bananeira.test', 'localhost', '127.0.0.1', '::1'], true);
if (!extension_loaded('curl') || !is_array($base) || !isset($base['scheme'], $base['host'])
    || isset($base['user']) || isset($base['pass']) || isset($base['query']) || isset($base['fragment'])
    || !in_array($base['scheme'], ['http', 'https'], true)
    || ($base['scheme'] === 'http' && (!$config['allow_http_local'] || !$localHost))
    || (!$https && !$config['allow_http_local'])
    || !is_int($config['connect_timeout']) || $config['connect_timeout'] < 1
    || !is_int($config['request_timeout']) || $config['request_timeout'] < $config['connect_timeout']) {
    http_response_code(503);
    exit('Configuração indisponível. Consulte o responsável pelo sistema.');
}
$config['api_base_url'] = rtrim($config['api_base_url'], '/');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('erp_bananeiro_session');
session_cache_limiter('');
$sessionDirectory = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    http_response_code(503);
    exit('Sessão indisponível. Tente novamente mais tarde.');
}
session_save_path($sessionDirectory);
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
if (!session_start()) {
    http_response_code(503);
    exit('Sessão indisponível. Tente novamente mais tarde.');
}
require __DIR__ . '/http.php';
require __DIR__ . '/session.php';
