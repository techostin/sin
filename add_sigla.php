<?php
require 'config/config.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->query("SHOW COLUMNS FROM certificadoras LIKE 'sigla'");
if($stmt->rowCount()==0){
    $pdo->exec("ALTER TABLE certificadoras ADD COLUMN sigla VARCHAR(20) AFTER nome");
    echo "Column added\n";
} else{
    echo "Column exists\n";
}
