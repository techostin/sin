<!-- Overlay para fechar sidebar no mobile -->
<div id="sidebar-overlay"></div>

<!-- Sidebar -->
<div class="bg-dark border-right" id="sidebar-wrapper">
    <div class="sidebar-heading text-white" style="display:flex; justify-content:center; align-items:stretch; padding:0; background:#fff; margin:0; border-bottom:1px solid rgba(0,0,0,0.1);">
        <img src="<?php echo SITE_URL; ?>/img/logo_Sinporg-2.png" alt="<?php echo SITE_TITLE; ?>" style="width:100%; height:auto; display:block; object-fit:cover; border-radius:0;">
    </div>
    <div class="list-group list-group-flush">
<?php
require_once __DIR__ . '/perfil.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
$inicioUrl = dashboard_destino();
$ehUsuarioAgroindustria = ($user->perfilDashboard() === 'agroindustria');
$areaProducaoAtual = $user->areaProducao();
$temAreaAnimal     = in_array('animal', $areaProducaoAtual, true);
$temAreaVegetal    = in_array('vegetal', $areaProducaoAtual, true);
$temAreaAgro       = in_array('agroindustria', $areaProducaoAtual, true);
$podeDiretoria = $user->podeAcessarDiretoria();
?>
        <a href="<?php echo $inicioUrl; ?>" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-house-door-fill text-info me-1"></i> Início</a>
        <a href="<?php echo SITE_URL; ?>/portal/area" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-person-circle text-success me-1"></i> Portal do Associado</a>

        <?php if ($podeDiretoria): ?>
        <a href="#diretoriaTop" data-bs-toggle="collapse" class="list-group-item list-group-item-action diretoria-menu-toggle fw-semibold" aria-expanded="false" style="background:linear-gradient(180deg,rgba(25,135,84,0.55) 0%,rgba(25,135,84,0.28) 100%); border-color:rgba(25,135,84,0.35); color:#fff;">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard da Diretoria
            <i class="bi bi-chevron-down float-end"></i>
        </a>
        <div class="collapse" id="diretoriaTop" style="background:linear-gradient(180deg,rgba(25,135,84,0.30) 0%,rgba(25,135,84,0.12) 100%);">
            <a href="<?php echo SITE_URL; ?>/dashboard/diretoria" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-speedometer2 text-success me-1"></i> Painel da Diretoria</a>
            <a href="<?php echo SITE_URL; ?>/associados" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-people-fill text-primary me-1"></i> Associados</a>
            <a href="<?php echo SITE_URL; ?>/cadastros-pendentes" class="list-group-item list-group-item-action bg-secondary text-white ps-5 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-fill-exclamation text-warning me-1"></i> Cadastros Pendentes</span>
                <?php
                $cadastros_pendentes_menu = 0;
                try {
                    $ficha_menu_db = Database::getInstance()->getConnection();
                    $cadastros_pendentes_menu = (int)$ficha_menu_db->query("SELECT COUNT(*) FROM ficha_adesao WHERE status = 'pendente'")->fetchColumn();
                } catch (Exception $e) {}
                if ($cadastros_pendentes_menu > 0) {
                    echo '<span class="badge bg-warning text-dark rounded-pill">' . $cadastros_pendentes_menu . '</span>';
                }
                ?>
            </a>
            <?php if ($user->hasPermission(NIVEL_ADMIN) || $user->isDiretoriaExecutiva()): ?>
            <a href="<?php echo SITE_URL; ?>/fichas-completas" class="list-group-item list-group-item-action bg-secondary text-white ps-5 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text text-info me-1"></i> Todas as Fichas</span>
                <?php
                $todas_fichas_menu = 0;
                try {
                    $ficha_menu_db = Database::getInstance()->getConnection();
                    $todas_fichas_menu = (int)$ficha_menu_db->query("SELECT COUNT(*) FROM ficha_adesao")->fetchColumn();
                } catch (Exception $e) {}
                if ($todas_fichas_menu > 0) {
                    echo '<span class="badge bg-info rounded-pill">' . $todas_fichas_menu . '</span>';
                }
                ?>
            </a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/associados/carteirinhas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-credit-card-2-front text-success me-1"></i> Carteirinhas</a>
            <a href="<?php echo SITE_URL; ?>/associacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-building-fill text-primary me-1"></i> Associações</a>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Financeiro da Associação</div>
            <a href="<?php echo SITE_URL; ?>/contas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-cash-stack text-success me-1"></i> Contas</a>
            <a href="<?php echo SITE_URL; ?>/mensalidades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-cash-coin text-warning me-1"></i> Mensalidades</a>
            <a href="<?php echo SITE_URL; ?>/caixa" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-bank text-info me-1"></i> Caixa</a>
            <a href="<?php echo SITE_URL; ?>/caixa/cobranca" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle text-danger me-1"></i> Cobrança</a>
            <a href="<?php echo SITE_URL; ?>/caixa/relatorio" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-graph-up-arrow text-info me-1"></i> Relatório Financeiro</a>
            <a href="<?php echo SITE_URL; ?>/eventos" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-calendar-event-fill text-primary me-1"></i> Eventos</a>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Gestão Territorial</div>
            <a href="<?php echo SITE_URL; ?>/regionalizacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-geo-alt-fill text-primary me-1"></i> Regionalização RS</a>
            <a href="<?php echo SITE_URL; ?>/regionalizacao/mapa" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-map-fill text-info me-1"></i> Mapa de Calor</a>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Relatórios Diretoria</div>
            <a href="<?php echo SITE_URL; ?>/relatorios_diretoria/ativos_municipio" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-geo-alt text-primary me-1"></i> Associados Ativos por Município</a>
            <a href="<?php echo SITE_URL; ?>/relatorios_diretoria/inadimplentes_micro" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle text-danger me-1"></i> Inadimplentes por Microrregião</a>
            <a href="<?php echo SITE_URL; ?>/relatorios_diretoria/entradas_saidas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-cash-stack text-success me-1"></i> Fluxo de Caixa (Entradas x Saídas)</a>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Comunicação</div>
            <a href="<?php echo SITE_URL; ?>/comunicacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-send me-1"></i> Comunicação por Cluster</a>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Assembleia</div>
            <a href="<?php echo SITE_URL; ?>/assembleia" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-people-fill me-1"></i> Assembleias e Votações</a>
        </div>
        <?php else: ?>
        <a href="#associacaoTop" data-bs-toggle="collapse" class="list-group-item list-group-item-action assoc-menu-toggle fw-semibold" aria-expanded="false">
            <i class="bi bi-people-fill me-1"></i> Associação
            <i class="bi bi-chevron-down float-end"></i>
        </a>
        <div class="collapse" id="associacaoTop">
            <a href="<?php echo SITE_URL; ?>/associacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-building-fill text-primary me-1"></i> Associações</a>
            <a href="<?php echo SITE_URL; ?>/eventos" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-calendar-event-fill text-primary me-1"></i> Eventos</a>
        </div>
        <?php endif; ?>

        <!-- Guia do Produtor (todos os usuários) -->
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-bs-toggle="modal" data-bs-target="#modalGuiaProdutorMenu">
            <i class="bi bi-journal-bookmark-fill text-warning me-1"></i> Guia do Produtor
        </a>

        <!-- Acessos a Relatórios (todos os usuários — modal com escopo por perfil) -->
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white" data-bs-toggle="modal" data-bs-target="#modalAcessosRelatorios">
            <i class="bi bi-folder2-open text-info me-1"></i> Acessos a Relatórios
        </a>

        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="#regionalizacaoTop" data-bs-toggle="collapse" class="list-group-item list-group-item-action regionalizacao-menu-toggle fw-semibold" aria-expanded="false" style="background:linear-gradient(180deg,rgba(13,110,253,0.55) 0%,rgba(13,110,253,0.28) 100%); border-color:rgba(13,110,253,0.35); color:#fff;">
            <i class="bi bi-geo-alt-fill me-1"></i> Regionalização RS
            <i class="bi bi-chevron-down float-end"></i>
        </a>
        <div class="collapse" id="regionalizacaoTop" style="background:linear-gradient(180deg,rgba(13,110,253,0.30) 0%,rgba(13,110,253,0.12) 100%);">
            <a href="<?php echo SITE_URL; ?>/regionalizacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-diagram-3 text-success me-1"></i> Dashboard Regional</a>
            <a href="<?php echo SITE_URL; ?>/regionalizacao/mapa" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-map-fill text-info me-1"></i> Mapa de Calor</a>
            <?php // A página /admin/clusters exige NIVEL_ADMIN: o menu não deve oferecê-la a gestores.
            if ($user->hasPermission(NIVEL_ADMIN)): ?>
            <a href="<?php echo SITE_URL; ?>/admin/clusters" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-people text-warning me-1"></i> Clusters de Comunicação</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($temAreaAgro): ?>
        <a href="#agroindustriaTop" data-bs-toggle="collapse" class="list-group-item list-group-item-action agro-menu-toggle bg-success text-white fw-semibold" aria-expanded="false">
            <i class="bi bi-buildings me-1"></i> Agroindústria
            <i class="bi bi-chevron-down float-end"></i>
        </a>
        <div class="collapse" id="agroindustriaTop">
            <a href="<?php echo SITE_URL; ?>/dashboard/agroindustria" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-speedometer2 text-success me-1"></i> Painel de Agroindústria</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/minhas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-buildings text-info me-1"></i> Minhas Agroindústrias</a>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Registro de Produção</div>
            <a href="<?php echo SITE_URL; ?>/agroindustria/setores" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-diagram-3 text-success me-1"></i> Setores</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/categorias" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-tags text-primary me-1"></i> Categorias</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/atividades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-calendar-check text-primary me-1"></i> Atividades Diárias</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/ocorrencias" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Ocorrências</a>
            <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em;">Processamento</div>
            <a href="<?php echo SITE_URL; ?>/agroindustria/receitas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-journal-richtext text-success me-1"></i> Receitas</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/higienizacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-droplet text-info me-1"></i> Higienização</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/ordens" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-clipboard-check text-success me-1"></i> Ordens de Produção</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/rastreabilidade" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-search text-info me-1"></i> Rastreabilidade</a>
        </div>
        <?php endif; ?>

        <?php if ($temAreaAnimal): ?>
        <a href="#producaoAnimalTop" data-bs-toggle="collapse" class="list-group-item list-group-item-action animal-menu-toggle fw-semibold" aria-expanded="false" style="background:linear-gradient(180deg,rgba(108,117,125,0.55) 0%,rgba(108,117,125,0.28) 100%); border-color:rgba(108,117,125,0.35); color:#fff;">
            <i class="bi bi-egg-fill me-1"></i> Produção Animal
            <i class="bi bi-chevron-down float-end"></i>
        </a>
        <div class="collapse" id="producaoAnimalTop" style="background:linear-gradient(180deg,rgba(108,117,125,0.30) 0%,rgba(108,117,125,0.12) 100%);">
            <a href="<?php echo SITE_URL; ?>/producao-animal" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-speedometer2 text-success me-1"></i> Painel</a>
            <a href="<?php echo SITE_URL; ?>/manejo-animal" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-collection text-info me-1"></i> Rebanho (Animais)</a>
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(108,117,125,0.45); color:rgba(255,255,255,0.75);">Lotes</div>
            <a href="<?php echo SITE_URL; ?>/producao-animal/lotes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-boxes text-info me-1"></i> Lotes</a>
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(108,117,125,0.45); color:rgba(255,255,255,0.75);">Alimentação</div>
            <a href="<?php echo SITE_URL; ?>/producao-animal/alimentacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-basket text-info me-1"></i> Alimentação</a>
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(108,117,125,0.45); color:rgba(255,255,255,0.75);">Sanidade</div>
            <a href="<?php echo SITE_URL; ?>/producao-animal/sanidade" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-heart-pulse text-info me-1"></i> Sanidade</a>
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(108,117,125,0.45); color:rgba(255,255,255,0.75);">Reprodução</div>
            <a href="<?php echo SITE_URL; ?>/producao-animal/reproducao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-stars text-info me-1"></i> Reprodução</a>
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(108,117,125,0.45); color:rgba(255,255,255,0.75);">Ambientes</div>
            <a href="<?php echo SITE_URL; ?>/producao-animal/ambientes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-house-heart text-info me-1"></i> Ambientes</a>
        </div>
        <?php endif; ?>

        <?php if ($temAreaVegetal): ?>
        <a href="#producaoVegetalTop" data-bs-toggle="collapse" class="list-group-item list-group-item-action vegetal-menu-toggle fw-semibold" aria-expanded="false" style="background:linear-gradient(180deg,rgba(47,158,68,0.6) 0%,rgba(43,138,62,0.3) 100%); border-color:rgba(47,158,68,0.4); color:#fff;">
            <i class="bi bi-tree-fill me-1"></i> Produção Vegetal
            <i class="bi bi-chevron-down float-end"></i>
        </a>
        <div class="collapse" id="producaoVegetalTop" style="background:linear-gradient(180deg,rgba(47,158,68,0.32) 0%,rgba(43,138,62,0.12) 100%);">
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(47,158,68,0.45); color:rgba(255,255,255,0.8);">Cadastro &amp; Insumos</div>
            <a href="<?php echo SITE_URL; ?>/propriedades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-house-door text-warning me-1"></i> Propriedades</a>
            <a href="<?php echo SITE_URL; ?>/lavouras" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-tree text-success me-1"></i> Lavouras</a>
            <a href="<?php echo SITE_URL; ?>/insumos" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-droplet-half text-info me-1"></i> Insumos</a>
            <a href="<?php echo SITE_URL; ?>/produtos" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-box-seam text-warning me-1"></i> Produtos</a>
            <a href="<?php echo SITE_URL; ?>/lotes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-layers text-info me-1"></i> Lotes de Produção</a>
            <a href="<?php echo SITE_URL; ?>/fornecedor" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-truck text-warning me-1"></i> Fornecedores</a>
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(47,158,68,0.45); color:rgba(255,255,255,0.8);">Registro Diário</div>
            <a href="<?php echo SITE_URL; ?>/atividades-diarias" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-calendar-check text-primary me-1"></i> Atividades Diárias</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencias" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Ocorrências</a>
            <div class="list-group-item ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; background:rgba(47,158,68,0.45); color:rgba(255,255,255,0.8);">Ferramentas</div>
            <a href="<?php echo SITE_URL; ?>/rastreabilidade" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-search text-info me-1"></i> Rastreabilidade</a>
            <a href="<?php echo SITE_URL; ?>/relatorios" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-file-earmark-bar-graph text-info me-1"></i> Relatórios</a>
        </div>
        <?php endif; ?>

        <?php if (!$user->hasPermission(NIVEL_GESTOR) && $temAreaVegetal): ?>
        <a href="#organicoSubmenuAgricultor" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-patch-check-fill text-success me-1"></i> Produção Orgânica <i class="bi bi-chevron-down float-end"></i></a>
        <div class="collapse" id="organicoSubmenuAgricultor">
            <a href="<?php echo SITE_URL; ?>/certificacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-award text-warning me-1"></i> Certificações</a>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-clipboard-check text-success me-1"></i> Minhas Visitas</a>
            <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-circle text-warning me-1"></i> Minhas NCs</a>
            <a href="<?php echo SITE_URL; ?>/planos-manejo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-journal-text text-info me-1"></i> Meu Plano</a>
            <a href="<?php echo SITE_URL; ?>/portal/area" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-person-circle me-1"></i> Portal do Associado</a>
        </div>
        <?php endif; ?>

        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="#fiscalizacaoSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-shield-lock-fill text-warning me-1"></i> Fiscalização <i class="bi bi-chevron-down float-end"></i>
        </a>
        <div class="collapse" id="fiscalizacaoSubmenu">
            <a href="<?php echo SITE_URL; ?>/certificacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-award text-warning me-1"></i> Certificações (SPG/OCS)</a>
            <a href="<?php echo SITE_URL; ?>/certificadoras_list.php" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-building me-1"></i> Certificadoras</a>
            <a href="<?php echo SITE_URL; ?>/planos-manejo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-journal-text text-info me-1"></i> Planos de Manejo — Análise</a>
            <a href="<?php echo SITE_URL; ?>/insumos-aprovados" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-shield-check text-success me-1"></i> Insumos Aprovados</a>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-clipboard-check text-success me-1"></i> Visitas e Inspeções</a>
            <a href="<?php echo SITE_URL; ?>/visita-tecnica/agendar" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-calendar-plus text-info me-1"></i> Agendar Visita</a>
            <a href="<?php echo SITE_URL; ?>/roteiros-inspecao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-card-checklist text-primary me-1"></i> Roteiros (Templates)</a>
            <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle text-danger me-1"></i> Não Conformidades</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos/validacao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-shield-check text-success me-1"></i> Validação de Insumos</a>
            <a href="<?php echo SITE_URL; ?>/relatorios-admin" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-file-earmark-bar-graph text-info me-1"></i> Relatórios Administrativos</a>
            <a href="<?php echo SITE_URL; ?>/relatorios-conformidade" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-shield-check text-success me-1"></i> Relatórios de Conformidade</a>
            <a href="<?php echo SITE_URL; ?>/consulta-cnpo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-patch-check text-success me-1"></i> Consulta CNPO</a>
        </div>
        <?php endif; ?>

        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="<?php echo SITE_URL; ?>/usuarios" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-person-badge-fill text-info me-1"></i> Usuários</a>
        <?php endif; ?>

        <?php if ($user->hasPermission(NIVEL_ADMIN)):
            $menu_pendentes = 0;
            $menu_todas_fichas = 0;
            try {
                $menu_db = Database::getInstance()->getConnection();
                $menu_pendentes = (int)$menu_db->query("SELECT (SELECT COUNT(*) FROM associados WHERE status = 'pendente') + (SELECT COUNT(*) FROM usuarios WHERE status = 'pendente')")->fetchColumn();
                $menu_todas_fichas = (int)$menu_db->query("SELECT COUNT(*) FROM ficha_adesao")->fetchColumn();
            } catch (Exception $e) {}
        ?>
        <a href="<?php echo SITE_URL; ?>/associados/pendentes" class="list-group-item list-group-item-action bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-fill-exclamation text-warning me-1"></i> Pendentes de Ativação</span>
            <?php if ($menu_pendentes > 0): ?>
            <span class="badge bg-warning text-dark rounded-pill"><?php echo $menu_pendentes; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo SITE_URL; ?>/fichas-completas" class="list-group-item list-group-item-action bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-earmark-text text-info me-1"></i> Todas as Fichas de Adesão</span>
            <?php if ($menu_todas_fichas > 0): ?>
            <span class="badge bg-info rounded-pill"><?php echo $menu_todas_fichas; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <a href="<?php echo SITE_URL; ?>/meu-perfil" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="bi bi-person-circle text-info me-1"></i> Meu Perfil
        </a>

        <a href="<?php echo SITE_URL; ?>/logout" class="list-group-item list-group-item-action bg-danger text-white"><i class="bi bi-box-arrow-right text-white me-1"></i> Sair</a>
    </div>
