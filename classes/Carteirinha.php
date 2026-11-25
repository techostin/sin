<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Carteirinha
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByAssociado($associadoId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM carteirinhas WHERE associado_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([(int) $associadoId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar carteirinha: " . $e->getMessage());
            return false;
        }
    }

    public function getByToken($token)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT c.*, CONCAT(a.nome, ' ', a.sobrenome) AS associado_nome,
                        a.foto_url, a.data_nascimento, a.tipo_associado, a.status_associado,
                        a.registro_cnpo, a.cpf
                 FROM carteirinhas c
                 INNER JOIN associados a ON a.id = c.associado_id
                 WHERE c.token = ?"
            );
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar carteirinha por token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gera (ou renova) a carteirinha digital de um associado.
     */
    public function gerar($associadoId, $validade = null)
    {
        try {
            $associadoId = (int) $associadoId;
            $validade = $validade ?: date('Y-m-d', strtotime('+1 year'));
            $token = bin2hex(random_bytes(24));

            $atual = $this->getByAssociado($associadoId);
            if ($atual) {
                $stmt = $this->db->prepare("UPDATE carteirinhas SET token = ?, validade = ?, gerada_em = NOW() WHERE id = ?");
                $stmt->execute([$token, $validade, (int) $atual['id']]);
                $id = (int) $atual['id'];
            } else {
                $stmt = $this->db->prepare("INSERT INTO carteirinhas (associado_id, token, validade) VALUES (?, ?, ?)");
                $stmt->execute([$associadoId, $token, $validade]);
                $id = (int) $this->db->lastInsertId();
            }

            return ['sucesso' => true, 'id' => $id, 'token' => $token, 'validade' => $validade];
        } catch (PDOException $e) {
            error_log("Erro ao gerar carteirinha: " . $e->getMessage());
            return ['erro' => 'Erro ao gerar carteirinha.'];
        }
    }

    public function validaToken($token)
    {
        $carteirinha = $this->getByToken($token);
        if (!$carteirinha) {
            return ['valida' => false, 'motivo' => 'Carteirinha não encontrada.'];
        }
        if (strtotime($carteirinha['validade']) < strtotime(date('Y-m-d'))) {
            return ['valida' => false, 'motivo' => 'Carteirinha vencida.', 'carteirinha' => $carteirinha];
        }
        if (($carteirinha['status_associado'] ?? 'Ativo') === 'Desligado' || ($carteirinha['status_associado'] ?? '') === 'Falecido') {
            return ['valida' => false, 'motivo' => 'Associado sem vínculo ativo.', 'carteirinha' => $carteirinha];
        }
        return ['valida' => true, 'carteirinha' => $carteirinha];
    }
}