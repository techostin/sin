<?php
$page_title = "Cadastrar Usuário";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

// Verifica se o usuário tem permissão de admin ou gestor
if (!$user->isLoggedIn() || !in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página. Apenas administradores e gestores podem cadastrar usuários.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Apenas Admin pode criar contas de Admin (nível 1). Gestor cria no máximo
// contas de Gestor/Usuário — antes qualquer gestor podia criar um Admin.
$souAdmin = $user->hasPermission(NIVEL_ADMIN);
$niveisPermitidos = $souAdmin ? [NIVEL_ADMIN, NIVEL_GESTOR, NIVEL_USUARIO] : [NIVEL_GESTOR, NIVEL_USUARIO];

// ---------------------------------------------------------------------------
// Identidade: uma pessoa = um "associados" + no máximo uma conta "usuarios".
// Ao criar a conta a partir de um associado existente, o sistema copia os dados
// pessoais do cadastro (fonte canônica) e fecha o vínculo dos DOIS lados
// (usuarios.associado_id <-> associados.user_id). Somente Admin pode vincular,
// porque o vínculo define qual pessoa recebe aquela conta (e o nível dela).
// ---------------------------------------------------------------------------
$associadosSemConta = [];
if ($souAdmin) {
    $associadosSemConta = $db->query(
        "SELECT a.id, a.nome, a.sobrenome, a.cpf, a.telefone, a.email
           FROM associados a
          WHERE a.status = 'ativo'
            AND a.user_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.associado_id = a.id)
          ORDER BY a.nome, a.sobrenome"
    )->fetchAll(PDO::FETCH_ASSOC);
}
$associadosSemContaPorId = [];
foreach ($associadosSemConta as $a) { $associadosSemContaPorId[(int) $a['id']] = $a; }

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = '<div class="alert alert-danger">Erro de segurança: token inválido.</div>';
    } else {
        $nome = (string) ($_POST['nome'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');
        $nivel = (string) ($_POST['nivel'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $associadoId = $souAdmin ? (int) ($_POST['associado_id'] ?? 0) : 0;

        if (!in_array((int) $nivel, $niveisPermitidos, true) || !in_array($status, ['ativo', 'inativo'], true)) {
            $mensagem = '<div class="alert alert-danger">Nível ou status inválido (ou sem permissão para atribuí-lo).</div>';
        } elseif ($associadoId > 0 && !isset($associadosSemContaPorId[$associadoId])) {
            $mensagem = '<div class="alert alert-danger">Associado inválido ou que já possui conta de acesso.</div>';
        } else {
            $partes = explode(' ', trim($nome), 2);
            $nomeFinal = $partes[0] ?? '';
            $sobrenomeFinal = $partes[1] ?? null;
            $cpfFinal = null;
            $telefoneFinal = null;

            if ($associadoId > 0) {
                // Dados pessoais vêm SEMPRE do cadastro do associado
                $a = $associadosSemContaPorId[$associadoId];
                $nomeFinal = $a['nome'];
                $sobrenomeFinal = $a['sobrenome'];
                $cpfFinal = $a['cpf'];
                $telefoneFinal = $a['telefone'];
                if ($email === '' && !empty($a['email'])) {
                    $email = (string) $a['email'];
                }
            }

            if ($nomeFinal === '' || $email === '' || $senha === '') {
                $mensagem = '<div class="alert alert-danger">Preencha nome, e-mail e senha.</div>';
            } else {
                try {
                    $db->beginTransaction();
                    $stmt = $db->prepare(
                        "INSERT INTO usuarios (nome, sobrenome, cpf, telefone, email, senha, nivel, status, associado_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $nomeFinal,
                        $sobrenomeFinal,
                        $cpfFinal,
                        $telefoneFinal,
                        $email,
                        password_hash($senha, PASSWORD_DEFAULT),
                        (int) $nivel,
                        $status,
                        $associadoId > 0 ? $associadoId : null,
                    ]);
                    $novoId = (int) $db->lastInsertId();

                    if ($associadoId > 0) {
                        // fecha o vínculo no lado do cadastro
                        $db->prepare('UPDATE associados SET user_id = ? WHERE id = ?')->execute([$novoId, $associadoId]);
                        AuditLog::registrar('IDENTIDADE_VINCULADA', 'usuarios', $novoId, "usuarios.id={$novoId} <-> associados.id={$associadoId} (criação de conta)");
                    }
                    $db->commit();

                    $mensagem = '<div class="alert alert-success">Usuário cadastrado com sucesso!';
                    if ($associadoId > 0) {
                        $mensagem .= ' Conta vinculada ao associado <strong>#' . (int) $associadoId . '</strong>.';
                    }
                    $mensagem .= '</div>';
                } catch (PDOException $e) {
                    if ($db->inTransaction()) { $db->rollBack(); }
                    $mensagem = '<div class="alert alert-danger">Erro ao cadastrar: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
}
?>
<h1 class="mt-4">Cadastrar Usuário</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php echo CSRFProtection::getTokenField(); ?>

            <?php if ($souAdmin): ?>
            <div class="mb-3">
                <label for="associado_id" class="form-label">Criar a conta a partir de um associado (recomendado)</label>
                <select class="form-select" id="associado_id" name="associado_id">
                    <option value="">— Conta de sistema sem vínculo (equipe interna) —</option>
                    <?php foreach ($associadosSemConta as $a): ?>
                    <option value="<?php echo (int) $a['id']; ?>"
                            data-nome="<?php echo htmlspecialchars($a['nome'] . ' ' . $a['sobrenome'], ENT_QUOTES); ?>"
                            data-email="<?php echo htmlspecialchars((string) $a['email'], ENT_QUOTES); ?>">
                        #<?php echo (int) $a['id']; ?> — <?php echo htmlspecialchars($a['nome'] . ' ' . $a['sobrenome']); ?>
                        <?php if (!empty($a['cpf'])): ?>(CPF <?php echo htmlspecialchars($a['cpf']); ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    Lista apenas associados ativos <strong>sem conta de acesso</strong>. Ao vincular, nome, sobrenome,
                    CPF e telefone são copiados do cadastro do associado e o vínculo é gravado nos dois lados
                    (<code>usuarios.associado_id</code> ↔ <code>associados.user_id</code>).
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
                <div class="form-text">Com um associado selecionado, este campo é preenchido pelo cadastro dele.</div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail de login</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div class="form-text">Pode ser diferente do e-mail do cadastro (ex.: e-mail individual do associado).</div>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <div class="mb-3">
                <label for="nivel" class="form-label">Nível</label>
                <select class="form-select" id="nivel" name="nivel" required>
                    <option value="">Selecione</option>
                    <?php if ($souAdmin): ?>
                    <option value="1">Admin</option>
                    <?php endif; ?>
                    <option value="2">Gestor</option>
                    <option value="3">Usuário</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="">Selecione</option>
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Salvar</button>
            <a href="<?php echo SITE_URL; ?>/usuarios" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php if ($souAdmin): ?>
<script>
// Preenche os campos com os dados do cadastro do associado escolhido.
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('associado_id');
    if (!sel) return;
    var nome = document.getElementById('nome');
    var email = document.getElementById('email');
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        if (opt && opt.value) {
            nome.value = opt.getAttribute('data-nome') || '';
            email.value = opt.getAttribute('data-email') || '';
        }
    });
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
