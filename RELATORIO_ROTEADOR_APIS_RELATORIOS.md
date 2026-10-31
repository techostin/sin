# Relatório Técnico — Roteador, APIs e Relatórios (SINPORG / sisagro)

Workspace: `C:\xampp\htdocs\app`
Base analisada (leitura integral): `index.php` (1497 linhas), `login.php` (998), os 56 `.php` de `api/` (22 em `api/relatorios/`), 17 `.php` de `relatorios/` + 9 templates, `relatorios.php` (1138), `relatorios/index.php` (755), `config/relatorios_config.php`, `classes/{Relatorio,ReportManager,ReportConformidade,User,ApiAuth}.php`, `.htaccess`.
Convenção de citação: `arquivo:linha`. Nada foi inferido sem leitura do código.

---

## 0. Contexto de execução e reescrita de URL

`.htaccess` (raiz):

| Linha | Regra | Efeito |
|---|---|---|
| 1-6 | `Options +FollowSymLinks -MultiViews -Indexes`, `RewriteBase /app/` | app vive em `/app`; listagem de diretório desligada |
| 9-10 | `RewriteCond %{REQUEST_URI} ^/app/relatorios/([a-z-]+)\.php$ → RewriteRule ^ - [L]` | **acesso direto** a `/app/relatorios/*.php` é permitido (pula o roteador) |
| 14 | `RewriteRule ^relatorios/([a-z-]+)/(pdf\|excel)$ relatorios/$1.php?tipo=$2` | `/app/relatorios/associados/pdf` → `relatorios/associados.php?tipo=pdf` |
| 17-24 | `!-f && !-d && !-l → index.php?url=$1 [QSA,L]` | todo o resto entra no roteador amigável |
| 47-65 | `FilesMatch` negando `.sql,.log,.env,.md` etc. e `check_*|run_*|debug_*|fix_*|test_*|seed_*|schema|_tmp_*|...` | mitiga o lixo de diagnóstico na raiz |

**Consequência arquitetural:** há **três caminhos de entrada** para o mesmo recurso:
1. roteador amigável (`index.php?url=...`, regras `$parts[0] === ...`);
2. arquivo `.php` acessado **diretamente** (existe em disco ⇒ o `RewriteCond !-f` não reescreve);
3. diretório com `DirectoryIndex` (`/app/relatorios/` → `relatorios/index.php`, **página diferente** de `/app/relatorios` → `relatorios.php`).

---

## 1. MAPA COMPLETO DE ROTAS

Autenticação: **A**=Admin(1) · **G**=Gestor(2) · **U**=usuário logado(3) · **PÚB**=sem login · **LOGIN**=apenas `isLoggedIn()` · **HDR**=garantido por `includes/header.php:16` (redireciona a `/login`).

### 1.1 Roteador amigável — `index.php`

#### Públicas / autenticação

| URL pattern | Arquivo | Método | Auth | Parâmetros |
|---|---|---|---|---|
| `/login` | `login.php` (index.php:11-14) | GET/POST | PÚB | POST `etapa,email,senha,orgao,especialidade,setor` |
| `/recuperar-senha` | `recuperar_senha.php` (19-22) | GET | PÚB | — |
| `/redefinir-senha/{token}` | `redefinir_senha.php` (23-27) | GET/POST | PÚB (token) | `token` (parts[1] → `$_GET['token']`); POST `senha,confirma` |
| `/logout` | `logout.php` (836-839) | GET | LOGIN | — |
| `/completar-perfil` | `completar_perfil.php` (842-845) | GET/POST | LOGIN | POST `orgao,setor,especialidade,nicho,tipo_usuario` |
| `/carteirinha/{token}` | `carteirinha_publica.php` (664-668) | GET | PÚB | `token` ← parts[1] |
| `/` (raiz, sem `url`) | `index.php` (1183-1497) | GET | PÚB* | se logado redireciona (1186-1190) |

> `/rastreabilidade` **não tem rota** (§6.7): a página pública `rastreabilidade.php` só é alcançável em `/rastreabilidade.php` direto; o `<form action>` de `rastreabilidade.php:205` aponta para a URL inexistente → 404.

#### Relatórios (páginas)

| URL pattern | Arquivo | Auth | Observação |
|---|---|---|---|
| `/relatorios` | `relatorios.php` (32-35) | U (17-21) | dashboard/matriz de categorias |
| `/relatorios/{categoria}/{relatorio}` | `relatorios.php` (38-44); `$_GET['relatorio']=parts[2]` | U + nível por relatório (202) | **`parts[1]` (categoria) é ignorado** |
| `/relatorios/{slug}` (2 níveis) | 404 do roteador | — | só funciona com 3 segmentos |
| `/relatorios-admin` | `modulos/caderno_campo/relatorios_admin.php` (499-502) | HDR | — |
| `/relatorios-conformidade` | `modulos/conformidade/relatorios.php` (1158-1160) | U (7-11) | 9 relatórios (ReportConformidade) |
| `/relatorios-conformidade/gerar` | `modulos/conformidade/relatorios/gerar.php` (1161-1163) | U (22-25) + filtro por associado (50-52) | `relatorio,formato(=pdf\|excel\|html),data_inicio,data_fim,status,tipo,associado_id,acao,user_id` |
| `/relatorio-associados` | `modulos/relatorios/relatorio_associados.php` (514-517) | **NENHUMA** | PDF mPDF de todos os associados — §6.1 |
| `/relatorio/geral` | `modulos/caderno_campo/relatorios/relatorio_geral.php` (518-521) | HDR | — |
| `/caderno-campo/relatorios` | `modulos/caderno_campo/relatorios_tabs.php` (503-506) | HDR | |
| `/caderno-campo/relatorios/exportar` | `.../relatorios/exportar_relatorio.php` (508-511) | HDR + guard no destino | `tipo=pdf\|excel&relatorio=atividades\|insumos\|producao\|lotes\|produtores\|propriedades\|ocorrencias\|propriedade\|geral` |
| `/caderno-campo/relatorios/{geral\|atividades\|insumos\|producao\|propriedade\|lotes}` | arquivos homônimos (522-546) | `$_SESSION['user_id']` | |
| `/relatorios_diretoria/{ativos_municipio\|inadimplentes_micro\|entradas_saidas}` | `modulos/relatorios_diretoria/*` (1147-1155) | HDR | |
| `/api/relatorios/{slug}` | `api/relatorios/{slug}.php` (47-58) | variável (o arquivo decide) | **include dinâmico sem whitelist** (§6.2) |

#### APIs dentro do roteador (regras explícitas)

| URL pattern | Arquivo | Auth |
|---|---|---|
| `/api/relatorios/{slug}` | `api/relatorios/{slug}.php` (47-58) | do arquivo |
| `/api/certificacao/controller.php` (literal) | `api/certificacao/controller.php` (63-66) | LOGIN + A/G por método |
| `/api/plano-manejo[...]` | `api/plano_manejo.php` (69-72) | LOGIN + CSRF em escrita |
| `/api/cidades` | `api/get_cidades.php` (850-853) | **PÚB** |
| `/api/ocorrencias` | `api/ocorrencias.php` (856-859) | LOGIN + CSRF escrita |

> APIs **não roteadas** (só por acesso direto ao `.php`): `api/certificadoras.php`, `api/certificadoras/controller.php`, `api/certificacao/gerar_pdf.php`, `api/ficha_adesao.php`, `api/insumos_aprovados.php`, `api/nao_conformidades.php`, `api/pre_cadastro.php`, `api/recuperar_senha.php`, `api/relatorios_admin.php`, `api/relatorios_controller.php`, `api/roteiros_inspecao.php`, `api/toggle_presenca.php`, `api/get_*`, `api/associados/listar.php`, `api/comunicacao/whatsapp_links.php`, `api/regionalizacao/*`, `api/pix/webhook/*`, `api/legacy/session-bridge.php`.

#### Dashboards (index.php:862-903)

| URL | Arquivo | Auth |
|---|---|---|
| `/dashboard/diretoria` | `modulos/dashboard/dashboard_diretoria.php` (864-867, **antes** de qualquer checagem) | **nenhuma checagem no roteador** |
| `/dashboard/admin` | `dashboard_admin.php` | LOGIN + coerção de perfil (873-892) |
| `/dashboard/gestor` | `dashboard_gestor.php` | idem |
| `/dashboard/agricultor` | `caderno_campo/dashboard_agricultor.php` | idem |
| `/dashboard/agroindustria` | `dashboard_agroindustria.php` | idem + `temAgroindustria()` (879-887) |
| `/dashboard/{outro}` | — | cai no fim e segue para a home (893-903) |

#### Portal do associado (906-917)

