<?php

function escape($value)
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect($page)
{
    header('Location: ' . $page, true, 303);
    exit;
}

function csrf_token()
{
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function valid_csrf()
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function destroy_session()
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/',
        'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

function flash($message)
{
    $_SESSION['message'] = $message;
}

function take_message()
{
    $message = $_SESSION['message'] ?? '';
    unset($_SESSION['message']);
    return $message;
}

// Todo módulo protegido deve chamar esta função antes de produzir HTML.
function require_auth()
{
    if (!isset($_SESSION['token'])) {
        redirect('login.php');
    }
    $response = api_request('GET', '/auth/me', null, $_SESSION['token']);
    if ($response['status'] === 401) {
        destroy_session();
        redirect('login.php');
    }
    $data = $response['data']['data'] ?? null;
    if ($response['status'] !== 200 || !is_array($data)
        || !valid_identity($data['usuario'] ?? null) || !valid_expiration($data['expires_at'] ?? null)) {
        error_log('ERP auth: endpoint=/auth/me validation=unavailable');
        http_response_code(503);
        $title = 'Não foi possível validar sua sessão';
        $message = 'O serviço está indisponível no momento. Tente novamente.';
        $retry = 'index.php';
        require __DIR__ . '/unavailable.php';
        exit;
    }
    $_SESSION['usuario'] = $data['usuario'];
    return $data['usuario'];
}
