<?php
session_start();

require_once "ligacao.php"; // Garante que este ficheiro existe e define $conn

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];

// 1. Procurar o utilizador (usando SELECT * para evitar erros de colunas inexistentes por agora)
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");

if (!$stmt) {
    die("Erro na preparação da consulta: " . $conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { 
    header("Location: login.php"); 
    exit(); 
}

// 2. Atribuir variáveis protegendo contra colunas que podem não existir na tabela 'users'
// Se a coluna não existir no array $user, define um valor padrão para não dar erro
$twoFA  = isset($user['two_factor_enabled']) ? $user['two_factor_enabled'] : 0;
$apiKey = isset($user['api_key']) ? $user['api_key'] : null;
$nomeBD = isset($user['nome']) ? $user['nome'] : '';

$nome = !empty($nomeBD) ? $nomeBD : ucwords(str_replace(['.','_'], ' ', explode('@', $email)[0]));

$partes   = explode(' ', $nome);
$iniciais = strtoupper(substr($partes[0], 0, 1));
if (count($partes) > 1) $iniciais .= strtoupper(substr(end($partes), 0, 1));

// Preferências persistidas na BD
$prefs = [
    'dark_mode'            => (int)($user['dark_mode']            ?? 0),
    'email_notifications'  => (int)($user['email_notifications']  ?? 1),
    'digest_notifications' => (int)($user['digest_notifications'] ?? 0),
    'timezone'             => $user['timezone']    ?? 'Europe/Lisbon',
    'date_format'          => $user['date_format'] ?? 'pt',
];
?>



<!DOCTYPE html>
<html lang="pt">
<head>
    <script>
        // Tema: localStorage tem prioridade (alterado noutras páginas); BD é fallback
        (function() {
            const lsTheme  = localStorage.getItem('theme');
            const dbTheme  = '<?php echo $prefs['dark_mode'] ? 'dark' : 'light'; ?>';
            const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = lsTheme || dbTheme || preferred;
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definições</title>
    <style>
        :root {
            --bg-gradient: #FAFAF8;
            --bg-grain-opacity: 0;
            --card-bg: #FFFFFF;
            --card-border: #E7E4DC;
            --card-inset: #F4F2EC;
            --text-primary: #18181B;
            --text-secondary: #5C5A54;
            --text-tertiary: #9A978D;
            --accent: #6E56CF;
            --accent-hover: #5A43B8;
            --toggle-off: #D8D5CC;
            --toggle-on: #2E7D55;
            --sidebar-bg: #FCFBF9;
            --sidebar-border: #E7E4DC;
            --hover-bg: rgba(110, 86, 207, 0.07);
            --danger: #C0392B;
            --danger-hover: #A93226;
            --success: #2E7D55;
            --warning: #B26A00;
            --separator: rgba(24, 24, 27, 0.07);
            --input-bg: #F4F2EC;
            --shadow-sm: 0 1px 2px rgba(24, 24, 27, 0.04);
            --shadow-md: 0 2px 6px rgba(24, 24, 27, 0.06);
            --shadow-lg: 0 10px 30px rgba(24, 24, 27, 0.10);
        }

        [data-theme="dark"] {
            --bg-gradient: #0E0E11;
            --bg-grain-opacity: 0;
            --card-bg: #16161A;
            --card-border: #27272F;
            --card-inset: #1D1D22;
            --text-primary: #ECECEE;
            --text-secondary: #9B9AA3;
            --text-tertiary: #6A6A73;
            --accent: #8B72E8;
            --accent-hover: #9D87F0;
            --toggle-off: #33333E;
            --toggle-on: #4EC28A;
            --sidebar-bg: #131316;
            --sidebar-border: #27272F;
            --hover-bg: rgba(139, 114, 232, 0.12);
            --danger: #E5645A;
            --danger-hover: #EC7B72;
            --success: #4EC28A;
            --warning: #E0962E;
            --separator: rgba(255, 255, 255, 0.07);
            --input-bg: #1D1D22;
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
            transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease;
        }

        body {
            min-height: 100vh;
            background: var(--bg-gradient);
            background-image: 
                var(--bg-gradient),
                url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='var(--bg-grain-opacity)'/%3E%3C/svg%3E");
            display: flex;
            line-height: 1.47059;
        }

        /* Animações suaves */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        @keyframes toastIn {
            from { transform: translateX(100%) translateY(-50%); opacity: 0; }
            to { transform: translateX(0) translateY(-50%); opacity: 1; }
        }

        @keyframes toastOut {
            from { transform: translateX(0) translateY(-50%); opacity: 1; }
            to { transform: translateX(100%) translateY(-50%); opacity: 0; }
        }

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
            background: var(--input-bg);
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-preview:hover {
            background: var(--hover-bg);
            transform: translateY(-1px);
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

        .user-email, .user-role {
            font-size: 11px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            padding: 10px; border-radius: 8px; color: var(--danger); text-decoration: none;
            font-size: 14px; font-weight: 600; transition: all 0.2s ease; margin-top: 8px;
        }
        .logout-btn:hover { background: rgba(192,57,43,.1); }
        .logout-btn svg { width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:2; }

        .content {
            margin-left: 260px;
            flex: 1;
            padding: 40px 48px;
            max-width: 800px;
            animation: slideIn 0.6s ease;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--accent);
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 16px;
            transition: all 0.25s ease;
            opacity: 0.9;
        }

        .back-link:hover {
            opacity: 1;
            transform: translateX(-3px);
        }

        .back-link svg {
            width: 16px;
            height: 16px;
            margin-right: 6px;
            stroke: currentColor;
            stroke-width: 2.5;
            fill: none;
        }

        .page-title {
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

        .settings-card {
            background: var(--card-bg);
            
          
            border-radius: 20px;
            padding: 0;
            box-shadow: var(--shadow-lg), inset 0 1px 0 var(--card-inset);
            border: 1px solid var(--card-border);
            margin-bottom: 24px;
            overflow: hidden;
            animation: slideIn 0.5s ease;
        }

        .card-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid var(--separator);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-icon svg {
            width: 16px;
            height: 16px;
            stroke: white;
            stroke-width: 2;
            fill: none;
        }

        .card-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }

        .card-content {
            padding: 8px 0;
        }

        .setting-group {
            padding: 8px 0;
        }

        .setting-group:not(:last-child) {
            border-bottom: 1px solid var(--separator);
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 24px;
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .setting-item:hover {
            background: rgba(110,86,207, 0.04);
        }

        [data-theme="dark"] .setting-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .setting-info {
            flex: 1;
            min-width: 0;
            margin-right: 16px;
        }

        .setting-label {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .setting-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            background: var(--accent);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .setting-badge.beta {
            background: var(--warning);
        }

        .setting-badge.new {
            background: var(--success);
        }

        .setting-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.4;
        }

        .setting-meta {
            font-size: 13px;
            color: var(--text-tertiary);
            margin-top: 4px;
        }

        .setting-control {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Toggle Switch iOS Style */
        .toggle {
            width: 50px;
            height: 30px;
            background: var(--toggle-off);
            border-radius: 999px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            -webkit-tap-highlight-color: transparent;
        }

        .toggle::after {
            content: '';
            position: absolute;
            width: 26px;
            height: 26px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15), 0 1px 2px rgba(0,0,0,0.1);
            transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94), width 0.2s ease;
        }

        .toggle:active::after {
            width: 30px;
        }

        .toggle.active {
            background: var(--toggle-on);
        }

        .toggle.active::after {
            transform: translateX(20px);
        }

        .toggle.active:active::after {
            transform: translateX(16px);
        }

        /* Select/Dropdown estilo iOS */
        .select-wrapper {
            position: relative;
        }

        .select-wrapper select {
            appearance: none;
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 8px 32px 8px 12px;
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            min-width: 140px;
            outline: none;
            transition: all 0.2s ease;
        }

        .select-wrapper select:hover {
            background: var(--hover-bg);
        }

        .select-wrapper select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(110,86,207, 0.15);
        }

        .select-wrapper::after {
            content: '';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid var(--text-secondary);
            pointer-events: none;
        }

        /* Botões estilo Apple */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            outline: none;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--input-bg);
            color: var(--text-primary);
            border: 1px solid var(--card-border);
        }

        .btn-secondary:hover {
            background: var(--hover-bg);
        }

        .btn-danger {
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }

        .btn-text {
            background: transparent;
            color: var(--accent);
            padding: 8px 12px;
        }

        .btn-text:hover {
            background: var(--hover-bg);
        }

        /* Seção de Perfil */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 24px;
        }

        .profile-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
            color: white;
            position: relative;
            flex-shrink: 0;
            box-shadow: var(--shadow-md);
        }

        .profile-avatar-large::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 50%;
            border: 3px solid var(--card-bg);
            box-shadow: 0 0 0 1px var(--card-border);
        }

        .avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 28px;
            height: 28px;
            background: var(--card-bg);
            border: 2px solid var(--card-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .avatar-edit:hover {
            transform: scale(1.1);
            background: var(--accent);
            border-color: var(--accent);
        }

        .avatar-edit svg {
            width: 14px;
            height: 14px;
            stroke: var(--text-primary);
            stroke-width: 2;
        }

        .avatar-edit:hover svg {
            stroke: white;
        }

        .profile-info h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .profile-info p {
            font-size: 15px;
            color: var(--text-secondary);
            margin: 0;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }

        /* Input fields */
        .input-field {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--input-bg);
            font-size: 15px;
            color: var(--text-primary);
            outline: none;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .input-field:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(110,86,207, 0.15);
        }

        .input-field::placeholder {
            color: var(--text-tertiary);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Segmented Control estilo iOS */
        .segmented-control {
            display: inline-flex;
            background: var(--input-bg);
            border-radius: 8px;
            padding: 2px;
            border: 1px solid var(--card-border);
        }

        .segmented-control button {
            padding: 6px 16px;
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
            position: relative;
        }

        .segmented-control button.active {
            background: var(--card-bg);
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
        }

        .segmented-control button:not(.active):hover {
            color: var(--text-primary);
        }

        /* Secção de Perigo */
        .danger-zone {
            border: 1px solid var(--danger);
            background: rgba(192,57,43, 0.04);
        }

        .danger-zone .card-header {
            border-bottom-color: rgba(192,57,43, 0.2);
        }

        .danger-zone .card-title {
            color: var(--danger);
        }

        .danger-zone .card-icon {
            background: var(--danger);
        }

        /* Toast Notification */
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

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            color: rgba(255,255,255,0.6);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .toast-close:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }

        /* Slider estilo iOS */
        .slider-container {
            width: 100%;
            max-width: 200px;
        }

        .slider {
            
            width: 100%;
            height: 4px;
            border-radius: 2px;
            background: var(--toggle-off);
            outline: none;
            transition: background 0.2s;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: transform 0.1s ease;
        }

        .slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }

        .slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            cursor: pointer;
            border: none;
        }

        /* Status indicators */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            display: inline-block;
            margin-right: 6px;
            box-shadow: 0 0 0 2px rgba(46,125,85, 0.3);
            animation: pulse 2s infinite;
        }

        .status-text {
            font-size: 13px;
            color: var(--success);
            font-weight: 600;
        }

        /* Links de navegação */
        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px;
            text-decoration: none;
            color: var(--text-primary);
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .nav-link:hover {
            background: rgba(110,86,207, 0.04);
        }

        .nav-link-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: var(--input-bg);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-link-icon svg {
            width: 14px;
            height: 14px;
            stroke: var(--accent);
            stroke-width: 2;
            fill: none;
        }

        .nav-link-text {
            font-size: 16px;
            font-weight: 500;
        }

        .nav-link-arrow {
            width: 16px;
            height: 16px;
            stroke: var(--text-tertiary);
            stroke-width: 2;
            fill: none;
            transition: transform 0.2s ease;
        }

        .nav-link:hover .nav-link-arrow {
            transform: translateX(3px);
            stroke: var(--accent);
        }

        .nav-link-value {
            font-size: 14px;
            color: var(--text-secondary);
            margin-right: 8px;
        }

        /* Versão e Footer */
        .version-info {
            padding: 24px;
            text-align: center;
            color: var(--text-tertiary);
            font-size: 12px;
        }

        .version-info p {
            margin: 4px 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { 
                width: 100%; 
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--sidebar-border);
                padding: 16px;
            }
            .content { 
                margin-left: 0; 
                padding: 24px 20px;
            }
            .sidebar h3, .sidebar-subtitle { display: block; }
            .nav-section-title { display: none; }
            .sidebar a span { display: inline; }
            .sidebar-footer { display: none; }
            .profile-header { flex-direction: column; text-align: center; }
            .setting-item { flex-direction: column; align-items: flex-start; gap: 12px; }
            .setting-control { width: 100%; justify-content: flex-end; }
        }

        /* Loading spinner */
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn.loading .spinner {
            display: inline-block;
            margin-right: 8px;
        }

        .btn.loading .btn-text {
            opacity: 0.7;
        }

        /* Checkbox estilo Apple */
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--toggle-off);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            background: transparent;
        }

        .checkbox.checked {
            background: var(--accent);
            border-color: var(--accent);
        }

        .checkbox svg {
            width: 12px;
            height: 12px;
            stroke: white;
            stroke-width: 3;
            fill: none;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.2s ease;
        }

        .checkbox.checked svg {
            opacity: 1;
            transform: scale(1);
        }
    </style>
