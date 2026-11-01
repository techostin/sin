<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modules\Governanca\GovernancaRepositoryInterface;

final class FakeGovernancaRepository implements GovernancaRepositoryInterface
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createEvent(array $data, int $userId): array
    {
        return ['id' => 1, 'nome' => $data['nome'] ?? '', 'status' => 'Agendado'];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createAssembly(array $data, int $userId): array
    {
        return ['id' => 2, 'titulo' => $data['titulo'] ?? '', 'status' => 'rascunho'];
    }

    /** @return list<array<string, mixed>> */
    public function listEvents(): array
    {
        return [['id' => 1, 'nome' => 'Evento']];
    }

    /** @return list<array<string, mixed>> */
    public function listAssemblies(): array
    {
        return [['id' => 2, 'titulo' => 'Assembleia']];
    }
}
