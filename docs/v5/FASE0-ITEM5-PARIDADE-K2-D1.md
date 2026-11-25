# Fase 0 — Item 5: Inventário de Paridade K2 e D1

> **Data:** 30/10/2026 · **Status:** **inventário concluído** (implementação ainda não iniciada).
> **Método:** varredura do roteador `index.php`, páginas de `modulos/`, APIs, classes e tabelas do
> banco `sisagro`. Cada linha traz `arquivo:linha` quando aplicável.
> **Nenhum dado pessoal é exibido.**

---

## 1. Resumo

| Módulo | Arquivos legados inventariados | Rotas/ações | Tabelas donas | Situação |
|---|---:|---:|---|---|
| **K2 Território & Referências** | 11 | ~15 | `estados`, `cidades`, `mesorregioes_rs`, `microrregioes_rs`, `cnpo`, `cnpo_atualizacoes` | inventário OK; implementação na F2 |
| **D1 Quadro Associativo & Adesão** | 23 + 6 classes | ~25 | `associados`, `associacoes`, `associado_associacao`, `ficha_adesao`, `ficha_adesao_produtos`, `carteirinhas`, `entidades` | inventário OK; implementação na F2 |

---

## 2. K2 — Território & Referências

### 2.1 Rotas legadas

| # | Rota/arquivo | Evidência | Ação | Alvo v1 |
|---|---|---|---|---|
| K2-01 | `/api/cidades` → `api/get_cidades.php` | `index.php:850-852` | lista cidades (todas ou por estado) | `GET /api/v1/territorios/cidades?uf=` |
| K2-02 | `/regionalizacao` → `modulos/regionalizacao/index.php` | `index.php:1136-1137` | painel de mesorregiões/microrregiões | `GET /api/v1/territorios/mesorregioes` |
| K2-03 | `/regionalizacao/mapa` → `modulos/regionalizacao/mapa.php` | `index.php:1139-1140` | mapa por indicador/mesorregião | `GET /api/v1/territorios/mapa` |
| K2-04 | `/consulta-cnpo` → `modulos/consulta_cnpo/index.php` | `index.php:1170-1172` | consulta CNPO/PEAF por UF/cidade/tipo | `GET /api/v1/cnpo?termo=&uf=&cidade=&tipo=` |
| K2-05 | `api/get_estado_cidade.php` (direto) | `api/get_estado_cidade.php:8-15` | estado de uma cidade | `GET /api/v1/territorios/cidades/{id}/estado` |
| K2-06 | `api/regionalizacao/dados.php` | `api/regionalizacao/dados.php:8-19` | dados agregados (auth) | `GET /api/v1/territorios/indicadores` |
| K2-07 | `api/regionalizacao/microrregioes.php` | `api/regionalizacao/microrregioes.php:8-23` | microrregiões por mesorregião | `GET /api/v1/territorios/microrregioes?mesorregiao_id=` |
| K2-08 | `api/cnpo_consulta.php` | `api/cnpo_consulta.php:24-51` | consulta CNPO (login + termo mínimo) | `GET /api/v1/cnpo` |
| K2-09 | `map_cidades_micro.php` (script raiz) | `map_cidades_micro.php:113-161` | preenche `cidades.microrregiao_id` a partir de IBGE | job administrativo, não endpoint |
| K2-10 | `municipio_micro_map.php` (página raiz) | arquivo (455 linhas) | mapa município × microrregião | absorvido por `GET /api/v1/territorios/mapa` |
| K2-11 | `classes/MicrorregiaoRS.php` | `classes/MicrorregiaoRS.php:24,44,63,79,91` | consultas de meso/microrregiões | repositório de K2 |

### 2.2 Consultas e regras observadas

- `api/get_cidades.php:9` — `?todos=1` lista todas; `:28-33` filtra por `estado_id`; sem guarda de
  autenticação aparente (endpoint público).
- `api/get_estado_cidade.php:8-15` — resolve estado por `cidade_id`.
- `api/regionalizacao/*` — exigem `ApiAuth::exigirLogin()` (`:8-13`).
- `api/cnpo_consulta.php:41-51` — termo mínimo de 2 caracteres; filtros por UF/cidade/tipo.
- `map_cidades_micro.php:144` — `UPDATE cidades SET microrregiao_id` (script de carga, não runtime).
- `classes/MicrorregiaoRS.php` — quatro consultas com JOIN em `mesorregioes_rs`.

### 2.3 Tabelas e ownership

