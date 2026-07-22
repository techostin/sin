<?php
require_once 'config/config.php';
require_once 'classes/AtividadeDiaria.php';
// --- INÍCIO DO ROTEADOR AMIGÁVEL ---
if (isset($_GET['url'])) {
    $url = trim($_GET['url'], '/');
    $parts = explode('/', $url);

    // ===== ROTAS DE RELATÓRIOS =====

    // Página principal de relatórios
    if ($parts[0] === 'relatorios' && !isset($parts[1])) {
        require __DIR__ . '/relatorios.php';
        exit;
    }

    // Relatórios específicos (ex: /relatorios/associados/lista)
    if ($parts[0] === 'relatorios' && isset($parts[1]) && isset($parts[2])) {
        $categoria = $parts[1];
        $relatorio = $parts[2];
        $_GET['relatorio'] = $relatorio;
        require __DIR__ . '/relatorios.php';
        exit;
    }

    // Relatórios rápidos via API
    if ($parts[0] === 'api' && $parts[1] === 'relatorios' && isset($parts[2])) {
        $tipo = $parts[2];
        $arquivo = __DIR__ . '/api/relatorios/' . $tipo . '.php';

        if (file_exists($arquivo)) {
            require $arquivo;
        } else {
            header('HTTP/1.0 404 Not Found');
            echo json_encode(['erro' => 'Relatório não encontrado']);
        }
        exit;
    }

    // ===== ROTAS DE CERTIFICAÇÃO PARTICIPATIVA =====

    // API de Certificação
    if ($parts[0] === 'api' && $parts[1] === 'certificacao' && isset($parts[2]) && $parts[2] === 'controller.php') {
        require __DIR__ . '/api/certificacao/controller.php';
        exit;
    }

    // API de Plano de Manejo
    if ($parts[0] === 'api' && $parts[1] === 'plano-manejo') {
        require __DIR__ . '/api/plano_manejo.php';
        exit;
    }

    // Listagem de certificações
    if ($parts[0] === 'certificacoes' && !isset($parts[1])) {
        require __DIR__ . '/modulos/certificacao/listar.php';
        exit;
    }
    if ($parts[0] === 'insumos-aprovados' && !isset($parts[1])) {
        require __DIR__ . '/modulos/certificacao/insumos.php';
        exit;
    }
    if ($parts[0] === 'certificacao' && isset($parts[1]) && $parts[1] === 'listar') {
        require __DIR__ . '/modulos/certificacao/listar.php';
        exit;
    }

    // Nova certificação
    if ($parts[0] === 'certificacao' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/certificacao/cadastrar.php';
        exit;
    }

    // Editar certificação
    if ($parts[0] === 'certificacao' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/certificacao/editar.php';
        exit;
    }

    // Renovar certificação
    if ($parts[0] === 'certificacao' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'renovar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/certificacao/renovar.php';
        exit;
    }

    // Ver histórico de certificações por associado
    if ($parts[0] === 'certificacao' && isset($parts[1]) && $parts[1] === 'historico' && isset($parts[2]) && is_numeric($parts[2])) {
        $_GET['associado_id'] = $parts[2];
        require __DIR__ . '/modulos/certificacao/historico.php';
        exit;
    }

    // Planos de Manejo
    if ($parts[0] === 'planos-manejo' && !isset($parts[1])) {
        require __DIR__ . '/modulos/plano_manejo/listar.php';
        exit;
    }
    if ($parts[0] === 'plano-manejo' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/plano_manejo/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'plano-manejo' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'visualizar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/plano_manejo/visualizar.php';
        exit;
    }
    if ($parts[0] === 'plano-manejo' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/plano_manejo/editar.php';
        exit;
    }
    if ($parts[0] === 'plano-manejo' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'aprovar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/plano_manejo/aprovar.php';
        exit;
    }
    if ($parts[0] === 'plano-manejo' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'upload_croqui') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/plano_manejo/upload_croqui.php';
        exit;
    }

    // ===== ROTAS DO CADERNO DE CAMPO =====

    // Dashboard do Caderno de Campo
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'dashboard') {
        require __DIR__ . '/modulos/caderno_campo/dashboard.php';
        exit;
    }

    // Dashboard Simplificado do Agricultor
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'dashboard-agricultor') {
        require __DIR__ . '/modulos/caderno_campo/dashboard_agricultor.php';
        exit;
    }

    // Propriedades
    if ($parts[0] === 'propriedades' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/propriedades/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'propriedades') {
        require __DIR__ . '/modulos/caderno_campo/propriedades/listar.php';
        exit;
    }
    if ($parts[0] === 'propriedade' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/propriedades/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'propriedade' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/propriedades/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'propriedade' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/caderno_campo/propriedades/editar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'propriedade' && isset($parts[2]) && is_numeric($parts[2]) && isset($parts[3]) && $parts[3] === 'editar') {
        $_GET['id'] = $parts[2];
        require __DIR__ . '/modulos/caderno_campo/propriedades/editar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'propriedade' && isset($parts[2]) && is_numeric($parts[2]) && isset($parts[3]) && $parts[3] === 'imagens') {
        $_GET['id'] = $parts[2];
        require __DIR__ . '/modulos/caderno_campo/propriedades/visualizar_imagens.php';
        exit;
    }

    // Lavouras
    if ($parts[0] === 'lavouras' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/lavouras/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'lavouras') {
        require __DIR__ . '/modulos/caderno_campo/lavouras/listar.php';
        exit;
    }
    if ($parts[0] === 'lavoura' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/lavouras/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'lavoura' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/lavouras/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'lavoura' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'editar') {
        if (strlen($parts[1]) === 36) {
            require_once __DIR__ . '/classes/Lavoura.php';
            $lav = new Lavoura();
            $res = $lav->getByUuid($parts[1]);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $parts[1];
        }
        require __DIR__ . '/modulos/caderno_campo/lavouras/editar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'lavoura' && isset($parts[2]) && (is_numeric($parts[2]) || strlen($parts[2]) === 36) && isset($parts[3]) && $parts[3] === 'editar') {
        $id_or_uuid = $parts[2];
        if (strlen($id_or_uuid) === 36) {
            require_once __DIR__ . '/classes/Lavoura.php';
            $lav = new Lavoura();
            $res = $lav->getByUuid($id_or_uuid);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $id_or_uuid;
        }
        require __DIR__ . '/modulos/caderno_campo/lavouras/editar.php';
        exit;
    }

    // Insumos
    if ($parts[0] === 'insumos' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/insumos/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'insumos') {
        require __DIR__ . '/modulos/caderno_campo/insumos/listar.php';
        exit;
    }
    if ($parts[0] === 'insumo' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/insumos/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'insumo' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/insumos/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'insumo' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'editar') {
        if (strlen($parts[1]) === 36) {
            require_once __DIR__ . '/classes/Insumo.php';
            $ins = new Insumo();
            $res = $ins->getByUuid($parts[1]);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $parts[1];
        }
        require __DIR__ . '/modulos/caderno_campo/insumos/editar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'insumo' && isset($parts[2]) && (is_numeric($parts[2]) || strlen($parts[2]) === 36) && isset($parts[3]) && $parts[3] === 'editar') {
        $id_or_uuid = $parts[2];
        if (strlen($id_or_uuid) === 36) {
            require_once __DIR__ . '/classes/Insumo.php';
            $ins = new Insumo();
            $res = $ins->getByUuid($id_or_uuid);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $id_or_uuid;
        }
        require __DIR__ . '/modulos/caderno_campo/insumos/editar.php';
        exit;
    }

    // Produtos
    if ($parts[0] === 'produtos' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/produtos/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'produtos') {
        require __DIR__ . '/modulos/caderno_campo/produtos/listar.php';
        exit;
    }
    if ($parts[0] === 'produto' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/produtos/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'produto' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/produtos/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'produto' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'editar') {
        if (strlen($parts[1]) === 36) {
            require_once __DIR__ . '/classes/Produto.php';
            $p = new Produto();
            $res = $p->getByUuid($parts[1]);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $parts[1];
        }
        require __DIR__ . '/modulos/caderno_campo/produtos/editar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'produto' && isset($parts[2]) && (is_numeric($parts[2]) || strlen($parts[2]) === 36) && isset($parts[3]) && $parts[3] === 'editar') {
        $id_or_uuid = $parts[2];
        if (strlen($id_or_uuid) === 36) {
            require_once __DIR__ . '/classes/Produto.php';
            $p = new Produto();
            $res = $p->getByUuid($id_or_uuid);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $id_or_uuid;
        }
        require __DIR__ . '/modulos/caderno_campo/produtos/editar.php';
        exit;
    }

    // Lotes de Produção
    if ($parts[0] === 'lotes' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/lotes_producao/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'lotes') {
        require __DIR__ . '/modulos/caderno_campo/lotes_producao/listar.php';
        exit;
    }
    if ($parts[0] === 'lote' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/lotes_producao/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'lote' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/lotes_producao/cadastrar.php';
        exit;
    }
    // Ocorrências
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'ocorrencias') {
        require __DIR__ . '/modulos/caderno_campo/ocorrencias/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'ocorrencia' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/ocorrencias/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'ocorrencia' && isset($parts[2]) && is_numeric($parts[2]) && isset($parts[3]) && $parts[3] === 'editar') {
        $_GET['id'] = $parts[2];
        require __DIR__ . '/modulos/caderno_campo/ocorrencias/editar.php';
        exit;
    }

    // Lotes de Produção (com suporte a UUID)
    if ($parts[0] === 'lote' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36)) {
        if (strlen($parts[1]) === 36) {
            require_once __DIR__ . '/classes/LoteProducao.php';
            $lp = new LoteProducao();
            $res = $lp->getByUuid($parts[1]);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $parts[1];
        }

        if (isset($parts[2]) && $parts[2] === 'editar') {
            require __DIR__ . '/modulos/caderno_campo/lotes_producao/editar.php';
        } else {
            require __DIR__ . '/modulos/caderno_campo/lotes_producao/rastrear.php';
        }
        exit;
    }

    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'lote' && isset($parts[2]) && (is_numeric($parts[2]) || strlen($parts[2]) === 36)) {
        $id_or_uuid = $parts[2];
        if (strlen($id_or_uuid) === 36) {
            require_once __DIR__ . '/classes/LoteProducao.php';
            $lp = new LoteProducao();
            $res = $lp->getByUuid($id_or_uuid);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $id_or_uuid;
        }

        if (isset($parts[3]) && $parts[3] === 'editar') {
            require __DIR__ . '/modulos/caderno_campo/lotes_producao/editar.php';
        } else {
            require __DIR__ . '/modulos/caderno_campo/lotes_producao/rastrear.php';
        }
        exit;
    }

    // Categorias de Atividades
    if ($parts[0] === 'categorias-atividades' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/categorias_atividades/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'categorias-atividades') {
        require __DIR__ . '/modulos/caderno_campo/categorias_atividades/listar.php';
        exit;
    }
    if ($parts[0] === 'categoria-atividade' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/categorias_atividades/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'categoria-atividade' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/categorias_atividades/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'categoria-atividade' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/caderno_campo/categorias_atividades/editar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'categoria-atividade' && isset($parts[2]) && is_numeric($parts[2]) && isset($parts[3]) && $parts[3] === 'editar') {
        $_GET['id'] = $parts[2];
        require __DIR__ . '/modulos/caderno_campo/categorias_atividades/editar.php';
        exit;
    }

    // Atividades Diárias
    if ($parts[0] === 'atividades-diarias' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/atividade_diaria/listar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'atividades-diarias') {
        require __DIR__ . '/modulos/caderno_campo/atividade_diaria/listar.php';
        exit;
    }
    if ($parts[0] === 'atividade-diaria' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/atividade_diaria/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'atividade-diaria' && isset($parts[2]) && $parts[2] === 'novo') {
        require __DIR__ . '/modulos/caderno_campo/atividade_diaria/cadastrar.php';
        exit;
    }
    if (($parts[0] === 'atividade-diaria' && isset($parts[1]) && (is_numeric($parts[1]) || strlen($parts[1]) === 36) && isset($parts[2]) && $parts[2] === 'editar')) {
        if (strlen($parts[1]) === 36) {
            $at = new AtividadeDiaria();
            $res = $at->getByUuid($parts[1]);
            if ($res) $_GET['id'] = $res['id'];
        } else {
            $_GET['id'] = $parts[1];
        }
        require __DIR__ . '/modulos/caderno_campo/atividade_diaria/editar.php';
        exit;
    }

    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'atividade-diaria' && isset($parts[2])) {
        $id_or_uuid = $parts[2];
        $action = $parts[3] ?? '';

        if (strlen($id_or_uuid) === 36) {
            $at = new AtividadeDiaria();
            $res = $at->getByUuid($id_or_uuid);
            if ($res) $_GET['id'] = $res['id'];
        } else if (is_numeric($id_or_uuid)) {
            $_GET['id'] = $id_or_uuid;
        }

        if ($action === 'imagens') {
            require __DIR__ . '/modulos/caderno_campo/atividade_diaria/visualizar_imagens.php';
            exit;
        } else if ($action === 'editar') {
            require __DIR__ . '/modulos/caderno_campo/atividade_diaria/editar.php';
            exit;
        }
    }

    // Sincronização Offline
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'sincronizacao') {
        require __DIR__ . '/modulos/caderno_campo/sincronizacao.php';
        exit;
    }

    // Validação de Insumos
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'insumos' && isset($parts[2]) && $parts[2] === 'validacao') {
        require __DIR__ . '/modulos/caderno_campo/insumos/validacao.php';
        exit;
    }

    // Relatórios do Caderno de Campo
    if ($parts[0] === 'relatorios' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/relatorios_tabs.php';
        exit;
    }
    if ($parts[0] === 'relatorios-admin' && !isset($parts[1])) {
        require __DIR__ . '/modulos/caderno_campo/relatorios_admin.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && !isset($parts[2])) {
        require __DIR__ . '/modulos/caderno_campo/relatorios_tabs.php';
        exit;
    }
    // Exportação de relatórios (PDF/Excel)
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && isset($parts[2]) && $parts[2] === 'exportar') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/exportar_relatorio.php';
        exit;
    }

    // Relatório de Associados
    if ($parts[0] === 'relatorio-associados' && !isset($parts[1])) {
        require __DIR__ . '/modulos/relatorios/relatorio_associados.php';
        exit;
    }
    if ($parts[0] === 'relatorio' && isset($parts[1]) && $parts[1] === 'geral') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/relatorio_geral.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && isset($parts[2]) && $parts[2] === 'geral') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/relatorio_geral.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && isset($parts[2]) && $parts[2] === 'atividades') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/atividades.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && isset($parts[2]) && $parts[2] === 'insumos') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/insumos.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && isset($parts[2]) && $parts[2] === 'producao') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/producao.php';
        exit;
    }
    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && isset($parts[2]) && $parts[2] === 'propriedade') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/propriedade.php';
        exit;
    }

    if ($parts[0] === 'caderno-campo' && isset($parts[1]) && $parts[1] === 'relatorios' && isset($parts[2]) && $parts[2] === 'lotes') {
        require __DIR__ . '/modulos/caderno_campo/relatorios/lotes.php';
        exit;
    }

    // ===== ROTAS DE VISITAS TÉCNICAS E INSPEÇÕES =====

    // Listagem de Visitas
    if ($parts[0] === 'visitas-tecnicas' && !isset($parts[1])) {
        require __DIR__ . '/modulos/roteiros_inspecao/visitas/listar.php';
        exit;
    }
    if ($parts[0] === 'visita-tecnica' && isset($parts[1]) && $parts[1] === 'agendar') {
        require __DIR__ . '/modulos/roteiros_inspecao/visitas/agendar.php';
        exit;
    }

    // Realizar Inspeção (ex: /app/visita-tecnica/123/realizar)
    if ($parts[0] === 'visita-tecnica' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'realizar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/roteiros_inspecao/visitas/realizar.php';
        exit;
    }

    // Relatório da Visita (ex: /app/visita-tecnica/123/relatorio)
    if ($parts[0] === 'visita-tecnica' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'relatorio') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/roteiros_inspecao/visitas/relatorio.php';
        exit;
    }

    // Gestão de Roteiros (Templates)
    if ($parts[0] === 'roteiros-inspecao' && !isset($parts[1])) {
        require __DIR__ . '/modulos/roteiros_inspecao/templates/listar.php';
        exit;
    }
    if ($parts[0] === 'roteiro-inspecao' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/roteiros_inspecao/templates/cadastrar.php';
        exit;
    }

    // ===== ROTAS DE NÃO CONFORMIDADES =====
    if ($parts[0] === 'nao-conformidades' && !isset($parts[1])) {
        require __DIR__ . '/modulos/roteiros_inspecao/nao_conformidades/listar.php';
        exit;
    }
    if ($parts[0] === 'nao-conformidade' && isset($parts[1]) && $parts[1] === 'acompanhar') {
        require __DIR__ . '/modulos/roteiros_inspecao/nao_conformidades/acompanhar.php';
        exit;
    }
    if ($parts[0] === 'nao-conformidade' && isset($parts[1]) && is_numeric($parts[1])) {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/roteiros_inspecao/nao_conformidades/detalhe.php';
        exit;
    }


    // Associados
    if ($parts[0] === 'associados' && !isset($parts[1])) {
        require __DIR__ . '/modulos/associados/listar.php';
        exit;
    }
    if ($parts[0] === 'associados' && isset($parts[1]) && $parts[1] === 'relatorio') {
        require __DIR__ . '/modulos/associados/form_relatorio.php';
        exit;
    }
    if ($parts[0] === 'associado' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/associados/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'associado' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/associados/editar.php';
        exit;
    }
    // Compatibilidade com rota legada: /associado/editar?id=123
    if ($parts[0] === 'associado' && isset($parts[1]) && $parts[1] === 'editar' && isset($_GET['id']) && is_numeric($_GET['id'])) {
        require __DIR__ . '/modulos/associados/editar.php';
        exit;
    }

    // Rota para exclusão de associado
    if ($parts[0] === 'associado' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'excluir') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/associados/excluir.php';
        exit;
    }

    // Associações
    if ($parts[0] === 'associacoes' && !isset($parts[1])) {
        require __DIR__ . '/modulos/associacoes/listar.php';
        exit;
    }
    if ($parts[0] === 'associacao' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/associacoes/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'associacao' && isset($parts[1]) && $parts[1] === 'editar') {
        if (isset($_POST['id'])) {
            $_GET['id'] = $_POST['id'];
        }
        require __DIR__ . '/modulos/associacoes/editar.php';
        exit;
    }

    // Usuários
    if ($parts[0] === 'usuarios' && !isset($parts[1])) {
        require __DIR__ . '/modulos/usuarios/listar.php';
        exit;
    }
    if ($parts[0] === 'usuario' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/usuarios/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'usuario' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/usuarios/editar.php';
        exit;
    }

    // Fornecedores
    if ($parts[0] === 'fornecedor' && !isset($parts[1])) {
        require __DIR__ . '/modulos/fornecedor/listar.php';
        exit;
    }
    if ($parts[0] === 'fornecedor' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/fornecedor/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'fornecedor' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/fornecedor/editar.php';
        exit;
    }

    // Eventos
    if ($parts[0] === 'eventos' && !isset($parts[1])) {
        require __DIR__ . '/modulos/eventos/listar.php';
        exit;
    }
    if ($parts[0] === 'eventos' && isset($parts[1]) && $parts[1] === 'presencas') {
        require __DIR__ . '/modulos/eventos/presencas.php';
        exit;
    }
    // Compatibilidade com variação singular da rota
    if ($parts[0] === 'evento' && isset($parts[1]) && $parts[1] === 'presencas') {
        require __DIR__ . '/modulos/eventos/presencas.php';
        exit;
    }
    if ($parts[0] === 'evento' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/eventos/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'evento' && isset($parts[1]) && is_numeric($parts[1]) && !isset($parts[2])) {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/eventos/visualizar.php';
        exit;
    }
    if ($parts[0] === 'evento' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/eventos/editar.php';
        exit;
    }

    // Teste
    if ($parts[0] === 'teste') {
        require __DIR__ . '/teste_evento.php';
        exit;
    }

    // Teste banco de dados
    if ($parts[0] === 'teste-db') {
        require __DIR__ . '/teste_db.php';
        exit;
    }

    // Teste AJAX
    if ($parts[0] === 'teste-ajax') {
        require __DIR__ . '/teste_ajax.php';
        exit;
    }

    // Contas
    if ($parts[0] === 'contas' && !isset($parts[1])) {
        require __DIR__ . '/modulos/contas/listar.php';
        exit;
    }
    if ($parts[0] === 'conta' && isset($parts[1]) && $parts[1] === 'novo') {
        require __DIR__ . '/modulos/contas/cadastrar.php';
        exit;
    }
    if ($parts[0] === 'conta' && isset($parts[1]) && is_numeric($parts[1]) && isset($parts[2]) && $parts[2] === 'editar') {
        $_GET['id'] = $parts[1];
        require __DIR__ . '/modulos/contas/editar.php';
        exit;
    }

    // ===== ROTAS DE USUÁRIO =====

    // Perfil do usuário
    if ($parts[0] === 'usuario' && isset($parts[1]) && $parts[1] === 'perfil') {
        require __DIR__ . '/modulos/usuarios/perfil.php';
        exit;
    }

    // Configurações do usuário
    if ($parts[0] === 'usuario' && isset($parts[1]) && $parts[1] === 'configuracoes') {
        require __DIR__ . '/modulos/usuarios/configuracoes.php';
        exit;
    }

    // Salvar notificações
    if ($parts[0] === 'usuario' && isset($parts[1]) && $parts[1] === 'salvar_notificacoes') {
        require __DIR__ . '/modulos/usuarios/salvar_notificacoes.php';
        exit;
    }

    // ===== ROTAS DE AUTENTICAÇÃO =====

    // Login
    if ($parts[0] === 'login' && !isset($parts[1])) {
        require __DIR__ . '/login.php';
        exit;
    }

    // Logout
    if ($parts[0] === 'logout' && !isset($parts[1])) {
        require __DIR__ . '/logout.php';
        exit;
    }

    // ===== ROTAS DE API =====

    // API para buscar cidades
    if ($parts[0] === 'api' && isset($parts[1]) && $parts[1] === 'cidades') {
        require __DIR__ . '/api/get_cidades.php';
        exit;
    }

    // Fallback: página não encontrada
    require __DIR__ . '/404.php';
    exit;
}
// --- FIM DO ROTEADOR AMIGÁVEL ---