</head>
<body data-theme="light">

<div class="toast-container" id="toastContainer"></div>

<?php require '_sidebar.php'; ?>

<div class="content">
    <div class="page-header">
        <a href="dashboard.php" class="back-link">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Voltar ao Dashboard
        </a>
        <h1 class="page-title">Definições</h1>
        <p class="page-subtitle">Personaliza a tua experiência e gere as preferências da conta</p>
    </div>

    <!-- Perfil do Utilizador -->
   
<div class="settings-card">
    <div class="profile-header">
        <div class="profile-avatar-large">
            <?php echo $iniciais; ?>
            <div class="avatar-edit" onclick="showToast('Alterar foto de perfil', 'info')">
                <svg viewBox="0 0 24 24">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
            </div>
        </div>

        <div class="profile-info">
            <h3><?php echo htmlspecialchars($nome); ?></h3>
            <p>Administrador do Sistema</p>

            <div class="profile-actions">
                <button class="btn btn-primary" onclick="openEditModal()">Editar Perfil</button>
                <button class="btn btn-secondary" onclick="openPasswordModal()">Alterar Password</button>
            </div>
        </div>
    </div>
    
    <div class="card-content">
        <div class="setting-group">
            
            <div class="form-group" style="padding: 0 24px 16px;">
                <label class="form-label">Nome Completo</label>
                <input 
                    type="text" 
                    class="input-field" 
                    value="<?php echo htmlspecialchars($nome); ?>" 
                    placeholder="O teu nome">
            </div>

            <div class="form-group" style="padding: 0 24px 16px;">
                <label class="form-label">Email</label>
                <input 
                    type="email" 
                    class="input-field" 
                    value="<?php echo htmlspecialchars($email); ?>" 
                    placeholder="email@exemplo.pt">
            </div>

        </div>

    </div>