</div>

<!-- Modal: Guia do Produtor Orgânico (PDF em /doc) -->
<div class="modal fade" id="modalGuiaProdutorMenu" tabindex="-1" aria-labelledby="modalGuiaProdutorMenuLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalGuiaProdutorMenuLabel">Guia do Produtor Orgânico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <iframe src="<?php echo SITE_URL; ?>/doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf" class="w-100" style="height:80vh;border:0;" title="Guia do Produtor Orgânico"></iframe>
            </div>
            <div class="modal-footer">
                <a class="btn btn-success btn-sm" href="<?php echo SITE_URL; ?>/doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i> Abrir em nova aba</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Acessos a Relatórios (escopo por perfil) -->
<?php
$acessosRelatorios = [];

$cadernoRelatorios = [
    'atividades'   => ['nome' => 'Atividades Diárias',       'desc' => 'Registros diários de campo e serviços.'],
    'insumos'      => ['nome' => 'Insumos',                  'desc' => 'Controle de insumos, fornecedores e estoque.'],
    'producao'     => ['nome' => 'Produção',                 'desc' => 'Produtos, colheitas e quantidades produzidas.'],
    'lotes'        => ['nome' => 'Lotes de Produção',        'desc' => 'Lotes com código de rastreabilidade.'],
    'ocorrencias'  => ['nome' => 'Ocorrências',              'desc' => 'Ocorrências de manejo e não conformidades.'],
];
$cadernoPdfSomente = [
    'propriedade'  => ['nome' => 'Minha Propriedade',        'desc' => 'Propriedade, lavouras e últimas atividades.'],
    'geral'        => ['nome' => 'Relatório Geral',          'desc' => 'Visão consolidada do caderno de campo.'],
];

