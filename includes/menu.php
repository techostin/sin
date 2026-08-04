<!-- Overlay para fechar sidebar no mobile -->
<div id="sidebar-overlay"></div>

<!-- Sidebar -->
<div class="bg-dark border-right" id="sidebar-wrapper">
    <div class="sidebar-heading text-white" style="display:flex; justify-content:center; align-items:stretch; padding:0; background:#fff; margin:0; border-bottom:1px solid rgba(0,0,0,0.1);">
        <img src="<?php echo SITE_URL; ?>/img/logo_Sinporg-2.png" alt="<?php echo SITE_TITLE; ?>" style="width:100%; height:auto; display:block; object-fit:cover; border-radius:0;">
    </div>
    <div class="list-group list-group-flush">
        <?php
$inicioUrl = SITE_URL . '/';
        if (isset($_SESSION['user_nivel']) && (int)$_SESSION['user_nivel'] === NIVEL_USUARIO) {
            $inicioUrl = SITE_URL . '/caderno-campo/dashboard-agricultor';
        }
        ?>
        <a href="<?php echo $inicioUrl; ?>" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-house-door-fill text-info me-1"></i> Início</a>
        
        <!-- Menu Associados (Apenas Gestores/Admin) -->
        <?php if ($user->hasPermission(NIVEL_GESTOR)):
            // Contar pendentes para badge no menu
            $menu_pendentes = 0;
            try {
                $menu_db   = Database::getInstance()->getConnection();
                $menu_stmt = $menu_db->query("SELECT COUNT(*) FROM associados WHERE status = 'pendente'");
                $menu_pendentes = (int)$menu_stmt->fetchColumn();
            } catch (Exception $e) {}
        ?>
        <a href="<?php echo SITE_URL; ?>/associados" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-people-fill text-primary me-1"></i> Associados
        </a>
        <a href="<?php echo SITE_URL; ?>/associados/pendentes"
           class="list-group-item list-group-item-action bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-fill-exclamation text-warning me-1"></i> Pendentes de Ativação</span>
            <?php if ($menu_pendentes > 0): ?>
            <span class="badge bg-warning text-dark rounded-pill"><?php echo $menu_pendentes; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Menu Associações -->
        <a href="<?php echo SITE_URL; ?>/associacoes" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-building-fill text-primary me-1"></i> Associações</a>

        <!-- Menu Produção Orgânica — Versão Agricultor (itens de consulta pessoal) -->
        <?php if (!$user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="#organicoSubmenuAgricultor" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-patch-check-fill text-success me-1"></i> Produção Orgânica</a>
        <div class="collapse" id="organicoSubmenuAgricultor">
            <a href="<?php echo SITE_URL; ?>/certificacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-award text-warning me-1"></i> Minhas Certificações</a>
            <a href="<?php echo SITE_URL; ?>/planos-manejo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-journal-text text-info me-1"></i> Meu Plano de Manejo</a>
        </div>
        <?php endif; ?>

        <!-- Bloco Fiscalização & Conformidade (Apenas Admin/Gestor) -->
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="#fiscalizacaoSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-shield-lock-fill text-warning me-1"></i> Fiscalização & Conformidade
        </a>
        <div class="collapse" id="fiscalizacaoSubmenu">
            <!-- Certificação & Autorização -->
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Certificação &amp; Autorização</div>
            <a href="<?php echo SITE_URL; ?>/certificacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-award text-warning me-1"></i> Certificações (SPG/OCS)</a>
            <a href="<?php echo SITE_URL; ?>/certificadoras_list.php" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-building me-1"></i> Certificadoras</a>
            <a href="<?php echo SITE_URL; ?>/planos-manejo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-journal-text text-info me-1"></i> Planos de Manejo — Análise</a>
            <a href="<?php echo SITE_URL; ?>/insumos-aprovados" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-shield-check text-success me-1"></i> Insumos Aprovados (Portaria 52)</a>

            <!-- Inspeção de Campo -->
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Inspeção de Campo</div>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-clipboard-check text-success me-1"></i> Visitas e Inspeções</a>
            <a href="<?php echo SITE_URL; ?>/visita-tecnica/agendar" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-calendar-plus text-info me-1"></i> Agendar Visita</a>
            <a href="<?php echo SITE_URL; ?>/roteiros-inspecao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-card-checklist text-primary me-1"></i> Roteiros (Templates)</a>
            <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle text-danger me-1"></i> Não Conformidades</a>

            <!-- Conformidade Operacional -->
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Conformidade Operacional</div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos/validacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-shield-check text-success me-1"></i> Validação de Insumos</a>

            <!-- Relatórios -->
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Relatórios</div>
            <a href="<?php echo SITE_URL; ?>/relatorios-admin" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-file-earmark-bar-graph text-info me-1"></i> Relatórios Administrativos</a>
        </div>
        <?php endif; ?>

        <!-- Menu Caderno de Campo -->
        <a href="#cadernoSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-book-fill text-success me-1"></i> Caderno de Campo</a>
        <div class="collapse" id="cadernoSubmenu">
            <!-- Visão Geral -->
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-speedometer2 text-info me-1"></i> Dashboard</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard-agricultor" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-person-workspace text-success me-1"></i> Painel do Agricultor</a>

            <!-- Registro Diário -->
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Registro Diário</div>
            <a href="<?php echo SITE_URL; ?>/propriedades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-house-door text-warning me-1"></i> Propriedades</a>
            <a href="<?php echo SITE_URL; ?>/lavouras" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-tree text-success me-1"></i> Lavouras</a>
            <a href="<?php echo SITE_URL; ?>/atividades-diarias" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-calendar-check text-primary me-1"></i> Atividades Diárias</a>
            <a href="#ocorrenciasSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Ocorrências</a>
            <div class="collapse" id="ocorrenciasSubmenu">
                <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencias" class="list-group-item list-group-item-action bg-dark text-white ps-5" style="padding-left:3.5rem!important">↳ Listar</a>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencia/novo" class="list-group-item list-group-item-action bg-dark text-white ps-5" style="padding-left:3.5rem!important">↳ Nova Ocorrência</a>
            </div>

            <!-- Insumos e Produção -->
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Insumos &amp; Produção</div>
            <a href="#insumosCadernoSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-droplet-half text-info me-1"></i> Insumos</a>
            <div class="collapse" id="insumosCadernoSubmenu">
                <a href="<?php echo SITE_URL; ?>/insumos" class="list-group-item list-group-item-action bg-dark text-white ps-5" style="padding-left:3.5rem!important">↳ Listar</a>
                <a href="<?php echo SITE_URL; ?>/insumo/novo" class="list-group-item list-group-item-action bg-dark text-white ps-5" style="padding-left:3.5rem!important">↳ Cadastrar</a>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos/validacao" class="list-group-item list-group-item-action bg-dark text-white ps-5" style="padding-left:3.5rem!important"><i class="bi bi-shield-check text-success me-1"></i> Validação Orgânica</a>
            </div>
            <a href="<?php echo SITE_URL; ?>/produtos" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-box-seam text-warning me-1"></i> Produtos</a>
            <a href="<?php echo SITE_URL; ?>/lotes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-layers text-info me-1"></i> Lotes de Produção</a>
            <a href="<?php echo SITE_URL; ?>/categorias-atividades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-tags text-primary me-1"></i> Categorias</a>

            <!-- Minha Conformidade (apenas Agricultor — Gestor usa o bloco Fiscalização) -->
            <?php if (!$user->hasPermission(NIVEL_GESTOR)): ?>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Minha Conformidade</div>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-clipboard-check text-success me-1"></i> Minhas Visitas</a>
            <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="list-group-item list-group-item-action bg-secondary text-white ps-5 position-relative">
                <i class="bi bi-exclamation-circle text-warning me-1"></i> Minhas NCs
                <?php
                try {
                    $nc_db = Database::getInstance()->getConnection();
                    $nc_assoc_id = $user->getAssociadoId();
                    if ($nc_assoc_id) {
                        $nc_stmt = $nc_db->prepare("SELECT COUNT(*) FROM nao_conformidades nc JOIN visitas_tecnicas vt ON nc.visita_tecnica_id = vt.id WHERE vt.associado_id = ? AND nc.status IN ('Aberta','Em Correção')");
                        $nc_stmt->execute([$nc_assoc_id]);
                        $nc_count = (int)$nc_stmt->fetchColumn();
                        if ($nc_count > 0) {
                            echo '<span class="badge bg-danger rounded-pill ms-1" style="font-size:0.6rem;">' . $nc_count . '</span>';
                        }
                    }
                } catch (Exception $e) { /* silently ignore */ }
                ?>
            </a>
            <a href="<?php echo SITE_URL; ?>/planos-manejo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-journal-text text-info me-1"></i> Meu Plano</a>
            <?php endif; ?>

            <!-- Ferramentas -->
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Ferramentas</div>
            <a href="<?php echo SITE_URL; ?>/rastreabilidade" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-search text-info me-1"></i> Rastreabilidade</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/sincronizacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5 position-relative"><i class="bi bi-arrow-repeat text-warning me-1"></i> Sincronização <span id="sync-badge-count" class="badge bg-danger rounded-pill ms-1" style="display:none; font-size:0.65rem;">0</span></a>
            <a href="<?php echo SITE_URL; ?>/relatorios" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-file-earmark-bar-graph text-info me-1"></i> Relatórios</a>

            <!-- Ações Rápidas -->
            <div class="p-2 mx-3 my-2 rounded" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);">
                <small class="text-white-50 d-block mb-2" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Ações Rápidas</small>
                <div class="d-grid gap-1">
                    <a href="<?php echo SITE_URL; ?>/atividade-diaria/novo" class="btn btn-sm btn-outline-light py-1" style="font-size:0.8rem;"><i class="bi bi-plus-circle me-1"></i> Nova Atividade</a>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencia/novo" class="btn btn-sm btn-outline-warning py-1" style="font-size:0.8rem;"><i class="bi bi-bug me-1"></i> Reportar Ocorrência</a>
                </div>
            </div>
        </div>

        <!-- Menu Fornecedores -->
        <a href="<?php echo SITE_URL; ?>/fornecedor" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-truck text-warning me-1"></i> Fornecedores</a>

        <!-- Menu Contas (Apenas Gestores/Admin) -->
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="<?php echo SITE_URL; ?>/contas" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-cash-stack text-success me-1"></i> Contas</a>
        <?php endif; ?>

        <!-- Menu Eventos -->
        <a href="<?php echo SITE_URL; ?>/eventos" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-calendar-event-fill text-primary me-1"></i> Eventos</a>
        
        <!-- Menu Usuários (Apenas Gestores/Admin) -->
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="<?php echo SITE_URL; ?>/usuarios" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-person-badge-fill text-info me-1"></i> Usuários</a>
        <?php endif; ?>

        <!-- Meu Perfil (todos os usuários) -->
        <a href="<?php echo SITE_URL; ?>/meu-perfil" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-person-circle text-info me-1"></i> Meu Perfil
        </a>

        <a href="<?php echo SITE_URL; ?>/logout" class="list-group-item list-group-item-action bg-danger text-white"><i class="bi bi-box-arrow-right text-white me-1"></i> Sair</a>
    </div>
