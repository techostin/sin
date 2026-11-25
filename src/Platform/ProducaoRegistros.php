<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * CRUD genérico dos recursos de produção, sempre limitado ao escopo do
 * agricultor (associado) quando ele não é gestor/diretoria/admin.
 */
final class ProducaoRegistros
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $colunasCache = [];

    /** @return array<string, mixed>|null */
    public static function definicao(string $recurso): ?array
    {
        return ProducaoRecursos::definicao($recurso);
    }

    /** @param array<string, mixed> $identidade */
    public static function gerenciaTudo(array $identidade): bool
    {
        if (Autorizacao::ehAdmin($identidade)) {
            return true;
        }

        return array_intersect(
            array_map('strval', (array) ($identidade['papeis'] ?? [])),
            ['gestor_associacao', 'diretoria', 'diretor']
        ) !== [];
    }

    /** @param array<string, mixed> $identidade */
    public static function associadoId(array $identidade): int
    {
        return ($identidade['origem'] ?? '') === 'associados'
            ? (int) ($identidade['id'] ?? 0)
            : (int) ($identidade['associado_id'] ?? 0);
    }

    /** @param array<string, mixed> $def */
    public static function areaDoRecurso(array $def): ?string
    {
        // Propriedades são transversais: servem para vegetal, animal e
        // agroindústria (e para o plano de manejo obrigatório).
        if (($def['chave'] ?? '') === 'producao.propriedades') {
            return null;
        }

        return match ((string) ($def['grupo'] ?? '')) {
            'Produção Vegetal' => 'vegetal',
            'Produção Animal' => 'animal',
            'Agroindústria' => 'agroindustria',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $identidade
     */
    public static function podeAcessarRecurso(string $recurso, array $identidade): bool
    {
        $def = self::definicao($recurso);
        if ($def === null) {
            return false;
        }

        $area = self::areaDoRecurso($def);

        return $area === null || ProducaoAreas::pode($identidade, $area);
    }

    /** @param array<string, mixed> $identidade */
    public static function podeEscrever(array $identidade): bool
    {
        return Autorizacao::pode($identidade, 'caderno.write')
            || Autorizacao::pode($identidade, 'agroindustria.write');
    }

    /**
     * Escrita separada por módulo: agroindústria exige agroindustria.write;
     * vegetal/animal exigem caderno.write. Admin escreve em todos.
     *
     * @param array<string, mixed> $def
     * @param array<string, mixed> $identidade
     */
    public static function podeEscreverRecurso(array $def, array $identidade): bool
    {
        if (Autorizacao::ehAdmin($identidade)) {
            return true;
        }

        $permissao = (string) ($def['grupo'] ?? '') === 'Agroindústria'
            ? 'agroindustria.write'
            : 'caderno.write';

        return Autorizacao::pode($identidade, $permissao);
    }

    /**
     * @param array<string, mixed> $def
     * @param array<string, mixed> $identidade
     * @return array{0: string, 1: array<string, int>}
     */
    private static function escopoWhere(array $def, array $identidade): array
    {
        if (self::gerenciaTudo($identidade)) {
            return ['', []];
        }

        $escopo = (string) ($def['escopo'] ?? 'associado');
        if ($escopo === 'global') {
            return ['', []];
        }

        $associadoId = self::associadoId($identidade);
        if ($associadoId <= 0) {
            return ['1 = 0', []];
        }

        if ($escopo === 'propriedade') {
            return [
                't.propriedade_id IN (
                    SELECT id FROM propriedades WHERE associado_id = :escopo_assoc
                    UNION
                    SELECT propriedade_id FROM propriedade_membros
                     WHERE associado_id = :escopo_membro AND excluido = 0
                )',
                ['escopo_assoc' => $associadoId, 'escopo_membro' => $associadoId],
            ];
        }

        return ['t.associado_id = :escopo_assoc', ['escopo_assoc' => $associadoId]];
    }

    /**
     * @param array<string, mixed> $identidade
     */
    public static function contar(string $recurso, array $identidade): int
    {
        $def = self::definicao($recurso);
        if ($def === null || !self::podeAcessarRecurso($recurso, $identidade)) {
            return 0;
        }

        [$where, $params] = self::escopoWhere($def, $identidade);
        $sql = 'SELECT COUNT(*) FROM `' . (string) $def['tabela'] . '` t';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        try {
            $stmt = Identidades::pdo()->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::contar - ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Busca pública/interna por lote, ordem de produção ou código de rastreio.
     *
     * @param array<string, mixed> $identidade
     * @return list<array<string, mixed>>
     */
    public static function rastrear(string $codigo, array $identidade): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }

        $gerencia = self::gerenciaTudo($identidade);
        $associadoId = self::associadoId($identidade);
        if (!$gerencia && $associadoId <= 0) {
            return [];
        }

        $areas = ProducaoAreas::doUsuario($identidade);
        $partes = [];
        $params = [];
        $indice = 1;

        if (in_array('vegetal', $areas, true)) {
            $filtro = $gerencia ? '' : ' AND associado_id = :assoc' . $indice;
            if (!$gerencia) {
                $params['assoc' . $indice] = $associadoId;
            }
            $params['q' . $indice] = '%' . $codigo . '%';
            $partes[] = "SELECT 'Lote de produção' AS tipo, nome_lote AS codigo, data_producao AS data, id, 'lotes' AS recurso
                          FROM lotes_producao WHERE excluido = 0 AND nome_lote LIKE :q{$indice}{$filtro}";
            $indice++;
        }

        if (in_array('agroindustria', $areas, true)) {
            $filtro = $gerencia ? '' : ' AND associado_id = :assoc' . $indice;
            if (!$gerencia) {
                $params['assoc' . $indice] = $associadoId;
            }
            $params['q' . $indice] = '%' . $codigo . '%';
            $partes[] = "SELECT 'Ordem de produção' AS tipo, lote_processado_final AS codigo, data_processamento AS data, id, 'agro_ordens' AS recurso
                          FROM ordens_producao WHERE excluido = 0 AND lote_processado_final LIKE :q{$indice}{$filtro}";
            $indice++;
        }

        if ($partes === []) {
            return [];
        }

        $filtroRastreio = $gerencia ? '' : ' AND associado_id = :assoc' . $indice;
        if (!$gerencia) {
            $params['assoc' . $indice] = $associadoId;
        }
        $params['q' . $indice] = '%' . $codigo . '%';
        $partes[] = "SELECT CONCAT('Rastreabilidade ', tipo) AS tipo, codigo_publico AS codigo, created_at AS data, id, '' AS recurso
                      FROM rastreabilidade_lotes WHERE codigo_publico LIKE :q{$indice}{$filtroRastreio}";

        $sql = implode(' UNION ALL ', $partes) . ' ORDER BY data DESC LIMIT 50';

        try {
            $stmt = Identidades::pdo()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::rastrear - ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param array<string, mixed> $identidade
     * @return list<array<string, mixed>>
     */
    public static function listar(string $recurso, array $identidade, string $busca = ''): array
    {
        $def = self::definicao($recurso);
        if ($def === null || !self::podeAcessarRecurso($recurso, $identidade)) {
            return [];
        }

        [$where, $params] = self::escopoWhere($def, $identidade);
        $condicoes = [];
        if ($where !== '') {
            $condicoes[] = $where;
        }

        $busca = trim($busca);
        $camposBusca = array_values(array_filter((array) ($def['busca'] ?? []), 'is_string'));
        if ($busca !== '' && $camposBusca !== []) {
            $partes = [];
            foreach ($camposBusca as $indice => $campo) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $campo)) {
                    continue;
                }
                $partes[] = 't.`' . $campo . '` LIKE :busca' . $indice;
                $params['busca' . $indice] = '%' . $busca . '%';
            }
            if ($partes !== []) {
                $condicoes[] = '(' . implode(' OR ', $partes) . ')';
            }
        }

        $ordem = (string) ($def['ordem'] ?? 'id DESC');
        $tabela = (string) $def['tabela'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tabela)) {
            return [];
        }

        $sql = 'SELECT t.* FROM `' . $tabela . '` t';
        if ($condicoes !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }
        $sql .= ' ORDER BY ' . $ordem . ' LIMIT 300';

        try {
            $stmt = Identidades::pdo()->prepare($sql);
            $stmt->execute($params);

            return array_map(
                static fn(array $linha): array => self::normalizarPropriedade($linha),
                $stmt->fetchAll()
            );
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::listar - ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param array<string, mixed> $identidade
     * @return array<string, mixed>|null
     */
    public static function obter(string $recurso, int $id, array $identidade): ?array
    {
        $def = self::definicao($recurso);
        if ($def === null || $id <= 0 || !self::podeAcessarRecurso($recurso, $identidade)) {
            return null;
        }

        [$where, $params] = self::escopoWhere($def, $identidade);
        $params['id'] = $id;
        $sql = 'SELECT t.* FROM `' . (string) $def['tabela'] . '` t WHERE t.id = :id';
        if ($where !== '') {
            $sql .= ' AND ' . $where;
        }
        $sql .= ' LIMIT 1';

        try {
            $stmt = Identidades::pdo()->prepare($sql);
            $stmt->execute($params);
            $linha = $stmt->fetch();

            return is_array($linha) ? self::normalizarPropriedade($linha) : null;
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::obter - ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param array<string, mixed> $def
     * @param array<string, mixed> $identidade
     * @return list<array<string, mixed>>
     */
    public static function campos(array $def, array $identidade): array
    {
        $tabela = (string) ($def['tabela'] ?? '');
        $colunas = self::colunasTabela($tabela);
        $relacoes = (array) ($def['relacoes'] ?? []);
        $rotulos = (array) ($def['rotulos'] ?? []);
        $ajudas = (array) ($def['ajudas'] ?? []);
        $excluir = array_merge(
            ['id', 'uuid', 'associado_id', 'criado_por', 'created_by', 'created_at', 'criado_em', 'updated_at', 'atualizado_em', 'excluido'],
            (array) ($def['excluir_campos'] ?? [])
        );

        $campos = [];
        foreach ($colunas as $coluna) {
            $nome = (string) $coluna['Field'];
            if (in_array($nome, $excluir, true)) {
                continue;
            }

            $tipo = self::tipoCampo($coluna);
            $opcoes = [];
            if ($tabela === 'propriedades' && in_array($nome, ['imagem_principal', 'imagem_secundaria'], true)) {
                $tipo = 'file';
            } elseif ($tabela === 'ocorrencias_manejo' && $nome === 'foto_url') {
                $tipo = 'file';
            } elseif ($tabela === 'propriedades' && $nome === 'estado') {
                $tipo = 'select';
                $opcoes = self::estadosUf();
            } elseif ($tabela === 'propriedades' && $nome === 'cidade') {
                $tipo = 'select';
                $opcoes = [];
            } elseif (isset($relacoes[$nome])) {
                $tipo = 'select';
                $opcoes = self::opcoesRelacao((string) $relacoes[$nome]['recurso'], $identidade);
            } elseif ($tipo === 'select') {
                $opcoes = self::opcoesEnum((string) $coluna['Type']);
            }

            $campos[] = [
                'nome' => $nome,
                'rotulo' => (string) ($rotulos[$nome] ?? self::rotuloCampo($nome)),
                'ajuda' => (string) ($ajudas[$nome] ?? ''),
                'tipo' => $tipo,
                'opcoes' => $opcoes,
                'obrigatorio' => $coluna['Null'] === 'NO'
                    && $coluna['Default'] === null
                    && !str_contains((string) $coluna['Extra'], 'auto_increment')
                    && !str_contains((string) $coluna['Extra'], 'current_timestamp'),
                'coluna' => $coluna,
            ];
        }

        return $campos;
    }

    /**
     * Normaliza IDs legados de estado/cidade de propriedades para UF/nome.
     *
     * @param array<string, mixed> $linha
     * @return array<string, mixed>
     */
    private static function normalizarPropriedade(array $linha): array
    {
        if (!array_key_exists('propriedade', $linha)) {
            return $linha;
        }

        static $estados = [];
        static $cidades = [];
        try {
            $estado = trim((string) ($linha['estado'] ?? ''));
            if ($estado !== '' && ctype_digit($estado)) {
                if (!isset($estados[$estado])) {
                    $stmt = Identidades::pdo()->prepare('SELECT uf FROM estados WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => (int) $estado]);
                    $estados[$estado] = (string) ($stmt->fetchColumn() ?: '');
                }
                if ($estados[$estado] !== '') {
                    $linha['estado'] = $estados[$estado];
                }
            }

            $cidade = trim((string) ($linha['cidade'] ?? ''));
            if ($cidade !== '' && ctype_digit($cidade)) {
                if (!isset($cidades[$cidade])) {
                    $stmt = Identidades::pdo()->prepare('SELECT nome FROM cidades WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => (int) $cidade]);
                    $cidades[$cidade] = (string) ($stmt->fetchColumn() ?: '');
                }
                if ($cidades[$cidade] !== '') {
                    $linha['cidade'] = $cidades[$cidade];
                }
            }
        } catch (\Throwable) {
        }

        return $linha;
    }

    /** @return array<string, string> */
    private static function estadosUf(): array
    {
        try {
            $linhas = Identidades::pdo()->query('SELECT uf, nome FROM estados ORDER BY nome ASC')->fetchAll();
            $opcoes = [];
            foreach ($linhas as $linha) {
                $opcoes[(string) $linha['uf']] = (string) $linha['uf'] . ' — ' . (string) $linha['nome'];
            }

            return $opcoes;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: string, id: int}
     */
    public static function salvar(string $recurso, ?int $id, array $post, array $identidade): array
    {
        $def = self::definicao($recurso);
        if ($def === null || !self::podeAcessarRecurso($recurso, $identidade)) {
            return ['ok' => false, 'erro' => 'Recurso inválido ou fora da sua área de produção.', 'id' => 0];
        }
        if (!self::podeEscreverRecurso($def, $identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode alterar registros de produção.', 'id' => 0];
        }
        if (!empty($def['somente_gestor']) && !self::gerenciaTudo($identidade)) {
            return ['ok' => false, 'erro' => 'Somente a gestão pode alterar este cadastro.', 'id' => 0];
        }

        $campos = self::campos($def, $identidade);
        $valores = [];
        $erros = [];

        foreach ($campos as $campo) {
            $nome = (string) $campo['nome'];
            $tipo = (string) $campo['tipo'];

            if ($tipo === 'checkbox') {
                $valores[$nome] = isset($post[$nome]) && (string) $post[$nome] !== '0' ? 1 : 0;
                continue;
            }
            if ($tipo === 'file') {
                $arquivo = $post[$nome] ?? null;
                if (is_string($arquivo) && $arquivo !== '') {
                    $valores[$nome] = $arquivo;
                }
                continue;
            }

            $valor = $post[$nome] ?? null;
            $valor = is_scalar($valor) ? trim((string) $valor) : '';

            if ($campo['obrigatorio'] && $valor === '') {
                $erros[] = $campo['rotulo'];
                continue;
            }
            if ($valor === '') {
                $valores[$nome] = null;
                continue;
            }

            if ($tipo === 'select') {
                $permitidos = array_map('strval', array_keys($campo['opcoes']));
                if ($permitidos !== [] && !in_array($valor, $permitidos, true)) {
                    $erros[] = 'Valor inválido em ' . $campo['rotulo'];
                    continue;
                }
                $valores[$nome] = $valor;
                continue;
            }

            if ($tipo === 'number') {
                $normalizado = str_replace(['.', ','], ['', '.'], $valor);
                if (str_contains($valor, ',') && !str_contains($valor, '.')) {
                    $normalizado = str_replace(',', '.', $valor);
                }
                $valores[$nome] = is_numeric($normalizado) ? $normalizado : null;
                continue;
            }

            if ($tipo === 'datetime') {
                $valores[$nome] = str_replace('T', ' ', $valor);
                continue;
            }

            $valores[$nome] = $valor;
        }

        if ($erros !== []) {
            return ['ok' => false, 'erro' => 'Preencha os campos obrigatórios: ' . implode(', ', array_unique($erros)), 'id' => 0];
        }

        // Escopo de criação: agricultor grava sempre no próprio associado.
        $colunas = self::colunasTabela((string) $def['tabela']);
        $nomesColunas = array_map(static fn(array $c): string => (string) $c['Field'], $colunas);
        if (in_array('associado_id', $nomesColunas, true) && !self::gerenciaTudo($identidade)) {
            $associadoId = self::associadoId($identidade);
            if ($associadoId <= 0) {
                return ['ok' => false, 'erro' => 'Vincule seu cadastro de associado antes de registrar produções.', 'id' => 0];
            }
            $valores['associado_id'] = $associadoId;
        } elseif (in_array('associado_id', $nomesColunas, true) && !isset($valores['associado_id'])) {
            $associadoId = self::associadoId($identidade);
            $valores['associado_id'] = $associadoId > 0 ? $associadoId : null;
        }

        // Lavouras não têm associado_id: garantia de que a propriedade é do agricultor.
        if ($recurso === 'lavouras' && !self::gerenciaTudo($identidade)) {
            $propriedadeId = (int) ($valores['propriedade_id'] ?? 0);
            if ($propriedadeId <= 0 || !self::propriedadeDoAssociado($propriedadeId, self::associadoId($identidade))) {
                return ['ok' => false, 'erro' => 'Selecione uma propriedade sua.', 'id' => 0];
            }
        }

        $pdo = Identidades::pdo();
        try {
            if ($id !== null && $id > 0) {
                $atual = self::obter($recurso, $id, $identidade);
                if ($atual === null) {
                    return ['ok' => false, 'erro' => 'Registro não encontrado ou fora do seu escopo.', 'id' => 0];
                }
                $sets = [];
                $params = ['id' => $id];
                foreach ($valores as $nome => $valor) {
                    $sets[] = '`' . $nome . '` = :' . $nome;
                    $params[$nome] = $valor;
                }
                $stmt = $pdo->prepare('UPDATE `' . (string) $def['tabela'] . '` SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $stmt->execute($params);
                $registroId = $id;
                $acao = 'PRODUCAO_ATUALIZADO';
            } else {
                $nomes = array_keys($valores);
                $marcadores = array_map(static fn(string $n): string => ':' . $n, $nomes);
                $stmt = $pdo->prepare(
                    'INSERT INTO `' . (string) $def['tabela'] . '` (`' . implode('`, `', $nomes) . '`)
                     VALUES (' . implode(', ', $marcadores) . ')'
                );
                $stmt->execute($valores);
                $registroId = (int) $pdo->lastInsertId();
                $acao = 'PRODUCAO_CRIADO';
            }
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::salvar - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível salvar o registro. Verifique os dados e tente novamente.', 'id' => 0];
        }

        Auditoria::registrar($acao, (int) ($identidade['id'] ?? 0), (string) $def['tabela'], $registroId, 'recurso=' . $recurso, 'producao');

        return ['ok' => true, 'erro' => '', 'id' => $registroId];
    }

    /**
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function excluir(string $recurso, int $id, array $identidade): array
    {
        $def = self::definicao($recurso);
        if ($def === null || $id <= 0 || !self::podeAcessarRecurso($recurso, $identidade)) {
            return ['ok' => false, 'erro' => 'Registro inválido ou fora da sua área de produção.'];
        }
        if (!self::podeEscreverRecurso($def, $identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode excluir registros de produção.'];
        }
        if (!empty($def['sem_exclusao'])) {
            return ['ok' => false, 'erro' => 'Este cadastro não permite exclusão pelo painel.'];
        }
        if (!empty($def['somente_gestor']) && !self::gerenciaTudo($identidade)) {
            return ['ok' => false, 'erro' => 'Somente a gestão pode excluir este cadastro.'];
        }

        $atual = self::obter($recurso, $id, $identidade);
        if ($atual === null) {
            return ['ok' => false, 'erro' => 'Registro não encontrado ou fora do seu escopo.'];
        }

        try {
            $colunas = self::colunasTabela((string) $def['tabela']);
            $nomes = array_map(static fn(array $c): string => (string) $c['Field'], $colunas);
            if (in_array('excluido', $nomes, true)) {
                Identidades::pdo()->prepare('UPDATE `' . (string) $def['tabela'] . '` SET excluido = 1 WHERE id = :id')
                    ->execute(['id' => $id]);
            } else {
                return ['ok' => false, 'erro' => 'Este cadastro não permite exclusão pelo painel.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::excluir - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível excluir o registro.'];
        }

        Auditoria::registrar('PRODUCAO_EXCLUIDO', (int) ($identidade['id'] ?? 0), (string) $def['tabela'], $id, 'recurso=' . $recurso, 'producao');

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * @param array<string, mixed> $identidade
     * @return array<int, string>
     */
    public static function opcoesRelacao(string $recurso, array $identidade): array
    {
        $def = self::definicao($recurso);
        $rel = ProducaoRecursos::relacionamentos()[$recurso] ?? null;
        if ($def === null || $rel === null) {
            return [];
        }

        $campo = (string) $rel['campo'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $campo)) {
            return [];
        }

        [$where, $params] = self::escopoWhere($def, $identidade);
        $sql = 'SELECT t.id, t.`' . $campo . '` AS rotulo FROM `' . (string) $def['tabela'] . '` t';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY rotulo ASC LIMIT 500';

        try {
            $stmt = Identidades::pdo()->prepare($sql);
            $stmt->execute($params);
            $opcoes = [];
            foreach ($stmt->fetchAll() as $linha) {
                $opcoes[(int) $linha['id']] = (string) ($linha['rotulo'] ?: ('#' . $linha['id']));
            }

            return $opcoes;
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::opcoesRelacao - ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string, string> */
    public static function opcoesEnum(string $tipo): array
    {
        if (!preg_match("/^enum\\((.*)\\)$/i", $tipo, $achado)) {
            return [];
        }

        $opcoes = [];
        foreach (explode(',', $achado[1]) as $valor) {
            $valor = trim($valor);
            $valor = trim($valor, "'");
            $valor = str_replace("''", "'", $valor);
            $opcoes[$valor] = $valor;
        }

        return $opcoes;
    }

    /**
     * @param array<string, mixed> $def
     * @param array<string, mixed> $linha
     */
    public static function rotuloRegistro(array $def, array $linha): string
    {
        foreach (['nome', 'nome_lote', 'titulo', 'propriedade', 'razao_social', 'nome_produto_final', 'nome_animal', 'equipamento_linha'] as $campo) {
            if (!empty($linha[$campo])) {
                return (string) $linha[$campo];
            }
        }

        return '#' . (int) ($linha['id'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public static function colunasTabela(string $tabela): array
    {
        if (isset(self::$colunasCache[$tabela])) {
            return self::$colunasCache[$tabela];
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tabela)) {
            return [];
        }

        try {
            $colunas = Identidades::pdo()->query('SHOW COLUMNS FROM `' . $tabela . '`')->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::colunasTabela - ' . $e->getMessage());
            $colunas = [];
        }

        self::$colunasCache[$tabela] = $colunas;

        return $colunas;
    }

    /** @param array<string, mixed> $coluna */
    private static function tipoCampo(array $coluna): string
    {
        $tipo = strtolower((string) ($coluna['Type'] ?? ''));
        $nome = (string) ($coluna['Field'] ?? '');

        if (str_starts_with($tipo, 'enum(')) {
            return 'select';
        }
        if (str_starts_with($tipo, 'tinyint(1)')) {
            return 'checkbox';
        }
        if (str_starts_with($tipo, 'datetime')) {
            return 'datetime';
        }
        if (str_starts_with($tipo, 'date')) {
            return 'date';
        }
        if (str_starts_with($tipo, 'time')) {
            return 'time';
        }
        if (preg_match('/^(int|bigint|smallint|mediumint|tinyint)/', $tipo)) {
            return 'number';
        }
        if (preg_match('/^(decimal|float|double)/', $tipo)) {
            return 'number';
        }
        if (str_contains($tipo, 'text') || in_array($nome, ['descricao', 'observacoes', 'observacoes_origem', 'motivo'], true)) {
            return 'textarea';
        }

        return 'text';
    }

    private static function rotuloCampo(string $nome): string
    {
        $mapa = [
            'nome' => 'Nome',
            'nome_lote' => 'Nome do lote',
            'nome_animal' => 'Nome do animal',
            'nome_instalacao' => 'Instalação',
            'nome_produto_final' => 'Produto final',
            'nome_comum' => 'Nome comum',
            'nome_fantasia' => 'Nome fantasia',
            'razao_social' => 'Razão social',
            'cpf' => 'CPF',
            'cnpj' => 'CNPJ',
            'cep' => 'CEP',
            'uf' => 'UF',
            'kg' => 'kg',
            'm2' => 'm²',
            'ha' => 'ha',
            'id' => 'ID',
            'uuid' => 'UUID',
            'quantidade_kg' => 'Quantidade (kg)',
            'area_total_m2' => 'Área total (m²)',
            'area_total' => 'Área total (ha)',
            'percentual_organico_calculado' => '% orgânico',
            'percentual_organico' => '% orgânico',
            'quantidade_final_obtida' => 'Quantidade final',
            'data_hora_inicio' => 'Início',
            'data_hora_fim' => 'Fim',
            'produto_normatizado' => 'Produto normatizado',
            'tem_sombreamento_abrigo' => 'Tem sombreamento/abrigo',
        ];

        if (isset($mapa[$nome])) {
            return $mapa[$nome];
        }

        $texto = str_replace('_', ' ', $nome);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
    }

    private static function propriedadeDoAssociado(int $propriedadeId, int $associadoId): bool
    {
        if ($propriedadeId <= 0 || $associadoId <= 0) {
            return false;
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT 1 FROM propriedades WHERE id = :id AND associado_id = :assoc
                 UNION
                 SELECT 1 FROM propriedade_membros WHERE propriedade_id = :id AND associado_id = :assoc AND excluido = 0
                 LIMIT 1'
            );
            $stmt->execute(['id' => $propriedadeId, 'assoc' => $associadoId]);

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            error_log('SINPORG ProducaoRegistros::propriedadeDoAssociado - ' . $e->getMessage());

            return false;
        }
    }
}
