<?php

declare(strict_types=1);

namespace App\Modules\Relatorios;

interface RelatoriosRepositoryInterface
{
    /**
     * @return array{
     *   associados: array<string, int>,
     *   lotes: array<string, int>,
     *   visitas: array<string, int>,
     *   financeiro: array<string, array{quantidade: int, valor: float}>,
     *   documentos: array<string, int>
     * }
     */
    public function summary(): array;
}