$conformidadeComum = [
    'balanco-massas'       => ['nome' => 'Balanço de Massas',            'desc' => 'Entrada x saída por ordem de produção.'],
    'rastreabilidade'      => ['nome' => 'Rastreabilidade Consolidada',  'desc' => 'Cadeia do lote final até a origem.'],
    'validacao-insumos'    => ['nome' => 'Validação de Insumos',         'desc' => 'Status orgânico frente à Portaria 52/2021.'],
    'historico-conversao'  => ['nome' => 'Histórico de Conversão',       'desc' => 'Conversão das propriedades para orgânico.'],
    'manejo-animal'        => ['nome' => 'Manejo Animal',                'desc' => 'Rebanho, origem e registros de manejo.'],
    'producao-animal'      => ['nome' => 'Produção Animal',              'desc' => 'Lotes, alimentação, sanidade e reprodução (Port. 52/2021).'],
    'agroindustria'        => ['nome' => 'Agroindústria Consolidado',    'desc' => 'Receitas, ordens e selos autorizados.'],
    'higienizacao'         => ['nome' => 'Livro de Higienização',        'desc' => 'Higienização de equipamentos e linhas.'],
];
$conformidadeGestor = ['trilha-auditoria' => ['nome' => 'Trilha de Auditoria', 'desc' => 'Logs de ações e acessos do sistema.']];

