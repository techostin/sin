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
     * Gera um hashid único para uma nova visita
     */
    public function getNextHashid() {
        do {
            $hash = substr(md5(uniqid(mt_rand(), true)), 0, 16);
            $stmt = $this->db->prepare("SELECT id FROM visitas_tecnicas WHERE hashid = ?");
            $stmt->execute([$hash]);
        } while ($stmt->fetch());
        return $hash;
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
     * Busca uma visita detalhada por hashid
     */
    public function getByHashid($hashid) {
        $sql = "SELECT v.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome, 
                       p.propriedade as propriedade_nome, r.nome as roteiro_nome, r.checklist as checklist_template
                FROM visitas_tecnicas v
                JOIN associados a ON v.associado_id = a.id
                JOIN propriedades p ON v.propriedade_id = p.id
                JOIN roteiros_inspecao r ON v.roteiro_id = r.id
                WHERE v.hashid = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hashid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $row['resultado'] = json_decode($row['resultado'], true);
            $row['checklist_template'] = json_decode($row['checklist_template'], true);
        }
        
        return $row;
    }

    /**
     * Salva o resultado da visita e calcula pontuação
     */
    public function saveResult($id, $resultado, $observacoes, $assinaturas = [], $fotos = []) {
        // Get roteiro to retrieve critical items
        $stmt = $this->db->prepare("SELECT r.itens_criticos FROM visitas_tecnicas v 
                                  JOIN roteiros_inspecao r ON v.roteiro_id = r.id WHERE v.id = ?");
        $stmt->execute([$id]);
        $roteiro = $stmt->fetch(PDO::FETCH_ASSOC);
        $itensCriticos = $roteiro['itens_criticos'] ? json_decode($roteiro['itens_criticos'], true) : [];
        
        // Check for any critical items marked non-conform
        $hasCriticalNonConformity = false;
        foreach ($resultado as $itemId => $res) {
            $isNa = isset($res['na']) && ($res['na'] === true || $res['na'] === "true" || $res['na'] === "1");
            if (!$isNa && in_array($itemId, $itensCriticos) && (isset($res['conforme']) && !$res['conforme'])) {
                $hasCriticalNonConformity = true;
                break;
            }
        }
        
        // Calcular pontuação
        $score = $this->calculateScore($resultado);
        
        // Determinar conformidade (following rules from Avaliação_de_conformidade.md)
        $conformidade = 'Conforme';
        if ($hasCriticalNonConformity) {
            $conformidade = 'Não Conforme';
        } elseif ($score < 100) {
            $conformidade = 'Parcialmente Conforme';
        }

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE visitas_tecnicas SET 
                    resultado = ?, 
                    pontuacao = ?, 
                    conformidade = ?, 
                    observacoes = ?, 
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
                $conformidade,
                $observacoes,
                $assinaturas['produtor'] ?? null,
                $assinaturas['tecnico'] ?? null,
                is_array($fotos) ? implode(',', $fotos) : $fotos,
                $id
            ]);

            // Gerar Não-Conformidades
            $this->processNaoConformidades($id, $resultado);

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
                if (isset($res['conforme']) && ($res['conforme'] === true || $res['conforme'] === "true")) {
                    $conformes++;
                }
            }
        }
        
        return $total > 0 ? round(($conformes / $total) * 100) : 0;
    }

    /**
     * Processa itens não conformes e cria registros na tabela nao_conformidades
     */
    private function processNaoConformidades($visitaId, $resultado) {
        // Primeiro limpar NCs antigas desta visita (opcional)
        //$this->db->prepare("DELETE FROM nao_conformidades WHERE visita_tecnica_id = ?")->execute([$visitaId]);

        foreach ($resultado as $itemId => $res) {
            if (isset($res['conforme']) && ($res['conforme'] === false || $res['conforme'] === "false")) {
                // Verificar se já existe NC idêntica para evitar duplicidade num re-save
                $stmtCheck = $this->db->prepare("SELECT id FROM nao_conformidades WHERE visita_tecnica_id = ? AND item_checklist_id = ?");
                $stmtCheck->execute([$visitaId, $itemId]);
                
                if (!$stmtCheck->fetch()) {
                    $sql = "INSERT INTO nao_conformidades (visita_tecnica_id, item_checklist_id, descricao, gravidade, status) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $visitaId,
                        $itemId,
                        $res['observacao'] ?: 'Identificado na inspeção técnica.',
                        'Alta',
                        'Aberta'
                    ]);
                }
            }
        }
    }
}
