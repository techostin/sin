<?php
// ============================================================
// MANUAL DO USUÁRIO — SINPORG
// Sistema Integrado da Produção Orgânica
// Gera PDF completo com capa, sumário, seções detalhadas e
// screenshots reais das páginas do sistema.
// ============================================================
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

$IMAGES_DIR  = __DIR__ . '/../img/manual/';
$LOGO_PATH   = __DIR__ . '/../img/logo_Sinporg-2.png';
$OUTPUT_FILE = __DIR__ . '/../img/manual_do_usuario.pdf';

if (!is_dir($IMAGES_DIR)) {
    die("ERRO: Diretório de imagens não encontrado: $IMAGES_DIR\n");
}

echo "== Gerando Manual do Usuário SINPORG ==\n";
echo "Diretório de imagens: $IMAGES_DIR\n";

// ============================================================
// MPDF
// ============================================================
$mpdf = new Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_left'   => 18,
    'margin_right'  => 18,
    'margin_top'    => 30,
    'margin_bottom' => 25,
    'margin_header' => 10,
    'margin_footer' => 10
]);

$mpdf->SetHeader('SINPORG — Manual do Usuário|{PAGENO}/{nbpg}');
$mpdf->SetFooter('© ' . date('Y') . ' Edmildon L. Santos — SINPORG');

