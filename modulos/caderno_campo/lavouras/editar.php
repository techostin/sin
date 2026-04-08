<?php
$page_title = "Editar Lavoura";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';

$message = '';
$message_type = '';
$dados_lavouras = null;
$propriedades = [];
$id = null;

// Verificar se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $message = 'ID da lavoura não informado.';
    $message_type = 'danger';
} else {
    $id = (int)$_GET['id'];
    $lavouras = new Lavoura();
    $propriedade_obj = new Propriedade();

    // Buscar dados da lavoura
    $dados_lavouras = $lavouras->getById($id);
    if (!$dados_lavouras) {
        $message = 'Lavoura não encontrada.';
        $message_type = 'danger';
    } else {
        // IDOR Check: Se for nível Usuário, deve ser dono da propriedade vinculada a esta lavoura
        if (!$user->hasPermission(NIVEL_GESTOR)) {
            $prop_dados = $propriedade_obj->getById($dados_lavouras['propriedade_id']);
            $associado_id = $user->getAssociadoId();
            if (!$prop_dados || $prop_dados['associado_id'] != $associado_id) {
                $message = 'Acesso Negado: Esta lavoura pertence a outro produtor.';
                $message_type = 'danger';
                $dados_lavouras = null; // Prevent form rendering
            }
        }
        
        if ($dados_lavouras) {
            // Buscar lista de propriedades (com isolamento)
            if (!$user->hasPermission(NIVEL_GESTOR)) {
                $associado_id = $user->getAssociadoId();
                if (!$associado_id) $associado_id = -1;
                $propriedades = $propriedade_obj->getByAssociadoId($associado_id);
            } else {
                $propriedades = $propriedade_obj->getAll();
            }

        // Processar formulário
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $dados = $_POST;
            $dados['id'] = $id;

            if ($lavouras->update($dados)) {
                $message = "Lavoura atualizada com sucesso!";
                $message_type = "success";
                // Recarregar dados após atualização
                $dados_lavouras = $lavouras->getById($id);
            } else {
                $message = "Erro ao atualizar a lavoura.";
                $message_type = "danger";
            }
        }
        } // Close if ($dados_lavouras)
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Editar Lavoura</h1>
    <a href="<?php echo SITE_URL; ?>/lavouras" class="btn btn-success">
        <i class="bi bi-list"></i> Listar Lavouras
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif;

if ($dados_lavouras): ?>
<form action="" method="POST" class="row g-3 mb-5">
    <div class="col-md-6">
        <label for="propriedade_id" class="form-label">Propriedade</label>
        <select class="form-select" id="propriedade_id" name="propriedade_id" required>
            <option value="">Selecione uma propriedade</option>
            <?php foreach ($propriedades as $prop): ?>
                <option value="<?php echo $prop['id']; ?>" <?php echo ($dados_lavouras['propriedade_id'] == $prop['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($prop['propriedade']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label for="cultura" class="form-label">Cultura</label>
        <input type="text" class="form-control" id="cultura" name="cultura" value="<?php echo htmlspecialchars($dados_lavouras['tipo_cultura'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="area" class="form-label">Área plantada (hectares)</label>
        <input type="number" step="0.01" class="form-control" id="area" name="area" value="<?php echo htmlspecialchars($dados_lavouras['area'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="data_plantio" class="form-label">Data de plantio</label>
        <input type="date" class="form-control" id="data_plantio" name="data_plantio" value="<?php echo htmlspecialchars($dados_lavouras['data_plantio'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="data_colheita_prevista" class="form-label">Data prevista para colheita</label>
        <input type="date" class="form-control" id="data_colheita_prevista" name="data_colheita_prevista" value="<?php echo htmlspecialchars($dados_lavouras['data_colheita_prevista'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status</label>
        <select class="form-select" id="status" name="status" required>
            <option value="" <?php echo empty($dados_lavouras['status']) ? 'selected' : ''; ?>>Selecione o status</option>
            <option value="planejada" <?php echo ($dados_lavouras['status'] ?? '') === 'planejada' ? 'selected' : ''; ?>>Planejada</option>
            <option value="plantada" <?php echo ($dados_lavouras['status'] ?? '') === 'plantada' ? 'selected' : ''; ?>>Plantada</option>
            <option value="em_desenvolvimento" <?php echo ($dados_lavouras['status'] ?? '') === 'em_desenvolvimento' ? 'selected' : ''; ?>>Em desenvolvimento</option>
            <option value="colhida" <?php echo ($dados_lavouras['status'] ?? '') === 'colhida' ? 'selected' : ''; ?>>Colhida</option>
            <option value="suspensa" <?php echo ($dados_lavouras['status'] ?? '') === 'suspensa' ? 'selected' : ''; ?>>Suspensa</option>
            <option value="cancelada" <?php echo ($dados_lavouras['status'] ?? '') === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
        </select>
    </div>

    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($dados_lavouras['observacoes'] ?? ''); ?></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Atualizar Lavoura</button>
        <a href="<?php echo SITE_URL; ?>/lavouras" class="btn btn-success">
            <i class="bi bi-list"></i> Listar Lavouras
        </a>
    </div>
</form>
<?php endif; ?>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
