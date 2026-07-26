<?php
session_start();
require_once "ligacao.php";
require_once __DIR__ . '/same_origin.php';
exigir_mesmo_origin();

// Autenticação obrigatória
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    header("Location: login.php");
    exit;
}

// Só aceita POST (evita trigger via link/imagem - CSRF básico)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header("Location: settings.php");
    exit;
}

$email = $_SESSION['email'];

// Prepared statement - sem interpolação de strings
$st = $conn->prepare("UPDATE users SET two_factor_enabled = IF(two_factor_enabled = 1, 0, 1) WHERE email = ?");
if (!$st) {
    http_response_code(500);
    header("Location: settings.php?erro=interno");
    exit;
}
$st->bind_param("s", $email);
$st->execute();
$st->close();

header("Location: settings.php");
exit;