</div>

<?php
// Guardar dados do bottom nav para renderizar no footer.php (fora do #wrapper)
$bnav_inicio_url  = $inicioUrl;
$bnav_is_gestor   = $user->hasPermission(NIVEL_GESTOR);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var indicator = document.createElement('div');
    indicator.id = 'sidebar-connection-indicator';
    indicator.style.cssText = 'position:sticky; bottom:0; left:0; right:0; padding:10px; border-radius:0; font-size:0.75rem; text-align:center; z-index:10; background: linear-gradient(to top, rgba(52,58,64,0.98) 0%, rgba(52,58,64,0.95) 100%); backdrop-filter: blur(4px); border-top:1px solid rgba(255,255,255,0.1);';
    var sidebar = document.querySelector('#sidebar-wrapper');
    if (sidebar) {
        var listGroup = sidebar.querySelector('.list-group');
        if (listGroup) {
            // Insert indicator after list group so it sticks to bottom
            listGroup.parentNode.insertBefore(indicator, listGroup.nextSibling);
        } else {
            sidebar.appendChild(indicator);
        }
        function updateIndicator() {
            if (navigator.onLine) {
                indicator.innerHTML = '<i class="bi bi-wifi"></i> Online';
                indicator.style.color = '#20c997';
            } else {
                indicator.innerHTML = '<i class="bi bi-wifi-off"></i> Offline (dados locais)';
                indicator.style.color = '#dc3545';
            }
        }
        updateIndicator();
        window.addEventListener('online', updateIndicator);
        window.addEventListener('offline', updateIndicator);
    }

    // ── Sidebar overlay: fechar ao clicar fora ──
    var overlay = document.getElementById('sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            document.getElementById('wrapper').classList.remove('toggled');
            overlay.classList.remove('active');
        });
    }

    // ── menu-toggle desktop: também mostra/oculta overlay no mobile ──
    var menuToggle = document.getElementById('menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            if (window.innerWidth < 992 && overlay) {
                overlay.classList.toggle('active');
            }
        });
    }

});
</script>
<!-- /#sidebar-wrapper -->

<!-- Page Content -->
<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <div class="container-fluid">
            <button class="btn btn-primary" id="menu-toggle"><i class="bi bi-list"></i></button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_nome']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/meu-perfil"><i class="bi bi-person-circle me-1"></i> Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/usuario/configuracoes"><i class="bi bi-gear me-1"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout"><i class="bi bi-box-arrow-right me-1"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">

