# ERP Bananeiro

ERP web com autenticação real pela API. Eriberto é o Product Owner; Órion coordena arquitetura e revisão no chat do projeto. Bilhetes, Consulta e Consolidação continuam em desenvolvimento.

O primeiro fluxo previsto é **Registrar bilhete → Conferir lançamentos → Consolidar por cliente**. Os requisitos e as decisões pendentes estão em [docs/escopo-etapa-1.md](docs/escopo-etapa-1.md).

## Execução local

Ambiente: Windows, XAMPP, PHP 7.4.10 e extensão cURL. Sem dependências para instalar ou compilação.

`config/example.php` contém a URL `http://api-bananeira.test/api/v1`, timeout de conexão (3 segundos) e timeout total (10 segundos). Funciona como padrão local; copie para `config/local.php` se precisar sobrescrever valores. A cópia local é ignorada pelo Git. Não coloque credenciais nesses arquivos nem copie configurações privadas da API.

`allow_http_local = true` permite HTTP apenas para desenvolvimento local; os hosts HTTP permitidos da API estão explicitamente listados no bootstrap. Não exponha essa configuração externamente. O reconhecimento de HTTPS usa apenas a variável HTTPS do servidor, sem confiar em cabeçalhos de proxy.

No PowerShell:

```powershell
Set-Location D:\erp-bananeiro
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8080 -t public
```

Abra <http://127.0.0.1:8080>: o sistema encaminha ao login. A API precisa estar disponível e a conta deve existir nela. Encerre com `Ctrl+C`. O servidor embutido é apenas para desenvolvimento, com diretório público exclusivamente em `public/`. Nenhuma configuração de Apache ou conexão ao banco é necessária.

As sessões ficam em `storage/sessions/`, ignorado pelo Git, fora do diretório público. O processo PHP precisa de escrita; restrinja o acesso aos arquivos ao processo/operador autorizado. O cookie contém somente o identificador da sessão, com nome próprio, HttpOnly, SameSite=Lax, caminho `/` e Secure em HTTPS. Token e identidade ficam somente no servidor.

Para validar a sintaxe:

```powershell
Get-ChildItem app,config,public -Recurse -Filter *.php | ForEach-Object {
    & 'C:\xampp\php\php.exe' -l $_.FullName
}
python tests/auth_smoke.py
git diff --check
git check-ignore config/local.php storage/sessions/exemplo .env
```

## Arquivos e fluxo de autenticação

- `config/example.php`: URL e timeouts; `config/local.php` é uma sobrescrita opcional não versionada.
- `app/bootstrap.php`: configuração, cabeçalhos sem cache e inicialização da sessão.
- `app/http.php`: cliente cURL e validação de respostas, sem seguir redirecionamentos.
- `app/session.php`: CSRF, limpeza de sessão, redirects e proteção reutilizável `require_auth()`.
- `app/unavailable.php`: aviso de indisponibilidade e opção de repetir validação ou saída.
- `public/login.php`: login real, validação e envio pelo servidor para `/auth/login`.
- `public/index.php`: Início protegido, nome escapado e botão Sair.
- `public/logout.php`: somente POST com CSRF, com revogação na API.
- `public/assets/css/app.css`: estilos existentes acrescidos de formulário e ações de sessão.
- `tests/auth_smoke.py`: teste HTTP isolado com API simulada e credenciais sintéticas.

Login válido regenera o ID da sessão e usa POST/Redirect/GET com HTTP 303. A senha não sofre trim ou transformação. Não há chamadas de autenticação por JavaScript, token em HTML, URL ou localStorage. Toda requisição ao Início consulta `/auth/me` antes de mostrar conteúdo. Futuros módulos devem chamar `require_auth()` antes de qualquer saída.

HTTP 401 de `/auth/me` descarta a sessão e volta ao login. Falhas de rede, timeout, resposta inválida ou erro do serviço bloqueiam o conteúdo, preservam o token e oferecem nova tentativa. Essas falhas não são apresentadas como senha incorreta. Login e logout usam CSRF aleatório com comparação segura. Mensagens externas da API não são reproduzidas; diagnósticos registram apenas rota fixa, status, código cURL ou categoria, sem dados pessoais, senhas, tokens ou corpos.

