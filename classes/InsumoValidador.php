<?php
require_once __DIR__ . '/Database.php';

class InsumoValidador {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca aproximada na lista oficial de aprovados (Portaria 52/2021).
     * @param string $nome Nome ou nome comercial do insumo.
     * @return array|false Registro encontrado ou false.
     */
    public function buscarAprovado($nome) {
        $stmt = $this->db->prepare("SELECT * FROM insumos_organicos_aprovados
                                    WHERE nome LIKE ? OR nome_comercial LIKE ?
                                    LIMIT 1");
        $like = "%" . trim($nome) . "%";
        $stmt->execute([$like, $like]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Avalia a conformidade de um insumo contra a lista oficial.
     * @param string $nome
     * @return array ['permitido'=>bool, 'status'=>string, 'message'=>string, 'insumo'=>array|false]
     */
    public function avaliar($nome) {
        $nome = trim((string)$nome);
        if ($nome === '') {
            return [
                'permitido' => false,
                'status' => 'error',
                'message' => 'Nome do insumo não fornecido.',
                'insumo' => false
            ];
        }

        $insumo = $this->buscarAprovado($nome);
        if ($insumo && !empty($insumo['permitido'])) {
            return [
                'permitido' => true,
                'status' => 'success',
                'message' => 'Insumo presente na lista de permitidos pela Portaria 52/2021.',
                'insumo' => $insumo
            ];
        }
        if ($insumo) {
            return [
                'permitido' => false,
                'status' => 'warning',
                'message' => 'ATENÇÃO: Este insumo está na lista de RESTRIÇÃO ou PROIBIÇÃO.',
                'insumo' => $insumo
            ];
        }
        return [
            'permitido' => false,
            'status' => 'not_found',
            'message' => 'Insumo não localizado na lista oficial (Portaria 52/2021). O uso foi bloqueado por conformidade.',
            'insumo' => false
        ];
    }

    /**
     * Verifica se um insumo é permitido (atende RF06).
     * @param string $nome
     * @return bool
     */
    public function permitido($nome) {
        return $this->avaliar($nome)['permitido'];
    }
}
