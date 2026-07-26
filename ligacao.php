<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ola"; // nome da tua base de dados

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Ligação falhou: " . $conn->connect_error);
}

// Migração: só corre se a coluna ainda não existir (1 query leve, sem escrita em disco)
$_mig = @$conn->query("SHOW COLUMNS FROM users LIKE 'email_notifications'");
if ($_mig && $_mig->num_rows === 0) {
    include_once __DIR__ . '/_migrate.php';
}
?>