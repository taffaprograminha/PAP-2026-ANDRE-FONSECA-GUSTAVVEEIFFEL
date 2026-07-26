<?php
session_start();
require_once "ligacao.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$userName = ucwords(str_replace(['.', '_'], ' ', explode('@', $email)[0]));

// Período (hoje / 7d / 30d / tudo)
$period = $_GET['period'] ?? '7d';
if (!in_array($period, ['today','7d','30d','all'], true)) $period = '7d';

// Constrói filtro WHERE e label legível
switch ($period) {
    case 'today':
        $whereLeit  = "DATE(l.data_leitura) = CURDATE()";
        $whereLeit2 = "DATE(data_leitura) = CURDATE()";
        $periodLabel = "Hoje";
        $diasParaGrafico = 1;
        break;
    case '30d':
        $whereLeit  = "l.data_leitura >= NOW() - INTERVAL 30 DAY";
        $whereLeit2 = "data_leitura >= NOW() - INTERVAL 30 DAY";
        $periodLabel = "Últimos 30 dias";
        $diasParaGrafico = 30;
        break;
    case 'all':
        $whereLeit  = "1=1";
        $whereLeit2 = "1=1";
        $periodLabel = "Sempre";
        $diasParaGrafico = 30;
        break;
    case '7d':
    default:
        $whereLeit  = "l.data_leitura >= NOW() - INTERVAL 7 DAY";
        $whereLeit2 = "data_leitura >= NOW() - INTERVAL 7 DAY";
        $periodLabel = "Últimos 7 dias";
        $diasParaGrafico = 7;
        break;
}

// KPIs gerais
$sqlGeral = "
    SELECT
        (SELECT COUNT(*) FROM users_autorizados) AS total_users,
        (SELECT COUNT(*) FROM users_autorizados WHERE ativo=1) AS users_ativos,
        (SELECT COUNT(*) FROM placas_arduino WHERE ativo=1) AS total_placas,
        (SELECT COUNT(*) FROM placas_arduino WHERE estado='online' AND ativo=1) AS online_placas,
        (SELECT COUNT(*) FROM leituras_rfid WHERE $whereLeit2) AS acessos_periodo,
        (SELECT COUNT(*) FROM leituras_rfid WHERE status='permitido' AND $whereLeit2) AS permitidos_periodo,
        (SELECT COUNT(*) FROM leituras_rfid WHERE status='negado' AND $whereLeit2) AS negados_periodo,
        (SELECT COUNT(*) FROM leituras_rfid WHERE status='desconhecido' AND $whereLeit2) AS desconhecidos_periodo,
        (SELECT COUNT(*) FROM cartoes_bloqueados) AS cartoes_bloqueados
";
$statsGeral = $conn->query($sqlGeral)->fetch_assoc();

// Taxa de sucesso global
$totalLeit = (int)$statsGeral['acessos_periodo'];
$taxaSucesso = $totalLeit > 0 ? round(($statsGeral['permitidos_periodo'] / $totalLeit) * 100, 1) : 0;

// Acessos por hora (do período)
$sqlHoras = "
    SELECT HOUR(data_leitura) as hora, COUNT(*) as total
    FROM leituras_rfid
    WHERE $whereLeit2
    GROUP BY hora
    ORDER BY hora ASC
";
$resHoras = $conn->query($sqlHoras);
$horasMap = array_fill(0, 24, 0);
while ($row = $resHoras->fetch_assoc()) {
    $horasMap[(int)$row['hora']] = (int)$row['total'];
}
$horasLabels = array_map(fn($h) => $h.'h', array_keys($horasMap));
$horasData   = array_values($horasMap);

// Acessos por dia (linha) - últimos N dias
$sqlDias = "
    SELECT DATE(data_leitura) as dia, COUNT(*) as total
    FROM leituras_rfid
    WHERE data_leitura >= NOW() - INTERVAL $diasParaGrafico DAY
    GROUP BY dia
    ORDER BY dia ASC
";
$resDias = $conn->query($sqlDias);
$diasLabels = [];
$diasData   = [];
while ($row = $resDias->fetch_assoc()) {
    $diasLabels[] = date('d/m', strtotime($row['dia']));
    $diasData[]   = (int)$row['total'];
}

