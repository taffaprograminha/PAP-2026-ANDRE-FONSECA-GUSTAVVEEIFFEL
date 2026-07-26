<?php
session_start();
if (!isset($_SESSION['email'])) { header("Location: login.php"); exit(); }

require_once "ligacao.php";

// Dados utilizadores
$users = $conn->query("
    SELECT u.id, u.nome, u.email, u.rfid_uid, u.ativo, u.created_at,
           cb.id        AS bloqueado_id,
           cb.motivo    AS bloqueado_motivo,
           cb.criado_em AS bloqueado_em
    FROM users_autorizados u
    LEFT JOIN cartoes_bloqueados cb ON cb.cartao_uid = u.rfid_uid
    ORDER BY u.nome ASC
")->fetch_all(MYSQLI_ASSOC);

// Histórico de bloqueios (inclui UIDs sem utilizador)
$blocos = $conn->query("
    SELECT cb.id, cb.cartao_uid, cb.motivo, cb.criado_em,
           u.nome AS user_nome
    FROM cartoes_bloqueados cb
    LEFT JOIN users_autorizados u ON u.rfid_uid = cb.cartao_uid
    ORDER BY cb.criado_em DESC
")->fetch_all(MYSQLI_ASSOC);

$total      = count($users);
$ativos     = count(array_filter($users, fn($u) => $u['ativo'] == 1));
$desativados = $total - $ativos;
$bloqueados  = count(array_filter($users, fn($u) => $u['bloqueado_id'] !== null));
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
    <title>Utilizadores — RFID</title>
    <link rel="stylesheet" href="dashboard.css?v=<?php echo @filemtime("dashboard.css"); ?>">
    <style>
        /* ── Tabela ─────────────────────────────────────────────────────────── */
        .tbl { width:100%; border-collapse:collapse; }
        .tbl th, .tbl td { padding:13px 16px; text-align:left; font-size:14px; }
        .tbl th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; color:var(--text-secondary); background:rgba(0,0,0,.02); }
        [data-theme="dark"] .tbl th { background:rgba(255,255,255,.02); }
        .tbl tr { border-bottom:1px solid var(--separator); }
        .tbl tr:last-child { border-bottom:none; }
        .tbl tbody tr { transition:background .15s; }
        .tbl tbody tr:hover { background:var(--hover-bg); }

        /* ── Badges ─────────────────────────────────────────────────────────── */
        .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600; }
        .badge::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
        .badge-ok  { background:rgba(46,125,85,.15);  color:var(--success); }
        .badge-off { background:rgba(142,142,147,.15); color:var(--text-secondary); }
        .badge-blocked { background:rgba(192,57,43,.15); color:var(--danger); }

        /* ── Botões toggle ───────────────────────────────────────────────────── */
        .tbtn { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:8px; font-size:12px; font-weight:600; border:none; cursor:pointer; transition:all .2s; }
        .tbtn svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; }
        .tbtn.t-active   { background:rgba(46,125,85,.15);   color:var(--success); }
        .tbtn.t-active:hover { background:rgba(46,125,85,.3); }
        .tbtn.t-inactive { background:rgba(142,142,147,.12); color:var(--text-secondary); }
        .tbtn.t-inactive:hover { background:rgba(142,142,147,.25); }
        .tbtn.t-block    { background:rgba(192,57,43,.12);   color:var(--danger); }
        .tbtn.t-block:hover   { background:rgba(192,57,43,.25); }
        .tbtn.t-unblock  { background:rgba(178,106,0,.12);   color:var(--warning); }
        .tbtn.t-unblock:hover { background:rgba(178,106,0,.25); }
        .tbtn.t-edit     { background:rgba(110,86,207,.12);   color:var(--accent); }
        .tbtn.t-edit:hover    { background:rgba(110,86,207,.22); }
        .tbtn.t-del      { background:rgba(192,57,43,.08);   color:var(--danger); }
        .tbtn.t-del:hover     { background:rgba(192,57,43,.2); }

        /* ── Pesquisa ────────────────────────────────────────────────────────── */
        .search-wrap { position:relative; }
        .search-wrap svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:15px; height:15px; stroke:var(--text-secondary); fill:none; stroke-width:2; pointer-events:none; }
        .search-input { width:240px; padding:8px 12px 8px 34px; border-radius:10px; border:1px solid var(--card-border); background:var(--card-bg); color:var(--text-primary); font-size:14px; outline:none; transition:all .2s; }
        .search-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(110,86,207,.12); }

        /* ── Modal ───────────────────────────────────────────────────────────── */
        .overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:1000; padding:20px; }
        .overlay.open { display:flex; }
        .modal { background:var(--card-bg); border:1px solid var(--card-border); border-radius:20px; box-shadow:var(--shadow-lg); width:100%; max-width:440px; overflow:hidden; }
        .modal-hdr { padding:20px 24px; border-bottom:1px solid var(--separator); display:flex; justify-content:space-between; align-items:center; }
        .modal-hdr h3 { font-size:17px; font-weight:600; }
        .modal-hdr button { background:none; border:none; cursor:pointer; font-size:20px; color:var(--text-secondary); width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
        .modal-hdr button:hover { background:var(--hover-bg); }
        .modal-body { padding:20px 24px; display:flex; flex-direction:column; gap:14px; }
        .modal-ftr { padding:16px 24px; border-top:1px solid var(--separator); display:flex; justify-content:flex-end; gap:10px; }
        .form-lbl { display:block; font-size:11px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.3px; margin-bottom:6px; }
        .form-inp { width:100%; padding:10px 13px; border-radius:10px; border:1px solid var(--card-border); background:var(--card-inset); color:var(--text-primary); font-size:14px; outline:none; transition:all .2s; font-family:inherit; }
        .form-inp:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(110,86,207,.12); }

        /* ── Histórico bloqueios ─────────────────────────────────────────────── */
        .uid-code { font-family:ui-monospace,"SF Mono",monospace; font-size:12px; background:var(--hover-bg); padding:3px 7px; border-radius:6px; }
        .motivo-text { font-size:13px; color:var(--text-secondary); font-style:italic; }

        .empty-state { text-align:center; padding:40px 20px; color:var(--text-secondary); font-size:14px; }
    </style>
