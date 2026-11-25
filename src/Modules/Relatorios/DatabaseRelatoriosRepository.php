<?php

declare(strict_types=1);

namespace App\Modules\Relatorios;

use App\Kernel\Persistence\Repository;
use PDO;

final class DatabaseRelatoriosRepository extends Repository implements RelatoriosRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /** @return array{associados: array<string, int>, lotes: array<string, int>, visitas: array<string, int>, financeiro: array<string, array{quantidade: int, valor: float}>, documentos: array<string, int>} */
    public function summary(): array
    {
        $associados = [];
        foreach ($this->fetchAll('SELECT status, COUNT(*) AS total FROM associados GROUP BY status') as $row) {
            $associados[(string) ($row['status'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        $lotes = [];
        foreach ($this->fetchAll('SELECT tipo, COUNT(*) AS total FROM rastreabilidade_lotes GROUP BY tipo') as $row) {
            $lotes[(string) ($row['tipo'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        $visitas = [];
        $rows = $this->fetchAll(
            'SELECT status, COUNT(*) AS total FROM fiscalizacao_visitas GROUP BY status'
        );
        foreach ($rows as $row) {
            $visitas[(string) ($row['status'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        $financeiro = [];
        $rows = $this->fetchAll(
            'SELECT status_mensalidade, COUNT(*) AS quantidade, COALESCE(SUM(valor_cobrado), 0) AS valor
             FROM mensalidades GROUP BY status_mensalidade'
        );
        foreach ($rows as $row) {
            $financeiro[(string) ($row['status_mensalidade'] ?? '')] = [
                'quantidade' => (int) ($row['quantidade'] ?? 0),
                'valor' => (float) ($row['valor'] ?? 0),
            ];
        }

        $documentos = [];
        foreach ($this->fetchAll('SELECT status, COUNT(*) AS total FROM documentos_emitidos GROUP BY status') as $row) {
            $documentos[(string) ($row['status'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        return [
            'associados' => $associados,
            'lotes' => $lotes,
            'visitas' => $visitas,
            'financeiro' => $financeiro,
            'documentos' => $documentos,
        ];
    }
}
