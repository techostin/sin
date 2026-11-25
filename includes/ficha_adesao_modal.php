<?php
/**
 * Ficha de Adesão ao Quadro Associativo — modal de primeiro acesso.
 * Reproduz a "Ficha de Cadastro de Associado" (/doc/Ficha de Cadastro de
 * Associado.docx) com todos os campos. Exibida após o login de associados
 * cuja ficha ainda não foi APROVADA; reaparece a cada novo acesso até a
 * aprovação pela Diretoria/Presidência.
 * Incluído por includes/header.php.
 */

if (!defined('NIVEL_USUARIO')) {
    require_once __DIR__ . '/../config/config.php';
}

// A ficha de adesão é do ASSOCIADO, mas o pedido de acesso nasce como CONTA
// (tabela usuarios). Nos dois casos o modal deve ser oferecido: quem decide
// exibir é a API (?action=status), que responde 'aprovada' quando já existe
// ficha aprovada — inclusive para a conta já vinculada a um cadastro.
$fichaModalAssociado = false;
if (isset($_SESSION['user_id'])) {
    $tabelaFicha = $_SESSION['user_table'] ?? '';
    $nivelFicha  = (int)($_SESSION['user_nivel'] ?? 0);
    // Sessões antigas/admin podem não ter user_table preenchido; nesse caso a
    // própria API de status decide (aprovada x pendente x sem ficha).
    $fichaModalAssociado = ($tabelaFicha === '' || in_array($tabelaFicha, ['usuarios', 'associados'], true))
        && $nivelFicha === NIVEL_USUARIO;
} elseif (!empty($_SESSION['associado_id'])) {
    $fichaModalAssociado = true;
}

if (!$fichaModalAssociado) {
    return;
}