`/portal/login` → `modulos/portal/login.php` (PÚB) · `/portal/area` → `modulos/portal/area.php` · `/portal/logout` → `modulos/portal/logout.php`.

#### Domínios funcionais (HDR; o arquivo decide o nível)

| Domínio | Rotas (linhas index.php) |
|---|---|
| Certificação | `/certificacoes`(75), `/insumos-aprovados`(79), `/certificacao/listar`(83), `/certificacao/novo`(89), `/certificacao/{id}/editar`(95), `/certificacao/{id}/renovar`(102), `/certificacao/historico/{id}`(109), `/certificacao/{id}/certificado`(116), `/certificacao/certificado-preview`(123) |
| Plano de manejo | `/planos-manejo`(129), `/plano-manejo/novo`(133), `/plano-manejo/{id\|uuid}/{visualizar\|editar\|aprovar\|upload_croqui}`(137-156) |
| Caderno de campo | `/caderno-campo/dashboard`(161), `/caderno-campo/dashboard-agricultor`(167), `/propriedades`(173), `/caderno-campo/propriedades`(177), `/propriedade/novo`(181), `/caderno-campo/propriedade/novo`(185), `/propriedade/{id}/editar`(189), `/caderno-campo/propriedade/{id}/{editar\|imagens}`(194-203), `/lavouras`(206), `/caderno-campo/lavouras`(210), `/lavoura/novo`(214), `/lavoura/{id\|uuid}/editar`(222-246), `/insumos`(249), `/insumo/novo`(257), `/insumo/{id\|uuid}/editar`(265-289), `/produtos`(292), `/produto/novo`(300), `/produto/{id\|uuid}/editar`(308-332), `/lotes`(335), `/lote/novo`(343), `/lote/{id\|uuid}[/editar\|/rastrear]`(367-402), `/caderno-campo/ocorrencias`(352), `/caderno-campo/ocorrencia/novo`(356), `/caderno-campo/ocorrencia/{id}/editar`(360), `/categorias-atividades`(405), `/categoria-atividade/novo`(413), `/categoria-atividade/{id}/editar`(421-430), `/atividades-diarias`(433), `/atividade-diaria/novo`(441), `/atividade-diaria/{id\|uuid}/editar`(449-459), `/caderno-campo/atividade-diaria/{id\|uuid}/{editar\|imagens}`(461-480), `/caderno-campo/sincronizacao`(483) |
| Visitas/inspeções | `/visitas-tecnicas`(551), `/visita-tecnica/agendar`(555), `/visita-tecnica/{id\|hashid}/realizar`(561-572), `/visita-tecnica/{id\|hashid}/relatorio`(575-586), `/roteiros-inspecao`(589), `/roteiro-inspecao/novo`(593) |
| Não conformidades | `/nao-conformidades`(599), `/nao-conformidade/acompanhar`(603), `/nao-conformidade/{id}`(607) |
| Associados | `/associados`(615), `/associados/pendentes`(619), `/cadastros-pendentes`(624), `/fichas-completas`(629), `/cadastro-ficha/visualizar`(634), `/associados/carteirinhas`(639), `/associado/{encodeId(id)}/carteirinha-{gerar\|impressa\|pdf\|enviar}`(643-662), `/associados/relatorio`(669), `/associado/novo`(673), `/meu-perfil`(679), `/associado/{id}/editar`(683), `/associado/editar?id=`(689), `/associado/{id}/excluir`(695) |
| Associações | `/associacoes`(702), `/associacao/novo`(706), `/associacao/editar`(710, aceita `POST['id']`), `/associacao/associados`(718) |
| Usuários | `/usuarios`(724), `/usuario/novo`(728), `/usuario/{id}/editar`(732), `/usuario/perfil`(818), `/usuario/configuracoes`(824), `/usuario/salvar_notificacoes`(830) |
| Fornecedores | `/fornecedor`(739), `/fornecedor/novo`(743), `/fornecedor/{id}/editar`(747) |
| Eventos | `/eventos`(754), `/eventos/presencas`(758), `/evento/presencas`(763), `/evento/novo`(767), `/evento/{id}`(771), `/evento/{id}/editar`(776) |
| Financeiro | `/contas`(801), `/conta/novo`(805), `/conta/{id}/editar`(809), `/caixa`(1093), `/caixa/lancamento`(1096), `/caixa/cobranca`(1099), `/caixa/relatorio`(1102), `/caixa/{id}/excluir`(1105) |
| Mensalidades | `/mensalidades`(1061), `/mensalidade/novo`(1064), `/mensalidade/gerar`(1067), `/mensalidade/{id}/{baixa\|estornar\|excluir}`(1070-1078), `/planos`(1079), `/plano/novo`(1082), `/plano/{id}/{editar\|excluir}`(1085-1090) |
| Assembleia | `/assembleia`(1110), `/assembleia/novo`(1113), `/assembleia/{id}/{editar\|encerrar\|pautas\|publicar\|resultado}`(1116-1130), `/assembleia/{id}`(1131) |
| Agroindústria | `/agroindustria/minhas`(920), `/agroindustria/minha/nova`(923), `/agroindustria/minha/{id}/editar`(926), `/agroindustria/setores`(929), `/agroindustria/setor/novo`(932), `/agroindustria/setor/{id}/editar`(935), `/agroindustria/categorias`(938), `/agroindustria/categoria/novo`(941), `/agroindustria/categoria/{id}/editar`(944), `/agroindustria/atividades`(947), `/agroindustria/atividade/novo`(950), `/agroindustria/atividade/{id}/editar`(953), `/agroindustria/ocorrencias`(956), `/agroindustria/ocorrencia/novo`(959), `/agroindustria/ocorrencia/{id}/editar`(962), `/agroindustria/receitas`(965), `/agroindustria/receitas/nova`(968), `/agroindustria/receitas/{id}/editar`(971), `/agroindustria/higienizacao`(974), `/agroindustria/higienizacao/nova`(977), `/agroindustria/ordens`(980), `/agroindustria/ordens/nova`(983), `/agroindustria/ordens/{id}`(986), `/agroindustria/rastreabilidade`(989) |
| Produção animal | `/producao-animal`(994), `/producao-animal/{lotes\|alimentacao\|sanidade\|reproducao\|ambientes}`(997-1035), `/producao-animal/{lote\|alimentacao\|sanidade\|reproducao\|ambiente}/novo`(1000-1037), `/producao-animal/{seg}/{id}/editar`(1003-1040), `/producao-animal/rastreabilidade`(1042) |
| Manejo animal | `/manejo-animal`(1047), `/manejo-animal/novo`(1050), `/manejo-animal/{id}/editar`(1053), `/manejo-animal/{id}`(1056) |
| Regionalização | `/regionalizacao`(1136), `/regionalizacao/mapa`(1139), `/admin/clusters`(1142) |
| Comunicação / CNPO | `/comunicacao`(1166), `/consulta-cnpo`(1171) |
| **Lixo** | `/teste`(783), `/teste-db`(789), `/teste-ajax`(795) — arquivos **inexistentes** na raiz (estão em `recicla/`) → `require` de arquivo ausente = **fatal** |
| Fallback | `/404.php` (1176-1177) — PÚB no roteador, mas o próprio `404.php:3` inclui o header → anônimo é mandado para /login |

### 1.2 Rotas colidentes / mortas

| Rota declarada | Linha | Situação |
|---|---|---|
| `/relatorios` && !isset(parts[1]) | 495-498 | **código morto** — a linha 32 já resolveu e deu `exit` |
| `/caderno-campo/insumos/validacao` | 489-492 | **inalcançável** — a linha 253 (`$parts[1]==='insumos'` sem checar `$parts[2]`) já retornou `listar.php` |
| `/relatorios/{categoria}/{relatorio}` | 38-44 | `parts[1]` descartado: `/relatorios/qualquercoisa/lista-associados` funciona igual ao canônico |

---

## 2. CONTRATO DE CADA ENDPOINT DE API

### 2.1 `api/` (raiz da pasta)

