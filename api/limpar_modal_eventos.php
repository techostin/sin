<?php
// Iniciar a sessão
session_start();

// Remover a flag que exibe o modal
unset($_SESSION['mostrar_modal_eventos']);

// Retornar resposta de sucesso
header('Content-Type: application/json');
echo json_encode(['success' => true]);