// Distribuição de status (donut)
$sqlStatus = "
    SELECT status, COUNT(*) as total
    FROM leituras_rfid
    WHERE $whereLeit2
    GROUP BY status
";
$resStatus = $conn->query($sqlStatus);
$statusLabels = [];
$statusData   = [];
while ($row = $resStatus->fetch_assoc()) {
    $statusLabels[] = ucfirst($row['status']);
    $statusData[]   = (int)$row['total'];
}

// Heatmap: dia da semana × hora
// DAYOFWEEK: 1=Dom, 2=Seg ... 7=Sab → reordeno para Seg ... Dom
$sqlHeat = "
    SELECT DAYOFWEEK(data_leitura) as dia, HOUR(data_leitura) as hora, COUNT(*) as total
    FROM leituras_rfid
    WHERE $whereLeit2
    GROUP BY dia, hora
";
$resHeat = $conn->query($sqlHeat);
// matriz [dia 0..6][hora 0..23] onde 0=Seg, 6=Dom
$heatMap = array_fill(0, 7, array_fill(0, 24, 0));
$maxHeat = 0;
while ($row = $resHeat->fetch_assoc()) {
    // converte 1=Dom..7=Sab para 0=Seg..6=Dom
    $diaMysql = (int)$row['dia'];
    $dia = ($diaMysql + 5) % 7; // Dom(1)->5? não. Vamos: Seg(2)->0, Ter(3)->1, ..., Sab(7)->5, Dom(1)->6
    $dia = ($diaMysql === 1) ? 6 : ($diaMysql - 2);
    $h   = (int)$row['hora'];
    $t   = (int)$row['total'];
    $heatMap[$dia][$h] = $t;
    if ($t > $maxHeat) $maxHeat = $t;
}
$diasSemana = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];

// Ranking de placas (com taxa de sucesso)
$sqlPlacas = "
    SELECT p.nome, p.localizacao, p.estado,
           COUNT(l.id) as total,
           SUM(CASE WHEN l.status='permitido'    THEN 1 ELSE 0 END) as permitidos,
           SUM(CASE WHEN l.status='negado'       THEN 1 ELSE 0 END) as negados,
           SUM(CASE WHEN l.status='desconhecido' THEN 1 ELSE 0 END) as desconhecidos
    FROM placas_arduino p
    LEFT JOIN leituras_rfid l ON p.id = l.placa_id AND ($whereLeit)
    WHERE p.ativo = 1
    GROUP BY p.id
    ORDER BY total DESC
";
$resPlacas = $conn->query($sqlPlacas);

// Top utilizadores (mais acessos no período)
$sqlTopUsers = "
    SELECT u.nome, u.email, COUNT(l.id) as total_acessos
    FROM users_autorizados u
    LEFT JOIN leituras_rfid l ON u.id = l.user_id AND l.status = 'permitido' AND ($whereLeit)
    GROUP BY u.id
    HAVING total_acessos > 0
    ORDER BY total_acessos DESC
    LIMIT 5
";
$resTopUsers = $conn->query($sqlTopUsers);

// Utilizadores inativos (não acedem há > 30 dias OU nunca)
$sqlInativos = "
    SELECT u.id, u.nome, u.email, u.rfid_uid,
           MAX(l.data_leitura) AS ultimo_acesso,
           DATEDIFF(NOW(), MAX(l.data_leitura)) AS dias_sem_acesso
    FROM users_autorizados u
    LEFT JOIN leituras_rfid l ON l.user_id = u.id AND l.status = 'permitido'
    WHERE u.ativo = 1
    GROUP BY u.id
    HAVING MAX(l.data_leitura) IS NULL OR MAX(l.data_leitura) < NOW() - INTERVAL 30 DAY
    ORDER BY MAX(l.data_leitura) IS NULL DESC, MAX(l.data_leitura) ASC
    LIMIT 10
";
$resInativos = $conn->query($sqlInativos);

// Últimas 10 leituras
$sqlRecentes = "
    SELECT l.data_leitura, l.status, l.cartao_uid,
           u.nome as user_nome,
           p.nome as placa_nome
    FROM leituras_rfid l
    LEFT JOIN users_autorizados u ON l.user_id = u.id
    JOIN placas_arduino p ON l.placa_id = p.id
    ORDER BY l.data_leitura DESC
    LIMIT 10
";
$resRecentes = $conn->query($sqlRecentes);

