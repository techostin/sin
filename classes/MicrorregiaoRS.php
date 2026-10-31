<?php
/**
 * MicrorregiaoRS - Classe para gestão de mesorregiões e microrregiões do RS
 */
require_once __DIR__ . '/Database.php';

class MicrorregiaoRS
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca microrregião pelo ID da cidade
     * @param int $cidadeId
     * @return array|null
     */
    public function findByCidade($cidadeId)
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mr.nome as mesorregiao_nome 
             FROM microrregioes_rs m
             INNER JOIN mesorregioes_rs mr ON mr.id = m.mesorregiao_id
             INNER JOIN cidades c ON c.microrregiao_id = m.id
             WHERE c.id = ?'
        );
        $stmt->execute([$cidadeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca microrregião pelo nome da cidade e UF
     * @param string $cidadeNome
     * @param string $uf
     * @return array|null
     */
    public function findByCidadeNome($cidadeNome, $uf = 'RS')
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mr.nome as mesorregiao_nome 
             FROM microrregioes_rs m
             INNER JOIN mesorregioes_rs mr ON mr.id = m.mesorregiao_id
             INNER JOIN cidades c ON c.microrregiao_id = m.id
             INNER JOIN estados e ON e.id = c.estado
             WHERE c.nome = ? AND e.uf = ?'
        );
        $stmt->execute([$cidadeNome, $uf]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Lista todas as microrregiões
     * @return array
     */
    public function listAll()
    {
        $stmt = $this->db->query(
            'SELECT m.*, mr.nome as mesorregiao_nome, mr.codigo_ibge as mesorregiao_codigo_ibge
             FROM microrregioes_rs m
             INNER JOIN mesorregioes_rs mr ON mr.id = m.mesorregiao_id
             ORDER BY mr.nome, m.nome'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista microrregiões por mesorregião
     * @param int $mesorregiaoId
     * @return array
     */
    public function listByMesorregiao($mesorregiaoId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM microrregioes_rs WHERE mesorregiao_id = ? ORDER BY nome'
        );
        $stmt->execute([$mesorregiaoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todas as mesorregiões
     * @return array
     */
    public function listMesorregioes()
    {
        $stmt = $this->db->query('SELECT * FROM mesorregioes_rs ORDER BY nome');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca mesorregião por ID
     * @param int $id
     * @return array|null
     */
    public function getMesorregiao($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM mesorregioes_rs WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca microrregião por ID
     * @param int $id
     * @return array|null
     */
    public function getMicrorregiao($id)
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mr.nome as mesorregiao_nome 
             FROM microrregioes_rs m
             INNER JOIN mesorregioes_rs mr ON mr.id = m.mesorregiao_id
             WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Estatísticas por microrregião (para dashboard/mapa)
     * @return array
     */
    public function getEstatisticasPorMicrorregiao()
    {
        $sql = '
            SELECT 
                mi.id,
                mi.nome as microrregiao,
                mi.codigo_ibge,
                mr.nome as mesorregiao,
                mr.codigo_ibge as mesorregiao_codigo,
                COUNT(DISTINCT c.id) as qtd_cidades,
                COUNT(DISTINCT a.id) as qtd_associados,
                COUNT(DISTINCT CASE WHEN a.status_associado = "Ativo" THEN a.id END) as qtd_ativos,
                COUNT(DISTINCT CASE WHEN a.status_associado = "Inadimplente" THEN a.id END) as qtd_inadimplentes
            FROM microrregioes_rs mi
            INNER JOIN mesorregioes_rs mr ON mr.id = mi.mesorregiao_id
            LEFT JOIN cidades c ON c.microrregiao_id = mi.id
            LEFT JOIN associados a ON a.microrregiao_id = mi.id
            GROUP BY mi.id, mi.nome, mr.nome
            ORDER BY mr.nome, mi.nome
        ';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dados para mapa de calor (GeoJSON simplificado)
     * @return array
     */
    public function getDadosMapaCalor()
    {
        $stats = $this->getEstatisticasPorMicrorregiao();
        $dados = [];
        foreach ($stats as $row) {
            $dados[] = [
                'microrregiao_id' => (int)$row['id'],
                'microrregiao' => $row['microrregiao'],
                'mesorregiao' => $row['mesorregiao'],
                'qtd_cidades' => (int)$row['qtd_cidades'],
                'qtd_associados' => (int)$row['qtd_associados'],
                'qtd_ativos' => (int)$row['qtd_ativos'],
                'qtd_inadimplentes' => (int)$row['qtd_inadimplentes'],
                'percentual_inadimplencia' => $row['qtd_ativos'] > 0
                    ? round(($row['qtd_inadimplentes'] / $row['qtd_ativos']) * 100, 1)
                    : 0
            ];
        }
        return $dados;
    }
}