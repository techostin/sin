<!-- Sidebar -->
<div class="bg-dark border-right" id="sidebar-wrapper">
    <div class="sidebar-heading text-white"><b><?php echo SITE_TITLE; ?></b></div>
    <div class="list-group list-group-flush">
        <a href="<?php echo SITE_URL; ?>/" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-house-door-fill"></i> Início</a>
        
        <!-- Menu Associados (Apenas Gestores/Admin) -->
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="#associadosSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-people-fill"></i> Associados</a>
        <div class="collapse" id="associadosSubmenu">
            <a href="<?php echo SITE_URL; ?>/associados" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Listar</a>
            <a href="<?php echo SITE_URL; ?>/associado/novo" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Cadastrar</a>
        </div>
        <?php endif; ?>

        <!-- Menu Associações -->
        <a href="#associacoesSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-building-fill"></i> Associações</a>
        <div class="collapse" id="associacoesSubmenu">
            <a href="<?php echo SITE_URL; ?>/associacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Listar</a>
            <a href="<?php echo SITE_URL; ?>/associacao/novo" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Cadastrar</a>
        </div>

        <!-- Menu Produção Orgânica (Módulo Principal) - Logo abaixo de Associações -->
        <a href="#organicoSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-patch-check-fill text-success"></i> Produção Orgânica</a>
        <div class="collapse" id="organicoSubmenu">
            <a href="<?php echo SITE_URL; ?>/certificacoes" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-award me-1"></i>Certificações (SPG/OCS)</a>
            <a href="<?php echo SITE_URL; ?>/planos-manejo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-journal-text me-1"></i>Planos de Manejo</a>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-clipboard-check me-1"></i>Visitas e Inspeções</a>
            <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-exclamation-triangle me-1"></i>Não Conformidades</a>
            <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
                <a href="<?php echo SITE_URL; ?>/roteiros-inspecao" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-card-checklist me-1"></i>Roteiros (Templates)</a>
                <a href="<?php echo SITE_URL; ?>/plano-manejo/novo" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-plus-circle me-1"></i>Novo Plano de Manejo</a>
                <a href="<?php echo SITE_URL; ?>/insumos-aprovados" class="list-group-item list-group-item-action bg-secondary text-white ps-5"><i class="bi bi-shield-check me-1"></i>Insumos Aprovados</a>
            <?php endif; ?>
        </div>

        <!-- Menu Caderno de Campo -->
        <a href="#cadernoSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-book-fill"></i> Caderno de Campo</a>
        <div class="collapse" id="cadernoSubmenu">
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Dashboard</a>
            <a href="<?php echo SITE_URL; ?>/propriedades" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Propriedades</a>
            <a href="<?php echo SITE_URL; ?>/lavouras" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Lavouras</a>
            <a href="<?php echo SITE_URL; ?>/insumos" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Insumos</a>
            <a href="<?php echo SITE_URL; ?>/produtos" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Produtos</a>
            <a href="<?php echo SITE_URL; ?>/lotes" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Lotes de Produção</a>
            <a href="<?php echo SITE_URL; ?>/categorias-atividades" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Categorias de Atividades</a>
            <a href="<?php echo SITE_URL; ?>/atividades-diarias" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Atividades Diárias</a>
            <a href="<?php echo SITE_URL; ?>/relatorios" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Relatórios</a>
        </div>

        <!-- Menu Fornecedores -->
        <a href="#fornecedoresSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-truck"></i> Fornecedores</a>
        <div class="collapse" id="fornecedoresSubmenu">
            <a href="<?php echo SITE_URL; ?>/fornecedor" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Listar</a>
            <a href="<?php echo SITE_URL; ?>/fornecedor/novo" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Cadastrar</a>
        </div>

        <!-- Menu Contas (Apenas Gestores/Admin) -->
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="#contasSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-cash-stack"></i> Contas</a>
        <div class="collapse" id="contasSubmenu">
            <a href="<?php echo SITE_URL; ?>/contas" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Listar Contas</a>
            <a href="<?php echo SITE_URL; ?>/conta/novo" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Cadastrar Conta</a>
        </div>
        <?php endif; ?>

        <!-- Menu Eventos -->
        <a href="#eventosSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-calendar-event-fill"></i> Eventos</a>
        <div class="collapse" id="eventosSubmenu">
            <a href="<?php echo SITE_URL; ?>/eventos" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Listar Eventos</a>
            <a href="<?php echo SITE_URL; ?>/eventos/presencas" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Presenças</a>
            <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
                <a href="<?php echo SITE_URL; ?>/evento/novo" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Cadastrar Evento</a>
            <?php endif; ?>
        </div>
        
        <!-- Menu Usuários (Apenas Gestores/Admin) -->
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
        <a href="#usuariosSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action bg-dark text-white"><i class="bi bi-person-badge-fill"></i> Usuários</a>
        <div class="collapse" id="usuariosSubmenu">
            <a href="<?php echo SITE_URL; ?>/usuarios" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Listar Usuários</a>
            <a href="<?php echo SITE_URL; ?>/usuario/novo" class="list-group-item list-group-item-action bg-secondary text-white ps-5">Cadastrar Usuário</a>
        </div>
        <?php endif; ?>

        <a href="<?php echo SITE_URL; ?>/logout" class="list-group-item list-group-item-action bg-danger text-white"><i class="bi bi-box-arrow-right"></i> Sair</a>
    </div>
</div>
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
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/usuario/perfil">Perfil</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/usuario/configuracoes">Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">

