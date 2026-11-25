<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modules\Relatorios\RelatoriosRepositoryInterface;

final class FakeRelatoriosRepository implements RelatoriosRepositoryInterface
{
    /** @return array{associados: array<string, int>, lotes: array<string, int>, visitas: array<string, int>, financeiro: array<string, array{quantidade: int, valor: float}>, documentos: array<string, int>} */
    public function summary(): array
    {
        return [
            'associados' => ['ativo' => 10, 'pendente' => 2],
            'lotes' => ['vegetal' => 5, 'animal' => 3],
            'visitas' => ['aprovada' => 4],
            'financeiro' => ['pendente' => ['quantidade' => 2, 'valor' => 100.0]],
            'documentos' => ['emitido' => 1],
        ];
    }
}
