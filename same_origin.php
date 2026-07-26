<?php
// so deixa passar POSTs do proprio site (contra CSRF)
// nao meter no api_rfid.php, esse e o arduino e usa api key

function exigir_mesmo_origin(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $host   = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');

    $originHost = $origin !== '' ? parse_url($origin, PHP_URL_HOST) : '';
    $hostHost   = $host   !== '' ? parse_url('http://' . $host, PHP_URL_HOST) : '';

    // origem diferente da nossa = fora
    if ($originHost === '' || $originHost !== $hostHost) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Origem inválida (CSRF)']);
        exit;
    }
}
