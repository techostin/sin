# 03 - Modelo de Dados e Ownership das Tabelas

> **Gerado por introspeccao direta do banco `sisagro`** (tabelas base, 48 FKs, 1 view),
> complementado por `sql/*.sql`, `prisma/schema.prisma` e `RELATORIO_MODULARIZACAO.md` Anexo B.
> Este e o **dicionario de dados oficial** do programa de migracao e a base do gate **G8**.

---

## 1. Numeros do schema

| Metrica | Valor |
|---|---|
| Tabelas base | 77 |
| Views | 1 |
| Chaves estrangeiras | 48 |
| Colunas | 1025 |
| Engines distintos | 1 |
| Collations distintas | 1 |

> Estado atual ja saneado: **InnoDB em 100%** e **uma unica collation** (`utf8mb4_unicode_ci`).
> A Fase 0 do legado ja corrigiu PKs ausentes, MyISAM e collations mistas. **Confirmar em
> staging antes de gerar a baseline do Prisma** — o saneamento so vale se estiver replicado.

---

## 2. Ownership das tabelas (regra das 5 leis)

Cada tabela tem **exatamente um modulo dono**. Um modulo **nunca** faz JOIN nem
UPDATE em tabela de outro modulo - usa `*Ref` do kernel ou publica evento.

| Modulo dono | Tabelas |
|---|---|
| **Kernel / Plataforma** | `usuarios`, `password_resets`, `auth_refresh_tokens`, `audit_log`, `notificacoes` |
| **Territorialidade & CNPO (M9)** | `estados`, `cidades`, `mesorregioes_rs`, `microrregioes_rs`, `cnpo`, `cnpo_atualizacoes` |
| **Associados & Quadro Associativo (M1)** | `associados`, `associacoes`, `associado_associacao`, `ficha_adesao`, `ficha_adesao_produtos`, `carteirinhas`, `entidades` |
| **Caderno de Campo (M2)** | `propriedades`, `propriedade_membros`, `lavouras`, `atividades_diarias`, `categorias_atividades`, `produtos`, `lotes_producao`, `lote_atividade`, `lote_insumo`, `ocorrencias_manejo`, `historico_conversao`, `culturas` |
| **Fiscalizacao & Conformidade (M3)** | `roteiros_inspecao`, `visitas_tecnicas`, `nao_conformidades`, `plano_manejo`, `plano_manejo_cultivos`, `certificacao_participativa`, `certificadoras` |
| **Financeiro (M4)** | `planos_mensalidades`, `mensalidades`, `pagamentos`, `caixa`, `contas` |
| **Producao Animal (M5)** | `animais`, `animal_lotes`, `manejo_animal_registros`, `controle_alimentacao_animal`, `controle_sanidade_animal`, `controle_reproducao_animal`, `ambiente_criacao_animal`, `ambiente_criacao_membros`, `densidades_maximas_animais` |
| **Agroindustria (M6)** | `agroindustrias`, `agroindustria_membros`, `agroindustria_existentes`, `agroindustria_atualizacoes`, `agro_setores`, `agro_categorias_atividades`, `agro_atividades_diarias`, `agro_ocorrencias`, `receitas_agroindustria`, `ingredientes_receita`, `ordens_producao`, `controle_higienizacao`, `rastreabilidade_insumos_processo` |
| **Suprimentos & Insumos (M7)** | `insumos`, `insumos_organicos_aprovados`, `fornecedores`, `fornecedor_unidades` |
| **Governanca, Eventos & Comunicacao (M8)** | `eventos`, `evento_presenca`, `assembleias`, `assembleia_pautas`, `assembleia_votos`, `participacoes` |
| **A decidir / descartar** | `caderno_campo`, `certificacao_produtos` |

> Tabelas em **A decidir / descartar** exigem ADR do `arquiteto-dados` (A2) na Fase 0:
> confirmar uso por varredura no codigo PHP, migrar dados remanescentes e so entao dropar.

---

## 3. Inventario das tabelas

| Tabela | Modulo dono | Engine | Collation | Linhas | Colunas | FKs |
|---|---|---|---|---:|---:|---:|
| `agroindustrias` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 2 | 21 | 1 |
| `agroindustria_atualizacoes` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 0 | 4 | 0 |
| `agroindustria_existentes` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 133 | 10 | 0 |
| `agroindustria_membros` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 1 | 6 | 1 |
| `agro_atividades_diarias` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 0 | 16 | 0 |
| `agro_categorias_atividades` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 6 | 8 | 0 |
| `agro_ocorrencias` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 0 | 13 | 0 |
| `agro_setores` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 6 | 8 | 0 |
| `ambiente_criacao_animal` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 0 | 14 | 0 |
| `ambiente_criacao_membros` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 2 | 6 | 1 |
| `animais` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 3 | 19 | 0 |
| `animal_lotes` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 3 | 10 | 0 |
| `assembleias` | Governanca, Eventos & Comunicacao (M8) | InnoDB | utf8mb4_unicode_ci | 0 | 12 | 0 |
| `assembleia_pautas` | Governanca, Eventos & Comunicacao (M8) | InnoDB | utf8mb4_unicode_ci | 0 | 8 | 1 |
| `assembleia_votos` | Governanca, Eventos & Comunicacao (M8) | InnoDB | utf8mb4_unicode_ci | 0 | 9 | 3 |
| `associacoes` | Associados & Quadro Associativo (M1) | InnoDB | utf8mb4_unicode_ci | 3 | 17 | 0 |
| `associados` | Associados & Quadro Associativo (M1) | InnoDB | utf8mb4_unicode_ci | 30 | 52 | 1 |
| `associado_associacao` | Associados & Quadro Associativo (M1) | InnoDB | utf8mb4_unicode_ci | 24 | 4 | 2 |
| `atividades_diarias` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 11 | 21 | 0 |
| `audit_log` | Kernel / Plataforma | InnoDB | utf8mb4_unicode_ci | 247 | 8 | 0 |
| `auth_refresh_tokens` | Kernel / Plataforma | InnoDB | utf8mb4_unicode_ci | 7 | 9 | 0 |
| `caderno_campo` | **A decidir** | InnoDB | utf8mb4_unicode_ci | 0 | 11 | 0 |
| `caixa` | Financeiro (M4) | InnoDB | utf8mb4_unicode_ci | 4 | 12 | 0 |
| `carteirinhas` | Associados & Quadro Associativo (M1) | InnoDB | utf8mb4_unicode_ci | 4 | 5 | 0 |
| `categorias_atividades` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 6 | 6 | 0 |
| `certificacao_participativa` | Fiscalizacao & Conformidade (M3) | InnoDB | utf8mb4_unicode_ci | 6 | 21 | 2 |
| `certificacao_produtos` | **A decidir** | InnoDB | utf8mb4_unicode_ci | 0 | 4 | 2 |
| `certificadoras` | Fiscalizacao & Conformidade (M3) | InnoDB | utf8mb4_unicode_ci | 7 | 20 | 1 |
| `cidades` | Territorialidade & CNPO (M9) | InnoDB | utf8mb4_unicode_ci | 5595 | 6 | 0 |
| `cnpo` | Territorialidade & CNPO (M9) | InnoDB | utf8mb4_unicode_ci | 26025 | 12 | 0 |
| `cnpo_atualizacoes` | Territorialidade & CNPO (M9) | InnoDB | utf8mb4_unicode_ci | 0 | 5 | 0 |
| `contas` | Financeiro (M4) | InnoDB | utf8mb4_unicode_ci | 4 | 5 | 0 |
| `controle_alimentacao_animal` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 0 | 14 | 2 |
| `controle_higienizacao` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 2 | 11 | 0 |
| `controle_reproducao_animal` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 0 | 15 | 1 |
| `controle_sanidade_animal` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 0 | 20 | 2 |
| `culturas` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 0 | 9 | 1 |
| `densidades_maximas_animais` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 11 | 7 | 0 |
| `entidades` | Associados & Quadro Associativo (M1) | InnoDB | utf8mb4_unicode_ci | 0 | 7 | 0 |
| `estados` | Territorialidade & CNPO (M9) | InnoDB | utf8mb4_unicode_ci | 28 | 6 | 0 |
| `eventos` | Governanca, Eventos & Comunicacao (M8) | InnoDB | utf8mb4_unicode_ci | 4 | 7 | 0 |
| `evento_presenca` | Governanca, Eventos & Comunicacao (M8) | InnoDB | utf8mb4_unicode_ci | 50 | 7 | 1 |
| `ficha_adesao` | Associados & Quadro Associativo (M1) | InnoDB | utf8mb4_unicode_ci | 5 | 58 | 2 |
| `ficha_adesao_produtos` | Associados & Quadro Associativo (M1) | InnoDB | utf8mb4_unicode_ci | 11 | 7 | 1 |
| `fornecedores` | Suprimentos & Insumos (M7) | InnoDB | utf8mb4_unicode_ci | 7 | 15 | 0 |
| `fornecedor_unidades` | Suprimentos & Insumos (M7) | InnoDB | utf8mb4_unicode_ci | 21 | 8 | 0 |
| `historico_conversao` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 3 | 10 | 0 |
| `ingredientes_receita` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 14 | 8 | 1 |
| `insumos` | Suprimentos & Insumos (M7) | InnoDB | utf8mb4_unicode_ci | 14 | 20 | 1 |
| `insumos_organicos_aprovados` | Suprimentos & Insumos (M7) | InnoDB | utf8mb4_unicode_ci | 250 | 17 | 0 |
| `lavouras` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 8 | 11 | 1 |
| `lotes_producao` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 9 | 14 | 0 |
| `lote_atividade` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 0 | 4 | 0 |
| `lote_insumo` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 0 | 7 | 0 |
| `manejo_animal_registros` | Producao Animal (M5) | InnoDB | utf8mb4_unicode_ci | 5 | 12 | 1 |
| `mensalidades` | Financeiro (M4) | InnoDB | utf8mb4_unicode_ci | 21 | 14 | 0 |
| `mesorregioes_rs` | Territorialidade & CNPO (M9) | InnoDB | utf8mb4_unicode_ci | 7 | 3 | 0 |
| `microrregioes_rs` | Territorialidade & CNPO (M9) | InnoDB | utf8mb4_unicode_ci | 35 | 4 | 1 |
| `nao_conformidades` | Fiscalizacao & Conformidade (M3) | InnoDB | utf8mb4_unicode_ci | 5 | 17 | 1 |
| `notificacoes` | Kernel / Plataforma | InnoDB | utf8mb4_unicode_ci | 364 | 10 | 0 |
| `ocorrencias_manejo` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 5 | 17 | 4 |
| `ordens_producao` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 0 | 15 | 2 |
| `pagamentos` | Financeiro (M4) | InnoDB | utf8mb4_unicode_ci | 0 | 16 | 0 |
| `participacoes` | Governanca, Eventos & Comunicacao (M8) | InnoDB | utf8mb4_unicode_ci | 0 | 8 | 0 |
| `password_resets` | Kernel / Plataforma | InnoDB | utf8mb4_unicode_ci | 1 | 6 | 0 |
| `planos_mensalidades` | Financeiro (M4) | InnoDB | utf8mb4_unicode_ci | 2 | 8 | 0 |
| `plano_manejo` | Fiscalizacao & Conformidade (M3) | InnoDB | utf8mb4_unicode_ci | 4 | 32 | 2 |
| `plano_manejo_cultivos` | Fiscalizacao & Conformidade (M3) | InnoDB | utf8mb4_unicode_ci | 11 | 12 | 1 |
| `produtos` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 13 | 16 | 0 |
| `propriedades` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 16 | 21 | 1 |
| `propriedade_membros` | Caderno de Campo (M2) | InnoDB | utf8mb4_unicode_ci | 16 | 6 | 1 |
| `rastreabilidade_insumos_processo` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 0 | 8 | 1 |
| `receitas_agroindustria` | Agroindustria (M6) | InnoDB | utf8mb4_unicode_ci | 3 | 10 | 0 |
| `roteiros_inspecao` | Fiscalizacao & Conformidade (M3) | InnoDB | utf8mb4_unicode_ci | 3 | 14 | 0 |
| `usuarios` | Kernel / Plataforma | InnoDB | utf8mb4_unicode_ci | 12 | 42 | 1 |
| `visitas_tecnicas` | Fiscalizacao & Conformidade (M3) | InnoDB | utf8mb4_unicode_ci | 12 | 33 | 4 |
| `_prisma_migrations` | **A decidir** | InnoDB | utf8mb4_unicode_ci | 3 | 8 | 0 |

