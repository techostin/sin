<?php
$page_title = "Editar Usuário";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>ID não informado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$mensagem = '';
try {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) {
        echo "<div class='alert alert-danger'>Usuário não encontrado.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar usuário: " . htmlspecialchars($e->getMessage()) . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Autorização: apenas Admin gerencia contas de equipe (níveis 1 e 2) ou
// promove alguém a Admin. Gestor só edita usuários comuns (nível 3) e nunca a
// própria conta — antes um gestor podia enviar nivel=1 e virar Admin.
$souAdmin = $user->hasPermission(NIVEL_ADMIN);
$nivelAlvo = (int) $usuario['nivel'];
$editandoPropriaConta = (($_SESSION['user_table'] ?? '') === 'usuarios')
    && ((int) ($_SESSION['user_id'] ?? 0) === (int) $id);
if (!$souAdmin && ($nivelAlvo <= NIVEL_GESTOR || $editandoPropriaConta)) {
    echo "<div class='alert alert-danger'>Acesso negado: apenas administradores podem gerenciar contas de equipe.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// ---------------------------------------------------------------------------
// Vínculo com o cadastro do associado (relação 1:1)
//   usuarios.associado_id <-> associados.user_id
// Só Admin altera o vínculo: ele define QUAL pessoa recebe esta conta (e,
// portanto, qual pessoa herda o nível dela ao entrar pelo Portal).
// ---------------------------------------------------------------------------
$associadoVinculadoId = (int) ($usuario['associado_id'] ?? 0);
$associadoVinculado = null;
if ($associadoVinculadoId > 0) {
    $stVinc = $db->prepare("SELECT id, nome, sobrenome, cpf, telefone, email, user_id FROM associados WHERE id = ? LIMIT 1");
    $stVinc->execute([$associadoVinculadoId]);
    $associadoVinculado = $stVinc->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$associadoVinculado) { $associadoVinculadoId = 0; }
}

$associadosParaVincular = [];
if ($souAdmin) {
    $associadosParaVincular = $db->query(
        "SELECT id, nome, sobrenome, cpf, user_id
           FROM associados
          WHERE status = 'ativo'
          ORDER BY nome, sobrenome"
    )->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = '<div class="alert alert-danger">Erro de segurança: token inválido.</div>';
    } else {
    $nome = (string) ($_POST['nome'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');
    $nivel = (string) ($_POST['nivel'] ?? '');
    $status = (string) ($_POST['status'] ?? '');

    // Só Admin troca o vínculo; os demais mantêm o atual.
    $associadoIdNovo = $souAdmin ? (int) ($_POST['associado_id'] ?? 0) : $associadoVinculadoId;

    // Whitelist de nível/status: gestor só pode manter o usuário como comum.
    $niveisPermitidos = $souAdmin ? [NIVEL_ADMIN, NIVEL_GESTOR, NIVEL_USUARIO] : [NIVEL_USUARIO];
    if (!in_array((int) $nivel, $niveisPermitidos, true) || !in_array($status, ['ativo', 'inativo'], true)) {
        $mensagem = '<div class="alert alert-danger">Nível ou status inválido (ou sem permissão para atribuí-lo).</div>';
        $podeSalvar = false;
    } else {
        $podeSalvar = true;
    }

    // Validação do vínculo escolhido
    $associadoNovo = null;
    if ($podeSalvar && $associadoIdNovo > 0) {
        $stNovo = $db->prepare("SELECT id, nome, sobrenome, cpf, telefone, user_id FROM associados WHERE id = ? AND status = 'ativo' LIMIT 1");
        $stNovo->execute([$associadoIdNovo]);
        $associadoNovo = $stNovo->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$associadoNovo) {
            $mensagem = '<div class="alert alert-danger">Associado informado não encontrado (ou inativo).</div>';
            $podeSalvar = false;
        } elseif ((int) ($associadoNovo['user_id'] ?? 0) > 0 && (int) $associadoNovo['user_id'] !== (int) $id) {
            $mensagem = '<div class="alert alert-danger">Este associado já está vinculado a outra conta de acesso.</div>';
            $podeSalvar = false;
        }
    }

    // Dados pessoais: quando há vínculo, valem SEMPRE os do cadastro do
    // associado (fonte canônica). Sem vínculo, o nome digitado é padronizado
    // no formato nome/sobrenome.
    if ($podeSalvar && $associadoIdNovo > 0 && $associadoNovo) {
        $nomeFinal = (string) $associadoNovo['nome'];
        $sobrenomeFinal = (string) $associadoNovo['sobrenome'];
        $cpfFinal = $associadoNovo['cpf'];
        $telefoneFinal = $associadoNovo['telefone'];
    } else {
        $partes = explode(' ', trim($nome), 2);
        $nomeFinal = $partes[0] ?? '';
        $sobrenomeFinal = $partes[1] ?? null;
        $cpfFinal = $usuario['cpf'];
        $telefoneFinal = $usuario['telefone'];
    }

    try {
        if (!$podeSalvar) {
            // nada a fazer: mensagem já definida
        } else {
            $sets = ['nome = ?', 'sobrenome = ?', 'email = ?', 'nivel = ?', 'status = ?'];
            $vals = [$nomeFinal, $sobrenomeFinal, $email, (int) $nivel, $status];

            if ($senha !== '') {
                $sets[] = 'senha = ?';
                $vals[] = password_hash($senha, PASSWORD_DEFAULT);
            }
            if ($associadoIdNovo > 0) {
                $sets[] = 'cpf = ?';
                $vals[] = $cpfFinal;
                $sets[] = 'telefone = ?';
                $vals[] = $telefoneFinal;
            }
            $sets[] = 'associado_id = ?';
            $vals[] = $associadoIdNovo > 0 ? $associadoIdNovo : null;
            $vals[] = (int) $id;

            $db->beginTransaction();
            $db->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);

            // Sincroniza o vínculo nos dois lados (e limpa o lado antigo)
            if ($associadoIdNovo !== $associadoVinculadoId) {
                if ($associadoVinculadoId > 0) {
                    $db->prepare('UPDATE associados SET user_id = NULL WHERE id = ? AND user_id = ?')
                       ->execute([$associadoVinculadoId, (int) $id]);
                }
                if ($associadoIdNovo > 0) {
                    $db->prepare('UPDATE associados SET user_id = ? WHERE id = ?')->execute([(int) $id, $associadoIdNovo]);
                    AuditLog::registrar('IDENTIDADE_VINCULADA', 'usuarios', (int) $id, "usuarios.id={$id} <-> associados.id={$associadoIdNovo} (edição)");
                } else {
                    AuditLog::registrar('IDENTIDADE_DESVINCULADA', 'usuarios', (int) $id, "vínculo removido (associados.id={$associadoVinculadoId})");
                }
            } elseif ($associadoIdNovo > 0) {
                // Garante o link reverso mesmo quando só um lado estava preenchido
                $db->prepare('UPDATE associados SET user_id = ? WHERE id = ?')->execute([(int) $id, $associadoIdNovo]);
            }
            $db->commit();

            $mensagem = '<div class="alert alert-success">Usuário atualizado com sucesso!</div>';
            $usuario['nome'] = $nomeFinal;
            $usuario['sobrenome'] = $sobrenomeFinal;
            $usuario['email'] = $email;
            $usuario['nivel'] = $nivel;
            $usuario['status'] = $status;
            $usuario['associado_id'] = $associadoIdNovo > 0 ? $associadoIdNovo : null;
            $associadoVinculadoId = $associadoIdNovo;
            $associadoVinculado = $associadoNovo;
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    }
}
?>
<h1 class="mt-4">Editar Usuário</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php echo CSRFProtection::getTokenField(); ?>

            <?php if ($souAdmin): ?>
            <div class="mb-3">
                <label for="associado_id" class="form-label">Associado vinculado (pessoa dona desta conta)</label>
                <select class="form-select" id="associado_id" name="associado_id">
                    <option value="">— Conta de sistema sem vínculo (equipe interna) —</option>
                    <?php foreach ($associadosParaVincular as $a):
                        $donoAtual = (int) ($a['user_id'] ?? 0);
                        $bloqueado = ($donoAtual > 0 && $donoAtual !== (int) $id);
                    ?>
                    <option value="<?php echo (int) $a['id']; ?>"
                            <?php if ((int) $a['id'] === $associadoVinculadoId) echo 'selected'; ?>
                            <?php if ($bloqueado) echo 'disabled'; ?>>
                        #<?php echo (int) $a['id']; ?> — <?php echo htmlspecialchars($a['nome'] . ' ' . $a['sobrenome']); ?>
                        <?php if ($bloqueado): ?>(já vinculado à conta #<?php echo $donoAtual; ?>)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    O vínculo é gravado nos dois lados (<code>usuarios.associado_id</code> ↔ <code>associados.user_id</code>).
                    Com vínculo, nome, sobrenome, CPF e telefone passam a ser os do cadastro do associado.
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <?php if ($associadoVinculado): ?>
                    <input type="text" class="form-control" id="nome" name="nome" readonly
                           value="<?php echo htmlspecialchars($associadoVinculado['nome'] . ' ' . $associadoVinculado['sobrenome']); ?>">
                    <div class="form-text">
                        Definido pelo cadastro do associado
                        <a href="<?php echo SITE_URL; ?>/associado/<?php echo (int) $associadoVinculado['id']; ?>/editar" target="_blank">#<?php echo (int) $associadoVinculado['id']; ?></a>
                        <?php if (!empty($associadoVinculado['cpf'])): ?>• CPF <?php echo htmlspecialchars($associadoVinculado['cpf']); ?><?php endif; ?>
                        <?php if (!empty($associadoVinculado['telefone'])): ?>• Tel <?php echo htmlspecialchars($associadoVinculado['telefone']); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <input type="text" class="form-control" id="nome" name="nome"
                           value="<?php echo htmlspecialchars(trim(($usuario['nome'] ?? '') . ' ' . ($usuario['sobrenome'] ?? ''))); ?>" required>
                    <div class="form-text">Nome completo (o sistema separa nome e sobrenome automaticamente).</div>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail de login</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars((string) $usuario['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha (deixe em branco para não alterar)</label>
                <input type="password" class="form-control" id="senha" name="senha">
            </div>
            <div class="mb-3">
                <label for="nivel" class="form-label">Nível</label>
                <select class="form-select" id="nivel" name="nivel" required>
                    <option value="">Selecione</option>
                    <?php if ($souAdmin): ?>
                    <option value="1" <?php if((int)$usuario['nivel'] === 1) echo 'selected'; ?>>Admin</option>
                    <?php endif; ?>
                    <option value="2" <?php if((int)$usuario['nivel'] === 2) echo 'selected'; ?>>Gestor</option>
                    <option value="3" <?php if((int)$usuario['nivel'] === 3) echo 'selected'; ?>>Usuário</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="">Selecione</option>
                    <option value="ativo" <?php if(strtolower($usuario['status'])==='ativo') echo 'selected'; ?>>Ativo</option>
                    <option value="inativo" <?php if(strtolower($usuario['status'])==='inativo') echo 'selected'; ?>>Inativo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="<?php echo SITE_URL; ?>/usuarios" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
