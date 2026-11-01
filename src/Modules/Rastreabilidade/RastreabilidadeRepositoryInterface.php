<?php

declare(strict_types=1);

namespace App\Modules\Rastreabilidade;

interface RastreabilidadeRepositoryInterface
{
    /**
     * @param array{
     *   nome_lote: string,
     *   produto_id: int,
     *   data_producao: string,
     *   quantidade: float|int|string,
     *   unidade_medida: string,
     *   observacoes?: string,
     *   associado_id?: int|null
     * } $data
     * @return array<string, mixed>
     */
    public function createVegetalLot(array $data, int $userId): array;

    /**
     * @param array{
     *   nome: string,
     *   fornecedor?: string,
     *   lote?: string,
     *   data_validade?: string,
     *   associado_id?: int|null
     * } $data
     * @return array<string, mixed>
     */
    public function createInputLot(array $data, int $userId): array;

    /**
     * @param array{
     *   nome_lote: string,
     *   especie?: string,
     *   categoria?: string,
     *   quantidade?: int|float|string,
     *   descricao?: string,
     *   associado_id?: int|null
     * } $data
     * @return array<string, mixed>
     */
    public function createAnimalLot(array $data, int $userId): array;

    /**
     * @param array{
     *   nome_animal: string,
     *   especie: string,
     *   raca?: string,
     *   identificacao?: string,
     *   sexo?: string,
     *   data_nascimento?: string,
     *   peso_kg?: float|int|string
     * } $data
     * @return array<string, mixed>|null
     */
    public function addAnimal(string $lotUuid, array $data, int $userId): ?array;

    /**
     * @param array{
     *   data_tratamento: string,
     *   tipo_tratamento: string,
     *   produto_utilizado?: string,
     *   motivo?: string,
     *   dose_quantidade?: string
     * } $data
     * @return array<string, mixed>|null
     */
    public function addSanitaryEvent(string $lotUuid, int $animalId, array $data, int $userId): ?array;

    /**
     * @param array{
     *   nome_produto_final: string,
     *   percentual_organico_calculado: float|int|string,
     *   selo_autorizado: string,
     *   ingredientes: list<array{nome_ingrediente: string, eh_organico: int, proporcao_porcentagem: float|int|string}>
     * } $data
     * @return array<string, mixed>
     */
    public function createRecipe(array $data, int $userId): array;

    /**
     * @param array{
     *   receita_id: int,
     *   lote_processado_final: string,
     *   data_processamento: string,
     *   quantidade_final_obtida: float|int|string,
     *   unidade_medida: string,
     *   lotes_entrada?: list<array{uuid: string, quantidade?: float|int|string, unidade?: string}>
     * } $data
     * @return array<string, mixed>
     */
    public function createAgroOrder(array $data, int $userId): array;

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array;

    /**
     * @param array{
     *   tipo: string,
     *   data_evento: string,
     *   local?: string,
     *   descricao?: string,
     *   payload?: array<string, mixed>
     * } $data
     * @return array<string, mixed>|null
     */
    public function addEvent(string $uuid, array $data, int $userId): ?array;

    /**
     * @param array{tipo: string, quantidade?: float|int|string, unidade?: string, payload?: array<string, mixed>} $data
     */
    public function addLink(string $origemUuid, string $destinoUuid, array $data, int $userId): bool;

    /** @return array<string, mixed>|null */
    public function verifyCode(string $code): ?array;
}