### 3.1 Views

- `view_caderno_campo_consolidado` - decisao ADR-011: **nao migrar para o Prisma**; recriar como consulta testavel no repositorio. Manter durante a coexistencia.

---

## 4. Dicionario de dados

Legenda da coluna **Chave**: `PK` primaria - `UQ` unica - `FK` estrangeira - `IDX` indexada.

### Kernel / Plataforma

#### `usuarios`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(100)` | nao | - | - | - |
| `sobrenome` | `varchar(100)` | sim | - | `NULL` | - |
| `data_nascimento` | `date` | sim | - | `NULL` | - |
| `cpf` | `varchar(14)` | sim | - | `NULL` | - |
| `rg` | `varchar(20)` | sim | - | `NULL` | - |
| `registro_cnpo` | `int(11)` | sim | - | `NULL` | - |
| `foto_url` | `varchar(200)` | sim | - | `NULL` | - |
| `endereco` | `varchar(200)` | sim | - | `NULL` | - |
| `numero` | `varchar(10)` | sim | - | `NULL` | - |
| `complemento` | `varchar(100)` | sim | - | `NULL` | - |
| `bairro` | `varchar(100)` | sim | - | `NULL` | - |
| `cidade` | `varchar(100)` | sim | - | `NULL` | - |
| `estado` | `char(2)` | sim | - | `NULL` | - |
| `cep` | `varchar(9)` | sim | - | `NULL` | - |
| `email` | `varchar(100)` | nao | UNI | - | - |
| `senha` | `varchar(255)` | nao | - | - | - |
| `nivel` | `int(11)` | nao | - | `1` | - |
| `nivel_solicitado` | `tinyint(1)` | sim | - | `NULL` | - |
| `perfil_solicitado` | `varchar(50)` | sim | - | `NULL` | - |
| `acesso_confirmado_por` | `int(11)` | sim | - | `NULL` | - |
| `acesso_confirmado_em` | `datetime` | sim | - | `NULL` | - |
| `acesso_negado_motivo` | `varchar(255)` | sim | - | `NULL` | - |
| `status` | `enum('ativo','inativo','pendente')` | nao | - | `'ativo'` | - |
| `telefone` | `varchar(15)` | sim | - | `NULL` | - |
| `tipo` | `enum('admin','gestor','usuario')` | nao | - | `'usuario'` | - |
| `tipo_usuario` | `varchar(50)` | sim | - | `NULL` | - |
| `tem_agroindustria` | `tinyint(1)` | nao | - | `0` | - |
| `area_producao` | `set('animal','vegetal','agroindustria')` | nao | - | `''` | - |
| `orgao` | `varchar(100)` | sim | - | `NULL` | - |
| `setor` | `varchar(100)` | sim | - | `NULL` | - |
| `especialidade` | `varchar(100)` | sim | - | `NULL` | - |
| `nicho` | `varchar(100)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | sim | UNI | `NULL` | - |
| `created_at` | `datetime` | nao | - | `current_timestamp()` | - |
| `data_criacao` | `datetime` | sim | - | `NULL` | - |
| `ultimo_login` | `datetime` | sim | - | `NULL` | - |
| `data_atualizacao` | `datetime` | sim | - | `NULL` | on update current_timestamp() |
| `certificadora_id` | `int(11)` | sim | MUL | `NULL` | - |
| `notificacoes_ativas` | `tinyint(1)` | sim | - | `1` | - |
| `dias_antecedencia_evento` | `int(11)` | sim | - | `7` | - |
| `ultima_notificacao_evento` | `timestamp` | sim | - | `NULL` | - |

**Chaves estrangeiras:**
- `certificadora_id` -> `certificadoras.id`

#### `password_resets`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `email` | `varchar(100)` | nao | MUL | - | - |
| `token_hash` | `char(64)` | nao | MUL | - | - |
| `expires_at` | `datetime` | nao | - | - | - |
| `used` | `tinyint(1)` | nao | - | `0` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `auth_refresh_tokens`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `token_hash` | `varchar(64)` | nao | UNI | - | - |
| `user_type` | `enum('usuario','associado')` | nao | MUL | - | - |
| `user_id` | `int(11)` | nao | - | - | - |
| `expires_at` | `timestamp` | nao | MUL | `current_timestamp()` | on update current_timestamp() |
| `revoked_at` | `timestamp` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `user_agent` | `varchar(255)` | sim | - | `NULL` | - |
| `ip` | `varchar(45)` | sim | - | `NULL` | - |

#### `audit_log`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `user_id` | `int(11)` | sim | - | `NULL` | - |
| `acao` | `varchar(50)` | nao | MUL | - | - |
| `tabela` | `varchar(50)` | sim | MUL | `NULL` | - |
| `registro_id` | `int(11)` | sim | - | `NULL` | - |
| `descricao` | `varchar(500)` | sim | - | `NULL` | - |
| `ip` | `varchar(45)` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `notificacoes`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `usuario_id` | `int(11)` | nao | MUL | - | - |
| `tipo_usuario` | `enum('associado','sistema')` | nao | - | - | - |
| `tipo_notificacao` | `varchar(50)` | nao | - | - | - |
| `titulo` | `varchar(255)` | nao | - | - | - |
| `mensagem` | `text` | nao | - | - | - |
| `dados_adicional` | `longtext` | sim | - | `NULL` | - |
| `lida` | `tinyint(1)` | sim | MUL | `0` | - |
| `criada_em` | `timestamp` | nao | MUL | `current_timestamp()` | - |
| `lida_em` | `timestamp` | sim | - | `NULL` | - |

### Territorialidade & CNPO (M9)

#### `estados`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uf` | `char(2)` | nao | UNI | - | - |
| `nome` | `varchar(50)` | nao | - | - | - |
| `regiao` | `enum('Norte','Nordeste','Centro-Oeste','Sudeste','Sul','Exterior')` | nao | - | - | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

#### `cidades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(120)` | sim | - | `NULL` | - |
| `estado` | `int(11)` | sim | - | `NULL` | - |
| `ibge` | `int(7)` | sim | - | `NULL` | - |
| `ddd` | `varchar(3)` | sim | - | `NULL` | - |
| `microrregiao_id` | `int(11)` | sim | MUL | `NULL` | - |

#### `mesorregioes_rs`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(100)` | nao | - | - | - |
| `codigo_ibge` | `int(11)` | nao | UNI | - | - |

