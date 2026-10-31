<?php
$page_title = "Consulta CNPO (Cadastro Nacional de Produtores Orgânicos)";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

// Apenas Admin e Gestor podem consultar o CNPO
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header("Location: " . SITE_URL . "/dashboard");
    exit;
}

// Lista de UFs disponíveis na tabela local (para o filtro)
$ufs = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT DISTINCT uf FROM cnpo WHERE uf <> 'N/A' AND uf <> '' ORDER BY uf");
    $ufs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $ufs = [];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="mt-2 mb-3">
        <h1><i class="bi bi-patch-check-fill text-success"></i> Consulta CNPO</h1>
        <p class="text-muted mb-0">
            Verifique se um agricultor, produtor de agroindústria ou de produção animal/vegetal
            é registrado no <strong>Cadastro Nacional de Produtores Orgânicos</strong> (MAPA).
            Dados da planilha oficial sincronizada na tabela <code>cnpo</code>.
        </p>
    </div>

    <div class="alert alert-warning small">
        <i class="bi bi-info-circle me-1"></i>
        O CNPO público (MAPA) divulga o CPF/CNPJ de forma <strong>parcial</strong>
        (ex.: <code>***714.687***</code>). Por isso a confirmação é feita por
        <strong>nome + município/UF</strong> e pelo <strong>escopo</strong> de produção.
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form id="formConsultaCNPO" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="termo">Nome do produtor / entidade</label>
                        <input type="text" class="form-control" id="termo" name="termo" minlength="2"
                               placeholder="Ex.: VINÍCOLA BOM JESUS (opcional)">
                        <div class="form-text">Preencha o nome <strong>ou</strong> use os filtros de UF/Cidade/Tipo.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="uf">UF</label>
                        <select class="form-select" id="uf" name="uf">
                            <option value="">Todas</option>
                            <?php foreach ($ufs as $uf): ?>
                                <option value="<?php echo htmlspecialchars($uf, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($uf, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="cidade">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" placeholder="Ex.: ESTÂNCIA VELHA">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="tipo_entidade">Tipo</label>
                        <select class="form-select" id="tipo_entidade" name="tipo_entidade">
                            <option value="">Todos</option>
                            <option value="CERTIFICADORA">Certificadora</option>
                            <option value="OCS">OCS</option>
                            <option value="OPAC">OPAC</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-search"></i> Buscar no CNPO
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnLimpar">
                        <i class="bi bi-eraser"></i> Limpar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="statusBusca"></div>

    <div class="card shadow-sm d-none" id="cardResultados">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check text-success me-1"></i> Resultados da busca</span>
            <span class="badge bg-secondary" id="badgeTotal">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Produtor</th>
                        <th>Entidade (certificadora/OCS)</th>
                        <th>Tipo</th>
                        <th>Município</th>
                        <th>UF</th>
                        <th>Escopo</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody id="tbodyResultados">
                    <tr><td colspan="7" class="text-center text-muted">Nenhum resultado.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2" id="paginacaoWrap">
            <div class="small text-muted" id="resumoResultados"></div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 small text-muted text-nowrap" for="limite">Por página</label>
                    <select class="form-select form-select-sm" id="limite" style="width:auto">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
                <nav aria-label="Paginação dos resultados">
                    <ul class="pagination pagination-sm mb-0" id="paginacao"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('formConsultaCNPO');
    const statusEl = document.getElementById('statusBusca');
    const cardEl = document.getElementById('cardResultados');
    const badgeEl = document.getElementById('badgeTotal');
    const tbodyEl = document.getElementById('tbodyResultados');
    const resumoEl = document.getElementById('resumoResultados');
    const paginacaoEl = document.getElementById('paginacao');
    const limiteEl = document.getElementById('limite');

    // Estado da última busca (permite paginar sem reenviar o formulário)
    let filtros = null;
    let paginaAtual = 1;
    let totalPaginas = 1;
    let totalRegistros = 0;
    let limiteAtual = 50;
    let seqBusca = 0;      // descarta respostas de buscas antigas
    let carregando = false;

    function mostrarStatus(tipo, msg) {
        statusEl.innerHTML =
            '<div class="alert alert-' + tipo + ' d-flex justify-content-between align-items-center mb-3">' +
            '<span>' + msg + '</span>' +
            (tipo === 'warning' || tipo === 'danger'
                ? '<button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>'
                : '') +
            '</div>';
    }

    function esc(html) {
        const d = document.createElement('div');
        d.textContent = html == null ? '' : String(html);
        return d.innerHTML;
    }

    function renderLinhas(rows) {
        if (!rows.length) {
            tbodyEl.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum registro encontrado para esses critérios.</td></tr>';
            return;
        }
        tbodyEl.innerHTML = rows.map(function(r) {
            return '<tr>' +
                '<td class="fw-semibold">' + esc(r.nome_produtor) + '</td>' +
                '<td>' + esc(r.entidade) + '</td>' +
                '<td><span class="badge bg-info">' + esc(r.tipo_entidade) + '</span></td>' +
                '<td>' + esc(r.cidade) + '</td>' +
                '<td>' + esc(r.uf) + '</td>' +
                '<td>' + esc(r.escopo) + '</td>' +
                '<td><span class="badge ' + (r.situacao === 'ATIVO' ? 'bg-success' : 'bg-secondary') + '">' + esc(r.situacao) + '</span></td>' +
                '</tr>';
        }).join('');
    }

    function renderResumo(rows) {
        if (!totalRegistros) {
            resumoEl.textContent = 'Nenhum registro encontrado.';
            return;
        }
        const inicio = (paginaAtual - 1) * limiteAtual + 1;
        const fim = inicio + rows.length - 1;
        resumoEl.textContent = 'Exibindo ' + inicio + '–' + fim + ' de ' +
            totalRegistros.toLocaleString('pt-BR') + ' registro' + (totalRegistros === 1 ? '' : 's') +
            ' (página ' + paginaAtual + ' de ' + totalPaginas + ')';
    }

    // Janela de páginas: 1 … atual-1, atual, atual+1 … última
    function listaPaginas(atual, total) {
        if (total <= 7) {
            const todas = [];
            for (let i = 1; i <= total; i++) todas.push(i);
            return todas;
        }
        const paginas = [1];
        const ini = Math.max(2, atual - 1);
        const fim = Math.min(total - 1, atual + 1);
        if (ini > 2) paginas.push('...');
        for (let i = ini; i <= fim; i++) paginas.push(i);
        if (fim < total - 1) paginas.push('...');
        paginas.push(total);
        return paginas;
    }

    function renderPaginacao() {
        const itens = [];
        const anterior = paginaAtual - 1;
        const proxima = paginaAtual + 1;

        itens.push('<li class="page-item' + (paginaAtual <= 1 || carregando ? ' disabled' : '') + '">' +
            '<button type="button" class="page-link" data-pagina="' + anterior + '" aria-label="Página anterior">&laquo;</button></li>');

        listaPaginas(paginaAtual, totalPaginas).forEach(function(p) {
            if (p === '...') {
                itens.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
                return;
            }
            itens.push('<li class="page-item' + (p === paginaAtual ? ' active' : '') + '">' +
                '<button type="button" class="page-link" data-pagina="' + p + '"' +
                (p === paginaAtual ? ' aria-current="page"' : '') + '>' + p + '</button></li>');
        });

        itens.push('<li class="page-item' + (paginaAtual >= totalPaginas || carregando ? ' disabled' : '') + '">' +
            '<button type="button" class="page-link" data-pagina="' + proxima + '" aria-label="Próxima página">&raquo;</button></li>');

        paginacaoEl.innerHTML = itens.join('');
    }

    function buscar(pagina) {
        if (!filtros) return;

        const seq = ++seqBusca;
        carregando = true;
        statusEl.innerHTML = '<div class="alert alert-info mb-3"><i class="bi bi-hourglass-split me-1"></i> Consultando CNPO...</div>';
        cardEl.classList.add('d-none');

        const params = new URLSearchParams(filtros);
        params.set('pagina', pagina);
        params.set('limite', limiteEl.value);

        fetch('<?php echo SITE_URL; ?>/api/cnpo_consulta.php?' + params.toString())
            .then(function(res) {
                return res.json().then(function(body) {
                    return { ok: res.ok, body: body };
                });
            })
            .then(function({ ok, body }) {
                if (seq !== seqBusca) return;   // resposta obsoleta
                carregando = false;

                if (!ok) {
                    mostrarStatus('danger', body.error || 'Erro ao consultar o CNPO.');
                    return;
                }

                statusEl.innerHTML = '';
                const rows = body.data || [];
                paginaAtual = body.pagina || 1;
                totalPaginas = body.total_paginas || 1;
                totalRegistros = body.total || 0;
                limiteAtual = body.limite || Number(limiteEl.value);

                badgeEl.textContent = totalRegistros;
                renderLinhas(rows);
                renderResumo(rows);
                renderPaginacao();
                cardEl.classList.remove('d-none');

                if (pagina > 1) {
                    cardEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            })
            .catch(function() {
                if (seq !== seqBusca) return;
                carregando = false;
                mostrarStatus('danger', 'Falha de comunicação com o servidor.');
            });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const termo = document.getElementById('termo').value.trim();
        const uf = document.getElementById('uf').value;
        const cidade = document.getElementById('cidade').value.trim();
        const tipo = document.getElementById('tipo_entidade').value;

        // O nome do produtor é opcional: exige apenas algum critério de busca.
        if (termo.length === 0 && !uf && !cidade && !tipo) {
            mostrarStatus('warning', 'Informe o nome do produtor ou selecione ao menos um filtro (UF, cidade ou tipo).');
            return;
        }
        if (termo.length > 0 && termo.length < 2) {
            mostrarStatus('warning', 'Informe ao menos 2 caracteres do nome do produtor, ou deixe o campo vazio.');
            return;
        }

        filtros = {};
        if (termo) filtros.termo = termo;
        if (uf) filtros.uf = uf;
        if (cidade) filtros.cidade = cidade;
        if (tipo) filtros.tipo_entidade = tipo;

        buscar(1);
    });

    // Troca de página pelos botões da paginação
    paginacaoEl.addEventListener('click', function(e) {
        const btn = e.target.closest('button[data-pagina]');
        if (!btn || btn.disabled) return;
        const li = btn.closest('.page-item');
        if (li && li.classList.contains('disabled')) return;
        const destino = parseInt(btn.getAttribute('data-pagina'), 10);
        if (!destino || destino < 1 || destino > totalPaginas || destino === paginaAtual) return;
        buscar(destino);
    });

    // Troca do tamanho da página: volta para a primeira
    limiteEl.addEventListener('change', function() {
        buscar(1);
    });

    document.getElementById('btnLimpar').addEventListener('click', function() {
        seqBusca++;              // invalida respostas em andamento
        carregando = false;
        filtros = null;
        paginaAtual = 1;
        totalPaginas = 1;
        totalRegistros = 0;
        form.reset();
        statusEl.innerHTML = '';
        cardEl.classList.add('d-none');
        badgeEl.textContent = '0';
        resumoEl.textContent = '';
        paginacaoEl.innerHTML = '';
        tbodyEl.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum resultado.</td></tr>';
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>