| Tabela | Uso | Observação |
|---|---|---|
| `estados` | lista de UFs | referência |
| `cidades` | municípios/IBGE | 5.595 registros; inclui `microrregiao_id` |
| `mesorregioes_rs` | 7 mesorregiões | referência RS |
| `microrregioes_rs` | 35 microrregiões | referência RS |
| `cnpo` | 26.025 registros | base oficial |
| `cnpo_atualizacoes` | log de importação | job |

### 2.4 Gaps/decidir em K2

1. `api/get_cidades.php` é **público**; alvo deve ter rate limit e cache.
2. Consulta CNPO tem dois caminhos (`api/cnpo_consulta.php` e `modulos/consulta_cnpo/index.php`);
   unificar na API v1.
3. Scripts `map_cidades_micro.php` e `municipio_micro_map.php` são de carga/uso interno; não expor.
4. Importação CNPO/PEAF roda por cron (`dev-utils/atualizar_cnpo.php`/`atualizar_peaf.php`); alvo:
   job administrativo com log.

---

## 3. D1 — Quadro Associativo & Adesão

### 3.1 Rotas legadas

| # | Rota/arquivo | Evidência | Ação | Alvo v1 |
|---|---|---|---|---|
| D1-01 | `/associados` → `listar.php` | `index.php:615-616` | listagem/filtros | `GET /api/v1/associados` |
| D1-02 | `/associados/pendentes` → `pendentes.php` | `index.php:619-620` | aprovar/reprovar acesso | `POST /api/v1/associados/{uuid}/aprovar` |
| D1-03 | `/cadastros-pendentes` → `cadastros_pendentes.php` | `index.php:624` | aprovar/reprovar ficha | `POST /api/v1/fichas/{uuid}/aprovar` |
| D1-04 | `/cadastro-ficha/visualizar` → `ficha_adesao_visualizar.php` | `index.php:634-635` | ver ficha/modal/PDF | `GET /api/v1/fichas/{uuid}` + PDF |
| D1-05 | `/associados/carteirinhas` → `carteirinha_listar.php` | `index.php:639-640` | lista carteirinhas | `GET /api/v1/carteirinhas` |
| D1-06 | `/associado/{id}/carteirinha-gerar` | `index.php:643-644` | gerar token/validade | `POST /api/v1/associados/{uuid}/carteirinha` |
| D1-07 | `/associado/{id}/carteirinha-impressa` | `index.php:648-649` | versão impressa | `GET /api/v1/carteirinhas/{id}/impressa` |
| D1-08 | `/associado/{id}/carteirinha-pdf` | `index.php:653-654` | PDF | `GET /api/v1/carteirinhas/{id}.pdf` |
| D1-09 | `/associado/{id}/carteirinha-enviar` | `index.php:658-659` | envio por e-mail | `POST /api/v1/carteirinhas/{id}/enviar` |
| D1-10 | `/carteirinha/{token}` → `carteirinha_publica.php` | `index.php:664` | validação pública | `GET /publico/carteirinha/{token}` |
| D1-11 | `/associados/relatorio` | `index.php:669-670` | relatório de associados | `GET /api/v1/relatorios/associados` |
| D1-12 | `/associado/novo` → `cadastrar.php` | `index.php:673-674` | cadastro | `POST /api/v1/associados` |
| D1-13 | `/associado/{id}/editar` → `editar.php` | `index.php:683-687` | edição | `PATCH /api/v1/associados/{uuid}` |
| D1-14 | `/associado/{id}/excluir` → `excluir.php` | `index.php:695-696` | exclusão | `DELETE /api/v1/associados/{uuid}` (soft delete) |
| D1-15 | `/meu-perfil` → `meu_perfil.php` | `index.php:679-680` | autoatendimento | `GET/PATCH /api/v1/meu-perfil` |
| D1-16 | `/associacoes` → `associacoes/listar.php` | `index.php:702-703` | listar associações | `GET /api/v1/associacoes` |
| D1-17 | `/associacao/novo` → `associacoes/cadastrar.php` | `index.php:706-707` | criar associação | `POST /api/v1/associacoes` |
| D1-18 | `/associacao/editar` → `associacoes/editar.php` | `index.php:710-714` | editar associação | `PATCH /api/v1/associacoes/{uuid}` |
| D1-19 | `/associacao/associados` → `associacoes/associados.php` | `index.php:718-719` | vínculo/cargo | `GET/PATCH /api/v1/associacoes/{uuid}/associados` |
| D1-20 | `api/pre_cadastro.php` (direto) | `api/pre_cadastro.php:18-23` | pré-cadastro público | `POST /api/v1/pre-cadastro` |
| D1-21 | `api/ficha_adesao.php` (direto) | `api/ficha_adesao.php:29-133` | ficha (GET/POST/CSRF) | `GET/POST /api/v1/ficha-adesao` |
| D1-22 | `api/associados/listar.php` (direto) | `api/associados/listar.php:46-56` | listagem JSON | absorvido por `GET /api/v1/associados` |
| D1-23 | `carteirinhas_enviar_massa.php` | `index.php` (bulk) | envio em massa | `POST /api/v1/carteirinhas/enviar-em-massa` |