// Redirecionar usuário do tipo 'usuario' para o dashboard do Caderno de Campo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_tipo']) && strtolower((string) $_SESSION['user_tipo']) === 'usuario') {
    header('Location: ' . SITE_URL . '/caderno-campo/dashboard-agricultor');
    exit;
}

$page_title = "Dashboard";
require_once 'includes/header.php';
require_once 'includes/menu.php';
require_once 'classes/Evento.php';

$evento = new Evento();
$eventos = $evento->getAllEventos();
$presencas = $evento->getPresencaEventos();
?>

<h1 class="mt-4">Sistema Integrado de Produção Orgânica</h1>
<p>Selecione uma opção no menu ao lado para começar a gerenciar as informações.</p>

<div class="row">
    <div class="col-md-4" style="width: 30%; margin-right: 2.5%;">
        <div class="card text-white bg-primary mb-3">
            <div class="card-header">Associados</div>
            <div class="card-body">
                <h5 class="card-title">Gerenciar Associados</h5>
                <p class="card-text">Cadastre, consulte e edite os dados dos associados.</p>
                <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-light">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4" style="width: 30%; margin-right: 2.5%;">
        <div class="card text-white bg-success mb-3">
            <div class="card-header">Caderno de Campo</div>
            <div class="card-body">
                <h5 class="card-title">Registrar Atividades</h5>
                <p class="card-text">Acompanhe as atividades diárias, insumos e produção.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-light">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4" style="width: 30%; margin-right: 2.5%;">
        <div class="card text-white bg-info mb-3">
            <div class="card-header">Relatórios</div>
            <div class="card-body">
                <h5 class="card-title">Relatórios</h5>
                <p class="card-text">Exporte informações importantes do sistema.</p>
                <a href="<?php echo SITE_URL; ?>/relatorios" class="btn btn-light">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4" style="width: 30%; margin-right: 2.5%;">
        <div class="card text-white bg-dark mb-3">
            <div class="card-header"><i class="bi bi-journal-richtext me-1"></i>Caderno de Campo</div>
            <div class="card-body">
                <h5 class="card-title">Painel do Caderno</h5>
                <p class="card-text">Acesse o painel completo de atividades, lavouras e insumos.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-light">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4" style="width: 30%; margin-right: 2.5%;">
        <div class="card text-white bg-secondary mb-3">
            <div class="card-header">Dashboard Caderno de Campo</div>
            <div class="card-body">
                <h5 class="card-title">Painel de Controle</h5>
                <p class="card-text">Acesse o dashboard completo do caderno de campo.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-light">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4" style="width: 30%;">
        <div class="card text-white bg-warning mb-3">
            <div class="card-header">Relatório de Associados</div>
            <div class="card-body">
                <h5 class="card-title">Relatório Geral</h5>
                <p class="card-text">Relatório completo de associados e associações.</p>
                <a href="javascript:void(0)" onclick="abrirRelatorioAssociados()" class="btn btn-light">Gerar Relatório
                    <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-calendar-event-fill me-1"></i>
                Próximos Eventos e Eventos Recentes
            </div>
            <div class="card-body">
                <table id="eventosTable" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Data</th>
                            <th>Local</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventos as $evt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($evt['nome']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($evt['data_evento'])); ?></td>
                                <td><?php echo htmlspecialchars($evt['local']); ?></td>
                                <td><span class="badge bg-<?php
                                switch ($evt['status']) {
                                    case 'Agendado':
                                        echo 'primary';
                                        break;
                                    case 'Realizado':
                                        echo 'success';
                                        break;
                                    case 'Cancelado':
                                        echo 'danger';
                                        break;
                                    default:
                                        echo 'secondary';
                                }
                                ?>"><?php echo htmlspecialchars($evt['status']); ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-outline-primary btn-sm" title="Ver detalhes"
                                        onclick="visualizarEvento(<?php echo $evt['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (!isset($_SESSION['user_tipo']) || $_SESSION['user_tipo'] !== 'usuario'): ?>
                                        <a href="<?php echo SITE_URL; ?>/evento/<?php echo $evt['id']; ?>/editar"
                                            class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                        <?php require_once __DIR__ . '/classes/CSRFProtection.php'; ?>
                                        <form method="POST" action="<?php echo SITE_URL; ?>/modulos/eventos/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este evento?');">
                                            <input type="hidden" name="id" value="<?php echo (int) $evt['id']; ?>">
                                            <?php echo CSRFProtection::getTokenField(); ?>
                                            <button type="submit" class="btn btn-danger btn-sm" title="Excluir">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-md-6">
        <?php require_once __DIR__ . '/includes/widget_eventos.php'; ?>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person-check-fill me-1"></i>
                Presença em Eventos
            </div>
            <div class="card-body">
                <table id="presencaTable" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Associado</th>
                            <th>Status</th>
                            <th>Data Evento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presencas as $pres): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pres['evento_nome']); ?></td>
                                <td><?php echo htmlspecialchars($pres['associado_nome'] . ' ' . $pres['associado_sobrenome']); ?>
                                </td>
                                <td><span class="badge bg-<?php
                                switch ($pres['status_presenca']) {
                                    case 'Confirmado':
                                        echo 'info';
                                        break;
                                    case 'Presente':
                                        echo 'success';
                                        break;
                                    case 'Ausente':
                                        echo 'danger';
                                        break;
                                    default:
                                        echo 'secondary';
                                }
                                ?>"><?php echo htmlspecialchars($pres['status_presenca']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($pres['data_evento'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