#### `microrregioes_rs`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `mesorregiao_id` | `int(11)` | nao | MUL | - | - |
| `nome` | `varchar(100)` | nao | - | - | - |
| `codigo_ibge` | `int(11)` | nao | UNI | - | - |

**Chaves estrangeiras:**
- `mesorregiao_id` -> `mesorregioes_rs.id`

#### `cnpo`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `tipo_entidade` | `varchar(40)` | sim | - | `NULL` | - |
| `entidade` | `varchar(255)` | sim | - | `NULL` | - |
| `pais` | `varchar(60)` | sim | - | `NULL` | - |
| `uf` | `varchar(10)` | sim | MUL | `NULL` | - |
| `cidade` | `varchar(120)` | sim | MUL | `NULL` | - |
| `situacao` | `varchar(20)` | sim | - | `NULL` | - |
| `cnpf_cnpj_nif` | `varchar(40)` | sim | - | `NULL` | - |
| `nome_produtor` | `varchar(255)` | sim | MUL | `NULL` | - |
| `escopo` | `text` | sim | - | `NULL` | - |
| `atividades` | `text` | sim | - | `NULL` | - |
| `contato` | `varchar(255)` | sim | - | `NULL` | - |

#### `cnpo_atualizacoes`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `arquivo` | `varchar(120)` | nao | - | - | - |
| `data_planilha` | `date` | sim | - | `NULL` | - |
| `total_registros` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

### Associados & Quadro Associativo (M1)

#### `associados`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(100)` | nao | - | - | - |
| `sobrenome` | `varchar(100)` | nao | - | - | - |
| `data_nascimento` | `date` | sim | - | `NULL` | - |
| `cpf` | `varchar(14)` | sim | UNI | `NULL` | - |
| `rg` | `varchar(20)` | sim | - | `NULL` | - |
| `registro_cnpo` | `int(11)` | sim | UNI | `NULL` | - |
| `foto_url` | `varchar(200)` | sim | - | `NULL` | - |
| `endereco` | `varchar(200)` | sim | - | `NULL` | - |
| `numero` | `varchar(10)` | sim | - | `NULL` | - |
| `complemento` | `varchar(100)` | sim | - | `NULL` | - |
| `bairro` | `varchar(100)` | sim | - | `NULL` | - |
| `cidade` | `varchar(100)` | sim | - | `NULL` | - |
| `estado` | `char(2)` | sim | - | `NULL` | - |
| `cep` | `varchar(9)` | sim | - | `NULL` | - |
| `email` | `varchar(100)` | sim | UNI | `NULL` | - |
| `telefone` | `varchar(15)` | sim | - | `NULL` | - |
| `senha` | `varchar(255)` | sim | - | `NULL` | - |
| `data_cadastro` | `timestamp` | sim | - | `NULL` | - |
| `ultima_atualizacao` | `timestamp` | sim | - | `current_timestamp()` | - |
| `nivel` | `int(11)` | nao | - | - | - |
| `ativo` | `tinyint(1)` | sim | - | `1` | - |
| `status` | `enum('ativo','inativo','pendente')` | sim | - | `'pendente'` | - |
| `tipo` | `enum('Admin','Gestor','Usuario')` | sim | - | `NULL` | - |
| `tipo_usuario` | `varchar(50)` | sim | - | `'agricultor'` | - |
| `tem_agroindustria` | `tinyint(1)` | nao | - | `0` | - |
| `area_producao` | `set('animal','vegetal','agroindustria')` | nao | - | `''` | - |
| `orgao` | `varchar(100)` | sim | - | `NULL` | - |
| `setor` | `varchar(100)` | sim | - | `NULL` | - |
| `especialidade` | `varchar(100)` | sim | - | `NULL` | - |
| `nicho` | `varchar(100)` | sim | - | `NULL` | - |
| `associacao_id` | `int(11)` | sim | - | `NULL` | - |
| `vinculo_base` | `int(11)` | sim | - | `NULL` | - |
| `vinculo` | `int(11)` | sim | - | `NULL` | - |
| `user_id` | `int(11)` | sim | UNI | `NULL` | - |
| `certificadora_id` | `int(11)` | sim | - | `NULL` | - |
| `tipo_certificacao` | `enum('OCS','SPG','AUDITORIA')` | nao | - | `'SPG'` | - |
| `tipo_associado` | `enum('Efetivo','Honorario','Benemerito','Dependente','Aspirante')` | sim | - | `'Efetivo'` | - |
| `status_associado` | `enum('Ativo','Inadimplente','Afastado','Desligado','Falecido')` | sim | - | `'Ativo'` | - |
| `profissao` | `varchar(100)` | sim | - | `NULL` | - |
| `data_admissao` | `date` | sim | - | `NULL` | - |
| `data_desligamento` | `date` | sim | - | `NULL` | - |
| `microrregiao_id` | `int(11)` | sim | MUL | `NULL` | - |
| `cluster_comunicacao` | `varchar(50)` | sim | MUL | `NULL` | - |
| `ficha_enviada` | `tinyint(1)` | sim | - | `0` | - |
| `ficha_aprovada` | `tinyint(1)` | sim | - | `0` | - |
| `ficha_aprovada_por` | `int(11)` | sim | - | `NULL` | - |
| `ficha_aprovada_em` | `datetime` | sim | - | `NULL` | - |
| `ficha_observacao` | `text` | sim | - | `NULL` | - |
| `notificacoes_ativas` | `tinyint(1)` | sim | - | `1` | - |
| `dias_antecedencia_evento` | `int(11)` | sim | - | `7` | - |
| `ultima_notificacao_evento` | `timestamp` | sim | - | `NULL` | - |

**Chaves estrangeiras:**
- `microrregiao_id` -> `microrregioes_rs.id`

#### `associacoes`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(255)` | nao | UNI | - | - |
| `sigla` | `varchar(20)` | sim | UNI | `NULL` | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `objetivo` | `text` | sim | - | `NULL` | - |
| `data_fundacao` | `date` | sim | - | `NULL` | - |
| `endereco` | `varchar(255)` | sim | - | `NULL` | - |
| `cidade` | `varchar(100)` | sim | - | `NULL` | - |
| `estado` | `varchar(50)` | sim | - | `NULL` | - |
| `cep` | `varchar(10)` | sim | - | `NULL` | - |
| `telefone` | `varchar(20)` | sim | - | `NULL` | - |
| `email` | `varchar(100)` | sim | UNI | `NULL` | - |
| `site` | `varchar(255)` | sim | - | `NULL` | - |
| `responsavel` | `varchar(100)` | sim | - | `NULL` | - |
| `cargo_responsavel` | `varchar(100)` | sim | - | `NULL` | - |
| `data_cadastro` | `timestamp` | nao | - | `current_timestamp()` | - |
| `status` | `varchar(20)` | sim | - | `'ativo'` | - |

#### `associado_associacao`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `associado_id` | `int(11)` | nao | PRI | - | - |
| `associacao_id` | `int(11)` | nao | PRI | - | - |
| `data_vinculo` | `datetime` | nao | - | `current_timestamp()` | - |
| `tipo_vinculo` | `enum('diretor','secretario','tesoureiro','comissao_educativa','comissao_etica','conselho_fiscal','presidente','vice-presidente','membro')` | sim | - | `'membro'` | - |

**Chaves estrangeiras:**
- `associado_id` -> `associados.id`
- `associacao_id` -> `associacoes.id`

