<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modules\Rastreabilidade\RastreabilidadeRepositoryInterface;

final class FakeRastreabilidadeRepository implements RastreabilidadeRepositoryInterface
{
    public ?string $lastUuid = null;
    /** @var array<string, mixed> */
    public array $lastData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createVegetalLot(array $data, int $userId): array
    {
        $this->lastData = $data;

        return [
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'tipo' => 'vegetal',
            'codigo_publico' => 'LOTE-ABC123',
            'status' => 'ativo',
            'eventos' => [],
            'elos' => [],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createAnimalLot(array $data, int $userId): array
    {
        $this->lastData = $data;

        return [
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'tipo' => 'animal',
            'codigo_publico' => 'LOTE-ANI123',
            'status' => 'ativo',
            'eventos' => [],
            'elos' => [],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addAnimal(string $lotUuid, array $data, int $userId): ?array
    {
        $this->lastUuid = $lotUuid;
        $this->lastData = $data;

        return ['id' => 10, 'nome_animal' => $data['nome_animal'] ?? '', 'lote_id' => 1];
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addSanitaryEvent(string $lotUuid, int $animalId, array $data, int $userId): ?array
    {
        return ['id' => 20, 'animal_id' => $animalId, 'tipo_tratamento' => $data['tipo_tratamento'] ?? ''];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createRecipe(array $data, int $userId): array
    {
        return ['id' => 99, 'nome_produto_final' => $data['nome_produto_final'] ?? '', 'ingredientes' => []];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createAgroOrder(array $data, int $userId): array
    {
        return [
            'uuid' => '44444444-4444-4444-8444-444444444444',
            'tipo' => 'agroindustria',
            'codigo_publico' => 'LOTE-AGR999',
            'status' => 'ativo',
            'eventos' => [['tipo' => 'processamento']],
            'elos' => [],
            'entrada_total' => 10.0,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createInputLot(array $data, int $userId): array
    {
        $this->lastData = $data;

        return [
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'tipo' => 'insumo',
            'codigo_publico' => 'LOTE-DEF456',
            'status' => 'ativo',
            'eventos' => [],
            'elos' => [],
        ];
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        $this->lastUuid = $uuid;

        if (
            $uuid === '11111111-1111-4111-8111-111111111111'
            || $uuid === '33333333-3333-4333-8333-333333333333'
        ) {
            return [
                'uuid' => $uuid,
                'tipo' => $uuid === '33333333-3333-4333-8333-333333333333' ? 'animal' : 'vegetal',
                'codigo_publico' => 'LOTE-ABC123',
                'associado_id' => 7,
                'associado_associacao_id' => 1,
                'status' => 'ativo',
                'eventos' => [['tipo' => 'colheita']],
                'elos' => [],
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addEvent(string $uuid, array $data, int $userId): ?array
    {
        return ['id' => 1, 'tipo' => $data['tipo'] ?? null, 'lote_uuid' => $uuid];
    }

    /** @param array<string, mixed> $data */
    public function addLink(string $origemUuid, string $destinoUuid, array $data, int $userId): bool
    {
        return $origemUuid !== $destinoUuid;
    }

    /** @return array<string, mixed>|null */
    public function verifyCode(string $code): ?array
    {
        return $code === 'LOTE-ABC123'
            ? ['codigo' => $code, 'tipo' => 'vegetal', 'status' => 'ativo', 'eventos' => 1]
            : null;
    }
}
