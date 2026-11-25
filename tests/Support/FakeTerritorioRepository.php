<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modules\Territorio\TerritorioRepositoryInterface;

final class FakeTerritorioRepository implements TerritorioRepositoryInterface
{
    public ?int $lastEstadoId = null;
    public ?string $lastUf = null;
    public int $lastLimit = 0;
    /** @var array<string, string> */
    public array $lastFilters = [];

    /** @return list<array<string, mixed>> */
    public function estados(): array
    {
        return [['id' => 1, 'uf' => 'RS', 'nome' => 'Rio Grande do Sul', 'regiao' => 'Sul']];
    }

    /** @return list<array<string, mixed>> */
    public function cidades(?int $estadoId, ?string $uf): array
    {
        $this->lastEstadoId = $estadoId;
        $this->lastUf = $uf;

        return [['id' => 1, 'nome' => 'Porto Alegre', 'uf' => 'RS']];
    }

    /** @return list<array<string, mixed>> */
    public function microrregioes(?int $mesorregiaoId): array
    {
        return [['id' => 1, 'nome' => 'Micro', 'mesorregiao_id' => 1]];
    }

    /** @param array{termo?: string, uf?: string, cidade?: string, tipo_entidade?: string} $filters */
    public function cnpo(array $filters, int $limit): array
    {
        $this->lastFilters = $filters;
        $this->lastLimit = $limit;

        return [['id' => 1, 'nome_produtor' => 'Produtor Teste', 'uf' => 'RS']];
    }
}
