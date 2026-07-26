<?php
// Este endpoint foi substituído por settings_api.php?action=generate_api
// Redireciona para evitar acesso direto
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}
header("Location: settings.php");
exit;
