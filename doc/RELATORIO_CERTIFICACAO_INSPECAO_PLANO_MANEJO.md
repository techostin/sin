# Relatório Técnico — Certificação, Certificadoras, Inspeção e Plano de Manejo (SINPORG / PHP legado)

**Workspace:** \`C:\xampp\htdocs\app\` · **Banco:** \`sisagro\` (MySQL/MariaDB, utf8mb4)
**Escopo:** engenharia reversa dos módulos de Certificação Participativa, Certificadoras, Roteiros/Visitas de Inspeção, Não Conformidades, Plano de Manejo, Conformidade e Consulta CNPO.
**Método:** leitura integral dos arquivos citados + verificação empírica do schema e dos dados reais em \`sisagro\` via PHP CLI. Toda afirmação abaixo tem linha ou resultado de consulta correspondente. Nada foi inferido sem evidência.

---

## 0. Sumário executivo

| # | Achado | Severidade | Evidência |
|---|---|---|---|
| A1 | \`modulos/certificacao/cadastrar.php\` grava em 2 colunas **inexistentes** (\`data_vencimento\`, \`escopo\`) → INSERT/UPDATE sempre falha com PDOException | **Crítico** | \`cadastrar.php:85-87,104-106,170,179\`; \`SHOW COLUMNS certificacao_participativa\` não tem essas colunas |
| A2 | Assinaturas de visita (canvas base64) são gravadas em \`VARCHAR(255)\` → **truncadas em 255 chars**; imagem corrompida | **Crítico** | \`realizar.php:516-517\` + \`visitas_tecnicas.assinatura_*_url varchar(255)\`; banco: \`CHAR_LENGTH = 255\` em 4/4 visitas com assinatura |
| A3 | \`api/plano_manejo.php\` GET-por-id / PUT / DELETE **não checam dono** → IDOR em plano de manejo | **Crítico** | \`api/plano_manejo.php:102-126, 242-355, 409-422\` (sem \`associado_id\`) |
| A4 | Todas as 11 linhas de \`plano_manejo_cultivos.tipo_semente\` estão \`''\` (perda silenciosa por conversão de ENUM) | **Alto** | \`sql/_backup/fix_enum_mojibake_acentos.sql:23-25\`; banco: 11/11 vazias |
| A5 | 2 de 5 não conformidades têm \`status = ''\` → máquina de estados quebrada | **Alto** | banco; mecanismo em \`sql/fix_enum_acentos.sql:16-17\` |
| A6 | \`verificado_por\` é INT mas o API grava **nome do usuário** → grava \`0\` | **Alto** | \`api/nao_conformidades.php:194-195\`; banco: \`verificado_por = 0\` |
| A7 | UI oferece gravidade \`"Critica"\` (sem acento) e omite \`Média\`; ENUM exige \`'Crítica'\`/\`'Média'\` | **Alto** | \`nao_conformidades/listar.php:107,188,373-377\`; \`detalhe.php:166\` |
| A8 | API PUT de NC **ignora \`gravidade\`** — alteração da UI é descartada em silêncio | **Alto** | \`api/nao_conformidades.php:176-189\` |
| A9 | Rotas \`/roteiro-inspecao/editar/{id}\` e \`/roteiro-inspecao/ficha/{id}\` **não existem** no roteador → 404 | **Alto** | \`index.php:593-596\` vs \`templates/listar.php:141\`, \`templates/cadastrar.php:47\` |
| A10 | Multi-tenant por certificadora **não existe no PHP**: \`certificacao_participativa.certificadora_id\` = NULL em 6/6; \`certificadoras.gestor_id\` = NULL em 7/7 | **Alto** | banco; \`sql/popular_certificadora_id.sql:5-17\` |
| A11 | \`RoteiroInspecao::delete()\` é DELETE físico, mas FK \`visitas_tecnicas.roteiro_id\` é \`ON DELETE RESTRICT\` | **Médio** | \`RoteiroInspecao.php:105-108\`; \`migration.sql:1702\` |
| A12 | \`createVisita()\` não gera \`hashid\` → 7 de 12 visitas com \`hashid NULL\`; rota por hashid inutilizável | **Médio** | \`api/roteiros_inspecao.php:440-450\`; banco |
| A13 | \`createVisita()\` grava \`$_SESSION['user_id']\` (tabela \`usuarios\`) em FK para \`associados\` | **Médio** | \`api/roteiros_inspecao.php:448\`; FK \`visitas_tecnicas_ibfk_4\` |
| A14 | Soft-delete (\`excluido\`) existe nas tabelas mas **nenhuma query filtra** \`excluido = 0\`; DELETE do controller é físico | **Médio** | \`controller.php:607-647\`; grep \`excluido\` = 0 ocorrências no controller |
| A15 | Validação MIME do upload de assinatura é **código morto** (só extensão é checada) | **Médio** | \`controller.php:470-483\` |
| A16 | \`certificacao_produtos\` (tabela N:N) só é **lida**, nunca escrita → 0 linhas; produtos ficam em \`dados_adicionais\` JSON duplicado | **Médio** | \`cadastrar.php:137-143\`; banco: 0 linhas |
| A17 | WCAG/negócio: sem validação de área; \`area_organica_ha = 99999999.99\` gravado | **Médio** | banco \`plano_manejo.id=1,2\` |
| A18 | \`croqui_propriedade_url\` gravado com URL absoluta \`http://localhost/app/...\` → quebra em produção | **Médio** | \`upload_croqui.php:113\`; banco |
| A19 | Sem renovação automática, sem job de vencimento: status \`Vencida\` nunca é aplicado (6/6 = \`Ativa\`) | **Médio** | banco; \`api/certificacao/controller.php:184-189\` só filtra |
| A20 | Roteiro 1 tem \`peso\`/\`obrigatorio\` ausentes no JSON; "peso" do checklist **nunca é usado no cálculo** da pontuação | **Médio** | banco \`roteiros_inspecao\`; \`VisitaTecnica.php:165-184\` |
| A21 | \`conformidade\` divergente: visitas com \`pontuacao=60\` e \`conformidade='Parcialmente Conforme'\` (regra atual daria 'Não Conforme') | **Baixo** | banco; regra em \`VisitaTecnica.php:104-114\` |
| A22 | \`visitas_tecnicas.relatorio_url\` nunca é escrito (coluna morta) | **Baixo** | grep \`relatorio_url\` = 0 escritas |
| A23 | \`assinatura_data\`/\`assinatura_por\`/\`data_renovacao\` não estão no script \`sql/schema_organico_sprint1.sql\` (só na baseline Prisma) | **Baixo** | \`schema_organico_sprint1.sql:14-53\` vs \`migration.sql:505-508\` |
| A24 | \`api/certificacao/gerar_pdf.php\` usa template com **assinatura hardcoded "Dr. João Silva"** e logos placeholder | **Médio** | \`templates/certificado.php:405-417,466-467\` |
| A25 | \`certificadoras/controller.php\` (o do subdiretório) não valida CSRF, ao contrário do \`api/certificadoras.php\` | **Médio** | \`api/certificadoras/controller.php:39-69\` |
| A26 | 2 usuários não-admin sem \`certificadora_id\` (Everton #220 nível 3, Alvaro #221 nível 2), contrariando o doc SQL | **Baixo** | banco; \`popular_certificadora_id.sql:53\` |

---

## 1. Inventário, rotas e arquitetura

### 1.1 Roteamento
O front controller é \`index.php\` (1 arquivo, 71 KB). O \`.htaccess\` (\`.htaccess:16-24\`) faz fallback de tudo que não é arquivo/diretório real para \`index.php?url=$1\`. Arquivos \`.php\` existentes são servidos diretamente (por isso \`/api/roteiros_inspecao.php\`, \`/api/plano_manejo.php\`, \`/certificadoras_list.php\` funcionam fora do roteador).

Rotas mapeadas no roteador (todas com \`exit\`):

| Rota | Arquivo | Linha em \`index.php\` |
|---|---|---|
| \`/api/certificacao/controller.php\` | \`api/certificacao/controller.php\` | 63-66 |
| \`/api/plano-manejo\` | \`api/plano_manejo.php\` | 69-72 |
| \`/certificacoes\` | \`modulos/certificacao/listar.php\` | 75-78 |
| \`/insumos-aprovados\` | \`modulos/certificacao/insumos.php\` | 79-82 |
| \`/certificacao/listar\` | idem listar | 83-86 |
| \`/certificacao/novo\` | \`cadastrar.php\` | 89-92 |
| \`/certificacao/{id}/editar\` | \`editar.php\` → \`cadastrar.php\` | 95-99 |
| \`/certificacao/{id}/renovar\` | \`renovar.php\` | 102-106 |
| \`/certificacao/historico/{associado_id}\` | \`historico.php\` | 109-113 |
| \`/certificacao/{id}/certificado\` | \`certificado.php\` | 116-120 |
| \`/certificacao/certificado-preview\` | \`certificado.php\` | 123-126 |
| \`/planos-manejo\` | \`plano_manejo/listar.php\` | 129-132 |
| \`/plano-manejo/novo\` | \`plano_manejo/cadastrar.php\` | 133-136 |
| \`/plano-manejo/{id}/visualizar\` | \`visualizar.php\` | 137-141 |
| \`/plano-manejo/{id}/editar\` | \`editar.php\` | 142-146 |
| \`/plano-manejo/{id}/aprovar\` | \`aprovar.php\` | 147-151 |
| \`/plano-manejo/{id}/upload_croqui\` | \`upload_croqui.php\` | 152-156 |
| \`/visitas-tecnicas\` | \`visitas/listar.php\` | 551-554 |
| \`/visita-tecnica/agendar\` | \`visitas/agendar.php\` | 555-558 |
| \`/visita-tecnica/{id}/realizar\` | \`visitas/realizar.php\` | 561-565 |
| \`/visita-tecnica/{hashid}/realizar\` | idem | 568-572 |
| \`/visita-tecnica/{id}/relatorio\` | \`visitas/relatorio.php\` | 575-579 |
| \`/visita-tecnica/{hashid}/relatorio\` | idem | 582-586 |
| \`/roteiros-inspecao\` | \`templates/listar.php\` | 589-592 |
| \`/roteiro-inspecao/novo\` | \`templates/cadastrar.php\` | 593-596 |
| \`/nao-conformidades\` | \`nao_conformidades/listar.php\` | 599-602 |
| \`/nao-conformidade/acompanhar?id=\` | \`acompanhar.php\` | 603-606 |
| \`/nao-conformidade/{id}\` | \`detalhe.php\` | 607-611 |
| \`/relatorios-conformidade\` | \`modulos/conformidade/relatorios.php\` | 1158-1160 |
| \`/relatorios-conformidade/gerar\` | \`relatorios/gerar.php\` | 1161-1163 |
| \`/consulta-cnpo\` | \`modulos/consulta_cnpo/index.php\` | 1171-1173 |

**Rotas quebradas** (links gerados pela UI sem rota correspondente):
* \`/roteiro-inspecao/editar/{id}\` — \`templates/listar.php:141\` → cai em \`404.php\` (index.php:1175-1177). **Não há tela de edição de roteiro acessível**, embora \`templates/cadastrar.php:23-32\` e \`RoteiroInspecao::update()\` suportem edição.
* \`/roteiro-inspecao/ficha/{id}\` — \`templates/cadastrar.php:47\` → 404. \`templates/ficha_pdf.php\` (PDF da ficha impressa) só funciona se chamado com \`?id=\` direto.
* \`/certificacao/controller\` em \`plano_manejo/listar.php:209\` resolve para \`/api/certificacao/controller.php\` (existe) — OK.

### 1.2 Níveis de acesso
\`config/config.php:23-25\`: \`NIVEL_ADMIN=1\`, \`NIVEL_GESTOR=2\`, \`NIVEL_USUARIO=3\` (menor = mais privilégio).
\`User::hasPermission()\` (\`classes/User.php:114-141\`) rejeita nível não-numérico (correção B-04 anti-escalonamento), devolve \`true\` para nível 1 e \`$nivel <= $required_level\` caso contrário. \`isGestorOuAdmin()\` = \`hasPermission(NIVEL_GESTOR)\` (\`User.php:147-149\`).
\`User::getAssociadoIdResolvido()\` (\`User.php:299-329\`) resolve o associado em 4 caminhos: sessão do Portal → login pela tabela \`associados\` → \`usuarios.associado_id\` → \`associados.user_id\`.
\`ApiAuth\` (\`classes/ApiAuth.php\`) centraliza: \`ehStaff()\` (nivel>0 e <=2, linhas 84-88), \`ehDiretoriaExecutiva()\` (97-108), \`exigirProprioAssociado()\` (170-183). \`associadoId()\` (111-122) usa \`getAssociadoIdResolvido()\`.

### 1.3 Menu (superfície de navegação)
\`includes/menu.php\`: bloco "Produção Orgânica → Agricultor" (\`!hasPermission(NIVEL_GESTOR)\` + área vegetal, linhas 182-191) expõe Certificações, Minhas Visitas, Minhas NCs, Meu Plano. Bloco "Fiscalização" (\`hasPermission(NIVEL_GESTOR)\`, linhas 193-211) expõe Certificações, Certificadoras, Planos de Manejo, Insumos Aprovados, Visitas, Agendar Visita, Roteiros, Não Conformidades, Validação de Insumos, Relatórios Admin, Relatórios de Conformidade, Consulta CNPO. **Certificadoras é o único item de módulo que aponta para um \`.php\` na raiz** (\`/certificadoras_list.php\`, linha 199) e não para o roteador.

---

## 2. Domínio de certificação

### 2.1 Modelo de dados real
\`certificacao_participativa\` (baseline \`sinporg/prisma/migrations/0_baseline/migration.sql:492-521\`; script original \`sql/schema_organico_sprint1.sql:14-53\`):

| Coluna | Tipo | Observação |
|---|---|---|
| \`id\` / \`associado_id\` | INT | FK \`certificacao_participativa_ibfk_1\` → \`associados(id)\` ON DELETE CASCADE (\`migration.sql:1597\`) |
| \`certificadora_id\` | INT NULL | FK \`fk_certificadora\` → \`certificadoras(id)\` ON DELETE RESTRICT (\`migration.sql:1600\`). **NULL em 6/6 linhas** |
| \`tipo_certificacao\` | ENUM('SPG','OCS','Rede Ecovida','Outro') DEFAULT 'SPG' | |
| \`numero_certificado\` | VARCHAR(100) | |
| \`data_emissao\` / \`data_validade\` | DATE | |
| \`status\` | ENUM('Ativa','Vencida','Suspensa','Em Conversão','Cancelada') NULL DEFAULT 'Em Conversão' | |
| \`organismo_certificador\` | VARCHAR(200) | **texto livre** (\`OPAC COCEARGS\`, \`OPAC RAMA\`) |
| \`nome_grupo\` | VARCHAR(200) | |
| \`documento_url\`, \`declaracao_compromisso_url\` | VARCHAR(255) | |
| \`assinatura_arquivo\` (VARCHAR 255), \`assinatura_data\` (DATETIME), \`assinatura_por\` (VARCHAR 100), \`data_renovacao\` (DATE) | | **NÃO existem em \`schema_organico_sprint1.sql\`** — vieram por migração não versionada naquele script |
| \`dados_adicionais\` | LONGTEXT (CHECK json_valid) | \`{escopo, produtos[]}\` |
| \`observacoes\` | TEXT | |
| \`excluido\` | TINYINT(1) DEFAULT 0 | soft-delete **nunca usado** |

\`certificacao_produtos\` (\`migration.sql:524-533\`): \`(certificacao_id, produto_id, criado_em)\`, UNIQUE \`unique_cert_prod\`, FKs CASCADE. **0 linhas** — o módulo PHP nunca escreve nela.

### 2.2 Tipos de certificação
Declarados em 4 lugares com **conjuntos diferentes**:
* Schema: \`ENUM('SPG','OCS','Rede Ecovida','Outro')\`.
* \`api/certificacao/controller.php:275\`: \`['SPG','OCS','Rede Ecovida','Outro']\` — igual.
* \`modulos/certificacao/cadastrar.php:163-166\`: select só com **\`OCS\`** e **\`SPG\`** (rótulos trocados: "OCS - Organismo de Certificação Social", quando o correto é *Organização de Controle Social*; "SPG - Sistema Participativo de Garantia").
* \`modulos/certificacao/listar.php:51-54\`: filtro com os 4 valores.
* \`certificadoras.tipo\` é \`ENUM('Auditoria','SPG','OCS')\` — taxonomia **diferente** de \`certificacao_participativa.tipo_certificacao\` (não há 'Rede Ecovida'/'Outro' no lado da certificadora).

Base legal citada no código: Portaria MAPA 52/2021 (\`schema_organico_sprint1.sql:5-7\`, \`CertificadoOrganico.php:484,543\`), Lei 10.831/2003 (\`CertificadoOrganico.php:501\`, \`declaracao_compromisso.php:83,92\`), IN 46/2011 parcialmente ainda referenciada em \`ChecklistPadrao.php:6,39\`.

### 2.3 Ciclo de vida
Implementado de forma **parcialmente manual**:

1. **Emissão** — via \`api/certificacao/controller.php\` POST (JSON, exige \`NIVEL_GESTOR\`, linhas 51-61). \`status\` default \`'Em Conversão'\` (linha 286). Valida \`associado_id\` numérico (264-268) e \`tipo_certificacao\` na whitelist (275-280). **Não valida** \`data_validade > data_emissao\`, não valida unicidade de \`numero_certificado\`, não valida existência do \`associado_id\` (só a FK).
2. **Validade** — armazenada, e apenas *consultada*: \`controller.php:184-189\` filtra \`data_validade < CURDATE()\` (\`validade=vencidas\`) e \`BETWEEN CURDATE() AND +30 DAY\` (\`validade=proximas\`). **Não existe job, cron ou trigger que promova o status a \`Vencida\`.** Resultado no banco: 6/6 certificações com \`status = 'Ativa'\`, incluindo uma com \`data_validade\` 2027.
3. **Renovação** — \`modulos/certificacao/renovar.php\` (tela) + \`api/certificacao/controller.php:522-543\`. O fluxo é *upload do PDF assinado*, não um novo registro: a renovação sobrescreve \`data_emissao\`, \`data_validade\`, \`numero_certificado\`, força \`status = $_POST['status'] ?? 'Ativa'\` (\`renovar.php:182\` envia \`status=Ativa\`), seta \`data_renovacao = CURDATE()\` e **concatena** a observação \`"\n\n[Renovado em dd/mm/aaaa HH:ii] ..."\` (\`controller.php:533\`). **O histórico não é preservado como novo registro** — só o texto de observações; \`historico.php\` mostra apenas as linhas atuais da tabela.
4. **Suspensão / Cancelamento** — apenas valores do ENUM, sem transições validadas. O \`PUT\` (\`controller.php:342-421\`) aceita \`status\` livremente entre os valores do ENUM, de qualquer estado para qualquer estado, desde que o chamador seja gestor/admin.
5. **Exclusão** — \`DELETE\` é **físico** (\`controller.php:628\`), apesar de existir \`excluido\`. Nenhuma query do controller filtra \`excluido = 0\` (grep = 0 ocorrências).

### 2.4 Geração do certificado PDF
Duas implementações **concorrentes e divergentes**:

**(a) Moderna — \`classes/CertificadoOrganico.php\`** (mPDF, A4-Landscape):
* \`buscarPorCertificacaoId()\` (21-51): JOIN com \`associados\` trazendo \`certificadora_id\`, \`registro_cnpo\`, endereço.
* \`buscarPorFormulario()\` (56-99): pré-visualização antes de salvar; monta \`dados_adicionais\` com \`escopo\` + \`produtos\` resolvidos.
* \`buscarCertificadoraDoUsuario()\` (105-143): 1º \`usuarios.certificadora_id\`, fallback \`certificadoras.gestor_id\`. **No banco atual: \`gestor_id\` é NULL em 7/7 → esse fallback é código morto.**
* \`buscarCertificadoraDoAssociado()\` (152-197): 1º \`associados.certificadora_id\`, 2º match exato por \`certificadoras.sigla = organismo_certificador\` (172-178), 3º match parcial \`nome LIKE / sigla LIKE\` (181-189), 4º fallback usuário. **Verificado: \`'OPAC COCEARGS'\` casa exatamente com \`certificadoras.sigla\` (id 15) → a logo resolve por esse caminho, já que \`certificadora_id\` é NULL.**
* \`gerarNumeroProvisorio()\` (287-292): \`"SPG-{id}/{ano}"\` quando \`numero_certificado\` está vazio.
* \`montarDados()\` (240-285): monta produtor (nome/CPF formatado/CNPO/endereço), propriedades via \`buscarPropriedades()\` (317-325, lê \`propriedades\`), produtos via \`Produto::getByAssociadoId()\` (327-338).
* \`gerarHtml()\` (375-563): layout IBD-like; logo da certificadora à esquerda, selo \`uploads/selo_SisOrg.jpeg\` à direita (391-394), fallback \`assets/img/logo-organico-brasil.svg\` (384-388). Tabela de produtos (518-531), caixa de validade (533-536), texto legal (540-547) e **duas linhas de assinatura em branco** (responsável SPG/OCS e produtor) — 549-558.
* \`gerarPdf()\` (568-592): mPDF \`A4-L\`, margens 12 mm; nome do arquivo \`Certificado_Organico_{nome}_{Ymd}.pdf\`.
* Rota: \`modulos/certificacao/certificado.php\` — \`?format=html\` imprime preview com \`window.print()\` (81-134), senão devolve PDF inline (136-140). Permissão: \`NIVEL_GESTOR\` para preview (31-35); para PDF por id, checa \`associado_id\` do registro contra \`getAssociadoId()\` (62-72).

**(b) Legada — \`api/certificacao/gerar_pdf.php\`** (Dompdf):
* Autenticação corrigida com \`ApiAuth::exigirLogin()\` (linha 14) e \`exigirProprioAssociado()\` (29) — comentário nas linhas 12-13 registra o histórico de IDOR anônimo.
* Usa os templates \`modulos/roteiros_inspecao/templates/certificado.php\` e \`declaracao_compromisso.php\`.
* Grava PDFs em \`uploads/certificados/\` e \`uploads/declaracoes/\` (99-113) e devolve URL calculada com \`$basePath = '/app'\` hardcoded (116-121) — divergente de \`SITE_URL\`.
* **\`templates/certificado.php\` é um mock**: contém nomes fictícios ("Fazenda Vida Orgânica Ltda.", "BR-ORG-2026/00142", 11-40), placeholders visuais "Logo da Certificadora (Ex: IBD)" / "Selo SisOrg Brasil" (405-417) e uma assinatura **hardcoded "Dr. João Silva / Diretor de Certificação"** (466-467). \`modulos/roteiros_inspecao/templates/certificado.php:6-9\` documenta explicitamente que são MOCKs.

Há ainda uma **terceira** geração: \`relatorios/gerar_certificado.php\` (mPDF, A4 retrato; linhas 1-119), que resolve certificadora por \`Certificadora::findByGestor($_SESSION['user_id'])\` (linha 33) — **também código morto** dado \`gestor_id\` NULL — e cai em \`findByNamePartial(organismo_certificador)\` (36-38). O selo esperado é \`uploads/seals/selo_organico_br.png\` (linha 49), **caminho diferente** do \`uploads/selo_SisOrg.jpeg\` usado pela classe moderna.

### 2.5 Assinatura digital — **não é assinatura digital**
Não há criptografia, hash, certificado ICP-Brasil, carimbo de tempo ou \`openssl_sign\` em nenhum arquivo analisado. O que existe:

* **Certificado**: upload de PDF **já assinado fora do sistema** — \`api/certificacao/controller.php:428-566\`. Grava \`assinatura_arquivo\` (nome), \`assinatura_data = NOW()\`, \`assinatura_por = $_SESSION['user_nome']\` (508) e \`documento_url = SITE_URL/uploads/certificados/{nome}\` (505). Validação real: **apenas a extensão \`.pdf\`** (476-483). As linhas 470-473 calculam \`$allowedTypes\` e \`$mimeType\` via \`finfo\` e **nunca usam a variável** — validação MIME é código morto. Em caso de falha de BD, o arquivo é removido (556-559).
* **Visita**: assinatura manuscrita em canvas (\`visitas/realizar.php:360-364\`, \`signature_pad@4.0.0\`) convertida em dataURL base64 (\`realizar.php:516-517\`) e enviada ao PUT. \`VisitaTecnica::saveResult()\` grava a string crua em \`visitas_tecnicas.assinatura_produtor_url\` / \`assinatura_tecnico_url\`, ambas \`VARCHAR(255)\` (\`migration.sql:1538-1539\`).
  **Verificado no banco (sql_mode = \`NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION\` — sem \`STRICT_TRANS_TABLES\`):** as 4 visitas com assinatura têm \`CHAR_LENGTH(assinatura_produtor_url) = 255\` e \`= 255\` no técnico. Ou seja: **todas as assinaturas foram truncadas silenciosamente e estão corrompidas**. \`VisitaReport::getHtmlVisita()\` (265-271) e \`visitas/relatorio.php:168-181\` tentam renderizá-las como \`<img src="data:image/png;base64,...">\` — resultado é imagem quebrada.

---

## 3. Multi-tenant de certificadoras

### 3.1 Modelo
\`certificadoras\` (\`migration.sql:536-560\`; \`sql/create_certificadoras.sql:2-24\`): \`id, sigla(VARCHAR 20), nome, cnpj, tipo ENUM('Auditoria','SPG','OCS') DEFAULT 'SPG', registro_mapa, estado VARCHAR(2) DEFAULT 'RS', logo_url, endereco, cidade, uf, telefone, email, site, contato, logo_filename, gestor_id, dados_adicionais, created_at, updated_at\`. FK \`fk_certificadora_gestor\` → \`usuarios(id)\` ON DELETE SET NULL (\`migration.sql:1609\`).

Pontos de escopo (\`certificadora_id\`):
* \`usuarios.certificadora_id\` INT NULL — FK \`fk_usu_cert\` → \`certificadoras(id)\` ON DELETE SET NULL (\`migration.sql:1507,1514,1693\`).
* \`associados.certificadora_id\` INT NULL (\`migration.sql:358\`) — **sem FK declarada**.
* \`certificacao_participativa.certificadora_id\` — com FK (RESTRICT).
* \`associados.tipo_certificacao\` ENUM('OCS','SPG','AUDITORIA') DEFAULT 'SPG' — adicionada por \`dev-utils/migrate_conformidade.php:170-175\`.

### 3.2 Como o escopo é (ou não é) aplicado
**No PHP legado, não é aplicado em lugar nenhum.** \`certificadora_id\` é usado exclusivamente para **branding** (resolver a logo):
* \`CertificadoOrganico::buscarCertificadoraDoUsuario()\` (105-143) e \`buscarCertificadoraDoAssociado()\` (152-197).
* \`VisitaReport::resolverLogoCertificadora()\` (67-145), com a mesma cascata: associado da visita → usuário logado → \`gestor_id\`.
* \`relatorios/gerar_certificado.php:33-38\`.

Nenhum \`WHERE certificadora_id = ...\` existe em listagens, APIs ou relatórios dos módulos analisados. O único guard de escopo do projeto está no **stack novo (NestJS)**: \`sinporg/apps/api/src/modules/auth/guards/certificadora-scope.guard.ts:31-64\`, controlado por \`AUTH_CERTIFICADORA_OBRIGATORIA\` (default \`'false'\`, linha 35), com a justificativa de que os vínculos estão incompletos (linhas 24-28, 50-56).

### 3.3 Estado real dos vínculos (verificado no banco)
| Vínculo | Estado |
|---|---|
| \`certificadoras.gestor_id\` | **NULL em 7/7** |
| \`certificacao_participativa.certificadora_id\` | **NULL em 6/6** |
| \`associados.certificadora_id\` | 3 preenchidos (ids 28, 29, 31 — todos = 16 / OPAC RAMA) |
| \`usuarios.certificadora_id\` | 7 preenchidos: #7→13, #10→15, #11/#12/#14/#20/#58→16. **NULL para #8 (admin), #16 (admin), #110 (admin) — esperado — e também para #220 Everton (nível 3) e #221 Alvaro (nível 2)**, contrariando \`sql/popular_certificadora_id.sql:53\` ("0 usuarios de nivel <> 1 permanecem sem vinculo") |

Certificadoras existentes: ids 13–19 (OPAC Rede Ecovida, OPAC Litoral Norte, OPAC COCEARGS, OPAC RAMA, OCS Arroio do Meio, OCS Sapiranga, OCS Porto Alegre/Viamão), todas com \`logo_filename\` preenchido no padrão \`logo_<hex>_<ts>.jpeg\`. \`add_sigla.php\` foi o script ad-hoc que adicionou a coluna \`sigla\`.

### 3.4 CRUD de certificadoras — **duas APIs concorrentes**
| | \`api/certificadoras.php\` (raiz de \`api/\`) | \`api/certificadoras/controller.php\` |
|---|---|---|
| Usada por | \`certificadoras_list.php:101\`, \`certificadoras_cadastrar.php:52\`, \`certificadoras_editar.php:49,70\` | Nenhuma tela analisada (código órfão) |
| Auth | \`isLoggedIn\` + \`NIVEL_GESTOR\` (linha 93, 122, 151, 182) | \`isLoggedIn\` + \`NIVEL_ADMIN\` para POST/DELETE (40, 72) |
| CSRF | **Sim** (22-31), suporta túnel \`_method=PUT\` (18-20) | **Não** |
| Upload | \`ImageUploader\` em \`uploads/certificadoras/\`, 2 MB, 400×400, q=90 (64-89) | \`handleUpload()\` manual em \`uploads/logos_certificadoras/\`, só jpg/jpeg/png + \`getimagesize\` (87-113) |
| Nome do arquivo de logo | \`logo_<uniqid>_<time>.ext\` | \`logo_<time>_<uniqid>.ext\` |
| Body | JSON **ou** multipart (\`getInput()\`, 47-57) | só \`$_POST\` |
| DELETE | físico (181-187) | físico (71-84) |

Consequência: \`certificadoras.logo_url\` fica com \`SITE_URL/uploads/certificadoras/...\` (API nova) **ou** \`SITE_URL/uploads/logos_certificadoras/...\` (API antiga) — dois diretórios, e \`CertificadoOrganico::resolverLogoCertificadora()\` (203-235) procura em \`uploads/certificadoras/\` e \`uploads/\`, **não** em \`uploads/logos_certificadoras/\`.

---

## 4. Roteiros de inspeção

### 4.1 Template (tabela \`roteiros_inspecao\`)
\`migration.sql:1452-1472\` / \`schema_organico_sprint1.sql:159-191\`: \`id, nome(200), codigo(50) UNIQUE, tipo ENUM('Visita Inicial','Visita Regular','Visita de Renovação','Visita Surpresa','Auditoria Interna'), descricao, objetivo, checklist LONGTEXT NOT NULL (JSON), pontuacao_minima INT DEFAULT 70, itens_criticos LONGTEXT (JSON), ativo BOOL DEFAULT 1, versao INT DEFAULT 1, created_by INT, created_at, updated_at\`.

Estrutura canônica do checklist documentada em \`schema_organico_sprint1.sql:172\` e \`sql/README_SPRINT1.md:88-100\`:
\`\`\`json
[{"id":1,"categoria":"Documentação","item":"...","obrigatorio":true,"peso":10}]
\`\`\`
**Mas os dados reais não seguem**: o roteiro id=1 (checklist de 3.276 chars, 18 itens) tem apenas \`{id, item, categoria}\` — **sem \`peso\` e sem \`obrigatorio\`**. A criticidade está só em \`itens_criticos = [2,5,8,9,11,12]\`. Os 3 roteiros existentes têm \`codigo = NULL\`, \`versao = 1\`, \`created_by = NULL\`, todos \`tipo = 'Visita Regular'\` e \`pontuacao_minima = 70\`.

### 4.2 Tipos de visita
O ENUM tem 5 valores, mas:
* \`templates/cadastrar.php:81-86\` oferece 4 (sem "Auditoria Interna").
* \`templates/listar.php:79-84\` (modal) oferece 4.
* \`templates/listar.php:82\` usa \`"Visita de Renovação"\` (correto) — o valor estava corrompido como \`Visita de Renova├º├úo\` no banco e foi corrigido por \`_backup/fix_enum_mojibake_acentos.sql:27-30\` (verificado: banco atual = \`Visita de Renovação\`).

### 4.3 Pontuação
\`classes/VisitaTecnica.php:165-184\` — \`calculateScore()\`:

\`\`\`php
// pseudo-código exato
foreach ($resultado as $res) {
    $isNa = ($res['na'] === true || 'true' || '1');   // linha 172
    if (!$isNa) {
        $total++;                                      // 174
        if ($res['conforme'] === true || 'true' || '1') $conformes++;   // 175
    }
}
return $total > 0 ? round(($conformes / $total) * 100) : 0;   // 183
\`\`\`
**É uma contagem simples item-a-item; o campo \`peso\` do checklist é coletado pela UI, exibido ("Peso Total Acumulado", \`templates/cadastrar.php:132,252\`) e impresso na ficha (\`ficha_pdf.php:67,75,103,157\`), mas nunca entra em nenhuma fórmula.** Itens \`N/A\` são excluídos do denominador (172-173); itens respondidos sem campo \`conforme\` contam no denominador e não no numerador (175-179).

\`pontuacao_maxima\` = \`count(resultado com na != true)\` (\`VisitaTecnica.php:94-96\`), gravado como \`max(0,(int)$maxScore)\` (139). No banco, visitas antigas têm \`pontuacao_maxima = NULL\` (ids 1,2,3) e novas têm 34 e 20.

### 4.4 Itens críticos e resultado
\`VisitaTecnica::saveResult()\` (\`VisitaTecnica.php:49-160\`):
1. Constrói \`$itensCriticos\` a partir do mapa \`opts['checklist']\` (\`obrigatorio => true\`) — linhas 55-62.
2. Fallback: lê \`roteiros_inspecao.itens_criticos\` do roteiro da visita — 65-71.
3. Varre o resultado; se um item crítico veio \`conforme = false\` → \`$hasCriticalNonConformity = true\` (87-89).
4. **Regra de conformidade** (98-114):
\`\`\`
score >= 100                  -> 'Conforme'
score >= pontuacao_minima     -> 'Parcialmente Conforme'
score <  pontuacao_minima     -> 'Não Conforme'
se houver NC em item crítico  -> 'Não Conforme'  (sobrepõe tudo)
\`\`\`
   Comentário na linha 101 ("Abaixo de 100 → Parcialmente Conforme") está **desatualizado** em relação ao código.
5. UPDATE atômico em transação (116-159), forçando \`status = 'Realizada'\`; depois \`processNaoConformidades()\`; commit/rollback.
\`pontuacao_minima\` vem do roteiro via \`api/roteiros_inspecao.php:794\`.

**Inconsistência histórica verificada**: visitas id 1 e 2 têm \`pontuacao = 60\`, \`conformidade = 'Parcialmente Conforme'\`, com roteiro de \`pontuacao_minima = 70\`. Pela regra atual seriam \`'Não Conforme'\` → essas linhas foram gravadas por versão anterior do algoritmo.

### 4.5 Checklist padrão mesclado
\`classes/ChecklistPadrao.php\` define 27 itens fixos com \`id >= 1000\` (24-65), para não colidir com ids de roteiro:
* 1001-1005 Produção Orgânica / Unidade de Produção (1003 crítico).
* 1101-1110 Processamento Orgânico / Beneficiamento (1101,1102,1104,1105,1107,1109 críticos).
* 1201-1204 Documentação, Rastreabilidade e OCS/SPG (1202 crítico).
* 1301-1311 Produção Animal — Portaria 52/2021 Título III (1301,1302,1304,1305,1306,1309 críticos).

\`mesclar()\` (87-113) une o checklist do roteiro com os padrão, **deduplicando por texto normalizado** (\`preg_replace('/[^a-z0-9à-ú]/iu','')\`, linha 93).
Usado em: \`api/roteiros_inspecao.php:763-778\` (ao finalizar), \`visitas/realizar.php:78-89\` (ao renderizar), \`VisitaReport.php:211-222\` (no PDF) e \`visitas/relatorio.php:202-209\` + \`acompanhar.php:55-67\` (resolução de nomes).
**Risco**: como o \`id\` dos itens padrão é fixo e o de roteiro é sequencial a partir de 1, um roteiro novo criado com 1000+ itens colidiria. Além disso, a deduplicação é só por texto — um item do roteiro com texto idêntico a um padrão mas \`obrigatorio = false\` "mascara" a criticidade do item padrão.

### 4.6 Telas de roteiro
* \`templates/listar.php\` — DataTable via \`api/roteiros_inspecao.php?action=templates\` (107-110); coluna "Itens" mostra \`data.length\` (116-121); botões editar/excluir (126-131). DELETE via \`fetch(..., {method:'DELETE'})\` (150-153). Modal "Novo Template simplificado" (64-95) faz \`JSON.parse\` do textarea (174) — **sem try/catch: JSON inválido lança e o form morre silenciosamente**. Restrição: apenas \`NIVEL_GESTOR\` (14-18).
* \`templates/cadastrar.php\` — construtor de checklist dinâmico. Campos: nome, codigo, tipo, pontuacao_minima, descricao, objetivo (68-106); itens com \`item/categoria/peso/obrigatorio\` (195-237). Categorias fixas: Geral, Solo, Água, Insumos, Sementes, Manejo, Armazenamento, Documentação, Rastreabilidade (212-222). Os \`itemId\` são **reindexados de 1..N na submissão** (328: \`const itemId = index + 1\`), invalidando \`itens_criticos\` de roteiros editados cuja numeração mude. Insere 3 itens-exemplo por padrão (278-280). Edição carrega \`itens_criticos\` para marcar os críticos (286-294). POST em \`?action=templates\`.
* \`templates/ficha_pdf.php\` — mPDF A4 retrato, exige \`NIVEL_GESTOR\` (43-46), logo de \`SINPORG_LOGO_PATH\` em base64 (56-58), tabela de itens com colunas Peso/Crít./Conformidade/Observações (151-166), duas linhas de assinatura (168-177). Nota no rodapé: "Itens marcados como Crítico (Crít.) geram reprovação automática" (179).
* \`templates/declaracao_compromisso.php\` — HTML puro (Dompdf) com 5 cláusulas (92-97) e assinatura do produtor (108-112). Sem logos dinâmicos (o plano em \`api/certificadoras-plan.md:53-54\` previa injetar as logos e não foi implementado aqui).

---

## 5. Não conformidades

### 5.1 Modelo
\`nao_conformidades\` (\`migration.sql:1124-1148\` / \`schema_organico_sprint1.sql:271-310\`):
\`id, visita_tecnica_id (FK CASCADE), item_checklist_id INT NULL, descricao TEXT NOT NULL, gravidade ENUM('Baixa','Média','Alta','Crítica') NOT NULL, categoria VARCHAR(100), acao_corretiva TEXT, responsavel_correcao VARCHAR(200), prazo_correcao DATE, status ENUM('Aberta','Em Correção','Resolvida','Não Resolvida','Cancelada') DEFAULT 'Aberta', data_resolucao DATE, evidencia_correcao_url VARCHAR(255), verificado_por INT, data_verificacao DATE, observacoes TEXT, created_at, updated_at\`.

### 5.2 Geração automática
\`VisitaTecnica::processNaoConformidades()\` (\`VisitaTecnica.php:189-223\`), chamada dentro da transação de \`saveResult()\` (linha 152):
* Ignora itens \`na = true\` (192-193) e itens conformes (194).
* \`categoria\` vem do mapa \`checklistMap[itemId]['categoria']\`, default \`'Geral'\` (197-198).
* **Gravidade é derivada de forma binária**: \`!empty($info['obrigatorio']) ? 'Crítica' : 'Alta'\` (199). Os valores \`Baixa\` e \`Média\` **nunca são gerados** pelo sistema.
* Deduplicação: só insere se não existir NC com o mesmo \`(visita_tecnica_id, item_checklist_id)\` (201-205) — evita duplicar em re-save.
* \`descricao\` = observação do item sem tags; fallback \`'Identificado na inspeção técnica.'\` (206-209).
* Insert sempre com \`status = 'Aberta'\` (219).

### 5.3 Máquina de estados — \`api/nao_conformidades.php\`
Não há máquina de estados formal. O \`PUT\` (\`handlePut\`, 169-206) atualiza qualquer subconjunto de \`{status, acao_corretiva, responsavel_correcao, prazo_correcao, observacoes}\` sem validar transição:
\`\`\`php
if ($status) { $setParts[] = 'status = ?'; ... }              // 185 — sem whitelist!
if ($status === 'Resolvida') {                                 // 192
    $setParts[] = 'data_resolucao = NOW()';
    $setParts[] = 'verificado_por = ?';
    $params[]   = $_SESSION['user_nome'] ?? 'Sistema';          // 195  <-- STRING em coluna INT
}
\`\`\`
Bugs concretos:
1. **\`verificado_por\` recebe um nome, não um id** (195), e a coluna é INT → grava \`0\`. Verificado no banco: NCs 4, 5 e 6 têm \`verificado_por = 0\` com \`data_resolucao\` preenchida. \`detalhe.php:192\` e \`acompanhar.php:208\` exibem esse valor ("resolvida por **0**").
2. **\`gravidade\` não está na lista de campos atualizáveis** (176-180) → o select de gravidade das telas \`listar.php:373-377\` e \`detalhe.php:163-167\` tem efeito zero ao salvar (a UI nem envia por causa do filtro, mas mesmo se enviasse seria ignorado).
3. **Sem whitelist de status** → \`status\` arbitrário é gravado; se não casar com o ENUM, o MySQL (não-estrito) grava \`''\`.
4. **Sem checagem de dono/perfil** no PUT: qualquer usuário logado pode resolver NC de qualquer produtor (o GET filtra por \`associado_id\` em 70-73 e 131-134, mas o PUT não).
5. \`data_verificacao\` nunca é escrita.

**Verificação empírica do estado atual** (5 NCs, todas \`gravidade = 'Alta'\`):
| id | visita | item | status | data_resolucao | verificado_por | categoria | prazo |
|---|---|---|---|---|---|---|---|
| 2 | 1 | 1 | Aberta | — | — | NULL | NULL |
| 3 | 1 | 4 | Aberta | — | — | NULL | NULL |
| 4 | 1 | 5 | Resolvida | 2026-08-16 | **0** | NULL | NULL |
| 5 | 2 | 1 | **''** | 2026-07-21 | **0** | NULL | NULL |
| 6 | 2 | 5 | **''** | 2026-04-27 | **0** | NULL | 2026-04-29 |

Mecanismo confirmado para os \`status = ''\`: \`sql/fix_enum_acentos.sql:16-17\` fez \`MODIFY COLUMN status ENUM('Aberta','Em Correção','Resolvida','Não Resolvida','Cancelada')\`. Linhas que continham o rótulo mojibake \`'Em Corre├º├úo'\` deixaram de casar com o novo ENUM e o MySQL as converteu para o índice 0 = \`''\` (não-estrito). É perda de dado **silenciosa e reproduzível** em qualquer \`MODIFY COLUMN\` de ENUM.
\`categoria\` é NULL nas 5 → a coluna só passou a ser preenchida a partir de \`finalizarVisitaComClasse()\` (\`api/roteiros_inspecao.php:773-778\`); as NCs existentes vêm de caminho anterior.

### 5.4 Prazos
\`prazo_correcao\` é DATE, editável livremente, **sem SLA por gravidade** em nenhum ponto do código. O "vencido" é calculado em runtime em 4 lugares: \`api/nao_conformidades.php:107-110\` (KPI \`vencidas\`), \`listar.php:220-226\`, \`detalhe.php:110\`, \`acompanhar.php:118\`. Todos usam a mesma condição \`prazo_correcao < agora && status !== 'Resolvida'\` — mas **apenas o KPI da API considera \`status\` diferente de 'Resolvida'**; \`acompanhar.php:118\` chama \`strtotime(null)\` quando o prazo é NULL (na prática \`strtotime('')\` = \`false\`), sem quebrar, mas \`detalhe.php:110\` faz \`strtotime($nc['prazo_correcao'])\` sem checar NULL (só o ternário de exibição protege).

### 5.5 Ações corretivas, evidências e acompanhamento
* **Ação corretiva**: texto livre (\`acao_corretiva\`), editável em \`listar.php:380-382\`, \`detalhe.php:170-172\` e \`acompanhar.php:134-136\`.
* **Evidência**: upload de imagem por \`api/nao_conformidades.php:210-241\` — \`action=upload_evidencia\`, campo \`evidencia_foto\`, \`ImageUploader('uploads/evidencias/')\` (226), grava \`evidencia_correcao_url\` com o \`filepath\` relativo **e força \`status = 'Em Correção'\`** (233) — a única transição automática existente no módulo. Exibição em \`acompanhar.php:179-186\` via \`SITE_URL . '/' . evidencia_correcao_url\`. Verificado no banco: NC 5 tem \`uploads/evidencias/evidencia_nc_5_...jpg\` — formato compatível.
* **Acompanhamento**: \`acompanhar.php\` (tela dedicada, rota \`/nao-conformidade/acompanhar?id=\`) mostra requisito não atendido, propriedade, produtor, datas, prazo, plano de ação, uploader de evidência e o card "Conformidade Validada" com \`verificado_por\` + \`data_resolucao\` (203-214). O select de status só oferece "Resolvida" para admin/gestor (149-151) — porém isso é **apenas UI**: o PUT da API não valida essa regra.
* **Listagem**: \`listar.php\` com 4 KPIs lidos de \`json.kpis\` (256-261) e modal de edição (327-406). A coluna de gravidade usa o mapa \`GRAVIDADE_BADGE\` com chave \`'Critica'\` (188) e o filtro oferece \`'Critica'\` (107) — **divergente do ENUM \`'Crítica'\`**; e nenhuma das duas telas oferece \`'Média'\`. \`detalhe.php:166\` idem. Como a API ignora \`gravidade\` no PUT, o efeito prático hoje é apenas não encontrar NCs críticas no filtro.

---

## 6. Plano de manejo

### 6.1 Modelo
\`plano_manejo\` (\`migration.sql:1275-1314\` / \`schema_organico_sprint1.sql:59-117\`):
identificação (id, propriedade_id FK CASCADE, associado_id FK CASCADE, ano_safra VARCHAR(9), titulo VARCHAR(200), versao INT DEFAULT 1);
áreas (area_total_ha, area_organica_ha, area_conversao_ha, area_convencional_ha — DECIMAL(10,2));
caracterização (descricao_solo, topografia ENUM('Plana','Ondulada','Montanhosa','Irregular'), fonte_agua, clima);
práticas (praticas_conservacao, manejo_pragas, manejo_doencas, adubacao_verde, rotacao_culturas, compostagem, biofertilizantes);
documentação visual (croqui_propriedade_url VARCHAR(255), fotos_propriedade TEXT);
aprovação (status ENUM('Rascunho','Em Análise','Aprovado','Reprovado','Revisão Necessária') DEFAULT 'Rascunho', aprovado_por INT, data_aprovacao DATE, data_reprovacao DATE, motivo_reprovacao TEXT);
observacoes; created_at/updated_at; \`excluido\` TINYINT(1) DEFAULT 0 (**nunca usado**).

\`plano_manejo_cultivos\` (\`migration.sql:1317-1334\`): \`plano_manejo_id (FK CASCADE), cultura(100) NOT NULL, variedade(100), area_cultivada_ha, data_plantio_prevista, data_colheita_prevista, producao_estimada_kg, tipo_semente ENUM('Orgânica Certificada','Convencional Não Tratada','Crioula','Própria') DEFAULT 'Orgânica Certificada', justificativa_semente TEXT, observacoes, created_at\`.

### 6.2 Campos nunca capturados pela UI
Comparando o schema com os 3 pontos de escrita (\`cadastrar.php:537-577\`, \`editar.php:304-329\`, \`api/plano_manejo.php:184-221 e 328-340\`), ficam **órfãos**:
* \`area_total_ha\` — nenhuma tela envia (banco: NULL em 4/4).
* \`area_convencional_ha\` — nenhuma tela envia.
* \`fotos_propriedade\` — nenhuma tela envia.
* \`data_reprovacao\` — nunca escrita (só \`data_aprovacao\` em \`api/plano_manejo.php:307\`).
* Em \`plano_manejo_cultivos\`: \`variedade\`, \`data_plantio_prevista\`, \`data_colheita_prevista\`, \`producao_estimada_kg\`, \`justificativa_semente\` — **nenhum é enviado por nenhuma tela**, embora \`visualizar.php:175,178\` exiba variedade e expectativa (sempre vazios). O campo \`justificativa_semente\` é justamente o exigido pela Portaria 52/2021 para dispensa de semente orgânica certificada (\`schema_organico_sprint1.sql:139\`, \`README_SPRINT1.md:73-75\`).

### 6.3 Cultivos — perda de dados verificada
**Todas as 11 linhas de \`plano_manejo_cultivos\` têm \`tipo_semente = ''\`**, apesar do DEFAULT \`'Orgânica Certificada'\` e de \`api/plano_manejo.php:218,337\` sempre enviarem \`$cultivo['tipo_semente'] ?? 'Orgânica Certificada'\`.
Causa: \`sql/_backup/fix_enum_mojibake_acentos.sql:23-25\` fez \`MODIFY COLUMN tipo_semente VARCHAR(50)\` e depois \`MODIFY COLUMN tipo_semente ENUM('Orgânica Certificada',...)\`. Os valores existentes eram o mojibake \`'Org├ónica Certificada'\` (documentado em \`sinporg/prisma/BUG_ENUM_MOJIBAKE.md:15\`), que não casou com o novo ENUM → convertidos para o índice 0 = \`''\`. Mesmo mecanismo dos \`status = ''\` das NCs. O \`BUG_ENUM_MOJIBAKE.md:122-129\` afirma \`virou_vazio = 0\` **apenas para \`insumos\`** — não cobriu cultivos nem NCs.

### 6.4 Fluxo de criação/edição
* \`cadastrar.php\` — wizard de 5 passos (Identificação → Propriedade → Práticas → Cultivos → Finalização, linhas 75-96). Escopo de dados por perfil em 16-54: usuário comum só vê suas propriedades/associado (com avisos quando não há vínculo, 99-108); gestor/admin vê tudo. Payload JSON em \`api/plano_manejo.php\` (589-593). Erros de fetch mostram \`data.error\` (604).
* \`editar.php\` — carrega via \`GET /api/plano_manejo.php?id=\` (243-299), normaliza status (257-273), permite editar status **somente** para gestor/admin (\`$pode_editar_status\`, linha 13; hidden input para os demais, 80-87). **Porém não valida dono**: as listas de propriedades/associados são globais (23-26) e o PUT não checa \`associado_id\`.
* \`aprovar.php\` — exige \`NIVEL_GESTOR\` (8-12); select de parecer com 3 opções: Aprovado / Revisão Necessária / Reprovado (60-62); \`motivo_reprovacao\` (68); envia \`data_aprovacao = hoje\` se Aprovado e \`aprovado_por = $_SESSION['user_id']\` (93-94) via PUT. O \`then\` tem um comentário confessando que o PUT não estava implementado (104-107) e trata \`data.error === 'Não implementado'\` como sucesso.
* \`api/plano_manejo.php\` — POST cria sempre com \`status='Rascunho'\` e \`versao=1\` **hardcoded** (184); PUT (242-355) sempre inclui \`status = ?\` no SET (284) e só aceita mudança de status se \`hasPermission(NIVEL_GESTOR)\`, preservando o do banco caso contrário (270-281); \`normalizeStatusValue()\` (57-88) mapeia variantes sem acento/hífen/underscore. PATCH (360-404) é endpoint dedicado só de status. **Nenhum incrementa \`versao\`** — o campo existe, a UI o exibe (\`listar.php:169\`, \`visualizar.php:215\`) e permanece 1 em 4/4 planos.

### 6.5 Croqui
\`upload_croqui.php\`:
* \`validarCroqui()\` (41-72): máx 5 MB; aceita PDF por \`%PDF-\` magic bytes (53-60) e imagens por \`getimagesize\` restritas a JPEG/PNG/GIF/WEBP (63-71). É a validação de upload **mais robusta** dos módulos analisados.
* CSRF via \`CSRFProtection::validatePostRequest()\` (75).
* **Checagem de dono presente** (85-91): gestor sempre; senão compara \`$plano['associado_id']\` com \`$user->getAssociadoId()\`.
* Nome: \`croqui_{plano_id}_{time}_{bin2hex(random_bytes(4))}.{ext}\` (108) — aleatoriedade adequada.
* Grava \`croqui_propriedade_url = SITE_URL . '/uploads/planos_manejo/' . $fileName\` (113).
* Na listagem de planos e em \`editar.php\`/aprovar.php, a rota é \`/plano-manejo/{id}/upload_croqui\` (index.php:152-156).
* **Problema**: \`visualizar.php:195\` renderiza o croqui sempre com \`<img src>\` — para PDFs o browser mostra imagem quebrada (só \`upload_croqui.php:191-196\` distingue PDF de imagem).
* **URL absoluta**: os 4 planos do banco têm \`croqui_propriedade_url\` = \`http://localhost/app/uploads/planos_manejo/...\` — quebra em qualquer host diferente.

### 6.6 Dados reais
4 planos: \`area_total_ha\` NULL em todos; \`area_organica_ha\` = \`99999999.99\` (planos 1 e 2 — **sem validação de faixa**), 200.00 (3), 100.00 (4); status \`Em Análise\`, \`Aprovado\`, \`Revisão Necessária\`, \`Aprovado\`; \`aprovado_por\` = 13 apenas no plano 1 e \`data_aprovacao\` NULL em 4/4 (o PUT de \`aprovar.php\` grava \`data_aprovacao\`, mas os planos aprovados 2 e 4 não têm) → \`visualizar.php:217-218\` mostra "Aprovado por: Pendente / Data Aprovação: Pendente" mesmo para status Aprovado.

---

## 7. Conformidade / \`ReportConformidade\`

### 7.1 Arquitetura
\`classes/ReportConformidade.php\` (1.040 linhas) é um **catálogo de fontes de dados estático** (não há "score" de conformidade). Cada método público devolve a estrutura normalizada documentada nas linhas 5-15: \`{titulo, descricao, resumo[], tabelas[{titulo,colunas,campos,dados[]}]}\`.

Helpers (não há nenhum cálculo de score):
* \`statusConversao()\` 38-44, \`statusAnimal()\` 49-56, \`origemAnimal()\` 61-67.
* \`filtroPeriodo()\` 72-86 (BETWEEN / >= / <=).
* \`normalizarNome()\` 91-99 — \`mb_check_encoding\` + \`iconv ASCII//TRANSLIT//IGNORE\` + lowercase + \`preg_replace('/[^A-Za-z0-9]/',' ')\`. Usado só em \`validacaoInsumos\`.
* \`filtroAssociado()\` 105-110, \`filtroEnum()\` 115-120.
* \`catalogo()\` 973-1039 — 9 relatórios com \`{metodo, titulo, icone, descricao, filtros[]}\`.

### 7.2 Os 9 relatórios
| Slug | Método (linha) | Fonte | Regra/indicador |
|---|---|---|---|
| \`balanco-massas\` | \`balancoMassas\` 126-196 | \`ordens_producao\` + \`receitas_agroindustria\` + \`rastreabilidade_insumos_processo\` | **Divergência = \`abs(entrada − saída)/entrada × 100\`** (161); tolerância de 10% citada na descrição (182) mas **não aplicada** no código; conta \`APROVADO\` e \`ALERTA_FRAUDE\` (176-177) |
| \`trilha-auditoria\` | \`trilhaAuditoria\` 202-266 | \`audit_log\` LEFT JOIN \`usuarios\`/\`associados\` | \`LIMIT 5000\`; resumo com contagem por \`acao\` (245-248) |
| \`rastreabilidade\` | \`rastreabilidadeConsolidada\` 272-366 | \`ordens_producao\`, \`rastreabilidade_insumos_processo\`, \`lotes_producao\`, \`produtos\`, \`insumos\`, \`fornecedores\` | 2 tabelas; conta "Selo POB" por \`strpos(selo,'PRODUTO_ORGANICO')\` (349). **N+1**: uma query por ordem dentro do loop (315-327) |
| \`validacao-insumos\` | \`validacaoInsumos\` 372-483 | \`insumos\` + \`insumos_organicos_aprovados\` | Cruza por \`aprovado_organico\` (417); se 0, tenta match por nome normalizado no índice oficial (420-422) ou por \`insumo_aprovado_id\` (423-430); classifica **Aprovado / Restrito-Proibido / Não aprovado** (440-449). **O \`LEFT JOIN\` traz \`ia.id\` mas o \`SELECT\` não inclui \`insumo_aprovado_id\` → o ramo 423-430 nunca executa** (bug) |
| \`historico-conversao\` | \`historicoConversao\` 489-543 | \`historico_conversao\` + \`propriedades\` | 12/24 meses citados na descrição (529), não validados |
| \`manejo-animal\` | \`manejoAnimal\` 549-654 | \`animais\` + \`manejo_animal_registros\` | 2 tabelas, LIMIT 5000 nos registros |
| \`agroindustria\` | \`agroindustriaConsolidado\` 660-746 | \`receitas_agroindustria\`, \`ordens_producao\` | 2 tabelas |
| \`higienizacao\` | \`higienizacao\` 752-799 | \`controle_higienizacao\` | conta concluídas por \`data_hora_fim\` (772) |
| \`producao-animal\` | \`producaoAnimalConsolidado\` 805-968 | \`animal_lotes\`, \`controle_alimentacao_animal\`, \`controle_sanidade_animal\`, \`controle_reproducao_animal\` | 4 tabelas; classifica sanidade como "Anexo II" vs "Excepcional" por \`produto_normatizado\` (887,894); \`$nao_organico_ms = [15.0,20.0]\` declarado na linha 852 e **nunca usado**; LIMIT 5000 em cada tabela |

### 7.3 Telas e autorização
* \`modulos/conformidade/relatorios.php\` — catálogo em cards (100-118) com 3 botões: PDF, Excel, HTML (111-113), repassando a query string (38). Formulário de filtros (53-95) com período, status (11 opções misturando status de OP, conversão e animal, 66-74) e associado (só para gestor, 77-89). A variável \`$isGestor\` (24) é usada **somente para montar a lista de associados** — não força o filtro.
* \`modulos/conformidade/relatorios/gerar.php\` — valida login (21-25), valida slug contra o catálogo (31-34) e formato \`pdf|excel|html\` (36-39). **Força o escopo**: \`if (!$user->hasPermission(NIVEL_GESTOR)) $filtros['associado_id'] = (int)$user->getAssociadoId();\` (50-52). Nota: usa \`getAssociadoId()\` (não \`getAssociadoIdResolvido()\`), então o caminho \`associados.user_id\` não é coberto.
* \`gerarPDF()\` 73-135 — mPDF A4-L, cabeçalho por \`sinporg_report_header()\`, resumo em tabela, tabelas, **bloco de assinatura do responsável** (120-124) e rodapé; \`Output(..., 'I')\`.
* \`gerarExcel()\` 140-256 — PhpSpreadsheet, **uma aba por tabela**, logo via \`Drawing\`, cabeçalho verde \`1B5E20\`, auto-size, bloco de assinatura (241-246). Limitação: \`$ultimaLetra = chr(64 + $totalColunas)\` (168) **quebra acima de 26 colunas**.
* \`gerarHTML()\` 261-336 — página standalone com Bootstrap CDN e \`window.print()\`; interpola \`{$dados['titulo']}\`/\`{$dados['descricao']}\` **sem \`htmlspecialchars\`** (318-319) dentro de heredoc — os valores vêm do catálogo estático, então hoje não é vetor, mas é frágil.

**Conclusão da seção**: não há "score de conformidade" no sistema. As métricas existentes são: (a) \`visitas_tecnicas.pontuacao\` (percentual de itens conformes, seção 4.3); (b) \`ordens_producao.percentual_organico\` / \`receitas_agroindustria.percentual_organico_calculado\`; (c) a divergência do balanço de massas (161). Nenhuma dessas é consolidada em um índice por produtor ou por certificadora.

---

## 8. Consulta CNPO / PEAF

### 8.1 O que é
O CNPO é o **Cadastro Nacional de Produtores Orgânicos** do MAPA. A tela \`modulos/consulta_cnpo/index.php\` documenta o propósito e a limitação (linhas 36-49): o CNPO público divulga CPF/CNPJ **parcial** (ex.: \`***714.687***\`), por isso a confirmação é feita por **nome + município/UF + escopo**.

### 8.2 Integração externa
**Não há integração em tempo real.** O fluxo é *batch* offline:
* \`dev-utils/atualizar_cnpo.php\` — scraping da página oficial (const \`CNPO_PAGINA_URL\`, linha 22), regex do link \`cnpo-mapa-DD-MM-AAAA.xlsx\` (83), download via cURL ou \`file_get_contents\` (39-76), leitura por PhpSpreadsheet (110-114), **reload total**: cria \`cnpo_nova\` (116-117), insere pulando as 3 primeiras linhas (127-136), e faz \`RENAME TABLE cnpo TO cnpo_antiga, cnpo_nova TO cnpo\` (143-144).
* Idempotência: consulta \`cnpo_atualizacoes.arquivo\` antes de baixar (95-102) e registra \`(arquivo, data_planilha, total_registros)\` depois (157-158). Script sugerido para rodar "diariamente ou nos dias 05/15/25" (12-13) — **mas não há cron/agendador versionado no repositório**.
* Arquivos baixados em \`storage/externos/\` (25).

Tabela \`cnpo\` (\`migration.sql:576-594\`): \`tipo_entidade, entidade, pais, uf, cidade, situacao, cnpf_cnpj_nif, nome_produtor, escopo, atividades, contato\` com 3 índices. **23.232 linhas** (verificado).
Tabela \`cnpo_atualizacoes\` (\`migration.sql:597-605\`).

### 8.3 API
\`api/cnpo_consulta.php\`:
* Exige login (21-26) **e** \`NIVEL_GESTOR\` (29-33) — usuário comum não consulta.
* Só GET (35-39).
* Termo mínimo de 2 caracteres (46-50).
* Normalização tolerante a acentos: função PHP \`$normalizar\` (53-64) e, do lado SQL, \`$buildCol()\` (76-90) que encadeia \`REPLACE(LOWER(col),'á','a')...\` sobre \`nome_produtor\`, \`entidade\`, \`cidade\`, \`uf\`, \`tipo_entidade\` (69-95) — permite casar "Jose" com "JOSÉ".
* Filtros opcionais \`uf\`/\`cidade\`/\`tipo_entidade\` (102-113); \`ORDER BY uf, cidade, nome_produtor LIMIT 50\` (115).
* Retorna \`{data:[...], total:n}\`.
* **Risco de performance**: o \`REPLACE\` encadeado em ~20 níveis sobre 5 colunas impede uso de índice → full scan de 23 mil linhas por consulta.

**PEAF**: não existe nenhuma referência a "PEAF" no workspace (grep sem resultados). O que existe de "Plano/Programa" ligado ao MAPA é o Plano de Manejo Orgânico (seção 6).

### 8.4 Vínculo produtor ↔ CNPO
\`associados.registro_cnpo\` existe (baseline \`migration.sql\`, coluna presente em \`associados\`) e é lido em \`CertificadoOrganico.php:27,62,279,406-408\` (imprime "Registro CNPO" no certificado quando preenchido). **No banco, \`registro_cnpo\` é NULL em 100% dos associados amostrados** → a linha nunca aparece no certificado e **não há job/fluxo que vincule a linha do CNPO ao associado**. A consulta é puramente manual/informativa.

---

## 9. Tela por tela

Legenda de permissões: **A** = Admin (1), **G** = Gestor (2), **U** = Usuário (3). \`hasPermission(n)\` = nível ≤ n.

### 9.1 \`/certificacoes\` — \`modulos/certificacao/listar.php\`
* **Propósito**: listar certificações participativas com filtros.
* **Permissão**: qualquer logado (7-11); botão "Nova Certificação" só para \`$isAdmin = nivel <= NIVEL_GESTOR\` (14,28-32).
* **Campos/filtros**: Tipo (SPG/OCS/Rede Ecovida/Outro, 49-55), Status (Ativa/Em Conversão/Vencida/Suspensa/Cancelada, 59-66), Validade (próximas 30 dias / vencidas, 70-74).
* **Tabela**: DataTable com AJAX em \`/api/certificacao/controller.php\` (\`serverSide:false\`, 156-158). Colunas: ID, Tipo, Nº, Associado (oculta para usuário comum, 190), Status, Emissão, Validade (com cor por proximidade, 216-231), Organismo, Ações.
* **Ações** (244-327): Editar, Renovar, Histórico (admin); Detalhes (todos, \`visualizarCertificacao\`, 355-442); Ver Certificado (\`?format=html\`); Baixar assinado se \`row.is_signed && row.documento_url\` (295-303) — \`is_signed\` é calculado pelo controller em 234; para usuário comum, botão desabilitado "Aguardando assinatura" (304-311); Excluir (admin).
* **Regras/tabelas**: \`certificacao_participativa\` (+ JOIN \`associados\`). Filtro de tipo/status **só existe na contagem** (\`controller.php:204-207\`), **não na consulta paginada** (166-181) → o filtro de tipo e status não filtra a listagem real; o filtro de validade funciona porque é aplicado nas duas queries.
* **Bootstrap 5 vs jQuery**: usa \`$('#modal').modal('show')\` (367) e \`bootstrap.Modal\` (367) misturados.

### 9.2 \`/certificacao/novo\` e \`/certificacao/{id}/editar\` — \`cadastrar.php\` (+ \`editar.php\` = 4 linhas, \`require cadastrar.php\`)
* **Permissão**: \`NIVEL_GESTOR\` (21-27); \`$hasAccess=false\` bloqueia o POST e exibe a tela com alerta (219-221).
* **Campos** (\`$formFields\`, 156-186): \`associado_id\` (select de **todos** os associados, 49, 158), \`certificadora_id\` (select de todas as certificadoras, 53, 159), \`numero_certificado\`, \`tipo_certificacao\` (só OCS/SPG, 163-166), \`data_emissao\`, **\`data_vencimento\`**, \`status\` (Ativa/Suspensa/Cancelada/Vencida, 173-178), **\`escopo\`** (179-184) — repetido em bloco separado (262-272) → **o mesmo campo \`escopo\` é renderizado duas vezes** com o mesmo \`name\`.
* **BUG crítico**: a lista de colunas gravadas é \`['associado_id','certificadora_id','numero_certificado','tipo_certificacao','data_emissao','data_vencimento','status','escopo','documento_url','declaracao_compromisso_url','observacoes']\` (85-87 e 104-106). \`data_vencimento\` e \`escopo\` **não existem** em \`certificacao_participativa\` (verificado por \`SHOW COLUMNS\`). Como o formulário sempre envia os dois (ambos \`required\`), **todo INSERT e todo UPDATE falham** com "Unknown column", capturado em 126-129 e exibido como "Erro ao salvar: ...".
* Consequência: o único caminho funcional de criação hoje é a API JSON (\`/api/certificacao/controller.php\` POST) — não há UI que a use.
* **Outros problemas**: \`$dados = $_POST\` gravado direto (sem sanitização além do bind); no INSERT as colunas são montadas por \`isset\` mas **os placeholders são montados em paralelo ao array de colunas** (108-118) — se um campo do meio faltar, colunas e valores desalinham (hoje não ocorre porque todos os campos são \`required\` no HTML, mas \`status\`/\`documento_url\`/\`observacoes\` podem faltar se o HTML mudar); \`certificadora_id\` é enviado mas o POST \`status\` pode ser string vazia; não há CSRF no \`fetch\` da API (o controller exige!).
* **Leitura de produtos**: \`certificacao_produtos\` é consultada em 137 com \`try/catch\` silencioso (140-143) e o resultado (\`$produtosSelecionados\`) **nunca é usado no HTML** — vestígio.
* **\`certificadoras\` no select**: nenhuma filtragem por usuário/certificadora; um gestor da OPAC RAMA pode emitir certificado em nome da OPAC COCEARGS (não há guard).
* **Mensagem/pós-salvamento**: após sucesso não há redirect; o formulário continua com os dados de \`$certificacao\` antigos (a query de reload não é refeita).

### 9.3 \`/certificacao/{id}/renovar\` — \`renovar.php\`
* **Permissão**: logado (8-12) + anti-IDOR: \`isGestorOuAdmin()\` ou dono (\`associado_id\` da cert == \`getAssociadoIdResolvido()\`, 37-43).
* **Campos**: data_emissao (default hoje), data_validade (default +1 ano), numero_certificado, **arquivo_assinatura (PDF, obrigatório)**, observações (85-115). Hidden: id, associado_id, tipo_certificacao.
* **JS** (150-205): valida extensão PDF (163-166) e tamanho ≤10 MB (169-172); \`formData.set('action','upload_assinatura')\` e \`formData.set('status','Ativa')\` (181-182); POST em \`/api/certificacao/controller.php\`.
* **Backend**: \`handleUploadAssinatura\` (\`controller.php:428-566\`) — ver 2.5. O limite de 10 MB do front não é aplicado no server (só \`upload_max_filesize\` do PHP).
* **Efeito**: sobrescreve o registro; não cria histórico (ver 2.3-3).

### 9.4 \`/certificacao/historico/{associado_id}\` — \`historico.php\`
* **Permissão**: logado (8-12) + gestor/admin ou o próprio associado (23-30).
* Lista todas as certificações do associado (40-44) em tabela (68-100) e em "Linha do Tempo" (105-117).
* **Bug de ordem**: \`includes/header.php\` e \`menu.php\` são incluídos **antes** da checagem de login (3-5) e o código usa \`$user\` sem instanciá-lo — depende de o header criar \`$user\` globalmente.
* **XSS**: \`$associado['nome']\` (55), \`$cert['numero_certificado']\`, \`$cert['status']\`, \`$cert['observacoes']\` (89-95) são impressos sem \`htmlspecialchars\`.

### 9.5 \`/certificacao/{id}/certificado\` e \`/certificacao/certificado-preview\` — \`certificado.php\`
* Ver 2.4. PDF (mPDF) por default; \`?format=html\` gera página de impressão que auto-chama \`window.print()\` (125-129).

### 9.6 \`/insumos-aprovados\` — \`modulos/certificacao/insumos.php\`
* **Permissão**: \`NIVEL_GESTOR\` (7-11).
* DataTable consumindo \`/api/insumos_aprovados.php\` (70) com colunas categoria, nome_comercial, principio_ativo, fabricante, permitido (badge, 78-85).
* API (\`api/insumos_aprovados.php\`): exige login (22-27), só GET (32-36), filtros \`categoria\`/\`q\`/\`permitido\` (39-62) sobre \`insumos_organicos_aprovados\`.
* Tabela \`insumos_organicos_aprovados\` (\`schema_organico_sprint1.sql:316-352\`): \`nome, nome_comercial, fabricante, categoria ENUM('Fertilizante','Defensivo','Adjuvante','Sementes','Mudas','Outros'), tipo, subcategoria, permitido BOOL, restricoes, normativa DEFAULT 'Portaria 52/2021', data_inclusao_normativa, composicao, principio_ativo, observacoes, fonte_referencia\`. Seeds em \`sql/seed_insumos_aprovados.sql\` (+ \`_animal\`, \`_processamento\`).

### 9.7 \`/certificadoras_list.php\`, \`/certificadoras_cadastrar.php\`, \`/certificadoras_editar.php\` (raiz)
* **Permissão**: \`isLoggedIn() && hasPermission(NIVEL_GESTOR)\` (\`listar:6\`, \`cadastrar:5-9\`, \`editar:5-9\`).
* **listar**: DataTable (\`/api/certificadoras.php\`) com Nome, Cidade/UF, Contato, Logo (thumb 48 px), Ações (99-123); botões de exportação Excel (com \`pageSetup\` landscape injetado no XML, 138-149), PDF e Print (130-171).
* **cadastrar**: campos nome (required), cidade, uf, contato, site, **arquivo de logo**, **logo_url** (14-39). Envia multipart; remove \`logo\`/\`logo_url\` vazios (49-50); injeta \`csrf_token\` (51) e \`window.csrfHeaders()\` (54). Não há campo \`tipo\`, \`sigla\`, \`registro_mapa\`, \`gestor_id\` → uma certificadora criada pela UI fica com \`tipo='SPG'\` (default do ENUM) e \`sigla NULL\` (o que quebra o match por sigla do \`CertificadoOrganico\`) e \`gestor_id NULL\`.
* **editar**: carrega por \`GET /api/certificadoras.php?id=\` (52-61); no submit usa \`_method=PUT\` + \`POST\` (66) e remove \`logo\`/\`logo_url\` vazios (67-68). Não há botão de excluir na UI (o DELETE existe na API).
* **Inconsistência**: \`editar.php:60\` preenche o campo "Logo (URL pública)" com \`c.logo_url || c.logo_filename\` — se só houver \`logo_filename\`, grava o **nome do arquivo** no campo de URL; ao salvar, \`api/certificadoras.php:163\` aceita esse valor não-vazio e sobrescreve \`logo_url\` com um nome de arquivo, corrompendo o campo.

### 9.8 \`/roteiros-inspecao\` e \`/roteiro-inspecao/novo\`
Ver 4.6.

### 9.9 \`/visitas-tecnicas\` — \`visitas/listar.php\`
* **Perfil** (15-20): \`$isAdmin\` (nível ≤ 2), \`$isUsuarioComum\` (não admin e \`user_table === 'associados'\`), \`$associadoIdLogado\`, \`$podeReagendar\`.
* Colunas dinâmicas (165-246): Data, Propriedade, [Agricultor], [Ciente], Roteiro, Status, Conformidade, Ações. A coluna "Ciente" (182-198) mostra Confirmado / Recusou-Reagendar / Pendente.
* Ações: **Realizar** (só admin, 217-222, usa \`hashid || id\`), **Reagendar** (226-232), **Ver** relatório e **Download PDF** (\`/api/roteiros_inspecao.php?action=relatorio&id=\`) quando Realizada (233-241).
* Modais: Agendar (79-115, só admin — carrega roteiros de \`action=templates\`, associados de \`/api/associados/listar.php\` e propriedades de \`/api/get_propriedades_associado.php\`, 275-311) e Reagendar (120-147).
* **Bug**: \`$('#modalAgendarVisita').modal('hide')\` (325) usa a API jQuery de Bootstrap 4; o resto do sistema usa Bootstrap 5 — o modal pode não fechar.

### 9.10 \`/visita-tecnica/agendar\` — \`visitas/agendar.php\`
* **Permissão**: \`NIVEL_GESTOR\` (14-21).
* Campos: associado_id, propriedade_id (disabled até escolher associado), roteiro_id, data_visita (min=hoje), **tecnico_responsavel_id** (select com única opção = \`$_SESSION['user_id']\`, 81-84), acompanhantes (89-92).
* **Risco A13**: \`tecnico_responsavel_id\` recebe \`usuarios.id\`, mas a FK aponta para \`associados.id\` (\`migration.sql:1705\`) → integridade só é mantida porque \`associados.id = 13\` e \`39\` existem por coincidência nos dados atuais (verificado: todas as 12 visitas têm \`tecnico_responsavel_id\` que existe em \`associados\`). Para um gestor cujo id não exista em \`associados\`, o INSERT quebra.
* Painel lateral explica o SPG (109-113) e mostra resumo do agricultor (176-183).

### 9.11 \`/visita-tecnica/{id|hashid}/realizar\` — \`visitas/realizar.php\`
* **Permissão**: logado (8-12); anti-IDOR por \`associado_id\` (56-64); **só status \`Agendada\`** (67-71).
* Mescla o checklist com \`ChecklistPadrao::mesclar()\` (78) e normaliza (81-89), agrupa por categoria (92-95) e conta total/críticos (98-99).
* UI: barra de progresso (112-120), abas por categoria quando >1 (173-237) ou lista simples (238-277), rádios **Conforme / Não Conforme / N/A** por item (214-224), textarea de observação por item (229), 2 uploads de foto (281-296), 2 canvases de assinatura (298-314), parecer final (316-319).
* Validações JS (461-502): hora início/fim/clima obrigatórios e \`horaFim > horaInicio\` (465-477); todos os itens respondidos e **observação obrigatória em todo item 'Não Conforme'** (433-458, 480-496); confirma se as assinaturas estão vazias (498-502).
* Payload (509-535): \`resultado[itemId] = {conforme, na, observacao}\`; PUT em \`?action=visitas\`.
* **Rateio de crédito**: \`evidencias_fotos\` é montado a partir de até 2 uploads (356, 581) — \`uploadedUrls\` fixo em 2 posições.

### 9.12 \`/visita-tecnica/{id}/relatorio\` — \`visitas/relatorio.php\`
* **Permissão**: anti-IDOR por \`associado_id\` (32-42) — **não exige gestor**.
* App Vue 3 via CDN (195-251): busca \`action=visitas&id=\` e \`action=templates\`, resolve nomes de itens com fallback em \`Window_ChecklistPadrao\` (202-209, 245-247).
* Exibe conformidade, barra de pontuação (113-119), parecer, tabela de itens e bloco de evidências/assinaturas (165-184).
* **Bug**: itera apenas \`visita.resultado\` (146) — itens do roteiro que ficaram sem resposta não aparecem; e o rótulo de status trata qualquer coisa diferente de \`true\` como "Não Conforme" (149-155), inclusive \`na = true\`.

### 9.13 \`/nao-conformidades\`, \`/nao-conformidade/{id}\`, \`/nao-conformidade/acompanhar\`
Ver seção 5.5.

Resumo de permissões: listagem serve admin e usuário (a API filtra por \`associado_id\`, \`api/nao_conformidades.php:70-73\`); \`detalhe.php\` faz o mesmo filtro (40-43); \`acompanhar.php\` idem (39-42). **Nenhum dos três restringe por nível** — a distinção é apenas por \`user_table === 'associados'\`/\`associado_id\`.

### 9.14 \`/planos-manejo\`, \`/plano-manejo/...\`
Ver seção 6. Detalhes de permissão por tela:
| Tela | Permissão | Linha |
|---|---|---|
| \`listar.php\` | qualquer logado | 6-11 |
| \`cadastrar.php\` | qualquer logado (escopo de dados por perfil) | 8-54 |
| \`editar.php\` | qualquer logado (status só gestor) | 7-13 |
| \`visualizar.php\` | qualquer logado; botão "Aprovar Plano" só gestor **e** status normalizado = "em análise" | 7-12, 58-63 |
| \`aprovar.php\` | \`NIVEL_GESTOR\` | 8-12 |
| \`upload_croqui.php\` | qualquer logado + dono ou gestor | 8-12, 84-95 |
| \`api/plano_manejo.php\` | login; status/PATCH só gestor; **PUT/DELETE/GET-id sem dono** | 14-19, 252, 363 |

\`listar.php\` também renderiza um badge "Tipo" (\`Plano\` vs \`Certificação\`, 135-142) baseado em \`tipo_registro\`, que a API fixa em \`'Plano'\` por literal SQL (\`api/plano_manejo.php:143\`) — o ramo "Certificação" nunca ocorre.

### 9.15 \`/relatorios-conformidade\` e \`/relatorios-conformidade/gerar\`
Ver seção 7.

### 9.16 \`/consulta-cnpo\`
Ver seção 8. Permissão: \`NIVEL_GESTOR\` (\`index.php\` da tela, 15-18 — redireciona para \`/dashboard\`).

### 9.17 \`api/certificacao/gerar_pdf.php\`
Ver 2.4(b). Propósito: gerar certificado + declaração via Dompdf a partir de \`associado_id\` + dados do POST. Sem UI que o chame nos módulos analisados.

---

## 10. Gaps e riscos consolidados

### 10.1 Riscos críticos (quebram funcionalidade ou expõem dados)
1. **\`cadastrar.php\` inutilizável** — \`data_vencimento\`/\`escopo\` inexistentes (A1). O caminho de criação/edição de certificação pela UI está morto. *Ação*: renomear para \`data_validade\` e mover \`escopo\` para \`dados_adicionais\` (JSON), como já faz \`api/certificacao/controller.php:571-602\`; remover o \`escopo\` duplicado do formulário (linha 262-272).
2. **Assinaturas truncadas a 255 chars** (A2) — todas as assinaturas de visita armazenadas estão corrompidas. *Ação*: migrar as colunas para \`TEXT\`/\`MEDIUMTEXT\` **e** converter as existentes (ou regravá-las); alternativamente salvar o PNG em disco e guardar só o caminho.
3. **IDOR no plano de manejo** (A3) — \`GET ?id=\`, \`PUT\` e \`DELETE\` sem checagem de dono. *Ação*: aplicar \`ApiAuth::exigirProprioAssociado()\` após buscar \`associado_id\` do plano, como já é feito em \`api/certificacao/controller.php:96-98\` e \`api/roteiros_inspecao.php:747-760\`.
4. **Sem escopo multi-tenant** (A10) — qualquer gestor vê e edita dados de qualquer certificadora; qualquer gestor pode emitir certificado com a logo de outra certificadora. *Ação*: implementar guard equivalente ao \`CertificadoraScopeGuard\` (Node) no PHP ou ligar a flag após completar os vínculos.

### 10.2 Riscos altos (dados errados/perdidos silenciosamente)
5. Perda de valor por \`MODIFY COLUMN\` de ENUM não-estrito: \`tipo_semente\` vazio em 11/11 (A4) e \`status\` de NC vazio em 2/5 (A5). *Ação*: \`ALTER TABLE ... MODIFY COLUMN\` sempre com a lista união (rótulos antigos + novos) e \`UPDATE\` de migração antes da remoção — o método correto está em \`sinporg/prisma/BUG_ENUM_MOJIBAKE.md:43-85\`, mas foi aplicado incompleto.
6. \`verificado_por\` = 0 em todas as NCs resolvidas (A6) — \`verificado_por\` deve receber \`$_SESSION['user_id']\`; a coluna é INT. \`acompanhar.php:208\`/\`detalhe.php:192\` devem resolver o nome por JOIN.
7. Divergência de rótulo de gravidade entre UI (\`Critica\`) e ENUM (\`Crítica\`), e ausência de \`Média\` na UI (A7).
8. \`gravidade\` ignorada no PUT de NC (A8) — incluir na whitelist de campos.
9. Duas rotas de UI 404 (A9) — registrar \`/roteiro-inspecao/editar/{id}\` e \`/roteiro-inspecao/ficha/{id}\` em \`index.php\`.
10. \`hashid\` não gerado em novas visitas (A12) — gerar em \`createVisita()\` (\`bin2hex(random_bytes(16))\` conforme \`dev-utils/migrate_visita_hashid.php\`) ou remover a rota por hashid.
11. \`tecnico_responsavel_id\` recebendo \`usuarios.id\` em FK para \`associados\` (A13) — mapear via \`resolveTecnicoId()\` (\`api/roteiros_inspecao.php:259-274\`) também na escrita.
12. Template de certificado legado com dados mock e assinatura hardcoded (A24) — \`api/certificacao/gerar_pdf.php\` deve usar \`CertificadoOrganico\` em vez do template.

### 10.3 Riscos médios
13. Soft-delete morto (A14): escolher entre usar (\`WHERE excluido = 0\` em todas as queries + DELETE lógico) ou remover as colunas.
14. MIME check morto no upload de assinatura (A15) — usar o \`$mimeType\` já calculado (\`controller.php:472\`).
15. \`certificacao_produtos\` órfã (A16) — ou popular a tabela, ou remover do schema e documentar que os produtos vivem em \`dados_adicionais.produtos\`.
16. Sem validação de faixa/coerência numérica em \`plano_manejo\` (A17), sem validação \`data_validade > data_emissao\` em certificação, sem validação \`data_visita >= hoje\` no POST de visita (\`api/roteiros_inspecao.php:434-450\`).
17. URL de croqui absoluta com host de desenvolvimento (A18) — gravar caminho relativo ou usar \`SITE_URL\` consistente.
18. Sem processo de vencimento/renovação automática (A19) — \`status = 'Vencida'\` nunca é setado; \`data_renovacao\` é apenas informativa.
19. \`peso\` do checklist coletado, exibido e impresso mas não usado na pontuação (A20) — ou aplicar pesos em \`calculateScore()\` ou remover a coluna da UI/ficha.
20. Duas APIs de certificadoras concorrentes, sem CSRF em uma delas, com diretórios de upload diferentes (A25) — consolidar em \`api/certificadoras.php\` e remover \`api/certificadoras/controller.php\`.
21. \`Relatorio\`/\`ReportConformidade\` com N+1 (\`ReportConformidade.php:315-327\`) e full scan no CNPO (\`cnpo_consulta.php:76-95\`, 23.232 linhas).
22. \`gerarExcel\` quebra com >26 colunas (\`gerar.php:168\`).
23. \`insumo_aprovado_id\` não selecionado em \`validacaoInsumos\` (\`ReportConformidade.php:381-394\`) → ramo de match por id (423-430) é inalcançável.
24. Ausência de XSS-escaping em \`historico.php:55,89-95\`, \`listar.php\` (certificações) e \`visitas/relatorio.php\` (nomes vindos de \`associados\`).
25. \`api/plano_manejo.php\` não filtra \`excluido = 0\` nem inclui \`versao\` no PUT — \`versao\` nunca incrementa.

### 10.4 Riscos baixos / higiene
26. \`visitas_tecnicas.relatorio_url\` nunca escrita (A22).
27. Colunas \`assinatura_*\`/\`data_renovacao\` ausentes do script de schema oficial (A23) — qualquer reinstalação a partir de \`sql/schema_organico_sprint1.sql\` reproduz o bug A1/A2 em outro nível.
28. \`certificadoras.sigla\`, \`registro_mapa\`, \`tipo\`, \`gestor_id\` não editáveis pela UI (9.7) — sem \`sigla\`, o certificado não resolve a logo pelo caminho 2 de \`buscarCertificadoraDoAssociado()\`.
29. \`certificadoras_editar.php:60\` pode sobrescrever \`logo_url\` com um nome de arquivo.
30. 2 usuários não-admin sem \`certificadora_id\` (A26).
31. \`associados.registro_cnpo\` NULL em 100% → a linha "Registro CNPO" do certificado nunca aparece; não há job de vinculação CNPO↔associado.
32. \`templates/listar.php:174\` faz \`JSON.parse\` sem try/catch.
33. Categorias de checklist em hardcode duplicado entre \`templates/cadastrar.php:212-222\` e \`ChecklistPadrao\`.
34. \`api/certificacao/gerar_pdf.php:118\` hardcoda \`$basePath = '/app'\`.
35. Não há \`PEAF\` no sistema (grep sem resultados) — se o requisito existe, não foi implementado.

---

## 11. Anexo — inventário de arquivos lidos

**Módulos**
\`modulos/certificacao/{cadastrar,certificado,editar,historico,insumos,listar,renovar}.php\`;
\`modulos/roteiros_inspecao/templates/{cadastrar,certificado,declaracao_compromisso,ficha_pdf,listar}.php\`;
\`modulos/roteiros_inspecao/visitas/{agendar,listar,realizar,relatorio}.php\`;
\`modulos/roteiros_inspecao/nao_conformidades/{acompanhar,detalhe,listar}.php\`;
\`modulos/plano_manejo/{aprovar,cadastrar,editar,listar,upload_croqui,visualizar}.php\`;
\`modulos/conformidade/relatorios.php\`, \`modulos/conformidade/relatorios/gerar.php\`;
\`modulos/consulta_cnpo/index.php\`;
\`certificadoras_list.php\`, \`certificadoras_cadastrar.php\`, \`certificadoras_editar.php\`.

**APIs**
\`api/certificacao/controller.php\`, \`api/certificacao/gerar_pdf.php\`;
\`api/certificadoras.php\`, \`api/certificadoras/controller.php\`, \`api/certificadoras-plan.md\`;
\`api/roteiros_inspecao.php\`, \`api/nao_conformidades.php\`, \`api/plano_manejo.php\`, \`api/cnpo_consulta.php\`, \`api/insumos_aprovados.php\`.

**Classes**
\`classes/CertificadoOrganico.php\`, \`Certificadora.php\`, \`RoteiroInspecao.php\`, \`VisitaTecnica.php\`, \`VisitaReport.php\`, \`ReportConformidade.php\`, \`ChecklistPadrao.php\`, \`Relatorio.php\`, \`User.php\`, \`ApiAuth.php\`, \`ImageUploader.php\`, \`Database.php\`.
**Não existe classe \`PlanoManejo\`** — o domínio é manipulado inteiramente por \`api/plano_manejo.php\` com SQL direto (o único \`Plano*\` em \`classes/\` é \`PlanoMensalidade.php\`, financeiro).

**SQL / infra**
\`sql/schema_organico_sprint1.sql\`, \`sql/create_certificadoras.sql\`, \`sql/install_sprint1.sql\`, \`sql/README_SPRINT1.md\`, \`sql/popular_certificadora_id.sql\`, \`sql/fix_enum_acentos.sql\`, \`sql/_backup/fix_enum_mojibake_acentos.sql\`;
\`sinporg/prisma/migrations/0_baseline/migration.sql\`, \`sinporg/prisma/BUG_ENUM_MOJIBAKE.md\`, \`sinporg/prisma/RISCOS_SCHEMA.md\`;
\`dev-utils/{migrate_conformidade,migrate_visita_hashid,atualizar_cnpo,check_certificadoras_structure}.php\`;
\`index.php\`, \`.htaccess\`, \`api/.htaccess\`, \`config/config.php\`, \`includes/menu.php\`, \`relatorios/gerar_certificado.php\`, \`add_sigla.php\`;
\`sinporg/apps/api/src/modules/auth/guards/certificadora-scope.guard.ts\` (referência do guard multi-tenant do stack novo).

**Verificação empírica** — consultas diretas via \`C:\xampp\php\php.exe\` + PDO contra \`sisagro\`: estrutura de \`certificacao_participativa\`, \`certificadoras\`, \`visitas_tecnicas\`, \`nao_conformidades\`, \`plano_manejo_cultivos\`, \`roteiros_inspecao\`, \`cnpo\`, \`propriedades\`; contagens; distribuições de status/gravidade; conteúdo de \`checklist\`, \`itens_criticos\`, \`resultado\`, \`dados_adicionais\`; comprimentos de \`assinatura_*_url\`; \`@@sql_mode\`. Scripts temporários usados na verificação foram removidos do workspace.
