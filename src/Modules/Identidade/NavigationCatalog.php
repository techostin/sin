<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

/**
 * Manifesto de navegação por capacidade (L2). A UI só renderiza o que está aqui.
 */
final class NavigationCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     module: string,
     *     permission: string,
     *     surface: list<string>
     * }>
     */
    public function items(): array
    {
        return [
            $this->item('inicio', 'Início', 'L2', 'portal.read', ['web', 'pwa']),
            $this->item('associados', 'Associação', 'D1', 'associados.read', ['web', 'pwa']),
            $this->item('fiscalizacao', 'Fiscalização', 'D3', 'fiscalizacao.read', ['web', 'pwa']),
            $this->item('caderno', 'Caderno de Campo', 'D2', 'caderno.read', ['pwa']),
            $this->item('financeiro', 'Financeiro', 'D4', 'financeiro.read', ['web']),
            $this->item('governanca', 'Governança', 'D8', 'governanca.read', ['web']),
            $this->item('relatorios', 'Relatórios', 'L1', 'relatorios.read', ['web']),
            $this->item('agroindustria', 'Agroindústria', 'D6', 'agroindustria.read', ['web', 'pwa']),
            $this->item('configuracoes', 'Configurações', 'K3', 'config.read', ['web']),
        ];
    }

    /**
     * @param list<string> $capabilities
     * @return list<array{
     *     key: string,
     *     label: string,
     *     module: string,
     *     permission: string,
     *     surface: list<string>
     * }>
     */
    public function forCapabilities(array $capabilities): array
    {
        if (in_array('*', $capabilities, true)) {
            return $this->items();
        }

        $visible = [];
        foreach ($this->items() as $item) {
            if ($this->allows($capabilities, $item['permission'])) {
                $visible[] = $item;
            }
        }

        return $visible;
    }

    /** @param list<string> $capabilities */
    private function allows(array $capabilities, string $permission): bool
    {
        foreach ($capabilities as $capability) {
            if ($capability === $permission) {
                return true;
            }
            if (
                str_ends_with($capability, '.*')
                && str_starts_with($permission, substr($capability, 0, -1))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $surface
     * @return array{
     *     key: string,
     *     label: string,
     *     module: string,
     *     permission: string,
     *     surface: list<string>
     * }
     */
    private function item(
        string $key,
        string $label,
        string $module,
        string $permission,
        array $surface,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'module' => $module,
            'permission' => $permission,
            'surface' => $surface,
        ];
    }
}
