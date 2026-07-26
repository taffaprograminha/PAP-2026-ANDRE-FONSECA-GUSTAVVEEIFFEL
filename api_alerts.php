<?php
session_start();
if (!isset($_SESSION['email'])) { http_response_code(401); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once 'ligacao.php';

$result = $conn->query("
    SELECT l.cartao_uid, COUNT(*) AS tentativas, MAX(l.data_leitura) AS ultima_vez,
           p.nome AS placa_nome
    FROM leituras_rfid l
    JOIN placas_arduino p ON l.placa_id = p.id
    WHERE l.status = 'desconhecido'
      AND l.data_leitura >= NOW() - INTERVAL 1 HOUR
    GROUP BY l.cartao_uid, l.placa_id
    ORDER BY ultima_vez DESC
    LIMIT 10
");
$items = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode(['count' => count($items), 'items' => $items]);
