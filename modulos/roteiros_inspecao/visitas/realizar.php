<?php
$page_title = "Realizar Inspeção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>ID de visita inválido.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar a visita e o template
$stmt = $db->prepare("SELECT v.*, r.nome as roteiro_nome, r.checklist 
                      FROM visitas_tecnicas v
                      JOIN roteiros_inspecao r ON v.roteiro_id = r.id
                      WHERE v.id = ?");
$stmt->execute([$id]);
$visita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visita) {
    echo "<div class='alert alert-danger'>Visita não encontrada.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$checklist = json_decode($visita['checklist'], true);
?>

<div class="container-fluid px-4 pb-5">
    <h1 class="mt-4 h3"> Checklist de Inspeção</h1>
    <p class="text-muted"><?php echo $visita['roteiro_nome']; ?> - Visita #<?php echo $id; ?></p>

    <form id="formChecklist">
        <input type="hidden" name="visita_id" value="<?php echo $id; ?>">
        
        <?php foreach ($checklist as $index => $item): ?>
            <div class="card mb-3 shadow-sm border-0">
                <div class="card-body">
                    <h6 class="card-title fw-bold"><?php echo ($index + 1) . '. ' . $item['item']; ?></h6>
                    
                    <div class="d-flex gap-3 my-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="item[<?php echo $item['id']; ?>][status]" id="item_<?php echo $item['id']; ?>_sim" value="Conforme" required>
                            <label class="form-check-label text-success fw-bold" for="item_<?php echo $item['id']; ?>_sim">CONFORME</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="item[<?php echo $item['id']; ?>][status]" id="item_<?php echo $item['id']; ?>_nao" value="Não Conforme">
                            <label class="form-check-label text-danger fw-bold" for="item_<?php echo $item['id']; ?>_nao">NÃO CONFORME</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="item[<?php echo $item['id']; ?>][status]" id="item_<?php echo $item['id']; ?>_na" value="N/A">
                            <label class="form-check-label text-muted" for="item_<?php echo $item['id']; ?>_na">N/A</label>
                        </div>
                    </div>

                    <div class="mt-2">
                        <label class="form-label small text-muted">Observações / Evidências</label>
                        <textarea class="form-control form-control-sm" name="item[<?php echo $item['id']; ?>][obs]" rows="2" placeholder="Descreva observações ou não conformidades..."></textarea>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="card mb-4 shadow-sm border-0 bg-light">
            <div class="card-body">
                <h6 class="fw-bold">Conclusão da Visita</h6>
                <div class="mb-3">
                    <label class="form-label">Parecer Geral</label>
                    <textarea class="form-control" name="parecer_geral" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fotos da Visita (URLs ou seleção múltipla)</label>
                    <input type="text" class="form-control" name="evidencias_fotos" placeholder="URLs separadas por vírgula">
                </div>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success btn-lg">Finalizar Inspeção</button>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="btn btn-outline-secondary">Salvar como Rascunho</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<style>
/* Estilos para Mobile */
.card-body { padding: 1rem; }
.form-check-input { width: 1.5em; height: 1.5em; }
.form-check-label { padding-top: 0.2rem; margin-left: 0.3rem; }
</style>

<script>
$(document).ready(function() {
    $('#formChecklist').submit(function(e) {
        e.preventDefault();
        
        // Coleta de dados manual para formatar o JSON esperado pela API
        const formData = {
            visita_id: $('input[name="visita_id"]').val(),
            resultado: {},
            status: 'Realizada',
            parecer_geral: $('textarea[name="parecer_geral"]').val(),
            evidencias_fotos: $('input[name="evidencias_fotos"]').val()
        };

        // Formatar resultados por item
        $('[name^="item"]').each(function() {
            const name = $(this).attr('name');
            const itemId = name.match(/\[(\d+)\]/)[1];
            
            if (!formData.resultado[itemId]) {
                formData.resultado[itemId] = { conforme: false, observacao: "" };
            }

            if ($(this).is(':checked')) {
                formData.resultado[itemId].conforme = ($(this).val() === 'Conforme');
            } else if ($(this).is('textarea')) {
                formData.resultado[itemId].observacao = $(this).val();
            }
        });

        fetch('<?php echo SITE_URL; ?>/api/roteiros-inspecao?action=visitas', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Inspeção finalizada com sucesso!');
                window.location.href = '<?php echo SITE_URL; ?>/visitas-tecnicas';
            } else {
                alert('Erro ao salvar inspeção');
            }
        });
    });
});
</script>
