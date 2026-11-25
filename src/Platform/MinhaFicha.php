<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Leitura da ficha de adesão do próprio associado (Minha ficha).
 *
 * Usa a mesma tabela `ficha_adesao`/`ficha_adesao_produtos` do sistema
 * atual, mas sempre filtrando pelo vínculo da identidade autenticada.
 */
final class MinhaFicha
{
    /**
     * @param array<string, mixed> $identidade
     * @return array{ficha: array<string, mixed>|null, produtos: list<array<string, mixed>>}
     */
    public static function carregar(array $identidade): array
    {
        $associadoId = ($identidade['origem'] ?? '') === 'associados'
            ? (int) ($identidade['id'] ?? 0)
            : (int) ($identidade['associado_id'] ?? 0);
        $usuarioId = ($identidade['origem'] ?? '') === 'usuarios' ? (int) ($identidade['id'] ?? 0) : 0;

        $pdo = Identidades::pdo();
        $ficha = null;

        try {
            if ($associadoId > 0) {
                $stmt = $pdo->prepare('SELECT * FROM ficha_adesao WHERE associado_id = :id ORDER BY id DESC LIMIT 1');
                $stmt->execute(['id' => $associadoId]);
                $ficha = $stmt->fetch() ?: null;
            }
            if ($ficha === null && $usuarioId > 0) {
                $stmt = $pdo->prepare('SELECT * FROM ficha_adesao WHERE usuario_id = :id ORDER BY id DESC LIMIT 1');
                $stmt->execute(['id' => $usuarioId]);
                $ficha = $stmt->fetch() ?: null;
            }
        } catch (\Throwable $e) {
            error_log('SINPORG MinhaFicha::carregar - ' . $e->getMessage());
            $ficha = null;
        }

        if (!is_array($ficha)) {
            return ['ficha' => null, 'produtos' => []];
        }

        $produtos = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT produto_cultura, variedade_especie, producao_estimada, unidade, destino_principal
                   FROM ficha_adesao_produtos WHERE ficha_id = :id ORDER BY id ASC LIMIT 20'
            );
            $stmt->execute(['id' => (int) $ficha['id']]);
            $produtos = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG MinhaFicha::produtos - ' . $e->getMessage());
        }

        return ['ficha' => $ficha, 'produtos' => $produtos];
    }

    /**
     * Carrega uma ficha específica para análise da Diretoria/Presidência.
     *
     * @return array{ficha: array<string, mixed>|null, produtos: list<array<string, mixed>>}
     */
    public static function carregarPorId(int $fichaId): array
    {
        if ($fichaId <= 0) {
            return ['ficha' => null, 'produtos' => []];
        }

        $pdo = Identidades::pdo();
        try {
            $stmt = $pdo->prepare('SELECT * FROM ficha_adesao WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $fichaId]);
            $ficha = $stmt->fetch() ?: null;

            if (!is_array($ficha)) {
                return ['ficha' => null, 'produtos' => []];
            }

            $stmt = $pdo->prepare(
                'SELECT produto_cultura, variedade_especie, producao_estimada, unidade, destino_principal
                   FROM ficha_adesao_produtos WHERE ficha_id = :id ORDER BY id ASC LIMIT 20'
            );
            $stmt->execute(['id' => $fichaId]);

            return ['ficha' => $ficha, 'produtos' => $stmt->fetchAll()];
        } catch (\Throwable $e) {
            error_log('SINPORG MinhaFicha::carregarPorId - ' . $e->getMessage());

            return ['ficha' => null, 'produtos' => []];
        }
    }

    public static function rotuloStatus(string $status): string
    {
        return match ($status) {
            'aprovada' => 'Aprovada',
            'pendente' => 'Em análise',
            'recusada' => 'Recusada',
            'rascunho' => 'Rascunho',
            default => 'Sem ficha',
        };
    }

    /** @param array<string, mixed> $ficha */
    public static function rotuloTipo(array $ficha): string
    {
        $mapa = [
            'agricultor_familiar' => 'Agricultor(a) familiar',
            'colaborador' => 'Colaborador(a)',
            'honorario' => 'Honorário(a)',
            'institucional' => 'Institucional',
            'consumidor' => 'Consumidor(a) / Cliente',
        ];

        return $mapa[(string) ($ficha['tipo_associado'] ?? '')] ?? '—';
    }
}
