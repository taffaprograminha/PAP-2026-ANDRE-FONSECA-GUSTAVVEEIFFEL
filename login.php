<?php
session_start();

// Se já tem sessão válida, vai direto ao dashboard
if (isset($_SESSION['email'])) {
    header("Location: dashboard.php");
    exit;
}

require_once 'ligacao.php';

$msg     = "";
$bloqueado = false;

// Rate limiting: máx. 5 tentativas falhadas por IP em 15 minutos
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$st = $conn->prepare("
    SELECT COUNT(*) AS tentativas
    FROM logs_login
    WHERE ip_address = ?
      AND sucesso = 0
      AND data_hora >= NOW() - INTERVAL 15 MINUTE
");
$st->bind_param("s", $ip);
$st->execute();
$tentativas = (int)$st->get_result()->fetch_assoc()['tentativas'];
$st->close();

if ($tentativas >= 5) {
    $bloqueado = true;
    $msg = "Demasiadas tentativas falhadas. Tenta novamente em 15 minutos.";
}

// Processar login
if (!$bloqueado && $_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]    ?? '');
    $password = (string)($_POST["password"] ?? ''); // password sem trim

    if ($email === '' || $password === '') {
        $msg = "Preenche todos os campos.";
    } else {
        $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {
            // Login bem-sucedido
            session_regenerate_id(true);
            $_SESSION['email'] = $user["email"];

            // Regista login com sucesso
            $log = $conn->prepare("INSERT INTO logs_login (email, ip_address, sucesso, motivo, data_hora) VALUES (?, ?, 1, 'Login bem-sucedido', NOW())");
            $log->bind_param("ss", $email, $ip);
            $log->execute();
            $log->close();

            header("Location: dashboard.php");
            exit;
        } else {
            // Regista tentativa falhada
            $emailLog = $email; // pode não existir, regista na mesma
            $log = $conn->prepare("INSERT INTO logs_login (email, ip_address, sucesso, motivo, data_hora) VALUES (?, ?, 0, 'Credenciais inválidas', NOW())");
            $log->bind_param("ss", $emailLog, $ip);
            $log->execute();
            $log->close();

            // Reconta para mostrar aviso
            $tentativas++;
            $restantes = max(0, 5 - $tentativas);

            // Mensagem genérica - não revela se o email existe ou não
            if ($restantes > 0) {
                $msg = "Credenciais inválidas. " . ($restantes === 1 ? "Última tentativa antes de bloqueio." : "$restantes tentativas restantes.");
            } else {
                $bloqueado = true;
                $msg = "Demasiadas tentativas falhadas. Tenta novamente em 15 minutos.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="login.css?v=<?php echo @filemtime("login.css"); ?>">
</head>
<body>
<div class="container">
    <div class="form-container">
        <form method="POST" autocomplete="off">
            <h1>Sign In</h1>
            <span>usa o teu email e palavra-passe</span>

            <input type="email" name="email" placeholder="Email" required
                   <?php if ($bloqueado) echo 'disabled'; ?>
                   value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="password" name="password" placeholder="Password" required
                   <?php if ($bloqueado) echo 'disabled'; ?>>

            <button type="submit" <?php if ($bloqueado) echo 'disabled'; ?>>Entrar</button>

            <?php if ($msg): ?>
                <p class="msg"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </form>
    </div>

    <div class="toggle-container">
        <div class="toggle">
            <div class="brand-mark">R</div>
            <h1>Bem-vindo!</h1>
            <p>Painel de controlo RFID. Faz login com a tua conta para aceder ao dashboard.</p>
        </div>
    </div>
</div>
</body>
</html>