#### `ficha_adesao`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | sim | UNI | `NULL` | - |
| `usuario_id` | `int(11)` | sim | UNI | `NULL` | - |
| `numero_cadastro` | `varchar(20)` | sim | - | `NULL` | - |
| `data_entrada` | `date` | sim | - | `NULL` | - |
| `tipo_associado` | `enum('agricultor_familiar','colaborador','honorario','institucional')` | sim | - | `NULL` | - |
| `nome_completo` | `varchar(200)` | sim | - | `NULL` | - |
| `nome_social` | `varchar(200)` | sim | - | `NULL` | - |
| `data_nascimento` | `date` | sim | - | `NULL` | - |
| `cpf` | `varchar(14)` | sim | - | `NULL` | - |
| `rg_orgao_emissor` | `varchar(100)` | sim | - | `NULL` | - |
| `sexo_genero` | `varchar(40)` | sim | - | `NULL` | - |
| `nacionalidade` | `varchar(60)` | sim | - | `NULL` | - |
| `estado_civil` | `varchar(40)` | sim | - | `NULL` | - |
| `escolaridade` | `varchar(60)` | sim | - | `NULL` | - |
| `logradouro` | `varchar(200)` | sim | - | `NULL` | - |
| `numero` | `varchar(20)` | sim | - | `NULL` | - |
| `complemento` | `varchar(200)` | sim | - | `NULL` | - |
| `bairro` | `varchar(100)` | sim | - | `NULL` | - |
| `municipio` | `varchar(100)` | sim | - | `NULL` | - |
| `estado` | `char(2)` | sim | - | `NULL` | - |
| `cep` | `varchar(9)` | sim | - | `NULL` | - |
| `zona` | `enum('rural','urbana','periurbana')` | sim | - | `NULL` | - |
| `latitude_longitude` | `varchar(80)` | sim | - | `NULL` | - |
| `georreferenciamento_sncr` | `varchar(120)` | sim | - | `NULL` | - |
| `telefone_whatsapp` | `varchar(20)` | sim | - | `NULL` | - |
| `telefone_alternativo` | `varchar(20)` | sim | - | `NULL` | - |
| `email` | `varchar(120)` | sim | - | `NULL` | - |
| `nome_propriedade` | `varchar(200)` | sim | - | `NULL` | - |
| `nirf_itr` | `varchar(30)` | sim | - | `NULL` | - |
| `area_total_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `area_cultivada_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `area_preservacao_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `situacao_fundiaria` | `enum('propria','arrendada','parceria','assentamento','outra')` | sim | - | `NULL` | - |
| `dap_caf` | `varchar(40)` | sim | - | `NULL` | - |
| `pessoas_familia` | `int(11)` | sim | - | `NULL` | - |
| `pessoas_trabalham` | `int(11)` | sim | - | `NULL` | - |
| `renda_mensal_estimada` | `decimal(12,2)` | sim | - | `NULL` | - |
| `tempo_producao_agroecologica` | `varchar(40)` | sim | - | `NULL` | - |
| `certificacao_organica` | `varchar(200)` | sim | - | `NULL` | - |
| `tipo_certificacao` | `enum('nao_possui','auditagem','ocs','opac')` | sim | - | `NULL` | - |
| `sistemas_producao` | `set('horticultura','fruticultura','olericultura','graos_cereais','pecuaria_leite','avicultura','apicultura','ervas_medicinais','plantas_ornamentais','agrofloresta_saf','silvicultura','aquicultura','beneficiamento','artesanato_rural','outros')` | sim | - | `NULL` | - |
| `canais_comercializacao` | `set('feiras_livres','paa_pnae','csa','ceasa','mercado_local','ecommerce')` | sim | - | `NULL` | - |
| `outra_organizacao` | `varchar(200)` | sim | - | `NULL` | - |
| `banco` | `varchar(80)` | sim | - | `NULL` | - |
| `agencia` | `varchar(20)` | sim | - | `NULL` | - |
| `conta` | `varchar(30)` | sim | - | `NULL` | - |
| `chave_pix` | `varchar(120)` | sim | - | `NULL` | - |
| `titular_conta` | `varchar(120)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `aceite_termos` | `tinyint(1)` | nao | - | `0` | - |
| `status` | `enum('rascunho','pendente','aprovada','recusada')` | nao | MUL | `'rascunho'` | - |
| `data_submissao` | `datetime` | sim | - | `NULL` | - |
| `data_analise` | `datetime` | sim | - | `NULL` | - |
| `analisado_por` | `int(11)` | sim | - | `NULL` | - |
| `parecer_diretoria` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

**Chaves estrangeiras:**
- `usuario_id` -> `usuarios.id`
- `associado_id` -> `associados.id`

#### `ficha_adesao_produtos`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `ficha_id` | `int(11)` | nao | MUL | - | - |
| `produto_cultura` | `varchar(150)` | sim | - | `NULL` | - |
| `variedade_especie` | `varchar(150)` | sim | - | `NULL` | - |
| `producao_estimada` | `varchar(80)` | sim | - | `NULL` | - |
| `unidade` | `varchar(40)` | sim | - | `NULL` | - |
| `destino_principal` | `varchar(150)` | sim | - | `NULL` | - |

**Chaves estrangeiras:**
- `ficha_id` -> `ficha_adesao.id`

#### `carteirinhas`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `token` | `varchar(64)` | nao | UNI | - | - |
| `validade` | `date` | nao | - | - | - |
| `gerada_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `entidades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | - |
| `nome` | `varchar(255)` | nao | - | - | - |
| `cnpj` | `varchar(18)` | sim | - | `NULL` | - |
| `tipo` | `varchar(255)` | sim | - | `NULL` | - |
| `endereco` | `varchar(255)` | sim | - | `NULL` | - |
| `telefone` | `varchar(20)` | sim | - | `NULL` | - |
| `email` | `varchar(255)` | sim | - | `NULL` | - |

### Caderno de Campo (M2)

#### `propriedades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `propriedade` | `varchar(255)` | nao | - | - | - |
| `area_total` | `decimal(10,2)` | sim | - | `NULL` | - |
| `endereco` | `varchar(255)` | sim | - | `NULL` | - |
| `cep` | `varchar(10)` | sim | - | `NULL` | - |
| `bairro` | `varchar(50)` | sim | - | `NULL` | - |
| `cidade` | `varchar(100)` | sim | - | `NULL` | - |
| `estado` | `char(2)` | sim | - | `NULL` | - |
| `latitude` | `varchar(20)` | sim | - | `NULL` | - |
| `longitude` | `varchar(20)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `nome` | `varchar(20)` | sim | - | `NULL` | - |
| `localizacao` | `varchar(20)` | sim | - | `NULL` | - |
| `tamanho` | `varchar(20)` | sim | - | `NULL` | - |
| `status` | `varchar(20)` | sim | - | `NULL` | - |
| `recursos_naturais` | `varchar(20)` | sim | - | `NULL` | - |
| `imagem_principal` | `varchar(255)` | sim | MUL | `NULL` | - |
| `imagem_secundaria` | `varchar(255)` | sim | - | `NULL` | - |
| `zona_amortecimento` | `text` | sim | - | `NULL` | - |

**Chaves estrangeiras:**
- `associado_id` -> `associados.id`

#### `propriedade_membros`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `propriedade_id` | `int(11)` | nao | MUL | - | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `propriedade_id` -> `propriedades.id`

#### `lavouras`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `char(36)` | sim | UNI | `NULL` | - |
| `propriedade_id` | `int(11)` | nao | MUL | - | - |
| `nome` | `varchar(100)` | nao | - | - | - |
| `tipo_cultura` | `varchar(100)` | sim | - | `NULL` | - |
| `area` | `decimal(10,2)` | sim | - | `NULL` | - |
| `data_plantio` | `date` | sim | - | `NULL` | - |
| `status` | `varchar(20)` | sim | - | `'planejada'` | - |
| `data_colheita_prevista` | `date` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `atualizado_em` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

**Chaves estrangeiras:**
- `propriedade_id` -> `propriedades.id`

#### `atividades_diarias`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `char(36)` | sim | UNI | `NULL` | - |
| `nome` | `varchar(255)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `data_atividade` | `datetime` | sim | - | `NULL` | - |
| `categotia_id` | `int(11)` | sim | - | `NULL` | - |
| `hora_inicio` | `time` | sim | - | `'00:00:00'` | - |
| `hora_fim` | `time` | sim | - | `'00:00:00'` | - |
| `lavoura_id` | `int(11)` | sim | - | `NULL` | - |
| `propriedade_id` | `int(11)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `status` | `enum('Pendente','Em andamento','Concluído')` | sim | - | `'Pendente'` | - |
| `categoria_id` | `int(11)` | sim | - | `NULL` | - |
| `tipo_atividade` | `enum('adubação','capina','irrigação','encanteramento','aplicação de bio insumo','colheita','plantio','poda')` | sim | - | `'adubação'` | - |
| `insumo_utilisado` | `varchar(100)` | sim | - | `NULL` | - |
| `imagem_diaria` | `varchar(255)` | sim | - | `NULL` | - |
| `observações` | `text` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | sim | - | `current_timestamp()` | - |
| `atualizado_em` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |
| `lote_id` | `int(11)` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |

#### `categorias_atividades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(100)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | sim | UNI | `NULL` | - |
| `cor` | `varchar(7)` | sim | - | `'#000000'` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `produtos`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `char(36)` | sim | UNI | `NULL` | - |
| `nome` | `varchar(255)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `tipo` | `varchar(100)` | sim | - | `NULL` | - |
| `unidade_medida` | `varchar(50)` | sim | - | `NULL` | - |
| `preco_unitario` | `decimal(10,2)` | sim | - | `NULL` | - |
| `quantidade_produzida` | `decimal(10,2)` | sim | - | `NULL` | - |
| `data_colheita` | `date` | sim | - | `NULL` | - |
| `lavoura_id` | `int(11)` | sim | - | `NULL` | - |
| `setor_id` | `int(11)` | sim | MUL | `NULL` | - |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `unidade_tipo` | `enum('propriedade','agroindustria','animal')` | sim | MUL | `NULL` | - |
| `unidade_id` | `int(11)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `lotes_producao`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `char(36)` | sim | UNI | `NULL` | - |
| `nome_lote` | `varchar(255)` | nao | - | - | - |
| `produto_id` | `int(11)` | nao | - | - | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `data_producao` | `date` | nao | - | - | - |
| `quantidade` | `decimal(10,2)` | nao | - | - | - |
| `unidade_medida` | `varchar(50)` | nao | - | - | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |
| `atividades_id` | `int(11)` | sim | - | `NULL` | - |
| `insumos_id` | `int(11)` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `atualizado_em` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

