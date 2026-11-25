<?php
/**
 * Classe para gerenciar Visitas Técnicas e Inspeções
 */
require_once __DIR__ . '/Database.php';

class VisitaTecnica {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca uma visita detalhada por ID
     */
    public function getById($id) {
        $sql = "SELECT v.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome, 
                       p.propriedade as propriedade_nome, r.nome as roteiro_nome, r.checklist as checklist_template
                FROM visitas_tecnicas v
                JOIN associados a ON v.associado_id = a.id
                JOIN propriedades p ON v.propriedade_id = p.id
                JOIN roteiros_inspecao r ON v.roteiro_id = r.id
                WHERE v.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $row['resultado'] = json_decode($row['resultado'], true);
            $row['checklist_template'] = json_decode($row['checklist_template'], true);
        }
        
        return $row;
    }

    /**
     * Salva o resultado da visita e calcula pontuação
     *
     * @param int   $id
     * @param array $resultado
     * @param string|null $observacoes
     * @param array $assinaturas ['produtor' => ..., 'tecnico' => ...]
     * @param array $fotos Lista de URLs
     * @param array $opts  Chaves opcionais: hora_inicio, hora_fim, condicoes_clima,
     *                     pontuacao_minima, checklist (mapa id => ['obrigatorio'=>bool,'categoria'=>string])
     */
    public function saveResult($id, $resultado, $observacoes, $assinaturas = [], $fotos = [], $opts = []) {
        if (!is_array($resultado)) {
            $resultado = [];
        }

        // ----- Regra de itens críticos: reprovam automaticamente -----
        $itensCriticos = [];
        if (!empty($opts['checklist']) && is_array($opts['checklist'])) {
            foreach ($opts['checklist'] as $itemId => $info) {
                if (!empty($info['obrigatorio'])) {
                    $itensCriticos[] = strval($itemId);
                }
            }
        }

        // Fallback: ler do roteiro se o mapa não foi informado
        if (!$itensCriticos) {
            $stmt = $this->db->prepare("SELECT r.itens_criticos FROM visitas_tecnicas v
                                      JOIN roteiros_inspecao r ON v.roteiro_id = r.id WHERE v.id = ?");
            $stmt->execute([$id]);
            $roteiro = $stmt->fetch(PDO::FETCH_ASSOC);
            $itensCriticos = array_map('strval', json_decode($roteiro['itens_criticos'] ?: '[]', true) ?: []);
        }
        $itensCriticos = array_map('strval', $itensCriticos);

        $hasCriticalNonConformity = false;
        $semResposta = [];
        foreach ($resultado as $itemId => $res) {
            $itemId = strval($itemId);
            $isNa = isset($res['na']) && ($res['na'] === true || $res['na'] === "true" || $res['na'] === "1");
            if ($isNa) continue;
            if (!isset($res['conforme'])) {
                // Item respondido mas sem campo validação → ignorar com ressalva
                continue;
            }
            $conforme = ($res['conforme'] === true || $res['conforme'] === "true" || $res['conforme'] === "1");
            if ($conforme) continue;

            if (in_array($itemId, $itensCriticos, true)) {
                $hasCriticalNonConformity = true;
            }
        }
        
        // Calcular pontuação
        $score = $this->calculateScore($resultado);
        $maxScore = count(array_filter($resultado, function ($res) {
            return !(isset($res['na']) && ($res['na'] === true || $res['na'] === "true" || $res['na'] === "1"));
        }));

        // Determinar conformidade:
        //  - Crítico NC jamais aprova
        //  - Score 100 → Conforme
        //  - Abaixo de 100 → Parcialmente Conforme (salvo NC crítica)
        //  - Abaixo da pontuação mínima do roteiro → Não Conforme
        $pontuacaoMinima = isset($opts['pontuacao_minima']) ? (int)$opts['pontuacao_minima'] : 70;
        $conformidade = 'Conforme';
        if ($score >= 100) {
            $conformidade = 'Conforme';
        } elseif ($score >= $pontuacaoMinima) {
            $conformidade = 'Parcialmente Conforme';
        } else {
            $conformidade = 'Não Conforme';
        }
        if ($hasCriticalNonConformity) {
            $conformidade = 'Não Conforme';
        }

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE visitas_tecnicas SET 
                    resultado = ?, 
                    pontuacao = ?, 
                    pontuacao_maxima = ?, 
                    conformidade = ?, 
                    observacoes = ?, 
                    hora_inicio = ?,
                    hora_fim = ?,
                    condicoes_clima = ?,
                    assinatura_produtor_url = ?, 
                    assinatura_tecnico_url = ?, 
                    evidencias_fotos = ?,
                    status = 'Realizada',
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                json_encode($resultado),
                $score,
                max(0, (int)$maxScore),
                $conformidade,
                $observacoes,
                $opts['hora_inicio'] ?? null,
                $opts['hora_fim'] ?? null,
                $opts['condicoes_clima'] ?? null,
                $assinaturas['produtor'] ?? null,
                $assinaturas['tecnico'] ?? null,
                is_array($fotos) ? implode(',', $fotos) : $fotos,
                $id
            ]);

            // Gerar Não-Conformidades
            $this->processNaoConformidades($id, $resultado, $opts['checklist'] ?? []);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Calcula a pontuação percentual (0-100)
     */
    private function calculateScore($resultado) {
        if (empty($resultado)) return 0;
        
        $total = 0;
        $conformes = 0;
        
        foreach ($resultado as $res) {
            $isNa = isset($res['na']) && ($res['na'] === true || $res['na'] === "true" || $res['na'] === "1");
            if (!$isNa) {
                $total++;
                if (isset($res['conforme']) && ($res['conforme'] === true || $res['conforme'] === "true" || $res['conforme'] === "1")) {
                    $conformes++;
                } else {
                    // Item respondido explicitamente como NC também conta como avaliado
                }
            }
        }
        
        return $total > 0 ? round(($conformes / $total) * 100) : 0;
    }

    /**
     * Processa itens não conformes e cria registros na tabela nao_conformidades
     */
    private function processNaoConformidades($visitaId, $resultado, $checklistMap = []) {
        foreach ($resultado as $itemId => $res) {
            $itemId = strval($itemId);
            $isNa = isset($res['na']) && ($res['na'] === true || $res['na'] === "true" || $res['na'] === "1");
            if ($isNa) continue;
            if (isset($res['conforme']) && ($res['conforme'] === true || $res['conforme'] === "true" || $res['conforme'] === "1")) continue;

            // Categoria e gravidade conforme o mapa do checklist
            $info = $checklistMap[$itemId] ?? [];
            $categoria = $info['categoria'] ?? 'Geral';
            $gravidade = !empty($info['obrigatorio']) ? 'Crítica' : 'Alta';

            // Verificar se já existe NC idêntica para evitar duplicidade num re-save
            $stmtCheck = $this->db->prepare("SELECT id FROM nao_conformidades WHERE visita_tecnica_id = ? AND item_checklist_id = ?");
            $stmtCheck->execute([$visitaId, $itemId]);

            if (!$stmtCheck->fetch()) {
                $descricao = trim(strip_tags($res['observacao'] ?? ''));
                if ($descricao === '') {
                    $descricao = 'Identificado na inspeção técnica.';
                }
                $sql = "INSERT INTO nao_conformidades (visita_tecnica_id, item_checklist_id, descricao, gravidade, categoria, status) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $visitaId,
                    $itemId,
                    $descricao,
                    $gravidade,
                    $categoria,
                    'Aberta'
                ]);
            }
        }
    }
}
