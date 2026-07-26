<?php
session_start();

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once 'ligacao.php';
require_once __DIR__ . '/same_origin.php';
exigir_mesmo_origin();

$type   = $_POST['type']     ?? '';
$params = json_decode($_POST['params'] ?? '{}', true) ?: [];
$pwd    = $_POST['password'] ?? '';

// Verificação de password obrigatória
if ($pwd === '') {
    echo json_encode(['success' => false, 'error' => 'Password obrigatória.', 'need_password' => true]);
    exit;
}

$email = $_SESSION['email'];
$st = $conn->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
$st->bind_param("s", $email);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row || !password_verify($pwd, $row['password'])) {
    echo json_encode(['success' => false, 'error' => 'Password incorreta.', 'wrong_password' => true]);
    exit;
}

// Valida o tipo de ação
$allowed = ['block_card','unblock_card','activate_user','deactivate_user','delete_user','add_user','update_user'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ação não permitida']);
    exit;
}

switch ($type) {

    // Bloquear cartão
    case 'block_card':
        $rfid   = strtoupper(preg_replace('/[^A-F0-9]/i', '', $params['rfid_uid'] ?? ''));
        $motivo = trim($params['motivo'] ?? 'Bloqueado via assistente IA');

        if (strlen($rfid) < 4) {
            echo json_encode(['success'=>false,'error'=>'UID inválido']); exit;
        }

        $chk = $conn->prepare("SELECT id FROM cartoes_bloqueados WHERE cartao_uid = ? LIMIT 1");
        $chk->bind_param("s", $rfid); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success'=>false,'error'=>'Cartão já está bloqueado']); exit;
        }
        $chk->close();

        $st = $conn->prepare("INSERT INTO cartoes_bloqueados (cartao_uid, motivo, criado_em) VALUES (?, ?, NOW())");
        $st->bind_param("ss", $rfid, $motivo);
        $st->execute(); $st->close();

        echo json_encode(['success'=>true, 'message'=>"Cartão <code>$rfid</code> bloqueado com sucesso."]);
        break;

    // Desbloquear cartão
    case 'unblock_card':
        $rfid = strtoupper(preg_replace('/[^A-F0-9]/i', '', $params['rfid_uid'] ?? ''));

        if (strlen($rfid) < 4) {
            echo json_encode(['success'=>false,'error'=>'UID inválido']); exit;
        }

        $st = $conn->prepare("DELETE FROM cartoes_bloqueados WHERE cartao_uid = ?");
        $st->bind_param("s", $rfid);
        $st->execute();
        $affected = $st->affected_rows;
        $st->close();

        if ($affected === 0) {
            echo json_encode(['success'=>false,'error'=>'Cartão não estava bloqueado']);
        } else {
            echo json_encode(['success'=>true, 'message'=>"Cartão <code>$rfid</code> desbloqueado."]);
        }
        break;

    // Ativar utilizador
    case 'activate_user':
        $id   = intval($params['user_id'] ?? 0);
        $nome = htmlspecialchars($params['nome'] ?? "ID $id", ENT_QUOTES);

        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

        $st = $conn->prepare("UPDATE users_autorizados SET ativo = 1 WHERE id = ?");
        $st->bind_param("i", $id); $st->execute(); $st->close();

        echo json_encode(['success'=>true, 'message'=>"Utilizador <strong>$nome</strong> ativado."]);
        break;

    // Desativar utilizador
    case 'deactivate_user':
        $id   = intval($params['user_id'] ?? 0);
        $nome = htmlspecialchars($params['nome'] ?? "ID $id", ENT_QUOTES);

        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

        $st = $conn->prepare("UPDATE users_autorizados SET ativo = 0 WHERE id = ?");
        $st->bind_param("i", $id); $st->execute(); $st->close();

        echo json_encode(['success'=>true, 'message'=>"Utilizador <strong>$nome</strong> desativado."]);
        break;

    // Eliminar utilizador
    case 'delete_user':
        $id   = intval($params['user_id'] ?? 0);
        $nome = htmlspecialchars($params['nome'] ?? "ID $id", ENT_QUOTES);

        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

        // Busca rfid para limpar cartoes_bloqueados
        $st = $conn->prepare("SELECT rfid_uid FROM users_autorizados WHERE id = ? LIMIT 1");
        $st->bind_param("i", $id); $st->execute();
        $row = $st->get_result()->fetch_assoc(); $st->close();

        if (!$row) { echo json_encode(['success'=>false,'error'=>'Utilizador não encontrado']); exit; }

        $del1 = $conn->prepare("DELETE FROM cartoes_bloqueados WHERE cartao_uid = ?");
        $del1->bind_param("s", $row['rfid_uid']); $del1->execute(); $del1->close();

        $del2 = $conn->prepare("DELETE FROM users_autorizados WHERE id = ?");
        $del2->bind_param("i", $id); $del2->execute(); $del2->close();

        echo json_encode(['success'=>true, 'message'=>"Utilizador <strong>$nome</strong> eliminado."]);
        break;

    // Editar utilizador (nome / email / rfid)
    case 'update_user':
        $id = intval($params['user_id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

        // Confirma que o utilizador existe
        $chk = $conn->prepare("SELECT id FROM users_autorizados WHERE id = ? LIMIT 1");
        $chk->bind_param("i", $id); $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            echo json_encode(['success'=>false,'error'=>'Utilizador não encontrado']); exit;
        }
        $chk->close();

        $campos = []; $valores = []; $tipos = ''; $resumo = [];

        if (isset($params['nome']) && trim($params['nome']) !== '') {
            $nome = trim($params['nome']);
            if (preg_match('/[^A-Za-zÀ-ÿ\s]/u', $nome)) {
                echo json_encode(['success'=>false,'error'=>'Nome inválido']); exit;
            }
            $campos[] = 'nome = ?'; $valores[] = $nome; $tipos .= 's';
            $resumo[] = "nome → <strong>" . htmlspecialchars($nome, ENT_QUOTES) . "</strong>";
        }

        if (isset($params['email'])) {
            $em = trim($params['email']);
            if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success'=>false,'error'=>'Email inválido']); exit;
            }
            $campos[] = 'email = ?'; $valores[] = $em; $tipos .= 's';
            $resumo[] = "email → <strong>" . htmlspecialchars($em, ENT_QUOTES) . "</strong>";
        }

        if (isset($params['rfid_uid']) && trim($params['rfid_uid']) !== '') {
            $rfid = strtoupper(preg_replace('/[^A-F0-9]/i', '', $params['rfid_uid']));
            if (strlen($rfid) < 4 || !ctype_xdigit($rfid)) {
                echo json_encode(['success'=>false,'error'=>'RFID UID inválido']); exit;
            }
            // Garante que o UID não pertence a outro utilizador
            $dup = $conn->prepare("SELECT id FROM users_autorizados WHERE rfid_uid = ? AND id <> ? LIMIT 1");
            $dup->bind_param("si", $rfid, $id); $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                echo json_encode(['success'=>false,'error'=>'Esse UID já pertence a outro utilizador']); exit;
            }
            $dup->close();
            $campos[] = 'rfid_uid = ?'; $valores[] = $rfid; $tipos .= 's';
            $resumo[] = "RFID → <code>$rfid</code>";
        }

        if (empty($campos)) {
            echo json_encode(['success'=>false,'error'=>'Nada para alterar']); exit;
        }

        $valores[] = $id; $tipos .= 'i';
        $st = $conn->prepare("UPDATE users_autorizados SET " . implode(', ', $campos) . " WHERE id = ?");
        $st->bind_param($tipos, ...$valores);
        $st->execute(); $st->close();

        echo json_encode(['success'=>true, 'message'=>"Utilizador atualizado: " . implode(', ', $resumo) . "."]);
        break;

    // Adicionar utilizador
    case 'add_user':
        $nome  = trim($params['nome']    ?? '');
        $email = trim($params['email']   ?? '');
        $rfid  = strtoupper(preg_replace('/[^A-F0-9]/i', '', $params['rfid_uid'] ?? ''));

        if ($nome === '' || preg_match('/[^A-Za-zÀ-ÿ\s]/u', $nome)) {
            echo json_encode(['success'=>false,'error'=>'Nome inválido']); exit;
        }
        if (strlen($rfid) < 4 || !ctype_xdigit($rfid)) {
            echo json_encode(['success'=>false,'error'=>'RFID UID inválido']); exit;
        }

        $chk = $conn->prepare("SELECT id FROM users_autorizados WHERE rfid_uid = ? LIMIT 1");
        $chk->bind_param("s", $rfid); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success'=>false,'error'=>'Já existe um utilizador com esse UID']); exit;
        }
        $chk->close();

        $ins = $conn->prepare("INSERT INTO users_autorizados (nome, email, rfid_uid, ativo, created_at) VALUES (?, ?, ?, 1, NOW())");
        $ins->bind_param("sss", $nome, $email, $rfid);
        if ($ins->execute()) {
            $nomeEsc = htmlspecialchars($nome, ENT_QUOTES);
            echo json_encode(['success'=>true, 'message'=>"Utilizador <strong>$nomeEsc</strong> adicionado com UID <code>$rfid</code>."]);
        } else {
            echo json_encode(['success'=>false,'error'=>'Erro ao guardar']);
        }
        $ins->close();
        break;
}
