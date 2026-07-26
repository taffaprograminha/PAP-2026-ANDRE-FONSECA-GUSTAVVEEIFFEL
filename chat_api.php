<?php
session_start();

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$email = $_SESSION['email'];

// Rate limit: máx. 20 mensagens por minuto por sessão
$_rl_now = time();
if (!isset($_SESSION['chat_rl_t'])) { $_SESSION['chat_rl_t'] = $_rl_now; $_SESSION['chat_rl_n'] = 0; }
if ($_rl_now - $_SESSION['chat_rl_t'] > 60) { $_SESSION['chat_rl_t'] = $_rl_now; $_SESSION['chat_rl_n'] = 0; }
if (++$_SESSION['chat_rl_n'] > 20) {
    http_response_code(429);
    echo json_encode(['type' => 'error', 'error' => 'Demasiadas mensagens seguidas. Aguarda um momento.']);
    exit;
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ola');

require_once __DIR__ . '/config.php'; // GROQ_API_KEY, GROQ_MODEL, GROQ_URL

header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host   = $_SERVER['HTTP_HOST']   ?? '';
if ($origin && parse_url($origin, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Método não permitido']); exit; }

$body        = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($body['message'] ?? '');
$history     = $body['history']  ?? [];
$mode        = ($body['mode'] ?? 'view') === 'exec' ? 'exec' : 'view'; // 'view' = só leitura | 'exec' = pode agir

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Mensagem vazia']);
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro BD']);
    exit;
}

$contextBlocks = [];
$msgLower = mb_strtolower($userMessage);

// Estatísticas gerais (sempre)
$statsRow = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users) AS total_admins,
        (SELECT COUNT(*) FROM placas_arduino WHERE ativo=1) AS total_placas,
        (SELECT COUNT(*) FROM placas_arduino WHERE estado='online' AND ativo=1) AS placas_online,
        (SELECT COUNT(*) FROM leituras_rfid WHERE DATE(data_leitura)=CURDATE()) AS leituras_hoje,
        (SELECT COUNT(*) FROM users_autorizados) AS users_autorizados,
        (SELECT COUNT(*) FROM users_autorizados WHERE ativo=1) AS users_ativos,
        (SELECT COUNT(*) FROM cartoes_bloqueados) AS cartoes_bloqueados
")->fetch_assoc();
if ($statsRow) {
    $contextBlocks[] = "SISTEMA: {$statsRow['total_admins']} admins | {$statsRow['total_placas']} placas ({$statsRow['placas_online']} online) | {$statsRow['leituras_hoje']} leituras hoje | {$statsRow['users_autorizados']} utilizadores RFID ({$statsRow['users_ativos']} ativos) | {$statsRow['cartoes_bloqueados']} bloqueados";
}

// Lista completa de utilizadores + cartões bloqueados (sempre em modo exec; em view só por keyword)
$kUsers = ['utilizador','user','cartão','cartao','email','mail','nome','ativo','inativo',
           'bloquei','desbloquei','bloquear','desbloquear','bloqueia','desbloqueia',
           'ativa','desativa','ativar','desativar','remove','elimina','apaga','adiciona',
           'cria','altera','edita','muda','atualiza','quem','lista','todos','todas'];
$needUsers = ($mode === 'exec') || (bool) array_filter($kUsers, fn($k) => str_contains($msgLower, $k));

if ($needUsers) {
    $res = $conn->query("
        SELECT u.id, u.nome, u.email, u.rfid_uid, u.ativo,
               IF(cb.id IS NOT NULL, 1, 0) AS bloqueado
        FROM users_autorizados u
        LEFT JOIN cartoes_bloqueados cb ON cb.cartao_uid = u.rfid_uid
        ORDER BY u.nome
    ");
    if ($res && $res->num_rows > 0) {
        $lines = ["=== UTILIZADORES RFID ==="];
        while ($row = $res->fetch_assoc()) {
            $st  = $row['ativo']    ? 'ativo'    : 'desativado';
            $blk = $row['bloqueado'] ? ' [BLOQUEADO]' : '';
            $lines[] = "ID:{$row['id']} | Nome:{$row['nome']} | Email:{$row['email']} | RFID:{$row['rfid_uid']} | {$st}{$blk}";
        }
        $contextBlocks[] = implode("\n", $lines);
    }

    // Cartões bloqueados (sempre junto com a lista de utilizadores)
    $res2 = $conn->query("
        SELECT cb.cartao_uid, cb.motivo, cb.criado_em, u.nome
        FROM cartoes_bloqueados cb
        LEFT JOIN users_autorizados u ON u.rfid_uid = cb.cartao_uid
        ORDER BY cb.criado_em DESC
    ");
    if ($res2 && $res2->num_rows > 0) {
        $lines = ["=== CARTÕES BLOQUEADOS ==="];
        while ($row = $res2->fetch_assoc()) {
            $dt   = date('d/m/Y H:i', strtotime($row['criado_em']));
            $nome = $row['nome'] ?: 'Desconhecido';
            $lines[] = "RFID:{$row['cartao_uid']} | {$nome} | Motivo:{$row['motivo']} | {$dt}";
        }
        $contextBlocks[] = implode("\n", $lines);
    }
}

// Placas Arduino
$kPlacas = ['placa','arduino','leitor','porta','dispositivo','ip','online','offline'];
if (array_filter($kPlacas, fn($k) => str_contains($msgLower, $k))) {
    $res = $conn->query("SELECT id, nome, localizacao, ip_address, estado FROM placas_arduino WHERE ativo=1 ORDER BY nome");
    if ($res && $res->num_rows > 0) {
        $lines = ["=== PLACAS ARDUINO ==="];
        while ($row = $res->fetch_assoc()) {
            $lines[] = "• [{$row['estado']}] {$row['nome']} | Local:{$row['localizacao']} | IP:{$row['ip_address']}";
        }
        $contextBlocks[] = implode("\n", $lines);
    }
}

// Leituras recentes
$kLeit = ['leitura','rfid','uid','acesso','recente','último','ultima','hoje','histórico','entrou','entrar'];
if (array_filter($kLeit, fn($k) => str_contains($msgLower, $k))) {
    $res = $conn->query("
        SELECT l.cartao_uid, l.status, l.data_leitura, p.nome AS placa_nome, u.nome AS user_nome
        FROM leituras_rfid l
        JOIN placas_arduino p ON l.placa_id = p.id
        LEFT JOIN users_autorizados u ON l.user_id = u.id
        ORDER BY l.data_leitura DESC LIMIT 20
    ");
    if ($res && $res->num_rows > 0) {
        $lines = ["=== LEITURAS RECENTES ==="];
        while ($row = $res->fetch_assoc()) {
            $user = $row['user_nome'] ?: 'Desconhecido';
            $dt   = date('d/m/Y H:i', strtotime($row['data_leitura']));
            $lines[] = "• [{$row['status']}] {$row['cartao_uid']} | {$user} | {$row['placa_nome']} | {$dt}";
        }
        $contextBlocks[] = implode("\n", $lines);
    }
}

// Alertas
$kAlert = ['alerta','desconhecido','suspeito','tentativa','negado','segurança','estranho'];
if (array_filter($kAlert, fn($k) => str_contains($msgLower, $k))) {
    $res = $conn->query("
        SELECT l.cartao_uid, COUNT(*) AS tentativas, MAX(l.data_leitura) AS ultima_vez, p.nome AS placa_nome
        FROM leituras_rfid l JOIN placas_arduino p ON l.placa_id = p.id
        WHERE l.status IN ('desconhecido','negado') AND l.data_leitura >= NOW() - INTERVAL 24 HOUR
        GROUP BY l.cartao_uid, l.placa_id ORDER BY ultima_vez DESC LIMIT 10
    ");
    if ($res && $res->num_rows > 0) {
        $lines = ["=== ALERTAS (últimas 24h) ==="];
        while ($row = $res->fetch_assoc()) {
            $dt = date('d/m/Y H:i', strtotime($row['ultima_vez']));
            $lines[] = "RFID:{$row['cartao_uid']} | {$row['tentativas']}x | {$row['placa_nome']} | {$dt}";
        }
        $contextBlocks[] = implode("\n", $lines);
    }
}

// Fuso horário do utilizador
$tzStmt = $conn->prepare("SELECT timezone FROM users WHERE email = ? LIMIT 1");
$tzStmt->bind_param("s", $email);
$tzStmt->execute();
$tzRow  = $tzStmt->get_result()->fetch_assoc();
$tzStmt->close();
$userTz = $tzRow['timezone'] ?? 'Europe/Lisbon';

$conn->close();

// Data e hora atual (injetada no contexto para a IA)
try { $tz = new DateTimeZone($userTz); } catch (Exception $e) { $tz = new DateTimeZone('Europe/Lisbon'); }
$agora       = new DateTime('now', $tz);
$diasSemana  = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
$dataHoje    = $agora->format('d/m/Y');
$horaAgora   = $agora->format('H:i');
$diaNome     = $diasSemana[(int)$agora->format('w')];
array_unshift($contextBlocks, "DATA E HORA ATUAL: {$dataHoje} ({$diaNome}), {$horaAgora} (fuso: {$userTz})");

// System prompt (curto e directo)
if ($mode === 'exec') {
    $systemPrompt = <<<'PROMPT'
És um assistente de controlo de acesso RFID. Responde SEMPRE em português de Portugal (pt-PT).

Tens DUAS coisas que podes fazer:

1. **RESPONDER A PERGUNTAS** — usa os DADOS DO SISTEMA abaixo e responde em texto natural.

2. **EXECUTAR ACÇÕES** — quando o utilizador te pede para fazer algo (bloquear, desbloquear, ativar, desativar, eliminar, adicionar, alterar), CHAMA as ferramentas disponíveis. Nunca descrevas a ação em texto — usa a ferramenta.

REGRAS CRÍTICAS:
- Usa SEMPRE os IDs e UIDs EXACTOS da lista. NUNCA inventes valores.
- Para encontrar uma pessoa pelo nome, procura na lista e usa user_id e rfid_uid exactos.
- **NUNCA chames uma ferramenta com campos obrigatórios em branco.** Se o utilizador disser apenas "cria um utilizador" sem dar nome/UID, NÃO chames add_user — RESPONDE EM TEXTO: "Diz-me o nome completo e o UID do cartão para criar o utilizador."
- Mesmo princípio para alterações sem valor novo: "Qual é o novo email que queres definir para [Nome]?"
- Só chamas a ferramenta quando tens TODOS os dados que ela precisa.
- Usa o histórico: combina intenção anterior + valor em falta numa só chamada quando aplicável. Ex: turno 1 "cria utilizador" → tu perguntas dados; turno 2 "andre antunes ABABABAB" → AGORA chamas add_user com nome="andre antunes" e rfid_uid="ABABABAB".
- O "confirm" das ferramentas singulares deve ser uma frase clara em pt-PT. Ex: "Bloquear o cartão AA11BB22 de João Ferreira".

QUANDO USAR FERRAMENTAS EM LOTE (preferencial — UMA só chamada):
- **bulk_add_users** → utilizador cola uma TABELA ou LISTA de pessoas para adicionar. Mesmo que o formato seja confuso (tab-separated, vírgulas, linhas soltas), extrai os nomes/UIDs/emails e chama esta ferramenta UMA vez com todos.
- **bulk_set_user_active** → "desativa todos os ativos", "ativa o João, Ana e Pedro", "ativa todos os inativos". Filtra pela lista de utilizadores e passa todos os IDs numa só chamada. ativo=false para desativar, true para ativar.
- **bulk_block_cards** → "bloqueia todos os cartões ativos", "bloqueia o cartão do João, Ana e Pedro". Passa os rfid_uids numa só chamada.
- **bulk_delete_users** → "elimina o João e a Ana", "apaga todos os inativos".

NUNCA faças 5 chamadas paralelas quando podes fazer 1 chamada em lote. As ferramentas bulk_ existem precisamente para evitar isso.
PROMPT;
} else {
    $systemPrompt = <<<'PROMPT'
És um assistente de controlo de acesso RFID em modo SÓ LEITURA. Responde SEMPRE em português de Portugal (pt-PT).

Podes apenas responder a perguntas usando os DADOS DO SISTEMA abaixo. NÃO podes executar nenhuma ação.

Se o utilizador pedir para fazer algo que altera dados (bloquear, desbloquear, ativar, desativar, eliminar, adicionar, alterar), responde EXACTAMENTE:
"Estou em modo de Visualização (só leitura), por isso não posso executar essa ação. Ativa o modo de Execução (botão no topo do chat) e a tua password para fazer alterações."

NUNCA tentes chamar ferramentas. Responde só em texto.
PROMPT;
}

if (!empty($contextBlocks)) {
    $systemPrompt .= "\n\n--- DADOS DO SISTEMA ---\n\n" . implode("\n\n", $contextBlocks);
}

// Histórico + mensagem atual
$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($history as $msg) {
    if (isset($msg['role'], $msg['content']) && in_array($msg['role'], ['user','assistant'])) {
        $messages[] = ['role' => $msg['role'], 'content' => substr($msg['content'], 0, 500)];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

// Definição de ferramentas (só em modo Exec)
$tools = [
    ['type' => 'function', 'function' => [
        'name' => 'block_card',
        'description' => 'Bloqueia um cartão RFID para que não consiga aceder. Usa o rfid_uid EXACTO da lista.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'rfid_uid' => ['type' => 'string', 'description' => 'UID exacto do cartão (hexadecimal)'],
                'motivo'   => ['type' => 'string', 'description' => 'Motivo do bloqueio em português'],
                'confirm'  => ['type' => 'string', 'description' => 'Frase em pt-PT a confirmar. Ex: "Bloquear o cartão XXXX de Nome Completo"'],
            ],
            'required' => ['rfid_uid','motivo','confirm'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'unblock_card',
        'description' => 'Desbloqueia um cartão RFID previamente bloqueado.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'rfid_uid' => ['type' => 'string', 'description' => 'UID exacto do cartão'],
                'confirm'  => ['type' => 'string', 'description' => 'Frase em pt-PT a confirmar'],
            ],
            'required' => ['rfid_uid','confirm'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'activate_user',
        'description' => 'Ativa um utilizador desativado (volta a permitir acessos).',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => ['integer','string'], 'description' => 'ID numérico exacto do utilizador'],
                'nome'    => ['type' => 'string',  'description' => 'Nome completo do utilizador'],
                'confirm' => ['type' => 'string',  'description' => 'Frase em pt-PT a confirmar'],
            ],
            'required' => ['user_id','nome','confirm'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'deactivate_user',
        'description' => 'Desativa um utilizador (suspende acessos sem eliminar).',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => ['integer','string'], 'description' => 'ID numérico exacto'],
                'nome'    => ['type' => 'string',  'description' => 'Nome completo'],
                'confirm' => ['type' => 'string',  'description' => 'Frase em pt-PT a confirmar'],
            ],
            'required' => ['user_id','nome','confirm'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'delete_user',
        'description' => 'Elimina permanentemente um utilizador da base de dados.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => ['integer','string'], 'description' => 'ID numérico exacto'],
                'nome'    => ['type' => 'string',  'description' => 'Nome completo'],
                'confirm' => ['type' => 'string',  'description' => 'Frase em pt-PT a confirmar'],
            ],
            'required' => ['user_id','nome','confirm'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'add_user',
        'description' => 'Adiciona um novo utilizador ao sistema. Requer nome e rfid_uid. Email é opcional.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'nome'     => ['type' => 'string', 'description' => 'Nome completo'],
                'rfid_uid' => ['type' => 'string', 'description' => 'UID do cartão (hex)'],
                'email'    => ['type' => 'string', 'description' => 'Email (opcional)'],
                'confirm'  => ['type' => 'string', 'description' => 'Frase em pt-PT a confirmar'],
            ],
            'required' => ['nome','rfid_uid','confirm'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'update_user',
        'description' => 'Altera dados de um utilizador existente (nome, email e/ou rfid_uid). Só inclui os campos a alterar.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'user_id'  => ['type' => ['integer','string'], 'description' => 'ID numérico exacto'],
                'nome'     => ['type' => 'string',  'description' => 'Novo nome (só se a alterar)'],
                'email'    => ['type' => 'string',  'description' => 'Novo email (só se a alterar)'],
                'rfid_uid' => ['type' => 'string',  'description' => 'Novo UID (só se a alterar)'],
                'confirm'  => ['type' => 'string',  'description' => 'Frase em pt-PT a confirmar'],
            ],
            'required' => ['user_id','confirm'],
        ],
    ]],
    // FERRAMENTAS EM LOTE - uma só chamada, expandida no servidor
    ['type' => 'function', 'function' => [
        'name' => 'bulk_add_users',
        'description' => 'Adiciona MÚLTIPLOS utilizadores de uma só vez. Usa esta ferramenta quando o utilizador cola uma tabela/lista de pessoas para adicionar. Faz UMA única chamada com todos.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'users' => [
                    'type'  => 'array',
                    'description' => 'Lista de utilizadores a adicionar',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nome'     => ['type' => 'string'],
                            'rfid_uid' => ['type' => 'string'],
                            'email'    => ['type' => 'string'],
                        ],
                        'required' => ['nome','rfid_uid'],
                    ],
                ],
            ],
            'required' => ['users'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'bulk_set_user_active',
        'description' => 'Ativa OU desativa vários utilizadores em lote. Usa quando o utilizador pede ações tipo "desativa todos os ativos", "ativa todos os inativos". UMA única chamada.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'ativo'    => ['type' => 'boolean', 'description' => 'true=ativar, false=desativar'],
                'user_ids' => [
                    'type' => 'array',
                    'description' => 'Lista de IDs (numéricos) a alterar',
                    'items' => ['type' => ['integer','string']],
                ],
            ],
            'required' => ['ativo','user_ids'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'bulk_block_cards',
        'description' => 'Bloqueia vários cartões em lote. Usa para "bloqueia todos os cartões", "bloqueia os cartões do João, Ana e Miguel". UMA única chamada.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'motivo'    => ['type' => 'string', 'description' => 'Motivo em português aplicado a todos'],
                'rfid_uids' => [
                    'type'  => 'array',
                    'description' => 'Lista de UIDs exactos a bloquear',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['motivo','rfid_uids'],
        ],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'bulk_delete_users',
        'description' => 'Elimina permanentemente vários utilizadores em lote. UMA única chamada.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'user_ids' => [
                    'type'  => 'array',
                    'description' => 'Lista de IDs a eliminar',
                    'items' => ['type' => ['integer','string']],
                ],
            ],
            'required' => ['user_ids'],
        ],
    ]],
];

