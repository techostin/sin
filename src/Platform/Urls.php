<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * URLs da plataforma, derivadas do caminho do script em execução.
 *
 * Funciona tanto em `/app/assoc/...` (XAMPP) quanto na raiz do domínio,
 * sem valor fixo no código.
 */
final class Urls
{
    public static function base(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '') {
            return '';
        }

        $diretorioApp = rtrim(dirname($script), '/');   // /app/assoc
        $base = rtrim(dirname($diretorioApp), '/');     // /app

        return $base === '/' || $base === '.' ? '' : $base;
    }

    /** URL de um arquivo dentro de um dos sistemas. */
    public static function app(string $slug, string $arquivo = 'index.php'): string
    {
        return self::base() . '/' . rawurlencode($slug) . '/' . ltrim($arquivo, '/');
    }

    /** URL da pasta de um sistema (para links de "trocar de sistema"). */
    public static function sistema(string $slug): string
    {
        return self::base() . '/' . rawurlencode($slug) . '/';
    }

    /**
     * URL do site público (landing).
     *
     * A raiz /app/ continua servindo o sistema atual, então a landing é
     * acessada explicitamente por /app/inicio.php.
     */
    public static function raiz(): string
    {
        $base = self::base();

        return ($base === '' ? '' : $base) . '/inicio.php';
    }

    /** URL de um asset da landing (Tailwind, fonte, imagens). */
    public static function asset(string $caminho): string
    {
        return self::base() . '/landing/' . ltrim($caminho, '/');
    }
}