</head>
<body>
<div class="toast-container" id="toastContainer"></div>

<!-- Modal Editar Utilizador -->
<div class="overlay" id="editOverlay">
    <div class="modal">
        <div class="modal-hdr">
            <h3>Editar Utilizador</h3>
            <button onclick="closeModal('editOverlay')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editId">
            <div>
                <label class="form-lbl">Nome</label>
                <input id="editNome" class="form-inp" type="text" placeholder="Nome do utilizador"
                       oninput="this.value=this.value.replace(/[^A-Za-zÀ-ÿ\s]/g,'')">
            </div>
            <div>
                <label class="form-lbl">Email</label>
                <input id="editEmail" class="form-inp" type="email" placeholder="email@exemplo.com">
            </div>
        </div>
        <div class="modal-ftr">
            <button class="btn btn-secondary" onclick="closeModal('editOverlay')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveEdit()">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal Bloquear (com motivo) -->
<div class="overlay" id="blockOverlay">
    <div class="modal">
        <div class="modal-hdr">
            <h3>Bloquear Cartão</h3>
            <button onclick="closeModal('blockOverlay')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="blockRfid">
            <div>
                <label class="form-lbl">Cartão UID</label>
                <input id="blockRfidDisplay" class="form-inp" type="text" readonly style="opacity:.6">
            </div>
            <div>
                <label class="form-lbl">Motivo</label>
                <input id="blockMotivo" class="form-inp" type="text" placeholder="Ex: Cartão perdido" value="Bloqueado pelo administrador">
            </div>
        </div>
        <div class="modal-ftr">
            <button class="btn btn-secondary" onclick="closeModal('blockOverlay')">Cancelar</button>
            <button class="btn btn-danger" onclick="confirmBlock()">Bloquear</button>
        </div>
    </div>
</div>

<?php require '_sidebar.php'; ?>

