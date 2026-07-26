<?php
session_start();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><title>A sair...</title></head>
<body>
<script>
try {
    localStorage.removeItem('rfid_chat_history');
    localStorage.removeItem('rfid_chat_open');
    localStorage.removeItem('rfid_chat_mode');
} catch(e) {}
window.location.replace('login.php');
</script>
</body>
</html>