| Arquivo | Método | Entrada | Saída | Erros | Auth |
|---|---|---|---|---|---|
| `get_cidades.php` | GET | `?todos=1` ou `?estado_id=int` | `[{id,nome,estado(_id),estado_sigla}]` | 500 `{error}` | **NENHUMA** (2-46) |
| `get_estado_cidade.php` | GET | `?cidade_id=int` | `{id,nome,sigla}` ou `null` | 500 | **NENHUMA** (1-31) |
| `get_associacao_detalhes.php` | GET | `?id=int` | `{success:true,data:{...}}` | 400/404/500 | `ApiAuth::exigirLogin()` (8) |
| `get_propriedades_associado.php` | GET | `?associado_id` | `[{id,nome}]` | 500 | `exigirLogin`(9)+`exigirProprioAssociado`(19) |
| `get_propriedade_detalhes.php` | GET | `?id=int` | `{propriedade,estatisticas,lavouras,atividades_recentes}` | 400/404/500 | `exigirLogin`(9)+`exigirProprioAssociado`(59) |
| `get_eventos_simples.php` | GET | — | `{success,eventos[],total,user_id}` | `{success:false,message}` (sem HTTP code) | `$_SESSION['user_id']` (14) |
| `get_eventos_pendentes.php` | GET/OPTIONS | — | `{success,eventos[],debug:{session_id,user_id,server{...}}}` | no erro devolve `$_SESSION`+`$_SERVER`+trace | `$_SESSION['user_id']` (74) |
| `get_eventos_pendentes_new.php` | GET | — | idem + `session_data => $_SESSION` inteiro | idem | `$_SESSION['user_id']` (14) |
| `get_presenca_evento.php` | GET (AJAX) | `?evento_id=int` + header `X-Requested-With` | `{success,evento,confirmados,nomes_confirmados,nao_confirmados_associados,nao_confirmados_usuarios,nao_confirmados_gestores}` | 400/403/404/405/500 | `exigirLogin` (9) |
| `limpar_modal_eventos.php` | POST/GET | — | `{success:true}` | — | `exigirLogin` (8) |
| `toggle_presenca.php` | POST (AJAX) | `evento_id` | `{success,acao:'adicionado'\|'removido',mensagem}` | 400/401/403/404/405/500 | LOGIN(21)+header AJAX(8)+CSRF(29) |
| `ocorrencias.php` | GET/PATCH | PATCH JSON `{id,status,correcoes_realizadas}` | `{success,data}` / `{success,message}` | 400/401/403/404/405/500 | LOGIN(17)+CSRF escrita(29); GET filtra por associado p/ nível>2 (40-46) |
| `nao_conformidades.php` | GET/PUT/POST | `?action=list\|detail&id&status&gravidade`; PUT JSON; POST multipart `action=upload_evidencia` | `{success,data,kpis,is_admin}` / `{success,data}` / `{success,filepath}` | 400/401/403/404/405/500 | LOGIN(8)+CSRF(20); filtro `vt.associado_id` p/ não-gestor (70-73) |
| `plano_manejo.php` | GET/POST/PUT/PATCH/DELETE | JSON; `?id` no GET/DELETE | `{success,data}`, `{success,id,message}`, `{success,status}` | 400/401/403/404/405/500 | LOGIN(15)+CSRF escrita(27); PATCH exige G(363); **PUT/DELETE sem checagem de dono** (§6.3) |
| `roteiros_inspecao.php` | GET/POST/PUT/DELETE | `?action=templates\|visitas\|nao_conformidades\|visitas_ciente_pendentes\|visitas_recusadas_pendentes\|visitas_agendadas_status\|relatorio\|upload_evidencia\|confirmar_ciente\|recusar_ciente\|reagendar_visita\|gestor_ciente` | `{success,data[,user_nivel]}` / PDF | 400/401/403/404/405/500/501 | LOGIN(27)+CSRF escrita(40); autorizações por ação (§2.4) |
| `relatorios_controller.php` | POST | **JSON** `{tipo,formato,data_inicio,data_fim,status,categoria_id,tipo,usuario_id,propriedade_id,colunas,ordenacao}` | binário PDF/XLSX com `Content-Disposition` | 400/401/405 | LOGIN(24); tipos `atividades,propriedades,insumos,financeiro,personalizado` (51) |
| `relatorios_admin.php` | GET | `?relatorio&formato(=pdf\|excel)&data_inicio&data_fim&status&tipo&cidade&tipo_fornecedor` | binário PDF(mPDF)/XLSX | 400/401/500 | LOGIN+`hasPermission(NIVEL_GESTOR)` (22) |
| `certificadoras.php` | GET/POST/PUT/DELETE | JSON ou multipart (`logo`, `_method=PUT`) | `{data:[]}` / `{success,id}` / `{error}` | 400/401/403/404/405/500 | LOGIN(14)+CSRF escrita(26)+G(93,122,151,182) |
| `certificadoras/controller.php` | GET/POST/DELETE | multipart `logo_arquivo` | `{data}` / `{success,message}` | 401/403/404/500 | LOGIN(14); POST/DELETE só `hasPermission(NIVEL_ADMIN)` (40,72) — **sem CSRF** |
| `certificacao/controller.php` | GET/POST/PUT/DELETE | JSON; GET `?id&action=produtos&associado_id&validade&page&limit&tipo_certificacao&status`; POST multipart `arquivo_assinatura` | `{data,total,page,limit,pages}` / `{success,id\|message}` / `{error,message}` | 400/401/403/404/405/500 | LOGIN(24)+CSRF(37)+G p/ POST/PUT/DELETE(51,65,74); GET nível U restrito (97,144,174) |
| `certificacao/gerar_pdf.php` | POST | JSON ou form `associado_id,numero_certificado,escopo,data_emissao,data_validade,produtos[]` | `{success,documento_url,declaracao_compromisso_url}` | `{success:false,error}` | `exigirLogin`(14)+`exigirProprioAssociado`(29) — **sem CSRF** |
| `ficha_adesao.php` | GET/POST | `?action=status\|salvar\|submeter\|aprovar\|recusar`; POST form-encoded | `{success,mostrar,status,ficha,produtos,usuario}` | 400/401/403/405/422 | GET `status` responde `{mostrar:false}` a anônimo (85-87); POST exige CSRF(138); aprovar/recusar exige `podeAcessarDiretoria()` (195) |
| `pre_cadastro.php` | POST | form `nome,sobrenome,email,senha,confirma_senha,perfil\|tipo_usuario,area_producao[]` | `{success,message}` | 405 / 200 `{success:false,error}` | **PÚB** (autocadastro) |
| `recuperar_senha.php` | POST | form `email` | `{success,message}` / `{success:false,error}` | 405/500 | **PÚB** (resposta genérica anti-enumeração, 24-39) |
| `insumos_aprovados.php` | GET | `?categoria&q&permitido` | `{success,data,total}` | 401/405/500 | LOGIN(23) |
| `cnpo_consulta.php` | GET | `?termo(≥2)&uf&cidade&tipo_entidade` | `{data,total}` | 400/401/403/405/500 | LOGIN(22)+`nivel<=NIVEL_GESTOR` (29) |
| `associados/listar.php` | GET | — | `{status,message,data:[{id,nome,email,telefone,status,cidade,uf,nome_associacao}]}` | 401/500; CORS `*` (7) | LOGIN(26) — **duplicado em 24-40** e **sem filtro de nível** (§6.3) |
| `comunicacao/whatsapp_links.php` | POST | JSON `{cluster}` | `{sucesso,links{nome:url}}` | 200 `{sucesso:false,erro}` | `exigirStaff`(14)+CSRF(16) |
| `regionalizacao/dados.php` | GET | — | `{success,data,total_microrregioes,timestamp}` | 500 | `exigirLogin`(13) |
| `regionalizacao/microrregioes.php` | GET | `?mesorregiao_id` | `{sucesso,dados}` / `{sucesso:false,erro}` | HTTP 200 em ambos | `exigirLogin`(13) |
| `legacy/session-bridge.php` | GET | — | `{accessToken,expiresIn,origem}` | 401/500 JSON | sessão PHP (71-109); JWT HS256 do `sinporg/.env` (58-66) |
| `pix/webhook/{asaas\|gerencianet\|mercadopago}.php` | POST | JSON do PSP | `$model->processarWebhookPix()` | 400 `{erro}` | **NENHUMA** — `ROTAS_PIX_WEBHOOK_PERMITIDAS` (`includes/rota_segura.php:57-63`) não é usada; sem assinatura (§6.5) |

### 2.2 `api/relatorios/` (22 arquivos)

