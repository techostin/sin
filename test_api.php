<?php
require_once __DIR__ . '/config/config.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_nivel'] = 1;
$_SESSION['user_nome'] = 'Admin Teste';
$_SESSION['user_tipo'] = 'gestor';
$_SESSION['user_table'] = 'usuarios';
session_write_close();
$session_id = session_id();

$patch_data = json_encode(['id' => 1, 'status' => 'Em Correção']);

$ch = curl_init('http://localhost/app/api/ocorrencias.php');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_POSTFIELDS, $patch_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: PHPSESSID=' . $session_id
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";

if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch);
}
curl_close($ch);
