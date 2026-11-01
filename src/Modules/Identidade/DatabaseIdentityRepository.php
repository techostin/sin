<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

use App\Kernel\Persistence\Repository;
use PDO;

final class DatabaseIdentityRepository extends Repository implements IdentityRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /** @return array{user: array<string, mixed>, roles: list<array{code: string, scope_type: string, scope_id: int}>, permissions: list<string>, association_ids: list<int>}|null */
    public function load(int $userId): ?array
    {
        $user = $this->fetchOne(
            'SELECT id, nome, sobrenome, email, nivel, tipo, tipo_usuario, status,
                    certificadora_id, associado_id, area_producao
             FROM usuarios WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        if ($user === null) {
            return null;
        }

        $roles = $this->loadRoles($userId);
        if ($roles === []) {
            $roles = [$this->legacyRole($user)];
        }

        return [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $this->loadPermissions($userId),
            'association_ids' => $this->loadAssociationIds($userId),
        ];
    }

    /** @return list<array{code: string, scope_type: string, scope_id: int}> */
    private function loadRoles(int $userId): array
    {
        $rows = $this->fetchAll(
            'SELECT p.codigo, up.escopo_tipo, up.escopo_id
             FROM usuario_papel up
             JOIN papeis p ON p.id = up.papel_id
             WHERE up.usuario_id = :id AND up.ativo = 1
             ORDER BY p.codigo',
            ['id' => $userId]
        );

        $roles = [];
        foreach ($rows as $row) {
            $roles[] = [
                'code' => (string) ($row['codigo'] ?? ''),
                'scope_type' => (string) ($row['escopo_tipo'] ?? 'global'),
                'scope_id' => (int) ($row['escopo_id'] ?? 0),
            ];
        }

        return $roles;
    }

    /** @return list<string> */
    private function loadPermissions(int $userId): array
    {
        $rows = $this->fetchAll(
            'SELECT DISTINCT perm.codigo
             FROM usuario_papel up
             JOIN papel_permissao pp ON pp.papel_id = up.papel_id
             JOIN permissoes perm ON perm.id = pp.permissao_id
             WHERE up.usuario_id = :id AND up.ativo = 1
             ORDER BY perm.codigo',
            ['id' => $userId]
        );

        $permissions = [];
        foreach ($rows as $row) {
            $code = (string) ($row['codigo'] ?? '');
            if ($code !== '') {
                $permissions[] = $code;
            }
        }

        return $permissions;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{code: string, scope_type: string, scope_id: int}
     */
    private function legacyRole(array $user): array
    {
        $nivel = (int) ($user['nivel'] ?? 3);
        $code = match ($nivel) {
            1 => 'admin',
            2 => 'gestor_associacao',
            default => 'produtor',
        };

        return ['code' => $code, 'scope_type' => 'global', 'scope_id' => 0];
    }

    /** @return list<int> */
    private function loadAssociationIds(int $userId): array
    {
        $ids = [];

        $scoped = $this->fetchAll(
            "SELECT escopo_id FROM usuario_papel
             WHERE usuario_id = :id AND ativo = 1 AND escopo_tipo = 'associacao' AND escopo_id > 0",
            ['id' => $userId]
        );
        foreach ($scoped as $row) {
            $ids[] = (int) $row['escopo_id'];
        }

        $certifier = $this->fetchAll(
            'SELECT DISTINCT ca.associacao_id
             FROM usuario_papel up
             JOIN certificadora_associacoes ca ON ca.certificadora_id = up.escopo_id
             WHERE up.usuario_id = :id AND up.ativo = 1 AND up.escopo_tipo = \'certificadora\'',
            ['id' => $userId]
        );
        foreach ($certifier as $row) {
            $ids[] = (int) $row['associacao_id'];
        }

        return array_values(array_unique($ids));
    }
}