| Arquivo | Auth | Entrada | Saída |
|---|---|---|---|
| `bootstrap.php` | — (include) | — | `ROOT_PATH`(9), autoload(23-28), `verificarAutenticacao()`(31-37) |
| `_config.php` | — | — | **arquivo morto**: credenciais hardcoded (3-6), `SITE_URL` via `HTTP_HOST` (9); nenhum `require` o usa |
| `gerar.php` | `verificarAutenticacao()`(6) | `?tipo&formato(=I\|D)&data_inicio&data_fim&status&categoria_id&vinculo` | PDF via `ReportManager::gerarRelatorioPDF`; 400/500 |
| `rapido.php` | LOGIN(18) | `?tipo=hoje\|pendentes\|estoque-minimo` | `{sucesso,dados:{titulo,colunas,dados},mensagem}`; CORS `*`(7) |
| `visao-geral.php` | `exigirNivel(1,false)`(12) | — | HTML de cards |
| `atividades-diarias.php` | `exigirNivel(3,false)`(12)+IDOR filter(22-25) | `?dataInicio&dataFim` | HTML tabela `#reportTable` |
| `produtividade.php` | `exigirNivel(3,false)`(12)+filtro(22-25) | `?dataInicio&dataFim` | HTML |
| `estoque-atual.php` | `exigirNivel(3,false)`(12)+filtro(25-28) | — | HTML |
| `movimentacoes.php` | `exigirNivel(2,false)`(12) | `?dataInicio&dataFim` | HTML |
| `fluxo-caixa.php` | `exigirNivel(2,false)`(12) | `?dataInicio&dataFim` | HTML |
| `contas-pagar.php` | `exigirNivel(2,false)`(12) | `?dataInicio&dataFim` | HTML (tipo='Despesa') |
| `contas-receber.php` | `exigirNivel(2,false)`(12) | `?dataInicio&dataFim` | HTML (tipo='Receita') |
| `lista-associados.php` | `exigirStaff(false)`(11) | `?dataInicio&dataFim&associado` | HTML (CPF/RG/telefone) |
| `status-associados.php` | `exigirStaff(false)`(11) | `?associado` | HTML + inadimplência |
| `inadimplencia.php` | `exigirStaff(false)`(12) | `?associacao&filtro_associado_id` | HTML + `Pagamento::getInadimplenciaPorMes` |
| `pagamentos-por-forma.php` | `exigirNivel(2,false)`(13) | `?associacao&filtro_associado_id&dataInicio&dataFim` | HTML |
| `previsao-receita.php` | `exigirNivel(2,false)`(13) | `?filtro_associado_id` (lido e **não usado**, 16) | HTML |
| `fluxo-caixa-realizado-previsto.php` | `exigirNivel(2,false)`(14) | `?associacao&filtro_associado_id` | HTML |
| `admin_export.php` | LOGIN+`hasPermission(NIVEL_GESTOR)`(29) | `?relatorio=usuarios\|associados\|associacoes\|fornecedores\|contas&tipo=pdf\|excel\|html&...` | XLSX / PDF / HTML |
| `relatorio_associados.php` | `$_SESSION`+`in_array($_SESSION['user_nivel'],[1,2],true)` (11-17) | POST `nome_associacao,nome_responsavel,formato` | PDF/XLS (mPDF) |
| `relatorio_atividades_usuario.php` | LOGIN(13,31); filtro por nível (46-63) | `?formato=pdf\|excel` | PDF (TCPDF) / XLSX |
| `relatorio_atividades_usuario_temp.php` | variante temporária de PDF | — | PDF |

### 2.3 Guarda central `classes/ApiAuth.php`

`nivel()` (50-68) aceita sessão principal (`user_id`) **ou** sessão do Portal (`associado_id` → nível 3); `exigirLogin`(133), `exigirStaff`(145), `exigirNivel`(157), `exigirProprioAssociado`(170 — libera staff **e** Diretoria Executiva), `negar`(186). Semântica: 1=Admin, 2=Gestor, 3=Usuário (menor = mais poder).

### 2.4 Autorizações por ação em `api/roteiros_inspecao.php`

| Ação | Regra | Linha |
|---|---|---|
| `templates` (POST criar/salvar) | **só LOGIN** | 370-378 |
| `templates` (DELETE) | **só LOGIN** | 717-730 |
| `nao_conformidades` (GET) | **só LOGIN, sem filtro de dono** | 326-349 |
| `relatorio` (GET, PDF) | LOGIN, `id` arbitrário (IDOR) | 92-111 |
| `upload_evidencia` (POST) | LOGIN+CSRF | 385-408 |
| `confirmar_ciente`/`recusar_ciente` | `user_table==='associados'` e `associado_id=user_id` | 464-553 |
| `reagendar_visita`/`gestor_ciente` | G (nivel≤2) ou técnico da visita | 560-698 |
| `visitas_ciente_pendentes` | agricultor | 177-208 |
| `visitas_recusadas_pendentes`/`visitas_agendadas_status` | G | 214-324 |
| `visitas` (PUT finalizar) | staff/diretoria ou dono/técnico | 737-760 |
| `visitas` (GET lista) | nível 3 filtra por `associado_id`/`tecnico_responsavel_id` | 131-141 |

---

## 3. SISTEMA DE RELATÓRIOS

### 3.1 Inventário (contagem real)

- `relatorios/`: **17 PHP** na raiz + **9 templates** = **26 arquivos** (a tarefa citava 33).
  Raiz: `associados.php, atividades.php, dashboard.php, eventos.php, financeiro.php, form_atividades.php, form_financeiro.php, form_insumos.php, form_personalizado.php, form_propriedades.php, gerar_certificado.php, gerar_pdf.php, index.php, insumos.php, lavouras.php, produtores.php, propriedades.php`.
  Templates: `relatorio_{associados,atividades,eventos,financeiro,insumos,lavouras,produtores,propriedades,usuarios}.php`.
- `relatorios.php` (raiz, 1138 linhas) = central **efetivamente roteada** em `/relatorios`.
- `api/relatorios/`: **22 PHP** (19 endpoints + `bootstrap.php` + `_config.php` + `relatorio_atividades_usuario_temp.php`).
- `config/relatorios_config.php`: **nunca é incluído por ninguém** (grep global só acha a auto-referência na linha 303) → **configuração morta**.

### 3.2 `ReportManager` (`classes/ReportManager.php`, 710 linhas)

Construtor (24-42): exige sessão (lança `Exception` se não logado, 40); define `user_id/nivel/tipo/table` e `user_associado_id` (36-38).

**Métodos de dados** (`get<X>Report`):

| Método | Linha | Filtro de segurança |
|---|---|---|
| `getAtividadesReport` | 161 | `a.associado_id` se nível>2 (166-169); `data_inicio/data_fim` |
| `getInsumosReport` | 201 | `i.associado_id` (206-209); `fornecedor_id` |
| `getLotesReport` | 233 | via `prop.associado_id`; `produto_id` |
| `getProducaoReport` | 268 | idem + `data_inicio/fim` |
| `getPropriedadesReport` | 308 | `p.associado_id`; `cidade` |
| `getUsuariosReport` | 342 | retorna `[]` se nível>2 (344); `tipo/status` |
| `getAssociadosReport` | 378 | próprio registro se nível>2 (383); `status/cidade` |
| `getAssociacoesReport` | 417 | `[]` se nível>2 |
| `getFornecedoresReport` | 441 | `[]` se nível>2; `cidade/tipo_fornecedor` |
| `getContasReport` | 479 | `[]` se nível>2; `tipo/data_inicio/data_fim` |
| `getFinanceiroReport` | 522 | filtro de período **comentado** (536-547) |
| `getLavourasReport` | 562 | `p.associado_id` |
| `getOcorrenciasReport` | 590 | `om.associado_id`; tipo/severidade/status/propriedade/data |
| `getProdutoresReport` | 649 | alias de `getAssociadosReport` |
| `getResumoGeral` | 656 | KPIs do dashboard |

**Métodos que NÃO existem** (mas são chamados): `getEventosReport` (`relatorios/eventos.php:60`) e `buscarDadosPersonalizado`/`buscarDadosFinanceiro`.

**Geração PDF** (`gerarRelatorioPDF`, 65-156): valida método (68-71) → `$templateFile = $this->templatesDir . 'relatorio_'.$tipo.'.php'` (77) → `ob_start` + `extract(['dados','filtros','assetsUrl','logoPath','titulo'])` (83-92) → `include` (95) → mPDF A4-L (101-110) com header/footer (113-121) → `Output($filepath,'F')` (131) → formato `I` (134-137), `D` (140-146) ou caminho (149). Exceção → `error_log` + `return false` (151-155).

