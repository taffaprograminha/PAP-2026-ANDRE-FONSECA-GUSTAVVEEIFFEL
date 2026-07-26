<?php
session_start();
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once 'ligacao.php';
require_once __DIR__ . '/same_origin.php';
exigir_mesmo_origin();

// GET: obter dados de uma placa (para edição)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

        $st = $conn->prepare("SELECT id, nome, localizacao, ip_address, estado, api_key FROM placas_arduino WHERE id = ? LIMIT 1");
        $st->bind_param("i", $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Placa não encontrada']); exit; }
        echo json_encode($row);
        exit;
    }

    // GET delete removido - delete agora exige POST com password

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']);
    exit;
}

// POST: criar ou atualizar placa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nome       = trim($_POST['nome']       ?? '');
        $local      = trim($_POST['localizacao'] ?? '');
        $ip         = trim($_POST['ip_address'] ?? '');
        $estado     = $_POST['estado'] ?? 'offline';

        if ($nome === '') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Nome obrigatório']); exit; }
        if (!in_array($estado, ['online','offline'])) $estado = 'offline';

        // Gera uma api_key única para esta placa
        $api_key = bin2hex(random_bytes(16));

        $st = $conn->prepare("INSERT INTO placas_arduino (nome, localizacao, ip_address, estado, api_key, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
        $st->bind_param("sssss", $nome, $local, $ip, $estado, $api_key);

        if (!$st->execute()) {
            http_response_code(500); echo json_encode(['success'=>false,'error'=>'Erro ao criar placa']); exit;
        }
        $newId = $conn->insert_id;
        $st->close();

        echo json_encode(['success' => true, 'id' => $newId, 'api_key' => $api_key]);
        exit;
    }

    if ($action === 'delete') {
        $id  = intval($_POST['id']       ?? 0);
        $pwd = trim($_POST['password']   ?? '');

        if ($id <= 0)  { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
        if ($pwd === '') { echo json_encode(['success'=>false,'error'=>'Password obrigatória']); exit; }

        // Verifica a password do admin (utilizador da sessão)
        $email = $_SESSION['email'];
        $st = $conn->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $email);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row || !password_verify($pwd, $row['password'])) {
            echo json_encode(['success' => false, 'error' => 'Password incorreta.']);
            exit;
        }

        // Password correta - marca a placa como inativa (preserva histórico)
        $st = $conn->prepare("UPDATE placas_arduino SET ativo = 0, updated_at = NOW() WHERE id = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $st->close();

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'update') {
        $id     = intval($_POST['id']           ?? 0);
        $nome   = trim($_POST['nome']           ?? '');
        $local  = trim($_POST['localizacao']    ?? '');
        $ip     = trim($_POST['ip_address']     ?? '');
        $estado = $_POST['estado']              ?? 'offline';

        if ($id <= 0 || $nome === '') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Dados inválidos']); exit; }
        if (!in_array($estado, ['online','offline'])) $estado = 'offline';

        $st = $conn->prepare("UPDATE placas_arduino SET nome=?, localizacao=?, ip_address=?, estado=?, updated_at=NOW() WHERE id=?");
        $st->bind_param("ssssi", $nome, $local, $ip, $estado, $id);

        if (!$st->execute()) {
            http_response_code(500); echo json_encode(['success'=>false,'error'=>'Erro ao atualizar']); exit;
        }
        $st->close();

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ação desconhecida']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método não permitido']);