</div>
    <!-- Aparência -->
    <div class="settings-card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                </div>
                Aparência
            </div>
            <p class="card-description">Personaliza o aspeto visual da aplicação</p>
        </div>
        <div class="card-content">
            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Modo Escuro</div>
                        <div class="setting-description">Alternar entre tema claro e escuro</div>
                    </div>
                    <div class="setting-control">
                        <div class="toggle" id="darkModeToggle"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notificações -->
    <div class="settings-card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon" style="background: var(--warning);">
                    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                </div>
                Notificações
            </div>
            <p class="card-description">Controla como e quando recebes alertas</p>
        </div>
        <div class="card-content">
            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Notificações por Email</div>
                        <div class="setting-description">Receber alertas importantes no teu email</div>
                    </div>
                    <div class="setting-control">
                        <div class="toggle <?php echo $prefs['email_notifications'] ? 'active' : ''; ?>" id="emailNotificationsToggle"></div>
                    </div>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Resumo Diário</div>
                        <div class="setting-description">Relatório consolidado uma vez por dia</div>
                    </div>
                    <div class="setting-control">
                        <div class="toggle <?php echo $prefs['digest_notifications'] ? 'active' : ''; ?>" id="digestToggle"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacidade e Segurança -->
    <div class="settings-card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon" style="background: var(--success);">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                </div>
                Privacidade e Segurança
            </div>
            <p class="card-description">Protege a tua conta e dados pessoais</p>
        </div>
        <div class="card-content">
            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Autenticação de Dois Fatores <span class="setting-badge new">Novo</span></div>
                        <div class="setting-description">Adiciona uma camada extra de segurança à tua conta</div>
                        <div class="setting-meta"><span class="status-dot"></span><span class="status-text">Ativo</span> · SMS + App Autenticador</div>
                    </div>
                    <div class="setting-control">
                        <button class="btn btn-secondary" id="btn2FA" onclick="toggle2FA(this)">
                            <?php echo $twoFA ? "Desativar" : "Ativar"; ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Histórico de Atividade</div>
                        <div class="setting-description">Registo de acessos e alterações na conta</div>
                    </div>
                    <div class="setting-control">
                        <a href="leituras_rfid.php" class="btn btn-text">Ver Leituras</a>
                    </div>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Chaves de Acesso API</div>
                        <div class="setting-description">Gerir tokens para integrações externas</div>
                    </div>
                    <div class="setting-control">
                        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                            <?php if($apiKey): ?>
                            <div style="display:flex;align-items:center;gap:8px;background:var(--input-bg);border:1px solid var(--card-border);border-radius:8px;padding:8px 12px;max-width:300px;">
                                <code id="apiKeyDisplay" style="font-size:11px;color:var(--text-secondary);word-break:break-all;flex:1;"><?php echo htmlspecialchars(substr($apiKey,0,16).'...'); ?></code>
                                <button class="btn btn-text" style="padding:2px 8px;font-size:12px;" onclick="copyApiKey('<?php echo htmlspecialchars($apiKey); ?>')">Copiar</button>
                            </div>
                            <?php endif; ?>
                            <div style="display:flex;gap:8px;">
                                <button class="btn btn-secondary" onclick="generateAPI()">
                                    <?php echo $apiKey ? 'Regenerar' : 'Gerar API Key'; ?>
                                </button>
                                <?php if($apiKey): ?>
                                <button class="btn btn-danger" onclick="revokeAPI()">Revogar</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>







    <!-- Região -->
    <div class="settings-card">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon" style="background: #6E56CF;">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                Região
            </div>
            <p class="card-description">Fuso horário e formato de datas (usado em todo o sistema e no assistente IA)</p>
        </div>
        <div class="card-content">
            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Fuso Horário</div>
                        <div class="setting-description">Afeta datas em leituras RFID e respostas do assistente</div>
                    </div>
                    <div class="setting-control">
                        <div class="select-wrapper">
                            <select id="timezoneSelect" onchange="saveTimezone(this.value)">
                                <?php
                                $timezones = [
                                    'Europe/Lisbon'     => 'Lisboa (Portugal)',
                                    'Atlantic/Azores'   => 'Açores',
                                    'Atlantic/Madeira'  => 'Madeira',
                                    'Europe/London'     => 'Londres (GMT)',
                                    'Europe/Madrid'     => 'Madrid',
                                    'Europe/Paris'      => 'Paris',
                                    'Europe/Berlin'     => 'Berlim',
                                    'America/Sao_Paulo' => 'São Paulo',
                                    'America/New_York'  => 'Nova Iorque',
                                    'UTC'               => 'UTC',
                                ];
                                foreach ($timezones as $tz => $label) {
                                    $sel = ($prefs['timezone'] === $tz) ? 'selected' : '';
                                    echo "<option value=\"$tz\" $sel>" . htmlspecialchars($label) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label">Formato de Data</div>
                        <div class="setting-description">Como as datas são apresentadas no sistema</div>
                    </div>
                    <div class="setting-control">
                        <div class="segmented-control">
                            <button class="<?php echo $prefs['date_format'] === 'pt' ? 'active' : ''; ?>" onclick="saveDateFormat('pt', this)">DD/MM/AAAA</button>
                            <button class="<?php echo $prefs['date_format'] === 'us' ? 'active' : ''; ?>" onclick="saveDateFormat('us', this)">MM/DD/AAAA</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Avançado -->
    
    <!-- Zona de Perigo -->
    <div class="settings-card danger-zone">
        <div class="card-header">
            <div class="card-title">
                <div class="card-icon">
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                Zona de Perigo
            </div>
            <p class="card-description">Ações irreversíveis para a tua conta</p>
        </div>
        <div class="card-content">
            <div class="setting-group">
                <div class="setting-item">
                    <div class="setting-info">
                        <div class="setting-label" style="color: var(--danger);">Eliminar Conta</div>
                        <div class="setting-description">Remove permanentemente todos os dados da conta. Esta ação não pode ser desfeita.</div>
                    </div>
                    <div class="setting-control">
                        <button class="btn btn-danger" onclick="confirmDelete()">Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Versão -->
    <div class="version-info">
        <p><strong>Versão 2.4.1</strong> (Build 2024.02.24)</p>
        <p>Última atualização: 24 de fevereiro de 2025</p>
        <p style="margin-top: 8px;">
            <a href="#" style="color: var(--accent); text-decoration: none;" onclick="showToast('A verificar atualizações...', 'info')">Verificar Atualizações</a> · 
            <a href="#" style="color: var(--text-secondary); text-decoration: none;">Termos de Serviço</a> · 
            <a href="#" style="color: var(--text-secondary); text-decoration: none;">Política de Privacidade</a>
        </p>
    </div>
</div>


<!-- Modais -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--card-bg);border-radius:20px;border:1px solid var(--card-border);box-shadow:var(--shadow-lg);width:100%;max-width:460px;overflow:hidden;">
        <div style="padding:24px;border-bottom:1px solid var(--separator);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:18px;font-weight:600;">Editar Perfil</h3>
            <button onclick="closeModal('editModal')" style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text-secondary);">✕</button>
        </div>
        <div style="padding:24px;display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Nome Completo</label>
                <input type="text" id="editNome" class="input-field" value="<?php echo htmlspecialchars($nome); ?>">
            </div>
            <div>
                <label class="form-label">Email</label>
                <input type="email" id="editEmail" class="input-field" value="<?php echo htmlspecialchars($email); ?>">
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--separator);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-secondary" onclick="closeModal('editModal')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveProfile()">Guardar</button>
        </div>
    </div>