// Groq API (OpenAI-compatible)
if (!defined('GROQ_API_KEY') || GROQ_API_KEY === 'COLA_AQUI_A_TUA_API_KEY' || GROQ_API_KEY === '') {
    http_response_code(503);
    echo json_encode(['type' => 'error', 'error' => 'API Key do Groq não configurada. Edita o ficheiro config.php.']);
    exit;
}

$payload = [
    'model'       => GROQ_MODEL,
    'messages'    => $messages,
    'temperature' => 0.1,          // muito baixo → mais determinístico
    'max_tokens'  => ($mode === 'exec') ? 4096 : 768,
    'stream'      => false,
];

// Tool calling só em modo Execução
if ($mode === 'exec') {
    $payload['tools']       = $tools;
    $payload['tool_choice'] = 'auto';
    $payload['parallel_tool_calls'] = true; // permite várias ferramentas numa só resposta (lotes)
}

$ch = curl_init(GROQ_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
]);

$response  = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['type' => 'error', 'error' => 'Sem ligação ao Groq. Verifica a ligação à internet.']);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $groqMsg = $data['error']['message'] ?? 'Erro desconhecido da API Groq.';
    http_response_code(502);
    echo json_encode(['type' => 'error', 'error' => "Groq: $groqMsg"]);
    exit;
}

