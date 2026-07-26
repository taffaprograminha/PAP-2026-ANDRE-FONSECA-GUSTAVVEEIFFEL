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

$action = $_POST['action'] ?? '';

switch ($action) {

    // Ativar / desativar utilizador RFID
    case 'toggle_ativo':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

        $st = $conn->prepare("UPDATE users_autorizados SET ativo = IF(ativo=1, 0, 1) WHERE id = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $st->close();

        $res = $conn->prepare("SELECT ativo FROM users_autorizados WHERE id = ?");
        $res->bind_param("i", $id);
        $res->execute();
        $row = $res->get_result()->fetch_assoc();
        $res->close();

        echo json_encode(['success' => true, 'ativo' => (int)$row['ativo']]);
        break;

    // Bloquear / desbloquear cartão (com motivo)
    case 'toggle_bloqueado':
        $rfid   = strtoupper(trim($_POST['rfid_uid'] ?? ''));
        $motivo = trim($_POST['motivo'] ?? 'Bloqueado pelo administrador');
        if ($rfid === '' || !ctype_xdigit($rfid)) {
            http_response_code(400); echo json_encode(['success'=>false,'error'=>'UID inválido']); exit;
        }

        $check = $conn->prepare("SELECT id FROM cartoes_bloqueados WHERE cartao_uid = ? LIMIT 1");
        $check->bind_param("s", $rfid);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $del = $conn->prepare("DELETE FROM cartoes_bloqueados WHERE cartao_uid = ?");
            $del->bind_param("s", $rfid);
            $del->execute();
            $del->close();
            echo json_encode(['success' => true, 'bloqueado' => false]);
        } else {
            $ins = $conn->prepare("INSERT INTO cartoes_bloqueados (cartao_uid, motivo, criado_em) VALUES (?, ?, NOW())");
            $ins->bind_param("ss", $rfid, $motivo);
            $ins->execute();
            $ins->close();
            echo json_encode(['success' => true, 'bloqueado' => true]);
        }
        break;

    // Editar utilizador
    case 'update_user':
        $id    = intval($_POST['id'] ?? 0);
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($id <= 0 || $nome === '') {
            http_response_code(400); echo json_encode(['success'=>false,'error'=>'Dados inválidos']); exit;
        }
        if (preg_match('/[^A-Za-zÀ-ÿ\s]/u', $nome)) {
            http_response_code(400); echo json_encode(['success'=>false,'error'=>'Nome inválido (só letras e espaços)']); exit;
        }

        $st = $conn->prepare("UPDATE users_autorizados SET nome = ?, email = ? WHERE id = ?");
        if (!$st) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'Erro interno']); exit; }
        $st->bind_param("ssi", $nome, $email, $id);
        $st->execute();
        $st->close();

        echo json_encode(['success' => true, 'nome' => $nome, 'email' => $email]);
        break;

    // Eliminar utilizador
    case 'delete_user':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

        // Guardar rfid para também apagar de cartoes_bloqueados
        $st = $conn->prepare("SELECT rfid_uid FROM users_autorizados WHERE id = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Utilizador não encontrado']); exit; }

        // Apaga de cartoes_bloqueados se existir
        $del = $conn->prepare("DELETE FROM cartoes_bloqueados WHERE cartao_uid = ?");
        $del->bind_param("s", $row['rfid_uid']);
        $del->execute();
        $del->close();

        // Apaga o utilizador (leituras ficam com user_id NULL via FK SET NULL ou ficam - não apaga leituras)
        $del2 = $conn->prepare("DELETE FROM users_autorizados WHERE id = ?");
        $del2->bind_param("i", $id);
        $del2->execute();
        $del2->close();

        echo json_encode(['success' => true]);
        break;

    // Adicionar utilizador
    case 'add_user':
        $nome  = trim($_POST['nome']    ?? '');
        $email = trim($_POST['email']   ?? '');
        $rfid  = strtoupper(preg_replace('/[^A-F0-9]/i', '', trim($_POST['rfid_uid'] ?? '')));

        if ($nome === '' || preg_match('/[^A-Za-zÀ-ÿ\s]/u', $nome)) {
            echo json_encode(['success'=>false,'error'=>'Nome inválido (só letras e espaços)']); exit;
        }
        if ($rfid === '' || !ctype_xdigit($rfid)) {
            echo json_encode(['success'=>false,'error'=>'RFID UID inválido']); exit;
        }

        $chk = $conn->prepare("SELECT id FROM users_autorizados WHERE rfid_uid = ? LIMIT 1");
        $chk->bind_param("s", $rfid);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success'=>false,'error'=>'Já existe um utilizador com esse UID']); exit;
        }
        $chk->close();

        $ins = $conn->prepare("INSERT INTO users_autorizados (nome, email, rfid_uid, ativo, created_at) VALUES (?, ?, ?, 1, NOW())");
        $ins->bind_param("sss", $nome, $email, $rfid);
        if ($ins->execute()) {
            echo json_encode(['success'=>true,'id'=>$conn->insert_id,'nome'=>$nome,'rfid_uid'=>$rfid]);
        } else {
            echo json_encode(['success'=>false,'error'=>'Erro ao inserir']);
        }
        $ins->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação desconhecida']);
}