#### `lote_atividade`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `lote_id` | `int(11)` | nao | - | - | - |
| `atividade_id` | `int(11)` | nao | - | - | - |
| `data_registro` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `lote_insumo`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `lote_id` | `int(11)` | nao | - | - | - |
| `insumo_id` | `int(11)` | nao | - | - | - |
| `quantidade_utilizada` | `decimal(10,2)` | nao | - | - | - |
| `unidade_medida` | `varchar(50)` | nao | - | - | - |
| `data_aplicacao` | `date` | nao | - | - | - |
| `data_registro` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `ocorrencias_manejo`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `propriedade_id` | `int(11)` | nao | MUL | - | - |
| `lavoura_id` | `int(11)` | sim | MUL | `NULL` | - |
| `tipo` | `enum('Praga','Doença','Fisiológica/Nutricional','Climatológica','Outro')` | nao | MUL | - | - |
| `nome_comum` | `varchar(150)` | nao | - | - | - |
| `data_deteccao` | `date` | nao | MUL | - | - |
| `severidade` | `enum('Baixa','Média','Alta')` | nao | - | `'Baixa'` | - |
| `status` | `enum('Aberta','Em Correção','Resolvida','Fechada')` | nao | - | `'Aberta'` | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `acao_manejo` | `text` | sim | - | `NULL` | - |
| `insumo_utilizado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `foto_url` | `varchar(255)` | sim | - | `NULL` | - |
| `correcoes_realizadas` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |

**Chaves estrangeiras:**
- `lavoura_id` -> `lavouras.id`
- `propriedade_id` -> `propriedades.id`
- `associado_id` -> `associados.id`
- `insumo_utilizado_id` -> `insumos.id`

#### `historico_conversao`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `propriedade_id` | `int(11)` | sim | MUL | `NULL` | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `tipo_uso_solo` | `varchar(100)` | nao | - | - | - |
| `data_inicio` | `date` | nao | - | - | - |
| `data_fim` | `date` | sim | - | `NULL` | - |
| `status` | `enum('EM_CONVERSAO','ORGANICO','CONVENCIONAL')` | nao | - | `'EM_CONVERSAO'` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `culturas`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(100)` | nao | - | - | - |
| `tipo` | `varchar(50)` | sim | - | `NULL` | - |
| `ciclo_dias` | `varchar(50)` | sim | - | `NULL` | - |
| `epoca_plantio` | `varchar(200)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

**Chaves estrangeiras:**
- `associado_id` -> `associados.id`

### Fiscalizacao & Conformidade (M3)

#### `roteiros_inspecao`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(200)` | nao | - | - | - |
| `codigo` | `varchar(50)` | sim | UNI | `NULL` | - |
| `tipo` | `enum('Visita Inicial','Visita Regular','Visita de Renovação','Visita Surpresa','Auditoria Interna')` | nao | MUL | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `objetivo` | `text` | sim | - | `NULL` | - |
| `checklist` | `longtext` | nao | - | - | - |
| `pontuacao_minima` | `int(11)` | sim | - | `70` | - |
| `itens_criticos` | `longtext` | sim | - | `NULL` | - |
| `ativo` | `tinyint(1)` | sim | MUL | `1` | - |
| `versao` | `int(11)` | sim | - | `1` | - |
| `created_by` | `int(11)` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

#### `visitas_tecnicas`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `hashid` | `varchar(32)` | sim | UNI | `NULL` | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `propriedade_id` | `int(11)` | nao | MUL | - | - |
| `roteiro_id` | `int(11)` | nao | MUL | - | - |
| `data_visita` | `date` | nao | MUL | - | - |
| `hora_inicio` | `time` | sim | - | `NULL` | - |
| `hora_fim` | `time` | sim | - | `NULL` | - |
| `tecnico_responsavel_id` | `int(11)` | sim | MUL | `NULL` | - |
| `acompanhantes` | `text` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `condicoes_clima` | `varchar(100)` | sim | - | `NULL` | - |
| `resultado` | `longtext` | sim | - | `NULL` | - |
| `pontuacao` | `int(11)` | sim | - | `NULL` | - |
| `pontuacao_maxima` | `int(11)` | sim | - | `NULL` | - |
| `conformidade` | `enum('Conforme','Não Conforme','Parcialmente Conforme')` | nao | MUL | `'Conforme'` | - |
| `evidencias_fotos` | `text` | sim | - | `NULL` | - |
| `relatorio_url` | `varchar(255)` | sim | - | `NULL` | - |
| `assinatura_produtor_url` | `varchar(255)` | sim | - | `NULL` | - |
| `assinatura_tecnico_url` | `varchar(255)` | sim | - | `NULL` | - |
| `status` | `enum('Agendada','Realizada','Cancelada','Reagendada')` | nao | MUL | `'Agendada'` | - |
| `ciente` | `tinyint(1)` | nao | - | `0` | - |
| `ciente_em` | `datetime` | sim | - | `NULL` | - |
| `ciente_recusada` | `tinyint(1)` | nao | - | `0` | - |
| `ciente_recusada_motivo` | `text` | sim | - | `NULL` | - |
| `ciente_recusada_em` | `datetime` | sim | - | `NULL` | - |
| `gestor_ciente` | `tinyint(1)` | nao | - | `0` | - |
| `gestor_ciente_em` | `datetime` | sim | - | `NULL` | - |
| `gestor_ciente_user_id` | `int(11)` | sim | - | `NULL` | - |
| `gestor_ciente_user_table` | `varchar(20)` | sim | - | `NULL` | - |
| `motivo_cancelamento` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

**Chaves estrangeiras:**
- `propriedade_id` -> `propriedades.id`
- `associado_id` -> `associados.id`
- `tecnico_responsavel_id` -> `associados.id`
- `roteiro_id` -> `roteiros_inspecao.id`

#### `nao_conformidades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `visita_tecnica_id` | `int(11)` | nao | MUL | - | - |
| `item_checklist_id` | `int(11)` | sim | - | `NULL` | - |
| `descricao` | `text` | nao | - | - | - |
| `gravidade` | `enum('Baixa','Média','Alta','Crítica')` | nao | MUL | - | - |
| `categoria` | `varchar(100)` | sim | - | `NULL` | - |
| `acao_corretiva` | `text` | sim | - | `NULL` | - |
| `responsavel_correcao` | `varchar(200)` | sim | - | `NULL` | - |
| `prazo_correcao` | `date` | sim | MUL | `NULL` | - |
| `status` | `enum('Aberta','Em Correção','Resolvida','Não Resolvida','Cancelada')` | nao | MUL | `'Aberta'` | - |
| `data_resolucao` | `date` | sim | - | `NULL` | - |
| `evidencia_correcao_url` | `varchar(255)` | sim | - | `NULL` | - |
| `verificado_por` | `int(11)` | sim | - | `NULL` | - |
| `data_verificacao` | `date` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

**Chaves estrangeiras:**
- `visita_tecnica_id` -> `visitas_tecnicas.id`

#### `plano_manejo`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `propriedade_id` | `int(11)` | nao | MUL | - | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `ano_safra` | `varchar(9)` | nao | MUL | - | - |
| `titulo` | `varchar(200)` | nao | - | - | - |
| `versao` | `int(11)` | sim | - | `1` | - |
| `area_total_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `area_organica_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `area_conversao_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `area_convencional_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `descricao_solo` | `text` | sim | - | `NULL` | - |
| `topografia` | `enum('Plana','Ondulada','Montanhosa','Irregular')` | sim | - | `'Plana'` | - |
| `fonte_agua` | `text` | sim | - | `NULL` | - |
| `clima` | `text` | sim | - | `NULL` | - |
| `praticas_conservacao` | `text` | sim | - | `NULL` | - |
| `manejo_pragas` | `text` | sim | - | `NULL` | - |
| `manejo_doencas` | `text` | sim | - | `NULL` | - |
| `adubacao_verde` | `text` | sim | - | `NULL` | - |
| `rotacao_culturas` | `text` | sim | - | `NULL` | - |
| `compostagem` | `text` | sim | - | `NULL` | - |
| `biofertilizantes` | `text` | sim | - | `NULL` | - |
| `croqui_propriedade_url` | `varchar(255)` | sim | - | `NULL` | - |
| `fotos_propriedade` | `text` | sim | - | `NULL` | - |
| `status` | `enum('Rascunho','Em Análise','Aprovado','Reprovado','Revisão Necessária')` | sim | MUL | `'Rascunho'` | - |
| `aprovado_por` | `int(11)` | sim | - | `NULL` | - |
| `data_aprovacao` | `date` | sim | - | `NULL` | - |
| `data_reprovacao` | `date` | sim | - | `NULL` | - |
| `motivo_reprovacao` | `text` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |

**Chaves estrangeiras:**
- `associado_id` -> `associados.id`
- `propriedade_id` -> `propriedades.id`

#### `plano_manejo_cultivos`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `plano_manejo_id` | `int(11)` | nao | MUL | - | - |
| `cultura` | `varchar(100)` | nao | MUL | - | - |
| `variedade` | `varchar(100)` | sim | - | `NULL` | - |
| `area_cultivada_ha` | `decimal(10,2)` | sim | - | `NULL` | - |
| `data_plantio_prevista` | `date` | sim | - | `NULL` | - |
| `data_colheita_prevista` | `date` | sim | - | `NULL` | - |
| `producao_estimada_kg` | `decimal(10,2)` | sim | - | `NULL` | - |
| `tipo_semente` | `enum('Orgânica Certificada','Convencional Não Tratada','Crioula','Própria')` | sim | - | `'Orgânica Certificada'` | - |
| `justificativa_semente` | `text` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `plano_manejo_id` -> `plano_manejo.id`