$message    = $data['choices'][0]['message'] ?? [];
$aiText     = trim($message['content'] ?? '');
$toolCalls  = $message['tool_calls'] ?? [];
$allowedFn  = ['block_card','unblock_card','activate_user','deactivate_user','delete_user','add_user','update_user'];
$bulkFn     = ['bulk_add_users','bulk_set_user_active','bulk_block_cards','bulk_delete_users'];

// Helper: procura nome do utilizador pelo ID na lista do contexto
// (para gerar "confirm" das ações em lote sem precisar do LLM)
$userNameById = [];
$userNameByUid = [];
foreach ($contextBlocks as $blk) {
    if (preg_match_all('/ID:(\d+) \| Nome:([^|]+) \|[^|]*\| RFID:([A-F0-9]+)/i', $blk, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $userNameById[(int)$row[1]]    = trim($row[2]);
            $userNameByUid[strtoupper(trim($row[3]))] = trim($row[2]);
        }
    }
}

// Helper: valida que campos obrigatórios não estão vazios
$validate = function(string $type, array $a): ?string {
    $isNonEmpty = fn($v) => is_string($v) ? trim($v) !== '' : !empty($v);
    switch ($type) {
        case 'block_card':
        case 'unblock_card':
            if (!$isNonEmpty($a['rfid_uid'] ?? null))
                return 'Qual é o UID do cartão que queres ' . ($type === 'block_card' ? 'bloquear' : 'desbloquear') . '?';
            break;
        case 'activate_user':
        case 'deactivate_user':
        case 'delete_user':
            if (empty($a['user_id']) || (int)$a['user_id'] <= 0)
                return 'Qual é o utilizador? Diz-me o nome ou o ID.';
            break;
        case 'add_user':
            $missing = [];
            if (!$isNonEmpty($a['nome'] ?? null))     $missing[] = 'nome completo';
            if (!$isNonEmpty($a['rfid_uid'] ?? null)) $missing[] = 'UID do cartão';
            if ($missing) return 'Para adicionar o utilizador preciso de: ' . implode(' e ', $missing) . '.';
            break;
        case 'update_user':
            if (empty($a['user_id']) || (int)$a['user_id'] <= 0)
                return 'Qual é o utilizador a alterar?';
            $hasField = $isNonEmpty($a['nome'] ?? null) || $isNonEmpty($a['email'] ?? null) || $isNonEmpty($a['rfid_uid'] ?? null);
            if (!$hasField) return 'O que queres alterar? (nome, email ou UID)';
            break;
    }
    return null;
};

