Sistema de permissões atual

config.php
Lines 20-22
define('NIVEL_ADMIN', 1);
define('NIVEL_GESTOR', 2);
define('NIVEL_USUARIO', 3);

User.php
Lines 58-67
    public function hasPermission($required_level) {
        if ($this->isLoggedIn() && isset($_SESSION['user_nivel'])) {
            // O nível 1 (Admin) tem acesso a tudo
            if ($_SESSION['user_nivel'] == NIVEL_ADMIN) {
                return true;
            }
            // Verifica se o nível do usuário é igual ou mais privilegiado que o necessário
            return $_SESSION['user_nivel'] <= $required_level;
        }
        return false;
    }
Padrão recomendado: $user->hasPermission(NIVEL_GESTOR) — cobre Admin (1) e Gestor (2).

Funcionalidades de fiscalização / análise (Admin + Gestor)
Área	Ação decisória	Rota	Backend hoje
Certificações
Emitir, editar, renovar, suspender
/certificacoes, /certificacao/novo
API protegida; UI parcial
Planos de Manejo
Aprovar/reprovar, alterar status
/planos-manejo, /plano-manejo/{id}/aprovar
aprovar.php OK; listar mostra botão a todos
Visitas / Inspeções
Agendar, realizar checklist, gerar laudo
/visitas-tecnicas, /visita-tecnica/agendar, /visita-tecnica/{id}/realizar
UI filtra por IS_ADMIN; realizar.php sem guard
Não Conformidades
Validar resolução, fechar NC
/nao-conformidades, /nao-conformidade/acompanhar
Status "Resolvida" só gestor
Roteiros (Templates)
Criar/editar checklists de inspeção
/roteiros-inspecao, /roteiro-inspecao/novo
cadastrar.php OK; listar.php aberto
Validação de Insumos
Conferir conformidade orgânica do estoque
/caderno-campo/insumos/validacao
Sem check de gestor
Insumos Aprovados
Catálogo oficial Portaria 52/2021
/insumos-aprovados
Sem check de gestor
Relatórios Administrativos
Visão global do sistema
/relatorios-admin
Protegido
Associados
Cadastro/gestão (base da fiscalização)
/associados
Menu já restrito
O que o agricultor (NIVEL_USUARIO) deve manter
Não é fiscalização — é consulta e resposta ao que lhe foi exigido:

Função	Rota	Motivo
Ver minhas certificações
/certificacoes
Somente leitura (já filtrado na API)
Enviar plano de manejo
/plano-manejo/novo
Submissão para análise
Ver meu plano
/planos-manejo
Acompanhar status
Minhas visitas
/visitas-tecnicas
Ver agendamentos/resultados
Minhas NCs
/nao-conformidades
Plano de ação + evidências
Painel do agricultor
/caderno-campo/dashboard-agricultor
Score de conformidade
Problemas atuais no menu
Duplicação: visitas, NC e planos aparecem em Produção Orgânica e em Caderno de Campo → Inspeção & Conformidade para todos.
Itens gestor expostos a todos: validação de insumos, insumos aprovados, roteiros (listar), certificações completas.
Inconsistência menu × backend: algumas telas bloqueiam gestor na API, mas o menu ainda mostra o link.
$user->isAdmin() é usado em relatórios, mas não existe em User.php (possível bug).
Proposta de menu: bloco único “Fiscalização & Conformidade”
Visível somente com $user->hasPermission(NIVEL_GESTOR):

<!-- Fiscalização & Conformidade (Admin/Gestor) -->
<?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
<a href="#fiscalizacaoSubmenu" data-bs-toggle="collapse" class="list-group-item ...">
    <i class="bi bi-shield-lock-fill"></i> Fiscalização & Conformidade
</a>
<div class="collapse" id="fiscalizacaoSubmenu">
    <!-- Certificação & Autorização -->
    <div class="list-group-item bg-dark text-white-50 ps-5 py-1" style="font-size:0.68rem; text-transform:uppercase;">Certificação & Autorização</div>
    <a href=".../certificacoes">Certificações (SPG/OCS)</a>
    <a href=".../planos-manejo">Planos de Manejo — Análise</a>
    <a href=".../insumos-aprovados">Insumos Aprovados (Portaria 52)</a>
    <!-- Inspeção de Campo -->
    <div class="list-group-item ...">Inspeção de Campo</div>
    <a href=".../visitas-tecnicas">Visitas e Inspeções</a>
    <a href=".../visita-tecnica/agendar">Agendar Visita</a>
    <a href=".../roteiros-inspecao">Roteiros (Templates)</a>
    <a href=".../nao-conformidades">Não Conformidades</a>
    <!-- Conformidade Operacional -->
    <div class="list-group-item ...">Conformidade Operacional</div>
    <a href=".../caderno-campo/insumos/validacao">Validação de Insumos</a>
    <!-- Relatórios -->
    <div class="list-group-item ...">Relatórios</div>
    <a href=".../relatorios-admin">Relatórios Administrativos</a>
</div>
<?php endif; ?>
Ajustes nos menus existentes
Menu atual	Mudança
Produção Orgânica
Remover ou esvaziar para gestor (itens vão para Fiscalização). Para agricultor: manter só “Minhas certificações” e “Meu plano de manejo”.
Caderno → Inspeção & Conformidade
Agricultor: renomear para “Minha Conformidade” (visitas/NC/plano filtrados). Gestor: remover bloco (já está em Fiscalização).
Associados, Usuários, Contas
Manter como estão (já restritos).
Guards de backend a reforçar (além do menu)
Arquivo	Guard sugerido
modulos/caderno_campo/insumos/validacao.php
hasPermission(NIVEL_GESTOR)
modulos/certificacao/insumos.php
hasPermission(NIVEL_GESTOR)
modulos/roteiros_inspecao/templates/listar.php
hasPermission(NIVEL_GESTOR)
modulos/roteiros_inspecao/visitas/realizar.php
hasPermission(NIVEL_GESTOR)
modulos/certificacao/cadastrar.php / editar.php
hasPermission(NIVEL_GESTOR)
modulos/plano_manejo/listar.php
Botão “Novo Plano” só agricultor; modal de status só gestor (já parcial)
Opcional em User.php:

public function isGestorOuAdmin(): bool {
    return $this->hasPermission(NIVEL_GESTOR);
}
Fluxo visual proposto
Admin / Gestor
Agricultor / Usuário
Fiscalização & Conformidade
Certificações
Planos - Análise/Aprovação
Visitas e Inspeções
Não Conformidades
Roteiros
Validação de Insumos
Relatórios Admin
Caderno de Campo
Minha Conformidade
Minhas Visitas
Minhas NCs
Meu Plano
Escopo de implementação (3 arquivos principais)
includes/menu.php — criar bloco Fiscalização; limpar duplicações; menus condicionais por perfil.
classes/User.php — helper isGestorOuAdmin() (opcional, padroniza checks).
Guards nas páginas listadas — alinhar menu com backend.
Estimativa: ~150–200 linhas, sem novas tabelas ou rotas.