// ============================================================
// CSS
// ============================================================
$css = '
body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; line-height: 1.5; }
h1 { font-size: 17pt; color: #0f5132; border-bottom: 3px solid #198754; padding-bottom: 8px; }
h2 { font-size: 13pt; color: #0f5132; border-bottom: 2px solid #20c997; padding-bottom: 5px; margin-top: 20px; }
h3 { font-size: 11pt; color: #198754; margin-top: 15px; }
p  { margin: 6px 0; text-align: justify; }
ul, ol { margin: 6px 0 6px 20px; }
li { margin: 3px 0; }
.capa { text-align: center; padding-top: 100px; }
.screenshot { border: 1px solid #ccc; border-radius: 8px; padding: 5px; background: #f9f9f9; margin: 10px 0; }
.screenshot img { width: 100%; }
.screenshot-caption { font-size: 9pt; color: #666; text-align: center; margin-top: 4px; font-style: italic; }
.toc { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 12px; }
.toc ul { list-style: none; margin: 0; padding: 0; }
.toc li { padding: 2px 0; }
.toc .num { color: #198754; font-weight: bold; margin-right: 5px; }
.info-box { background: #ecfdf5; border-left: 4px solid #198754; padding: 10px 12px; border-radius: 6px; margin: 10px 0; }
.warning-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 10px 12px; border-radius: 6px; margin: 10px 0; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th { background: #0f5132; color: white; padding: 8px; text-align: left; }
td { border: 1px solid #dee2e6; padding: 7px; }
tr:nth-child(even) { background: #f8f9fa; }
.sequence { background: #f0f9ff; border: 1px solid #b3d7f2; border-radius: 8px; padding: 12px; margin: 10px 0; }
.sequence ol { margin-left: 18px; }
.sequence li { margin: 5px 0; }
';

$mpdf->WriteHTML("<style>$css</style>");

// ============================================================
// Função auxiliar: seção com título, conteúdo e screenshot
// ============================================================
function addSection($mpdf, $imagesDir, $title, $content, $screenshot, $caption = null) {
    $mpdf->AddPage();
    $mpdf->WriteHTML("<h1>$title</h1>");
    $mpdf->WriteHTML($content);
    if ($screenshot && file_exists($imagesDir . $screenshot)) {
        $mpdf->WriteHTML('<div class="screenshot"><img src="' . $imagesDir . $screenshot . '" style="width:100%;">');
        if ($caption) {
            $mpdf->WriteHTML('<div class="screenshot-caption">' . $caption . '</div>');
        }
        $mpdf->WriteHTML('</div>');
    }
}

// ============================================================
// CAPA
// ============================================================
$mpdf->AddPage();
$capaHtml  = '<div class="capa">';
$capaHtml .= '<div style="text-align:center; margin-bottom:30px;">';
if (file_exists($LOGO_PATH)) {
    $capaHtml .= '<img src="' . $LOGO_PATH . '" style="width:150px;">';
}
$capaHtml .= '</div>';
$capaHtml .= '<h1 style="font-size:28pt; border:none; color:#0f5132;">MANUAL DO USUÁRIO</h1>';
$capaHtml .= '<h2 style="font-size:16pt; border:none; color:#198754; font-weight:normal;">SINPORG - Sistema Integrado da Produção Orgânica</h2>';
$capaHtml .= '<br><br><p style="color:#666; text-align:center;">Guia completo de utilização do sistema</p>';
$capaHtml .= '<p style="color:#666; text-align:center;">Detalhamento de funcionalidades e sequência lógica de utilização</p>';
$capaHtml .= '<p style="color:#666; text-align:center;">Versão 1.0 — ' . date('d/m/Y') . '</p>';
$capaHtml .= '<br><br><br><br><br><br><br>';
$capaHtml .= '<p style="font-size:9pt; color:#999; text-align:center;">© ' . date('Y') . ' Edmildon L. Santos. Todos os direitos reservados.</p>';
$capaHtml .= '</div>';
$mpdf->WriteHTML($capaHtml);

// ============================================================
// SUMÁRIO
// ============================================================
$mpdf->AddPage();
$mpdf->WriteHTML('
<h1>Sumário</h1>
<div class="toc">
<ul>
    <li><span class="num">1.</span> Introdução ao Sistema</li>
    <li><span class="num">2.</span> Requisitos e Acesso</li>
    <li><span class="num">3.</span> Primeiro Acesso (Pré-cadastro)</li>
    <li><span class="num">4.</span> Login no Sistema</li>
    <li><span class="num">5.</span> Dashboard Principal</li>
    <li><span class="num">6.</span> Caderno de Campo</li>
    <ul>
        <li><span class="num">6.1.</span> Propriedades</li>
        <li><span class="num">6.2.</span> Cadastro de Propriedade</li>
        <li><span class="num">6.3.</span> Lavouras</li>
        <li><span class="num">6.4.</span> Insumos</li>
        <li><span class="num">6.5.</span> Produtos</li>
        <li><span class="num">6.6.</span> Lotes de Produção</li>
        <li><span class="num">6.7.</span> Atividades Diárias</li>
        <li><span class="num">6.8.</span> Ocorrências</li>
    </ul>
    <li><span class="num">7.</span> Gestão de Associados</li>
    <li><span class="num">8.</span> Associações</li>
    <li><span class="num">9.</span> Certificações</li>
    <li><span class="num">10.</span> Planos de Manejo</li>
    <li><span class="num">11.</span> Relatórios</li>
    <li><span class="num">12.</span> Eventos</li>
    <li><span class="num">13.</span> Usuários</li>
    <li><span class="num">14.</span> Contas</li>
    <li><span class="num">15.</span> Fornecedor</li>
    <li><span class="num">16.</span> Meu Perfil</li>
    <li><span class="num">17.</span> Visitas Técnicas</li>
    <li><span class="num">18.</span> Roteiros de Inspeção</li>
    <li><span class="num">19.</span> Não Conformidades</li>
    <li><span class="num">20.</span> Relatórios Administrativos</li>
    <li><span class="num">21.</span> Sequência Lógica de Utilização</li>
</ul>
</div>
');

// ============================================================
// 1. INTRODUÇÃO
// ============================================================
$mpdf->AddPage();
$mpdf->WriteHTML('
<h1>1. Introdução ao Sistema</h1>
<p>O <strong>SINPORG — Sistema Integrado da Produção Orgânica</strong> é uma plataforma web desenvolvida para gerenciar de forma integrada os processos de certificação orgânica participativa. O sistema permite o cadastro de associados, propriedades, lavouras, atividades do caderno de campo, insumos, produtos, certificações, planos de manejo, inspeções e visitas técnicas, além de relatórios gerenciais.</p>

<p>Este manual tem como objetivo orientar todos os usuários do sistema — agricultores, gestores e administradores — sobre as funcionalidades disponíveis, apresentando passo a passo a utilização de cada módulo, com a sequência lógica correta de utilização e imagens ilustrativas das telas.</p>

<h2>1.1 Público-alvo</h2>
<ul>
    <li><strong>Agricultores:</strong> utilizam o sistema para registrar propriedades, lavouras, atividades diárias, insumos, produtos e acompanhar sua certificação.</li>
    <li><strong>Gestores:</strong> gerenciam associados, certificações, inspeções, eventos e emitem relatórios.</li>
    <li><strong>Administradores:</strong> possuem acesso total ao sistema, incluindo gestão de usuários, contas e configurações.</li>
</ul>

<h2>1.2 Perfis de acesso</h2>
<table>
    <tr><th>Perfil</th><th>Nível</th><th>Principais permissões</th></tr>
    <tr><td>Admin</td><td>1</td><td>Acesso total: usuários, associados, certificações, relatórios, contas</td></tr>
    <tr><td>Gestor</td><td>2</td><td>Gestão de associados, certificações, inspeções, eventos, relatórios</td></tr>
    <tr><td>Usuário/Agricultor</td><td>3</td><td>Caderno de campo, propriedades, certificações, planos de manejo</td></tr>
</table>
');

// ============================================================
// 2. REQUISITOS E ACESSO
// ============================================================
$mpdf->AddPage();
$mpdf->WriteHTML('
<h1>2. Requisitos e Acesso</h1>
<h2>2.1 Requisitos mínimos</h2>
<ul>
    <li>Navegador atualizado: <strong>Chrome, Firefox, Edge ou Safari</strong> em versões recentes.</li>
    <li>Resolução recomendada: 1366×768 ou superior.</li>
    <li>Conexão com a internet (para dados online).</li>
</ul>
<h2>2.2 Acesso ao sistema</h2>
<p>Para acessar o SINPORG, digite o endereço do sistema no navegador:</p>
<p style="text-align:center; font-weight:bold; font-size:14pt; color:#0f5132;">http://localhost/app</p>
<div class="info-box">
    <strong>Dica:</strong> O sistema é responsivo e pode ser acessado também por dispositivos móveis (tablets e smartphones), permitindo registrar atividades no campo.
</div>
');

// ============================================================
// 3. PRIMEIRO ACESSO
// ============================================================
$mpdf->AddPage();
$mpdf->WriteHTML('
<h1>3. Primeiro Acesso (Pré-cadastro)</h1>
<p>Novos usuários podem se cadastrar diretamente pelo sistema utilizando a aba <strong>"Primeiro Acesso"</strong> na tela de login. A imagem abaixo mostra a tela de login do sistema.</p>
');
if (file_exists($IMAGES_DIR . '01-login.png')) {
    $mpdf->WriteHTML('<div class="screenshot"><img src="' . $IMAGES_DIR . '01-login.png" style="width:100%;"><div class="screenshot-caption">Figura 1 — Tela de Login do SINPORG</div></div>');
}
$mpdf->WriteHTML('
<div class="sequence">
<strong>Sequência lógica do primeiro acesso:</strong>
<ol>
    <li>Acesse a página de login do sistema.</li>
    <li>Clique na aba <strong>"Primeiro Acesso"</strong> localizada no topo do formulário.</li>
    <li>Preencha os campos obrigatórios: <strong>Nome</strong>, <strong>Sobrenome</strong>, <strong>E-mail</strong>, <strong>Senha</strong> e <strong>Confirmação de senha</strong>.</li>
    <li>Clique no botão <strong>"Solicitar Cadastro"</strong>.</li>
</ol>
</div>
<div class="warning-box">
    <strong>Atenção:</strong> A senha deve ter no mínimo 6 caracteres e as confirmações devem coincidir. Após o envio, sua solicitação será analisada e aprovada por um administrador. O login só será liberado após a ativação da conta.
</div>
<h2>3.1 Completar perfil</h2>
<p>Após o primeiro login, o sistema direciona automaticamente para a página de <strong>Meu Perfil</strong>, onde devem ser preenchidos os dados complementares (telefone, CPF, endereço, dados de nascimento, etc.).</p>
<h2>3.2 Cadastro de propriedade</h2>
<p>Ao salvar o perfil no primeiro acesso, o sistema direciona automaticamente para o cadastro de propriedade, onde o usuário informa os dados da sua propriedade rural para vínculo com o cadastro.</p>
');

// ============================================================
// 4. LOGIN
// ============================================================
$mpdf->AddPage();
$mpdf->WriteHTML('
<h1>4. Login no Sistema</h1>
<p>Para acessar o sistema, informe suas credenciais (e-mail e senha) e clique no botão <strong>"Entrar"</strong>.</p>
');
if (file_exists($IMAGES_DIR . '01-login.png')) {
    $mpdf->WriteHTML('<div class="screenshot"><img src="' . $IMAGES_DIR . '01-login.png" style="width:100%;"><div class="screenshot-caption">Figura 2 — Tela de login do SINPORG</div></div>');
}
$mpdf->WriteHTML('
<div class="sequence">
<strong>Sequência lógica do login:</strong>
<ol>
    <li>Acesse a página de login do sistema.</li>
    <li>Informe seu <strong>e-mail cadastrado</strong> no campo indicado.</li>
    <li>Informe sua <strong>senha</strong> no campo indicado.</li>
    <li>Clique no botão <strong>"Entrar"</strong>.</li>
</ol>
</div>
<h2>4.1 Gestores — confirmação de vínculo</h2>
<p>Na primeira vez que um gestor acessa o sistema, é solicitado o preenchimento do <strong>Órgão</strong> e <strong>Cargo/Especialidade</strong> para confirmar o vínculo institucional.</p>
');

// ============================================================
// 5. DASHBOARD
// ============================================================
addSection($mpdf, $IMAGES_DIR, '5. Dashboard Principal',
'<p>Após o login, o sistema exibe o <strong>Dashboard principal</strong>, que apresenta um resumo dos principais recursos e informações do sistema.</p>
<h3>Funcionalidades do Dashboard:</h3>
<ul>
    <li>Acesso rápido aos módulos: <strong>Associados</strong>, <strong>Caderno de Campo</strong>, <strong>Relatórios</strong> e <strong>Eventos</strong>.</li>
    <li>Tabela de <strong>Próximos Eventos e Eventos Recentes</strong>.</li>
    <li>Tabela de <strong>Presença em Eventos</strong>.</li>
    <li>Widget de <strong>cadastros pendentes</strong> de ativação (para gestores/administradores).</li>
    <li>Menu lateral com acesso a todos os módulos do sistema.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Após o login, o Dashboard é a tela inicial que centraliza o acesso a todos os módulos. Utilize o menu lateral para navegar entre as funcionalidades.
</div>',
'02-dashboard.png', 'Figura 3 — Dashboard principal do SINPORG');

// ============================================================
// 6. CADERNO DE CAMPO
// ============================================================
addSection($mpdf, $IMAGES_DIR, '6. Caderno de Campo',
'<p>O módulo <strong>Caderno de Campo</strong> é a ferramenta central para registro das atividades de produção orgânica. Ele é organizado em sub-módulos que cobrem todo o ciclo produtivo:</p>
<ul>
    <li><strong>Propriedades</strong> — cadastro das unidades produtivas</li>
    <li><strong>Lavouras</strong> — cultivos realizados em cada propriedade</li>
    <li><strong>Insumos</strong> — registro de insumos utilizados na produção</li>
    <li><strong>Produtos</strong> — produção e comercialização</li>
    <li><strong>Lotes de Produção</strong> — rastreabilidade</li>
    <li><strong>Atividades Diárias</strong> — registro das atividades realizadas</li>
    <li><strong>Ocorrências</strong> — registro de não conformidades e ocorrências</li>
</ul>
<div class="sequence">
<strong>Sequência lógica de uso do Caderno de Campo:</strong>
<ol>
    <li>Cadastre a <strong>Propriedade</strong> (unidade produtiva).</li>
    <li>Cadastre as <strong>Lavouras</strong> da propriedade.</li>
    <li>Registre os <strong>Insumos</strong> utilizados.</li>
    <li>Cadastre os <strong>Produtos</strong> e os <strong>Lotes de Produção</strong>.</li>
    <li>Registre as <strong>Atividades Diárias</strong> realizadas.</li>
    <li>Registre as <strong>Ocorrências</strong> relevantes.</li>
</ol>
</div>',
'03-caderno-dashboard.png', 'Figura 4 — Dashboard do Caderno de Campo');

// 6.1 Propriedades
addSection($mpdf, $IMAGES_DIR, '6.1 Propriedades',
'<p>O módulo de <strong>Propriedades</strong> permite cadastrar e gerenciar as unidades produtivas dos associados.</p>
<h3>Ações disponíveis:</h3>
<ul>
    <li><strong>Adicionar Nova Propriedade:</strong> cadastrar uma nova propriedade com informações como nome, endereço, área, certificação orgânica, etc.</li>
    <li><strong>Editar:</strong> alterar os dados de uma propriedade existente.</li>
    <li><strong>Visualizar Imagens:</strong> gerenciar as fotos da propriedade.</li>
</ul>
<p>Para cadastrar uma nova propriedade, clique no botão <strong>"Adicionar Nova Propriedade"</strong> e preencha o formulário com os dados da unidade produtiva.</p>',
'04-propriedades.png', 'Figura 5 — Listagem de propriedades');

// 6.2 Cadastro de Propriedade
addSection($mpdf, $IMAGES_DIR, '6.2 Cadastro de Propriedade',
'<p>O formulário de cadastro de propriedade inclui campos como:</p>
<ul>
    <li><strong>Nome</strong> da propriedade.</li>
    <li><strong>Endereço completo</strong> (logradouro, número, bairro, cidade, estado, CEP).</li>
    <li><strong>Área total</strong> e área produtiva.</li>
    <li><strong>Coordenadas geográficas</strong> (latitude/longitude, opcional).</li>
    <li><strong>Data de certificação</strong> e dados complementares.</li>
    <li>Upload de <strong>imagens</strong> da propriedade.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica do cadastro de propriedade:</strong>
<ol>
    <li>Acesse o módulo <strong>Propriedades</strong> no Caderno de Campo.</li>
    <li>Clique em <strong>"Adicionar Nova Propriedade"</strong>.</li>
    <li>Preencha todos os campos obrigatórios do formulário.</li>
    <li>Anexe as imagens da propriedade (se houver).</li>
    <li>Clique em <strong>"Salvar"</strong> para concluir o cadastro.</li>
</ol>
</div>',
'05-propriedade-novo.png', 'Figura 6 — Formulário de cadastro de propriedade');

// 6.3 Lavouras
addSection($mpdf, $IMAGES_DIR, '6.3 Lavouras',
'<p>O módulo <strong>Lavouras</strong> permite registrar os cultivos realizados em cada propriedade, com informações sobre a cultura plantada, área de plantio e data.</p>
<ul>
    <li><strong>Adicionar Lavoura:</strong> registrar um novo cultivo informando propriedade, cultura, área e datas.</li>
    <li><strong>Editar:</strong> alterar os dados de uma lavoura existente.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Antes de cadastrar uma lavoura, a propriedade já deve estar cadastrada no sistema. Selecione a propriedade, informe a cultura, a área plantada e as datas de plantio.
</div>',
'06-lavouras.png', 'Figura 7 — Listagem de lavouras');

// 6.4 Insumos
addSection($mpdf, $IMAGES_DIR, '6.4 Insumos',
'<p>O módulo <strong>Insumos</strong> permite registrar os insumos agrícolas utilizados na produção, como adubos orgânicos, defensivos naturais, sementes, etc.</p>
<ul>
    <li><strong>Adicionar Insumo:</strong> registrar um novo insumo com nome, categoria e status de aprovação.</li>
    <li><strong>Editar:</strong> alterar as informações de um insumo.</li>
    <li><strong>Validação de Insumos:</strong> fluxo de aprovação de insumos para uso na produção orgânica.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Cadastre os insumos utilizados na produção e acompanhe o status de aprovação de cada um para garantir a conformidade com a certificação orgânica.
</div>',
'07-insumos.png', 'Figura 8 — Listagem de insumos');

// 6.5 Produtos
addSection($mpdf, $IMAGES_DIR, '6.5 Produtos',
'<p>O módulo <strong>Produtos</strong> gerencia os produtos da produção orgânica, incluindo a rastreabilidade dos lotes.</p>
<ul>
    <li>Cadastro de produtos com nome, categoria e unidade de medida.</li>
    <li>Vínculo dos produtos com os lotes de produção.</li>
    <li>Acompanhamento da comercialização.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Cadastre os produtos oriundos da produção orgânica para posteriormente vinculá-los aos lotes de produção, garantindo a rastreabilidade.
</div>',
'08-produtos.png', 'Figura 9 — Listagem de produtos');

// 6.6 Lotes
addSection($mpdf, $IMAGES_DIR, '6.6 Lotes de Produção',
'<p>O módulo <strong>Lotes de Produção</strong> é fundamental para a rastreabilidade dos produtos orgânicos.</p>
<ul>
    <li><strong>Adicionar Lote:</strong> registrar um lote de produção com informações do produto, quantidade e data.</li>
    <li><strong>Rastrear:</strong> acompanhar a história do lote desde a produção até a comercialização.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> O lote de produção vincula o produto à sua origem (propriedade, lavoura, insumos e atividades), permitindo a rastreabilidade completa da cadeia produtiva.
</div>',
'09-lotes.png', 'Figura 10 — Listagem de lotes de produção');

// 6.7 Atividades
addSection($mpdf, $IMAGES_DIR, '6.7 Atividades Diárias',
'<p>O módulo <strong>Atividades Diárias</strong> funciona como o caderno de campo eletrônico, permitindo registrar todas as atividades realizadas na propriedade.</p>
<ul>
    <li><strong>Adicionar Atividade:</strong> registrar atividade com tipo (plantio, colheita, adubação, etc.), data, propriedade e descrição.</li>
    <li>Upload de <strong>imagens</strong> como evidência das atividades.</li>
    <li><strong>Categorias de Atividades:</strong> organização das atividades por categoria para relatórios consolidados.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Registre as atividades diárias realizadas na propriedade, informando o tipo de atividade, a data, a propriedade e anexando imagens de evidência quando possível.
</div>',
'10-atividades.png', 'Figura 11 — Listagem de atividades diárias');

// 6.8 Ocorrências
addSection($mpdf, $IMAGES_DIR, '6.8 Ocorrências',
'<p>O módulo <strong>Ocorrências</strong> permite registrar eventos não previstos na propriedade, como pragas, doenças, intempéries climáticas, incidentes, etc.</p>
<ul>
    <li><strong>Adicionar Ocorrência:</strong> registrar nova ocorrência com título, descrição, data e propriedade.</li>
    <li><strong>Editar:</strong> atualizar informações da ocorrência.</li>
    <li><strong>Acompanhamento:</strong> monitorar a resolução das ocorrências.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Qualquer evento relevante que fuja da normalidade na propriedade deve ser registrado como ocorrência, com descrição detalhada e data, para compor o histórico da produção.
</div>',
'11-ocorrencias.png', 'Figura 12 — Listagem de ocorrências');

// ============================================================
// 7. ASSOCIADOS
// ============================================================
addSection($mpdf, $IMAGES_DIR, '7. Gestão de Associados',
'<p>O módulo <strong>Associados</strong> permite gerenciar o cadastro de todos os associados do sistema (produtores, agricultores, gestores, etc.).</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Listagem:</strong> visualização de todos os associados cadastrados com status (ativo, pendente, etc.).</li>
    <li><strong>Cadastro:</strong> registro de novos associados com dados pessoais e de contato.</li>
    <li><strong>Edição:</strong> atualização dos dados de um associado.</li>
    <li><strong>Ativação:</strong> aprovação de cadastros pendentes (gestores/administradores).</li>
    <li><strong>Exclusão:</strong> remoção de associados do sistema.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica (gestor/admin):</strong>
<ol>
    <li>Acesse o módulo <strong>Associados</strong> no menu lateral.</li>
    <li>Visualize a lista de associados e os status dos cadastros.</li>
    <li>Aprove os cadastros pendentes de ativação.</li>
    <li>Edite os dados quando necessário.</li>
</ol>
</div>',
'12-associados.png', 'Figura 13 — Listagem de associados');

// ============================================================
// 8. ASSOCIAÇÕES
// ============================================================
addSection($mpdf, $IMAGES_DIR, '8. Associações',
'<p>O módulo <strong>Associações</strong> permite gerenciar as associações vinculadas ao sistema de produção orgânica participativa.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Listagem:</strong> visualização das associações cadastradas.</li>
    <li><strong>Cadastro:</strong> registro de novas associações com dados institucionais.</li>
    <li><strong>Edição:</strong> atualização dos dados das associações.</li>
    <li><strong>Detalhes:</strong> visualização de informações completas de cada associação.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> O cadastro das associações é pré-requisito para vincular associados e propriedades ao sistema de certificação participativa.
</div>',
'13-associacoes.png', 'Figura 14 — Listagem de associações');

// ============================================================
// 9. CERTIFICAÇÕES
// ============================================================
addSection($mpdf, $IMAGES_DIR, '9. Certificações',
'<p>O módulo <strong>Certificações</strong> gerencia o processo de certificação orgânica dos associados.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Listagem:</strong> visualização de todas as certificações com status.</li>
    <li><strong>Cadastro:</strong> solicitação de nova certificação.</li>
    <li><strong>Renovação:</strong> renovação de certificações existentes.</li>
    <li><strong>Acompanhamento:</strong> monitoramento do status (pendente, ativa, vencida).</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong>
<ol>
    <li>Cadastre a propriedade e as lavouras.</li>
    <li>Registre as atividades e insumos no caderno de campo.</li>
    <li>Solicite a certificação pelo módulo <strong>Certificações</strong>.</li>
    <li>Acompanhe o status até a aprovação.</li>
</ol>
</div>',
'14-certificacoes.png', 'Figura 15 — Listagem de certificações');

// ============================================================
// 10. PLANOS DE MANEJO
// ============================================================
addSection($mpdf, $IMAGES_DIR, '10. Planos de Manejo',
'<p>O módulo <strong>Planos de Manejo</strong> permite o registro e acompanhamento dos planos de manejo orgânico das propriedades.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Visualizar:</strong> consulta do plano de manejo atual da propriedade.</li>
    <li><strong>Cadastro:</strong> registro de novo plano de manejo.</li>
    <li><strong>Edição:</strong> atualização do plano existente.</li>
    <li><strong>Histórico:</strong> acompanhamento das versões anteriores.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> O plano de manejo deve ser cadastrado após a propriedade e antes ou junto do processo de certificação, descrevendo as práticas de manejo orgânico adotadas.
</div>',
'15-planos-manejo.png', 'Figura 16 — Listagem de planos de manejo');

// ============================================================
// 11. RELATÓRIOS
// ============================================================
addSection($mpdf, $IMAGES_DIR, '11. Relatórios',
'<p>O módulo <strong>Relatórios</strong> permite a geração de relatórios gerenciais do sistema.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Relatórios de associados:</strong> listagem de produtores e propriedades.</li>
    <li><strong>Relatórios de atividades:</strong> consolidação das atividades do caderno de campo.</li>
    <li><strong>Relatórios de insumos:</strong> insumos utilizados na produção.</li>
    <li><strong>Relatórios financeiros:</strong> dados de contas e pagamentos.</li>
    <li><strong>Relatórios personalizados:</strong> geração com filtros específicos.</li>
    <li>Exportação em <strong>PDF</strong> e <strong>Excel</strong>.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Acesse o módulo de relatórios, selecione o tipo de relatório desejado, aplique os filtros e exporte o documento no formato desejado.
</div>',
'16-relatorios.png', 'Figura 17 — Módulo de relatórios');

// ============================================================
// 12. EVENTOS
// ============================================================
addSection($mpdf, $IMAGES_DIR, '12. Eventos',
'<p>O módulo <strong>Eventos</strong> permite o gerenciamento de eventos do sistema (reuniões, assembleias, visitas, capacitações, etc.).</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Cadastro de eventos:</strong> registro com título, data, local e descrição.</li>
    <li><strong>Confirmação de presença:</strong> registro de presença dos associados.</li>
    <li><strong>Listagem:</strong> próximos eventos e eventos recentes no dashboard.</li>
    <li><strong>Controle:</strong> acompanhamento da participação.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Cadastre o evento, divulgue aos associados e registre a presença dos participantes para compor o histórico de participação da certificação participativa.
</div>',
'17-eventos.png', 'Figura 18 — Listagem de eventos');

// ============================================================
// 13. USUÁRIOS
// ============================================================
addSection($mpdf, $IMAGES_DIR, '13. Usuários',
'<p>O módulo <strong>Usuários</strong> (acesso administrativo) permite o gerenciamento dos usuários do sistema.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Listagem:</strong> visualização de todos os usuários cadastrados.</li>
    <li><strong>Cadastro:</strong> criação de novos usuários com definição de perfil/nível.</li>
    <li><strong>Edição:</strong> alteração de dados e permissões.</li>
    <li><strong>Ativação/Desativação:</strong> controle de acesso ao sistema.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica (admin):</strong> Cadastre os usuários, defina o nível de acesso (Admin, Gestor ou Usuário) e gerencie as permissões de cada um.
</div>',
'18-usuarios.png', 'Figura 19 — Listagem de usuários');

// ============================================================
// 14. CONTAS
// ============================================================
addSection($mpdf, $IMAGES_DIR, '14. Contas',
'<p>O módulo <strong>Contas</strong> (acesso administrativo) gerencia as finanças e pagamentos do sistema.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Registro de contas:</strong> lançamento de receitas e despesas.</li>
    <li><strong>Controle de pagamentos:</strong> acompanhamento de pagamentos dos associados.</li>
    <li><strong>Relatórios financeiros:</strong> consolidação dos dados financeiros.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica (admin):</strong> Registre as contas e pagamentos, acompanhe os lançamentos e gere relatórios financeiros para o controle da associação.
</div>',
'19-contas.png', 'Figura 20 — Módulo de contas');

// ============================================================
// 15. FORNECEDOR
// ============================================================
addSection($mpdf, $IMAGES_DIR, '15. Fornecedor',
'<p>O módulo <strong>Fornecedor</strong> permite o gerenciamento dos fornecedores de insumos e materiais.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Cadastro de fornecedores:</strong> registro com dados de contato e CNPJ/CPF.</li>
    <li><strong>Listagem:</strong> visualização de todos os fornecedores cadastrados.</li>
    <li><strong>Edição:</strong> atualização dos dados cadastrais.</li>
    <li><strong>Vínculo:</strong> associação de fornecedores aos insumos.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Cadastre os fornecedores e vincule-os aos insumos utilizados, garantindo a rastreabilidade da origem dos materiais.
</div>',
'20-fornecedor.png', 'Figura 21 — Listagem de fornecedores');

// ============================================================
// 16. MEU PERFIL
// ============================================================
addSection($mpdf, $IMAGES_DIR, '16. Meu Perfil',
'<p>O módulo <strong>Meu Perfil</strong> permite ao usuário visualizar e editar seus dados pessoais cadastrais.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Dados pessoais:</strong> nome, e-mail, telefone, CPF, endereço, etc.</li>
    <li><strong>Edição:</strong> atualização dos dados do usuário.</li>
    <li><strong>Propriedades:</strong> vínculo e gerenciamento das propriedades do usuário.</li>
    <li><strong>Configurações:</strong> alteração de senha e preferências.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica:</strong> Mantenha seus dados sempre atualizados, especialmente após o primeiro acesso. Cadastre e gerencie suas propriedades pelo perfil.
</div>',
'21-meu-perfil.png', 'Figura 22 — Meu Perfil');

// ============================================================
// 17. VISITAS TÉCNICAS
// ============================================================
addSection($mpdf, $IMAGES_DIR, '17. Visitas Técnicas',
'<p>O módulo <strong>Visitas Técnicas</strong> registra e gerencia as visitas técnicas realizadas pelos gestores/inspecionadores às propriedades.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Agendamento:</strong> registro de visitas com data, propriedade e responsável.</li>
    <li><strong>Realização:</strong> preenchimento do relatório da visita.</li>
    <li><strong>Relatórios:</strong> geração do relatório de visita técnica em PDF.</li>
    <li><strong>Histórico:</strong> acompanhamento das visitas realizadas.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica (gestor):</strong>
<ol>
    <li>Agende a visita técnica à propriedade.</li>
    <li>Realize a visita e preencha o relatório.</li>
    <li>Registre as evidências e conclusões.</li>
    <li>Gere e arquive o relatório de visita.</li>
</ol>
</div>',
'22-visitas.png', 'Figura 23 — Listagem de visitas técnicas');

// ============================================================
// 18. ROTEIROS DE INSPEÇÃO
// ============================================================
addSection($mpdf, $IMAGES_DIR, '18. Roteiros de Inspeção',
'<p>O módulo <strong>Roteiros de Inspeção</strong> gerencia os roteiros e checklists de inspeção da certificação orgânica.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Templates:</strong> modelos de roteiros de inspeção.</li>
    <li><strong>Realização:</strong> execução do roteiro durante a inspeção.</li>
    <li><strong>Não conformidades:</strong> registro de não conformidades encontradas.</li>
    <li><strong>Relatórios:</strong> geração do relatório de inspeção.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica (gestor):</strong> Selecione o template de roteiro, realize a inspeção na propriedade, registre as não conformidades e gere o relatório final.
</div>',
'23-roteiros.png', 'Figura 24 — Roteiros de inspeção');

// ============================================================
// 19. NÃO CONFORMIDADES
// ============================================================
addSection($mpdf, $IMAGES_DIR, '19. Não Conformidades',
'<p>O módulo <strong>Não Conformidades</strong> registra e acompanha as não conformidades identificadas durante inspeções e auditorias.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Registro:</strong> cadastro de não conformidades com descrição e severidade.</li>
    <li><strong>Classificação:</strong> categorização por tipo e gravidade.</li>
    <li><strong>Acompanhamento:</strong> monitoramento das ações corretivas.</li>
    <li><strong>Resolução:</strong> registro da correção e encerramento.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica (gestor):</strong>
<ol>
    <li>Registre a não conformidade identificada na inspeção.</li>
    <li>Classifique quanto à severidade.</li>
    <li>Acompanhe as ações corretivas.</li>
    <li>Registre a resolução e encerre o caso.</li>
</ol>
</div>',
'24-nao-conformidades.png', 'Figura 25 — Listagem de não conformidades');

// ============================================================
// 20. RELATÓRIOS ADMIN
// ============================================================
addSection($mpdf, $IMAGES_DIR, '20. Relatórios Administrativos',
'<p>O módulo <strong>Relatórios Administrativos</strong> (acesso administrativo) disponibiliza relatórios consolidados para a gestão do sistema.</p>
<h3>Funcionalidades:</h3>
<ul>
    <li><strong>Relatórios de associados:</strong> estatísticas de cadastro e ativação.</li>
    <li><strong>Relatórios de certificações:</strong> status das certificações.</li>
    <li><strong>Relatórios de atividades:</strong> consolidação das atividades produtivas.</li>
    <li><strong>Relatórios financeiros:</strong> dados de contas e pagamentos.</li>
    <li><strong>Exportação:</strong> PDF e Excel.</li>
</ul>
<div class="sequence">
<strong>Sequência lógica (admin):</strong> Acesse o módulo de relatórios administrativos, escolha o tipo de relatório, aplique os filtros desejados e exporte o documento.
</div>',
'25-relatorios-admin.png', 'Figura 26 — Relatórios administrativos');

// ============================================================
// 21. SEQUÊNCIA LÓGICA DE UTILIZAÇÃO
// ============================================================
$mpdf->AddPage();
$mpdf->WriteHTML('
<h1>21. Sequência Lógica de Utilização</h1>
<p>Para garantir o correto uso do SINPORG, recomenda-se seguir a sequência lógica abaixo:</p>

<h2>21.1 Para o Agricultor</h2>
<div class="sequence">
<ol>
    <li><strong>Primeiro Acesso:</strong> realize o pré-cadastro pela tela de login.</li>
    <li><strong>Completar Perfil:</strong> preencha seus dados pessoais em Meu Perfil.</li>
    <li><strong>Cadastrar Propriedade:</strong> registre sua unidade produtiva.</li>
    <li><strong>Cadastrar Lavouras:</strong> registre os cultivos da propriedade.</li>
    <li><strong>Registrar Insumos:</strong> cadastre os insumos utilizados.</li>
    <li><strong>Registrar Atividades Diárias:</strong> mantenha o caderno de campo atualizado.</li>
    <li><strong>Registrar Produtos e Lotes:</strong> cadastre a produção e os lotes.</li>
    <li><strong>Acompanhar Certificação:</strong> solicite e acompanhe sua certificação.</li>
    <li><strong>Manter Plano de Manejo:</strong> atualize o plano de manejo.</li>
    <li><strong>Registrar Ocorrências:</strong> documente eventos relevantes.</li>
</ol>
</div>

<h2>21.2 Para o Gestor</h2>
<div class="sequence">
<ol>
    <li><strong>Aprovar Associados:</strong> ative os cadastros pendentes.</li>
    <li><strong>Gerenciar Associações:</strong> mantenha as associações cadastradas.</li>
    <li><strong>Acompanhar Certificações:</strong> valide e renove certificações.</li>
    <li><strong>Realizar Visitas Técnicas:</strong> agende e execute visitas.</li>
    <li><strong>Realizar Inspeções:</strong> execute os roteiros de inspeção.</li>
    <li><strong>Registrar Não Conformidades:</strong> documente e acompanhe as NCs.</li>
    <li><strong>Gerenciar Eventos:</strong> cadastre e registre presenças.</li>
    <li><strong>Gerar Relatórios:</strong> emita relatórios de acompanhamento.</li>
</ol>
</div>

<h2>21.3 Para o Administrador</h2>
<div class="sequence">
<ol>
    <li><strong>Gerenciar Usuários:</strong> cadastre e defina permissões.</li>
    <li><strong>Gerenciar Contas:</strong> controle as finanças e pagamentos.</li>
    <li><strong>Gerenciar Fornecedores:</strong> mantenha o cadastro atualizado.</li>
    <li><strong>Relatórios Administrativos:</strong> gere relatórios consolidados.</li>
    <li><strong>Configurações:</strong> mantenha o sistema configurado.</li>
</ol>
</div>

<h2>21.4 Resumo do fluxo completo</h2>
<table>
    <tr><th>Etapa</th><th>Módulo</th><th>Responsável</th></tr>
    <tr><td>1</td><td>Pré-cadastro / Login</td><td>Agricultor</td></tr>
    <tr><td>2</td><td>Meu Perfil</td><td>Agricultor</td></tr>
    <tr><td>3</td><td>Propriedades</td><td>Agricultor</td></tr>
    <tr><td>4</td><td>Lavouras</td><td>Agricultor</td></tr>
    <tr><td>5</td><td>Insumos / Atividades</td><td>Agricultor</td></tr>
    <tr><td>6</td><td>Produtos / Lotes</td><td>Agricultor</td></tr>
    <tr><td>7</td><td>Associados (ativação)</td><td>Gestor/Admin</td></tr>
    <tr><td>8</td><td>Certificações</td><td>Gestor</td></tr>
    <tr><td>9</td><td>Visitas Técnicas</td><td>Gestor</td></tr>
    <tr><td>10</td><td>Roteiros de Inspeção</td><td>Gestor</td></tr>
    <tr><td>11</td><td>Não Conformidades</td><td>Gestor</td></tr>
    <tr><td>12</td><td>Relatórios</td><td>Gestor/Admin</td></tr>
</table>

<div class="info-box">
    <strong>Conclusão:</strong> O SINPORG foi projetado para integrar toda a cadeia da produção orgânica, desde o cadastro do agricultor até a emissão de relatórios gerenciais, garantindo rastreabilidade e conformidade com a certificação orgânica participativa.
</div>
');

// ============================================================
// SALVAR PDF
// ============================================================
$mpdf->Output($OUTPUT_FILE, \Mpdf\Output\Destination::FILE);
echo "\n✅ PDF gerado com sucesso!\n";
echo "Arquivo: $OUTPUT_FILE\n";
echo "Tamanho: " . round(filesize($OUTPUT_FILE) / 1024) . " KB\n";

