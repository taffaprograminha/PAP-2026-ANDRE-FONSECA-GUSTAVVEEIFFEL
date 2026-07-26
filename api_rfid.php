<?php
// Nunca expor erros em produção
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

require_once 'ligacao.php';

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Autenticação por API Key da placa
// A key é enviada no header X-API-Key e validada contra placas_arduino.api_key
// Para desenvolvimento local sem key configurada, define RFID_DEV_MODE=1 no ambiente
$headerKey = trim($_SERVER['HTTP_X_API_KEY'] ?? '');
$devMode   = (getenv('RFID_DEV_MODE') === '1');

if ($devMode) {
    // Modo desenvolvimento - aceita sem key (só quando RFID_DEV_MODE=1 no ambiente)
    $keyValida = true;
} elseif ($headerKey === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key em falta']);
    exit;
} else {
    // Valida a key contra a tabela placas_arduino
    $stmt = $conn->prepare("SELECT id FROM placas_arduino WHERE api_key = ? AND ativo = 1 LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro interno']);
        exit;
    }
    $stmt->bind_param("s", $headerKey);
    $stmt->execute();
    $keyValida = ($stmt->get_result()->num_rows > 0);
    $stmt->close();
}

if (!$keyValida) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key inválida ou placa inativa']);
    exit;
}

// Ler corpo JSON ou form-data
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$cartaoUID = strtoupper(trim($body['cartao_uid'] ?? ''));
$placaID   = intval($body['placa_id'] ?? 0);

if ($cartaoUID === '' || $placaID === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'cartao_uid e placa_id são obrigatórios']);
    exit;
}

// Verificar se a placa existe e está ativa
$stmt = $conn->prepare("SELECT id, nome FROM placas_arduino WHERE id = ? AND ativo = 1 LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno (prepare placas)']);
    exit;
}
$stmt->bind_param("i", $placaID);
$stmt->execute();
$placa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$placa) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Placa não encontrada ou inativa']);
    exit;
}

// Atualizar estado da placa de forma segura (prepared statement)
$up = $conn->prepare("UPDATE placas_arduino SET estado = 'online' WHERE id = ? LIMIT 1");
if ($up) {
    $up->bind_param("i", $placaID);
    $up->execute();
    $up->close();
}

// 1. Verificar se cartão está bloqueado
$stmt = $conn->prepare("SELECT id FROM cartoes_bloqueados WHERE cartao_uid = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno (prepare cartoes_bloqueados)']);
    exit;
}
$stmt->bind_param("s", $cartaoUID);
$stmt->execute();
$bloqueado = $stmt->get_result()->num_rows > 0;
$stmt->close();

// 2. Verificar utilizador RFID (só ativos)
$stmt = $conn->prepare("SELECT id, nome, email, ativo FROM users_autorizados WHERE rfid_uid = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno (prepare users_autorizados)']);
    exit;
}
$stmt->bind_param("s", $cartaoUID);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Determinar status (permitido / negado / desconhecido)
$userAutorizado = null;
$userID = null;

if ($bloqueado) {
    // Cartão explicitamente bloqueado - sempre negado
    $status = 'negado';
    if ($userRow) {
        $userID = (int)$userRow['id'];
        $userAutorizado = $userRow;
    }
} elseif ($userRow && (int)$userRow['ativo'] === 1) {
    // Utilizador ativo e cartão não bloqueado - acesso permitido
    $status = 'permitido';
    $userID = (int)$userRow['id'];
    $userAutorizado = $userRow;
} elseif ($userRow && (int)$userRow['ativo'] === 0) {
    // Utilizador existe mas está desativado - acesso negado
    $status = 'negado';
    $userID = (int)$userRow['id'];
    $userAutorizado = $userRow;
} else {
    // Cartão desconhecido
    $status = 'desconhecido';
}

// Registar leitura na BD (prepared statements, branches para user_id null)
if ($userID !== null) {
    $stmt = $conn->prepare("
        INSERT INTO leituras_rfid (placa_id, cartao_uid, user_id, status, data_leitura)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro interno (insert com user)']);
        exit;
    }
    $stmt->bind_param("isis", $placaID, $cartaoUID, $userID, $status);
} else {
    $stmt = $conn->prepare("
        INSERT INTO leituras_rfid (placa_id, cartao_uid, user_id, status, data_leitura)
        VALUES (?, ?, NULL, ?, NOW())
    ");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro interno (insert sem user)']);
        exit;
    }
    $stmt->bind_param("iss", $placaID, $cartaoUID, $status);
}

if (!$stmt->execute()) {
    error_log('api_rfid: erro ao gravar leitura: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao gravar leitura']);
    $stmt->close();
    exit;
}

$leituraID = $conn->insert_id;
$stmt->close();

// Resposta JSON para o Arduino / Bridge
$resposta = [
    'success'    => true,
    'leitura_id' => $leituraID,
    'status'     => $status,
    'acesso'     => ($status === 'permitido'),
    'utilizador' => $userAutorizado ? $userAutorizado['nome'] : null,
    'placa'      => $placa['nome'],
    'timestamp'  => date('Y-m-d H:i:s'),
];

http_response_code(200);
echo json_encode($resposta);