// Processar tool_calls
$actions       = [];
$missingPrompt = null; // se um tool_call ficar incompleto, perguntamos em texto

foreach ($toolCalls as $tc) {
    $fnName = $tc['function']['name'] ?? '';
    $args   = json_decode($tc['function']['arguments'] ?? '{}', true);
    if (!is_array($args)) continue;

    // Ferramentas individuais
    if (in_array($fnName, $allowedFn, true)) {
        $err = $validate($fnName, $args);
        if ($err !== null) { $missingPrompt = $err; continue; }

        $confirm = trim($args['confirm'] ?? '');
        unset($args['confirm']);
        if ($confirm === '') $confirm = 'Confirmas esta ação?';
        $actions[] = ['type' => $fnName, 'params' => $args, 'confirm' => $confirm];
        continue;
    }

    // Ferramentas em lote: expandir para várias ações individuais
    if ($fnName === 'bulk_add_users' && isset($args['users']) && is_array($args['users'])) {
        foreach ($args['users'] as $u) {
            if (empty($u['nome']) || empty($u['rfid_uid'])) continue;
            $actions[] = [
                'type'    => 'add_user',
                'params'  => [
                    'nome'     => $u['nome'],
                    'rfid_uid' => $u['rfid_uid'],
                    'email'    => $u['email'] ?? '',
                ],
                'confirm' => "Adicionar " . $u['nome'] . " (UID " . $u['rfid_uid'] . ")",
            ];
        }
        continue;
    }
    if ($fnName === 'bulk_set_user_active' && isset($args['user_ids']) && is_array($args['user_ids'])) {
        $ativo = !empty($args['ativo']);
        $type  = $ativo ? 'activate_user' : 'deactivate_user';
        $verbo = $ativo ? 'Ativar' : 'Desativar';
        foreach ($args['user_ids'] as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) continue;
            $nome = $userNameById[$uid] ?? "ID $uid";
            $actions[] = [
                'type'    => $type,
                'params'  => ['user_id' => $uid, 'nome' => $nome],
                'confirm' => "$verbo a conta de $nome",
            ];
        }
        continue;
    }
    if ($fnName === 'bulk_block_cards' && isset($args['rfid_uids']) && is_array($args['rfid_uids'])) {
        $motivo = $args['motivo'] ?? 'Bloqueio em lote';
        foreach ($args['rfid_uids'] as $uid) {
            $uid = strtoupper(trim($uid));
            if ($uid === '') continue;
            $nome = $userNameByUid[$uid] ?? 'Desconhecido';
            $actions[] = [
                'type'    => 'block_card',
                'params'  => ['rfid_uid' => $uid, 'motivo' => $motivo],
                'confirm' => "Bloquear o cartão $uid de $nome",
            ];
        }
        continue;
    }
    if ($fnName === 'bulk_delete_users' && isset($args['user_ids']) && is_array($args['user_ids'])) {
        foreach ($args['user_ids'] as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) continue;
            $nome = $userNameById[$uid] ?? "ID $uid";
            $actions[] = [
                'type'    => 'delete_user',
                'params'  => ['user_id' => $uid, 'nome' => $nome],
                'confirm' => "Eliminar $nome",
            ];
        }
        continue;
    }
}

