<?php

declare(strict_types=1);

namespace App\Modules\Rastreabilidade;

use App\Kernel\Persistence\Repository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseRastreabilidadeRepository extends Repository implements RastreabilidadeRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createVegetalLot(array $data, int $userId): array
    {
        $productId = (int) ($data['produto_id'] ?? 0);
        $product = $this->fetchOne(
            'SELECT p.id, p.lavoura_id, l.propriedade_id
             FROM produtos p LEFT JOIN lavouras l ON l.id = p.lavoura_id
             WHERE p.id = :id LIMIT 1',
            ['id' => $productId]
        );
        if ($product === null) {
            throw new InvalidArgumentException('Produto invalido.');
        }

        $uuid = $this->uuid();
        $code = $this->code();
        $associadoId = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : null;
        $propertyId = isset($product['propriedade_id']) ? (int) $product['propriedade_id'] : null;
        $payload = [
            'produto_id' => $productId,
            'nome_lote' => (string) ($data['nome_lote'] ?? ''),
            'quantidade' => $data['quantidade'] ?? 0,
            'unidade_medida' => (string) ($data['unidade_medida'] ?? ''),
        ];

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO lotes_producao
                    (uuid, nome_lote, produto_id, associado_id, data_producao, quantidade,
                     unidade_medida, observacoes, criado_em)
                 VALUES (:uuid, :nome_lote, :produto_id, :associado_id, :data_producao, :quantidade,
                         :unidade_medida, :observacoes, NOW())'
            );
            $insert->execute([
                'uuid' => $uuid,
                'nome_lote' => (string) ($data['nome_lote'] ?? ''),
                'produto_id' => $productId,
                'associado_id' => $associadoId,
                'data_producao' => (string) ($data['data_producao'] ?? date('Y-m-d')),
                'quantidade' => $data['quantidade'] ?? 0,
                'unidade_medida' => (string) ($data['unidade_medida'] ?? 'kg'),
                'observacoes' => (string) ($data['observacoes'] ?? ''),
            ]);
            $legacyId = (int) $this->pdo->lastInsertId();

            $lotId = $this->insertLot(
                $uuid,
                'vegetal',
                'lotes_producao',
                $legacyId,
                $code,
                $associadoId,
                $propertyId,
                $payload,
                $userId
            );

            $this->insertEvent($lotId, [
                'tipo' => 'colheita',
                'data_evento' => (string) ($data['data_producao'] ?? date('Y-m-d')),
                'local' => null,
                'descricao' => 'Lote vegetal registrado no caderno de campo.',
                'payload' => $payload,
            ], $userId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $lot = $this->findByUuid($uuid);
        if ($lot === null) {
            throw new RuntimeException('Lote criado nao encontrado.');
        }

        return $lot;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createAnimalLot(array $data, int $userId): array
    {
        $uuid = $this->uuid();
        $code = $this->code();
        $associadoId = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : null;
        $payload = [
            'nome_lote' => (string) ($data['nome_lote'] ?? ''),
            'especie' => (string) ($data['especie'] ?? ''),
            'categoria' => (string) ($data['categoria'] ?? ''),
            'quantidade' => $data['quantidade'] ?? null,
        ];

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO animal_lotes
                    (associado_id, nome_lote, especie, categoria, quantidade, descricao, excluido, criado_por)
                 VALUES (:associado_id, :nome_lote, :especie, :categoria, :quantidade, :descricao, 0, :criado_por)'
            );
            $insert->execute([
                'associado_id' => $associadoId,
                'nome_lote' => (string) ($data['nome_lote'] ?? ''),
                'especie' => (string) ($data['especie'] ?? ''),
                'categoria' => (string) ($data['categoria'] ?? ''),
                'quantidade' => $data['quantidade'] ?? null,
                'descricao' => (string) ($data['descricao'] ?? ''),
                'criado_por' => $userId,
            ]);
            $legacyId = (int) $this->pdo->lastInsertId();

            $lotId = $this->insertLot(
                $uuid,
                'animal',
                'animal_lotes',
                $legacyId,
                $code,
                $associadoId,
                null,
                $payload,
                $userId
            );

            $this->insertEvent($lotId, [
                'tipo' => 'entrada',
                'data_evento' => date('Y-m-d H:i:s'),
                'local' => null,
                'descricao' => 'Lote animal registrado.',
                'payload' => $payload,
            ], $userId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $lot = $this->findByUuid($uuid);
        if ($lot === null) {
            throw new RuntimeException('Lote animal criado nao encontrado.');
        }

        return $lot;
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addAnimal(string $lotUuid, array $data, int $userId): ?array
    {
        $lot = $this->fetchOne(
            "SELECT id, referencia_id, associado_id FROM rastreabilidade_lotes
             WHERE uuid = :uuid AND tipo = 'animal' LIMIT 1",
            ['uuid' => $lotUuid]
        );
        if ($lot === null) {
            return null;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO animais
                (associado_id, nome_animal, especie, raca, identificacao, sexo,
                 data_nascimento, status, origem_conversao, lote_id, peso_kg,
                 tipo_entrada, criado_por, excluido)
             VALUES (:associado_id, :nome_animal, :especie, :raca, :identificacao, :sexo,
                     :data_nascimento, :status, :origem_conversao, :lote_id, :peso_kg,
                     :tipo_entrada, :criado_por, 0)'
        );
        $insert->execute([
            'associado_id' => $lot['associado_id'] ?? null,
            'nome_animal' => (string) ($data['nome_animal'] ?? ''),
            'especie' => (string) ($data['especie'] ?? ''),
            'raca' => $data['raca'] ?? null,
            'identificacao' => $data['identificacao'] ?? null,
            'sexo' => $data['sexo'] ?? null,
            'data_nascimento' => $data['data_nascimento'] ?? null,
            'status' => 'ATIVO',
            'origem_conversao' => 'ORGANICO',
            'lote_id' => (int) $lot['referencia_id'],
            'peso_kg' => $data['peso_kg'] ?? null,
            'tipo_entrada' => 'NASCIDO_PROPRIEDADE',
            'criado_por' => $userId,
        ]);
        $animalId = (int) $this->pdo->lastInsertId();

        $this->insertEvent((int) $lot['id'], [
            'tipo' => 'animal_adicionado',
            'data_evento' => date('Y-m-d H:i:s'),
            'descricao' => 'Animal individual vinculado ao lote.',
            'payload' => ['animal_id' => $animalId, 'nome' => $data['nome_animal'] ?? ''],
        ], $userId);

        return $this->fetchOne('SELECT * FROM animais WHERE id = :id LIMIT 1', ['id' => $animalId]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addSanitaryEvent(string $lotUuid, int $animalId, array $data, int $userId): ?array
    {
        $lot = $this->fetchOne(
            "SELECT id, referencia_id, associado_id FROM rastreabilidade_lotes
             WHERE uuid = :uuid AND tipo = 'animal' LIMIT 1",
            ['uuid' => $lotUuid]
        );
        if ($lot === null) {
            return null;
        }

        $animal = $this->fetchOne(
            'SELECT id FROM animais WHERE id = :id AND lote_id = :lote AND excluido = 0 LIMIT 1',
            ['id' => $animalId, 'lote' => (int) $lot['referencia_id']]
        );
        if ($animal === null) {
            return null;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO controle_sanidade_animal
                (associado_id, animal_id, lote_id, data_tratamento, tipo_tratamento,
                 produto_utilizado, motivo, dose_quantidade, produto_normatizado, criado_por, excluido)
             VALUES (:associado_id, :animal_id, :lote_id, :data_tratamento, :tipo_tratamento,
                     :produto_utilizado, :motivo, :dose_quantidade, 0, :criado_por, 0)'
        );
        $insert->execute([
            'associado_id' => $lot['associado_id'] ?? null,
            'animal_id' => $animalId,
            'lote_id' => (int) $lot['referencia_id'],
            'data_tratamento' => (string) ($data['data_tratamento'] ?? date('Y-m-d')),
            'tipo_tratamento' => (string) ($data['tipo_tratamento'] ?? 'PREVENTIVO'),
            'produto_utilizado' => $data['produto_utilizado'] ?? null,
            'motivo' => $data['motivo'] ?? null,
            'dose_quantidade' => $data['dose_quantidade'] ?? null,
            'criado_por' => $userId,
        ]);
        $sanitaryId = (int) $this->pdo->lastInsertId();

        $this->insertEvent((int) $lot['id'], [
            'tipo' => 'sanidade',
            'data_evento' => (string) ($data['data_tratamento'] ?? date('Y-m-d')),
            'descricao' => 'Evento sanitario registrado.',
            'payload' => ['animal_id' => $animalId, 'tipo' => $data['tipo_tratamento'] ?? ''],
        ], $userId);

        return $this->fetchOne('SELECT * FROM controle_sanidade_animal WHERE id = :id LIMIT 1', ['id' => $sanitaryId]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addReproductionEvent(string $lotUuid, int $animalId, array $data, int $userId): ?array
    {
        $lot = $this->animalLot($lotUuid);
        if ($lot === null || !$this->animalBelongsToLot($animalId, (int) $lot['referencia_id'])) {
            return null;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO controle_reproducao_animal
                (associado_id, animal_id, tipo_evento, data_evento, observacoes, excluido, criado_por)
             VALUES (:associado_id, :animal_id, :tipo, :data_evento, :observacoes, 0, :criado_por)'
        );
        $insert->execute([
            'associado_id' => $lot['associado_id'] ?? null,
            'animal_id' => $animalId,
            'tipo' => (string) ($data['tipo_evento'] ?? 'COBERTURA'),
            'data_evento' => (string) ($data['data_evento'] ?? date('Y-m-d')),
            'observacoes' => $data['observacoes'] ?? null,
            'criado_por' => $userId,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        $this->insertEvent((int) $lot['id'], [
            'tipo' => 'reproducao',
            'data_evento' => (string) ($data['data_evento'] ?? date('Y-m-d')),
            'descricao' => 'Evento reprodutivo registrado.',
            'payload' => ['animal_id' => $animalId, 'tipo' => $data['tipo_evento'] ?? ''],
        ], $userId);

        return $this->fetchOne('SELECT * FROM controle_reproducao_animal WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addAnimalMovement(string $lotUuid, int $animalId, array $data, int $userId): ?array
    {
        $lot = $this->animalLot($lotUuid);
        if ($lot === null || !$this->animalBelongsToLot($animalId, (int) $lot['referencia_id'])) {
            return null;
        }

        $movUuid = $this->uuid();
        $insert = $this->pdo->prepare(
            'INSERT INTO animal_movimentacoes
                (uuid, animal_id, lote_id, tipo, data_movimentacao, destino, observacoes, criado_por)
             VALUES (:uuid, :animal_id, :lote_id, :tipo, :data_movimentacao, :destino, :observacoes, :criado_por)'
        );
        $insert->execute([
            'uuid' => $movUuid,
            'animal_id' => $animalId,
            'lote_id' => (int) $lot['referencia_id'],
            'tipo' => (string) ($data['tipo'] ?? 'TRANSFERENCIA'),
            'data_movimentacao' => (string) ($data['data_movimentacao'] ?? date('Y-m-d')),
            'destino' => $data['destino'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'criado_por' => $userId,
        ]);

        $this->insertEvent((int) $lot['id'], [
            'tipo' => 'movimentacao',
            'data_evento' => (string) ($data['data_movimentacao'] ?? date('Y-m-d')),
            'descricao' => 'Movimentacao animal registrada.',
            'payload' => ['animal_id' => $animalId, 'tipo' => $data['tipo'] ?? ''],
        ], $userId);

        return $this->fetchOne('SELECT * FROM animal_movimentacoes WHERE uuid = :uuid LIMIT 1', ['uuid' => $movUuid]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addHygiene(string $orderLotUuid, array $data, int $userId): ?array
    {
        $lot = $this->fetchOne(
            "SELECT id FROM rastreabilidade_lotes WHERE uuid = :uuid AND tipo = 'agroindustria' LIMIT 1",
            ['uuid' => $orderLotUuid]
        );
        if ($lot === null) {
            return null;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO controle_higienizacao
                (data_hora_inicio, equipamento_linha, sanitizante_utilizado, responsavel_nome,
                 associado_id, observacoes, excluido, criado_por)
             VALUES (:inicio, :equipamento, :sanitizante, :responsavel, :associado_id, :observacoes, 0, :criado_por)'
        );
        $insert->execute([
            'inicio' => (string) ($data['data_hora_inicio'] ?? date('Y-m-d H:i:s')),
            'equipamento' => (string) ($data['equipamento_linha'] ?? ''),
            'sanitizante' => (string) ($data['sanitizante_utilizado'] ?? ''),
            'responsavel' => (string) ($data['responsavel_nome'] ?? ''),
            'associado_id' => $data['associado_id'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'criado_por' => $userId,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        $this->insertEvent((int) $lot['id'], [
            'tipo' => 'higienizacao',
            'data_evento' => (string) ($data['data_hora_inicio'] ?? date('Y-m-d H:i:s')),
            'descricao' => 'Higienizacao previa registrada.',
            'payload' => ['higienizacao_id' => $id],
        ], $userId);

        return $this->fetchOne('SELECT * FROM controle_higienizacao WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    private function animalLot(string $uuid): ?array
    {
        return $this->fetchOne(
            "SELECT id, referencia_id, associado_id FROM rastreabilidade_lotes
             WHERE uuid = :uuid AND tipo = 'animal' LIMIT 1",
            ['uuid' => $uuid]
        );
    }

    private function animalBelongsToLot(int $animalId, int $lotId): bool
    {
        $animal = $this->fetchOne(
            'SELECT id FROM animais WHERE id = :id AND lote_id = :lote AND excluido = 0 LIMIT 1',
            ['id' => $animalId, 'lote' => $lotId]
        );

        return $animal !== null;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createLegacyInputLot(array $data, int $userId): array
    {
        $uuid = $this->uuid();
        $code = $this->code();
        $associadoId = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : null;
        $payload = [
            'nome' => (string) ($data['nome'] ?? ''),
            'lote' => (string) ($data['lote'] ?? ''),
            'fornecedor_id' => $data['fornecedor_id'] ?? null,
        ];

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO insumos
                    (uuid, associado_id, nome, fornecedor_id, lote, data_validade,
                     quantidade_estoque, aprovado_organico, excluido)
                 VALUES (:uuid, :associado_id, :nome, :fornecedor_id, :lote, :data_validade,
                         :quantidade, 1, 0)'
            );
            $insert->execute([
                'uuid' => $uuid,
                'associado_id' => $associadoId,
                'nome' => (string) ($data['nome'] ?? ''),
                'fornecedor_id' => $data['fornecedor_id'] ?? null,
                'lote' => $data['lote'] ?? null,
                'data_validade' => $data['data_validade'] ?? null,
                'quantidade' => $data['quantidade_estoque'] ?? 0,
            ]);
            $legacyId = (int) $this->pdo->lastInsertId();

            $lotId = $this->insertLot(
                $uuid,
                'insumo',
                'insumos',
                $legacyId,
                $code,
                $associadoId,
                null,
                $payload,
                $userId
            );

            $this->insertEvent($lotId, [
                'tipo' => 'recebimento',
                'data_evento' => date('Y-m-d H:i:s'),
                'descricao' => 'Lote de insumo legado registrado.',
                'payload' => $payload,
            ], $userId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $lot = $this->findByUuid($uuid);
        if ($lot === null) {
            throw new RuntimeException('Lote de insumo criado nao encontrado.');
        }

        return $lot;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createRecipe(array $data, int $userId): array
    {
        $selo = (string) ($data['selo_autorizado'] ?? 'SEM_SELO');
        if (!in_array($selo, ['PRODUTO_ORGANICO_BRASIL', 'INGREDIENTES_ORGANICOS', 'SEM_SELO'], true)) {
            throw new InvalidArgumentException('Selo invalido.');
        }

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO receitas_agroindustria
                    (nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado,
                     associado_id, status, excluido, criado_por)
                 VALUES (:nome, :descricao, :percentual, :selo, :associado_id, :status, 0, :criado_por)'
            );
            $insert->execute([
                'nome' => (string) ($data['nome_produto_final'] ?? ''),
                'descricao' => $data['descricao'] ?? null,
                'percentual' => $data['percentual_organico_calculado'] ?? 0,
                'selo' => $selo,
                'associado_id' => $data['associado_id'] ?? null,
                'status' => 'Ativa',
                'criado_por' => $userId,
            ]);
            $recipeId = (int) $this->pdo->lastInsertId();

            $ingredients = $data['ingredientes'] ?? [];
            if (is_array($ingredients)) {
                $ingredientInsert = $this->pdo->prepare(
                    'INSERT INTO ingredientes_receita
                        (receita_id, nome_ingrediente, eh_organico, proporcao_porcentagem)
                     VALUES (:receita_id, :nome, :organico, :proporcao)'
                );
                foreach ($ingredients as $ingredient) {
                    if (!is_array($ingredient)) {
                        continue;
                    }
                    $ingredientInsert->execute([
                        'receita_id' => $recipeId,
                        'nome' => (string) ($ingredient['nome_ingrediente'] ?? ''),
                        'organico' => (int) ($ingredient['eh_organico'] ?? 0),
                        'proporcao' => $ingredient['proporcao_porcentagem'] ?? 0,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $recipe = $this->fetchOne('SELECT * FROM receitas_agroindustria WHERE id = :id LIMIT 1', ['id' => $recipeId]);
        if ($recipe === null) {
            throw new RuntimeException('Receita criada nao encontrada.');
        }
        $recipe['ingredientes'] = $this->fetchAll(
            'SELECT * FROM ingredientes_receita WHERE receita_id = :id',
            ['id' => $recipeId]
        );

        return $recipe;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createAgroOrder(array $data, int $userId): array
    {
        $recipeId = (int) ($data['receita_id'] ?? 0);
        $recipe = $this->fetchOne(
            'SELECT id, percentual_organico_calculado, selo_autorizado
             FROM receitas_agroindustria WHERE id = :id AND excluido = 0 LIMIT 1',
            ['id' => $recipeId]
        );
        if ($recipe === null) {
            throw new InvalidArgumentException('Receita invalida.');
        }

        $uuid = $this->uuid();
        $code = $this->code();
        $associadoId = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : null;
        $payload = [
            'receita_id' => $recipeId,
            'lote_processado_final' => (string) ($data['lote_processado_final'] ?? ''),
            'quantidade_final_obtida' => $data['quantidade_final_obtida'] ?? 0,
            'unidade_medida' => (string) ($data['unidade_medida'] ?? 'kg'),
        ];
        $inputTotal = 0.0;
        $inputs = is_array($data['lotes_entrada'] ?? null) ? $data['lotes_entrada'] : [];

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO ordens_producao
                    (receita_id, higienizacao_previa_id, lote_processado_final, data_processamento,
                     quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico,
                     selo_autorizado, associado_id, observacoes, excluido, criado_por)
                 VALUES (:receita_id, :higienizacao_id, :lote_final, :data_processamento,
                         :quantidade, :unidade, :status, :percentual, :selo, :associado_id,
                         :observacoes, 0, :criado_por)'
            );
            $insert->execute([
                'receita_id' => $recipeId,
                'higienizacao_id' => $data['higienizacao_previa_id'] ?? null,
                'lote_final' => (string) ($data['lote_processado_final'] ?? ''),
                'data_processamento' => (string) ($data['data_processamento'] ?? date('Y-m-d H:i:s')),
                'quantidade' => $data['quantidade_final_obtida'] ?? 0,
                'unidade' => (string) ($data['unidade_medida'] ?? 'kg'),
                'status' => 'EM_ANALISE',
                'percentual' => $data['percentual_organico'] ?? $recipe['percentual_organico_calculado'],
                'selo' => $data['selo_autorizado'] ?? $recipe['selo_autorizado'],
                'associado_id' => $associadoId,
                'observacoes' => $data['observacoes'] ?? null,
                'criado_por' => $userId,
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            $lotId = $this->insertLot(
                $uuid,
                'agroindustria',
                'ordens_producao',
                $orderId,
                $code,
                $associadoId,
                null,
                $payload,
                $userId
            );

            $this->insertEvent($lotId, [
                'tipo' => 'processamento',
                'data_evento' => (string) ($data['data_processamento'] ?? date('Y-m-d H:i:s')),
                'descricao' => 'Ordem de producao agroindustrial registrada.',
                'payload' => $payload,
            ], $userId);

            $link = $this->pdo->prepare(
                'INSERT INTO rastreabilidade_elos
                    (origem_lote_id, destino_lote_id, tipo, quantidade, unidade, payload_json, created_by)
                 VALUES (:origem, :destino, :tipo, :quantidade, :unidade, :payload, :created_by)'
            );
            foreach ($inputs as $input) {
                if (!is_array($input) || !isset($input['uuid']) || !is_string($input['uuid'])) {
                    continue;
                }
                $origin = $this->fetchOne(
                    'SELECT id FROM rastreabilidade_lotes WHERE uuid = :uuid LIMIT 1',
                    ['uuid' => $input['uuid']]
                );
                if ($origin === null) {
                    throw new InvalidArgumentException('Lote de entrada inexistente.');
                }
                $link->execute([
                    'origem' => (int) $origin['id'],
                    'destino' => $lotId,
                    'tipo' => 'transformacao',
                    'quantidade' => $input['quantidade'] ?? null,
                    'unidade' => $input['unidade'] ?? null,
                    'payload' => $this->encodeJson(['ordem_id' => $orderId]),
                    'created_by' => $userId,
                ]);
                $inputTotal += (float) ($input['quantidade'] ?? 0);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $lot = $this->findByUuid($uuid);
        if ($lot === null) {
            throw new RuntimeException('Ordem agroindustrial criada nao encontrada.');
        }
        $lot['entrada_total'] = $inputTotal;

        return $lot;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createInputLot(array $data, int $userId): array
    {
        $uuid = $this->uuid();
        $code = $this->code();
        $associadoId = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : null;
        $payload = [
            'nome' => (string) ($data['nome'] ?? ''),
            'fornecedor' => (string) ($data['fornecedor'] ?? ''),
            'lote' => (string) ($data['lote'] ?? ''),
            'data_validade' => (string) ($data['data_validade'] ?? ''),
        ];

        $this->pdo->beginTransaction();
        try {
            $lotId = $this->insertLot(
                $uuid,
                'insumo',
                'manual',
                0,
                $code,
                $associadoId,
                null,
                $payload,
                $userId
            );

            $this->insertEvent($lotId, [
                'tipo' => 'recebimento',
                'data_evento' => date('Y-m-d H:i:s'),
                'local' => null,
                'descricao' => 'Lote de insumo registrado.',
                'payload' => $payload,
            ], $userId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $lot = $this->findByUuid($uuid);
        if ($lot === null) {
            throw new RuntimeException('Lote de insumo criado nao encontrado.');
        }

        return $lot;
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        $lot = $this->fetchOne(
            'SELECT rl.*, a.associacao_id AS associado_associacao_id
             FROM rastreabilidade_lotes rl
             LEFT JOIN associados a ON a.id = rl.associado_id
             WHERE rl.uuid = :uuid LIMIT 1',
            ['uuid' => $uuid]
        );
        if ($lot === null) {
            return null;
        }

        $lot['payload'] = $this->decodeJson($lot['payload_json'] ?? null);
        unset($lot['payload_json']);

        $events = $this->fetchAll(
            'SELECT * FROM rastreabilidade_eventos WHERE lote_id = :id ORDER BY data_evento, id',
            ['id' => (int) $lot['id']]
        );
        foreach ($events as $index => $event) {
            $events[$index]['payload'] = $this->decodeJson($event['payload_json'] ?? null);
            unset($events[$index]['payload_json']);
        }

        $links = $this->fetchAll(
            'SELECT * FROM rastreabilidade_elos
             WHERE origem_lote_id = :origem_id OR destino_lote_id = :destino_id
             ORDER BY id',
            ['origem_id' => (int) $lot['id'], 'destino_id' => (int) $lot['id']]
        );
        foreach ($links as $index => $link) {
            $links[$index]['payload'] = $this->decodeJson($link['payload_json'] ?? null);
            unset($links[$index]['payload_json']);
        }

        $lot['eventos'] = $events;
        $lot['elos'] = $links;

        return $lot;
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addEvent(string $uuid, array $data, int $userId): ?array
    {
        $lot = $this->fetchOne('SELECT id FROM rastreabilidade_lotes WHERE uuid = :uuid LIMIT 1', ['uuid' => $uuid]);
        if ($lot === null) {
            return null;
        }

        $this->insertEvent((int) $lot['id'], $data, $userId);

        $event = $this->fetchOne(
            'SELECT * FROM rastreabilidade_eventos WHERE lote_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => (int) $lot['id']]
        );
        if ($event === null) {
            return null;
        }

        $event['payload'] = $this->decodeJson($event['payload_json'] ?? null);
        unset($event['payload_json']);

        return $event;
    }

    /** @param array<string, mixed> $data */
    public function addLink(string $origemUuid, string $destinoUuid, array $data, int $userId): bool
    {
        $origem = $this->fetchOne(
            'SELECT id FROM rastreabilidade_lotes WHERE uuid = :uuid LIMIT 1',
            ['uuid' => $origemUuid]
        );
        $destino = $this->fetchOne(
            'SELECT id FROM rastreabilidade_lotes WHERE uuid = :uuid LIMIT 1',
            ['uuid' => $destinoUuid]
        );
        if ($origem === null || $destino === null || (int) $origem['id'] === (int) $destino['id']) {
            return false;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO rastreabilidade_elos
                (origem_lote_id, destino_lote_id, tipo, quantidade, unidade, payload_json, created_by)
             VALUES (:origem, :destino, :tipo, :quantidade, :unidade, :payload, :created_by)'
        );
        $insert->execute([
            'origem' => (int) $origem['id'],
            'destino' => (int) $destino['id'],
            'tipo' => (string) ($data['tipo'] ?? 'insumo'),
            'quantidade' => $data['quantidade'] ?? null,
            'unidade' => $data['unidade'] ?? null,
            'payload' => $this->encodeJson($data['payload'] ?? []),
            'created_by' => $userId,
        ]);

        return true;
    }

    /** @return array<string, mixed>|null */
    public function verifyCode(string $code): ?array
    {
        $lot = $this->fetchOne(
            'SELECT id, tipo, codigo_publico, status, created_at
             FROM rastreabilidade_lotes WHERE codigo_publico = :codigo LIMIT 1',
            ['codigo' => $code]
        );
        if ($lot === null) {
            return null;
        }

        $count = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM rastreabilidade_eventos WHERE lote_id = :id',
            ['id' => (int) $lot['id']]
        );

        return [
            'codigo' => $lot['codigo_publico'],
            'tipo' => $lot['tipo'],
            'status' => $lot['status'],
            'eventos' => (int) ($count['total'] ?? 0),
            'criado_em' => $lot['created_at'],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function insertLot(
        string $uuid,
        string $type,
        string $referenceTable,
        int $referenceId,
        string $code,
        ?int $associadoId,
        ?int $propertyId,
        array $payload,
        int $userId,
    ): int {
        $insert = $this->pdo->prepare(
            'INSERT INTO rastreabilidade_lotes
                (uuid, tipo, referencia_tabela, referencia_id, codigo_publico,
                 associado_id, propriedade_id, status, payload_json, created_by)
             VALUES (:uuid, :tipo, :ref_tabela, :ref_id, :codigo,
                     :associado_id, :propriedade_id, :status, :payload, :created_by)'
        );
        $insert->execute([
            'uuid' => $uuid,
            'tipo' => $type,
            'ref_tabela' => $referenceTable,
            'ref_id' => $referenceId,
            'codigo' => $code,
            'associado_id' => $associadoId,
            'propriedade_id' => $propertyId,
            'status' => 'ativo',
            'payload' => $this->encodeJson($payload),
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    private function insertEvent(int $lotId, array $data, int $userId): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO rastreabilidade_eventos
                (lote_id, tipo, data_evento, local, descricao, payload_json, usuario_id)
             VALUES (:lote_id, :tipo, :data_evento, :local, :descricao, :payload, :usuario_id)'
        );
        $dataEvento = (string) ($data['data_evento'] ?? date('Y-m-d H:i:s'));
        $timestamp = strtotime($dataEvento);
        if ($timestamp === false) {
            $timestamp = time();
        }

        $insert->execute([
            'lote_id' => $lotId,
            'tipo' => mb_substr((string) ($data['tipo'] ?? 'evento'), 0, 40),
            'data_evento' => date('Y-m-d H:i:s', $timestamp),
            'local' => isset($data['local']) ? mb_substr((string) $data['local'], 0, 255) : null,
            'descricao' => isset($data['descricao']) ? mb_substr((string) $data['descricao'], 0, 500) : null,
            'payload' => $this->encodeJson($data['payload'] ?? []),
            'usuario_id' => $userId,
        ]);
    }

    /** @param mixed $value */
    private function encodeJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function code(): string
    {
        return 'LOTE-' . strtoupper(bin2hex(random_bytes(6)));
    }
}