### 3.2 Ações e regras observadas

| Arquivo:linha | Regra/ação |
|---|---|
| `modulos/associados/cadastrar.php:33` | CSRF obrigatório |
| `modulos/associados/cadastrar.php:44-47` | nome, sobrenome, e-mail, CPF |
| `modulos/associados/editar.php:77` | CSRF na edição |
| `modulos/associados/pendentes.php:20-28` | aprovar/reprovar acesso (`associado`/`usuario`) |
| `modulos/associados/cadastros_pendentes.php:32-42` | aprovar/recusar ficha |
| `modulos/associados/excluir.php:63` | **`DELETE FROM associados`** — hoje é exclusão física |
| `modulos/associados/ficha_adesao_visualizar.php:30-36` | ações da ficha + CSRF |
| `modulos/associados/meu_perfil.php:95,112-118` | autoatendimento e gestão de vínculos |
| `api/pre_cadastro.php:18-23` | pré-cadastro público (nome/e-mail/senha/perfil) |
| `api/ficha_adesao.php:102-107` | consulta em `associados` e `usuarios` |
| `carteirinha_publica.php:9-16` | token público + `Carteirinha::validaToken` |
| `classes/Associado.php:57` | `INSERT INTO associados` |
| `classes/Associacao.php:32` | `INSERT INTO associacoes` |
| `classes/FichaAdesao.php:136,153` | leitura da ficha por associado/usuário |
| `classes/Carteirinha.php:17,30,57,61` | geração/validação de token |
| `login.php:95-111` | login lê `usuarios` e `associados` (acoplamento com K1) |

### 3.3 Tabelas e ownership

| Tabela | Uso |
|---|---|
| `associados` | cadastro/perfil/vínculo (52 colunas) |
| `associacoes` | organizações |
| `associado_associacao` | vínculo N:N + cargo |
| `ficha_adesao` | ficha (58 colunas) |
| `ficha_adesao_produtos` | produtos da ficha |
| `carteirinhas` | token/validade |
| `entidades` | referência institucional |

### 3.4 Gaps/decidir em D1

1. **Exclusão física** (`excluir.php:63`) precisa virar **soft delete + anonimização** (LGPD).
2. **Login acoplado** (`login.php:95-111`) — a fatia A de D1 deve apenas resolver `associadoId`;
   a migração de credenciais fica para a fatia C/D.
3. **Duas portas de ficha** (`ficha_adesao` por associado e por usuário) precisam de ADR.
4. `api/pre_cadastro.php` e `api/ficha_adesao.php` são públicos — rate limit, captcha e validação.
5. `associados` tem `tem_agroindustria`/`user_id`/`area_producao` (integração) — remover
   dependências cruzadas na F2.
6. `carteirinha_publica.php` usa token opaco; manter e testar invalidação.
7. `associacoes/excluir.php` existe, mas não há rota clara no roteador — confirmar se é código morto.

---

## 4. Critérios de aceite da paridade (por módulo)

- [ ] Toda rota/ação acima mapeada para endpoint ou decisão de descarte.
- [ ] Toda regra de cálculo/estado reproduzida e testada.
- [ ] Toda tela tem paridade visual/funcional aprovada por fiscal.
- [ ] Toda consulta usa `uuid`/referências do Kernel, sem JOIN cruzado.
- [ ] Exclusão de associado = soft delete + anonimização.
- [ ] `arquivo:linha` de origem e evidência de teste em cada linha.
- [ ] Matriz assinada pelo especialista de domínio e pelo fiscal de paridade.

---

## 5. Conclusão

O inventário de K2 e D1 está **completo o suficiente para a F2**. As lacunas de decisão estão
listadas acima e devem virar ADRs antes da implementação do módulo correspondente. **Nenhum código
de módulo foi alterado.**
