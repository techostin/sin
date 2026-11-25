<?php

declare(strict_types=1);

namespace App\Modules\Health;

use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Persistence\Connection;
use App\Kernel\Support\Logger;
use Throwable;

final class HealthController
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function __invoke(Request $request): Response
    {
        $database = 'ok';
        $status = 200;

        try {
            Connection::pdo()->query('SELECT 1');
        } catch (Throwable $exception) {
            $database = 'error';
            $status = 503;
            $this->logger->error('health.database', ['exception' => $exception::class]);
        }

        return Response::json(
            [
                'status' => $status === 200 ? 'ok' : 'degraded',
                'php' => PHP_VERSION,
                'database' => $database,
                'time' => date(DATE_ATOM),
            ],
            $status,
            ['requestId' => $request->requestId]
        );
    }
}
