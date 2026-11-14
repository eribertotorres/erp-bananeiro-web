<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (isset($_SESSION['token'])) {
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        flash('A solicitação expirou ou é inválida. Tente novamente.');
    } else {
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $password === '') {
            flash('Informe um e-mail válido e sua senha.');
        } else {
            $response = api_request('POST', '/auth/login', ['email' => $email, 'password' => $password]);
            unset($password);
            $data = $response['data']['data'] ?? null;
            if ($response['status'] === 200 && is_array($data)
                && is_string($data['token'] ?? null) && preg_match('/^[A-Za-z0-9._~+\/-]+=*$/D', $data['token'])
                && ($data['token_type'] ?? null) === 'Bearer'
                && valid_expiration($data['expires_at'] ?? null) && valid_identity($data['usuario'] ?? null)) {
                session_regenerate_id(true);
                $_SESSION = ['token' => $data['token'], 'usuario' => $data['usuario']];
                csrf_token();
                redirect('index.php');
            }
            if ($response['status'] === 401) {
                flash('E-mail ou senha incorretos.');
            } else {
                error_log('ERP auth: endpoint=/auth/login validation=unavailable status=' . $response['status']);
                flash('Não foi possível entrar agora. Tente novamente.');
            }
        }
    }
    redirect('login.php');
}
$message = take_message();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Entrar | ERP Bananeiro</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<main id="conteudo" class="container auth-panel" tabindex="-1">
    <p class="brand"><span class="brand-mark" aria-hidden="true">B</span> ERP Bananeiro</p>
    <h1>Entrar</h1>
    <?php if ($message !== ''): ?><p class="notice" role="alert"><?= escape($message) ?></p><?php endif; ?>
    <form method="post" action="login.php" class="auth-form">
        <input type="hidden" name="csrf" value="<?= escape(csrf_token()) ?>">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required>
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Entrar</button>
    </form>
</main>
</body></html>
