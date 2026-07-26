<?php
session_start();

// Autenticação obrigatória
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once 'ligacao.php';
require_once __DIR__ . '/same_origin.php';
exigir_mesmo_origin();

$email  = $_SESSION['email'];
$action = $_POST['action'] ?? '';

switch ($action) {

    // Atualizar perfil (nome + email)
    case 'update_profile':
        $nome      = trim($_POST['nome']  ?? '');
        $novoEmail = trim($_POST['email'] ?? '');

        if ($nome === '') {
            echo json_encode(['success' => false, 'error' => 'Nome obrigatório']); exit;
        }
        if ($novoEmail === '') {
            echo json_encode(['success' => false, 'error' => 'Email obrigatório']); exit;
        }

        if ($novoEmail !== $email) {
            // Verifica se o novo email já está em uso
            $stCheck = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stCheck->bind_param("s", $novoEmail);
            $stCheck->execute();
            if ($stCheck->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'Email já em uso']); exit;
            }
            $stCheck->close();

            $st = $conn->prepare("UPDATE users SET nome = ?, email = ? WHERE email = ?");
            $st->bind_param("sss", $nome, $novoEmail, $email);
            $st->execute();
            $st->close();
            $_SESSION['email'] = $novoEmail; // atualiza sessão
        } else {
            $st = $conn->prepare("UPDATE users SET nome = ? WHERE email = ?");
            $st->bind_param("ss", $nome, $email);
            $st->execute();
            $st->close();
        }

        echo json_encode(['success' => true, 'message' => 'Perfil atualizado!']);
        break;

    // Guardar preferências
    case 'save_preferences':
        $campos  = [];
        $valores = [];
        $tipos   = '';

        if (isset($_POST['email_notifications'])) {
            $campos[] = 'email_notifications = ?';
            $valores[] = (int)$_POST['email_notifications'];
            $tipos .= 'i';
        }
        if (isset($_POST['digest_notifications'])) {
            $campos[] = 'digest_notifications = ?';
            $valores[] = (int)$_POST['digest_notifications'];
            $tipos .= 'i';
        }
        if (isset($_POST['dark_mode'])) {
            $campos[] = 'dark_mode = ?';
            $valores[] = (int)$_POST['dark_mode'];
            $tipos .= 'i';
        }
        if (isset($_POST['timezone'])) {
            $tz = $_POST['timezone'];
            if (!in_array($tz, DateTimeZone::listIdentifiers())) {
                echo json_encode(['success' => false, 'error' => 'Fuso horário inválido']); exit;
            }
            $campos[] = 'timezone = ?';
            $valores[] = $tz;
            $tipos .= 's';
        }
        if (isset($_POST['date_format'])) {
            $df = $_POST['date_format'];
            if (!in_array($df, ['pt', 'us'])) {
                echo json_encode(['success' => false, 'error' => 'Formato inválido']); exit;
            }
            $campos[] = 'date_format = ?';
            $valores[] = $df;
            $tipos .= 's';
        }

        if (empty($campos)) {
            echo json_encode(['success' => true, 'message' => 'Nada a guardar']); break;
        }

        $valores[] = $email;
        $tipos .= 's';

        $st = $conn->prepare("UPDATE users SET " . implode(', ', $campos) . " WHERE email = ?");
        $st->bind_param($tipos, ...$valores);
        $st->execute();
        $st->close();

        echo json_encode(['success' => true, 'message' => 'Preferências guardadas']);
        break;

    // Alterar password
    case 'change_password':
        // Aceita os dois nomes de campo (compatibilidade)
        $atual = $_POST['atual']          ?? $_POST['password_atual'] ?? '';
        $nova  = $_POST['nova']           ?? $_POST['password_nova']  ?? '';
        $conf  = $_POST['conf']           ?? $_POST['password_conf']  ?? '';

        if ($nova === '' || strlen($nova) < 6) {
            echo json_encode(['success' => false, 'error' => 'A nova password deve ter pelo menos 6 caracteres']); exit;
        }
        if ($nova !== $conf) {
            echo json_encode(['success' => false, 'error' => 'As passwords não coincidem']); exit;
        }

        // Verifica a password atual
        $st = $conn->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $email);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row || !password_verify($atual, $row['password'])) {
            echo json_encode(['success' => false, 'error' => 'Password atual incorreta']); exit;
        }

        $hash = password_hash($nova, PASSWORD_DEFAULT);
        $st = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $st->bind_param("ss", $hash, $email);
        $st->execute();
        $st->close();

        echo json_encode(['success' => true, 'message' => 'Password alterada com sucesso!']);
        break;

    // Ativar / desativar 2FA
    case 'toggle_2fa':
        $st = $conn->prepare("UPDATE users SET two_factor_enabled = IF(two_factor_enabled = 1, 0, 1) WHERE email = ?");
        $st->bind_param("s", $email);
        $st->execute();
        $st->close();

        // Lê o novo estado
        $st = $conn->prepare("SELECT two_factor_enabled FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $email);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        $enabled = (bool)($row['two_factor_enabled'] ?? false);
        echo json_encode([
            'success' => true,
            'enabled' => $enabled,
            'message' => $enabled ? '2FA ativado.' : '2FA desativado.'
        ]);
        break;

    // Gerar nova API Key
    case 'generate_api':
        $newKey = bin2hex(random_bytes(32));
        $st = $conn->prepare("UPDATE users SET api_key = ? WHERE email = ?");
        $st->bind_param("ss", $newKey, $email);
        $st->execute();
        $st->close();

        echo json_encode([
            'success' => true,
            'api_key' => $newKey,
            'message' => 'Nova API Key gerada!'
        ]);
        break;

    // Revogar API Key
    case 'revoke_api':
        $st = $conn->prepare("UPDATE users SET api_key = NULL WHERE email = ?");
        $st->bind_param("s", $email);
        $st->execute();
        $st->close();
        echo json_encode(['success' => true, 'message' => 'API Key revogada.']);
        break;

    // Eliminar conta
    case 'delete_account':
        $pwd = $_POST['password'] ?? '';
        if ($pwd === '') {
            echo json_encode(['success' => false, 'error' => 'Password obrigatória.']); exit;
        }

        $st = $conn->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
        $st->bind_param("s", $email);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row || !password_verify($pwd, $row['password'])) {
            echo json_encode(['success' => false, 'error' => 'Password incorreta.']); exit;
        }

        $st = $conn->prepare("DELETE FROM users WHERE email = ?");
        $st->bind_param("s", $email);
        $st->execute();
        $st->close();

        // Termina a sessão
        $_SESSION = [];
        session_destroy();

        echo json_encode(['success' => true, 'redirect' => 'login.php', 'message' => 'Conta eliminada.']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação desconhecida']);
}
