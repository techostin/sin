<?php
/**
 * Classe para gerenciar templates de Roteiros de Inspeção
 */
require_once __DIR__ . '/Database.php';

class RoteiroInspecao {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todos os roteiros ativos
     */
    public function getAll($ativo = true) {
        $sql = "SELECT * FROM roteiros_inspecao";
        if ($ativo) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['checklist'] = json_decode($row['checklist'], true);
            if (!empty($row['itens_criticos'])) {
                $row['itens_criticos'] = json_decode($row['itens_criticos'], true);
            }
        }
        
        return $results;
    }

    /**
     * Busca um roteiro pelo ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM roteiros_inspecao WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $row['checklist'] = json_decode($row['checklist'], true);
            if (!empty($row['itens_criticos'])) {
                $row['itens_criticos'] = json_decode($row['itens_criticos'], true);
            }
        }
        
        return $row;
    }

    /**
     * Salva ou atualiza um roteiro
     */
    public function save($data) {
        if (isset($data['id']) && !empty($data['id'])) {
            return $this->update($data);
        }
        return $this->create($data);
    }

    private function create($data) {
        $sql = "INSERT INTO roteiros_inspecao (nome, codigo, tipo, descricao, objetivo, checklist, itens_criticos, pontuacao_minima) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['nome'],
            $data['codigo'] ?? null,
            $data['tipo'] ?? 'Visita Regular',
            $data['descricao'] ?? null,
            $data['objetivo'] ?? null,
            is_array($data['checklist']) ? json_encode($data['checklist']) : $data['checklist'],
            isset($data['itens_criticos']) ? (is_array($data['itens_criticos']) ? json_encode($data['itens_criticos']) : $data['itens_criticos']) : null,
            $data['pontuacao_minima'] ?? 70
        ]);
    }

    private function update($data) {
        $sql = "UPDATE roteiros_inspecao SET 
                nome = ?, codigo = ?, tipo = ?, descricao = ?, objetivo = ?, 
                checklist = ?, itens_criticos = ?, pontuacao_minima = ?, ativo = ? 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['nome'],
            $data['codigo'] ?? null,
            $data['tipo'] ?? 'Visita Regular',
            $data['descricao'] ?? null,
            $data['objetivo'] ?? null,
            is_array($data['checklist']) ? json_encode($data['checklist']) : $data['checklist'],
            isset($data['itens_criticos']) ? (is_array($data['itens_criticos']) ? json_encode($data['itens_criticos']) : $data['itens_criticos']) : null,
            $data['pontuacao_minima'] ?? 70,
            $data['ativo'] ?? 1,
            $data['id']
        ]);
    }
}