$adminRelatorios = [
    'usuarios'     => ['nome' => 'Usuários',       'desc' => 'Cadastro e permissões de usuários.'],
    'associados'   => ['nome' => 'Associados',     'desc' => 'Cadastro completo de associados.'],
    'associacoes'  => ['nome' => 'Associações',    'desc' => 'Associações vinculadas.'],
    'fornecedores' => ['nome' => 'Fornecedores',   'desc' => 'Fornecedores cadastrados.'],
    'contas'       => ['nome' => 'Contas',         'desc' => 'Contas a pagar/receber.'],
];
$acessosRelatorios['gestor'] = $user->hasPermission(NIVEL_GESTOR);

if (!function_exists('renderRelatorioAcao')) {
    function renderRelatorioAcao($url, $formato, $icone, $cor, $titulo) {
        return '<a href="' . $url . '" target="_blank" class="btn btn-sm ' . $cor . ' me-1 mb-1" title="' . $titulo . '"><i class="bi ' . $icone . '"></i> ' . $formato . '</a>';
    }
}
?>

<div class="modal fade" id="modalAcessosRelatorios" tabindex="-1" aria-labelledby="modalAcessosRelatoriosLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAcessosRelatoriosLabel"><i class="bi bi-folder2-open text-info me-2"></i>Acessos a Relatórios do Sistema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <?php if (!$acessosRelatorios['gestor']): ?>
                <div class="alert alert-info py-2 small"><i class="bi bi-person-lock me-1"></i> Você está vendo apenas os relatórios <strong>vinculados à sua propriedade e às suas atividades</strong>.</div>
                <?php else: ?>
                <div class="alert alert-success py-2 small"><i class="bi bi-shield-check me-1"></i> Perfil Gestor/Admin — acesso a todos os relatórios de fiscalização e geração de documentos.</div>
                <?php endif; ?>

                <h6 class="text-uppercase text-muted mt-1" style="font-size:0.75rem; letter-spacing:0.06em;"><i class="bi bi-journal-text text-success me-1"></i> Meus Registros — Caderno de Campo</h6>
                <div class="list-group mb-3">
                    <?php foreach (array_merge($cadernoRelatorios, $cadernoPdfSomente) as $slug => $rep): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                        <div class="me-2">
                            <strong><?php echo htmlspecialchars($rep['nome']); ?></strong>
                            <span class="text-muted small d-block"><?php echo htmlspecialchars($rep['desc']); ?></span>
                        </div>
                        <div>
                            <?php
                            $base = SITE_URL . '/caderno-campo/relatorios/exportar?relatorio=' . $slug;
                            echo renderRelatorioAcao($base . '&tipo=pdf', 'PDF', 'bi-file-earmark-pdf-fill', 'btn-outline-danger', 'Gerar PDF');
                            if (!isset($cadernoPdfSomente[$slug])) {
                                echo renderRelatorioAcao($base . '&tipo=excel', 'Excel', 'bi-file-earmark-excel-fill', 'btn-outline-success', 'Gerar Excel');
                            }
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <h6 class="text-uppercase text-muted mt-3" style="font-size:0.75rem; letter-spacing:0.06em;"><i class="bi bi-shield-check text-success me-1"></i> Minha Conformidade Orgânica</h6>
                <div class="list-group mb-3">
                    <?php foreach ($conformidadeComum as $slug => $rep): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                        <div class="me-2">
                            <strong><?php echo htmlspecialchars($rep['nome']); ?></strong>
                            <span class="text-muted small d-block"><?php echo htmlspecialchars($rep['desc']); ?></span>
                        </div>
                        <div>
                            <?php
                            $base = SITE_URL . '/relatorios-conformidade/gerar?relatorio=' . $slug;
                            echo renderRelatorioAcao($base . '&formato=pdf', 'PDF', 'bi-file-earmark-pdf-fill', 'btn-outline-danger', 'Gerar PDF');
                            echo renderRelatorioAcao($base . '&formato=excel', 'Excel', 'bi-file-earmark-excel-fill', 'btn-outline-success', 'Gerar Excel');
                            echo renderRelatorioAcao($base . '&formato=html', 'Abrir', 'bi-box-arrow-up-right', 'btn-outline-primary', 'Abrir no navegador');
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($acessosRelatorios['gestor']): foreach ($conformidadeGestor as $slug => $rep): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                        <div class="me-2">
                            <strong><?php echo htmlspecialchars($rep['nome']); ?></strong>
                            <span class="text-muted small d-block"><?php echo htmlspecialchars($rep['desc']); ?></span>
                        </div>
                        <div>
                            <?php
                            $base = SITE_URL . '/relatorios-conformidade/gerar?relatorio=' . $slug;
                            echo renderRelatorioAcao($base . '&formato=pdf', 'PDF', 'bi-file-earmark-pdf-fill', 'btn-outline-danger', 'Gerar PDF');
                            echo renderRelatorioAcao($base . '&formato=excel', 'Excel', 'bi-file-earmark-excel-fill', 'btn-outline-success', 'Gerar Excel');
                            ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <?php if ($acessosRelatorios['gestor']): ?>
                <h6 class="text-uppercase text-muted mt-3" style="font-size:0.75rem; letter-spacing:0.06em;"><i class="bi bi-briefcase text-primary me-1"></i> Relatórios Administrativos</h6>
                <div class="list-group mb-3">
                    <?php foreach ($adminRelatorios as $slug => $rep): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                        <div class="me-2">
                            <strong><?php echo htmlspecialchars($rep['nome']); ?></strong>
                            <span class="text-muted small d-block"><?php echo htmlspecialchars($rep['desc']); ?></span>
                        </div>
                        <div>
                            <?php
                            $base = SITE_URL . '/relatorios-admin?relatorio=' . $slug;
                            echo renderRelatorioAcao($base . '&formato=pdf', 'PDF', 'bi-file-earmark-pdf-fill', 'btn-outline-danger', 'Gerar PDF');
                            echo renderRelatorioAcao($base . '&formato=excel', 'Excel', 'bi-file-earmark-excel-fill', 'btn-outline-success', 'Gerar Excel');
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
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

    var overlay = document.getElementById('sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            document.getElementById('wrapper').classList.remove('toggled');
            overlay.classList.remove('active');
        });
    }

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
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user_nome'] ?? $_SESSION['associado_nome'] ?? 'Visitante', ENT_QUOTES); ?>
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
<script>
(function () {
    const rules = [
        ['associados', ['/associados', '/associado/', '/associacoes', '/associacao/']],
        ['fiscalizacao', ['/fiscalizacao', '/roteiros_inspecao', '/conformidade']],
        ['caderno', ['/caderno_campo', '/lotes_producao', '/propriedades', '/lavouras']],
        ['financeiro', ['/financeiro', '/mensalidades', '/caixa', '/contas']],
        ['governanca', ['/governanca', '/assembleias', '/assembleia', '/eventos']],
        ['relatorios', ['/relatorios', '/relatorio']],
        ['agroindustria', ['/agroindustria']],
        ['configuracoes', ['/usuarios', '/configuracoes']]
    ];

    fetch(<?php echo json_encode(SITE_URL . '/api/v1/me/capabilities'); ?>, { credentials: 'same-origin' })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (payload) {
            const data = payload && payload.data ? payload.data : null;
            if (!data || !Array.isArray(data.navigation)) { return; }
            const allowed = new Set(data.navigation.map(function (item) { return item.key; }));
            document.querySelectorAll('#sidebar-wrapper a[href]').forEach(function (link) {
                const href = link.getAttribute('href') || '';
                rules.forEach(function (rule) {
                    const key = rule[0];
                    const matches = rule[1].some(function (part) { return href.indexOf(part) !== -1; });
                    if (matches && !allowed.has(key)) {
                        link.style.display = 'none';
                    }
                });
            });
        })
        .catch(function () { /* menu permanece intacto se a API falhar */ });
})();
</script>
