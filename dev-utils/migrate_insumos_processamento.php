<?php
/**
 * Migração idempotente: Insumos de Processamento (Agroindústria)
 *
 * Estende a tabela insumos_organicos_aprovados com as categorias de
 * processamento de alimentos orgânicos:
 *   - 'Aditivo Alimentar'           (Anexo III - IN Conjunta 18/2009 + IN 24/2011)
 *   - 'Coadjuvante de Tecnologia'   (Anexo III - IN Conjunta 18/2009 + IN 24/2011)
 *
 * Rode: php dev-utils/migrate_insumos_processamento.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

$categorias = "ENUM('Fertilizante','Defensivo','Adjuvante','Sementes','Mudas','Outros','Alimentação Animal','Suplemento Mineral','Aditivo Tecnológico','Sanidade Animal','Higienização','Apicultura','Aditivo Alimentar','Coadjuvante de Tecnologia')";

echo "=== Estendendo ENUM de categorias ===\n";
$db->exec("ALTER TABLE insumos_organicos_aprovados MODIFY COLUMN categoria $categorias NOT NULL");
echo "ENUM atualizado.\n\n";

echo "=== Removendo registros antigos das categorias de processamento ===\n";
$stmt = $db->prepare("DELETE FROM insumos_organicos_aprovados WHERE categoria IN ('Aditivo Alimentar','Coadjuvante de Tecnologia')");
$stmt->execute();
echo "Removidos: " . $stmt->rowCount() . " registros.\n\n";

$insumos = [
    // ---- ADITIVOS ALIMENTARES (IN 18/2009) ----
    ['Ácido algínico', 'Aditivo Alimentar', 'Espessante, estabilizante e gelificante', 'Aditivo', null, 'IN 18/2009', 'INS 400'],
    ['Ácido ascórbico (L-)', 'Aditivo Alimentar', 'Antioxidante e regulador de acidez', 'Aditivo', null, 'IN 18/2009', 'INS 300'],
    ['Ácido cítrico', 'Aditivo Alimentar', 'Acidulante e regulador de acidez', 'Aditivo', null, 'IN 18/2009', 'INS 330; ação antioxidante sinérgica'],
    ['Ácido láctico (L-, D- e DL-)', 'Aditivo Alimentar', 'Acidulante e regulador de acidez', 'Aditivo', null, 'IN 18/2009', 'INS 270'],
    ['Ácido tartárico (L(+)-)', 'Aditivo Alimentar', 'Acidulante', 'Aditivo', 'Somente para vinhos, com limite máximo de 0,15 g/100 mL', 'IN 18/2009', 'INS 334'],
    ['Ágar', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', null, 'IN 18/2009', 'INS 406'],
    ['Alginato de sódio', 'Aditivo Alimentar', 'Espessante, estabilizante e gelificante', 'Aditivo', null, 'IN 18/2009', 'INS 401'],
    ['Aromatizantes', 'Aditivo Alimentar', 'Aromatizante', 'Aditivo', 'Somente os naturais', 'IN 18/2009', null],
    ['Carbonato de amônio', 'Aditivo Alimentar', 'Regulador de acidez e fermento', 'Aditivo', null, 'IN 18/2009', 'INS 503i'],
    ['Carbonato de cálcio', 'Aditivo Alimentar', 'Regulador de acidez e antiaglomerante', 'Aditivo', null, 'IN 18/2009', 'INS 170i'],
    ['Carbonato de magnésio', 'Aditivo Alimentar', 'Antiaglomerante e regulador de acidez', 'Aditivo', null, 'IN 18/2009', 'INS 504i; inclui carbonato básico de magnésio'],
    ['Carbonato de potássio', 'Aditivo Alimentar', 'Regulador de acidez', 'Aditivo', null, 'IN 18/2009', 'INS 501i'],
    ['Carbonato de sódio', 'Aditivo Alimentar', 'Regulador de acidez', 'Aditivo', null, 'IN 18/2009', 'INS 500i'],
    ['Carragena', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', null, 'IN 18/2009', 'INS 407; inclui furcelarana, sais de Na e K e musgo irlandês'],
    ['Cera de abelha', 'Aditivo Alimentar', 'Agente de revestimento e glaceante', 'Aditivo', null, 'IN 18/2009', 'INS 901; branca e amarela'],
    ['Citrato trissódico', 'Aditivo Alimentar', 'Regulador de acidez, sequestrante e emulsificante', 'Aditivo', null, 'IN 18/2009', 'INS 331iii; citrato de sódio'],
    ['Cloreto de cálcio', 'Aditivo Alimentar', 'Firmador e sequestrante', 'Aditivo', null, 'IN 18/2009', 'INS 509'],
    ['Cloreto de magnésio', 'Aditivo Alimentar', 'Firmador', 'Aditivo', null, 'IN 18/2009', 'INS 511'],
    ['Cloreto de potássio', 'Aditivo Alimentar', 'Gelificante e substituto de sal', 'Aditivo', null, 'IN 18/2009', 'INS 508'],
    ['Corantes', 'Aditivo Alimentar', 'Corante', 'Aditivo', 'Somente os naturais (não sintéticos)', 'IN 18/2009', null],
    ['Dióxido de carbono', 'Aditivo Alimentar', 'Gás de embalagem, acidulante e propulsor', 'Aditivo', null, 'IN 18/2009', 'INS 290'],
    ['Dióxido de enxofre', 'Aditivo Alimentar', 'Conservante e antioxidante', 'Aditivo', 'Somente para vinhos, com limite máximo de 0,01 g/100 g', 'IN 18/2009', 'INS 220; anidrido sulfuroso'],
    ['Dióxido de silício', 'Aditivo Alimentar', 'Antiumectante e antiaglomerante', 'Aditivo', null, 'IN 18/2009', 'INS 551; sílica'],
    ['Edulcorantes', 'Aditivo Alimentar', 'Edulcorante', 'Aditivo', 'Somente os naturais (não sintéticos)', 'IN 18/2009', null],
    ['Gelatina', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', null, 'IN 18/2009', 'INS 428'],
    ['Goma arábica', 'Aditivo Alimentar', 'Espessante, estabilizante e emulsificante', 'Aditivo', null, 'IN 18/2009', 'INS 414; goma acácia'],
    ['Goma guar', 'Aditivo Alimentar', 'Espessante e estabilizante', 'Aditivo', null, 'IN 18/2009', 'INS 412'],
    ['Goma caroba', 'Aditivo Alimentar', 'Espessante, gelificante e estabilizante', 'Aditivo', null, 'IN 18/2009', 'INS 410; garrofina, alfarroba, jataí'],
    ['Goma xantana', 'Aditivo Alimentar', 'Espessante e estabilizante', 'Aditivo', null, 'IN 18/2009', 'INS 415'],
    ['Hidróxido de cálcio', 'Aditivo Alimentar', 'Regulador de acidez e firmador', 'Aditivo', null, 'IN 18/2009', 'INS 526'],
    // ---- ADITIVOS ALIMENTARES acrescidos pela IN 24/2011 ----
    ['Ácido málico (D-, L-)', 'Aditivo Alimentar', 'Regulador de acidez', 'Aditivo', 'Regulador de acidez para produtos de origem vegetal, exceto vinagres e fermentados acéticos', 'IN 24/2011', 'INS 296'],
    ['Alginato de potássio', 'Aditivo Alimentar', 'Espessante e estabilizante', 'Aditivo', null, 'IN 24/2011', 'INS 402'],
    ['Bicarbonato de sódio', 'Aditivo Alimentar', 'Fermento químico e regulador de acidez', 'Aditivo', 'Para farinha de trigo com adição de aditivos, o limite máximo é 4,5 g/100 g', 'IN 24/2011', 'INS 500ii; carbonato ácido de sódio'],
    ['Bicarbonato de amônio', 'Aditivo Alimentar', 'Fermento químico', 'Aditivo', null, 'IN 24/2011', 'INS 503ii; carbonato ácido de amônio'],
    // ---- COADJUVANTES DE TECNOLOGIA (IN 18/2009) ----
    ['Ácido tartárico', 'Coadjuvante de Tecnologia', 'Acidulante e regulador de acidez', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Albumina de ovo', 'Coadjuvante de Tecnologia', 'Clarificante e emulsificante', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Álcool etílico', 'Coadjuvante de Tecnologia', 'Solvente e extrator', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Bentonita', 'Coadjuvante de Tecnologia', 'Clarificante e agente de filtração', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Caolin', 'Coadjuvante de Tecnologia', 'Antiaglomerante e agente de filtração', 'Coadjuvante', null, 'IN 18/2009', 'Caulim'],
    ['Cera de carnaúba', 'Coadjuvante de Tecnologia', 'Agente de revestimento', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Culturas de micro-organismos', 'Coadjuvante de Tecnologia', 'Cultura fermentativa', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Ictiocola', 'Coadjuvante de Tecnologia', 'Clarificante e agente de colagem', 'Coadjuvante', null, 'IN 18/2009', 'Cola de peixe'],
    ['Nitrogênio', 'Coadjuvante de Tecnologia', 'Gás de embalagem', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Oxigênio', 'Coadjuvante de Tecnologia', 'Gás de embalagem', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Perlita', 'Coadjuvante de Tecnologia', 'Agente de filtração', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Terra diatomácea', 'Coadjuvante de Tecnologia', 'Agente de filtração', 'Coadjuvante', null, 'IN 18/2009', null],
    ['Hidróxido de sódio', 'Coadjuvante de Tecnologia', 'Regulador de acidez e catalisador', 'Coadjuvante', null, 'IN 18/2009', 'INS 524'],
    ['Lecitinas', 'Coadjuvante de Tecnologia', 'Emulsificante e estabilizante', 'Coadjuvante', null, 'IN 18/2009', 'INS 322'],
    ['Pectina', 'Coadjuvante de Tecnologia', 'Gelificante, espessante e estabilizante', 'Coadjuvante', null, 'IN 18/2009', 'INS 440; pectina amidada'],
    ['Sulfato de cálcio', 'Coadjuvante de Tecnologia', 'Firmador e coagulante', 'Coadjuvante', null, 'IN 18/2009', 'INS 516'],
    ['Tartarato dipotássico', 'Coadjuvante de Tecnologia', 'Estabilizante', 'Coadjuvante', 'Somente para produtos de panificação, com limite máximo de 0,5 g/100 g (expresso como ácido tartárico)', 'IN 18/2009', 'INS 336ii; tartarato de potássio'],
    ['Enzimas', 'Coadjuvante de Tecnologia', 'Enzima (coadjuvante de tecnologia)', 'Coadjuvante', 'Permitidas desde que atendam à legislação vigente aplicável (art. 10 da IN 18/2009)', 'IN 18/2009', 'Sem lista específica no anexo; uso conforme Boas Práticas de Fabricação'],
    // ---- COADJUVANTES acrescidos pela IN 24/2011 ----
    ['Taninos (ácido tânico)', 'Coadjuvante de Tecnologia', 'Agente de floculação', 'Coadjuvante', 'Somente aqueles autorizados para uso em alimentos em regulamentos específicos publicados pelo órgão competente da Saúde', 'IN 24/2011', 'Tratamento de caldo de cana'],
    ['Ácido sulfúrico', 'Coadjuvante de Tecnologia', 'Controle de micro-organismos', 'Coadjuvante', 'Usado na produção de bebida alcoólica (controle de leveduras)', 'IN 24/2011', null],
];

echo "=== Inserindo insumos de processamento ===\n";
$sql = "INSERT INTO insumos_organicos_aprovados
        (nome, categoria, tipo, subcategoria, permitido, restricoes, normativa, observacoes, fonte_referencia)
        VALUES (?, ?, ?, ?, 1, ?, ?, ?, 'Anexo III (Processamento)')";
$stmt = $db->prepare($sql);

$total = 0;
foreach ($insumos as [$nome, $categoria, $tipo, $sub, $rest, $norm, $obs]) {
    $stmt->execute([$nome, $categoria, $tipo, $sub, $rest, $norm, $obs]);
    $total++;
}
echo "Inseridos: $total insumos.\n";

$count = $db->query("SELECT COUNT(*) FROM insumos_organicos_aprovados WHERE categoria IN ('Aditivo Alimentar','Coadjuvante de Tecnologia') AND permitido = 1")->fetchColumn();
echo "\nTotal na base (categorias de processamento, permitidos): $count\n";
echo "Migração concluída.\n";