</div>

<div id="passwordModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--card-bg);border-radius:20px;border:1px solid var(--card-border);box-shadow:var(--shadow-lg);width:100%;max-width:460px;overflow:hidden;">
        <div style="padding:24px;border-bottom:1px solid var(--separator);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:18px;font-weight:600;">Alterar Password</h3>
            <button onclick="closeModal('passwordModal')" style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--text-secondary);">✕</button>
        </div>
        <div style="padding:24px;display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Password Atual</label>
                <input type="password" id="pwAtual" class="input-field" placeholder="••••••••">
            </div>
            <div>
                <label class="form-label">Nova Password</label>
                <input type="password" id="pwNova" class="input-field" placeholder="••••••••">
            </div>
            <div>
                <label class="form-label">Confirmar Nova Password</label>
                <input type="password" id="pwConf" class="input-field" placeholder="••••••••">
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--separator);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-secondary" onclick="closeModal('passwordModal')">Cancelar</button>
            <button class="btn btn-primary" onclick="savePassword()">Alterar</button>
        </div>
    </div>
</div>

<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--card-bg);border-radius:20px;border:1px solid var(--card-border);box-shadow:var(--shadow-lg);width:100%;max-width:420px;overflow:hidden;">
        <div style="padding:24px;border-bottom:1px solid rgba(192,57,43,.3);">
            <h3 style="font-size:18px;font-weight:600;color:var(--danger);">⚠ Eliminar Conta</h3>
            <p style="font-size:14px;color:var(--text-secondary);margin-top:6px;">Esta ação é irreversível. Todos os dados serão apagados permanentemente.</p>
        </div>
        <div style="padding:24px;">
            <label class="form-label">Confirma a tua password para continuar</label>
            <input type="password" id="deletePassword" class="input-field" placeholder="A tua password">
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--separator);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancelar</button>
            <button class="btn btn-danger" onclick="deleteAccount()">Eliminar Conta</button>
        </div>
    </div>
