# ERP Bananeiro

Fundação da interface web do ERP Bananeiro. Eriberto é o Product Owner; Órion coordena arquitetura e revisão no chat do projeto.

O primeiro fluxo previsto é **Registrar bilhete → Conferir lançamentos → Consolidar por cliente**. Os requisitos e as decisões pendentes estão em [docs/escopo-etapa-1.md](docs/escopo-etapa-1.md).

## Execução local

Ambiente informado: Windows, XAMPP e PHP 7.4.10. Não há dependências para instalar nem etapa de compilação.

No PowerShell:

```powershell
Set-Location D:\erp-bananeiro
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8080 -t public
```

Abra <http://127.0.0.1:8080>. Para encerrar, use `Ctrl+C` no terminal. O servidor embutido destina-se ao desenvolvimento local; o diretório público é `public`. Esta entrega não exige configuração do Apache ou conexão ao banco.

Para validar a sintaxe:

```powershell
& 'C:\xampp\php\php.exe' -l public/index.php
```

## Limites desta entrega

- Página inicial em português brasileiro, responsiva, com navegação interna e apresentação do fluxo previsto.
- Bilhetes e Consolidação identificados como próximos módulos, sem rotas ou ações operacionais.
- Sem autenticação, formulário de login, persistência, banco, cálculos, indicadores ou dados operacionais simulados.
- Sem integração com a API nesta entrega. A estratégia de autenticação web será definida depois; tokens não devem ser armazenados em `localStorage`, e credenciais não devem ser copiadas.
- Nenhuma alteração no legado em `D:\erp` ou na API funcional em `D:\api-bananeira`.

A etapa 1 completa inclui login real, bilhete digital, consulta e consolidação; esta entrega cobre apenas a fundação visual e a documentação. Configurações privadas, dependências e logs são ignorados pelo Git. Não versionar segredos.
