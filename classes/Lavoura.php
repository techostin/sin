<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Lavoura {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todas as lavouras do banco de dados.
     * @return array Retorna um array de lavouras.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT l.*, p.propriedade as propriedade_nome FROM lavouras l LEFT JOIN propriedades p ON l.propriedade_id = p.id ORDER BY l.nome ASC");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Garantir que todos os campos existam para evitar warnings
            foreach ($result as &$row) {
                $row['id'] = $row['id'] ?? '';
                $row['uuid'] = $row['uuid'] ?? '';
                $row['nome'] = $row['nome'] ?? '';
                $row['tipo_cultura'] = $row['tipo_cultura'] ?? '';
                $row['area'] = $row['area'] ?? '0.00';
                $row['status'] = $row['status'] ?? 'planejada';
                $row['propriedade_nome'] = $row['propriedade_nome'] ?? 'N/A';
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Erro ao buscar lavouras: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca todas as lavouras de um determinado associado através das propriedades.
     * @param int $associado_id
     * @return array
     */
    public function getByAssociadoId($associado_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT l.*, p.propriedade as propriedade_nome 
                FROM lavouras l 
                INNER JOIN propriedades p ON l.propriedade_id = p.id 
                WHERE p.associado_id = :associado_id
                ORDER BY l.nome ASC
            ");
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($result as &$row) {
                $row['id'] = $row['id'] ?? '';
                $row['uuid'] = $row['uuid'] ?? '';
                $row['nome'] = $row['nome'] ?? '';
                $row['tipo_cultura'] = $row['tipo_cultura'] ?? '';
                $row['area'] = $row['area'] ?? '0.00';
                $row['status'] = $row['status'] ?? 'planejada';
                $row['propriedade_nome'] = $row['propriedade_nome'] ?? 'N/A';
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Erro ao buscar lavouras por associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca uma lavoura pelo UUID.
     * @param string $uuid O UUID da lavoura.
     * @return array|false Retorna os dados da lavoura ou false se não encontrada.
     */
    public function getByUuid($uuid) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM lavouras WHERE uuid = :uuid");
            $stmt->bindParam(':uuid', $uuid);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lavoura por UUID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria uma nova lavoura.
     * @param array $dados Os dados da lavoura.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            // Gerar UUID se não foi fornecido
            if (empty($dados_sanitizados['uuid'])) {
                $dados_sanitizados['uuid'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }

            // Verificar se todos os campos obrigatórios estão presentes
            $campos_obrigatorios = ['nome', 'propriedade_id', 'cultura', 'area', 'data_plantio'];
            foreach ($campos_obrigatorios as $campo) {
                if (empty($dados_sanitizados[$campo])) {
                    return "O campo '$campo' é obrigatório.";
                }
            }
            
            $sql = "INSERT INTO lavouras (uuid, nome, propriedade_id, tipo_cultura, area, data_plantio, status, data_colheita_prevista, observacoes) 
                    VALUES (:uuid, :nome, :propriedade_id, :cultura, :area, :data_plantio, :status, :data_colheita_prevista, :observacoes)";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':uuid', $dados_sanitizados['uuid']);
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':propriedade_id', $dados_sanitizados['propriedade_id']);
            $stmt->bindParam(':cultura', $dados_sanitizados['cultura']);
            $stmt->bindParam(':area', $dados_sanitizados['area']);
            $stmt->bindParam(':data_plantio', $dados_sanitizados['data_plantio']);
            $stmt->bindParam(':status', $dados_sanitizados['status']);
            $stmt->bindParam(':data_colheita_prevista', $dados_sanitizados['data_colheita_prevista']);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);

            $result = $stmt->execute();
            return $result;
        } catch (PDOException $e) {
            error_log("Erro ao criar lavoura: " . $e->getMessage());
            return "Erro ao cadastrar lavoura: " . $e->getMessage();
        }
    }

    /**
     * Busca uma lavoura pelo ID.
     * @param int $id O ID da lavoura.
     * @return array|false Retorna os dados da lavoura ou false se não encontrada.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM lavouras WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lavoura por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza os dados de uma lavoura existente.
     * @param array $dados Os dados atualizados da lavoura, incluindo o ID.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            // Verificar se o ID foi fornecido
            if (empty($dados_sanitizados['id'])) {
                error_log("Erro ao atualizar lavoura: ID não fornecido");
                return false;
            }
            
            // Verificar se a lavoura existe
            $lavoura = $this->getById($dados_sanitizados['id']);
            if (!$lavoura) {
                error_log("Erro ao atualizar lavoura: Lavoura não encontrada com o ID " . $dados_sanitizados['id']);
                return false;
            }
            
            // Construir a query de atualização dinamicamente com base nos campos fornecidos
            $campos = [];
            $valores = [':id' => $dados_sanitizados['id']];
            
            // Mapear os campos do formulário para os nomes das colunas no banco de dados
            $mapeamento = [
                'propriedade_id' => 'propriedade_id',
                'cultura' => 'tipo_cultura',
                'area' => 'area',
                'data_plantio' => 'data_plantio',
                'data_colheita_prevista' => 'data_colheita_prevista',
                'status' => 'status',
                'observacoes' => 'observacoes'
            ];
            
            // Adicionar apenas os campos que foram fornecidos e existem no mapeamento
            foreach ($mapeamento as $formField => $dbField) {
                if (array_key_exists($formField, $dados_sanitizados)) {
                    $campos[] = "$dbField = :$formField";
                    $valores[":$formField"] = $dados_sanitizados[$formField];
                }
            }
            
            if (empty($campos)) {
                error_log("Nenhum campo válido para atualização");
                return false;
            }
            
            $sql = "UPDATE lavouras SET " . implode(", ", $campos) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            // Vincular os valores
            foreach ($valores as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $result = $stmt->execute();
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("Erro ao executar atualização: " . print_r($errorInfo, true));
                return false;
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Erro ao atualizar lavoura: " . $e->getMessage());
            return false;
        }
    }
}