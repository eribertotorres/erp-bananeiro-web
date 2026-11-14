<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Serviço indisponível | ERP Bananeiro</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body><main class="container auth-panel"><h1><?= escape($title) ?></h1><p role="alert"><?= escape($message) ?></p>
<?php if ($retry === 'logout.php'): ?>
<form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= escape(csrf_token()) ?>"><button type="submit">Tentar sair novamente</button></form>
<?php else: ?>
<a href="index.php">Tentar novamente</a>
<?php endif; ?>
</main></body></html>
