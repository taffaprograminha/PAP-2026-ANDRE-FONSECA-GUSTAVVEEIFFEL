<?php
session_start();
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once 'ligacao.php';

// Devolve os últimos timestamps de alteração para cada área da app
$data = [];

// Última leitura RFID
$r = $conn->query("SELECT UNIX_TIMESTAMP(MAX(data_leitura)) AS ts FROM leituras_rfid");
$data['leituras'] = (int)($r->fetch_assoc()['ts'] ?? 0);

// Última alteração em utilizadores RFID
$r = $conn->query("SELECT UNIX_TIMESTAMP(MAX(created_at)) AS ts FROM users_autorizados");
$data['users'] = (int)($r->fetch_assoc()['ts'] ?? 0);

// Último bloqueio
$r = $conn->query("SELECT UNIX_TIMESTAMP(MAX(criado_em)) AS ts FROM cartoes_bloqueados");
$data['bloqueios'] = (int)($r->fetch_assoc()['ts'] ?? 0);

// Estado das placas (last seen)
$r = $conn->query("SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS ts FROM placas_arduino");
// Se não houver coluna updated_at, usa 0
$row = $r ? $r->fetch_assoc() : ['ts' => 0];
$data['placas'] = (int)($row['ts'] ?? 0);

// Timestamp global = o maior de todos
$data['global'] = max($data['leituras'], $data['users'], $data['bloqueios'], $data['placas']);

echo json_encode($data);