</div>

<script>
// Preferências carregadas da BD
const PREFS = <?php echo json_encode($prefs); ?>;

// API helper
async function api(action, data = {}) {
    const form = new FormData();
    form.append('action', action);
    Object.entries(data).forEach(([k,v]) => form.append(k, v));
    const res = await fetch('settings_api.php', { method: 'POST', body: form });
    return res.json();
}

// Modais
function openEditModal() {
    document.getElementById('editModal').style.display = 'flex';
}
function openPasswordModal() {
    document.getElementById('passwordModal').style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Fechar modal ao clicar fora
['editModal','passwordModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => {
        if (e.target.id === id) closeModal(id);
    });
});

// Perfil
async function saveProfile() {
    const nome  = document.getElementById('editNome').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    if (!nome || !email) { showToast('Preenche todos os campos', 'error'); return; }

    const r = await api('update_profile', { nome, email });
    if (r.success) {
        showToast(r.message, 'success');
        closeModal('editModal');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(r.error, 'error');
    }
}

// Password
async function savePassword() {
    const atual = document.getElementById('pwAtual').value;
    const nova  = document.getElementById('pwNova').value;
    const conf  = document.getElementById('pwConf').value;

    if (!atual || !nova || !conf) { showToast('Preenche todos os campos', 'error'); return; }

    const r = await api('change_password', {
        password_atual: atual, password_nova: nova, password_conf: conf
    });
    if (r.success) {
        showToast(r.message, 'success');
        closeModal('passwordModal');
        document.getElementById('pwAtual').value = '';
        document.getElementById('pwNova').value  = '';
        document.getElementById('pwConf').value  = '';
    } else {
        showToast(r.error, 'error');
    }
}

