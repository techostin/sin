<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Certificadora {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM certificadoras ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar certificadoras: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM certificadoras WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar certificadora por ID: " . $e->getMessage());
            return false;
        }
    }

    public function findByGestor($gestorId) {
        try {
            if (empty($gestorId)) return null;
            $stmt = $this->db->prepare("SELECT * FROM certificadoras WHERE gestor_id = ? LIMIT 1");
            $stmt->execute([$gestorId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
            return null;
        } catch (PDOException $e) {
            error_log("Erro ao buscar certificadora por gestor: " . $e->getMessage());
            return null;
        }
    }

    public function findByNamePartial($name) {
        try {
            if (empty($name)) return null;
            $stmt = $this->db->prepare("SELECT * FROM certificadoras WHERE nome LIKE ? LIMIT 1");
            $stmt->execute(['%'. $name .'%']);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar certificadora por nome: " . $e->getMessage());
            return null;
        }
    }

    public function create($dados) {
        try {
            $sql = "INSERT INTO certificadoras (nome, cnpj, tipo, registro_mapa, estado, logo_url, cidade, uf, telefone, email, site, contato, logo_filename, gestor_id, dados_adicionais, created_at, updated_at)
                    VALUES (:nome, :cnpj, :tipo, :registro_mapa, :estado, :logo_url, :cidade, :uf, :telefone, :email, :site, :contato, :logo_filename, :gestor_id, :dados_adicionais, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindValue(':cnpj', $dados['cnpj'] ?? null);
            $stmt->bindValue(':tipo', $dados['tipo'] ?? 'SPG');
            $stmt->bindValue(':registro_mapa', $dados['registro_mapa'] ?? null);
            $stmt->bindValue(':estado', $dados['estado'] ?? 'RS');
            $stmt->bindValue(':logo_url', $dados['logo_url'] ?? null);
            $stmt->bindValue(':cidade', $dados['cidade'] ?? null);
            $stmt->bindValue(':uf', $dados['uf'] ?? null);
            $stmt->bindValue(':telefone', $dados['telefone'] ?? null);
            $stmt->bindValue(':email', $dados['email'] ?? null);
            $stmt->bindValue(':site', $dados['site'] ?? null);
            $stmt->bindValue(':contato', $dados['contato'] ?? null);
            $stmt->bindValue(':logo_filename', $dados['logo_filename'] ?? null);
            $stmt->bindValue(':gestor_id', $dados['gestor_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':dados_adicionais', isset($dados['dados_adicionais']) ? json_encode($dados['dados_adicionais']) : null);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar certificadora: " . $e->getMessage());
            return false;
        }
    }

    public function update($dados) {
        try {
            $sql = "UPDATE certificadoras SET 
                        nome = :nome, 
                        cnpj = :cnpj, 
                        tipo = :tipo, 
                        registro_mapa = :registro_mapa, 
                        estado = :estado,
                        cidade = :cidade,
                        uf = :uf,
                        telefone = :telefone,
                        email = :email,
                        site = :site,
                        contato = :contato";
            
            // Só atualiza a logo se foi enviada
            if (isset($dados['logo_url'])) {
                $sql .= ", logo_url = :logo_url";
            }
            if (isset($dados['logo_filename'])) {
                $sql .= ", logo_filename = :logo_filename";
            }
            
            $sql .= " WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindValue(':cnpj', $dados['cnpj'] ?? null);
            $stmt->bindValue(':tipo', $dados['tipo'] ?? 'SPG');
            $stmt->bindValue(':registro_mapa', $dados['registro_mapa'] ?? null);
            $stmt->bindValue(':estado', $dados['estado'] ?? 'RS');
            $stmt->bindValue(':cidade', $dados['cidade'] ?? null);
            $stmt->bindValue(':uf', $dados['uf'] ?? null);
            $stmt->bindValue(':telefone', $dados['telefone'] ?? null);
            $stmt->bindValue(':email', $dados['email'] ?? null);
            $stmt->bindValue(':site', $dados['site'] ?? null);
            $stmt->bindValue(':contato', $dados['contato'] ?? null);
            
            if (isset($dados['logo_url'])) {
                $stmt->bindValue(':logo_url', $dados['logo_url']);
            }
            if (isset($dados['logo_filename'])) {
                $stmt->bindValue(':logo_filename', $dados['logo_filename']);
            }
            
            $stmt->bindParam(':id', $dados['id'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar certificadora: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM certificadoras WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir certificadora: " . $e->getMessage());
            return false;
        }
    }
}