<div class="content">
    <div class="page-header">
        <div class="page-title-section">
            <h1>Utilizadores RFID</h1>
            <p class="page-subtitle">Gestão de utilizadores e cartões de acesso</p>
        </div>
        <div class="header-actions">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" class="search-input" id="searchInput" placeholder="Pesquisar..." oninput="filterUsers()">
            </div>
            <button class="btn btn-secondary" onclick="location.reload()">
                <svg class="btn-icon" viewBox="0 0 24 24"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Atualizar
            </button>
            <a href="adicionar_user.php" class="btn btn-primary">
                <svg class="btn-icon" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Novo Utilizador
            </a>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header"><div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></div></div>
            <div class="stat-value" id="sc-total"><?php echo $total; ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div></div>
            <div class="stat-value" id="sc-ativos"><?php echo $ativos; ?></div>
            <div class="stat-label">Ativos</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div></div>
            <div class="stat-value" id="sc-desativ"><?php echo $desativados; ?></div>
            <div class="stat-label">Desativados</div>
        </div>
        <div class="stat-card">
            <div class="stat-header"><div class="stat-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div></div>
            <div class="stat-value" id="sc-bloq"><?php echo $bloqueados; ?></div>
            <div class="stat-label">Bloqueados</div>
        </div>
    </div>

    <!-- Tabela utilizadores -->
    <div class="card" style="margin-bottom:28px">
        <div class="card-header">
            <h3 class="card-title">Utilizadores Registados</h3>
            <span class="card-action" id="countLabel"><?php echo $total; ?> utilizadores</span>
        </div>
        <div class="card-content" style="padding:0">
            <?php if (empty($users)): ?>
            <div class="empty-state">Nenhum utilizador registado. <a href="adicionar_user.php" style="color:var(--accent)">Adicionar agora</a></div>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table class="tbl" id="usersTable">
                    <thead><tr>
                        <th>Nome</th><th>Email</th><th>RFID UID</th><th>Estado</th><th>Cartão</th><th>Desde</th><th>Ações</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $ativo = (int)$u['ativo'];
                        $bloq  = $u['bloqueado_id'] !== null;
                    ?>
                    <tr id="row-<?php echo $u['id']; ?>"
                        data-search="<?php echo strtolower(htmlspecialchars($u['nome'].' '.$u['email'].' '.$u['rfid_uid'])); ?>">
                        <td style="font-weight:600" id="nome-<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nome']); ?></td>
                        <td style="color:var(--text-secondary)" id="email-<?php echo $u['id']; ?>"><?php echo $u['email'] ? htmlspecialchars($u['email']) : '—'; ?></td>
                        <td><code class="uid-code"><?php echo htmlspecialchars($u['rfid_uid']); ?></code></td>
                        <td id="badge-ativo-<?php echo $u['id']; ?>">
                            <span class="badge <?php echo $ativo ? 'badge-ok' : 'badge-off'; ?>"><?php echo $ativo ? 'Ativo' : 'Desativado'; ?></span>
                        </td>
                        <td id="badge-bloq-<?php echo $u['id']; ?>">
                            <span class="badge <?php echo $bloq ? 'badge-blocked' : 'badge-ok'; ?>"><?php echo $bloq ? 'Bloqueado' : 'Livre'; ?></span>
                        </td>
                        <td style="color:var(--text-secondary);white-space:nowrap"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <div style="display:flex;gap:5px;flex-wrap:wrap">
                                <!-- Ativo/Desativado -->
                                <button class="tbtn <?php echo $ativo ? 't-active' : 't-inactive'; ?>"
                                        id="btn-ativo-<?php echo $u['id']; ?>"
                                        data-id="<?php echo $u['id']; ?>"
                                        onclick="toggleAtivo(this)">
                                    <?php if ($ativo): ?>
                                    <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Ativo
                                    <?php else: ?>
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>Desativ.
                                    <?php endif; ?>
                                </button>
                                <!-- Bloquear/Desbloquear -->
                                <button class="tbtn <?php echo $bloq ? 't-unblock' : 't-block'; ?>"
                                        id="btn-bloq-<?php echo $u['id']; ?>"
                                        data-rfid="<?php echo htmlspecialchars($u['rfid_uid']); ?>"
                                        data-userid="<?php echo $u['id']; ?>"
                                        data-bloq="<?php echo $bloq ? '1':'0'; ?>"
                                        onclick="clickBlock(this)">
                                    <?php if ($bloq): ?>
                                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Desbloquear
                                    <?php else: ?>
                                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>Bloquear
                                    <?php endif; ?>
                                </button>
                                <!-- Editar -->
                                <button class="tbtn t-edit"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($u['nome']); ?>"
                                        data-email="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"
                                        onclick="openEdit(this)">
                                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>Editar
                                </button>
                                <!-- Eliminar -->
                                <button class="tbtn t-del"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($u['nome']); ?>"
                                        onclick="deleteUser(this)">
                                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Histórico de Bloqueios -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Histórico de Bloqueios</h3>
            <span class="card-action"><?php echo count($blocos); ?> registos</span>
        </div>
        <div class="card-content" style="padding:0">
            <?php if (empty($blocos)): ?>
            <div class="empty-state">Nenhum cartão bloqueado.</div>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table class="tbl">
                    <thead><tr>
                        <th>Cartão UID</th><th>Utilizador</th><th>Motivo</th><th>Bloqueado em</th><th>Ação</th>
                    </tr></thead>
                    <tbody id="bloqTable">
                    <?php foreach ($blocos as $b): ?>
                    <tr id="bloq-row-<?php echo $b['id']; ?>">
                        <td><code class="uid-code"><?php echo htmlspecialchars($b['cartao_uid']); ?></code></td>
                        <td><?php echo $b['user_nome'] ? htmlspecialchars($b['user_nome']) : '<span style="color:var(--text-secondary);font-style:italic">Desconhecido</span>'; ?></td>
                        <td><span class="motivo-text"><?php echo htmlspecialchars($b['motivo'] ?? '—'); ?></span></td>
                        <td style="color:var(--text-secondary);white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($b['criado_em'])); ?></td>
                        <td>
                            <button class="tbtn t-unblock"
                                    data-rfid="<?php echo htmlspecialchars($b['cartao_uid']); ?>"
                                    data-bloqid="<?php echo $b['id']; ?>"
                                    onclick="desbloquearDireto(this)">
                                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Desbloquear
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Toast
function showToast(msg, type='info') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${msg}</span><button class="toast-close" onclick="this.parentElement.remove()">✕</button>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('hide'); setTimeout(() => t.remove(), 300); }, 3500);
}

