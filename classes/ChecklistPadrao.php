<?php
/**
 * Checklist Padrão de Inspeção — Produção e Processamento Orgânico
 *
 * Verificações complementares baseadas no Regulamento Técnico da Produção
 * Orgânica (IN 05/2016 / IN 46/2011) e no modelo de Avaliação de Conformidade
 * adotado pelo SINPORG (SPG/OCS).
 *
 * Estes itens são SEMPRE mesclados ao checklist do roteiro na tela de
 * realização de inspeção, garantindo que produção vegetal e processamento
 * orgânico sejam cobertos mesmo quando o roteiro está incompleto.
 *
 * Estrutura de cada item:
 *   id         => int estável (>=1000, não colide com IDs de roteiros)
 *   categoria  => grupo de exibição
 *   item       => requisito/verificação
 *   obrigatorio=> true = item crítico (reprova automaticamente se NC)
 */
class ChecklistPadrao
{
    /**
     * @return array lista de itens padrão
     */
    public static function itens()
    {
        return [
            // ── Produção Vegetal / Geral (complemento ao roteiro) ──
            ['id' => 1001, 'categoria' => 'Produção Orgânica (Unidade de Produção)', 'item' => 'A colheita e o pós-colheita seguem boas práticas para manter a integridade orgânica do produto?', 'obrigatorio' => false],
            ['id' => 1002, 'categoria' => 'Produção Orgânica (Unidade de Produção)', 'item' => 'Os insumos orgânicos permitidos são armazenados separadamente dos proibidos?', 'obrigatorio' => false],
            ['id' => 1003, 'categoria' => 'Produção Orgânica (Unidade de Produção)', 'item' => 'Em caso de produção paralela (orgânica e não-orgânica), há separação clara de áreas, produtos e registros?', 'obrigatorio' => true],
            ['id' => 1004, 'categoria' => 'Produção Orgânica (Unidade de Produção)', 'item' => 'São empregadas práticas conservacionistas (rotação, adubação verde, compostagem, cobertura de solo)?', 'obrigatorio' => false],
            ['id' => 1005, 'categoria' => 'Produção Orgânica (Unidade de Produção)', 'item' => 'Os resíduos orgânicos da produção são destinados adequadamente (compostagem, reciclagem)?', 'obrigatorio' => false],

            // ── Processamento Orgânico ──
            ['id' => 1101, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'A unidade de processamento/beneficiamento está regularmente cadastrada e opera conforme o regulamento técnico da produção orgânica?', 'obrigatorio' => true],
            ['id' => 1102, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'Há separação física e temporal entre produtos orgânicos e não-orgânicos no recebimento, processamento e expedição?', 'obrigatorio' => true],
            ['id' => 1103, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'Os equipamentos e utensílios são de uso exclusivo orgânico ou higienizados/sanitizados entre lotes?', 'obrigatorio' => false],
            ['id' => 1104, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'A água utilizada no processo é potável e atende aos padrões sanitários exigidos?', 'obrigatorio' => true],
            ['id' => 1105, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'Os aditivos, ingredientes e coadjuvantes de tecnologia utilizados constam da lista permitida para processamento orgânico (IN 46/2011)?', 'obrigatorio' => true],
            ['id' => 1106, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'São adotadas Boas Práticas de Fabricação e o controle de pragas da unidade (PPHO) é documentado?', 'obrigatorio' => false],
            ['id' => 1107, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'Não são utilizados produtos irradiados nem organismos geneticamente modificados no processamento?', 'obrigatorio' => true],
            ['id' => 1108, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'A rotulagem dos produtos processados contém a identificação de "Produto Orgânico"/selo e a indicação do responsável (OCS/SPG)?', 'obrigatorio' => false],
            ['id' => 1109, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'Existe registro de rastreabilidade por lote (matéria-prima → processamento → expedição)?', 'obrigatorio' => true],
            ['id' => 1110, 'categoria' => 'Processamento Orgânico (Beneficiamento)', 'item' => 'Há responsável técnico e os manipuladores recebem treinamento em boas práticas?', 'obrigatorio' => false],

            // ── Documentação e OCS/SPG ──
            ['id' => 1201, 'categoria' => 'Documentação, Rastreabilidade e OCS/SPG', 'item' => 'O produtor mantém o Plano de Manejo Orgânico (PMO) atualizado e compatível com as práticas realizadas?', 'obrigatorio' => false],
            ['id' => 1202, 'categoria' => 'Documentação, Rastreabilidade e OCS/SPG', 'item' => 'A propriedade possui certificado/certidão de conformidade orgânica vigente (OCS/SPG)?', 'obrigatorio' => true],
            ['id' => 1203, 'categoria' => 'Documentação, Rastreabilidade e OCS/SPG', 'item' => 'O produtor mantém registros de estoque e comercialização dos produtos orgânicos?', 'obrigatorio' => false],
            ['id' => 1204, 'categoria' => 'Documentação, Rastreabilidade e OCS/SPG', 'item' => 'O produtor participa das atividades da OCS/SPG (reuniões e visitas de pares)?', 'obrigatorio' => false],

            // ── Produção Animal Orgânica (Portaria MAPA 52/2021, Título III) ──
            ['id' => 1301, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'Os animais possuem registro de origem/aquisição e o período de conversão orgânica foi respeitado conforme a espécie?', 'obrigatorio' => true],
            ['id' => 1302, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'A alimentação é predominantemente da própria unidade ou de outra unidade orgânica, sem OGM?', 'obrigatorio' => true],
            ['id' => 1303, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'Em escassez, o uso de alimentos não orgânicos está dentro dos limites e foi aprovado pela OCS no PMO (15% MS ruminantes / 20% MS não ruminantes)?', 'obrigatorio' => false],
            ['id' => 1304, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'Os animais têm acesso a áreas externas, ar livre, sombreamento/abrigo e as densidades máximas de criação são respeitadas?', 'obrigatorio' => true],
            ['id' => 1305, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'Não são utilizadas substâncias proibidas no manejo (promotores de crescimento, hormônios, antibióticos não autorizados)?', 'obrigatorio' => true],
            ['id' => 1306, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'Os tratamentos veterinários excepcionais estão registrados com período de carência dobrado (mínimo 96h), aprovação da OCS e limite de 2 tratamentos/ano por animal?', 'obrigatorio' => true],
            ['id' => 1307, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'O aleitamento mínimo previsto na norma por espécie (bovinos/bubalinos 90, ovinos/caprinos 28, suínos 45 dias) está sendo cumprido?', 'obrigatorio' => false],
            ['id' => 1308, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'As instalações e os sistemas de criação garantem bem-estar, higiene e prevenção de doenças (manejo sanitário preventivo)?', 'obrigatorio' => false],
            ['id' => 1309, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'Os produtos veterinários utilizados constam do Anexo II da Portaria 52/2021 (lista de sanitizantes e produtos permitidos)?', 'obrigatorio' => true],
            ['id' => 1310, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'A identificação individual dos animais permite a rastreabilidade (brinco/tag, registros de lote)?', 'obrigatorio' => false],
            ['id' => 1311, 'categoria' => 'Produção Animal (Portaria 52/2021)', 'item' => 'A produção apícola (se houver) respeita o manejo orgânico e a distância/contaminação por cultivos não orgânicos?', 'obrigatorio' => false],
        ];
    }

    /**
     * Retorna um mapa id => item para resolução de nomes (relatório/PDF).
     * @return array
     */
    public static function mapa()
    {
        $map = [];
        foreach (self::itens() as $item) {
            $map[$item['id']] = $item;
        }
        return $map;
    }

    /**
     * Mescla o checklist do roteiro com os itens padrão, evitando duplicatas
     * por texto normalizado. Itens padrão recebem marcação de crítico.
     *
     * @param array $roteiroChecklist
     * @return array lista mesclada
     */
    public static function mesclar($roteiroChecklist)
    {
        $roteiroChecklist = is_array($roteiroChecklist) ? $roteiroChecklist : [];

        $vistos = [];
        $norm = function ($t) {
            return mb_strtolower(preg_replace('/[^a-z0-9à-ú]/iu', '', trim($t)));
        };

        $mesclado = [];
        foreach ($roteiroChecklist as $item) {
            $key = $norm($item['item'] ?? $item['pergunta'] ?? '');
            $vistos[$key] = true;
            $mesclado[] = $item;
        }

        foreach (self::itens() as $item) {
            $key = $norm($item['item']);
            if (!$key || isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;
            $mesclado[] = $item;
        }

        return $mesclado;
    }
}