<script>
    $(document).ready(function () {
        $('#eventosTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
        $('#presencaTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
    });

    // Função para visualizar detalhes do evento
    function visualizarEvento(eventoId) {
        // Mostrar loading no modal
        const modal = new bootstrap.Modal(document.getElementById('modalEvento'));
        const modalBody = document.getElementById('modalEventoBody');
        const modalTitle = document.getElementById('modalEventoTitle');

        modalTitle.innerHTML = '<i class="bi bi-hourglass-split"></i> Carregando...';
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Carregando detalhes do evento...</p></div>';
        modal.show();

        // Fazer requisição AJAX
        fetch('<?php echo SITE_URL; ?>/evento/' + eventoId, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalTitle.innerHTML = data.titulo;
                    modalBody.innerHTML = data.html;
                } else {
                    modalTitle.innerHTML = 'Erro';
                    modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do evento: ' + (data.erro || 'Erro desconhecido') + '</div>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                modalTitle.innerHTML = 'Erro';
                modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do evento. Tente novamente.</div>';
            });
    }

    // Função para abrir relatório de associados em nova janela
    function abrirRelatorioAssociados() {
        const url = '<?php echo SITE_URL; ?>/relatorio-associados';
        const width = 1200;
        const height = 800;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;

        window.open(url, 'relatorio_associados',
            'width=' + width + ',height=' + height +
            ',left=' + left + ',top=' + top +
            ',scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no'
        );
    }
</script>

<!-- Modal para visualizar detalhes do evento -->
<div class="modal fade" id="modalEvento" tabindex="-1" aria-labelledby="modalEventoTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEventoTitle">
                    <i class="bi bi-calendar-event-fill text-primary me-2"></i>
                    Detalhes do Evento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalEventoBody">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
        </div>
    </div>
</div>