// 2FA
async function toggle2FA(btn) {
    btn.disabled = true;
    btn.textContent = '...';

    const r = await api('toggle_2fa');
    if (r.success) {
        showToast(r.message, 'success');
        btn.textContent = r.enabled ? 'Desativar' : 'Ativar';
    } else {
        showToast('Erro ao alterar 2FA', 'error');
        btn.textContent = btn.textContent === '...' ? 'Ativar' : btn.textContent;
    }
    btn.disabled = false;
}

// API Key
async function generateAPI() {
    if (!confirm('Gerar nova API Key? A chave atual ficará inválida.')) return;

    const r = await api('generate_api');
    if (r.success) {
        showToast(r.message, 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast('Erro ao gerar API Key', 'error');
    }
}

async function revokeAPI() {
    if (!confirm('Tens a certeza que queres revogar a API Key?')) return;

    const r = await api('revoke_api');
    if (r.success) {
        showToast(r.message, 'success');
        setTimeout(() => location.reload(), 800);
    }
}

function copyApiKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        showToast('API Key copiada!', 'success');
    });
}

// Eliminar conta
function confirmDelete() {
    document.getElementById('deleteModal').style.display = 'flex';
}

async function deleteAccount() {
    const pw = document.getElementById('deletePassword').value;
    if (!pw) { showToast('Introduz a tua password', 'error'); return; }

    const r = await api('delete_account', { password: pw });
    if (r.success) {
        showToast('Conta eliminada. A redirecionar...', 'info');
        setTimeout(() => window.location.href = r.redirect, 1500);
    } else {
        showToast(r.error, 'error');
    }
}
</script>

