# Relatório Técnico — Módulos FINANCEIRO e PRODUÇÃO (SINPORG)
Workspace: `C:\xampp\htdocs\app` · PHP puro (sem framework) + MySQL/MariaDB · Front controller `index.php`
Documento gerado por leitura integral dos arquivos. Cada afirmação traz citação `arquivo:linha`.

---

## 0. Base de análise e metodologia

- Front controller: `.htaccess:24` manda toda requisição que não seja arquivo/diretório existente para `index.php?url=$1`. Os arquivos reais em `api/**` são servidos **diretamente** (sem passar pelo roteador) — é o caso dos webhooks PIX.
- O roteador amigável está em `index.php:4-1179`; `$parts = explode('/', $rawUrl)` em `index.php:8`.
- **Não existe guarda global de autenticação no roteador**: `index.php:1-8` apenas carrega `config/config.php`, e `config/config.php` termina em `config/config.php:104` (apenas bootstrap de sessão, sem checagem de login). Portanto **cada arquivo de módulo é responsável pela própria autorização**, e páginas que não checam nada são públicas.
- Bootstrap das telas internas: `includes/header.php:5` cria `$user = new User()`; `includes/header.php:16-19` redireciona para `/login` se `!$user->isLoggedIn()` (salvo `$PERMITIR_ACESSO_ANONIMO` / `$PERMITIR_SESSAO_PORTAL`). Várias telas carregam `header.php` **depois** de fazer o próprio check (ex.: `modulos/contas/*`), outras fazem o check antes (ex.: `modulos/caixa/*`).
- Semântica de nível (`config/config.php:22-24`): `NIVEL_ADMIN=1`, `NIVEL_GESTOR=2`, `NIVEL_USUARIO=3`.
  - `User::hasPermission()` (`classes/User.php:114-139`): nível 1 passa sempre; exige `is_numeric($_SESSION['user_nivel'])` e `> 0` (endurecimento anti-escalonamento em `classes/User.php:119-133`).
  - `User::podeAcessarDiretoria()` (`classes/User.php:380-383`) = Admin **ou** membro de diretoria/comissão.
  - `User::isMembroDiretoria()` (`classes/User.php:363-370`); cargos em `classes/User.php:278-287` (diretor, secretario, tesoureiro, comissao_educativa, comissao_etica, conselho_fiscal, presidente, vice-presidente).
  - `User::getAssociadoId()` (`classes/User.php:259-272`) devolve `null` em 3 cenários — inclusive quando o usuário é da tabela `usuarios` sem `associado_id` e o vínculo só existe por `associados.user_id`.
  - `User::getAssociadoIdResolvido()` (`classes/User.php:299-329`) é a versão robusta (cobre as 3 formas de sessão).
- `Database::getInstance()` usa `PDO::ERRMODE_EXCEPTION` (`classes/Database.php:15`) → **qualquer SQL com coluna inexistente lança exceção**; onde não há `try/catch`, resulta em erro fatal/HTTP 500.

### Roteamento dos módulos analisados (`index.php`)