// API helper
async function api(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k,v));
    const r = await fetch('api_users.php', { method:'POST', body:fd });
    return r.json();
}

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));

// Pesquisa
function filterUsers() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    let v = 0;
    rows.forEach(r => { const m = r.dataset.search.includes(q); r.style.display = m ? '' : 'none'; if(m) v++; });
    document.getElementById('countLabel').textContent = v + ' utilizadores';
}

// Toggle ativo
async function toggleAtivo(btn) {
    const id = btn.dataset.id;
    btn.disabled = true;
    try {
        const d = await api({ action:'toggle_ativo', id });
        if (!d.success) throw new Error(d.error);
        const on = d.ativo === 1;
        btn.className = 'tbtn ' + (on ? 't-active' : 't-inactive');
        btn.innerHTML = on
            ? '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Ativo'
            : '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>Desativ.';
        document.getElementById('badge-ativo-'+id).innerHTML =
            `<span class="badge ${on?'badge-ok':'badge-off'}">${on?'Ativo':'Desativado'}</span>`;
        showToast(on ? 'Utilizador ativado.' : 'Utilizador desativado.', 'info');
    } catch(e) { showToast('Erro: '+e.message, 'error'); }
    btn.disabled = false;
}

// Abrir modal bloquear
let _blockBtn = null;
function clickBlock(btn) {
    const bloq = btn.dataset.bloq === '1';
    if (bloq) {
        // Desbloquear direto sem modal
        doToggleBlock(btn, '');
    } else {
        // Abrir modal para pedir motivo
        _blockBtn = btn;
        document.getElementById('blockRfid').value = btn.dataset.rfid;
        document.getElementById('blockRfidDisplay').value = btn.dataset.rfid;
        document.getElementById('blockMotivo').value = 'Bloqueado pelo administrador';
        openModal('blockOverlay');
    }
}

async function confirmBlock() {
    const motivo = document.getElementById('blockMotivo').value.trim() || 'Bloqueado pelo administrador';
    closeModal('blockOverlay');
    await doToggleBlock(_blockBtn, motivo);
}

