<?php

declare(strict_types=1);

namespace App\Kernel\FeatureFlags;

use App\Kernel\Contracts\FeatureFlags;
use PDO;

final class DatabaseFeatureFlags implements FeatureFlags
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isEnabled(string $key): bool
    {
        $statement = $this->pdo->prepare('SELECT ativo FROM feature_flags WHERE chave = :chave LIMIT 1');
        $statement->execute(['chave' => $key]);
        $value = $statement->fetchColumn();

        return $value !== false && (int) $value === 1;
    }

    /** @return array<string, bool> */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT chave, ativo FROM feature_flags');
        if ($statement === false) {
            return [];
        }

        $flags = [];
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $row) {
            $key = isset($row['chave']) ? (string) $row['chave'] : '';
            if ($key !== '') {
                $flags[$key] = (int) ($row['ativo'] ?? 0) === 1;
            }
        }

        return $flags;
    }
}
