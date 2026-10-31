<?php
/**
 * Comunicação Segmentada por Cluster
 * Envio de e-mail/WhatsApp para associados agrupados por cluster_comunicacao
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Admin/Gestor do sistema OU membro da Diretoria (Presidente, Vice-Presidente,
// Diretor, Secretário, Tesoureiro e comissões/conselhos) da associação.
if (!$user->hasPermission(NIVEL_GESTOR) && !$user->isMembroDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = Database::getInstance()->getConnection();

$mensagem = '';
$mensagem_tipo = '';

// Buscar clusters disponíveis
$clusters = $db->query("
    SELECT DISTINCT cluster_comunicacao, COUNT(*) as qtd
    FROM associados
    WHERE estado = 23 AND cluster_comunicacao IS NOT NULL AND status = 'ativo'
    GROUP BY cluster_comunicacao
    ORDER BY cluster_comunicacao
")->fetchAll(PDO::FETCH_ASSOC);

// Buscar associações para filtro
$associacoes = $db->query("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $acao = $_POST['acao'] ?? '';
        
        if ($acao === 'enviar_email') {
            $cluster = $_POST['cluster'] ?? '';
            $associacao_id = !empty($_POST['associacao_id']) ? (int) $_POST['associacao_id'] : null;
            $assunto = trim($_POST['assunto'] ?? '');
            $corpo = trim($_POST['corpo'] ?? '');
            $tipo_envio = $_POST['tipo_envio'] ?? 'email'; // email, whatsapp, ambos
            
            if (empty($cluster) || empty($assunto) || empty($corpo)) {
                $mensagem = 'Preencha todos os campos obrigatórios.';
                $mensagem_tipo = 'danger';
            } else {
                // Buscar associados do cluster
                $sql = "
                    SELECT a.id, a.nome, a.sobrenome, a.email, a.telefone, a.cpf
                    FROM associados a
                    WHERE a.estado = 23
                      AND a.cluster_comunicacao = ?
                      AND a.status = 'ativo'
                      AND a.email IS NOT NULL AND a.email != ''
                ";
                $params = [$cluster];
                
                if ($associacao_id) {
                    $sql .= " AND EXISTS (
                        SELECT 1 FROM associado_associacao aa 
                        WHERE aa.associado_id = a.id AND aa.associacao_id = ?
                    )";
                    $params[] = $associacao_id;
                }
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($destinatarios)) {
                    $mensagem = 'Nenhum associado encontrado com e-mail para este cluster/filtro.';
                    $mensagem_tipo = 'warning';
                } else {
                    $enviados = 0;
                    $falhas = 0;
                    
                    foreach ($destinatarios as $dest) {
                        $sucesso = false;
                        
                        // Preparar corpo personalizado
                        $corpoPersonalizado = str_replace(
                            ['{{nome}}', '{{cpf}}', '{{cluster}}'],
                            [$dest['nome'], $dest['cpf'], $cluster],
                            $corpo
                        );
                        
                        if ($tipo_envio === 'email' || $tipo_envio === 'ambos') {
                            // Enviar e-mail via Resend (já configurado em config.php)
                            $sucessoEmail = enviarEmailResend($dest['email'], $assunto, $corpoPersonalizado);
                            if ($sucessoEmail) $sucesso = true;
                        }
                        
                        if ($tipo_envio === 'whatsapp' || $tipo_envio === 'ambos') {
                            // Gerar link WhatsApp (não envia automaticamente, abre no navegador)
                            $telefone = preg_replace('/\D/', '', $dest['telefone'] ?? '');
                            if ($telefone) {
                                $msgWhatsapp = urlencode("Olá {$dest['nome']},\n\n{$corpoPersonalizado}\n\n--\nSINPORG");
                                $linkWhatsapp = "https://wa.me/55{$telefone}?text={$msgWhatsapp}";
                                // Salvar link para exibir depois
                                $dest['whatsapp_link'] = $linkWhatsapp;
                                $sucesso = true;
                            }
                        }
                        
                        if ($sucesso) $enviados++;
                        else $falhas++;
                    }
                    
                    $mensagem = "Processamento concluído: <strong>{$enviados}</strong> associados processados";
                    if ($falhas > 0) $mensagem .= ", <strong>{$falhas}</strong> falhas";
                    if ($tipo_envio === 'whatsapp' || $tipo_envio === 'ambos') {
                        $mensagem .= ". <a href='#' onclick=\"abrirWhatsApps('{$cluster}')\" class='alert-link'>Ver links WhatsApp</a>";
                    }
                    $mensagem_tipo = $falhas > 0 ? 'warning' : 'success';
                    
                    // Guardar links WhatsApp na sessão para exibir
                    if ($tipo_envio === 'whatsapp' || $tipo_envio === 'ambos') {
                        $_SESSION['whatsapp_links'] = array_column(array_filter($destinatarios, fn($d) => !empty($d['whatsapp_link'])), 'whatsapp_link', 'nome');
                        $_SESSION['whatsapp_cluster'] = $cluster;
                    }
                }
            }
        }
    }
}

function enviarEmailResend($email, $assunto, $corpoHtml) {
    $apiKey = RESEND_API_KEY ?? '';
    $from = EMAIL_FROM_ADDRESS ?? 'sinporg@sinporg.organicosrs.com.br';
    $fromName = EMAIL_FROM_NAME ?? 'SINPORG';
    
    if (empty($apiKey) || str_starts_with($apiKey, 're_') === false) {
        error_log("Resend API Key não configurada");
        return false;
    }
    
    $payload = [
        'from' => "{$fromName} <{$from}>",
        'to' => [$email],
        'subject' => $assunto,
        'html' => $corpoHtml,
    ];
    
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }
    
    error_log("Erro Resend: HTTP {$httpCode} - {$response}");
    return false;
}

$page_title = "Comunicação por Cluster";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-send text-primary me-2"></i>Comunicação Segmentada por Cluster</h1>
            <span class="text-muted">Envie e-mails e mensagens WhatsApp para associados agrupados por região</span>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?> alert-dismissible fade show">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Formulário de Envio -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-pen me-2"></i>Nova Comunicação</h5>
        </div>
        <div class="card-body">
            <form method="post" id="formComunicacao">
                <?php echo CSRFProtection::getTokenField(); ?>
                <input type="hidden" name="acao" value="enviar_email">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Cluster de Comunicação <span class="text-danger">*</span></label>
                        <select name="cluster" class="form-select" required>
                            <option value="">Selecione um cluster...</option>
                            <?php foreach ($clusters as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['cluster_comunicacao']); ?>">
                                <?php echo htmlspecialchars($c['cluster_comunicacao']); ?> (<?php echo $c['qtd']; ?> associados)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Baseado na mesorregião da cidade do associado (RS)</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Associação (opcional)</label>
                        <select name="associacao_id" class="form-select">
                            <option value="">Todas as associações</option>
                            <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nome'] . ' (' . $a['sigla'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Canal de Envio <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="tipo_envio" id="tipo_email" value="email" checked>
                            <label class="btn btn-outline-primary" for="tipo_email"><i class="bi bi-envelope me-1"></i> E-mail</label>
                            
                            <input type="radio" class="btn-check" name="tipo_envio" id="tipo_whatsapp" value="whatsapp">
                            <label class="btn btn-outline-success" for="tipo_whatsapp"><i class="bi bi-whatsapp me-1"></i> WhatsApp</label>
                            
                            <input type="radio" class="btn-check" name="tipo_envio" id="tipo_ambos" value="ambos">
                            <label class="btn btn-outline-dark" for="tipo_ambos"><i class="bi bi-plus-circle me-1"></i> Ambos</label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Assunto <span class="text-danger">*</span></label>
                    <input type="text" name="assunto" class="form-control" placeholder="Ex: Assembleia Geral - Convocação" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Mensagem <span class="text-danger">*</span></label>
                    <textarea name="corpo" class="form-control" rows="8" required placeholder="Olá {{nome}},\n\nSua mensagem aqui...\n\nVariáveis disponíveis:\n{{nome}} - Nome do associado\n{{cpf}} - CPF\n{{cluster}} - Cluster de comunicação"></textarea>
                    <div class="form-text">
                        Use <code>{{nome}}</code>, <code>{{cpf}}</code>, <code>{{cluster}}</code> para personalização automática.
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Enviar Comunicação</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="previewMessage()">Visualizar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pré-visualização da Mensagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Links Modal -->
    <div class="modal fade" id="whatsappModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-whatsapp me-2"></i>Links WhatsApp - Cluster: <span id="whatsappCluster"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="whatsappContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumo dos Clusters -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Associados por Cluster (RS)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaClusters">
                    <thead class="table-light">
                        <tr>
                            <th>Cluster</th>
                            <th class="text-center">Qtd Associados</th>
                            <th class="text-center">Com E-mail</th>
                            <th class="text-center">Com Telefone</th>
                            <th class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($clusters as $cluster):
                            $clusterNome = $cluster['cluster_comunicacao'];
                            
                            // Contar com e-mail
                            $stmt = $db->prepare("
                                SELECT COUNT(*) FROM associados 
                                WHERE estado = 23 AND cluster_comunicacao = ? AND status = 'ativo' AND email IS NOT NULL AND email != ''
                            ");
                            $stmt->execute([$clusterNome]);
                            $comEmail = $stmt->fetchColumn();
                            
                            // Contar com telefone
                            $stmt = $db->prepare("
                                SELECT COUNT(*) FROM associados 
                                WHERE estado = 23 AND cluster_comunicacao = ? AND status = 'ativo' AND telefone IS NOT NULL AND telefone != ''
                            ");
                            $stmt->execute([$clusterNome]);
                            $comTel = $stmt->fetchColumn();
                        ?>
                        <tr>
                            <td><span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($clusterNome); ?></span></td>
                            <td class="text-center"><?php echo $cluster['qtd']; ?></td>
                            <td class="text-center"><?php echo $comEmail; ?></td>
                            <td class="text-center"><?php echo $comTel; ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="preencherCluster('<?php echo htmlspecialchars($clusterNome, ENT_QUOTES); ?>')">
                                    <i class="bi bi-send me-1"></i> Comunicar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelaClusters').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 15,
        "order": [[0, 'asc']]
    });
});

function preencherCluster(cluster) {
    document.querySelector('select[name="cluster"]').value = cluster;
    document.querySelector('select[name="cluster"]').focus();
}

function previewMessage() {
    const assunto = document.querySelector('input[name="assunto"]').value;
    const corpo = document.querySelector('textarea[name="corpo"]').value;
    const cluster = document.querySelector('select[name="cluster"]').value;
    
    if (!assunto || !corpo) {
        alert('Preencha assunto e mensagem primeiro.');
        return;
    }
    
    // Exemplo com dados fictícios
    const preview = corpo
        .replace(/{{nome}}/g, '<strong>João da Silva</strong>')
        .replace(/{{cpf}}/g, '<strong>123.456.789-00</strong>')
        .replace(/{{cluster}}/g, `<strong>${cluster || 'CLUSTER_EXEMPLO'}</strong>`)
        .replace(/\n/g, '<br>');
    
    document.getElementById('previewContent').innerHTML = `
        <div class="card">
            <div class="card-header"><strong>Assunto:</strong> ${assunto}</div>
            <div class="card-body">${preview}</div>
        </div>
        <div class="alert alert-info mt-2">
            <small>Esta é uma prévia com dados fictícios. No envio real, cada associado receberá a mensagem personalizada.</small>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

function abrirWhatsApps(cluster) {
    // Os links são armazenados na sessão PHP, precisamos recarregar via AJAX
    fetch('<?php echo SITE_URL; ?>/api/comunicacao/whatsapp_links.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo CSRFProtection::getToken(); ?>' },
        body: JSON.stringify({ cluster: cluster })
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso && data.links) {
            let html = '<div class="list-group">';
            for (const [nome, link] of Object.entries(data.links)) {
                html += `
                    <a href="${link}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${nome}</strong>
                            <br><small class="text-muted">${link}</small>
                        </div>
                        <i class="bi bi-box-arrow-up-right text-success"></i>
                    </a>
                `;
            }
            html += '</div>';
            document.getElementById('whatsappContent').innerHTML = html;
            document.getElementById('whatsappCluster').textContent = cluster;
            new bootstrap.Modal(document.getElementById('whatsappModal')).show();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>