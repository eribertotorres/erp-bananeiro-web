<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use o botão Sair para encerrar a sessão.');
}
if (!valid_csrf()) {
    http_response_code(403);
    $title = 'Solicitação inválida';
    $message = 'A solicitação expirou ou é inválida. Tente novamente.';
    $retry = 'logout.php';
    require dirname(__DIR__) . '/app/unavailable.php';
    exit;
}
if (!isset($_SESSION['token'])) {
    destroy_session();
    redirect('login.php');
}
$response = api_request('POST', '/auth/logout', null, $_SESSION['token']);
if (in_array($response['status'], [204, 401], true)) {
    destroy_session();
    redirect('login.php');
}
error_log('ERP auth: endpoint=/auth/logout validation=unavailable status=' . $response['status']);
http_response_code(503);
$title = 'Saída não concluída';
$message = 'Não foi possível confirmar a saída. Tente novamente.';
$retry = 'logout.php';
require dirname(__DIR__) . '/app/unavailable.php';
