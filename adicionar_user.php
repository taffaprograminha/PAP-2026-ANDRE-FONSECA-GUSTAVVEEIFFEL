<?php
// adicionar_user.php - formulário com validações no cliente e servidor
// erros vao para o log, nao para o ecra
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();

// Autenticação obrigatória
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/ligacao.php';

// Gera token CSRF simples
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
    } else {
        $_SESSION['csrf_token'] = substr(bin2hex(md5(uniqid((string)mt_rand(), true))), 0, 32);
    }
}
$csrf = $_SESSION['csrf_token'];

$erro = '';

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $sent_csrf)) {
        $erro = 'Token inválido. Tenta novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rfid = strtoupper(trim($_POST['rfid_uid'] ?? ''));
        $rfid = preg_replace('/[^A-F0-9]/i', '', $rfid);

        // Validação servidor: nome só letras e espaços
        if ($nome === '' || preg_match('/[^A-Za-zÀ-ÿ\s]/u', $nome)) {
            $erro = 'O nome só pode conter letras e espaços.';
        } elseif ($rfid === '') {
            $erro = 'O RFID UID é obrigatório.';
        } elseif (!ctype_xdigit($rfid)) {
            $erro = 'O RFID UID só pode conter números e letras A-F.';
        } else {
            $chk = $conn->prepare("SELECT id FROM users_autorizados WHERE rfid_uid = ? LIMIT 1");
            if ($chk === false) {
                $erro = 'Erro interno (prepare): ' . $conn->error;
            } else {
                $chk->bind_param("s", $rfid);
                $chk->execute();
                $res = $chk->get_result();
                if ($res && $res->num_rows > 0) {
                    $erro = 'Já existe um utilizador com esse UID.';
                } else {
                    $ins = $conn->prepare("INSERT INTO users_autorizados (nome, email, rfid_uid, ativo, created_at) VALUES (?, ?, ?, 1, NOW())");
                    if ($ins === false) {
                        $erro = 'Erro interno (prepare insert): ' . $conn->error;
                    } else {
                        $ins->bind_param("sss", $nome, $email, $rfid);
                        if ($ins->execute()) {
                            header('Location: leituras_rfid.php?msg=ok&uid=' . urlencode($rfid));
                            exit();
                        } else {
                            $erro = 'Erro ao gravar: ' . $conn->error;
                        }
                    }
                }
                $chk->close();
            }
        }
    }
}

// Preenche RFID se vier da URL (clique em "Registar" na lista)
$val_nome  = '';
$val_email = '';
$val_rfid  = '';
if (isset($_GET['uid'])) {
    $val_rfid = preg_replace('/[^A-F0-9]/i', '', strtoupper($_GET['uid']));
}
?>
<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Novo Utilizador</title>
<script>
    (function(){
        var s = localStorage.getItem('theme');
        var d = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', s || (d ? 'dark' : 'light'));
    })();
</script>
<style>
    :root{
        --bg:#FAFAF8; --surface:#FFFFFF; --border:#E7E4DC; --inset:#F4F2EC;
        --text:#18181B; --text-muted:#5C5A54; --text-tertiary:#9A978D;
        --accent:#6E56CF; --accent-hover:#5A43B8; --accent-light:rgba(110,86,207,.10);
        --danger:#C0392B;
    }
    [data-theme="dark"]{
        --bg:#0E0E11; --surface:#16161A; --border:#27272F; --inset:#1D1D22;
        --text:#ECECEE; --text-muted:#9B9AA3; --text-tertiary:#6A6A73;
        --accent:#8B72E8; --accent-hover:#9D87F0; --accent-light:rgba(139,114,232,.15);
        --danger:#E5645A;
    }
    *{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
    body{min-height:100vh;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;padding:24px;-webkit-font-smoothing:antialiased}
    @keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:36px 32px;box-shadow:0 10px 30px rgba(24,24,27,.06);width:100%;max-width:440px;animation:fadeIn .4s ease}
    h2{font-size:26px;font-weight:700;letter-spacing:-.6px;margin-bottom:4px;color:var(--text)}
    h2::after{content:'';display:block;width:32px;height:3px;background:var(--accent);border-radius:2px;margin-top:10px;margin-bottom:22px}
    label{display:block;margin-top:14px;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px}
    input{width:100%;padding:11px 14px;background:var(--inset);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:14.5px;outline:none;transition:border-color .15s ease,background .15s ease,box-shadow .15s ease}
    input::placeholder{color:var(--text-tertiary)}
    input:focus{border-color:var(--accent);background:var(--surface);box-shadow:0 0 0 3px var(--accent-light)}
    input:invalid:not(:placeholder-shown){border-color:var(--danger)}
    .erro{margin-bottom:16px;padding:11px 14px;background:rgba(192,57,43,.08);border:1px solid rgba(192,57,43,.22);border-radius:6px;color:var(--danger);font-size:13.5px;font-weight:500}
    button{width:100%;padding:12px;margin-top:22px;background:var(--accent);color:#fff;border:none;border-radius:6px;font-size:14.5px;font-weight:600;cursor:pointer;transition:background .15s ease;letter-spacing:.2px}
    button:hover{background:var(--accent-hover)}
    a.back{display:inline-block;margin-top:16px;color:var(--text-muted);text-decoration:none;font-size:13.5px;font-weight:500;transition:color .15s ease}
    a.back:hover{color:var(--accent)}
</style>
</head>
<body>
    <div class="card" role="main">
        <h2>Novo Utilizador</h2>

        <?php if ($erro): ?>
            <div class="erro"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" novalidate autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="nome">Nome:</label>
            <input id="nome" name="nome" type="text" required placeholder="Nome do utilizador"
                   value="<?php echo htmlspecialchars($val_nome, ENT_QUOTES, 'UTF-8'); ?>"
                   pattern="[A-Za-zÀ-ÿ\s]+"
                   oninput="this.value = this.value.replace(/[^A-Za-zÀ-ÿ\s]/g, '')">

            <label for="email">Email:</label>
            <input id="email" name="email" type="email" placeholder="email@exemplo.com"
                   value="<?php echo htmlspecialchars($val_email, ENT_QUOTES, 'UTF-8'); ?>">

            <label for="rfid_uid">RFID UID:</label>
            <input id="rfid_uid" name="rfid_uid" type="text" required placeholder="Ex: A1B2C3D4"
                   value="<?php echo htmlspecialchars($val_rfid, ENT_QUOTES, 'UTF-8'); ?>"
                   pattern="[0-9A-Fa-f]+"
                   oninput="this.value = this.value.replace(/[^0-9A-Fa-f]/g, '').toUpperCase()">

            <button type="submit">GRAVAR UTILIZADOR</button>
        </form>

        <a class="back" href="leituras_rfid.php">← Voltar</a>
    </div>
</body>
</html>