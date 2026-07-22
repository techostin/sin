<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/RoteiroInspecao.php';

$db = Database::getInstance()->getConnection();
$manager = new RoteiroInspecao();

// Create new checklist based on Avaliação_de_conformidade.md
$checklist = [
    // Section 1
    ["id" => 1, "item" => "Identificação do Produtor/Propriedade: Nome, CPF/CNPJ, endereço/coordenadas GPS.", "categoria" => "Dados da Propriedade e Registro"],
    ["id" => 2, "item" => "Unidade de Produção: Área total vs. área em manejo orgânico (croqui/mapa anexado).", "categoria" => "Dados da Propriedade e Registro"],
    ["id" => 3, "item" => "Histórico do Uso da Terra: Período de conversão/transição respeitado (mínimo de 12 a 18 meses para anuais; até 3 anos para perenes).", "categoria" => "Dados da Propriedade e Registro"],
    // Section 2
    ["id" => 4, "item" => "Há barreiras vegetais ou físicas contra contaminação por vizinhos (pesticidas/transgênicos)?", "categoria" => "Zonas de Amortecimento e Barreiras de Proteção"],
    ["id" => 5, "item" => "As fontes de água para irrigação estão livres de contaminação por efluentes ou agrotóxicos?", "categoria" => "Zonas de Amortecimento e Barreiras de Proteção"],
    ["id" => 6, "item" => "Os equipamentos (tratores, pulverizadores) são de uso exclusivo ou sanitizados antes do uso?", "categoria" => "Zonas de Amortecimento e Barreiras de Proteção"],
    // Section3
    ["id" => 7, "item" => "Sementes e mudas utilizadas são orgânicas ou não tratadas com agrotóxicos sintéticos?", "categoria" => "Insumos, Sementes e Propagação"],
    ["id" => 8, "item" => "É garantida a ausência de Organismos Modificados Geneticamente (OGM / Transgênicos)?", "categoria" => "Insumos, Sementes e Propagação"],
    ["id" => 9, "item" => "Os adubos e fertilizantes utilizados estão na lista de insumos permitidos pela legislação?", "categoria" => "Insumos, Sementes e Propagação"],
    // Section4
    ["id" => 10, "item" => "Há prática de rotação/consorciação de culturas e cobertura de solo?", "categoria" => "Manejo do Solo e Fitossanitário"],
    ["id" => 11, "item" => "O controle de pragas/doenças utiliza apenas produtos biológicos ou caldas autorizadas?", "categoria" => "Manejo do Solo e Fitossanitário"],
    ["id" => 12, "item" => "O manejo de plantas espontâneas é mecânico, manual ou por cobertura morta (sem herbicidas)?", "categoria" => "Manejo do Solo e Fitossanitário"],
    // Section5 (optional)
    ["id" => 13, "item" => "A alimentação dos animais é 100% orgânica (ou dentro dos percentuais permitidos)?", "categoria" => "Manejo Animal (Se Aplicável)"],
    ["id" => 14, "item" => "O bem-estar animal é garantido (acesso a pasto, espaço adequado, iluminação natural)?", "categoria" => "Manejo Animal (Se Aplicável)"],
    ["id" => 15, "item" => "O uso de antibióticos ou alopáticos respeita o período de carência dobrado e limites anuais?", "categoria" => "Manejo Animal (Se Aplicável)"],
    // Section6
    ["id" => 16, "item" => "Produtos orgânicos são armazenados separadamente dos não orgânicos?", "categoria" => "Armazenamento, Transporte e Rastreabilidade"],
    ["id" => 17, "item" => "As embalagens de transporte são limpas, atóxicas e identificadas com rótulo orgânico?", "categoria" => "Armazenamento, Transporte e Rastreabilidade"],
    ["id" => 18, "item" => "O produtor mantém o Caderno de Campo atualizado (registros de plantio, insumos e colheita)?", "categoria" => "Armazenamento, Transporte e Rastreabilidade"]
];

// Critical items (those that cause automatic failure if not conform)
$itensCriticos = [2,5,8,9,11,12];

// Update roteiro id=1 with new checklist and critical items
$data = [
    'id' => 1,
    'nome' => "Checklist de Inspeção Orgânica - Vegetal (Completo)",
    'descricao' => "Roteiro completo para verificação de conformidade de produção vegetal orgânica, com todos os critérios de conformidade.",
    'checklist' => $checklist,
    'itens_criticos' => $itensCriticos
];

if ($manager->save($data)) {
    echo "Roteiro atualizado com sucesso!";
} else {
    echo "Erro ao atualizar roteiro.";
}

?>