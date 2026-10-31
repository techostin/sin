<?php
/**
 * Compatibilidade: este arquivo era uma SEGUNDA fonte de credenciais do banco
 * (root sem senha). Agora delega para a configuração central, que usa o
 * usuário dedicado de menor privilégio definido em config/secrets.php.
 *
 * Mantido apenas para compatibilidade de inclusões antigas; não define
 * constantes próprias para evitar duplicidade.
 */
require_once __DIR__ . '/../../config/config.php';