#### `certificacao_participativa`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `certificadora_id` | `int(11)` | sim | MUL | `NULL` | - |
| `tipo_certificacao` | `enum('SPG','OCS','Rede Ecovida','Outro')` | nao | MUL | `'SPG'` | - |
| `numero_certificado` | `varchar(100)` | sim | - | `NULL` | - |
| `data_emissao` | `date` | sim | - | `NULL` | - |
| `data_validade` | `date` | sim | MUL | `NULL` | - |
| `status` | `enum('Ativa','Vencida','Suspensa','Em Conversão','Cancelada')` | sim | MUL | `'Em Conversão'` | - |
| `organismo_certificador` | `varchar(200)` | sim | - | `NULL` | - |
| `nome_grupo` | `varchar(200)` | sim | - | `NULL` | - |
| `documento_url` | `varchar(255)` | sim | - | `NULL` | - |
| `declaracao_compromisso_url` | `varchar(255)` | sim | - | `NULL` | - |
| `assinatura_arquivo` | `varchar(255)` | sim | - | `NULL` | - |
| `assinatura_data` | `datetime` | sim | - | `NULL` | - |
| `assinatura_por` | `varchar(100)` | sim | - | `NULL` | - |
| `data_renovacao` | `date` | sim | - | `NULL` | - |
| `dados_adicionais` | `longtext` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |

**Chaves estrangeiras:**
- `certificadora_id` -> `certificadoras.id`
- `associado_id` -> `associados.id`

#### `certificadoras`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `sigla` | `varchar(20)` | sim | - | `NULL` | - |
| `nome` | `varchar(255)` | nao | - | - | - |
| `cnpj` | `varchar(20)` | sim | - | `NULL` | - |
| `tipo` | `enum('Auditoria','SPG','OCS')` | sim | - | `'SPG'` | - |
| `registro_mapa` | `varchar(100)` | sim | - | `NULL` | - |
| `estado` | `varchar(2)` | sim | - | `'RS'` | - |
| `logo_url` | `varchar(255)` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |
| `endereco` | `varchar(255)` | sim | - | `NULL` | - |
| `cidade` | `varchar(120)` | sim | - | `NULL` | - |
| `uf` | `varchar(2)` | sim | - | `NULL` | - |
| `telefone` | `varchar(50)` | sim | - | `NULL` | - |
| `email` | `varchar(150)` | sim | - | `NULL` | - |
| `site` | `varchar(255)` | sim | - | `NULL` | - |
| `contato` | `varchar(200)` | sim | - | `NULL` | - |
| `logo_filename` | `varchar(255)` | sim | - | `NULL` | - |
| `gestor_id` | `int(11)` | sim | MUL | `NULL` | - |
| `dados_adicionais` | `longtext` | sim | - | `NULL` | - |

**Chaves estrangeiras:**
- `gestor_id` -> `usuarios.id`

### Financeiro (M4)

#### `planos_mensalidades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associacao_id` | `int(11)` | sim | MUL | `NULL` | - |
| `nome` | `varchar(150)` | nao | - | - | - |
| `tipo` | `enum('mensalidade','anuidade','joia')` | nao | - | `'mensalidade'` | - |
| `valor` | `decimal(10,2)` | nao | - | `0.00` | - |
| `dia_vencimento` | `tinyint(4)` | nao | - | `10` | - |
| `ativo` | `tinyint(1)` | nao | - | `1` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `mensalidades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | nao | PRI | - | auto_increment |
| `associado_id` | `bigint(20) unsigned` | nao | - | - | - |
| `associacao_id` | `int(11)` | sim | - | `NULL` | - |
| `plano_id` | `int(11)` | sim | - | `NULL` | - |
| `fonte` | `enum('manual','automatica')` | nao | - | `'manual'` | - |
| `ano_referencia` | `int(11)` | nao | - | - | - |
| `mes_referencia` | `int(11)` | nao | - | - | - |
| `valor_cobrado` | `decimal(10,2)` | nao | - | - | - |
| `data_vencimento` | `date` | nao | - | - | - |
| `data_pagamento` | `date` | sim | - | `NULL` | - |
| `status_mensalidade` | `enum('pendente','pago','atrasado','isenta','cancelada')` | nao | - | `'pendente'` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | sim | - | `NULL` | - |
| `updated_at` | `timestamp` | sim | - | `NULL` | - |

#### `pagamentos`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | nao | PRI | - | auto_increment |
| `associado_id` | `bigint(20) unsigned` | nao | - | - | - |
| `associacao_id` | `int(11)` | sim | - | `NULL` | - |
| `descricao_pagamento` | `varchar(255)` | nao | - | - | - |
| `valor` | `decimal(10,2)` | nao | - | - | - |
| `data_pagamento` | `date` | nao | - | - | - |
| `data_vencimento` | `date` | nao | - | - | - |
| `metodo_pagamento` | `varchar(255)` | nao | - | - | - |
| `status_pagamento` | `enum('pendente','pago','cancelado')` | nao | - | - | - |
| `referencia_type` | `varchar(255)` | nao | - | - | - |
| `referencia_id` | `bigint(20) unsigned` | nao | - | - | - |
| `comprovante_url` | `varchar(255)` | sim | - | `NULL` | - |
| `txid` | `varchar(255)` | sim | UNI | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | sim | - | `NULL` | - |
| `updated_at` | `timestamp` | sim | - | `NULL` | - |

#### `caixa`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associacao_id` | `int(11)` | sim | MUL | `NULL` | - |
| `tipo` | `enum('Entrada','Saida')` | nao | - | - | - |
| `descricao` | `varchar(255)` | nao | - | - | - |
| `categoria` | `varchar(100)` | sim | - | `NULL` | - |
| `valor` | `decimal(10,2)` | nao | - | `0.00` | - |
| `data` | `date` | nao | - | - | - |
| `comprovante_url` | `varchar(255)` | sim | - | `NULL` | - |
| `referencia_type` | `varchar(50)` | sim | - | `NULL` | - |
| `referencia_id` | `bigint(20) unsigned` | sim | - | `NULL` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `contas`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(100)` | nao | - | - | - |
| `tipo` | `enum('Receita','Despesa')` | nao | - | - | - |
| `valor` | `decimal(8,2)` | nao | - | - | - |
| `data` | `date` | nao | - | - | - |

### Producao Animal (M5)

#### `animais`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `nome_animal` | `varchar(100)` | nao | - | - | - |
| `especie` | `varchar(60)` | nao | - | - | - |
| `raca` | `varchar(60)` | sim | - | `NULL` | - |
| `identificacao` | `varchar(50)` | sim | - | `NULL` | - |
| `sexo` | `enum('M','F')` | sim | - | `NULL` | - |
| `data_nascimento` | `date` | sim | - | `NULL` | - |
| `status` | `enum('ATIVO','VENDIDO','ABATIDO','MORTO')` | nao | - | `'ATIVO'` | - |
| `origem_conversao` | `enum('ORGANICO','EM_CONVERSAO','CONVENCIONAL')` | nao | - | `'ORGANICO'` | - |
| `lote_id` | `int(11)` | sim | MUL | `NULL` | - |
| `peso_kg` | `decimal(8,2)` | sim | - | `NULL` | - |
| `tipo_entrada` | `enum('NASCIDO_PROPRIEDADE','COMPRADO_ORGANICO','COMPRA_CONVENCIONAL','TRANSFERIDO_OCS')` | sim | - | `NULL` | - |
| `fornecedor_origem` | `varchar(150)` | sim | - | `NULL` | - |
| `data_entrada` | `date` | sim | - | `NULL` | - |
| `observacoes_origem` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `animal_lotes`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `nome_lote` | `varchar(120)` | nao | - | - | - |
| `especie` | `varchar(60)` | sim | - | `NULL` | - |
| `categoria` | `varchar(80)` | sim | - | `NULL` | - |
| `quantidade` | `int(11)` | sim | - | `0` | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `manejo_animal_registros`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `animal_id` | `int(11)` | nao | MUL | - | - |
| `tipo_registro` | `enum('CLINICO','VACINACAO','ALIMENTACAO','REPRODUCAO','PARASITARIO','BEM_ESTAR','OBITO')` | nao | - | - | - |
| `data_registro` | `date` | nao | - | - | - |
| `descricao` | `varchar(500)` | sim | - | `NULL` | - |
| `produto_utilizado` | `varchar(150)` | sim | - | `NULL` | - |
| `dose_quantidade` | `varchar(60)` | sim | - | `NULL` | - |
| `periodo_carencia_horas` | `int(11)` | sim | - | `NULL` | - |
| `responsavel` | `varchar(100)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `animal_id` -> `animais.id`

#### `controle_alimentacao_animal`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `animal_id` | `int(11)` | sim | MUL | `NULL` | - |
| `lote_id` | `int(11)` | sim | MUL | `NULL` | - |
| `data_alimentacao` | `date` | nao | - | - | - |
| `tipo_alimento` | `enum('FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA')` | nao | - | - | - |
| `produto_utilizado` | `varchar(150)` | sim | - | `NULL` | - |
| `quantidade_kg` | `decimal(10,2)` | sim | - | `NULL` | - |
| `percentual_materia_seca` | `decimal(5,2)` | sim | - | `NULL` | - |
| `origem` | `enum('ORGANICO','EM_CONVERSAO','NAO_ORGANICO_AUTORIZADO')` | nao | - | `'ORGANICO'` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `lote_id` -> `animal_lotes.id`
- `animal_id` -> `animais.id`