**Bug estrutural (bloqueante):** `$templatesDir = __DIR__ . '/../../relatorios/templates/'` (linha 17). Com `__DIR__ = C:\xampp\htdocs\app\classes`, resolve para `C:\xampp\htdocs\relatorios\templates\${B} — **fora da aplicação** (o correto é `__DIR__ . '/../relatorios/templates/'`). Consequência: **todo PDF de `ReportManager` falha** com "Template do relatório não encontrado" → `return false` → página em branco (`relatorios/{atividades,insumos,propriedades,produtores,lavouras,eventos}.php`, `api/relatorios/gerar.php`).

**Segundo bug (após corrigir o caminho):** os templates usam variáveis que o `extract` **não fornece**:
- `$colunas` — `relatorio_propriedades.php:267-275`, `relatorio_produtores.php:225-234`, `relatorio_lavouras.php:213-220`, `relatorio_financeiro.php:286-294` → `in_array('id', null)` = `TypeError` (PHP 8) → fatal;
- coleções com nomes divergentes: `$propriedades` (280), `$produtores` (244), `$lavouras` (227), `$lancamentos` (299) — o `extract` só entrega `$dados`;
- `relatorio_usuarios.php:32`, `relatorio_atividades.php`, `relatorio_insumos.php`, `relatorio_associados.php` usam `$dados` (compatíveis).

### 3.3 `classes/Relatorio.php` (400 linhas)

`setPeriodo`(26), `setFiltros`(35), `gerarPdf`(43), `gerarExcel`(51), `buscarDados` → `buscarDados<Ucfirst(tipo)>` (59-65).

Implementados: `buscarDadosAtividades`(70), `buscarDadosPropriedades`(116), `buscarDadosInsumos`(147), `buscarDadosAssociados`(176). **Faltam `buscarDadosFinanceiro` e `buscarDadosPersonalizado`**, ambos aceitos em `api/relatorios_controller.php:51` → `throw new Exception("Tipo de relatório não suportado")` (64) → HTTP 500.

Filtro por nível: `if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] > 2)` (100, 131, 160, 190) — coerção de string, mas **sem `is_numeric`**, ao contrário de `User::hasPermission`.

Inconsistência de coluna: `getCabecalhosRelatorio('associados')` espera `cidade` (394) mas `buscarDadosAssociados` retorna `cidade_nome` (180) → coluna vazia.

### 3.4 `config/relatorios_config.php` (327 linhas) — **código morto**

`return [ ... ]` na linha 8 encerra a execução do arquivo. Portanto **nunca executam**:
- constantes `RELATORIO_*` (233-240), incluindo `RELATORIO_URL_BASE` montada com `$_SERVER['HTTP_HOST']` sem sanitização (239);
- criação de `temp/relatorios/{cache,exportados,email}`, `logs/`, `uploads/relatorios/` (243-255);
- helpers `formatar_moeda`(266), `formatar_data`(279), `obter_config_relatorio`(299).

Conteúdo declarativo (não usado em runtime): geral/versão `1.0.0`, criador "AgroTech Solutions"; `pdf` (A4 retrato, `#3498db`, bloco de proteção/permissoes); `excel` (estilos `padrao|cabecalho|destaque|total`, larguras/alturas); `email` (remetente `relatorios@agricola.com.br`, anexos em `temp/relatorios/email/`); `cache` (24 h, `temp/relatorios/cache/`); `log` (nível info, `logs/relatorios_YYYY-MM.log`, 10 MB); `seguranca` (`niveis_acesso: admin/gerente/usuario/visitante`, rate-limit 100/h, `validar_origem` com `$_SERVER['SERVER_NAME']`); `exportacao` (`pdf,xlsx,csv,html`, limites 1k/10k/100k); `templates` (aponta para `views/relatorios/templates/` — **diretório inexistente**); `notificacoes` (e-mails fictícios); `depuracao.modo_desenvolvimento => true` e `exibir_erros => true` (222-223).

### 3.5 `classes/ReportConformidade.php` (1040 linhas)

Estrutura normalizada: `titulo, descricao, resumo[{label,valor}], tabelas[{titulo,colunas,campos,dados}]` (5-14).

| Método | Linha | Tabelas produzidas | Requisito |
|---|---|---|---|
| `balancoMassas` | 126 | 1 (lote final, entrada×saída, divergência %, status auditoria) | RF11 |
| `trilhaAuditoria` | 202 | 1 (audit_log, LIMIT 5000) | NFR03 |
| `rastreabilidadeConsolidada` | 272 | 2 (lotes finais; origem insumo/NF/lote primário) | RF12 |
| `validacaoInsumos` | 372 | 1 (insumos × lista oficial) | RF06 |
| `historicoConversao` | 489 | 1 (propriedade, uso do solo, início/fim, status) | RF03 |
| `manejoAnimal` | 549 | 2 (animais; registros de manejo) | RF05 |
| `agroindustriaConsolidado` | 660 | 2 (receitas; ordens de produção) | RF07-RF10/RF12 |
| `higienizacao` | 752 | 1 (controle_higienizacao) | RF08 |
| `producaoAnimalConsolidado` | 805 | 4 (lotes; alimentação art.34-37; sanidade art.59-63; reprodução) | Portaria Título III |
| `catalogo()` | 973 | 9 slugs com `metodo,titulo,icone,descricao,filtros` | — |

