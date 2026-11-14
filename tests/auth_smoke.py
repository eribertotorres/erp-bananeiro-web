"""Teste HTTP isolado: Python padrão + PHP local, sem chamar a API real."""
import http.cookiejar
import pathlib
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[1]
PHP = r"C:\xampp\php\php.exe"

FIXTURE = r'''<?php
$mode = trim(file_get_contents(__DIR__ . '/mode'));
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
if ($mode === 'timeout') { sleep(2); exit; }
if ($mode === 'unavailable') { http_response_code(503); exit; }
if ($mode === 'invalid') { echo '{invalid'; exit; }
$user = ['id' => 1, 'nome' => '<script>synthetic</script>', 'email' => 'teste@example.test'];
$expiry = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
if ($path === '/auth/login') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (($input['email'] ?? '') !== 'teste@example.test' || ($input['password'] ?? '') !== ' synthetic ') {
        http_response_code(401); echo json_encode(['error' => ['code' => 'invalid', 'message' => 'invalid']]); exit;
    }
    echo json_encode(['data' => ['token' => 'synthetic-token', 'token_type' => 'Bearer', 'expires_at' => $expiry, 'usuario' => $user]]); exit;
}
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer synthetic-token' || $mode === 'revoked') {
    http_response_code(401); exit;
}
if ($path === '/auth/me') {
    file_put_contents(__DIR__ . '/me-count', '1', FILE_APPEND);
    echo json_encode(['data' => ['usuario' => $user, 'expires_at' => $expiry]]); exit;
}
if ($path === '/auth/logout') { http_response_code(204); exit; }
http_response_code(404);
'''


def port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


def run():
    (ROOT / 'tmp').mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='auth-smoke-', dir=ROOT / 'tmp') as temporary:
        work = pathlib.Path(temporary)
        for directory in ('app', 'config', 'public'):
            shutil.copytree(ROOT / directory, work / directory, ignore=shutil.ignore_patterns('local.php'))
        api_port, web_port = port(), port()
        (work / 'fixture.php').write_text(FIXTURE, encoding='utf-8')
        (work / 'mode').write_text('ok')
        (work / 'config' / 'local.php').write_text(
            "<?php return ['api_base_url'=>'http://127.0.0.1:%d', 'connect_timeout'=>1, 'request_timeout'=>1];" % api_port,
            encoding='utf-8')
        processes = []
        try:
            for server_port, args in ((api_port, ['fixture.php']), (web_port, ['-t', 'public'])):
                processes.append(subprocess.Popen(
                    [PHP, '-S', '127.0.0.1:%d' % server_port] + args, cwd=work,
                    stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                    creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0)))
            for server_port in (api_port, web_port):
                for attempt in range(50):
                    try:
                        with socket.create_connection(('127.0.0.1', server_port), timeout=0.1):
                            break
                    except OSError:
                        time.sleep(0.1)
                else:
                    raise AssertionError('Servidor de teste não iniciou')
            jar = http.cookiejar.CookieJar()
            opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())

            def request(page, fields=None):
                data = None if fields is None else urllib.parse.urlencode(fields).encode()
                try:
                    response = opener.open('http://127.0.0.1:%d/%s' % (web_port, page), data, timeout=5)
                except urllib.error.HTTPError as error:
                    response = error
                body = response.read().decode('utf-8')
                assert response.headers.get('Cache-Control') == 'no-store', 'Cache não bloqueado'
                assert 'synthetic-token' not in body, 'Token exposto'
                return response.code, response.headers, body

            def csrf(body):
                return re.search(r'name="csrf" value="([a-f0-9]+)"', body).group(1)

            def mode(value):
                (work / 'mode').write_text(value)

            def login():
                body = request('login.php')[2]
                result = request('login.php', {'csrf': csrf(body), 'email': 'teste@example.test', 'password': ' synthetic '})
                assert result[0] == 303 and result[1]['Location'] == 'index.php'

            first = request('index.php')
            assert first[1]['Location'] == 'login.php'
            assert 'HttpOnly' in first[1]['Set-Cookie'] and 'SameSite=Lax' in first[1]['Set-Cookie']
            status, headers, body = request('login.php')
            assert status == 200
            before = list(jar)[0].value
            assert request('login.php', {'csrf': 'invalid', 'email': 'teste@example.test', 'password': ' synthetic '})[1]['Location'] == 'login.php'
            assert 'solicitação expirou' in request('login.php')[2]
            body = request('login.php')[2]
            request('login.php', {'csrf': csrf(body), 'email': 'teste@example.test', 'password': 'wrong'})
            assert 'E-mail ou senha incorretos' in request('login.php')[2]
            login()
            assert list(jar)[0].value != before, 'ID não regenerado'
            body = request('index.php')[2]
            assert '&lt;script&gt;synthetic&lt;/script&gt;' in body and '<script>synthetic' not in body
            request('index.php')
            assert len((work / 'me-count').read_text()) == 2, '/me não foi chamado a cada acesso'
            token = csrf(body)
            assert request('logout.php')[0] == 405
            assert request('logout.php', {'csrf': 'invalid'})[0] == 403
            assert request('index.php')[0] == 200
            for failure in ('unavailable', 'invalid', 'timeout'):
                mode(failure)
                result = request('index.php')
                assert result[0] == 503 and 'Registrar bilhete' not in result[2]
                # O fixture PHP é monothread: aguardar o timeout concluir.
                if failure == 'timeout':
                    time.sleep(1.2)
                mode('ok')
                assert request('index.php')[0] == 200, 'Token perdido durante indisponibilidade'
            mode('unavailable')
            assert request('logout.php', {'csrf': token})[0] == 503
            mode('ok')
            assert request('index.php')[0] == 200
            assert request('logout.php', {'csrf': token})[1]['Location'] == 'login.php'
            assert not list(jar), 'Cookie não removido'
            assert request('index.php')[1]['Location'] == 'login.php'
            login()
            mode('revoked')
            assert request('index.php')[1]['Location'] == 'login.php'
            mode('ok')
            login()
            token = csrf(request('index.php')[2])
            mode('revoked')
            assert request('logout.php', {'csrf': token})[1]['Location'] == 'login.php'
            mode('unavailable')
            body = request('login.php')[2]
            request('login.php', {'csrf': csrf(body), 'email': 'teste@example.test', 'password': ' synthetic '})
            body = request('login.php')[2]
            assert 'Não foi possível entrar agora' in body and 'senha incorretos' not in body
            mode('ok')
            login()
            token = csrf(request('index.php')[2])
            processes[0].terminate()
            processes[0].wait(timeout=5)
            assert request('index.php')[0] == 503, 'Falha de conexão liberou conteúdo'
            assert request('logout.php', {'csrf': token})[0] == 503
            assert request('login.php')[1]['Location'] == 'index.php', 'Falha de conexão apagou token'
            print('OK: login, PRG, CSRF, cookie, rotação, escape, /me por acesso, falhas de conexão/timeout/JSON/503, revogação e logout (API simulada).')
        finally:
            for process in processes:
                process.terminate()
            for process in processes:
                process.wait(timeout=5)


if __name__ == '__main__':
    run()