// Fallback: o modelo pode ainda devolver ACTION: em texto (modelos mais antigos)
if (empty($actions) && $aiText !== '') {
    $cleanText = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $aiText);
    $parts = preg_split('/ACTION:\s*/i', $cleanText);
    foreach (array_slice($parts, 1) as $part) {
        $depth = 0; $jsonStr = ''; $started = false;
        for ($i = 0; $i < strlen($part); $i++) {
            $ch = $part[$i];
            if ($ch === '{') { $depth++; $started = true; }
            if ($started) $jsonStr .= $ch;
            if ($ch === '}' && $started && --$depth === 0) break;
        }
        $parsed = json_decode($jsonStr, true);
        if ($parsed && isset($parsed['type']) && in_array($parsed['type'], $allowedFn, true)) {
            $actions[] = [
                'type'    => $parsed['type'],
                'params'  => $parsed['params'] ?? [],
                'confirm' => $parsed['confirm'] ?? 'Confirmas esta ação?',
            ];
        }
    }
}

// Se o modelo tentou chamar tool mas faltavam dados - pergunta em texto
if (empty($actions) && $missingPrompt !== null) {
    echo json_encode(['type' => 'text', 'reply' => $missingPrompt]);
    exit;
}

// Devolver acções (single ou batch)
if (!empty($actions)) {
    if ($mode !== 'exec') {
        echo json_encode(['type' => 'text', 'reply' => 'Estou em modo de Visualização (só leitura), por isso não posso executar essa ação. Ativa o modo de Execução (botão no topo do chat) e a tua password para fazer alterações.']);
        exit;
    }
    if (count($actions) === 1) {
        $a = $actions[0];
        echo json_encode([
            'type'        => 'action',
            'action_type' => $a['type'],
            'params'      => $a['params'],
            'confirm'     => $a['confirm'],
        ]);
    } else {
        echo json_encode([
            'type'    => 'action_batch',
            'actions' => array_map(fn($a) => [
                'action_type' => $a['type'],
                'params'      => $a['params'],
                'confirm'     => $a['confirm'],
            ], $actions),
        ]);
    }
    exit;
}

// Resposta de texto normal
if ($aiText === '') {
    echo json_encode(['type' => 'text', 'reply' => 'O modelo não devolveu resposta. Tenta novamente.']);
    exit;
}
echo json_encode(['type' => 'text', 'reply' => $aiText]);