Helpers: `filtroPeriodo`(72), `normalizarNome`(91, iconv ASCII//TRANSLIT), `filtroAssociado`(105), `filtroEnum`(115).

**Bugs:**
- `validacaoInsumos`: o `SELECT` (381-394) **não traz `insumo_aprovado_id`**, mas a linha 423 testa `!empty($r['insumo_aprovado_id'])` → sempre falso; e `$aprovados` (402-404) **não seleciona `id`**, então `$aprovadoRow['id']` (425) é indefinido. O fallback por ID é inoperante — só o casamento por nome normalizado funciona.
- `producaoAnimalConsolidado`: `$nao_organico_ms = [15.0, 20.0];` (852) nunca usado (validação de %MS não implementada).
- `trilhaAuditoria` ignora `associado_id`: em `modulos/conformidade/relatorios/gerar.php:50-52` o não-gestor recebe `filtros['associado_id']` forçado, mas esse filtro não é aplicado na trilha → produtor vê o log global (IPs, ações, descrições de todos).
- `balancoMassas` exibe `status_auditoria` cru (sem normalizador), diferente dos demais.

### 3.6 Exportação PDF/Excel — bibliotecas por arquivo

| Biblioteca | Onde |
|---|---|
| **mPDF** (`Mpdf\Mpdf`) | `ReportManager::gerarRelatorioPDF`(101), `classes/Relatorio::gerarPdfComDados`(211), `api/relatorios_admin.php:387`, `api/relatorios/admin_export.php:304`, `api/relatorios/relatorio_associados.php:131`, `relatorios/associados.php:164`, `relatorios/financeiro.php:155`, `relatorios/gerar_pdf.php:60`, `relatorios/gerar_certificado.php:109`, `modulos/relatorios/relatorio_associados.php:13`, `modulos/conformidade/relatorios/gerar.php:74`, `modulos/caderno_campo/relatorios/*` |
| **Dompdf** (`Dompdf\Dompdf`) | **apenas** `api/certificacao/gerar_pdf.php:94,107` |
| **TCPDF** | `api/relatorios/relatorio_atividades_usuario.php:21` (`use TCPDF`) e ~401 |
| **PhpSpreadsheet** | `classes/Relatorio::gerarExcelComDados`(240), `api/relatorios_admin.php:252`, `api/relatorios/admin_export.php:208`, `relatorios/financeiro.php:174`, `api/relatorios/relatorio_atividades_usuario.php:19`, `modulos/conformidade/relatorios/gerar.php:141` |
| **Excel "falso" (HTML com `application/vnd.ms-excel`)** | `relatorios/associados.php:150-159`, `api/relatorios/relatorio_associados.php:123-128` |
| **Excel via JS (SheetJS/xlsx)** | `relatorios.php:790-801`, `relatorios/index.php:738-749` |
| **Excel via DataTables buttons** | `relatorios.php:269-307,711-727`, `relatorios/index.php:507-517` |

### 3.7 As duas "centrais" e o legado

**(A) `relatorios.php` — roteada em `/relatorios`.** `$reportCategories` (53-189) define **6 categorias / 14 relatórios** com `level`:

| Categoria | Relatórios (level) |
|---|---|
| dashboard | visao-geral (1) |
| associados | lista-associados (2), status-associados (2) |
| atividades | atividades-diarias (3), produtividade (3) |
| estoque | estoque-atual (3), movimentacoes (2) |
| financeiro | fluxo-caixa (2), contas-pagar (2), contas-receber (2) |
| associacao | inadimplencia (2), pagamentos-por-forma (2), previsao-receita (2), fluxo-caixa-realizado-previsto (2) |

Fluxo: `$_GET['relatorio']` → procura em `reportCategories` (196-198) → nega se `$userLevel > level` (202-208) → renderiza página com filtros `dataInicio,dataFim,filtro_associado_id` (239-257) → AJAX `api/relatorios/<slug>.php` (278) → DataTables (293-307) + lista de associados de `api/associados/listar.php` (316).

Problemas: (i) URL **relativa** `'api/relatorios/<slug>.php'` (278) resolve contra `/relatorios/<cat>/<rep>` → `/relatorios/<cat>/api/relatorios/...` → cai em `relatorios.php` com `relatorio='api'` → "Relatório não encontrado"; (ii) `$filtros = $_POST` (32) — os filtros do formulário (`name=data_inicio/data_fim/status`, 872-900) nunca chegam ao `ReportManager` do painel inicial; (iii) **dados financeiros fictícios hardcoded** (1049-1074: "R$ 15.250,00", "R$ 8.750,00"; 1093-1124: lançamentos 05/11/2025) apresentados como reais; (iv) markup órfão: 804-1133 renderiza `tab-pane` sem container de abas (a página específica sempre faz `exit` na linha 342); (v) links para `relatorio_associados.php`, `relatorio_atividades.php`, `relatorio_insumos.php`, `relatorio_financeiro.php` (811, 866, 958, 1036) — **arquivos inexistentes** em `relatorios/`; (vi) links para `modulos/estoque/visualizar.php` (1011) e `associados/visualizar.php` (845) — **inexistentes**.

**(B) `relatorios/index.php` — alcançável como `/app/relatorios/` (DirectoryIndex).** **Não é referenciado pelo roteador** (nenhum `require` aponta para ele). Links para `relatorios/{associados,propriedades,atividades,insumos,lavouras,financeiro}.php` (217, 267-270, 293-296, 316-319, 339-342, 363). O botão "Relatório Personalizado" (693-729) navega para `/relatorios/visualizar?...` — **rota inexistente** (`visualizar` não está em `reportCategories`).

**(C) `relatorios_router.php`** (59 linhas, mapa tipo→form): **nunca incluído** (grep global vazio) → morto. Se usado, incluiria `relatorios/dashboard.php` no caso `tipo=''` (28), que é fatal (§3.8).

### 3.8 Estado real dos arquivos de `relatorios/`

| Arquivo | Auth | Veredito |
|---|---|---|
| `index.php` | LOGIN (17) | órfão do roteador; links e `/relatorios/visualizar` quebrados |
| `dashboard.php` | LOGIN (13) | **FATAL**: `$user->isAdmin()` (26) e `$user->getNome()` (208) **não existem** em `classes/User.php` → `Error` não capturado por `catch(Exception)`. "Recentes" fictícios (203-225) |
| `form_atividades.php` | LOGIN (14) | **FATAL** em `$user->isAdmin()` (36) |
| `form_propriedades.php` | LOGIN (14) | **FATAL** em `$user->isAdmin()` (36) |
| `form_insumos.php` | LOGIN (14) | OK; POST JSON via fetch (244) para `api/relatorios_controller.php` |
| `form_financeiro.php` | LOGIN (14) | form carrega, mas `tipo=financeiro` → método inexistente → 500 |
| `form_personalizado.php` | LOGIN (14) | **duplo defeito**: `form.submit()` nativo (494) envia `x-www-form-urlencoded`, mas `api/relatorios_controller.php:38` faz `json_decode(php://input)` → 400; e `personalizado` não tem método em `Relatorio` |
| `atividades.php`/`insumos.php`/`lavouras.php`/`produtores.php`/`propriedades.php` | LOGIN (18) | chamam `gerarRelatorioPDF` → template fora do app → saída vazia |
| `associados.php` | **apenas LOGIN** (25) | PDF/Excel com **todos** os associados (nome/email/telefone/status) — nível 3 incluído (§6.3) |
| `financeiro.php` | LOGIN + `userNivel>2` bloqueia (31-35) | funciona (mPDF + PhpSpreadsheet diretos); grava `relatorios/debug.log` (173,278) |
| `gerar_pdf.php` | LOGIN (22) | SQL usa `a.cpf_cnpj` (36), provável coluna inexistente; template `templates/relatorio_associados.php` existe |
| `gerar_certificado.php` | **apenas LOGIN** (14) | IDOR: qualquer logado gera certificado de `certificacao_id` arbitrário |
| `eventos.php` | LOGIN (18) | **FATAL**: `getEventosReport` inexistente (60); `__DIR__.'/includes/header.php'` (78) e `'/relatorios/visualizar_eventos.php'` (82) inexistentes |
| `templates/*` | — | 9 templates; usam `$logoPath ?: SITE_URL.'/img/logo_Sinporg-2.png'`; exigem variáveis não fornecidas (§3.2) |

---

## 4. LOGIN EM 2 ETAPAS DO GESTOR

### 4.1 Fluxo exato (`login.php`)

1. **Entrada**: `/login` → `index.php:11-14` → `login.php`.
2. **Já logado** (10-17): nível 3 → `/caderno-campo/dashboard-agricultor`; demais → `/`.
3. **Etapa 1 — credenciais** (78-221), só POST sem `etapa=2`:
   - `email`/`senha` coagidos a string (81-82) — defesa contra `senha[]=x` (TypeError em `password_verify`);
   - busca `usuarios WHERE email=? AND status='ativo'` (95) **e** `associados ... status='ativo'` (99);
   - valida o hash nas duas, **preferindo `usuarios`** (105-111);
   - `session_regenerate_id(true)` (115) — anti-fixação;
   - **bifurcação por nível** (118):
     - **Gestor (2)**: `$gestorJaPreenchido = !empty(orgao) && !empty(especialidade) && !empty(setor)` (120-122);
       - **incompleto** → grava `$_SESSION['login_temp']` com `user_id,user_nivel,user_nome,user_tipo,user_table,email` (126-133), marca `primeiro_acesso=true` (134) e exibe a **Etapa 2** (`$showStep2=true`, 135);
       - **completo** → sessão definitiva (138-146) e redireciona: perfil incompleto → `/meu-perfil` (149-152); senão `/` (153).
     - **Admin / usuário comum** (156-212): sessão (158-163); para `usuarios` nível 3 sem vínculo e sem ficha (`FichaAdesao::associadoIdDoUsuario`, 175-185) → `/caderno-campo/dashboard-agricultor`; primeiro acesso = `empty(telefone) && empty(cpf)` (188-191); perfil incompleto → `/meu-perfil` (195-198); primeiro acesso (exceto admin) → `/meu-perfil` (201-204); nível 3 → dashboard-agricultor (206-207); resto → `/`.
   - Falha → `"E-mail ou senha inválidos!"` (214); exceção → mensagem genérica + `error_log` (216-219).

4. **Etapa 2 — vínculo institucional** (20-75). Condição: `$_POST['etapa']==='2' && isset($_SESSION['login_temp'])`.
   - Lê `orgao, especialidade, setor` (22-24);
   - **validação**: `empty($orgao) || empty($especialidade)` → "Preencha o órgão e a especialidade/cargo." (26-27). `setor` é **opcional na tela** (609) mas **obrigatório para pular a etapa** no próximo login (120-122) → gestor que deixar `setor` vazio **repete a etapa 2 em todos os logins**;
   - `UPDATE {$table} SET orgao=:orgao, setor=:setor, especialidade=:especialidade WHERE id=:id` (34-40) — `$table` vem de `login_temp['user_table']` (escrito pelo servidor);
   - promove a sessão temporária a definitiva (42-50) e define `mostrar_modal_eventos=true` (50);
   - `primeiro_acesso` → `$_SESSION['primeiro_acesso']=true` (53-56); `unset($_SESSION['login_temp'])` (58); `session_regenerate_id(true)` (61);
   - redireciona: primeiro acesso → `/meu-perfil`; senão → `/` (64-68);
   - **exceção** → `unset($_SESSION['login_temp'])` (72) — perde o estado e volta à etapa 1.

5. **UI**: indicador de 2 passos (571-574); `form-step2` (591) com `orgao`/`especialidade` `required` e `setor` opcional (594-613); botão com loader (615-618; JS 881-888).

**Validações da 2ª etapa:** (a) exige `etapa=2` **e** `$_SESSION['login_temp']`; (b) `orgao` e `especialidade` não vazios; (c) `trim()` nos três; (d) **sem CSRF**; (e) **sem rate-limit**; (f) `login_temp` **sem TTL**.

### 4.2 Outros logins

- `classes/User::login()` (54-104) duplica a lógica de `login.php` (busca dupla, mesma preferência) mas **não trata a 2ª etapa do gestor**; não é usado pelo `login.php`.
- Portal do associado: `modulos/portal/login.php` → sessão `associado_id` (reconhecida por `ApiAuth::nivel()`, 63-65).

---

## 5. RECUPERAÇÃO DE SENHA

### 5.1 Fluxo

1. `/recuperar-senha` (`index.php:19-22`) → `recuperar_senha.php` (124 linhas, HTML/JS). Promete **60 minutos** (54). JS: `fetch(POST, x-www-form-urlencoded, body='email=...')` para `SITE_URL + '/api/recuperar_senha.php'` (103-107); regex de e-mail no cliente (96).
2. `api/recuperar_senha.php` (75 linhas): só POST (16-20); `filter_var(...,FILTER_VALIDATE_EMAIL)` (26); `PasswordReset::findUserByEmail` (33); **resposta genérica** exista ou não a conta (24, 36-39, 67) — anti-enumeração; gera token (41); `SITE_URL.'/redefinir-senha/'.$token` (42); `Mailer::send`/Resend com `Mailer::layout` (54). **Falha de envio**: loga o link em `error_log` (58) e responde erro explícito (60-64) — vaza o link para o log.
3. `/redefinir-senha/{token}` (`index.php:23-27`, token de `parts[1]`) → `redefinir_senha.php`: `getValidToken` (17) → se válido e POST: `strlen(senha)>=6` (26), `senha===confirma` (28), `findUserByEmail` (31), `updatePassword` (35), `markUsed` (36), `AuditLog::registrar('SENHA_RESET',...)` (37). Form faz POST para `SITE_URL.'/redefinir-senha/'.$token` (99). **Sem CSRF**.

### 5.2 Tokens e tabela (`classes/PasswordReset.php`, 121 linhas)

| Operação | Implementação |
|---|---|
| `findUserByEmail` (20-41) | só `is_string`; consulta **`associados` primeiro** (26-31) e depois `usuarios` (33-38); apenas `status='ativo'`; retorna `['tabela','id','nome','email']` |
| `createToken` (48-65) | `bin2hex(random_bytes(32))` (256 bits); guarda **`hash('sha256',$token)`** (54); `expires_at = now + 3600` (55); **`DELETE FROM password_resets WHERE email = ?`** antes do INSERT (58-59) → 1 token por e-mail |
| `getValidToken` (72-86) | `hash('sha256',token)`; `WHERE token_hash=? AND used=0 AND expires_at > NOW() LIMIT 1` |
| `markUsed` (91-98) | `UPDATE ... SET used=1 WHERE id=? AND used=0` + `rowCount()>0` — consumo idempotente (anti-TOCTOU) |
| `updatePassword` (105-112) | whitelist `['associados','usuarios']`; `password_hash(PASSWORD_DEFAULT)` (bcrypt) |
| `purgeOldTokens` (117-120) | apaga expirados/usados (não há cron no código) |

**Tabela `password_resets`** (deduzida do SQL): `id` (PK), `email`, `token_hash` (SHA-256 hex), `expires_at` (DATETIME), `used` (0/1). O mesmo modelo existe em `sinporg/apps/api/src/generated/prisma/models/password_resets.ts`.

**Observações:** (i) o token em claro só existe no e-mail e no `error_log` de falha (58); (ii) `createToken` não recebe `user_id`/tabela — a ligação é só por e-mail, então um e-mail presente em **ambas** as tabelas redefiniria a senha de `associados` (a primeira encontrada), não de `usuarios` — **inconsistência em relação ao login**, que prefere `usuarios`; (iii) sem rate-limit/CAPTCHA na solicitação.

---

## 6. GAPS, INCONSISTÊNCIAS E BUGS

### 6.1 CRÍTICO — endpoint público que vaza a base de associados

`modulos/relatorios/relatorio_associados.php` (153 linhas) **não tem session_start, nem `isLoggedIn()`, nem `hasPermission`, nem include de header** — confirmado por varredura dos 204 alvos do roteador. Rota pública: `/relatorio-associados` (`index.php:514-517`), também acionada pelo dashboard inicial (`index.php:1288` → `abrirRelatorioAssociados()`, 1466-1478). O SQL (26-37) traz `nome_completo, cpf, email, telefone, nome_associacao` de **todos** os associados e devolve PDF inline (121-126). Um anônimo que abra `/app/relatorio-associados` baixa a lista completa com CPF.

### 6.2 ALTO — LFI documentado e **não corrigido**

`includes/rota_segura.php` implementa `rota_arquivo_seguro()` (formato 77-82, whitelist 85-87, `realpath` 90-102) e `ROTAS_API_RELATORIOS_PERMITIDAS` (31-51) + `ROTAS_PIX_WEBHOOK_PERMITIDAS` (57-63), mas **nenhum arquivo o inclui**. O roteador continua com include dinâmico:

```php
// index.php:47-58
if ($parts[0] === 'api' && $parts[1] === 'relatorios' && isset($parts[2])) {
    $tipo = $parts[2];
    $arquivo = __DIR__ . '/api/relatorios/' . $tipo . '.php';
    if (file_exists($arquivo)) { require $arquivo; } else { /* 404 JSON */ }
}
```

Exploração exige barra codificada (`%2f`) no segmento — barrada por `AllowEncodedSlashes Off` (padrão Apache), mas viável se ligada; o `.php` fixo impede escape com `..` simples (`/api/relatorios/../get_cidades` procura `api/relatorios/...php`). Ainda assim: **a mitigação escrita não está aplicada** e `/api/relatorios/{_config,bootstrap,relatorio_atividades_usuario_temp}` são alcançáveis como endpoints. Docs internos já registram o achado (`RELATORIO_MODULARIZACAO.md:36,255,447,598`).

### 6.3 ALTO — Broken Access Control / IDOR

| Endpoint | Problema | Linha |
|---|---|---|
| `modulos/relatorios/relatorio_associados.php` | sem auth | arquivo inteiro |
| `api/associados/listar.php` | nível 3 recebe nome/e-mail/telefone de **todos** os ativos; CORS `*`; checagem de login duplicada | 7, 24-40, 46-67 |
| `api/plano_manejo.php` DELETE | **sem checagem de dono/nível** → qualquer logado apaga qualquer plano | 409-422 |
| `api/plano_manejo.php` PUT | aceita `associado_id`/`propriedade_id` de terceiros; só `status` é gated | 287-316 |
| `api/roteiros_inspecao.php` `action=templates` POST/DELETE | só LOGIN → nível 3 cria/exclui templates | 370-378, 717-730 |
| `api/roteiros_inspecao.php` `action=nao_conformidades` | sem filtro por associado (ao contrário de `api/nao_conformidades.php:70-73`) | 326-349 |
| `api/roteiros_inspecao.php` `action=relatorio&id=` | PDF de qualquer visita | 92-111 |
| `relatorios/associados.php` | só `isLoggedIn` → nível 3 emite PDF com toda a base | 25 |
| `relatorios/gerar_certificado.php` | só `isLoggedIn` → certificado de `certificacao_id` arbitrário | 14, 18-27 |
| `modulos/conformidade/relatorios/gerar.php` | `associado_id` forçado (50-52) mas **ignorado** por `trilhaAuditoria` | ReportConformidade.php:202-266 |
| `api/certificacao/controller.php` `listCertificacoes` | `$_SESSION['user_nivel'] == NIVEL_USUARIO` (frouxo) e `$certificacao['associado_id'] != $associadoId` | 144-151, 174-177 |
| `api/certificadoras.php` | GET exige G (93), mas POST/PUT/DELETE usam `!$_SESSION['user_nivel']` (falsy para qualquer valor "0"/não numérico) | 122,151,182 |
| `api/get_propriedade_detalhes.php` | IDOR **já corrigido** com `exigirProprioAssociado` (59) | — |

### 6.4 Bugs funcionais que quebram features inteiras

| # | Bug | Evidência |
|---|---|---|
| B1 | `ReportManager::$templatesDir` fora do app | `ReportManager.php:17` vs `relatorios/templates/` |
| B2 | Templates exigem `$colunas` e coleções que o `extract` não fornece | `ReportManager.php:86-92` vs `templates/relatorio_propriedades.php:267`, `relatorio_produtores.php:225`, `relatorio_lavouras.php:213`, `relatorio_financeiro.php:286` |
| B3 | `Relatorio` sem `buscarDadosFinanceiro`/`buscarDadosPersonalizado` → 500 em 2 dos 5 tipos aceitos | `Relatorio.php:59-65,70-201` vs `api/relatorios_controller.php:51` |
| B4 | `User::isAdmin()` e `User::getNome()` **não existem** → fatal em `relatorios/dashboard.php:26,208`, `form_atividades.php:36`, `form_propriedades.php:36` | grep em `classes/User.php` (só `isLoggedIn` em 106) |
| B5 | `ReportManager::getEventosReport` inexistente → fatal em `relatorios/eventos.php:60` | — |
| B6 | `config/relatorios_config.php` com `return` na linha 8 → linhas 232-327 nunca executam | — |
| B7 | `api/relatorios/_config.php` nunca incluído (credenciais duplicadas) | grep global |
| B8 | `relatorios_router.php` nunca incluído | grep global |
| B9 | Rotas `/teste`, `/teste-db`, `/teste-ajax` apontam para arquivos inexistentes na raiz (estão em `recicla/`) → fatal | `index.php:783-798` |
| B10 | `/caderno-campo/insumos/validacao` inalcançável (colisão de rota) | `index.php:253` vs `489` |
| B11 | Bloco `/relatorios` em 495-498 é código morto | `index.php:32` |
| B12 | `/rastreabilidade` sem rota; o formulário da própria página aponta para ela | `rastreabilidade.php:205` |
| B13 | `relatorios/index.php` navega para `/relatorios/visualizar` inexistente | `relatorios/index.php:724` |
| B14 | AJAX de `relatorios.php` usa URL relativa em página de profundidade 3 | `relatorios.php:278` e `685` |
| B15 | `relatorios/eventos.php` inclui `__DIR__.'/includes/header.php'` e `'/relatorios/visualizar_*.php'` — inexistentes | 78, 82, 87 |
| B16 | `relatorios.php` exibe valores financeiros fictícios hardcoded | 1049-1074, 1093-1124 |
| B17 | `relatorios/dashboard.php` exibe "Relatórios Recentes" fictícios | 203-225 |
| B18 | `api/relatorios/previsao-receita.php` lê `filtro_associado_id` e o descarta | 16 |
| B19 | `api/relatorios/relatorio_associados.php` usa `in_array($_SESSION['user_nivel'],[1,2],true)` — `user_nivel` vem do MySQL como **string** → admin legítimo recebe "Você não tem permissão" | 15 |
| B20 | `api/certificacao/controller.php` `listCertificacoes`: `$countSql` com placeholders de `tipo_certificacao/status/associado_id` (204-206) mas `$params` só recebe `associado_id` (171-181) → "Invalid parameter number" (500) para gestor filtrado | 200-215 |
| B21 | `handleUploadAssinatura` calcula `$mimeType` (472) e nunca usa; valida só extensão `.pdf` | 470-483 |
| B22 | `api/get_eventos_pendentes_new.php` devolve `$_SESSION` inteiro a quem não está logado | 14-23 |
| B23 | `api/get_eventos_pendentes.php` grava `$_SESSION` em `logs/api_debug.log` (35,72) e, no erro, devolve `$_SERVER`+`$_SESSION`+trace (145-158); cria `logs/` com `0777` (40) | — |
| B24 | `relatorios/financeiro.php` escreve `relatorios/debug.log` em produção | 173, 278 |
| B25 | `relatorios/gerar_pdf.php` consulta `a.cpf_cnpj` (o resto do código usa `a.cpf`) | 36 |
| B26 | `api/certificacao/gerar_pdf.php` usa `$associado['cpf_cnpj']` (46) e grava em `uploads/{certificados,declaracoes}/` **sem `mkdir`** nem verificação de `file_put_contents` | 46, 99-113 |
| B27 | `ReportConformidade`: fallback por ID inoperante (`insumo_aprovado_id`/`id` não selecionados) | 381-404, 423-430 |
| B28 | `relatorios_config`: `'depuracao' => ['modo_desenvolvimento'=>true,'exibir_erros'=>true]` versionado | 221-229 |
| B29 | `rastreabilidade.php` usa `ad.insumo_utilisado` (grafia divergente do `insumo_utilizado` de `ReportManager.php:635`) | 43-48, 187 |
| B30 | `api/get_cidades.php` responde `[]` sem erro se nenhum parâmetro for enviado | 6-45 |

### 6.5 Webhooks PIX sem autenticação nem validação de origem

`api/pix/webhook/{asaas,gerencianet,mercadopago}.php`: nenhum verifica assinatura/token/IP; o `txid` vem do corpo JSON e vai direto para `Mensalidade::processarWebhookPix($txid,$data)` (`asaas.php:22-31`, `gerencianet.php:20-29`, `mercadopago.php:20-29`). Sem rota e sem whitelist aplicada, é acessível publicamente em `/app/api/pix/webhook/{provedor}.php` — um POST forjado pode confirmar pagamento. Nada é gravado em `audit_log`.

### 6.6 Segredos e credenciais no código

- `config/config.php:11-14`: `DB_HOST localhost`, `DB_USER root`, `DB_PASS ''`, `DB_NAME sisagro`.
- `config/config.php:19`: `SITE_URL = http://localhost/app` (produção comentada na linha 18).
- `config/config.php:52`: fallback `ID_ENCODE_KEY = 'troque-esta-chave-em-producao'` → `encodeId/decodeId` (58-81) previsíveis sem `config/secrets.php`.
- `api/relatorios/_config.php:3-6`: mesmas credenciais duplicadas.
- `api/legacy/session-bridge.php:58`: lê `../sinporg/.env` com parser caseiro e emite JWT HS256 de 900 s (66) — ponte temporária que deveria sair na Fase 7 (comentário 1-13).
- `config/secrets.php` existe (0,5 KB) e não foi lido (fora do escopo).

### 6.7 Outras inconsistências de roteamento/contrato

1. **Categoria de relatório ignorada**: `/relatorios/{qualquer}/{slug}` funciona se `slug` existir em `reportCategories` (`index.php:38-44` + `relatorios.php:197`).
2. **Dois endpoints para o relatório financeiro**: `api/relatorios_admin.php` e `api/relatorios/admin_export.php`, com mapas de colunas **diferentes** para `usuarios` (8 colunas em `admin_export.php:67` vs 6 em `relatorios_admin.php:64`) e `associados` (RG x CPF/CNPJ).
3. **`/evento/{id}`** roteia para `modulos/eventos/visualizar.php`, mas a home consome via `fetch` esperando JSON `{success,titulo,html}` (`index.php:1442-1462`).
4. **Dupla checagem** de login em `api/associados/listar.php:24-40` (copy/paste).
5. `404.php:3` inclui `includes/header.php` → anônimo recebe redirect para /login em vez do 404.
6. `api/relatorios/gerar.php` não passa por `Relatorio`: usa `ReportManager::gerarRelatorioPDF` e herda B1.

### 6.8 Matriz de risco resumida

| Severidade | Achados |
|---|---|
| **Crítica** | 6.1 (`/relatorio-associados` anônimo com CPF); 6.5 (webhook PIX forjável); 6.2 (LFI latente) |
| **Alta** | IDOR em `plano_manejo` DELETE/PUT, `roteiros_inspecao` templates/NC/PDF, `relatorios/associados.php`, `gerar_certificado.php`; trilha de auditoria global para produtor; `api/associados/listar.php` sem nível; `get_eventos_pendentes*` vazando `$_SESSION` |
| **Média** | B1/B2 (PDFs via `ReportManager` falham), B3/B4/B5 (features fatais), B6/B7/B8 (código morto), PII em logs |
| **Baixa** | Dados fictícios (B16/B17), endpoints duplicados, colisões de rota (B10/B11), `debug.log` (B24), `relatorios/index.php` órfão |

---

## 7. Correções mínimas sugeridas (ordem de risco)

1. Guarda em `modulos/relatorios/relatorio_associados.php` (ex.: `include includes/header.php` ou `ApiAuth::exigirStaff()`).
2. Segredo compartilhado/assinatura HMAC nos três webhooks PIX + registro em `audit_log`.
3. Incluir `includes/rota_segura.php` em `index.php` e usar `rota_arquivo_seguro(__DIR__.'/api/relatorios', $parts[2], ROTAS_API_RELATORIOS_PERMITIDAS)`.
4. Fechar IDORs: dono em `plano_manejo` (PUT/DELETE), nível G em `roteiros_inspecao` (templates/NC/relatório), `exigirStaff` em `relatorios/associados.php` e `gerar_certificado.php`.
5. `ReportManager.php:17` → `__DIR__ . '/../relatorios/templates/'`; passar `colunas` + coleções esperadas no `extract` (ou padronizar os templates para `$dados`).
6. B3 (`buscarDadosFinanceiro`/`Personalizado`), B4 (`isAdmin()`/`getNome()` em `User`), B5 (`getEventosReport`), B12 (rota `/rastreabilidade`), B19 (`(int)` na comparação de nível), B20 (alinhar `$params` do `countSql`).
7. `config/relatorios_config.php`: trocar o `return` da linha 8 por `$config = [...]` para que helpers/diretórios existam — ou apagar também `api/relatorios/_config.php` e `relatorios_router.php`.
8. Remover as rotas `/teste*` (B9) e o bloco morto (B11); desambiguar `/caderno-campo/insumos/validacao` (B10).
