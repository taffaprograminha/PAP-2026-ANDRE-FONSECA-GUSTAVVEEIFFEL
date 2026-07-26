<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

require_once "ligacao.php";

// Buscar estatísticas
$stats = [
    'total' => 0,
    'online' => 0,
    'offline' => 0,
    'leituras_hoje' => 0
];

$result = $conn->query("SELECT COUNT(*) as total, 
    SUM(CASE WHEN estado = 'online' THEN 1 ELSE 0 END) as online,
    SUM(CASE WHEN estado = 'offline' THEN 1 ELSE 0 END) as offline 
    FROM placas_arduino WHERE ativo = 1");
if ($row = $result->fetch_assoc()) {
    $stats['total'] = $row['total'];
    $stats['online'] = $row['online'];
    $stats['offline'] = $row['offline'];
}

// Leituras hoje
$result = $conn->query("SELECT COUNT(*) as total FROM leituras_rfid 
    WHERE DATE(data_leitura) = CURDATE()");
if ($row = $result->fetch_assoc()) {
    $stats['leituras_hoje'] = $row['total'];
}

// Buscar todas as placas com última leitura
$placas = [];
$query = "SELECT p.*, 
    l.cartao_uid, l.data_leitura as ultima_leitura_real,
    u.nome as user_nome, u.email as user_email
    FROM placas_arduino p
    LEFT JOIN (
        SELECT placa_id, cartao_uid, data_leitura, user_id
        FROM leituras_rfid l1
        WHERE data_leitura = (
            SELECT MAX(data_leitura) 
            FROM leituras_rfid l2 
            WHERE l2.placa_id = l1.placa_id
        )
    ) l ON l.placa_id = p.id
    LEFT JOIN users_autorizados u ON l.user_id = u.id
    WHERE p.ativo = 1
    ORDER BY p.estado DESC, p.nome";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $placas[] = $row;
}

// Buscar utilizadores para dropdown
$users = [];
$result = $conn->query("SELECT id, nome, email FROM users_autorizados ORDER BY nome");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
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
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placas Arduino - Gestão</title>
    <style>
        :root {
            --bg-gradient: #FAFAF8;
            --bg-grain-opacity: 0;
            --card-bg: #FFFFFF;
            --card-bg-solid: #FFFFFF;
            --card-border: #E7E4DC;
            --card-inset: #F4F2EC;
            --text-primary: #18181B;
            --text-secondary: #5C5A54;
            --text-tertiary: #9A978D;
            --accent: #6E56CF;
            --accent-hover: #5A43B8;
            --accent-light: rgba(110, 86, 207, 0.10);
            --success: #2E7D55;
            --warning: #B26A00;
            --danger: #C0392B;
            --sidebar-bg: #FCFBF9;
            --sidebar-border: #E7E4DC;
            --hover-bg: rgba(110, 86, 207, 0.07);
            --separator: rgba(24, 24, 27, 0.07);
            --shadow-sm: 0 1px 2px rgba(24, 24, 27, 0.04);
            --shadow-md: 0 2px 6px rgba(24, 24, 27, 0.06);
            --shadow-lg: 0 10px 30px rgba(24, 24, 27, 0.10);
        }

        [data-theme="dark"] {
            --bg-gradient: #0E0E11;
            --bg-grain-opacity: 0;
            --card-bg: #16161A;
            --card-bg-solid: #16161A;
            --card-border: #27272F;
            --card-inset: #1D1D22;
            --text-primary: #ECECEE;
            --text-secondary: #9B9AA3;
            --text-tertiary: #6A6A73;
            --accent: #8B72E8;
            --accent-hover: #9D87F0;
            --accent-light: rgba(139, 114, 232, 0.15);
            --success: #4EC28A;
            --warning: #E0962E;
            --danger: #E5645A;
            --sidebar-bg: #131316;
            --sidebar-border: #27272F;
            --hover-bg: rgba(139, 114, 232, 0.12);
            --separator: rgba(255, 255, 255, 0.07);
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.25);
            --shadow-md: 0 2px 6px rgba(0, 0, 0, 0.35);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.50);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text-primary);
        }

        body {
            min-height: 100vh;
            background: var(--bg-gradient);
            background-image: 
                var(--bg-gradient),
                url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='var(--bg-grain-opacity)'/%3E%3C/svg%3E");
            display: flex;
            line-height: 1.47059;
            overflow-x: hidden;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(46,125,85, 0.4); }
            50% { transform: scale(1.02); box-shadow: 0 0 0 8px rgba(46,125,85, 0); }
        }

        @keyframes pulseOffline {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(192,57,43, 0.4); }
            50% { transform: scale(1.02); box-shadow: 0 0 0 8px rgba(192,57,43, 0); }
        }

        @keyframes toastIn {
            from { transform: translateX(100%) translateY(-50%); opacity: 0; }
            to { transform: translateX(0) translateY(-50%); opacity: 1; }
        }

        @keyframes toastOut {
            from { transform: translateX(0) translateY(-50%); opacity: 1; }
            to { transform: translateX(100%) translateY(-50%); opacity: 0; }
        }

        /* Toast Container */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .toast {
            background: rgba(30, 30, 30, 0.95);
            
            color: white;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            pointer-events: auto;
            animation: toastIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            max-width: 400px;
        }

        .toast.hide {
            animation: toastOut 0.3s ease forwards;
        }

        .toast-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .toast.success { border-left: 3px solid var(--success); }
        .toast.error { border-left: 3px solid var(--danger); }
        .toast.info { border-left: 3px solid var(--accent); }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            
           
            border-right: 1px solid var(--sidebar-border);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.08);
            height: 100vh;
            position: fixed;
            padding: 24px 16px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            animation: slideIn 0.5s ease;
        }

        .sidebar-header {
            padding: 0 12px 24px;
            border-bottom: 1px solid var(--separator);
            margin-bottom: 16px;
        }

        .sidebar h3 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .sidebar-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .nav-section {
            margin-bottom: 8px;
        }

        .nav-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-tertiary);
            padding: 8px 12px;
            font-weight: 600;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            padding: 10px 12px;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            color: var(--text-primary);
            position: relative;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--hover-bg);
            color: var(--accent);
        }

        .sidebar a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 16px;
            background: var(--accent);
            border-radius: 0 2px 2px 0;
        }

        .sidebar a svg {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            opacity: 0.85;
            transition: transform 0.3s ease;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .sidebar a:hover svg {
            transform: scale(1.1);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--separator);
        }

        .user-preview {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 10px;
            background: var(--card-bg);
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid var(--card-border);
        }

        .user-preview:hover {
            background: var(--hover-bg);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            color: white;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 11px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border-radius: 8px;
            color: var(--danger);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .logout-btn:hover {
            background: rgba(192,57,43, 0.1);
        }

        /* Content */
        .content {
            margin-left: 260px;
            flex: 1;
            padding: 32px 40px;
            max-width: 1400px;
            animation: slideIn 0.6s ease;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }

        .page-title-section h1 {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.8px;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 17px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            outline: none;
            gap: 6px;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 2px 8px rgba(110,86,207, 0.3);
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(110,86,207, 0.4);
        }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1px solid var(--card-border);
            
        }

        .btn-secondary:hover {
            background: var(--hover-bg);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-icon {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-sm), inset 0 1px 0 var(--card-inset);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md), inset 0 1px 0 var(--card-inset);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.blue { background: var(--accent-light); }
        .stat-icon.green { background: rgba(46,125,85, 0.15); }
        .stat-icon.orange { background: rgba(178,106,0, 0.15); }
        .stat-icon.red { background: rgba(192,57,43, 0.15); }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Placas Grid */
        .placas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .placa-card {
            background: var(--card-bg);
            
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-sm), inset 0 1px 0 var(--card-inset);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .placa-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md), inset 0 1px 0 var(--card-inset);
        }

        .placa-header {
            padding: 20px;
            border-bottom: 1px solid var(--separator);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .placa-title {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.3px;
        }

        .placa-status {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .placa-status.online {
            background: rgba(46,125,85, 0.15);
            color: var(--success);
        }

        .placa-status.offline {
            background: rgba(192,57,43, 0.15);
            color: var(--danger);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-dot.online {
            animation: pulse 2s infinite;
        }

        .status-dot.offline {
            animation: pulseOffline 2s infinite;
        }

        .placa-content {
            padding: 20px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--hover-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-icon svg {
            width: 16px;
            height: 16px;
            stroke: var(--accent);
            stroke-width: 2;
            fill: none;
        }

        .info-content {
            flex: 1;
            min-width: 0;
        }

        .info-label {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .placa-actions {
            display: flex;
            gap: 8px;
            padding: 16px 20px;
            border-top: 1px solid var(--separator);
            background: rgba(0, 0, 0, 0.02);
        }

        [data-theme="dark"] .placa-actions {
            background: rgba(255, 255, 255, 0.02);
        }

        .btn-small {
            padding: 8px 14px;
            font-size: 13px;
            border-radius: 8px;
            flex: 1;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal {
            background: var(--card-bg-solid);
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--separator);
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-content {
            padding: 24px;
            overflow-y: auto;
            max-height: 60vh;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            font-size: 15px;
            color: var(--text-primary);
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(110,86,207, 0.15);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%236e6e73' stroke-width='2' d='M1 4l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--separator);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Tabela de Leituras */
        .leituras-section {
            background: var(--card-bg);
            
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-sm), inset 0 1px 0 var(--card-inset);
            overflow: hidden;
        }

        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--separator);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: left;
            font-size: 14px;
        }

        th {
            font-weight: 600;
            color: var(--text-secondary);
            background: rgba(0, 0, 0, 0.02);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        [data-theme="dark"] th {
            background: rgba(255, 255, 255, 0.02);
        }

        tr {
            border-bottom: 1px solid var(--separator);
        }

        tr:last-child {
            border-bottom: none;
        }

        tr:hover {
            background: var(--hover-bg);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(46,125,85, 0.15);
            color: var(--success);
        }

        .badge-danger {
            background: rgba(192,57,43, 0.15);
            color: var(--danger);
        }

        .badge-warning {
            background: rgba(178,106,0, 0.15);
            color: var(--warning);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { 
                width: 100%; 
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--sidebar-border);
            }
            .content { 
                margin-left: 0; 
                padding: 20px;
            }
            .placas-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                gap: 16px;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background: var(--hover-bg);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-icon svg {
            width: 32px;
            height: 32px;
            stroke: var(--text-tertiary);
            stroke-width: 2;
            fill: none;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 14px;
            max-width: 300px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- Modal Confirmar Remoção -->
<div class="modal-overlay" id="modalDelete">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header" style="display:flex;align-items:center;gap:12px;">
            <div style="width:40px;height:40px;border-radius:12px;background:rgba(192,57,43,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </div>
            <div>
                <h3 class="modal-title" style="font-size:17px;">Remover Placa</h3>
                <p id="deleteSubtitle" style="font-size:13px;color:var(--text-secondary);margin-top:2px;"></p>
            </div>
        </div>
        <div class="modal-content">
            <p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px;line-height:1.5;">
                Esta ação é irreversível. Confirma a tua password de administrador para continuar.
            </p>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Password de Administrador</label>
                <div style="position:relative;">
                    <input type="password" class="form-input" id="deletePassword" placeholder="Insere a tua password"
                           style="padding-right:44px;" onkeydown="if(event.key==='Enter') confirmDelete()">
                    <button onclick="toggleDeletePwd()" tabindex="-1"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:var(--text-tertiary);">
                        <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <p id="deleteError" style="color:var(--danger);font-size:12px;margin-top:8px;display:none;"></p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
            <button class="btn btn-danger" id="btnConfirmDelete" onclick="confirmDelete()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                Remover Definitivamente
            </button>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar -->
<div class="modal-overlay" id="modalPlaca">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Nova Placa</h3>
        </div>
        <div class="modal-content">
            <form id="formPlaca">
                <input type="hidden" id="placaId" name="id">
                <div class="form-group">
                    <label class="form-label">Nome da Placa</label>
                    <input type="text" class="form-input" id="placaNome" name="nome" placeholder="Ex: Porta Principal" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Localização</label>
                    <input type="text" class="form-input" id="placaLocalizacao" name="localizacao" placeholder="Ex: Entrada do Edifício A">
                </div>
                <div class="form-group">
                    <label class="form-label">IP Address (opcional)</label>
                    <input type="text" class="form-input" id="placaIP" name="ip_address" placeholder="192.168.1.100">
                </div>
                <div class="form-group">
                    <label class="form-label">Estado Inicial</label>
                    <select class="form-input form-select" id="placaEstado" name="estado">
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" onclick="savePlaca()">Guardar</button>
        </div>
    </div>
</div>

<?php require '_sidebar.php'; ?>

<div class="content">
    <div class="page-header">
        <div class="page-title-section">
            <h1>Placas Arduino</h1>
            <p class="page-subtitle">Gestão de leitores RFID e controlo de acessos</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="refreshPlacas()">
                <svg class="btn-icon" viewBox="0 0 24 24"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Atualizar
            </button>
            <button class="btn btn-primary" onclick="openModal()">
                <svg class="btn-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nova Placa
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M4 9h5M15 9h5M4 15h5M15 15h5M9 4v5M15 4v5M9 15v5M15 15v5"/></svg></div>
            </div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total de Placas</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            </div>
            <div class="stat-value"><?php echo $stats['online']; ?></div>
            <div class="stat-label">Placas Online</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></div>
            </div>
            <div class="stat-value"><?php echo $stats['offline']; ?></div>
            <div class="stat-label">Placas Offline</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2"><path d="M1 6l5.5 5.5L12 6l5.5 5.5L23 6"/><path d="M1 13l5.5 5.5L12 13l5.5 5.5L23 13"/></svg></div>
            </div>
            <div class="stat-value"><?php echo $stats['leituras_hoje']; ?></div>
            <div class="stat-label">Leituras Hoje</div>
        </div>
    </div>

    <!-- Tabela de Placas -->
    <div class="leituras-section">
        <div class="section-header">
            <h3 class="section-title">Placas Registadas</h3>
            <button class="btn btn-primary" onclick="openModal()">
                <svg class="btn-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nova Placa
            </button>
        </div>
        <div class="table-container">
        <?php if (empty($placas)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg>
                </div>
                <div class="empty-title">Nenhuma placa configurada</div>
                <div class="empty-text">Adiciona a tua primeira placa Arduino para começar a monitorizar acessos.</div>
            </div>
        <?php else: ?>
            <table><thead><tr>
                <th>Estado</th><th>Nome</th><th>Localização</th><th>IP</th><th>Último Cartão</th><th>Última Leitura</th><th>Ações</th>
            </tr></thead><tbody>
            <?php foreach ($placas as $placa):
                $isOnline = $placa['estado'] === 'online';
                $ultimaLeitura = $placa['ultima_leitura_real'] ? date('d/m/Y H:i', strtotime($placa['ultima_leitura_real'])) : 'Nunca';
            ?>
            <tr data-id="<?php echo $placa['id']; ?>">
                <td>
                    <div class="placa-status <?php echo $isOnline ? 'online' : 'offline'; ?>">
                        <span class="status-dot <?php echo $isOnline ? 'online' : 'offline'; ?>"></span>
                        <?php echo $isOnline ? 'Online' : 'Offline'; ?>
                    </div>
                </td>
                <td style="font-weight:600;"><?php echo htmlspecialchars($placa['nome']); ?></td>
                <td style="color:var(--text-secondary);"><?php echo htmlspecialchars($placa['localizacao'] ?: '—'); ?></td>
                <td><code style="font-size:13px; background:var(--hover-bg); padding:3px 8px; border-radius:6px;"><?php echo htmlspecialchars($placa['ip_address'] ?: '—'); ?></code></td>
                <td>
                    <?php if ($placa['user_nome']): ?>
                        <span style="font-weight:500;"><?php echo htmlspecialchars($placa['user_nome']); ?></span>
                    <?php elseif ($placa['cartao_uid']): ?>
                        <span style="color:var(--warning); font-size:13px;">⚠ <?php echo htmlspecialchars($placa['cartao_uid']); ?></span>
                    <?php else: ?>
                        <span style="color:var(--text-tertiary);">—</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-secondary); white-space:nowrap;"><?php echo $ultimaLeitura; ?></td>
                <td>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-secondary btn-small" onclick="editPlaca(<?php echo $placa['id']; ?>)">Editar</button>
                        <button class="btn btn-danger btn-small" onclick="deletePlaca(<?php echo $placa['id']; ?>, <?php echo json_encode($placa['nome']); ?>)">Remover</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
        </div>
    </div>

    <!-- Últimas Leituras -->
    <div class="leituras-section" style="margin-top: 32px;">
        <div class="section-header">
            <h3 class="section-title">Últimas Leituras</h3>
            <button class="btn btn-secondary" onclick="viewAllLeituras()">
                Ver Todas
            </button>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Placa</th>
                        <th>Cartão UID</th>
                        <th>Utilizador</th>
                        <th>Data/Hora</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $leituras = $conn->query("
                        SELECT l.*, p.nome as placa_nome, u.nome as user_nome, u.email as user_email
                        FROM leituras_rfid l
                        JOIN placas_arduino p ON l.placa_id = p.id
                        LEFT JOIN users_autorizados u ON l.user_id = u.id
                        ORDER BY l.data_leitura DESC
                        LIMIT 10
                    ");
                    while ($l = $leituras->fetch_assoc()):
                        $badgeClass = $l['status'] === 'permitido' ? 'badge-success' : ($l['status'] === 'negado' ? 'badge-danger' : 'badge-warning');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($l['placa_nome']); ?></td>
                        <td><code><?php echo htmlspecialchars($l['cartao_uid']); ?></code></td>
                        <td><?php echo $l['user_nome'] ? htmlspecialchars($l['user_nome']) : '<span style="color: var(--text-tertiary);">Desconhecido</span>'; ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($l['data_leitura'])); ?></td>
                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($l['status']); ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Toast System
    function showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icons = {
            success: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="#2E7D55" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="#C0392B" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            info: '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="#8B72E8" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };
        
        toast.innerHTML = `
            ${icons[type]}
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // Modal Functions
    function openModal(id = null) {
        const modal = document.getElementById('modalPlaca');
        const title = document.getElementById('modalTitle');
        const form = document.getElementById('formPlaca');
        
        if (id) {
            title.textContent = 'Editar Placa';
            // Carregar dados via AJAX
            fetch(`api_placas.php?action=get&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('placaId').value = data.id;
                    document.getElementById('placaNome').value = data.nome;
                    document.getElementById('placaLocalizacao').value = data.localizacao || '';
                    document.getElementById('placaIP').value = data.ip_address || '';
                    document.getElementById('placaEstado').value = data.estado;
                });
        } else {
            title.textContent = 'Nova Placa';
            form.reset();
            document.getElementById('placaId').value = '';
        }
        
        modal.classList.add('active');
    }

    function closeModal() {
        document.getElementById('modalPlaca').classList.remove('active');
    }

    function savePlaca() {
        const form = document.getElementById('formPlaca');
        const formData = new FormData(form);
        const id = document.getElementById('placaId').value;
        
        formData.append('action', id ? 'update' : 'create');
        
        fetch('api_placas.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (!id && data.api_key) {
                    showToast(`Placa criada! API Key: ${data.api_key}`, 'success', 8000);
                } else {
                    showToast(id ? 'Placa atualizada!' : 'Placa criada!', 'success');
                }
                closeModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Erro: ' + (data.error || 'Erro desconhecido'), 'error');
            }
        })
        .catch(() => showToast('Erro de ligação ao servidor', 'error'));
    }

    function editPlaca(id) {
        openModal(id);
    }

    // Remoção com confirmação de password
    let _deleteTargetId   = null;
    let _deleteTargetNome = '';

    function deletePlaca(id, nome) {
        _deleteTargetId   = id;
        _deleteTargetNome = nome || 'esta placa';
        document.getElementById('deleteSubtitle').textContent = _deleteTargetNome;
        document.getElementById('deletePassword').value = '';
        document.getElementById('deleteError').style.display = 'none';
        document.getElementById('deleteError').textContent   = '';
        document.getElementById('modalDelete').classList.add('active');
        setTimeout(() => document.getElementById('deletePassword').focus(), 300);
    }

    function closeDeleteModal() {
        document.getElementById('modalDelete').classList.remove('active');
        _deleteTargetId = null;
    }

    function toggleDeletePwd() {
        const inp = document.getElementById('deletePassword');
        inp.type = inp.type === 'password' ? 'text' : 'password';
    }

    function confirmDelete() {
        const pwd = document.getElementById('deletePassword').value.trim();
        const errEl = document.getElementById('deleteError');
        const btn   = document.getElementById('btnConfirmDelete');

        if (!pwd) {
            errEl.textContent = 'Insere a tua password.';
            errEl.style.display = 'block';
            document.getElementById('deletePassword').focus();
            return;
        }

        btn.disabled = true;
        btn.textContent = 'A verificar…';
        errEl.style.display = 'none';

        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', _deleteTargetId);
        fd.append('password', pwd);

        fetch('api_placas.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeDeleteModal();
                    showToast('Placa removida com sucesso.', 'success');
                    const row = document.querySelector(`tr[data-id="${_deleteTargetId}"]`);
                    if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>row.remove(), 300); }
                } else {
                    errEl.textContent = data.error || 'Erro ao remover.';
                    errEl.style.display = 'block';
                    document.getElementById('deletePassword').focus();
                }
            })
            .catch(() => { errEl.textContent = 'Erro de ligação.'; errEl.style.display = 'block'; })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>Remover Definitivamente';
            });
    }

    document.getElementById('modalDelete').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeDeleteModal();
    });

    function refreshPlacas() {
        showToast('A atualizar...', 'info');
        location.reload();
    }

    function viewAllLeituras() {
        window.location.href = 'leituras_rfid.php';
    }

    // Fechar modal ao clicar fora
    document.getElementById('modalPlaca').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeModal();
    });

    // Auto-refresh: recarrega quando o estado das placas muda
    (function autoRefresh() {
        const baseline = <?php echo time(); ?>;
        let lastTs = baseline;
        function check() {
            fetch('api_check_updates.php')
                .then(r => r.json())
                .then(d => {
                    if (d.placas > lastTs || d.leituras > lastTs) {
                        // Só recarrega se não houver modal aberto
                        const aberto = document.querySelector('.modal-overlay.active') !== null;
                        if (!aberto) {
                            location.reload();
                        } else {
                            lastTs = Math.max(d.placas, d.leituras);
                        }
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