<?php
session_start();
require_once "ligacao.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$userName = ucwords(str_replace(['.', '_'], ' ', explode('@', $email)[0]));
$partes   = explode(' ', $userName);
$iniciais = strtoupper(substr($partes[0], 0, 1));
if (isset($partes[1])) $iniciais .= strtoupper(substr($partes[1], 0, 1));

// Estatísticas gerais
$stats = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users_autorizados)                                          AS total_users,
        (SELECT COUNT(*) FROM users_autorizados WHERE ativo = 1)                          AS users_ativos,
        (SELECT COUNT(*) FROM placas_arduino WHERE ativo = 1)                             AS total_placas,
        (SELECT COUNT(*) FROM placas_arduino WHERE estado = 'online' AND ativo = 1)       AS online_placas,
        (SELECT COUNT(*) FROM leituras_rfid WHERE DATE(data_leitura) = CURDATE())         AS acessos_hoje,
        (SELECT COUNT(*) FROM leituras_rfid WHERE status = 'permitido' AND DATE(data_leitura) = CURDATE()) AS permitidos_hoje,
        (SELECT COUNT(*) FROM leituras_rfid WHERE status = 'negado'
            AND data_leitura >= NOW() - INTERVAL 24 HOUR)                                 AS negados_24h,
        (SELECT COUNT(*) FROM cartoes_bloqueados)                                         AS cartoes_bloqueados
")->fetch_assoc();

