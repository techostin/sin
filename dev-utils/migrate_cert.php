<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

$db->exec("CREATE TABLE IF NOT EXISTS certificadoras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20),
    tipo ENUM('Auditoria', 'SPG', 'OCS') DEFAULT 'SPG',
    registro_mapa VARCHAR(100),
    estado VARCHAR(2) DEFAULT 'RS',
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

try {
    $db->exec("ALTER TABLE usuarios ADD COLUMN certificadora_id INT NULL, ADD CONSTRAINT fk_usu_cert FOREIGN KEY (certificadora_id) REFERENCES certificadoras(id) ON DELETE SET NULL");
} catch (PDOException $e) {
    echo "Column/Constraint probably exists: " . $e->getMessage() . "\n";
}

$db->exec("INSERT IGNORE INTO certificadoras (nome, tipo, estado) VALUES 
('Associação Rede Ecovida de Certificação Participativa', 'SPG', 'RS'),
('Associação dos Produtores da Rede Agroecológica Metropolitana (RAMA)', 'SPG', 'RS'),
('OPAC Litoral Norte', 'SPG', 'RS'),
('Centro Ecológico', 'SPG', 'RS')");

echo "Migration Applied!";