#### `controle_sanidade_animal`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `animal_id` | `int(11)` | sim | MUL | `NULL` | - |
| `lote_id` | `int(11)` | sim | MUL | `NULL` | - |
| `data_tratamento` | `date` | nao | - | - | - |
| `tipo_tratamento` | `enum('VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL')` | nao | - | - | - |
| `produto_utilizado` | `varchar(150)` | sim | - | `NULL` | - |
| `motivo` | `varchar(250)` | sim | - | `NULL` | - |
| `dose_quantidade` | `varchar(60)` | sim | - | `NULL` | - |
| `via_aplicacao` | `varchar(40)` | sim | - | `NULL` | - |
| `responsavel` | `varchar(100)` | sim | - | `NULL` | - |
| `produto_normatizado` | `tinyint(1)` | nao | - | `1` | - |
| `periodo_carencia_horas` | `int(11)` | sim | - | `NULL` | - |
| `data_liberacao_organica` | `date` | sim | - | `NULL` | - |
| `aprovado_ocs` | `tinyint(1)` | sim | - | `NULL` | - |
| `numero_tratamento` | `int(11)` | sim | - | `1` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `animal_id` -> `animais.id`
- `lote_id` -> `animal_lotes.id`

#### `controle_reproducao_animal`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `animal_id` | `int(11)` | sim | MUL | `NULL` | - |
| `grupo_reprodutivo` | `varchar(80)` | sim | - | `NULL` | - |
| `tipo_evento` | `enum('COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME')` | nao | - | - | - |
| `data_evento` | `date` | nao | - | - | - |
| `macho_reprodutor_id` | `int(11)` | sim | - | `NULL` | - |
| `previsao_parto` | `date` | sim | - | `NULL` | - |
| `nascidos_vivos` | `int(11)` | sim | - | `NULL` | - |
| `natimortos` | `int(11)` | sim | - | `NULL` | - |
| `periodo_aleitamento_dias` | `int(11)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `animal_id` -> `animais.id`

#### `ambiente_criacao_animal`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `nome_instalacao` | `varchar(120)` | nao | - | - | - |
| `especie` | `enum('BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS','APICULTURA')` | sim | - | `NULL` | - |
| `sistema` | `enum('EXTENSIVO','SEMI_EXTENSIVO','INTENSIVO','ROTACIONADO')` | sim | - | `NULL` | - |
| `tipo_instalacao` | `varchar(80)` | sim | - | `NULL` | - |
| `area_total_m2` | `decimal(10,2)` | sim | - | `NULL` | - |
| `quantidade_animais` | `int(11)` | sim | - | `NULL` | - |
| `densidade_referencia` | `decimal(8,2)` | sim | - | `NULL` | - |
| `tem_sombreamento_abrigo` | `tinyint(1)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `ambiente_criacao_membros`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `ambiente_id` | `int(11)` | nao | MUL | - | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `ambiente_id` -> `ambiente_criacao_animal.id`

#### `densidades_maximas_animais`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `especie` | `varchar(40)` | nao | MUL | - | - |
| `fase` | `varchar(80)` | nao | - | - | - |
| `densidade` | `varchar(80)` | nao | - | - | - |
| `referencia` | `varchar(60)` | sim | - | `NULL` | - |
| `ativo` | `tinyint(1)` | nao | - | `1` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

### Agroindustria (M6)

#### `agroindustrias`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `razao_social` | `varchar(200)` | sim | - | `NULL` | - |
| `nome_fantasia` | `varchar(200)` | sim | - | `NULL` | - |
| `cnpj` | `varchar(18)` | sim | - | `NULL` | - |
| `inscricao_estadual` | `varchar(30)` | sim | - | `NULL` | - |
| `telefone` | `varchar(15)` | sim | - | `NULL` | - |
| `email` | `varchar(100)` | sim | - | `NULL` | - |
| `responsavel_tecnico` | `varchar(150)` | sim | - | `NULL` | - |
| `endereco` | `varchar(200)` | sim | - | `NULL` | - |
| `numero` | `varchar(10)` | sim | - | `NULL` | - |
| `bairro` | `varchar(100)` | sim | - | `NULL` | - |
| `cidade` | `varchar(100)` | sim | - | `NULL` | - |
| `estado` | `char(2)` | sim | - | `NULL` | - |
| `cep` | `varchar(9)` | sim | - | `NULL` | - |
| `status` | `enum('Ativa','Inativa')` | nao | MUL | `'Ativa'` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |
| `atualizado_em` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

**Chaves estrangeiras:**
- `associado_id` -> `associados.id`

#### `agroindustria_membros`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `agroindustria_id` | `int(11)` | nao | MUL | - | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `agroindustria_id` -> `agroindustrias.id`

#### `agroindustria_existentes`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `numero` | `int(11)` | sim | - | `NULL` | - |
| `agroindustria` | `varchar(200)` | sim | - | `NULL` | - |
| `municipio` | `varchar(100)` | sim | MUL | `NULL` | - |
| `processamento` | `varchar(255)` | sim | - | `NULL` | - |
| `data_inclusao` | `date` | sim | - | `NULL` | - |
| `peaf` | `varchar(30)` | sim | - | `NULL` | - |
| `licenciamento_sanitario` | `varchar(100)` | sim | - | `NULL` | - |
| `ultima_atualizacao` | `date` | sim | - | `NULL` | - |
| `organicos` | `varchar(60)` | sim | MUL | `NULL` | - |

#### `agroindustria_atualizacoes`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `arquivo` | `varchar(120)` | nao | - | - | - |
| `total_registros` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `agro_setores`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `varchar(36)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `agroindustria_id` | `int(11)` | sim | MUL | `NULL` | - |
| `nome` | `varchar(100)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `agro_categorias_atividades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `varchar(36)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `nome` | `varchar(100)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `cor` | `varchar(7)` | nao | - | `'#0d6efd'` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `agro_atividades_diarias`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `varchar(36)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `setor_id` | `int(11)` | sim | MUL | `NULL` | - |
| `categoria_id` | `int(11)` | sim | MUL | `NULL` | - |
| `nome` | `varchar(255)` | nao | - | - | - |
| `data_atividade` | `date` | nao | MUL | - | - |
| `hora_inicio` | `time` | sim | - | `NULL` | - |
| `hora_fim` | `time` | sim | - | `NULL` | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `status` | `varchar(50)` | nao | - | `'Concluída'` | - |
| `tipo_atividade` | `varchar(100)` | sim | - | `NULL` | - |
| `insumo_utilizado` | `varchar(255)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `agro_ocorrencias`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `varchar(36)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `setor_id` | `int(11)` | sim | MUL | `NULL` | - |
| `tipo` | `varchar(60)` | nao | - | `'Outro'` | - |
| `titulo` | `varchar(150)` | nao | - | - | - |
| `data_ocorrencia` | `date` | nao | MUL | - | - |
| `severidade` | `enum('Baixa','Média','Alta')` | nao | - | `'Baixa'` | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `acao_manejo` | `text` | sim | - | `NULL` | - |
| `status` | `varchar(50)` | nao | - | `'Aberta'` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `receitas_agroindustria`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome_produto_final` | `varchar(150)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `percentual_organico_calculado` | `decimal(5,2)` | nao | - | `0.00` | - |
| `selo_autorizado` | `enum('PRODUTO_ORGANICO_BRASIL','INGREDIENTES_ORGANICOS','SEM_SELO')` | nao | - | `'SEM_SELO'` | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `status` | `enum('Rascunho','Ativa','Inativa')` | nao | - | `'Rascunho'` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `ingredientes_receita`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `receita_id` | `int(11)` | nao | MUL | - | - |
| `nome_ingrediente` | `varchar(100)` | nao | - | - | - |
| `eh_organico` | `tinyint(1)` | nao | - | `1` | - |
| `quantidade` | `decimal(10,3)` | sim | - | `NULL` | - |
| `unidade_medida` | `varchar(10)` | sim | - | `'kg'` | - |
| `proporcao_porcentagem` | `decimal(12,3)` | nao | - | `0.000` | - |
| `observacoes` | `varchar(255)` | sim | - | `NULL` | - |

**Chaves estrangeiras:**
- `receita_id` -> `receitas_agroindustria.id`