<script>
    // Sistema de Toast Notifications
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

    // Toggle switches
    const toggles = {
        darkMode:           document.getElementById('darkModeToggle'),
        emailNotifications: document.getElementById('emailNotificationsToggle'),
        digest:             document.getElementById('digestToggle'),
    };

    const html = document.documentElement;

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        if (toggles.darkMode) toggles.darkMode.classList.toggle('active', theme === 'dark');
    }

    // Inicializar tema a partir de localStorage (mesma fonte que o resto da app)
    applyTheme(localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));

    // Eventos dos toggles
    Object.keys(toggles).forEach(key => {
        const toggle = toggles[key];
        if (!toggle) return;
        
        toggle.addEventListener('click', () => {
            toggle.classList.toggle('active');
            const isActive = toggle.classList.contains('active');

            if (key === 'darkMode') {
                applyTheme(isActive ? 'dark' : 'light');
                showToast(isActive ? 'Modo escuro ativado' : 'Modo claro ativado', 'success');
                api('save_preferences', { dark_mode: isActive ? 1 : 0 });
            } else if (key === 'emailNotifications') {
                api('save_preferences', { email_notifications: isActive ? 1 : 0 });
                showToast(`Notificações por email ${isActive ? 'ativadas' : 'desativadas'}`, 'success');
            } else if (key === 'digest') {
                api('save_preferences', { digest_notifications: isActive ? 1 : 0 });
                showToast(`Resumo diário ${isActive ? 'ativado' : 'desativado'}`, 'success');
            }
        });
    });

    // Região
    async function saveTimezone(tz) {
        const r = await api('save_preferences', { timezone: tz });
        if (r.success) showToast('Fuso horário guardado', 'success');
        else showToast(r.error || 'Erro ao guardar', 'error');
    }

    async function saveDateFormat(fmt, btn) {
        btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const r = await api('save_preferences', { date_format: fmt });
        if (r.success) showToast('Formato de data guardado', 'success');
        else showToast(r.error || 'Erro ao guardar', 'error');
    }

    // Efeito de entrada suave nos cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.settings-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);


        
    });
</script>
</body>
</html>