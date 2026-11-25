<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Fachada do framework para as páginas dos sistemas.
 *
 * Uso típico em `/assoc/login.php`:
 *   require .../Bootstrap.php;
 *   Plataforma::iniciar('assoc');
 *   Fluxo::login('assoc');
 *
 * As páginas dos sistemas não conhecem SQL, sessão nem regra de perfil:
 * consomem esta API.
 */
final class Plataforma
{
    /** @var array<string, mixed>|null */
    private static ?array $app = null;

    private static ?string $slug = null;

    public static function iniciar(string $slug): void
    {
        if (!Aplicacoes::existe($slug)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Sistema não encontrado.';
            exit;
        }

        self::$slug = $slug;
        self::$app = Aplicacoes::obter($slug);
        Sessao::iniciar($slug);
    }

    /** @return array<string, mixed> */
    public static function app(): array
    {
        if (self::$app === null) {
            throw new \RuntimeException('Plataforma não iniciada. Chame Plataforma::iniciar().');
        }

        return self::$app;
    }

    public static function slug(): string
    {
        return self::$slug ?? '';
    }

    /** @return array<string, mixed>|null */
    public static function usuario(): ?array
    {
        return Sessao::usuario();
    }

    public static function autenticado(): bool
    {
        return self::usuario() !== null;
    }

    /** @param array<string, mixed> $identidade */
    public static function pode(array $identidade, string $permissao): bool
    {
        return Autorizacao::pode($identidade, $permissao);
    }

    /** @param array<string, mixed> $identidade */
    public static function podeAcessar(array $identidade): bool
    {
        return Autorizacao::podeAcessar($identidade, self::slug());
    }

    /**
     * @param array<string, mixed> $identidade
     * @return list<array<string, mixed>>
     */
    public static function funcoes(array $identidade): array
    {
        return Autorizacao::funcoesVisiveis($identidade, self::slug());
    }

    public static function csrf(): string
    {
        return Csrf::token();
    }

    public static function url(string $arquivo = 'index.php'): string
    {
        return Urls::app(self::slug(), $arquivo);
    }

    public static function urlSistema(string $slug): string
    {
        return Urls::sistema($slug);
    }

    public static function urlRaiz(): string
    {
        return Urls::raiz();
    }

    public static function nomeSessao(): string
    {
        return 'SINPORG_' . strtoupper(self::slug());
    }

    public static function caminhoCookie(): string
    {
        return Urls::sistema(self::slug());
    }
}