// Últimas 5 leituras
$recentes = $conn->query("
    SELECT l.data_leitura, l.status, l.cartao_uid,
           u.nome  AS user_nome,
           p.nome  AS placa_nome
    FROM leituras_rfid l
    LEFT JOIN users_autorizados u ON l.user_id = u.id
    JOIN  placas_arduino p ON l.placa_id = p.id
    ORDER BY l.data_leitura DESC
    LIMIT 5
");

// Acessos últimos 7 dias (gráfico)
$resDias = $conn->query("
    SELECT DATE(data_leitura) AS dia, COUNT(*) AS total
    FROM leituras_rfid
    WHERE data_leitura >= NOW() - INTERVAL 7 DAY
    GROUP BY dia ORDER BY dia ASC
");
$diasLabels = $diasData = [];
while ($r = $resDias->fetch_assoc()) {
    $diasLabels[] = date('d/m', strtotime($r['dia']));
    $diasData[]   = (int)$r['total'];
}

// Distribuição permitido vs negado (donut)
$resDist = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM leituras_rfid
    WHERE data_leitura >= NOW() - INTERVAL 30 DAY
    GROUP BY status
");
$distLabels = $distData = $distColors = [];
$colorMap = ['permitido'=>'#2E7D55','negado'=>'#C0392B','desconhecido'=>'#B26A00'];
while ($r = $resDist->fetch_assoc()) {
    $distLabels[] = ucfirst($r['status']);
    $distData[]   = (int)$r['total'];
    $distColors[] = $colorMap[$r['status']] ?? '#8e8e93';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <script>
        (function(){
            const t = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
            const s = localStorage.getItem('textSize') || 'medium';
            document.documentElement.style.fontSize = {small:'90%',medium:'100%',large:'110%'}[s];
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — RFID</title> 
    <link rel="stylesheet" href="dashboard.css?v=<?php echo @filemtime("dashboard.css"); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .donut-wrap { display:flex; align-items:center; justify-content:center; gap:24px; flex-wrap:wrap; }
        .donut-legend { display:flex; flex-direction:column; gap:8px; }
        .donut-legend-item { display:flex; align-items:center; gap:8px; font-size:13px; }
        .donut-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- ═══════════════════════════ SIDEBAR ═══════════════════════════ -->
<?php require '_sidebar.php'; ?>

<!-- ═══════════════════════════ CONTEÚDO ═══════════════════════════ -->
<div class="content">

    <!-- HEADER -->
    <div class="page-header">
        <div class="page-title-section">
            <h1>Overview</h1>
            <p class="page-subtitle">Bem-vindo, <?php echo htmlspecialchars($userName); ?> — <?php echo date('d \d\e F \d\e Y'); ?></p>
        </div>
        <div class="header-actions">
            <button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            </button>
            <button class="btn btn-secondary" onclick="location.reload()">
                <svg class="btn-icon" viewBox="0 0 24 24"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Atualizar
            </button>
            <a href="estatisticas.php" class="btn btn-primary">
                <svg class="btn-icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                Estatísticas
            </a>
        </div>
    </div>

    <!-- CARTÕES DE STATS -->
    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='users.php'">
            <div class="stat-header">
                <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></div>
                <span class="stat-trend" id="dash-users-ativos"><?php echo $stats['users_ativos']; ?> ativos</span>
            </div>
            <div class="stat-value" id="dash-total-users"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Utilizadores RFID</div>
        </div>
        <div class="stat-card" onclick="location.href='placas.php'">
            <div class="stat-header">
                <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M4 9h5M15 9h5M4 15h5M15 15h5M9 4v5M15 4v5M9 15v5M15 15v5"/></svg></div>
                <span class="stat-trend" id="dash-online-placas"><?php echo $stats['online_placas']; ?>/<?php echo $stats['total_placas']; ?> online</span>
            </div>
            <div class="stat-value" id="dash-total-placas"><?php echo $stats['total_placas']; ?></div>
            <div class="stat-label">Placas Arduino</div>
        </div>
        <div class="stat-card" onclick="location.href='leituras_rfid.php'">
            <div class="stat-header">
                <div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></div>
                <span class="stat-trend" id="dash-permitidos"><?php echo $stats['permitidos_hoje']; ?> permitidos</span>
            </div>
            <div class="stat-value" id="dash-acessos-hoje"><?php echo $stats['acessos_hoje']; ?></div>
            <div class="stat-label">Acessos Hoje</div>
        </div>
        <div class="stat-card" onclick="location.href='leituras_rfid.php'">
            <div class="stat-header">
                <div class="stat-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></div>
                <span class="stat-trend down" id="dash-bloqueados"><?php echo $stats['cartoes_bloqueados']; ?> bloqueados</span>
            </div>
            <div class="stat-value" id="dash-negados-24h"><?php echo $stats['negados_24h']; ?></div>
            <div class="stat-label">Negados (24h)</div>
        </div>
    </div>

    <!-- GRÁFICO LINHA + ATIVIDADE RECENTE -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Acessos — Últimos 7 Dias</h3>
                <span class="card-action" onclick="location.href='estatisticas.php'">Ver mais ↗</span>
            </div>
            <div class="card-content">
                <canvas id="chartDias" height="110"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Atividade Recente</h3>
                <span class="card-action" onclick="location.href='leituras_rfid.php'">Ver tudo</span>
            </div>
            <div class="card-content">
                <div class="activity-list">
                    <?php while ($row = $recentes->fetch_assoc()):
                        $cor  = match($row['status']) { 'permitido'=>'rgba(46,125,85,0.15)', 'negado'=>'rgba(192,57,43,0.15)', default=>'rgba(178,106,0,0.15)' };
                        $stroke = match($row['status']) { 'permitido'=>'var(--success)', 'negado'=>'var(--danger)', default=>'var(--warning)' };
                        $svg  = match($row['status']) {
                            'permitido' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>',
                            'negado'    => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
                            default     => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                        };
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background:<?php echo $cor; ?>; display:flex;align-items:center;justify-content:center;">
                            <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:<?php echo $stroke; ?>;fill:none;stroke-width:2.5"><?php
                                echo match($row['status']) {
                                    'permitido' => '<path d="M20 6 9 17l-5-5"/>',
                                    'negado'    => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
                                    default     => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
                                };
                            ?></svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text"><?php echo htmlspecialchars($row['user_nome'] ?? 'Desconhecido'); ?> — <?php echo htmlspecialchars($row['placa_nome']); ?></div>
                            <div class="activity-time"><?php echo date('d/m/Y H:i', strtotime($row['data_leitura'])); ?> • <?php echo $row['status']; ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DONUT + AÇÕES RÁPIDAS + ESTADO SISTEMA -->
    <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr 1fr; gap:24px;">

        <!-- Donut -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Distribuição (30 dias)</h3>
            </div>
            <div class="card-content">
                <div class="donut-wrap">
                    <canvas id="chartDonut" width="140" height="140" style="max-width:140px;"></canvas>
                    <div class="donut-legend" id="donutLegend"></div>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ações Rápidas</h3>
            </div>
            <div class="card-content">
                <div class="quick-actions">
                    <a href="users.php" class="quick-action-btn">
                        <div class="quick-action-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
                        <span class="quick-action-text">Utilizadores</span>
                    </a>
                    <a href="placas.php" class="quick-action-btn">
                        <div class="quick-action-icon"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg></div>
                        <span class="quick-action-text">Placas</span>
                    </a>
                    <a href="leituras_rfid.php" class="quick-action-btn">
                        <div class="quick-action-icon"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg></div>
                        <span class="quick-action-text">Leituras</span>
                    </a>
                    <a href="settings.php" class="quick-action-btn">
                        <div class="quick-action-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
                        <span class="quick-action-text">Definições</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Estado do Sistema -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Estado do Sistema</h3>
                <span class="status-badge">Operacional</span>
            </div>
            <div class="card-content">
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon" style="background:rgba(46,125,85,0.15);">
                            <svg viewBox="0 0 24 24" style="stroke:var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Servidor XAMPP</div>
                            <div class="activity-time">Apache • Online</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon" style="background:rgba(46,125,85,0.15);">
                            <svg viewBox="0 0 24 24" style="stroke:var(--success);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Base de Dados</div>
                            <div class="activity-time">MariaDB • <?php echo $stats['acessos_hoje']; ?> leituras hoje</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon" style="background:rgba(178,106,0,0.15);">
                            <svg viewBox="0 0 24 24" style="stroke:var(--warning);"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Ollama IA</div>
                            <div class="activity-time">phi3.5 • Verificar manualmente</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /content -->

<!-- Chat movido para _sidebar.php - disponível em todas as páginas -->

<!-- ═══════════════════════ SCRIPTS ═══════════════════════════ -->
<script>
// Auto-refresh: stats + recarrega quando há novos dados
const _dashLoadTs = <?php echo time(); ?>;
let   _dashLastTs  = _dashLoadTs;

function refreshStats() {
    // Atualiza os números dos cards
    fetch('api_dashboard_stats.php')
        .then(r => r.json())
        .then(d => {
            const set = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = val; };
            set('dash-total-users',    d.total_users);
            set('dash-users-ativos',   d.users_ativos + ' ativos');
            set('dash-total-placas',   d.total_placas);
            set('dash-online-placas',  d.online_placas + '/' + d.total_placas + ' online');
            set('dash-acessos-hoje',   d.acessos_hoje);
            set('dash-permitidos',     d.permitidos_hoje + ' permitidos');
            set('dash-negados-24h',    d.negados_24h);
            set('dash-bloqueados',     d.cartoes_bloqueados + ' bloqueados');
        })
        .catch(() => {});
}
refreshStats();
setInterval(refreshStats, 60000);

// Verifica novos dados globais e recarrega a página se houver
(function checkUpdates() {
    fetch('api_check_updates.php')
        .then(r => r.json())
        .then(d => {
            if (d.global > _dashLastTs) {
                _dashLastTs = d.global;
                location.reload();
            }
        })
        .catch(() => {})
        .finally(() => setTimeout(checkUpdates, 3000));
})();

// Dados PHP → JS
const diasLabels = <?php echo json_encode($diasLabels ?: ['Sem dados']); ?>;
const diasData   = <?php echo json_encode($diasData   ?: [0]); ?>;
const distLabels = <?php echo json_encode($distLabels ?: ['Sem dados']); ?>;
const distData   = <?php echo json_encode($distData   ?: [0]); ?>;
const distColors = <?php echo json_encode($distColors ?: ['#8e8e93']); ?>;

// Gráfico de linha
new Chart(document.getElementById('chartDias'), {
    type: 'line',
    data: {
        labels: diasLabels,
        datasets: [{
            label: 'Acessos',
            data: diasData,
            borderColor: '#6E56CF',
            backgroundColor: 'rgba(110,86,207,0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#6E56CF',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Gráfico donut
new Chart(document.getElementById('chartDonut'), {
    type: 'doughnut',
    data: {
        labels: distLabels,
        datasets: [{ data: distData, backgroundColor: distColors, borderWidth: 0 }]
    },
    options: {
        responsive: false,
        plugins: { legend: { display: false } },
        cutout: '65%'
    }
});
// Legenda manual
const legend = document.getElementById('donutLegend');
distLabels.forEach((l, i) => {
    legend.innerHTML += `<div class="donut-legend-item">
        <div class="donut-dot" style="background:${distColors[i]}"></div>
        <span>${l}: <strong>${distData[i]}</strong></span>
    </div>`;
});

// Tema
function toggleTheme() {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
}

// Toast
function showToast(msg, type = 'info') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${msg}</span><button class="toast-close" onclick="this.parentElement.remove()">✕</button>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('hide'); setTimeout(() => t.remove(), 300); }, 3500);
}

// Chat está em _sidebar.php - disponível em todas as páginas
</script>
</body>
</html>