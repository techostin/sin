<?php
/*
 * Centraliza a definição do dashboard de cada perfil de usuário.
 * Retorna a URL relativa do dashboard conforme o nível e o tipo de usuário.
 *
 * Perfis:
 *   - NIVEL_ADMIN (1)  -> /dashboard/admin
 *   - NIVEL_GESTOR (2) -> /dashboard/gestor
 *   - NIVEL_USUARIO (3) -> split por tipo_usuario:
 *        produtor_agroindustria / agroindustria -> /dashboard/agroindustria
 *        agricultor (padrão)                    -> /dashboard/agricultor
 */

if (!function_exists('dashboard_destino')) {
    function dashboard_destino(): string {
        $nivel = isset($_SESSION['user_nivel']) ? (int)$_SESSION['user_nivel'] : NIVEL_USUARIO;
        $tipo  = isset($_SESSION['user_tipo_usuario']) ? strtolower((string)$_SESSION['user_tipo_usuario']) : '';

        if ($nivel === NIVEL_ADMIN) {
            return SITE_URL . '/dashboard/admin';
        }
        if ($nivel === NIVEL_GESTOR) {
            return SITE_URL . '/dashboard/gestor';
        }
        if ($tipo === 'produtor_agroindustria' || $tipo === 'agroindustria') {
            return SITE_URL . '/dashboard/agroindustria';
        }
        return SITE_URL . '/dashboard/agricultor';
    }
}