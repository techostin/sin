<?php

declare(strict_types=1);

namespace App\Platform;

/** Relatórios e rastreabilidade do painel Produção do Agricultor. */
final class FluxoProducaoFerramentas
{
    public static function relatorios(string $slug): void
    {
        [$app, $identidade] = self::contexto($slug);
        $recursos = ProducaoRecursos::todos();
        $totais = [];
        foreach ($recursos as $recurso => $def) {
            if (!ProducaoRegistros::podeAcessarRecurso((string) $recurso, $identidade)) {
                continue;
            }
            $totais[$recurso] = [
                'def' => $def,
                'total' => ProducaoRegistros::contar($recurso, $identidade),
            ];
        }

        UiProducaoFerramentas::relatorios($slug, $app, $identidade, $totais);
    }

    public static function rastreabilidade(string $slug): void
    {
        [$app, $identidade] = self::contexto($slug);
        $codigo = trim((string) ($_GET['q'] ?? ''));
        $resultados = ProducaoRegistros::rastrear($codigo, $identidade);
        UiProducaoFerramentas::rastreabilidade($slug, $app, $identidade, $codigo, $resultados);
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private static function contexto(string $slug): array
    {
        Plataforma::iniciar($slug);

        if ($slug !== 'producao') {
            header('Location: ' . Urls::app($slug, 'index.php'), true, 303);
            exit;
        }

        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }
        if (!Autorizacao::podeAcessar($identidade, $slug)) {
            Ui::erro(403, 'Sem acesso', 'Seu perfil não tem acesso ao painel de produção.');
            exit;
        }

        return [Plataforma::app(), $identidade];
    }
}