// Exibe a ficha apenas no primeiro acesso após o login (flag criada em
// login.php). Assim ela não reaparece a cada clique/navegação.
if (empty($_SESSION['mostrar_ficha_modal'])) {
    return;
}
unset($_SESSION['mostrar_ficha_modal']);
?>
<style>
    .ficha-adesao-modal .modal-header { background: linear-gradient(135deg, #1b6b43 0%, #2f9e6f 100%); color:#fff; border-bottom: 4px solid #f0b429; }
    .ficha-adesao-modal .modal-body { background: #fbfdf9; }
    .ficha-header-titulo { letter-spacing: 0.04em; }
    .ficha-header-titulo h4 { font-weight: 800; margin-bottom: 0; }
    .ficha-header-titulo .ficha-org { font-size: .82rem; opacity: .92; }
    .ficha-secao { background: #eaf6ee; border: 1px solid #cfe8d8; border-left: 5px solid #2f9e6f; padding: .55rem .9rem; margin: 1.4rem 0 .7rem; font-weight: 700; color: #155d3a; text-transform: uppercase; letter-spacing: .04em; font-size: .86rem; border-radius: .35rem; }
    .ficha-secao:first-of-type { margin-top: .4rem; }
    .ficha-cabecalho { background:#fff; border:1px solid #dce7dc; border-radius:.5rem; padding: .8rem 1rem; margin-bottom: .4rem; }
    .ficha-radio-group { display:flex; flex-wrap:wrap; gap:.35rem .9rem; }
    .ficha-radio-group .form-check { padding-left: 1.6em; }
    .ficha-check-group { columns: 3 200px; column-gap: 1.2rem; }
    .ficha-check-group .form-check { break-inside: avoid; }
    .ficha-rodape-linha { border-top: 2px dashed #9db8a6; margin-top: 1rem; padding-top: .9rem; }
    .ficha-produtos td, .ficha-produtos th { vertical-align: middle; }
    .ficha-produtos input { border: 1px solid #ced4da; }
    .ficha-aviso-lgpd { background:#f6f4e8; border:1px solid #e4dcb8; color:#5b5434; font-size: .78rem; border-radius:.45rem; padding:.7rem .9rem; }
    .ficha-aguardando-icone { width:74px; height:74px; border-radius:50%; background:linear-gradient(135deg,#f0b429,#f59f00); display:flex; align-items:center; justify-content:center; margin: 0 auto .8rem; color:#fff; font-size:2.1rem; }
    .modal-ficha-footer { display:flex; justify-content: space-between; align-items:center; flex-wrap:wrap; gap:.5rem; width:100%; }
</style>

<div class="modal fade ficha-adesao-modal" id="modalFichaAdesao" tabindex="-1"
     aria-labelledby="modalFichaAdesaoLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="ficha-header-titulo w-100 text-center">
                    <div class="ficha-org fw-semibold">AAES/RS &mdash; Associação de Agricultores Ecologistas Solidários do RS</div>
                    <h4 class="mt-1">FICHA DE CADASTRO DE ASSOCIADO</h4>
                    <div class="ficha-org">Ficha de Adesão ao Quadro Associativo &bull; Rev. 2025</div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

                <div id="fichaAlertaRecusa" class="alert alert-danger py-2 small" style="display:none;">
                    <i class="bi bi-exclamation-octagon-fill me-1"></i>
                    <strong>Sua ficha foi recusada pela Diretoria. Parecer:</strong>
                    <span id="fichaAlertaRecusaTexto"></span>
                    <span class="d-block mt-1">Revise as informações abaixo, corrija o que for necessário e submeta novamente.</span>
                </div>

                <form id="formFichaAdesao" autocomplete="off" novalidate>
                    <input type="hidden" id="ficha_id" value="">

                    <div class="ficha-cabecalho">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold mb-1">Nº DE CADASTRO</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="f-numero_cadastro" readonly placeholder="Gerado após aprovação" value="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold mb-1">DATA DE ENTRADA</label>
                                <input type="text" class="form-control form-control-sm bg-light" id="f-data_entrada" readonly placeholder="Registrada após aprovação" value="">
                            </div>
                            <div class="col-md-4">
                                <div class="small fw-semibold mb-1">TIPO DE ASSOCIADO <span class="text-danger">*</span></div>
                                <div class="ficha-radio-group">
                                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_associado" id="ta-familiar" value="agricultor_familiar"><label class="form-check-label small" for="ta-familiar">Agricultor(a) Familiar</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_associado" id="ta-produtor-agroindustria" value="produtor_agroindustria"><label class="form-check-label small" for="ta-produtor-agroindustria">Produtor(a)/Agroindústria</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_associado" id="ta-colaborador" value="colaborador"><label class="form-check-label small" for="ta-colaborador">Colaborador(a)</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_associado" id="ta-honorario" value="honorario"><label class="form-check-label small" for="ta-honorario">Honorário(a)</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_associado" id="ta-institucional" value="institucional"><label class="form-check-label small" for="ta-institucional">Institucional</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_associado" id="ta-consumidor" value="consumidor"><label class="form-check-label small" for="ta-consumidor">Consumidor(a) / Cliente</label></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ficha-secao">1. Identificação Pessoal</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nome completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="nome_completo" id="f-nome_completo" maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nome social (se houver)</label>
                            <input type="text" class="form-control form-control-sm" name="nome_social" id="f-nome_social" maxlength="200">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Data de nascimento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" name="data_nascimento" id="f-data_nascimento">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">CPF <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="cpf" id="f-cpf" maxlength="14" placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">RG / Órgão emissor</label>
                            <input type="text" class="form-control form-control-sm" name="rg_orgao_emissor" id="f-rg_orgao_emissor" maxlength="100" placeholder="RG e órgão emissor">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Sexo / Gênero</label>
                            <select class="form-select form-select-sm" name="sexo_genero" id="f-sexo_genero">
                                <option value="">Selecione...</option>
                                <option value="feminino">Feminino</option>
                                <option value="masculino">Masculino</option>
                                <option value="outro">Outro</option>
                                <option value="prefiro_nao_informar">Prefiro não informar</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Nacionalidade</label>
                            <input type="text" class="form-control form-control-sm" name="nacionalidade" id="f-nacionalidade" maxlength="60" placeholder="Brasileira">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Estado civil</label>
                            <select class="form-select form-select-sm" name="estado_civil" id="f-estado_civil">
                                <option value="">Selecione...</option>
                                <option value="solteiro">Solteiro(a)</option>
                                <option value="casado">Casado(a)</option>
                                <option value="uniao_estavel">União Estável</option>
                                <option value="divorciado">Divorciado(a)</option>
                                <option value="viuvo">Viúvo(a)</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Escolaridade</label>
                            <select class="form-select form-select-sm" name="escolaridade" id="f-escolaridade">
                                <option value="">Selecione...</option>
                                <option value="sem_instrucao">Sem instrução</option>
                                <option value="fundamental_incompleto">Ensino Fundamental incompleto</option>
                                <option value="fundamental_completo">Ensino Fundamental completo</option>
                                <option value="medio_incompleto">Ensino Médio incompleto</option>
                                <option value="medio_completo">Ensino Médio completo</option>
                                <option value="superior_incompleto">Superior incompleto</option>
                                <option value="superior_completo">Superior completo</option>
                                <option value="pos_graduacao">Pós-graduação</option>
                            </select>
                        </div>
                    </div>

                    <div class="ficha-secao">2. Endereço</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Logradouro / Linha / Comunidade</label>
                            <input type="text" class="form-control form-control-sm" name="logradouro" id="f-logradouro" maxlength="200">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Número / Lote</label>
                            <input type="text" class="form-control form-control-sm" name="numero" id="f-numero" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Complemento / Localidade</label>
                            <input type="text" class="form-control form-control-sm" name="complemento" id="f-complemento" maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Bairro / Distrito</label>
                            <input type="text" class="form-control form-control-sm" name="bairro" id="f-bairro" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Município <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="municipio" id="f-municipio" maxlength="100">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small fw-semibold">Estado <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="estado" id="f-estado">
                                <option value="">UF</option>
                                <?php
                                $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                foreach ($ufs as $uf) {
                                    echo '<option value="' . $uf . '"' . ($uf === 'RS' ? ' selected' : '') . '>' . $uf . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small fw-semibold">CEP</label>
                            <input type="text" class="form-control form-control-sm" name="cep" id="f-cep" maxlength="9" placeholder="00000-000">
                        </div>
                        <div class="col-12">
                            <span class="small fw-semibold d-block mb-1">Zona:</span>
                            <div class="ficha-radio-group">
                                <div class="form-check"><input class="form-check-input" type="radio" name="zona" id="z-rural" value="rural"><label class="form-check-label small" for="z-rural">Rural</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="zona" id="z-urbana" value="urbana"><label class="form-check-label small" for="z-urbana">Urbana</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="zona" id="z-periurbana" value="periurbana"><label class="form-check-label small" for="z-periurbana">Periurbana</label></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Latitude / Longitude (opcional)</label>
                            <input type="text" class="form-control form-control-sm" name="latitude_longitude" id="f-latitude_longitude" maxlength="80" placeholder="-30.000000, -51.000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Georreferenciamento SNCR (opcional)</label>
                            <input type="text" class="form-control form-control-sm" name="georreferenciamento_sncr" id="f-georreferenciamento_sncr" maxlength="120">
                        </div>
                    </div>

                    <div class="ficha-secao">3. Contato</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Telefone / WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="telefone_whatsapp" id="f-telefone_whatsapp" maxlength="20" placeholder="(00) 00000-0000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Telefone alternativo</label>
                            <input type="text" class="form-control form-control-sm" name="telefone_alternativo" id="f-telefone_alternativo" maxlength="20" placeholder="(00) 00000-0000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">E-mail</label>
                            <input type="email" class="form-control form-control-sm" name="email" id="f-email" maxlength="120">
                        </div>
                    </div>

                    <div class="ficha-secao">4. Dados da Propriedade Rural</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nome / Denominação da propriedade</label>
                            <input type="text" class="form-control form-control-sm" name="nome_propriedade" id="f-nome_propriedade" maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nº do NIRF / ITR</label>
                            <input type="text" class="form-control form-control-sm" name="nirf_itr" id="f-nirf_itr" maxlength="30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Área total (ha)</label>
                            <input type="text" class="form-control form-control-sm" name="area_total_ha" id="f-area_total_ha" inputmode="decimal" placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Área cultivada (ha)</label>
                            <input type="text" class="form-control form-control-sm" name="area_cultivada_ha" id="f-area_cultivada_ha" inputmode="decimal" placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Área de preservação (ha)</label>
                            <input type="text" class="form-control form-control-sm" name="area_preservacao_ha" id="f-area_preservacao_ha" inputmode="decimal" placeholder="0,00">
                        </div>
                        <div class="col-12">
                            <span class="small fw-semibold d-block mb-1">Situação fundiária:</span>
                            <div class="ficha-radio-group">
                                <div class="form-check"><input class="form-check-input" type="radio" name="situacao_fundiaria" id="sf-propria" value="propria"><label class="form-check-label small" for="sf-propria">Própria</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="situacao_fundiaria" id="sf-arrendada" value="arrendada"><label class="form-check-label small" for="sf-arrendada">Arrendada</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="situacao_fundiaria" id="sf-parceria" value="parceria"><label class="form-check-label small" for="sf-parceria">Parceria</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="situacao_fundiaria" id="sf-assentamento" value="assentamento"><label class="form-check-label small" for="sf-assentamento">Assentamento</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="situacao_fundiaria" id="sf-outra" value="outra"><label class="form-check-label small" for="sf-outra">Outra</label></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">DAP / CAF (nº)</label>
                            <input type="text" class="form-control form-control-sm" name="dap_caf" id="f-dap_caf" maxlength="40">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nº de pessoas na família</label>
                            <input type="number" class="form-control form-control-sm" name="pessoas_familia" id="f-pessoas_familia" min="0" max="99">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Nº de pessoas que trabalham na propriedade</label>
                            <input type="number" class="form-control form-control-sm" name="pessoas_trabalham" id="f-pessoas_trabalham" min="0" max="99">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Renda mensal estimada da propriedade (R$)</label>
                            <input type="text" class="form-control form-control-sm" name="renda_mensal_estimada" id="f-renda_mensal_estimada" inputmode="decimal" placeholder="0,00">
                        </div>
                    </div>

                    <div class="ficha-secao">5. Produção Agroecológica</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tempo de produção agroecológica (anos)</label>
                            <input type="text" class="form-control form-control-sm" name="tempo_producao_agroecologica" id="f-tempo_producao_agroecologica" maxlength="40" placeholder="Ex.: 5 anos">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Certificação orgânica</label>
                            <input type="text" class="form-control form-control-sm" name="certificacao_organica" id="f-certificacao_organica" maxlength="200" placeholder="Certificadora / organismo / validade (se houver)">
                        </div>
                        <div class="col-12">
                            <span class="small fw-semibold d-block mb-1">Tipo de certificação:</span>
                            <div class="ficha-radio-group">
                                <div class="form-check"><input class="form-check-input" type="radio" name="tipo_certificacao" id="tc-nao" value="nao_possui"><label class="form-check-label small" for="tc-nao">Não possui</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="tipo_certificacao" id="tc-auditagem" value="auditagem"><label class="form-check-label small" for="tc-auditagem">Auditagem (3ª parte)</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="tipo_certificacao" id="tc-ocs" value="ocs"><label class="form-check-label small" for="tc-ocs">OCS</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="tipo_certificacao" id="tc-opac" value="opac"><label class="form-check-label small" for="tc-opac">OPAC</label></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <span class="small fw-semibold d-block mb-1">Sistemas de produção utilizados:</span>
                            <div class="ficha-check-group">
                                <?php
                                require_once __DIR__ . '/../classes/FichaAdesao.php';
                                foreach (FichaAdesao::SISTEMAS_PRODUCAO as $spKey => $spLabel) {
                                    echo '<div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_producao[]" id="sp-' . htmlspecialchars($spKey) . '" value="' . htmlspecialchars($spKey) . '"><label class="form-check-label small" for="sp-' . htmlspecialchars($spKey) . '">' . htmlspecialchars($spLabel) . '</label></div>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <span class="small fw-semibold d-block mb-1">Principais produtos / culturas:</span>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered ficha-produtos mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:34px;">#</th>
                                            <th>Produto / Cultura</th>
                                            <th>Variedade / Espécie</th>
                                            <th>Produção estimada</th>
                                            <th>Unidade</th>
                                            <th>Destino principal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="fichaProdutosBody">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <tr>
                                            <td class="text-center fw-semibold"><?php echo $i; ?></td>
                                            <td><input type="text" class="form-control form-control-sm" name="produtos[<?php echo $i - 1; ?>][produto_cultura]" maxlength="150"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="produtos[<?php echo $i - 1; ?>][variedade_especie]" maxlength="150"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="produtos[<?php echo $i - 1; ?>][producao_estimada]" maxlength="80" style="min-width:110px;"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="produtos[<?php echo $i - 1; ?>][unidade]" maxlength="40" placeholder="kg, un., cx..." style="min-width:90px;"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="produtos[<?php echo $i - 1; ?>][destino_principal]" maxlength="150"></td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-12">
                            <span class="small fw-semibold d-block mb-1">Canais de comercialização:</span>
                            <div class="ficha-check-group">
                                <?php
                                foreach (FichaAdesao::CANAIS_COMERCIALIZACAO as $ccKey => $ccLabel) {
                                    echo '<div class="form-check"><input class="form-check-input" type="checkbox" name="canais_comercializacao[]" id="cc-' . htmlspecialchars($ccKey) . '" value="' . htmlspecialchars($ccKey) . '"><label class="form-check-label small" for="cc-' . htmlspecialchars($ccKey) . '">' . htmlspecialchars($ccLabel) . '</label></div>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">Participa de outra organização / cooperativa? Qual?</label>
                            <input type="text" class="form-control form-control-sm" name="outra_organizacao" id="f-outra_organizacao" maxlength="200">
                        </div>
                    </div>

                    <div class="ficha-secao">6. Dados Bancários (para recebimentos / repasses)</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Banco</label>
                            <input type="text" class="form-control form-control-sm" name="banco" id="f-banco" maxlength="80" placeholder="Ex.: Sicredi 000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Agência</label>
                            <input type="text" class="form-control form-control-sm" name="agencia" id="f-agencia" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Conta (corrente / poupança)</label>
                            <input type="text" class="form-control form-control-sm" name="conta" id="f-conta" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Chave Pix</label>
                            <input type="text" class="form-control form-control-sm" name="chave_pix" id="f-chave_pix" maxlength="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Titular da conta</label>
                            <input type="text" class="form-control form-control-sm" name="titular_conta" id="f-titular_conta" maxlength="120">
                        </div>
                    </div>

                    <div class="ficha-secao">7. Observações / Informações Complementares</div>
                    <textarea class="form-control" name="observacoes" id="f-observacoes" rows="3" maxlength="5000"></textarea>

                    <div class="ficha-rodape-linha row g-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="f-aceite_termos" name="aceite_termos" value="1">
                                <label class="form-check-label small" for="f-aceite_termos">
                                    <strong>Declaro</strong>, para os devidos fins, que as informações prestadas nesta ficha são verdadeiras e autorizo a associação a utilizá-las para a gestão do quadro associativo.
                                </label>
                            </div>
                            <div class="mt-2 small text-muted">
                                Assinatura do(a) Associado(a) &mdash; Nome legível: <strong id="f-assinatura-nome" class="text-dark"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? ($_SESSION['associado_nome'] ?? '')); ?></strong>
                                &nbsp;&bull;&nbsp; Data: <span id="f-assinatura-data"><?php echo date('d/m/Y'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">
                                Responsável pelo Cadastro (AAES/RS) &mdash; preenchido pela Diretoria/Presidência após a aprovação da ficha.
                            </div>
                        </div>
                    </div>

                    <div class="ficha-aviso-lgpd mt-3">
                        <i class="bi bi-shield-lock me-1"></i>
                        <strong>Aviso de privacidade:</strong> os dados coletados nesta ficha são utilizados exclusivamente para fins de gestão associativa, acesso a políticas públicas e comercialização de produtos, em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018). O(a) associado(a) pode solicitar alteração, exclusão ou portabilidade de seus dados a qualquer momento junto à secretaria da associação.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="modal-ficha-footer">
                    <div class="d-flex gap-2 w-100 justify-content-end">
                        <button type="button" class="btn btn-outline-success" id="btnFichaSalvarRascunho">
                            <i class="bi bi-save me-1"></i> Salvar rascunho
                        </button>
                        <button type="button" class="btn btn-success" id="btnFichaSubmeter">
                            <i class="bi bi-send-check me-1"></i> Submeter para análise da Diretoria
                        </button>
                    </div>
                    <div class="form-text text-muted mt-2 w-100 text-center">
                        <i class="bi bi-info-circle me-1"></i>
                        Preencha os campos obrigatórios e submeta para análise. Use "Salvar rascunho" para continuar depois.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFichaAguardando" tabindex="-1"
     aria-labelledby="modalFichaAguardandoLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1b6b43 0%,#2f9e6f 100%); color:#fff;">
                <h5 class="modal-title"><i class="bi bi-person-vcard me-2"></i>Ficha de Adesão &mdash; Em Análise</h5>
            </div>
            <div class="modal-body text-center py-4">
                <div class="ficha-aguardando-icone"><i class="bi bi-hourglass-split"></i></div>
                <h5 class="fw-bold">Aguarde a análise da Diretoria/Presidência</h5>
                <p class="text-muted mb-2">
                    Sua ficha de adesão ao quadro associativo foi recebida e está
                    <span class="badge bg-warning text-dark">pendente de avaliação</span>.
                </p>
                <p class="text-muted small mb-1">Submetida em: <strong id="fichaAguardandoData"></strong></p>
                <p class="text-muted small mb-3">
                    Este aviso será exibido a cada acesso até a aprovação da sua ficha.
                    Em caso de recusa, o formulário reabrirá com o parecer da Diretoria.
                </p>
                <a href="#" id="btnFichaVerMinha" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-text me-1"></i> Visualizar minha ficha
                </a>
            </div>
            <div class="modal-footer justify-content-center">
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Este aviso permanecerá até que sua ficha seja aprovada pela Diretoria.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1081;">
    <div id="fichaToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="fichaToastMsg">Mensagem</div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var STORAGE_PREFIX = 'ficha_adesao_vista_';
    var modalFormEl     = document.getElementById('modalFichaAdesao');
    var modalAguardarEl = document.getElementById('modalFichaAguardando');
    if (!modalFormEl || !modalAguardarEl || typeof window.SITE_URL === 'undefined') return;

    var API = window.SITE_URL + '/api/ficha_adesao.php';

    // Bootstrap é carregado no rodapé (footer.php), depois deste script.
    // Por isso os modais são construídos de forma preguiçosa.
    var bsModalForm = null, bsModalAguardar = null;
    function modalForm() {
        if (!bsModalForm && window.bootstrap) bsModalForm = new bootstrap.Modal(modalFormEl);
        return bsModalForm;
    }
    function modalAguardar() {
        if (!bsModalAguardar && window.bootstrap) bsModalAguardar = new bootstrap.Modal(modalAguardarEl);
        return bsModalAguardar;
    }

    function toast(msg) {
        var el = document.getElementById('fichaToast');
        var msgEl = document.getElementById('fichaToastMsg');
        if (!el || !msgEl || !window.bootstrap) return;
        msgEl.textContent = msg;
        var t = bootstrap.Toast.getOrCreateInstance(el);
        t.show();
    }

    function soNumeros(v) { return String(v || '').replace(/\D/g, ''); }

    function maskCPF(v) {
        v = soNumeros(v).slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        return v;
    }
    function maskFone(v) {
        v = soNumeros(v).slice(0, 11);
        if (v.length > 10) {
            v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else {
            v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        }
        return v;
    }
    function maskCEP(v) {
        v = soNumeros(v).slice(0, 8);
        return v.replace(/(\d{5})(\d)/, '$1-$2');
    }

    function bindMasks() {
        var cpf = document.getElementById('f-cpf');
        if (cpf) cpf.addEventListener('input', function () { this.value = maskCPF(this.value); });
        ['f-telefone_whatsapp', 'f-telefone_alternativo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', function () { this.value = maskFone(this.value); });
        });
        var cep = document.getElementById('f-cep');
        if (cep) cep.addEventListener('input', function () { this.value = maskCEP(this.value); });
    }

    function valor(campo) {
        var el = document.querySelector('[name="' + campo + '"]');
        return el ? el.value : '';
    }
    function setValor(campo, v) {
        var el = document.querySelector('[name="' + campo + '"]');
        if (el) el.value = v || '';
    }
    function setRadio(nome, v) {
        var el = document.querySelector('input[name="' + nome + '"][value="' + (v || '') + '"]');
        if (el) el.checked = true;
    }
    function setCheckboxes(nome, lista) {
        var els = document.querySelectorAll('input[name="' + nome + '"]');
        els.forEach(function (el) { el.checked = lista.indexOf(el.value) !== -1; });
    }

    function setComma(dec) {
        return (dec === null || dec === undefined || dec === '') ? '' : String(dec).replace('.', ',');
    }

    function preencherForm(ficha, produtos, usuario) {
        if (!ficha) {
            if (usuario && usuario.nome) setValor('nome_completo', usuario.nome);
            if (usuario && usuario.cpf) setValor('cpf', usuario.cpf);
            if (usuario && usuario.telefone) setValor('telefone_whatsapp', usuario.telefone);
            if (usuario && usuario.email) setValor('email', usuario.email);
            return;
        }
        setValor('nome_completo', ficha.nome_completo);
        setValor('nome_social', ficha.nome_social);
        setValor('data_nascimento', ficha.data_nascimento);
        setValor('cpf', ficha.cpf);
        setValor('rg_orgao_emissor', ficha.rg_orgao_emissor);
        setValor('sexo_genero', ficha.sexo_genero);
        setValor('nacionalidade', ficha.nacionalidade);
        setValor('estado_civil', ficha.estado_civil);
        setValor('escolaridade', ficha.escolaridade);
        setValor('logradouro', ficha.logradouro);
        setValor('numero', ficha.numero);
        setValor('complemento', ficha.complemento);
        setValor('bairro', ficha.bairro);
        setValor('municipio', ficha.municipio);
        setValor('estado', ficha.estado);
        setValor('cep', ficha.cep);
        setRadio('zona', ficha.zona);
        setValor('latitude_longitude', ficha.latitude_longitude);
        setValor('georreferenciamento_sncr', ficha.georreferenciamento_sncr);
        setValor('telefone_whatsapp', ficha.telefone_whatsapp);
        setValor('telefone_alternativo', ficha.telefone_alternativo);
        setValor('email', ficha.email);
        setValor('nome_propriedade', ficha.nome_propriedade);
        setValor('nirf_itr', ficha.nirf_itr);
        setValor('area_total_ha', setComma(ficha.area_total_ha));
        setValor('area_cultivada_ha', setComma(ficha.area_cultivada_ha));
        setValor('area_preservacao_ha', setComma(ficha.area_preservacao_ha));
        setRadio('situacao_fundiaria', ficha.situacao_fundiaria);
        setValor('dap_caf', ficha.dap_caf);
        setValor('pessoas_familia', ficha.pessoas_familia);
        setValor('pessoas_trabalham', ficha.pessoas_trabalham);
        setValor('renda_mensal_estimada', setComma(ficha.renda_mensal_estimada));
        setValor('tempo_producao_agroecologica', ficha.tempo_producao_agroecologica);
        setValor('certificacao_organica', ficha.certificacao_organica);
        setRadio('tipo_certificacao', ficha.tipo_certificacao);
        setCheckboxes('sistemas_producao[]', (ficha.sistemas_producao || '').split(',').filter(Boolean));
        setCheckboxes('canais_comercializacao[]', (ficha.canais_comercializacao || '').split(',').filter(Boolean));
        setValor('outra_organizacao', ficha.outra_organizacao);
        setValor('banco', ficha.banco);
        setValor('agencia', ficha.agencia);
        setValor('conta', ficha.conta);
        setValor('chave_pix', ficha.chave_pix);
        setValor('titular_conta', ficha.titular_conta);
        setValor('observacoes', ficha.observacoes);

        document.getElementById('f-numero_cadastro').value = ficha.numero_cadastro || '';
        document.getElementById('f-data_entrada').value = ficha.data_entrada || '';
        document.getElementById('ficha_id').value = ficha.id || '';

        if (produtos && produtos.length) {
            produtos.forEach(function (p, i) {
                if (i > 4) return;
                setValor('produtos[' + i + '][produto_cultura]', p.produto_cultura);
                setValor('produtos[' + i + '][variedade_especie]', p.variedade_especie);
                setValor('produtos[' + i + '][producao_estimada]', p.producao_estimada);
                setValor('produtos[' + i + '][unidade]', p.unidade);
                setValor('produtos[' + i + '][destino_principal]', p.destino_principal);
            });
        }
    }

    function normalizarDecimais(fd) {
        ['area_total_ha', 'area_cultivada_ha', 'area_preservacao_ha', 'renda_mensal_estimada'].forEach(function (campo) {
            var v = fd.get(campo);
            if (v) fd.set(campo, String(v).replace(',', '.'));
        });
        return fd;
    }

    function validarSubmissao() {
        var obrigatorios = [
            ['tipo_associado', 'Selecione o Tipo de Associado.'],
            ['nome_completo', 'Informe o nome completo.'],
            ['data_nascimento', 'Informe a data de nascimento.'],
            ['cpf', 'Informe um CPF válido.'],
            ['municipio', 'Informe o município.'],
            ['estado', 'Selecione o estado (UF).'],
            ['telefone_whatsapp', 'Informe o telefone / WhatsApp.'],
        ];
        for (var i = 0; i < obrigatorios.length; i++) {
            var campo = obrigatorios[i][0];
            var msg = obrigatorios[i][1];
            var el = document.querySelector('[name="' + campo + '"]:checked');
            if (campo === 'tipo_associado') {
                if (!el) { toast(msg); return false; }
                continue;
            }
            var input = document.querySelector('[name="' + campo + '"]');
            if (!input || !String(input.value).trim()) { toast(msg); return false; }
            if (campo === 'cpf' && soNumeros(input.value).length !== 11) { toast(msg); return false; }
        }
        if (!document.getElementById('f-aceite_termos').checked) {
            toast('É necessário marcar a declaração de veracidade (aceite) para submeter.');
            return false;
        }
        return true;
    }

    function salvarFicha(submeter) {
        var btn = submeter
            ? document.getElementById('btnFichaSubmeter')
            : document.getElementById('btnFichaSalvarRascunho');
        if (submeter && !validarSubmissao()) return;

        var fd = new FormData(document.getElementById('formFichaAdesao'));
        fd.append('action', submeter ? 'submeter' : 'salvar');
        if (!submeter) fd.set('aceite_termos', document.getElementById('f-aceite_termos').checked ? '1' : '0');
        normalizarDecimais(fd);

        var original = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...'; }

        fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.success) {
                    toast(resp.message || 'Ficha salva com sucesso.');
                    if (submeter) {
                        marcarVisto('pendente');
                    } else {
                        marcarVistoFormulario();
                    }
                    if (modalForm()) modalForm().hide();
                    if (submeter) {
                        document.getElementById('fichaAguardandoData').textContent =
                            new Date().toLocaleDateString('pt-BR');
                        if (modalAguardar()) modalAguardar().show();
                    }
                } else {
                    toast(resp.message || 'Erro ao salvar a ficha.');
                }
            })
            .catch(function () { toast('Erro de comunicação. Tente novamente.'); })
            .finally(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
            });
    }

    function marcarVisto(status) {
        try { sessionStorage.setItem(STORAGE_PREFIX + status, '1'); } catch (e) {}
    }
    function marcarVistoFormulario() {
        ['sem_ficha', 'rascunho', 'recusada'].forEach(function (s) {
            try { sessionStorage.setItem(STORAGE_PREFIX + s, '1'); } catch (e) {}
        });
    }

    function abrirModalAguardando(dataSubmissao, fichaId) {
        document.getElementById('fichaAguardandoData').textContent = dataSubmissao || '—';
        var link = document.getElementById('btnFichaVerMinha');
        if (fichaId) {
            link.href = window.SITE_URL + '/cadastro-ficha/visualizar?id=' + encodeURIComponent(fichaId);
            link.style.display = '';
        } else {
            link.style.display = 'none';
        }
        if (modalAguardar()) modalAguardar().show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindMasks();

        fetch(API + '?action=status', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp || !resp.success || !resp.mostrar) return;

                // A ficha deve reaparecer a cada acesso enquanto não estiver
                // aprovada — inclusive para um usuário recém-criado. Não usar
                // sessionStorage aqui (a chave antiga era compartilhada entre
                // contas do mesmo navegador e suprimia a ficha do novo usuário).
                var status = resp.status;

                if (status === 'pendente') {
                    var sub = resp.ficha && resp.ficha.data_submissao
                        ? new Date(resp.ficha.data_submissao.replace(' ', 'T')).toLocaleDateString('pt-BR')
                        : '';
                    abrirModalAguardando(sub, resp.ficha ? resp.ficha.id : null);
                    marcarVisto('pendente');
                    return;
                }

                preencherForm(resp.ficha, resp.produtos, resp.usuario);

                if (status === 'recusada' && resp.ficha && resp.ficha.parecer_diretoria) {
                    document.getElementById('fichaAlertaRecusaTexto').textContent = resp.ficha.parecer_diretoria;
                    document.getElementById('fichaAlertaRecusa').style.display = 'block';
                }
                if (modalForm()) modalForm().show();
                marcarVisto(status);
            })
            .catch(function () { /* falha silenciosa */ });

        document.getElementById('btnFichaSalvarRascunho').addEventListener('click', function () { salvarFicha(false); });
        document.getElementById('btnFichaSubmeter').addEventListener('click', function () { salvarFicha(true); });
        // Modal de aguardando: não pode ser fechado pelo usuário (apenas quando aprovada)
        // btnFichaAguardandoFechar removido - o modal fecha automaticamente quando status muda para aprovada
    });
})();
</script>
<?php
/* fim do include da ficha de adesão */
?>
