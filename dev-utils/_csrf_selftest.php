<?php
// Teste autônomo da lógica CSRF (não precisa de DB).
// Execute: php dev-utils/_csrf_selftest.php
require_once __DIR__ . '/../classes/CSRFProtection.php';
session_start();
$_SESSION = [];

$t1 = CSRFProtection::getToken();
if (!(is_string($t1) && $t1 !== '')) { echo "FAIL: token gerado\n"; exit(1); }
echo "token gerado: " . substr($t1,0,8) . "...\n";

if (CSRFProtection::validateToken($t1) !== true) { echo "FAIL: token valido\n"; exit(1); }
echo "validateToken(token correto): PASS\n";

if (CSRFProtection::validateToken('tokinho_falso') !== false) { echo "FAIL: token invalido\n"; exit(1); }
echo "validateToken(token errado): PASS\n";

if (CSRFProtection::validateToken('') !== false) { echo "FAIL: vazio\n"; exit(1); }
if (CSRFProtection::validateToken(null) !== false) { echo "FAIL: nulo\n"; exit(1); }
echo "validateToken(vazio/null): PASS\n";

$_SERVER['HTTP_X_CSRF_TOKEN'] = $t1;
if (CSRFProtection::tokenFromRequest() !== $t1) { echo "FAIL: header\n"; exit(1); }
echo "tokenFromRequest(header): PASS\n";
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

$_POST[CSRF_TOKEN_NAME] = $t1;
if (CSRFProtection::tokenFromRequest() !== $t1) { echo "FAIL: POST\n"; exit(1); }
echo "tokenFromRequest(POST): PASS\n";
unset($_POST[CSRF_TOKEN_NAME]);

if (CSRFProtection::tokenFromRequest() !== null) { echo "FAIL: ausente\n"; exit(1); }
echo "tokenFromRequest(ausente): PASS\n";

echo "\nALL CSRF TESTS PASSED\n";
