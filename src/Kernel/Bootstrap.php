<?php

declare(strict_types=1);

namespace App\Kernel;

use App\Kernel\Audit\DatabaseAuditLogger;
use App\Kernel\Contracts\AuditLogger;
use App\Kernel\Contracts\FeatureFlags;
use App\Kernel\Documents\DocumentNumberGenerator;
use App\Kernel\FeatureFlags\DatabaseFeatureFlags;
use App\Kernel\Http\Request;
use App\Kernel\Persistence\Connection;
use App\Kernel\Support\Logger;

/**
 * Bootstrap único da API modular (F1).
 * Carrega a configuração existente, mas não inicia sessão (API stateless por padrão).
 */
final class Bootstrap
{
    public static function api(): Container
    {
        if (!defined('SINPORG_SKIP_SESSION')) {
            define('SINPORG_SKIP_SESSION', true);
        }

        require_once dirname(__DIR__, 2) . '/config/config.php';

        $root = dirname(__DIR__, 2);
        $container = new Container();

        $container->set(Logger::class, static fn (Container $c): object => new Logger($root . '/logs/app.log'));
        $container->set(Request::class, static fn (Container $c): object => Request::fromGlobals());
        $container->set(
            FeatureFlags::class,
            static fn (Container $c): object => new DatabaseFeatureFlags(Connection::pdo())
        );
        $container->set(
            AuditLogger::class,
            static fn (Container $c): object => new DatabaseAuditLogger(Connection::pdo())
        );
        $container->set(
            DocumentNumberGenerator::class,
            static fn (Container $c): object => new DocumentNumberGenerator(Connection::pdo())
        );

        return $container;
    }
}