async function doToggleBlock(btn, motivo) {
    const id   = btn.dataset.userid;
    const rfid = btn.dataset.rfid;
    const bloq = btn.dataset.bloq === '1';
    btn.disabled = true;
    try {
        const d = await api({ action:'toggle_bloqueado', rfid_uid:rfid, motivo });
        if (!d.success) throw new Error(d.error);
        const nowBloq = d.bloqueado;
        btn.dataset.bloq = nowBloq ? '1' : '0';
        btn.className = 'tbtn ' + (nowBloq ? 't-unblock' : 't-block');
        btn.innerHTML = nowBloq
            ? '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Desbloquear'
            : '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>Bloquear';
        if (id) document.getElementById('badge-bloq-'+id).innerHTML =
            `<span class="badge ${nowBloq?'badge-blocked':'badge-ok'}">${nowBloq?'Bloqueado':'Livre'}</span>`;
        showToast(nowBloq ? 'Cartão bloqueado.' : 'Cartão desbloqueado.', 'info');
        // Recarregar a tabela de histórico após 800ms
        setTimeout(() => location.reload(), 900);
    } catch(e) { showToast('Erro: '+e.message, 'error'); }
    btn.disabled = false;
}

// Desbloquear direto a partir do histórico
async function desbloquearDireto(btn) {
    const rfid   = btn.dataset.rfid;
    const bloqid = btn.dataset.bloqid;
    btn.disabled = true;
    try {
        const d = await api({ action:'toggle_bloqueado', rfid_uid:rfid });
        if (!d.success) throw new Error(d.error);
        showToast('Cartão desbloqueado.', 'info');
        const row = document.getElementById('bloq-row-'+bloqid);
        if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>row.remove(), 300); }
        // Actualizar badge na tabela de utilizadores se existir
        document.querySelectorAll('[data-rfid="'+rfid+'"]').forEach(b => {
            if (b.dataset.bloq !== undefined) {
                b.dataset.bloq = '0';
                b.className = 'tbtn t-block';
                b.innerHTML = '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>Bloquear';
            }
        });
    } catch(e) { showToast('Erro: '+e.message, 'error'); }
    btn.disabled = false;
}

// Editar utilizador
function openEdit(btn) {
    document.getElementById('editId').value    = btn.dataset.id;
    document.getElementById('editNome').value  = btn.dataset.nome;
    document.getElementById('editEmail').value = btn.dataset.email;
    openModal('editOverlay');
}

async function saveEdit() {
    const id    = document.getElementById('editId').value;
    const nome  = document.getElementById('editNome').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    if (!nome) { showToast('Nome é obrigatório.', 'error'); return; }
    try {
        const d = await api({ action:'update_user', id, nome, email });
        if (!d.success) throw new Error(d.error);
        closeModal('editOverlay');
        // Actualizar células na tabela
        const n = document.getElementById('nome-'+id);
        const e = document.getElementById('email-'+id);
        if (n) n.textContent = d.nome;
        if (e) e.textContent = d.email || '—';
        // Actualizar data-nome no botão editar
        document.querySelector(`[data-id="${id}"][data-nome]`).dataset.nome  = d.nome;
        document.querySelector(`[data-id="${id}"][data-nome]`).dataset.email = d.email;
        // Actualizar search
        const row = document.getElementById('row-'+id);
        if (row) row.dataset.search = (d.nome+' '+d.email).toLowerCase();
        showToast('Utilizador atualizado.', 'success');
    } catch(e) { showToast('Erro: '+e.message, 'error'); }
}

// Eliminar utilizador
async function deleteUser(btn) {
    const id   = btn.dataset.id;
    const nome = btn.dataset.nome;
    if (!confirm(`Eliminar "${nome}"? Esta ação não pode ser desfeita.`)) return;
    btn.disabled = true;
    try {
        const d = await api({ action:'delete_user', id });
        if (!d.success) throw new Error(d.error);
        const row = document.getElementById('row-'+id);
        if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>row.remove(), 300); }
        showToast(`"${nome}" eliminado.`, 'info');
    } catch(e) { showToast('Erro: '+e.message, 'error'); }
    btn.disabled = false;
}

// Auto-refresh: recarrega quando utilizadores ou bloqueios mudam
(function autoRefresh() {
    const baseline = <?php echo time(); ?>;
    let lastTs = baseline;
    function check() {
        fetch('api_check_updates.php')
            .then(r => r.json())
            .then(d => {
                const newTs = Math.max(d.users, d.bloqueios);
                if (newTs > lastTs) {
                    // Só recarrega se nenhum modal estiver aberto
                    const aberto = document.querySelector('.overlay.open') !== null;
                    if (!aberto) {
                        location.reload();
                    } else {
                        lastTs = newTs; // atualiza baseline para não recarregar repetidamente
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