| Rota | Arquivo | Linha do roteador |
|---|---|---|
| `/caixa` | `modulos/caixa/listar.php` | `index.php:1093-1095` |
| `/caixa/lancamento` | `modulos/caixa/cadastrar.php` | `index.php:1096-1098` |
| `/caixa/cobranca` | `modulos/caixa/cobranca.php` | `index.php:1099-1101` |
| `/caixa/relatorio` | `modulos/caixa/relatorio.php` | `index.php:1102-1104` |
| `/caixa/{id}/excluir` | `modulos/caixa/excluir.php` (`$_GET['id']=$parts[1]`) | `index.php:1105-1107` |
| `/contas` | `modulos/contas/listar.php` | `index.php:801-804` |
| `/conta/novo` | `modulos/contas/cadastrar.php` | `index.php:805-808` |
| `/conta/{id}/editar` | `modulos/contas/editar.php` | `index.php:809-813` |
| `/contas/excluir` (na prática `/contas/excluir` cai no roteador como `$parts[1]='excluir'**) | `modulos/contas/excluir.php` **não possui rota** | — |
| `/mensalidades` | `modulos/mensalidades/listar.php` | `index.php:1061-1063` |
| `/mensalidade/novo` | `modulos/mensalidades/cadastrar.php` | `index.php:1064-1066` |
| `/mensalidade/gerar` | `modulos/mensalidades/gerar.php` | `index.php:1067-1069` |
| `/mensalidade/{id}/baixa` | `modulos/mensalidades/baixa.php` | `index.php:1070-1072` |
| `/mensalidade/{id}/estornar` | `modulos/mensalidades/estornar.php` | `index.php:1073-1075` |
| `/mensalidade/{id}/excluir` | `modulos/mensalidades/excluir.php` | `index.php:1076-1078` |
| `/planos` | `modulos/mensalidades/planos.php` | `index.php:1079-1081` |
| `/plano/novo` | `modulos/mensalidades/plano_cadastrar.php` | `index.php:1082-1084` |
| `/plano/{id}/editar` | `modulos/mensalidades/plano_editar.php` | `index.php:1085-1087` |
| `/plano/{id}/excluir` | `modulos/mensalidades/plano_excluir.php` | `index.php:1088-1090` |
| `/comunicacao` | `modulos/comunicacao/index.php` | `index.php:1166-1168` |
| `/fornecedor` · `/fornecedor/novo` · `/fornecedor/{id}/editar` | `modulos/fornecedor/{listar,cadastrar,editar}.php` | `index.php:739-751` |
| `/agroindustria/minhas` · `/minha/nova` · `/minha/{id}/editar` | `minhas_*.php` | `index.php:920-927` |
| `/agroindustria/setores` · `/setor/novo` · `/setor/{id}/editar` | `setores_*.php` | `index.php:929-936` |
| `/agroindustria/categorias` · `/categoria/novo` · `/categoria/{id}/editar` | `categorias_*.php` | `index.php:938-945` |
| `/agroindustria/atividades` · `/atividade/novo` · `/atividade/{id}/editar` | `atividades_*.php` | `index.php:947-954` |
| `/agroindustria/ocorrencias` · `/ocorrencia/novo` · `/ocorrencia/{id}/editar` | `ocorrencias_*.php` | `index.php:956-963` |
| `/agroindustria/receitas` · `/receitas/nova` · `/receitas/{id}/editar` | `receitas_*.php` | `index.php:965-972` |
| `/agroindustria/higienizacao` · `/higienizacao/nova` | `higienizacao_*.php` | `index.php:974-978` |
| `/agroindustria/ordens` · `/ordens/nova` · `/ordens/{id}` | `ordens_{listar,cadastrar,visualizar}.php` | `index.php:980-988` |
| `/agroindustria/rastreabilidade` | `agroindustria/rastreabilidade.php` (**pública**) | `index.php:989-991` |
| `/producao-animal` (painel) | `producao_animal/painel.php` | `index.php:994-996` |
| `/producao-animal/lotes` · `/lote/novo` · `/lote/{id}/editar` | `lotes_*.php` | `index.php:997-1005` |
| `/producao-animal/alimentacao` · `/alimentacao/novo` · `/alimentacao/{id}/editar` | `alimentacao_*.php` | `index.php:1006-1014` |
| `/producao-animal/sanidade` · `/sanidade/novo` · `/sanidade/{id}/editar` | `sanidade_*.php` | `index.php:1015-1023` |
| `/producao-animal/reproducao` · `/reproducao/novo` · `/reproducao/{id}/editar` | `reproducao_*.php` | `index.php:1024-1032` |
| `/producao-animal/ambientes` · `/ambiente/novo` · `/ambiente/{id}/editar` | `ambientes_*.php` | `index.php:1033-1041` |
| `/producao-animal/rastreabilidade?uuid=|?lote=` | `producao_animal/rastreabilidade.php` (**pública**) | `index.php:1042-1044` |
| `/manejo-animal` · `/novo` · `/{id}/editar` · `/{id}` | `manejo_animal/animais_*.php` | `index.php:1047-1058` |

Observações de roteamento:
- A rota `/contas/excluir` **não existe** em `index.php` (não há `conta/{id}/excluir`) → a tela `modulos/contas/excluir.php` é inalcançável por URL amigável; além disso ela só foi desenhada para POST com `id` no corpo (`modulos/contas/excluir.php:12`) e faz `header('Location: listar.php?msg=excluido')` (caminho **relativo**, `modulos/contas/excluir.php:23`) → resolveria para `/app/listar.php` (404).
- `/comunicacao` não tem subrotas; os links WhatsApp são obtidos por AJAX em `/api/comunicacao/whatsapp_links.php`.

---