Sair revoga por `/auth/logout` antes de limpar a sessão. HTTP 204 ou 401 permite remover a sessão e seu cookie e redirecionar ao login. Se a comunicação falhar, a sessão permanece e a página permite repetir a saída. Todas as páginas de autenticação, protegidas e redirects usam `Cache-Control: no-store`.

## Verificações automatizadas

O teste opcional usa Python padrão já disponível, sem instalar bibliotecas. Cria uma cópia temporária em `tmp/`, inicia servidores PHP apenas em loopback e os encerra ao final. Não utiliza a configuração local privada e não chama a API real. Verifica login válido/inválido, senha com espaços, CSRF, PRG, cookie, rotação de ID, escape do nome, `/me` a cada acesso, revogação, timeout, JSON inválido, 503 e logout com sucesso ou falha. Não substitui o teste com a API funcional e navegador.

## Roteiro manual para o PO — ainda não executado

Use uma conta de teste já existente na API. Não envie senhas pelo chat nem compartilhe cookies, tokens ou exportações de tráfego do navegador.

1. **Acesso direto:** em janela privada, abra `/index.php`. Deve redirecionar para `/login.php`, sem cartões ou nome. Confira `Cache-Control: no-store` nas respostas.
2. **Login inválido:** envie credenciais incorretas. Deve voltar ao login com mensagem de e-mail/senha incorretos. Campos vazios ou e-mail inválido não devem autenticar.
3. **Login válido:** entre com a conta de teste. Deve haver POST e redirect ao Início com nome, cartões indisponíveis e Sair. Confira troca do identificador da sessão, cookie HttpOnly/SameSite=Lax e ausência de token no HTML, URLs e localStorage. O token da API nunca aparece no tráfego do navegador.
4. **Revogação:** peça ao operador da API que revogue a sessão de teste por um meio já autorizado. Recarregue o Início: deve ir ao login. Fechar uma aba não equivale a revogar.
5. **Indisponibilidade:** temporariamente configure em `config/local.php` uma porta loopback sem serviço. Recarregue o Início autenticado: deve bloquear conteúdo e oferecer tentativa. Restaure a URL e repita: deve recuperar o acesso se a sessão ainda for válida. No login, a mesma falha deve informar indisponibilidade, sem acusar senha incorreta.
6. **CSRF:** nas ferramentas do navegador, altere/remova o campo oculto `csrf` antes de enviar login e logout. Login deve recusar; logout deve responder 403 e preservar sessão. GET direto a `/logout.php` deve responder 405. Reabra a página para obter formulário válido.
7. **Logout:** com a URL temporariamente indisponível, clique Sair. Deve informar saída não concluída e preservar sessão. Restaure a URL e repita pelo formulário: deve voltar ao login, remover cookie e impedir acesso direto ao Início. Sessão já revogada também deve permitir finalizar a saída local.
8. **Acessibilidade:** percorra login, navegação e Sair com Tab/Shift+Tab/Enter; confira foco, rótulos e tela estreita. Em HTTPS, confira a flag Secure do cookie.

## Limites e pendências

Esta é autenticação local, sem permissões empresariais ou acesso a dados do ERP. Antes de exposição externa são pendências: **HTTPS com HTTP desabilitado, atualização do PHP, limitação de tentativas e autorização por função/empresa**. A implantação também exige revisão das permissões dos arquivos de sessão e configuração explícita de eventuais proxies.

Sem cadastro público, recuperação de senha, persistência de dados do ERP, tabelas, cálculos ou módulos operacionais. Legado em `D:\erp`, API em `D:\api-bananeira` e Flutter permanecem intactos. Sem instalações, configuração de Apache, commit ou push.

O documento de escopo descreve a fundação visual original; esta entrega avança somente a autenticação, sem antecipar regras de negócio. Configurações privadas, dependências, sessões e logs são ignorados pelo Git. Não versionar segredos.
