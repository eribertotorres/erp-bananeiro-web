# Escopo e primeiro fluxo

## Responsabilidades e limites

Eriberto é o Product Owner. Órion coordena arquitetura e revisão no chat. O novo ERP fica em `D:\erp-bananeiro`; o legado em `D:\erp` deve permanecer intacto. A API funcional em `D:\api-bananeira` não será alterada nesta entrega.

Ambiente informado: Windows, XAMPP e PHP 7.4.10 em `C:\xampp\php\php.exe`. Esta entrega não instala dependências, atualiza o ambiente, configura Apache ou conecta ao banco.

## Etapas confirmadas

1. **ERP web:** login real, bilhete digital, consulta e consolidação.
2. **Aplicativo Flutter:** mesmo fluxo, offline-first e sincronização sem duplicação.
3. **Dashboard:** programação de equipes, visitas e checklists.

Marmitas fica fora do escopo atual e isolado no computador da empresa.

## Entrega atual

Criar a fundação visual com Início funcional, navegação interna e apresentação do percurso. Bilhetes e Consolidação aparecem como próximos módulos. Não implementar autenticação, persistência ou cálculos operacionais; não simular usuário autenticado, dados, indicadores ou funcionalidades concluídas. Não criar formulário de login fictício nem esquema de banco baseado em hipóteses.

## Primeiro fluxo previsto

**Registrar bilhete → Conferir lançamentos → Consolidar por cliente**

1. **Registrar bilhete:** registrar produtor, cliente, data do embalamento, motorista e quantidades por tipo de caixa. Produtor e fornecedor são a mesma entidade neste fluxo. O sistema terá identificador próprio; o número impresso do bilhete pode ser ignorado inicialmente.
2. **Conferir lançamentos:** consultar e conferir os registros antes da consolidação. Campos obrigatórios, critérios de conferência e procedimento de correção ainda precisam ser confirmados.
3. **Consolidar por cliente:** reunir os lançamentos conforme as regras de cada cliente. Regras detalhadas, alocação entre clientes e tratamento de correções dependem de confirmação antes da implementação.

Os passos descrevem o fluxo futuro, sem definir telas, validações ou resultados ainda não acordados.

## Regras de domínio confirmadas

- A unidade é **caixas**. Rótulos como `20k` ou `15k` não autorizam conversão para peso real.
- Ego possui Plan4 e regras específicas, ainda a detalhar.
- “Polpa Nova” é o nome histórico do modelo genérico dos demais clientes, incluindo Banana Brasil. Não é exclusivo de indústria.
- Massa/indústria deve ser distinguida das caixas comerciais.
- Não deduzir regras de consolidação ou estrutura de banco a partir desses nomes e rótulos.

## API existente: referência para tarefa posterior

| Método e rota | Contrato informado |
| --- | --- |
| `POST /api/v1/auth/login` | Recebe `email` e `password`; retorna `data.token`, `token_type`, `expires_at` e `usuario`. |
| `GET /api/v1/auth/me` | Valida Bearer token. |
| `POST /api/v1/auth/logout` | Revoga a sessão e retorna HTTP 204. |
| `GET /api/v1/motd` | Retorna comunicado público ou `data`. |

Esta entrega não chama esses endpoints. A integração de autenticação web será definida em tarefa posterior. Não armazenar tokens em `localStorage` nem copiar credenciais.

## Dúvidas a confirmar antes de implementar

- Quais campos são obrigatórios e quais validações se aplicam ao bilhete?
- Quais tipos de caixa existem e como massa/indústria deve ser registrada e apresentada?
- Quais são as regras do Plan4 de Ego e do modelo histórico “Polpa Nova”?
- Como funcionam agrupamento, período, fechamento e resultados da consolidação por cliente?
- Como tratar correções, exclusões e alterações após a consolidação?
- Como ocorre a alocação entre clientes e quais restrições se aplicam?
- Como será a autenticação web, incluindo guarda de sessão, expiração e saída?
- Para a etapa Flutter, como identificar registros e resolver conflitos garantindo sincronização sem duplicação?

Essas dúvidas não bloqueiam a página inicial; devem orientar as próximas decisões com o Product Owner antes da implementação operacional.
