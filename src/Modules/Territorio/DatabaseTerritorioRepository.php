<?php

declare(strict_types=1);

namespace App\Modules\Territorio;

use App\Kernel\Persistence\Repository;
use PDO;

final class DatabaseTerritorioRepository extends Repository implements TerritorioRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /** @return list<array<string, mixed>> */
    public function estados(): array
    {
        return $this->fetchAll('SELECT id, uf, nome, regiao FROM estados ORDER BY nome');
    }

    /** @return list<array<string, mixed>> */
    public function cidades(?int $estadoId, ?string $uf): array
    {
        if ($estadoId !== null) {
            return $this->fetchAll(
                'SELECT c.id, c.nome, e.uf, c.ibge, c.microrregiao_id
                 FROM cidades c JOIN estados e ON e.id = c.estado
                 WHERE c.estado = :estado ORDER BY c.nome',
                ['estado' => $estadoId]
            );
        }

        if ($uf !== null && $uf !== '') {
            return $this->fetchAll(
                'SELECT c.id, c.nome, e.uf, c.ibge, c.microrregiao_id
                 FROM cidades c JOIN estados e ON e.id = c.estado
                 WHERE e.uf = :uf ORDER BY c.nome',
                ['uf' => $uf]
            );
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    public function microrregioes(?int $mesorregiaoId): array
    {
        if ($mesorregiaoId !== null) {
            return $this->fetchAll(
                'SELECT m.id, m.nome, m.mesorregiao_id, mr.nome AS mesorregiao_nome
                 FROM microrregioes_rs m
                 LEFT JOIN mesorregioes_rs mr ON mr.id = m.mesorregiao_id
                 WHERE m.mesorregiao_id = :mesorregiao ORDER BY m.nome',
                ['mesorregiao' => $mesorregiaoId]
            );
        }

        return $this->fetchAll(
            'SELECT m.id, m.nome, m.mesorregiao_id, mr.nome AS mesorregiao_nome
             FROM microrregioes_rs m
             LEFT JOIN mesorregioes_rs mr ON mr.id = m.mesorregiao_id
             ORDER BY mr.nome, m.nome'
        );
    }

    /** @param array{termo?: string, uf?: string, cidade?: string, tipo_entidade?: string} $filters */
    public function cnpo(array $filters, int $limit): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (isset($filters['termo']) && $filters['termo'] !== '') {
            $where[] = '(nome_produtor_norm LIKE :termo_nome OR entidade_norm LIKE :termo_entidade)';
            $params['termo_nome'] = '%' . mb_strtolower($filters['termo']) . '%';
            $params['termo_entidade'] = '%' . mb_strtolower($filters['termo']) . '%';
        }
        if (isset($filters['uf']) && $filters['uf'] !== '') {
            $where[] = 'uf_norm = :uf';
            $params['uf'] = strtoupper($filters['uf']);
        }
        if (isset($filters['cidade']) && $filters['cidade'] !== '') {
            $where[] = 'cidade_norm LIKE :cidade';
            $params['cidade'] = '%' . mb_strtolower($filters['cidade']) . '%';
        }
        if (isset($filters['tipo_entidade']) && $filters['tipo_entidade'] !== '') {
            $where[] = 'tipo_entidade_norm = :tipo';
            $params['tipo'] = $filters['tipo_entidade'];
        }

        $limit = max(1, min(100, $limit));
        $sql = 'SELECT id, tipo_entidade, entidade, nome_produtor, uf, cidade, situacao
                FROM cnpo WHERE ' . implode(' AND ', $where) . '
                ORDER BY nome_produtor, entidade LIMIT ' . $limit;

        return $this->fetchAll($sql, $params);
    }
}
