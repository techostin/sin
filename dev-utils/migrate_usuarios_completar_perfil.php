<?php
/**
 * Migração: adiciona colunas faltantes à tabela `usuarios` para que
 * o formulário completo de /meu-perfil funcione para qualquer usuário.
 *
 * Colunas adicionadas (espelhando a tabela `associados`):
 * sobrenome, data_nascimento, cpf, rg, registro_cnpo, foto_url,
 * endereco, numero, complemento, bairro, cidade, estado, cep
 *
 * Também corrige o tipo de `telefone` de int(11) para varchar(15)
 * para suportar a máscara (00) 00000-0000.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

// Lista de colunas a adicionar: [nome, definição SQL]
$colunas = [
    'sobrenome'       => "ALTER TABLE usuarios ADD COLUMN sobrenome VARCHAR(100) NULL AFTER nome",
    'data_nascimento' => "ALTER TABLE usuarios ADD COLUMN data_nascimento DATE NULL AFTER sobrenome",
    'cpf'             => "ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) NULL AFTER data_nascimento",
    'rg'              => "ALTER TABLE usuarios ADD COLUMN rg VARCHAR(20) NULL AFTER cpf",
    'registro_cnpo'   => "ALTER TABLE usuarios ADD COLUMN registro_cnpo INT(11) NULL AFTER rg",
    'foto_url'        => "ALTER TABLE usuarios ADD COLUMN foto_url VARCHAR(200) NULL AFTER registro_cnpo",
    'endereco'        => "ALTER TABLE usuarios ADD COLUMN endereco VARCHAR(200) NULL AFTER foto_url",
    'numero'          => "ALTER TABLE usuarios ADD COLUMN numero VARCHAR(10) NULL AFTER endereco",
    'complemento'     => "ALTER TABLE usuarios ADD COLUMN complemento VARCHAR(100) NULL AFTER numero",
    'bairro'          => "ALTER TABLE usuarios ADD COLUMN bairro VARCHAR(100) NULL AFTER complemento",
    'cidade'          => "ALTER TABLE usuarios ADD COLUMN cidade VARCHAR(100) NULL AFTER bairro",
    'estado'          => "ALTER TABLE usuarios ADD COLUMN estado CHAR(2) NULL AFTER cidade",
    'cep'             => "ALTER TABLE usuarios ADD COLUMN cep VARCHAR(9) NULL AFTER estado",
];

// Obter colunas atuais
$stmt = $db->query("SHOW COLUMNS FROM usuarios");
$existentes = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $existentes[$c['Field']] = $c['Type'];
}

echo "=== Migração: tabela usuarios ===\n\n";

// Adicionar colunas faltantes
foreach ($colunas as $nome => $sql) {
    if (!isset($existentes[$nome])) {
        try {
            $db->exec($sql);
            echo "[OK] Adicionada coluna: {$nome}\n";
        } catch (PDOException $e) {
            echo "[ERRO] Falha ao adicionar {$nome}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "[IGNORADO] Coluna já existe: {$nome}\n";
    }
}

// Corrigir tipo de telefone (int -> varchar)
if (isset($existentes['telefone']) && strpos($existentes['telefone'], 'int') !== false) {
    try {
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN telefone VARCHAR(15) NULL");
        echo "[OK] Coluna telefone alterada de int para varchar(15)\n";
    } catch (PDOException $e) {
        echo "[ERRO] Falha ao alterar telefone: " . $e->getMessage() . "\n";
    }
} else {
    echo "[IGNORADO] telefone já é varchar ou não existe\n";
}

// Corrigir enum de status para incluir 'pendente' (mesmo do formulário de perfil)
if (isset($existentes['status']) && strpos($existentes['status'], 'pendente') === false) {
    try {
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN status ENUM('ativo','inativo','pendente') NOT NULL DEFAULT 'ativo'");
        echo "[OK] Coluna status alterada para incluir 'pendente'\n";
    } catch (PDOException $e) {
        echo "[ERRO] Falha ao alterar status: " . $e->getMessage() . "\n";
    }
} else {
    echo "[IGNORADO] status já inclui 'pendente' ou não existe\n";
}

echo "\n=== Migração concluída ===\n";
