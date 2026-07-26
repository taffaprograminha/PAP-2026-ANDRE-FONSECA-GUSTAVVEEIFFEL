<?php
session_start();
if (!isset($_SESSION['email'])) { http_response_code(401); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once 'ligacao.php';

$stats = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users_autorizados)                                              AS total_users,
        (SELECT COUNT(*) FROM users_autorizados WHERE ativo=1)                               AS users_ativos,
        (SELECT COUNT(*) FROM placas_arduino WHERE ativo=1)                                   AS total_placas,
        (SELECT COUNT(*) FROM placas_arduino WHERE estado='online' AND ativo=1)               AS online_placas,
        (SELECT COUNT(*) FROM leituras_rfid WHERE DATE(data_leitura)=CURDATE())              AS acessos_hoje,
        (SELECT COUNT(*) FROM leituras_rfid WHERE status='permitido' AND DATE(data_leitura)=CURDATE()) AS permitidos_hoje,
        (SELECT COUNT(*) FROM leituras_rfid WHERE status='negado' AND data_leitura>=NOW()-INTERVAL 24 HOUR) AS negados_24h,
        (SELECT COUNT(*) FROM cartoes_bloqueados)                                             AS cartoes_bloqueados
")->fetch_assoc();
echo json_encode($stats);