// Sensores (última leitura)
$sqlSensor = "SELECT temperatura, humidade, data_hora FROM sensores ORDER BY data_hora DESC LIMIT 1";
$sensor = $conn->query($sqlSensor)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <script>
        (function() {
            const saved = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = saved || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            const textSize = localStorage.getItem('textSize') || 'medium';
            const sizes = { small: '90%', medium: '100%', large: '110%' };
            document.documentElement.style.fontSize = sizes[textSize];
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatísticas</title>
    <link rel="stylesheet" href="dashboard.css?v=<?php echo @filemtime("dashboard.css"); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .period-bar {
            display: flex; gap: 8px; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap;
        }
        .period-bar .seg {
            display: inline-flex; gap: 2px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px; padding: 3px;
        }
        .period-bar .seg a {
            padding: 7px 14px; font-size: 13px; font-weight: 500;
            color: var(--text-secondary); text-decoration: none;
            border-radius: 7px; transition: all .15s ease;
        }
        .period-bar .seg a:hover { color: var(--text-primary); }
        .period-bar .seg a.active {
            background: var(--accent); color: #fff;
        }
        .period-bar .export-btn {
            margin-left: auto;
            padding: 8px 16px; font-size: 13px; font-weight: 500;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px; color: var(--text-primary);
            text-decoration: none; cursor: pointer;
            transition: border-color .15s ease;
        }
        .period-bar .export-btn:hover { border-color: var(--accent); }

        .stats-grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 22px;
            position: relative;
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            left: 0; top: 16px; bottom: 16px;
            width: 3px;
            background: var(--accent-color, var(--accent));
            border-radius: 0 2px 2px 0;
        }
        .stat-card:hover { border-color: var(--accent); box-shadow: var(--shadow-sm); }
        .stat-card .label {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase;
            color: var(--text-tertiary);
            letter-spacing: 0.7px;
        }
        .stat-card .number {
            font-size: 30px; font-weight: 700;
            letter-spacing: -0.8px; margin-top: 6px;
            color: var(--text-primary);
        }
        .stat-card .sub {
            font-size: 13px; color: var(--text-secondary); margin-top: 4px;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .charts-grid .card.full { grid-column: span 2; }
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left;
            font-size: 11px; font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-tertiary);
            padding: 8px 12px;
            border-bottom: 1px solid var(--separator);
        }
        .data-table td {
            padding: 10px 12px; font-size: 14px;
            border-bottom: 1px solid var(--separator);
        }
        .badge {
            display: inline-block;
            padding: 3px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 600;
        }
        .badge.permitido  { background: rgba(46,125,85,0.15);  color: #2E7D55; }
        .badge.negado     { background: rgba(192,57,43,0.15);  color: #C0392B; }
        .badge.desconhecido { background: rgba(178,106,0,0.15); color: #B26A00; }
        .badge.online  { background: rgba(46,125,85,0.15);  color: #2E7D55; }
        .badge.offline { background: rgba(192,57,43,0.15);  color: #C0392B; }

        .sensor-card {
            display: flex; gap: 20px;
            justify-content: center;
            margin-bottom: 24px;
        }
        .sensor-item {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px 40px;
            text-align: center;
            transition: border-color .18s ease;
        }
        .sensor-item:hover { border-color: var(--accent); }
        .sensor-item .icon { font-size: 32px; }
        .sensor-item .value { font-size: 28px; font-weight: 700; letter-spacing: -0.6px; margin-top: 6px; color: var(--text-primary); }
        .sensor-item .label { font-size: 12px; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; margin-top: 6px; }

        .placa-rate {
            display: flex; align-items: center; gap: 8px;
        }
        .placa-rate-bar {
            flex: 1;
            display: flex;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background: var(--separator);
            min-width: 60px;
        }
        .placa-rate-bar .ok { background: #2E7D55; }
        .placa-rate-bar .ko { background: #C0392B; }
        .placa-rate-bar .un { background: #B26A00; }
        .placa-rate-val {
            font-size: 12px; font-weight: 600;
            color: var(--text-secondary);
            min-width: 38px; text-align: right;
        }

        /* Heatmap */
        .heatmap-wrap { overflow-x: auto; padding: 4px 0; }
        .heatmap {
            display: grid;
            grid-template-columns: 40px repeat(24, minmax(22px, 1fr));
            gap: 3px;
            min-width: 640px;
        }
        .hm-cell {
            font-size: 10px; text-align: center;
            padding: 6px 0;
            color: var(--text-tertiary);
            font-weight: 600;
        }
        .hm-day {
            font-size: 11px; font-weight: 600;
            color: var(--text-secondary);
            text-align: center; padding: 6px 0;
            display: flex; align-items: center; justify-content: center;
        }
        .hm-box {
            aspect-ratio: 1/1;
            border-radius: 4px;
            background: var(--separator);
            position: relative;
            cursor: default;
            transition: transform .12s ease;
        }
        .hm-box:hover { transform: scale(1.4); z-index: 2; }
        .hm-box[title]:hover::after {
            content: attr(title);
            position: absolute; bottom: 110%; left: 50%;
            transform: translateX(-50%);
            background: var(--text-primary);
            color: var(--card-bg);
            padding: 4px 8px; border-radius: 4px;
            font-size: 11px; white-space: nowrap;
            pointer-events: none;
        }
        .hm-legend {
            display: flex; align-items: center; gap: 6px;
            justify-content: flex-end;
            margin-top: 10px;
            font-size: 11px;
            color: var(--text-tertiary);
        }
        .hm-legend .scale {
            display: flex; gap: 2px;
        }
        .hm-legend .scale span {
            width: 14px; height: 14px; border-radius: 3px;
        }

        .ultimo-acesso-nunca { color: var(--text-tertiary); font-style: italic; }
        .ultimo-acesso-dias { font-weight: 600; }
    </style>
</head>
<body>

<?php require '_sidebar.php'; ?>

<div class="content">
    <div class="page-header">
        <div class="page-title-section">
            <h1>Estatísticas</h1>
            <p class="page-subtitle">Visão geral do sistema RFID — <?php echo $periodLabel; ?></p>
        </div>
    </div>

    <!-- Filtro de período + Exportar -->
    <div class="period-bar">
        <div class="seg">
            <a href="?period=today" class="<?php echo $period==='today'?'active':''; ?>">Hoje</a>
            <a href="?period=7d"    class="<?php echo $period==='7d'   ?'active':''; ?>">7 dias</a>
            <a href="?period=30d"   class="<?php echo $period==='30d'  ?'active':''; ?>">30 dias</a>
            <a href="?period=all"   class="<?php echo $period==='all'  ?'active':''; ?>">Sempre</a>
        </div>
        <a class="export-btn" href="export_excel.php?period=<?php echo $period; ?>">⬇ Exportar Excel</a>
    </div>

    <!-- KPIs -->
    <div class="stats-grid-4">
        <div class="stat-card" style="--accent-color: #6E56CF">
            <div class="label">Utilizadores</div>
            <div class="number"><?php echo $statsGeral['total_users']; ?></div>
            <div class="sub"><?php echo $statsGeral['users_ativos']; ?> ativos</div>
        </div>
        <div class="stat-card" style="--accent-color: #2E7D55">
            <div class="label">Leitores Online</div>
            <div class="number"><?php echo $statsGeral['online_placas']; ?>/<?php echo $statsGeral['total_placas']; ?></div>
            <div class="sub">placas ativas</div>
        </div>
        <div class="stat-card" style="--accent-color: #B26A00">
            <div class="label">Acessos (<?php echo $periodLabel; ?>)</div>
            <div class="number"><?php echo $statsGeral['acessos_periodo']; ?></div>
            <div class="sub"><?php echo $statsGeral['permitidos_periodo']; ?> permitidos · <?php echo $taxaSucesso; ?>% sucesso</div>
        </div>
        <div class="stat-card" style="--accent-color: #C0392B">
            <div class="label">Negados / Desconhecidos</div>
            <div class="number"><?php echo $statsGeral['negados_periodo'] + $statsGeral['desconhecidos_periodo']; ?></div>
            <div class="sub"><?php echo $statsGeral['cartoes_bloqueados']; ?> cartões bloqueados</div>
        </div>
    </div>

    <!-- SENSORES -->
    <?php if ($sensor): ?>
    <div class="sensor-card">
        <div class="sensor-item">
            <div class="icon">🌡️</div>
            <div class="value"><?php echo $sensor['temperatura'] ?? '--'; ?>°C</div>
            <div class="label">Temperatura</div>
        </div>
        <div class="sensor-item">
            <div class="icon">💧</div>
            <div class="value"><?php echo $sensor['humidade'] ?? '--'; ?>%</div>
            <div class="label">Humidade</div>
        </div>
        <div class="sensor-item">
            <div class="icon">🕐</div>
            <div class="value" style="font-size:16px"><?php echo date('H:i', strtotime($sensor['data_hora'])); ?></div>
            <div class="label">Última leitura</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- GRÁFICOS -->
    <div class="charts-grid">
        <div class="card full">
            <div class="card-header">
                <h3 class="card-title">Acessos por Dia</h3>
            </div>
            <div class="card-content">
                <canvas id="chartDias" height="100"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Distribuição de Acessos</h3>
            </div>
            <div class="card-content">
                <canvas id="chartStatus" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- HEATMAP semanal -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3 class="card-title">Padrão Semanal — Dia × Hora</h3>
            <span class="card-action" style="cursor:default; opacity:.7">passa o rato pelas células</span>
        </div>
        <div class="card-content">
            <div class="heatmap-wrap">
                <div class="heatmap">
                    <div></div>
                    <?php for ($h = 0; $h < 24; $h++): ?>
                        <div class="hm-cell"><?php echo $h; ?></div>
                    <?php endfor; ?>
                    <?php foreach ($diasSemana as $i => $dia): ?>
                        <div class="hm-day"><?php echo $dia; ?></div>
                        <?php for ($h = 0; $h < 24; $h++):
                            $val = $heatMap[$i][$h];
                            $intensity = $maxHeat > 0 ? min(1, $val / $maxHeat) : 0;
                            $alpha = $val === 0 ? 0 : (0.15 + $intensity * 0.85);
                            $style = $val > 0 ? "background: rgba(110, 86, 207, " . round($alpha, 2) . ")" : "";
                            $title = $val > 0 ? "$dia $h h — $val acesso" . ($val === 1 ? '' : 's') : "";
                        ?>
                            <div class="hm-box" <?php if ($style) echo 'style="'.$style.'"'; ?>
                                 <?php if ($title) echo 'title="'.htmlspecialchars($title).'"'; ?>></div>
                        <?php endfor; ?>
                    <?php endforeach; ?>
                </div>
                <div class="hm-legend">
                    <span>menos</span>
                    <div class="scale">
                        <span style="background: rgba(110,86,207,0.15)"></span>
                        <span style="background: rgba(110,86,207,0.35)"></span>
                        <span style="background: rgba(110,86,207,0.55)"></span>
                        <span style="background: rgba(110,86,207,0.75)"></span>
                        <span style="background: rgba(110,86,207,1)"></span>
                    </div>
                    <span>mais</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Acessos por hora -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3 class="card-title">Acessos por Hora — <?php echo $periodLabel; ?></h3>
        </div>
        <div class="card-content">
            <canvas id="chartHoras" height="80"></canvas>
        </div>
    </div>

    <!-- TABELAS -->
    <div class="tables-grid">
        <!-- Placas com taxa de sucesso -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ranking de Placas (com taxa de sucesso)</h3>
            </div>
            <div class="card-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Localização</th>
                            <th>Estado</th>
                            <th>Leituras</th>
                            <th style="min-width:160px">Distribuição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resPlacas->fetch_assoc()):
                            $tot = (int)$row['total'];
                            $ok  = (int)$row['permitidos'];
                            $ko  = (int)$row['negados'];
                            $un  = (int)$row['desconhecidos'];
                            $taxa = $tot > 0 ? round(($ok / $tot) * 100) : 0;
                            $pOk = $tot > 0 ? ($ok / $tot) * 100 : 0;
                            $pKo = $tot > 0 ? ($ko / $tot) * 100 : 0;
                            $pUn = $tot > 0 ? ($un / $tot) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['nome']); ?></td>
                            <td><?php echo htmlspecialchars($row['localizacao'] ?: '-'); ?></td>
                            <td><span class="badge <?php echo $row['estado']; ?>"><?php echo $row['estado']; ?></span></td>
                            <td><strong><?php echo $tot; ?></strong></td>
                            <td>
                                <div class="placa-rate">
                                    <div class="placa-rate-bar" title="<?php echo "$ok permitidos · $ko negados · $un desconhecidos"; ?>">
                                        <div class="ok" style="width: <?php echo $pOk; ?>%"></div>
                                        <div class="ko" style="width: <?php echo $pKo; ?>%"></div>
                                        <div class="un" style="width: <?php echo $pUn; ?>%"></div>
                                    </div>
                                    <div class="placa-rate-val"><?php echo $tot > 0 ? $taxa.'%' : '-'; ?></div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Utilizadores -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Utilizadores Mais Ativos</h3>
            </div>
            <div class="card-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Acessos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; while ($row = $resTopUsers->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $rank++; ?>º</strong></td>
                            <td><?php echo htmlspecialchars($row['nome']); ?></td>
                            <td style="color:var(--text-tertiary); font-size:12px"><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><strong><?php echo $row['total_acessos']; ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($rank === 1): ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text-tertiary); padding:20px">Sem acessos permitidos no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Utilizadores inativos -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3 class="card-title">Utilizadores Sem Acesso Recente</h3>
            <span class="card-action" style="cursor:default; opacity:.7">há mais de 30 dias ou nunca</span>
        </div>
        <div class="card-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>UID</th>
                        <th>Último acesso</th>
                        <th>Há quanto tempo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $temInativos = false; while ($row = $resInativos->fetch_assoc()): $temInativos = true; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nome']); ?></td>
                        <td style="color:var(--text-secondary)"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                        <td><code><?php echo htmlspecialchars($row['rfid_uid']); ?></code></td>
                        <td>
                            <?php if ($row['ultimo_acesso']): ?>
                                <?php echo date('d/m/Y', strtotime($row['ultimo_acesso'])); ?>
                            <?php else: ?>
                                <span class="ultimo-acesso-nunca">nunca</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['dias_sem_acesso']): ?>
                                <span class="ultimo-acesso-dias"><?php echo $row['dias_sem_acesso']; ?> dias</span>
                            <?php else: ?>
                                <span class="ultimo-acesso-nunca">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if (!$temInativos): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--text-tertiary); padding:20px">Todos os utilizadores acederam nos últimos 30 dias 🎉</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Últimas leituras -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Últimas 10 Leituras</h3>
            <span class="card-action" onclick="location.href='leituras_rfid.php'">Ver todas ↗</span>
        </div>
        <div class="card-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Utilizador</th>
                        <th>Cartão UID</th>
                        <th>Placa</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $resRecentes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['data_leitura'])); ?></td>
                        <td><?php echo $row['user_nome'] ? htmlspecialchars($row['user_nome']) : '<span style="color:var(--text-tertiary)">Desconhecido</span>'; ?></td>
                        <td><code><?php echo htmlspecialchars($row['cartao_uid']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['placa_nome']); ?></td>
                        <td><span class="badge <?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const diasLabels  = <?php echo json_encode($diasLabels); ?>;
const diasData    = <?php echo json_encode($diasData); ?>;
const horasLabels = <?php echo json_encode($horasLabels); ?>;
const horasData   = <?php echo json_encode($horasData); ?>;
const statusLabels = <?php echo json_encode($statusLabels); ?>;
const statusData   = <?php echo json_encode($statusData); ?>;

new Chart(document.getElementById('chartDias'), {
    type: 'line',
    data: {
        labels: diasLabels.length ? diasLabels : ['Sem dados'],
        datasets: [{
            label: 'Acessos',
            data: diasData.length ? diasData : [0],
            borderColor: '#6E56CF',
            backgroundColor: 'rgba(110,86,207,0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#6E56CF'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('chartHoras'), {
    type: 'bar',
    data: {
        labels: horasLabels,
        datasets: [{
            label: 'Acessos',
            data: horasData,
            backgroundColor: 'rgba(110,86,207,0.7)',
            borderRadius: 6
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: statusLabels.length ? statusLabels : ['Sem dados'],
        datasets: [{
            data: statusData.length ? statusData : [1],
            backgroundColor: ['#2E7D55', '#C0392B', '#B26A00'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// Auto-refresh: recarrega quando há nova leitura
(function autoRefresh() {
    const baseline = <?php echo time(); ?>;
    let lastTs = baseline;
    function check() {
        fetch('api_check_updates.php')
            .then(r => r.json())
            .then(d => {
                if (d.global > lastTs) {
                    lastTs = d.global;
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