#### `ordens_producao`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `receita_id` | `int(11)` | nao | MUL | - | - |
| `higienizacao_previa_id` | `int(11)` | sim | MUL | `NULL` | - |
| `lote_processado_final` | `varchar(50)` | nao | - | - | - |
| `data_processamento` | `datetime` | nao | - | - | - |
| `quantidade_final_obtida` | `decimal(10,2)` | nao | - | - | - |
| `unidade_medida` | `varchar(10)` | nao | - | `'KG'` | - |
| `status_auditoria` | `enum('APROVADO','ALERTA_FRAUDE','EM_ANALISE')` | nao | - | `'EM_ANALISE'` | - |
| `percentual_organico` | `decimal(5,2)` | sim | - | `NULL` | - |
| `selo_autorizado` | `varchar(30)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `receita_id` -> `receitas_agroindustria.id`
- `higienizacao_previa_id` -> `controle_higienizacao.id`

#### `controle_higienizacao`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `data_hora_inicio` | `datetime` | nao | - | - | - |
| `data_hora_fim` | `datetime` | sim | - | `NULL` | - |
| `equipamento_linha` | `varchar(100)` | nao | - | - | - |
| `sanitizante_utilizado` | `varchar(100)` | nao | - | - | - |
| `responsavel_nome` | `varchar(100)` | nao | - | - | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

#### `rastreabilidade_insumos_processo`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `ordem_producao_id` | `int(11)` | nao | MUL | - | - |
| `lote_primario_id` | `int(11)` | sim | - | `NULL` | - |
| `produto_id` | `int(11)` | sim | - | `NULL` | - |
| `insumo_id` | `int(11)` | sim | - | `NULL` | - |
| `numero_nf` | `varchar(30)` | sim | - | `NULL` | - |
| `quantidade_utilizada` | `decimal(10,2)` | nao | - | - | - |
| `unidade_medida` | `varchar(10)` | nao | - | `'KG'` | - |

**Chaves estrangeiras:**
- `ordem_producao_id` -> `ordens_producao.id`

### Suprimentos & Insumos (M7)

#### `insumos`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `uuid` | `char(36)` | sim | UNI | `NULL` | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `nome` | `varchar(255)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `fornecedor_id` | `int(11)` | sim | - | `NULL` | - |
| `estoq_anterior` | `int(11)` | sim | - | `NULL` | - |
| `quant_recebida` | `int(11)` | sim | - | `NULL` | - |
| `quantidade_estoque` | `int(11)` | sim | - | `NULL` | - |
| `tipo_insumo` | `enum('Orgânico','Em Conversão','Convencional','Não Permitido')` | sim | MUL | `'Orgânico'` | - |
| `aprovado_organico` | `tinyint(1)` | sim | MUL | `0` | - |
| `certificacao_insumo` | `varchar(200)` | sim | - | `NULL` | - |
| `insumo_aprovado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `restricoes` | `text` | sim | - | `NULL` | - |
| `composicao` | `text` | sim | - | `NULL` | - |
| `fabricante` | `varchar(200)` | sim | - | `NULL` | - |
| `lote` | `varchar(100)` | sim | - | `NULL` | - |
| `data_validade` | `date` | sim | - | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `atualizado_em` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

**Chaves estrangeiras:**
- `insumo_aprovado_id` -> `insumos_organicos_aprovados.id`

#### `insumos_organicos_aprovados`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(200)` | nao | MUL | - | - |
| `nome_comercial` | `varchar(200)` | sim | - | `NULL` | - |
| `fabricante` | `varchar(200)` | sim | - | `NULL` | - |
| `categoria` | `enum('Fertilizante','Defensivo','Adjuvante','Sementes','Mudas','Outros','Alimentação Animal','Suplemento Mineral','Aditivo Tecnológico','Sanidade Animal','Higienização','Apicultura','Aditivo Alimentar','Coadjuvante de Tecnologia')` | nao | MUL | - | - |
| `tipo` | `varchar(100)` | sim | MUL | `NULL` | - |
| `subcategoria` | `varchar(100)` | sim | - | `NULL` | - |
| `permitido` | `tinyint(1)` | sim | MUL | `1` | - |
| `restricoes` | `text` | sim | - | `NULL` | - |
| `normativa` | `varchar(100)` | sim | - | `'Portaria 52/2021'` | - |
| `data_inclusao_normativa` | `date` | sim | - | `NULL` | - |
| `composicao` | `text` | sim | - | `NULL` | - |
| `principio_ativo` | `varchar(200)` | sim | - | `NULL` | - |
| `observacoes` | `text` | sim | - | `NULL` | - |
| `fonte_referencia` | `varchar(255)` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

#### `fornecedores`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(255)` | nao | - | - | - |
| `razao_social` | `varchar(255)` | sim | - | `NULL` | - |
| `representante` | `varchar(255)` | sim | - | `NULL` | - |
| `cnpj` | `varchar(18)` | sim | UNI | `NULL` | - |
| `email` | `varchar(255)` | sim | UNI | `NULL` | - |
| `telefone` | `varchar(20)` | sim | - | `NULL` | - |
| `produto_fornecido` | `varchar(255)` | sim | - | `NULL` | - |
| `endereco` | `varchar(255)` | sim | - | `NULL` | - |
| `bairro` | `varchar(100)` | sim | - | `NULL` | - |
| `cidade` | `int(11)` | sim | - | `NULL` | - |
| `estado` | `int(11)` | sim | - | `NULL` | - |
| `status` | `enum('ativo','inativo')` | nao | - | `'ativo'` | - |
| `abrangencia` | `enum('Estadual','Municipal')` | nao | - | `'Municipal'` | - |
| `data_cadastro` | `datetime` | nao | - | `current_timestamp()` | - |

#### `fornecedor_unidades`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `fornecedor_id` | `int(11)` | nao | MUL | - | - |
| `unidade_tipo` | `enum('propriedade','agroindustria','animal')` | nao | MUL | - | - |
| `unidade_id` | `int(11)` | nao | - | - | - |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `excluido` | `tinyint(1)` | nao | - | `0` | - |
| `criado_por` | `int(11)` | sim | - | `NULL` | - |
| `criado_em` | `timestamp` | nao | - | `current_timestamp()` | - |

### Governanca, Eventos & Comunicacao (M8)

#### `eventos`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `nome` | `varchar(255)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `data_evento` | `datetime` | nao | - | - | - |
| `local` | `varchar(255)` | sim | - | `NULL` | - |
| `status` | `enum('Agendado','Realizado','Cancelado')` | nao | - | `'Agendado'` | - |
| `confirmados` | `text` | sim | - | `NULL` | - |

#### `evento_presenca`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `evento_id` | `int(11)` | nao | MUL | - | - |
| `associado_id` | `int(11)` | sim | MUL | `NULL` | - |
| `usuario_id` | `int(11)` | sim | - | `NULL` | - |
| `status_presenca` | `enum('Confirmado','Presente','Ausente','Justificado')` | nao | - | `'Confirmado'` | - |
| `confirmado_em` | `timestamp` | nao | - | `current_timestamp()` | - |
| `data_registro` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `evento_id` -> `eventos.id`

#### `assembleias`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `titulo` | `varchar(255)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `data_inicio` | `datetime` | nao | MUL | - | - |
| `data_fim` | `datetime` | nao | - | - | - |
| `associacao_id` | `int(11)` | sim | - | `NULL` | - |
| `status` | `enum('rascunho','ativa','encerrada','cancelada')` | sim | MUL | `'rascunho'` | - |
| `tipo_votacao` | `enum('simples','ponderada')` | sim | - | `'simples'` | - |
| `quorum_minimo` | `decimal(5,2)` | sim | - | `50.00` | - |
| `created_by` | `int(11)` | nao | - | - | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |
| `updated_at` | `timestamp` | nao | - | `current_timestamp()` | on update current_timestamp() |

#### `assembleia_pautas`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `assembleia_id` | `int(11)` | nao | MUL | - | - |
| `titulo` | `varchar(255)` | nao | - | - | - |
| `descricao` | `text` | sim | - | `NULL` | - |
| `tipo` | `enum('aprovacao','eleicao','outros')` | sim | - | `'aprovacao'` | - |
| `ordem` | `int(11)` | sim | - | `0` | - |
| `opcoes` | `longtext` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `assembleia_id` -> `assembleias.id`

#### `assembleia_votos`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `assembleia_id` | `int(11)` | nao | MUL | - | - |
| `pauta_id` | `int(11)` | nao | MUL | - | - |
| `associado_id` | `int(11)` | nao | MUL | - | - |
| `voto` | `longtext` | nao | - | - | - |
| `peso` | `decimal(10,2)` | sim | - | `1.00` | - |
| `ip` | `varchar(45)` | sim | - | `NULL` | - |
| `user_agent` | `text` | sim | - | `NULL` | - |
| `created_at` | `timestamp` | nao | - | `current_timestamp()` | - |

**Chaves estrangeiras:**
- `assembleia_id` -> `assembleias.id`
- `associado_id` -> `associados.id`
- `pauta_id` -> `assembleia_pautas.id`

#### `participacoes`

| Coluna | Tipo | Nulo | Chave | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int(11)` | nao | PRI | - | auto_increment |
| `evento_id` | `int(11)` | sim | - | `NULL` | - |
| `associado_id` | `int(11)` | sim | - | `NULL` | - |
| `data_inscricao` | `timestamp` | sim | - | `current_timestamp()` | - |
| `status_presenca` | `enum('pendente','confirmada')` | sim | - | `'pendente'` | - |
| `observacoes` | `varchar(200)` | sim | - | `NULL` | - |
| `participacao_evento` | `int(11)` | sim | UNI | `NULL` | - |
| `participacao_associado` | `int(11)` | sim | UNI | `NULL` | - |

---

## 5. Regras de migracao (Prisma)

1. **Toda migracao e aditiva e retrocompativel** enquanto o PHP escrever no banco. Nunca renomear nem dropar coluna que o PHP ainda le.
2. Toda coluna nova e **anulavel** ou tem **default** - nunca `NOT NULL` sem default.
3. Toda tabela nova precisa de `created_at` e `updated_at` (a sincronizacao offline depende de `updated_at` confiavel).
4. Toda tabela com dados regulatorios (diario de campo, insumos, balanco de massas) recebe `deleted_at` para *soft delete* (retencao de 3 anos).
5. `uuid CHAR(36) UNIQUE` e adicionado as entidades-nucleo (`associados`, `associacoes`, `propriedades`, `usuarios`) - habilita referencia entre servicos e elimina a ofuscacao de ID na URL.
6. Migracao so e aprovada (gate G8) se **aplicar e reverter** com sucesso sobre uma copia do dump real.
7. `prisma migrate` e a **unica** fonte de verdade do schema a partir da baseline. Os scripts `dev-utils/migrate_*.php` sao aposentados.
8. A separacao de identidade (Lei L3) cria `kernel_usuarios` com `senha`, `nivel`, `papeis` e `associado_id`; `associados` deixa de ser tabela de login.

