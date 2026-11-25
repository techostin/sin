<?php
// Iniciar a sessão
session_start();

// Seguranca: endpoint so faz sentido para usuario logado (chamado por includes/header.php,
// que ja exige login). Negar anonimos evita manipulacao da flag de sessao por terceiros.
require_once __DIR__ . '/../classes/ApiAuth.php';
ApiAuth::exigirLogin();

// Remover a flag que exibe o modal
unset($_SESSION['mostrar_modal_eventos']);

// Retornar resposta de sucesso
header('Content-Type: application/json');
echo json_encode(['success' => true]);
