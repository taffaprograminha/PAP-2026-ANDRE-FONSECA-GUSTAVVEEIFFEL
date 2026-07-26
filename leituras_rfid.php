<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
require_once "ligacao.php";

// Histórico de cartão (endpoint AJAX)
if (isset($_GET['cartao_historico'])) {
    header('Content-Type: application/json');
    $uid = trim($_GET['cartao_historico']);
    $sh = $conn->prepare("
        SELECT l.data_leitura, l.status, p.nome AS placa_nome, u.nome AS user_nome
        FROM leituras_rfid l
        JOIN placas_arduino p ON l.placa_id = p.id
        LEFT JOIN users_autorizados u ON l.user_id = u.id
        WHERE l.cartao_uid = ?
        ORDER BY l.data_leitura DESC LIMIT 50
    ");
    $sh->bind_param("s", $uid);
    $sh->execute();
    echo json_encode($sh->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}

// Filtros
$filtroPlaca      = intval($_GET['placa_id']   ?? 0);
$filtroStatus     = trim($_GET['status']       ?? '');
$filtroDataInicio = trim($_GET['data_inicio']  ?? date('Y-m-d', strtotime('-90 days')));
$filtroDataFim    = trim($_GET['data_fim']     ?? date('Y-m-d'));
$filtroCartao     = trim($_GET['cartao_uid']   ?? '');
$filtroUser       = trim($_GET['user_nome']    ?? '');
$pagina           = max(1, intval($_GET['pagina'] ?? 1));
$porPagina        = 25;
$offset           = ($pagina - 1) * $porPagina;

// WHERE dinâmico
$where  = ["DATE(l.data_leitura) BETWEEN ? AND ?"];
$params = [$filtroDataInicio, $filtroDataFim];
$types  = "ss";
if ($filtroPlaca   > 0)   { $where[] = "l.placa_id = ?";       $params[] = $filtroPlaca;         $types .= "i"; }
if ($filtroStatus !== '')  { $where[] = "l.status = ?";         $params[] = $filtroStatus;        $types .= "s"; }
if ($filtroCartao !== '')  { $where[] = "l.cartao_uid LIKE ?";  $params[] = "%$filtroCartao%";    $types .= "s"; }
if ($filtroUser   !== '')  { $where[] = "u.nome LIKE ?";        $params[] = "%$filtroUser%";      $types .= "s"; }
$w = "WHERE " . implode(" AND ", $where);

// Total
$sc = $conn->prepare("SELECT COUNT(*) AS total FROM leituras_rfid l LEFT JOIN users_autorizados u ON l.user_id = u.id $w");
$sc->bind_param($types, ...$params);
$sc->execute();
$totalRegistos = $sc->get_result()->fetch_assoc()['total'];
$totalPaginas  = max(1, ceil($totalRegistos / $porPagina));

// Leituras paginadas
$sl = $conn->prepare("
    SELECT l.id, l.cartao_uid, l.status, l.data_leitura,
           p.nome AS placa_nome,
           u.nome AS user_nome, u.email AS user_email
    FROM leituras_rfid l
    JOIN placas_arduino p ON l.placa_id = p.id
    LEFT JOIN users_autorizados u ON l.user_id = u.id
    $w ORDER BY l.data_leitura DESC LIMIT ? OFFSET ?
");
$pL = array_merge($params, [$porPagina, $offset]);
$sl->bind_param($types . "ii", ...$pL);
$sl->execute();
$leituras = $sl->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$ss = $conn->prepare("
    SELECT COUNT(*) AS total,
           SUM(status='permitido')    AS permitidos,
           SUM(status='desconhecido') AS desconhecidos,
           SUM(status='negado')       AS negados,
           COUNT(DISTINCT cartao_uid) AS cartoes_unicos
    FROM leituras_rfid l LEFT JOIN users_autorizados u ON l.user_id = u.id $w
");
$ss->bind_param($types, ...$params);
$ss->execute();
$stats = $ss->get_result()->fetch_assoc();

// Alertas: desconhecidos nas últimas 24h
$alertas = [];
$ra = $conn->query("
    SELECT l.cartao_uid, COUNT(*) AS tentativas,
           MAX(l.data_leitura) AS ultima_vez,
           p.nome AS placa_nome
    FROM leituras_rfid l
    JOIN placas_arduino p ON l.placa_id = p.id
    WHERE l.status = 'desconhecido'
      AND l.data_leitura >= NOW() - INTERVAL 24 HOUR
    GROUP BY l.cartao_uid, l.placa_id
    ORDER BY ultima_vez DESC LIMIT 8
");
while ($row = $ra->fetch_assoc()) $alertas[] = $row;

// Gráfico por dia
$sg = $conn->prepare("
    SELECT DATE(l.data_leitura) AS dia,
           COUNT(*) AS total,
           SUM(status='permitido')    AS permitidos,
           SUM(status='desconhecido') AS desconhecidos
    FROM leituras_rfid l
    WHERE DATE(l.data_leitura) BETWEEN ? AND ?
    GROUP BY dia ORDER BY dia
");
$sg->bind_param("ss", $filtroDataInicio, $filtroDataFim);
$sg->execute();
$grafRows = $sg->get_result()->fetch_all(MYSQLI_ASSOC);
$grafJSON = json_encode($grafRows);

// Placas para filtro
$placas = $conn->query("SELECT id, nome FROM placas_arduino WHERE ativo=1 ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

// QS base (sem pagina)
$qsBase = http_build_query(['placa_id' => $filtroPlaca, 'status' => $filtroStatus, 'data_inicio' => $filtroDataInicio, 'data_fim' => $filtroDataFim, 'cartao_uid' => $filtroCartao, 'user_nome' => $filtroUser]);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <script>
        (function(){
            const s=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme',s||(d?'dark':'light'));
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leituras RFID</title>
    <style>
        :root{
            --bg:     #FAFAF8;
            --card:   #FFFFFF;
            --cards:  #FFFFFF;
            --border: #E7E4DC;
            --inset:  #F4F2EC;
            --tp:     #18181B; --ts: #5C5A54; --tt: #9A978D;
            --ac:     #6E56CF; --ah: #5A43B8; --al: rgba(110,86,207,.10);
            --ok:     #2E7D55; --warn: #B26A00; --err: #C0392B;
            --sb:     #FCFBF9; --sbb: #E7E4DC;
            --hov:    rgba(110,86,207,.07); --sep: rgba(24,24,27,.07);
            --sh1:    0 1px 2px rgba(24,24,27,.04);
            --sh2:    0 2px 6px rgba(24,24,27,.06);
            --sh3:    0 10px 30px rgba(24,24,27,.10);
        }
        [data-theme="dark"]{
            --bg:     #0E0E11;
            --card:   #16161A;
            --cards:  #16161A;
            --border: #27272F;
            --inset:  #1D1D22;
            --tp:     #ECECEE; --ts: #9B9AA3; --tt: #6A6A73;
            --ac:     #8B72E8; --ah: #9D87F0; --al: rgba(139,114,232,.15);
            --ok:     #4EC28A; --warn: #E0962E; --err: #E5645A;
            --sb:     #131316; --sbb: #27272F;
            --hov:    rgba(139,114,232,.12); --sep: rgba(255,255,255,.07);
            --sh1:    0 1px 2px rgba(0,0,0,.25);
            --sh2:    0 2px 6px rgba(0,0,0,.35);
            --sh3:    0 10px 30px rgba(0,0,0,.50);
        }
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;min-height:100vh;background:var(--bg);color:var(--tp);display:flex}

        @keyframes in   {from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
        @keyframes tIn  {from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
        @keyframes tOut {from{opacity:1}to{opacity:0;transform:translateX(110%)}}
        @keyframes pdot {0%,100%{box-shadow:0 0 0 0 rgba(46,125,85,.5)}50%{box-shadow:0 0 0 6px rgba(46,125,85,0)}}
        @keyframes spin {to{transform:rotate(360deg)}}
        @keyframes mIn  {from{opacity:0;transform:scale(.93) translateY(12px)}to{opacity:1;transform:scale(1) translateY(0)}}

        /* ── Toast ─────────────────────────────────────────────────────────── */
        .tc{position:fixed;top:24px;right:24px;z-index:2000;display:flex;flex-direction:column;gap:10px;pointer-events:none}
        .toast{background:rgba(20,20,20,.95);color:#fff;padding:13px 18px;border-radius:12px;box-shadow:var(--sh3);display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;pointer-events:auto;animation:tIn .3s ease;max-width:360px}
        .toast.hide{animation:tOut .25s ease forwards}
        .toast.s{border-left:3px solid var(--ok)}.toast.e{border-left:3px solid var(--err)}.toast.i{border-left:3px solid var(--ac)}
        .toast button{margin-left:auto;background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:16px;line-height:1}
        .toast button:hover{color:#fff}

        /* ── Sidebar ───────────────────────────────────────────────────────── */
        .sidebar{width:260px;height:100vh;position:fixed;background:var(--sb);border-right:1px solid var(--sbb);padding:24px 16px;z-index:10;display:flex;flex-direction:column}
        .sbh{padding:0 12px 24px;border-bottom:1px solid var(--sep);margin-bottom:16px}
        .sbh h3{font-size:22px;font-weight:700;letter-spacing:-.5px;margin-bottom:4px}
        .sbh p{font-size:13px;color:var(--ts)}
        .nl{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--tt);padding:8px 12px;font-weight:600}
        .sidebar a{display:flex;align-items:center;text-decoration:none;font-size:15px;font-weight:500;padding:10px 12px;border-radius:8px;margin:2px 0;transition:all .2s;color:var(--tp);position:relative}
        .sidebar a:hover,.sidebar a.active{background:var(--hov);color:var(--ac)}
        .sidebar a.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:16px;background:var(--ac);border-radius:0 2px 2px 0}
        .sidebar a svg{width:18px;height:18px;margin-right:10px;stroke:currentColor;fill:none;stroke-width:2;transition:transform .25s}
        .sidebar a:hover svg{transform:scale(1.1)}
        .sbf{margin-top:auto;padding-top:16px;border-top:1px solid var(--sep)}
        .up{display:flex;align-items:center;padding:12px;border-radius:10px;background:var(--card);margin-bottom:8px;border:1px solid var(--border)}
        .av{width:32px;height:32px;border-radius:6px;background:var(--ac);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;margin-right:10px;flex-shrink:0}
        .un{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .ur{font-size:11px;color:var(--ts)}
        .lb{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border-radius:8px;color:var(--err);text-decoration:none;font-size:14px;font-weight:600;transition:all .2s;margin-top:8px}
        .lb:hover{background:rgba(192,57,43,.1)}
        .lb svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2}

        /* ── Aliases para _sidebar.php (class names completos) ─────────────── */
        .sidebar-header{padding:0 12px 24px;border-bottom:1px solid var(--sep);margin-bottom:16px}
        .sidebar-header h3{font-size:22px;font-weight:700;letter-spacing:-.5px;margin-bottom:4px}
        .sidebar-header p,.sidebar-subtitle{font-size:13px;color:var(--ts)}
        .nav-section{margin-bottom:8px}
        .nav-section-title{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--tt);padding:8px 12px;font-weight:600}
        .sidebar-footer{margin-top:auto;padding-top:16px;border-top:1px solid var(--sep)}
        .user-preview{display:flex;align-items:center;padding:12px;border-radius:10px;background:var(--card);margin-bottom:8px;border:1px solid var(--border)}
        .user-avatar{width:32px;height:32px;border-radius:6px;background:var(--ac);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;margin-right:10px;flex-shrink:0}
        .user-info{flex:1;min-width:0}
        .user-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .user-role{font-size:11px;color:var(--ts)}
        .logout-btn{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border-radius:8px;color:var(--err);text-decoration:none;font-size:14px;font-weight:600;transition:all .2s;margin-top:8px}
        .logout-btn:hover{background:rgba(192,57,43,.1)}
        .logout-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2}

        /* ── Content ───────────────────────────────────────────────────────── */
        .content{margin-left:260px;flex:1;padding:32px 40px;animation:in .5s ease}
        .ph{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;gap:16px;flex-wrap:wrap}
        .ph h1{font-size:30px;font-weight:700;letter-spacing:-.8px;margin-bottom:5px}
        .ph p{font-size:16px;color:var(--ts)}
        .ha{display:flex;gap:10px;flex-wrap:wrap}

        /* ── Buttons ───────────────────────────────────────────────────────── */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 16px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s ease,border-color .15s ease,color .15s ease;border:1px solid transparent;outline:none;text-decoration:none}
        .bp{background:var(--ac);color:#fff}.bp:hover{background:var(--ah)}
        .bs{background:var(--card);color:var(--tp);border-color:var(--border)}.bs:hover{background:var(--hov);border-color:var(--ac);color:var(--ac)}
        .bd{background:var(--err);color:#fff}.bd:hover{filter:brightness(.92)}
        .sm{padding:7px 13px;font-size:13px;border-radius:6px}
        .bi{width:15px;height:15px;stroke:currentColor;stroke-width:2;fill:none}

        /* ── Stats ─────────────────────────────────────────────────────────── */
        .sg{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px}
        .sc{background:var(--card);border-radius:12px;padding:20px;border:1px solid var(--border);transition:border-color .18s ease,box-shadow .18s ease}
        .sc:hover{border-color:var(--ac);box-shadow:var(--sh1)}
        .si{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px}
        .si.bl{background:var(--al)}.si.gr{background:rgba(46,125,85,.15)}.si.or{background:rgba(178,106,0,.15)}.si.re{background:rgba(192,57,43,.15)}.si.pu{background:rgba(175,82,222,.15)}
        .sv{font-size:24px;font-weight:700;letter-spacing:-.5px;margin-bottom:3px}
        .sl{font-size:13px;color:var(--ts);font-weight:500}

        /* ── Alertas ───────────────────────────────────────────────────────── */
        .alert-bar{background:rgba(178,106,0,.1);border:1px solid rgba(178,106,0,.3);border-radius:14px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:flex-start;gap:14px}
        .alert-bar svg{width:20px;height:20px;stroke:var(--warn);stroke-width:2;fill:none;flex-shrink:0;margin-top:1px}
        .alert-title{font-size:14px;font-weight:600;color:var(--warn);margin-bottom:10px}
        .alert-chips{display:flex;flex-wrap:wrap;gap:8px}
        .chip{display:inline-flex;align-items:center;gap:6px;background:rgba(178,106,0,.15);border:1px solid rgba(178,106,0,.25);color:var(--warn);padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s}
        .chip:hover{background:rgba(178,106,0,.25)}
        .chip span{background:rgba(178,106,0,.3);border-radius:10px;padding:1px 6px;font-size:11px}

        /* ── Card ──────────────────────────────────────────────────────────── */
        .card{background:var(--card);border-radius:12px;border:1px solid var(--border);overflow:hidden;margin-bottom:22px}
        .ch{padding:16px 22px;border-bottom:1px solid var(--sep);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
        .ct{font-size:16px;font-weight:600}

        /* ── Filtros ───────────────────────────────────────────────────────── */
        .fg{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;padding:18px 22px;align-items:end}
        .fl{display:block;font-size:11px;font-weight:600;color:var(--ts);text-transform:uppercase;letter-spacing:.3px;margin-bottom:6px}
        .fi{width:100%;padding:10px 13px;border-radius:10px;border:1px solid var(--border);background:var(--cards);font-size:14px;color:var(--tp);outline:none;transition:all .2s;appearance:none}
        .fi:focus{border-color:var(--ac);box-shadow:0 0 0 3px rgba(110,86,207,.12)}
        .fsel{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%236e6e73' stroke-width='2' d='M1 4l5 5 5-5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px}
        .fa{display:flex;gap:8px}

        /* ── Chart ─────────────────────────────────────────────────────────── */
        .chart-wrap{padding:18px 22px 12px;position:relative;height:190px}
        .chart-empty{height:160px;display:flex;align-items:center;justify-content:center;color:var(--tt);font-size:14px}

        /* ── Table ─────────────────────────────────────────────────────────── */
        .tw{overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:13px 16px;text-align:left;font-size:14px}
        th{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:var(--ts);background:rgba(0,0,0,.02)}
        [data-theme="dark"] th{background:rgba(255,255,255,.02)}
        tr{border-bottom:1px solid var(--sep)}
        tr:last-child{border-bottom:none}
        tbody tr{cursor:pointer;transition:background .15s}
        tbody tr:hover{background:var(--hov)}
        td code{font-family:ui-monospace,"SF Mono",monospace;font-size:12px;background:var(--hov);padding:3px 7px;border-radius:6px}

        /* ── Badge ─────────────────────────────────────────────────────────── */
        .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}
        .b-ok{background:rgba(46,125,85,.15);color:var(--ok)}
        .b-err{background:rgba(192,57,43,.15);color:var(--err)}
        .b-warn{background:rgba(178,106,0,.15);color:var(--warn)}

        /* ── Pagination ────────────────────────────────────────────────────── */
        .pag{display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-top:1px solid var(--sep);flex-wrap:wrap;gap:10px}
        .pag-info{font-size:13px;color:var(--ts)}
        .pag-btns{display:flex;gap:6px;flex-wrap:wrap}
        .pb{min-width:34px;height:34px;padding:0 10px;border-radius:8px;border:1px solid var(--border);background:var(--card);font-size:13px;font-weight:600;color:var(--tp);cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
        .pb:hover{background:var(--hov);border-color:var(--ac);color:var(--ac)}
        .pb.active{background:var(--ac);color:#fff;border-color:var(--ac)}
        .pb:disabled{opacity:.35;pointer-events:none}

        /* ── Empty ─────────────────────────────────────────────────────────── */
        .empty{text-align:center;padding:52px 20px}
        .ei{width:52px;height:52px;margin:0 auto 14px;background:var(--hov);border-radius:14px;display:flex;align-items:center;justify-content:center}
        .ei svg{width:24px;height:24px;stroke:var(--tt);stroke-width:2;fill:none}
        .empty h3{font-size:16px;font-weight:600;margin-bottom:5px}
        .empty p{font-size:14px;color:var(--ts);max-width:260px;margin:0 auto}

        /* ── Modal histórico ───────────────────────────────────────────────── */
        .overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px}
        .overlay.open{display:flex}
        .modal{background:var(--cards);border-radius:20px;border:1px solid var(--border);box-shadow:var(--sh3);width:100%;max-width:580px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;animation:mIn .3s ease}
        .mh{padding:20px 24px;border-bottom:1px solid var(--sep);display:flex;justify-content:space-between;align-items:center}
        .mh h3{font-size:18px;font-weight:600}
        .mh button{background:none;border:none;cursor:pointer;width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--ts);font-size:20px;transition:all .2s}
        .mh button:hover{background:var(--hov);color:var(--tp)}
        .mb{overflow-y:auto;flex:1}
        .minfo{padding:16px 24px;background:var(--hov);border-bottom:1px solid var(--sep);display:flex;gap:20px;flex-wrap:wrap}
        .minfo-item label{font-size:11px;text-transform:uppercase;letter-spacing:.3px;color:var(--tt);font-weight:600;display:block;margin-bottom:3px}
        .minfo-item span{font-size:14px;font-weight:600}
        .loading{display:flex;align-items:center;justify-content:center;padding:40px;gap:12px;color:var(--ts);font-size:14px}
        .spinner{width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--ac);border-radius:50%;animation:spin .7s linear infinite}

        @media(max-width:768px){
            .sidebar{width:100%;height:auto;position:relative;border-right:none;border-bottom:1px solid var(--sbb)}
            .content{margin-left:0;padding:20px 16px}
            .ph{flex-direction:column}
        }
    </style>
</head>
<body>

<div class="tc" id="tc"></div>

<!-- Modal Histórico de Cartão -->
<div class="overlay" id="modalOverlay">
    <div class="modal">
        <div class="mh">
            <h3 id="modalTitulo">Histórico do Cartão</h3>
            <button onclick="closeModal()">✕</button>
        </div>
        <div class="mb" id="modalBody">
            <div class="loading"><div class="spinner"></div> A carregar...</div>
        </div>
    </div>
</div>

<!-- Sidebar -->
<?php require '_sidebar.php'; ?>

<!-- Content -->
<div class="content">

    <div class="ph">
        <div>
            <h1>Leituras RFID</h1>
            <p>Histórico de acessos e leituras das placas Arduino</p>
        </div>
        <div class="ha">
            <button class="btn bs sm" onclick="exportCSV()">
                <svg class="bi" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar CSV
            </button>
            <button class="btn bs sm" onclick="location.reload()">
                <svg class="bi" viewBox="0 0 24 24"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Atualizar
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="sg">
        <div class="sc"><div class="si bl"><svg viewBox="0 0 24 24" fill="none" stroke="var(--ac)" stroke-width="2"><path d="M1 6l5.5 5.5L12 6l5.5 5.5L23 6"/><path d="M1 13l5.5 5.5L12 13l5.5 5.5L23 13"/></svg></div><div class="sv"><?php echo number_format($stats['total']); ?></div><div class="sl">Total de Leituras</div></div>
        <div class="sc"><div class="si gr"><svg viewBox="0 0 24 24" fill="none" stroke="var(--ok)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div class="sv"><?php echo number_format($stats['permitidos']); ?></div><div class="sl">Permitidos</div></div>
        <div class="sc"><div class="si or"><svg viewBox="0 0 24 24" fill="none" stroke="var(--warn)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="sv"><?php echo number_format($stats['desconhecidos']); ?></div><div class="sl">Desconhecidos</div></div>
        <div class="sc"><div class="si re"><svg viewBox="0 0 24 24" fill="none" stroke="var(--err)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></div><div class="sv"><?php echo number_format($stats['negados']); ?></div><div class="sl">Negados</div></div>
        <div class="sc"><div class="si pu"><svg viewBox="0 0 24 24" fill="none" stroke="#8B72E8" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div><div class="sv"><?php echo number_format($stats['cartoes_unicos']); ?></div><div class="sl">Cartões Únicos</div></div>
    </div>

    <!-- Alertas cartões desconhecidos -->
    <?php if (!empty($alertas)): ?>
    <div class="alert-bar">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div style="flex:1">
            <div class="alert-title">⚠ Cartões desconhecidos nas últimas 24h</div>
            <div class="alert-chips">
                <?php foreach ($alertas as $a): ?>
                <div class="chip" onclick="abrirHistorico('<?php echo htmlspecialchars($a['cartao_uid']); ?>')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <?php echo htmlspecialchars($a['cartao_uid']); ?>
                    <span><?php echo $a['tentativas']; ?>×</span>
                    <span style="opacity:.7;font-weight:400"><?php echo htmlspecialchars($a['placa_nome']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card">
        <div class="ch">
            <span class="ct">
                <svg style="width:15px;height:15px;stroke:var(--ac);stroke-width:2;fill:none;vertical-align:middle;margin-right:6px" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filtros
            </span>
        </div>
        <form method="GET" action="leituras_rfid.php">
            <div class="fg">
                <div>
                    <label class="fl">Data Início</label>
                    <input type="date" class="fi" name="data_inicio" value="<?php echo htmlspecialchars($filtroDataInicio); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="fl">Data Fim</label>
                    <input type="date" class="fi" name="data_fim" value="<?php echo htmlspecialchars($filtroDataFim); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="fl">Placa</label>
                    <select class="fi fsel" name="placa_id">
                        <option value="0">Todas as placas</option>
                        <?php foreach ($placas as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $filtroPlaca == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="fl">Estado</label>
                    <select class="fi fsel" name="status">
                        <option value="">Todos</option>
                        <option value="permitido"    <?php echo $filtroStatus==='permitido'    ?'selected':''; ?>>✅ Permitido</option>
                        <option value="desconhecido" <?php echo $filtroStatus==='desconhecido' ?'selected':''; ?>>❓ Desconhecido</option>
                        <option value="negado"       <?php echo $filtroStatus==='negado'       ?'selected':''; ?>>🚫 Negado</option>
                    </select>
                </div>
                <div>
                    <label class="fl">Cartão UID</label>
                    <input type="text" class="fi" name="cartao_uid" value="<?php echo htmlspecialchars($filtroCartao); ?>" placeholder="Pesquisar UID...">
                </div>
                <div>
                    <label class="fl">Utilizador</label>
                    <input type="text" class="fi" name="user_nome" value="<?php echo htmlspecialchars($filtroUser); ?>" placeholder="Nome do utilizador...">
                </div>
                <div>
                    <label class="fl">&nbsp;</label>
                    <div class="fa">
                        <button type="submit" class="btn bp" style="flex:1">Filtrar</button>
                        <a href="leituras_rfid.php" class="btn bs">Limpar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Gráfico -->
    <div class="card">
        <div class="ch">
            <span class="ct">Leituras por Dia</span>
            <span style="font-size:13px;color:var(--ts)"><?php echo date('d/m/Y', strtotime($filtroDataInicio)); ?> → <?php echo date('d/m/Y', strtotime($filtroDataFim)); ?></span>
        </div>
        <?php if (empty($grafRows)): ?>
        <div class="chart-empty">Sem dados no período selecionado</div>
        <?php else: ?>
        <div class="chart-wrap"><canvas id="chartCanvas"></canvas></div>
        <?php endif; ?>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="ch">
            <span class="ct">Registo de Leituras</span>
            <span style="font-size:13px;color:var(--ts)">
                <?php
                $ini = ($pagina-1)*$porPagina+1;
                $fim = min($pagina*$porPagina,$totalRegistos);
                echo $totalRegistos>0 ? "$ini-$fim de ".number_format($totalRegistos)." registos" : "0 registos";
                ?>
            </span>
        </div>

        <?php if (empty($leituras)): ?>
        <div class="empty">
            <div class="ei"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg></div>
            <h3>Nenhuma leitura encontrada</h3>
            <p>Tenta ajustar os filtros ou o intervalo de datas.</p>
        </div>
        <?php else: ?>
        <div class="tw">
            <table id="tabela">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Placa</th>
                        <th>Cartão UID</th>
                        <th>Utilizador</th>
                        <th>Data / Hora</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($leituras as $i => $l):
                    $badge = match($l['status']) { 'permitido'=>'b-ok', 'negado'=>'b-err', default=>'b-warn' };
                    $rn = ($pagina-1)*$porPagina+$i+1;
                ?>
                <tr onclick="abrirHistorico('<?php echo htmlspecialchars($l['cartao_uid']); ?>')" title="Clica para ver histórico deste cartão">
                    <td style="color:var(--tt);font-size:12px"><?php echo $rn; ?></td>
                    <td><?php echo htmlspecialchars($l['placa_nome']); ?></td>
                    <td><code><?php echo htmlspecialchars($l['cartao_uid']); ?></code></td>
                    <td>
                        <?php if ($l['user_nome']): ?>
                            <span style="font-weight:500"><?php echo htmlspecialchars($l['user_nome']); ?></span>
                            <?php if ($l['user_email']): ?><br><span style="font-size:12px;color:var(--tt)"><?php echo htmlspecialchars($l['user_email']); ?></span><?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--tt);font-style:italic">Desconhecido</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php echo date('d/m/Y', strtotime($l['data_leitura'])); ?>
                        <span style="color:var(--tt)"><?php echo date('H:i:s', strtotime($l['data_leitura'])); ?></span>
                    </td>
                    <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($l['status']); ?></span></td>
                    <td>
                        <?php if (!$l['user_nome']): ?>
                            <a href="adicionar_user.php?uid=<?php echo urlencode($l['cartao_uid']); ?>" class="btn bp sm" title="Registar este UID">Registar</a>
                        <?php else: ?>
                            <a href="users.php?search=<?php echo urlencode($l['user_email'] ?: $l['user_nome']); ?>" class="btn bs sm" title="Ver utilizador">Ver</a>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
        <div class="pag">
            <span class="pag-info"><?php echo $totalPaginas; ?> páginas</span>
            <div class="pag-btns">
                <?php
                if ($pagina > 1) echo "<a href='?$qsBase&pagina=".($pagina-1)."' class='pb'>‹</a>";
                else echo "<button class='pb' disabled>‹</button>";
                for ($p = max(1,$pagina-2); $p <= min($totalPaginas,$pagina+2); $p++)
                    echo "<a href='?$qsBase&pagina=$p' class='pb".($p==$pagina?' active':'')."'>$p</a>";
                if ($pagina < $totalPaginas) echo "<a href='?$qsBase&pagina=".($pagina+1)."' class='pb'>›</a>";
                else echo "<button class='pb' disabled>›</button>";
                ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// Verificar msg=ok na URL
(function(){
    const params = new URLSearchParams(window.location.search);
    if (params.get('msg') === 'ok') {
        const uid = params.get('uid') || '';
        toast(`Utilizador registado para UID ${uid}`, 's', 4500);
        history.replaceState(null, '', location.pathname);
    }
})();

// Toast
function toast(msg, type='i', ms=3000) {
    const c = document.getElementById('tc');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span style="flex:1">${msg}</span><button onclick="this.parentElement.remove()">✕</button>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('hide'); setTimeout(() => t.remove(), 250); }, ms);
}

// Gráfico
const gd = <?php echo $grafJSON; ?>;
if (gd.length > 0 && document.getElementById('chartCanvas')) {
    const dark   = document.documentElement.getAttribute('data-theme') === 'dark';
    const grid   = dark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
    const txtClr = dark ? '#8e8e93' : '#6e6e73';
    const ctx    = document.getElementById('chartCanvas').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: gd.map(r => { const [y,m,d]=r.dia.split('-'); return `${d}/${m}`; }),
            datasets: [
                {
                    label: 'Permitidos',
                    data: gd.map(r => r.permitidos),
                    backgroundColor: dark ? 'rgba(48,209,88,.7)' : 'rgba(46,125,85,.7)',
                    borderRadius: 4
                },
                {
                    label: 'Desconhecidos',
                    data: gd.map(r => r.desconhecidos),
                    backgroundColor: dark ? 'rgba(255,159,10,.7)' : 'rgba(178,106,0,.7)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: txtClr, font: { size: 12 } } },
                tooltip: { backgroundColor:'rgba(20,20,20,.92)', titleColor:'#fff', bodyColor:'#fff', padding:10, cornerRadius:10 }
            },
            scales: {
                x: { stacked: true, grid: { color: grid }, ticks: { color: txtClr, font: { size: 12 } } },
                y: { stacked: true, grid: { color: grid }, ticks: { color: txtClr, font: { size: 12 }, precision: 0 }, beginAtZero: true }
            }
        }
    });
}

// Modal histórico de cartão
function abrirHistorico(uid) {
    document.getElementById('modalTitulo').textContent = `Cartão: ${uid}`;
    document.getElementById('modalBody').innerHTML = `<div class="loading"><div class="spinner"></div> A carregar...</div>`;
    document.getElementById('modalOverlay').classList.add('open');

    fetch(`leituras_rfid.php?cartao_historico=${encodeURIComponent(uid)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                document.getElementById('modalBody').innerHTML = `<div class="empty"><div class="ei"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg></div><h3>Sem histórico</h3><p>Nenhuma leitura encontrada para este cartão.</p></div>`;
                return;
            }

            const badgeMap = { permitido:'b-ok', negado:'b-err', desconhecido:'b-warn' };
            const user = data[0].user_nome || 'Desconhecido';
            const total = data.length;

            let html = `
                <div class="minfo">
                    <div class="minfo-item"><label>Utilizador</label><span>${user}</span></div>
                    <div class="minfo-item"><label>Total de Leituras</label><span>${total}</span></div>
                    <div class="minfo-item"><label>Primeira Vez</label><span>${fmtDate(data[data.length-1].data_leitura)}</span></div>
                    <div class="minfo-item"><label>Última Vez</label><span>${fmtDate(data[0].data_leitura)}</span></div>
                </div>
                <div style="overflow-x:auto">
                <table>
                    <thead><tr><th>Data / Hora</th><th>Placa</th><th>Estado</th></tr></thead>
                    <tbody>`;

            data.forEach(l => {
                const b = badgeMap[l.status] || 'b-warn';
                html += `<tr>
                    <td style="white-space:nowrap">${fmtDate(l.data_leitura)}</td>
                    <td>${l.placa_nome}</td>
                    <td><span class="badge ${b}">${l.status.charAt(0).toUpperCase()+l.status.slice(1)}</span></td>
                </tr>`;
            });

            html += `</tbody></table></div>`;
            document.getElementById('modalBody').innerHTML = html;
        })
        .catch(() => toast('Erro ao carregar histórico', 'e'));
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}

document.getElementById('modalOverlay').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});

function fmtDate(dt) {
    const d = new Date(dt);
    return d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT');
}

// Exportar CSV
function exportCSV() {
    const rows = document.querySelectorAll('#tabela tr');
    if (rows.length <= 1) { toast('Sem dados para exportar', 'e'); return; }
    let csv = [];
    rows.forEach(row => {
        const cells = [...row.querySelectorAll('th,td')].map(c => `"${c.innerText.replace(/\n/g,' ').trim().replace(/"/g,'""')}"`);
        csv.push(cells.join(','));
    });
    const blob = new Blob(['\uFEFF' + csv.join('\n')], { type:'text/csv;charset=utf-8;' });
    const a = Object.assign(document.createElement('a'), { href:URL.createObjectURL(blob), download:`leituras_rfid_<?php echo $filtroDataInicio; ?>_<?php echo $filtroDataFim; ?>.csv` });
    a.click();
    URL.revokeObjectURL(a.href);
    toast('CSV exportado!', 's');
}

// Auto-refresh: recarrega quando chegam novas leituras
(function autoRefresh() {
    const baseline = <?php echo time(); ?>;
    let lastTs = baseline;

    function check() {
        fetch('api_check_updates.php')
            .then(r => r.json())
            .then(d => {
                if (d.leituras > lastTs) {
                    lastTs = d.leituras;
                    location.reload();
                }
            })
            .catch(() => {})
            .finally(() => setTimeout(check, 3000));
    }
    setTimeout(check, 3000);
})();
</script>
</body>
</html>