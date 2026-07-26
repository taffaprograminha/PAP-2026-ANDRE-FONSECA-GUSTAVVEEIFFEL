<?php
// _sidebar.php - sidebar partilhada por todas as páginas
// Requer que session_start() já tenha sido chamado antes de incluir este ficheiro.

$_sbPage  = basename($_SERVER['PHP_SELF']);
$_sbEmail = $_SESSION['email'] ?? '';
$_sbName  = ucwords(str_replace(['.', '_'], ' ', explode('@', $_sbEmail)[0]));
$_sbParts = explode(' ', trim($_sbName));
$_sbInit  = strtoupper(substr($_sbParts[0], 0, 1));
if (isset($_sbParts[1]) && $_sbParts[1] !== '') {
    $_sbInit .= strtoupper(substr($_sbParts[1], 0, 1));
}

function _sb_active(string $page): string {
    global $_sbPage;
    return $_sbPage === $page ? ' class="active"' : '';
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3>RFID Panel</h3>
        <p class="sidebar-subtitle">Sistema de Gestão</p>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">Principal</div>
        <a href="dashboard.php"<?php echo _sb_active('dashboard.php'); ?>>
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
            <span>Overview</span>
        </a>
        <a href="users.php"<?php echo _sb_active('users.php'); ?>>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
            <span>Utilizadores</span>
        </a>
        <a href="placas.php"<?php echo _sb_active('placas.php'); ?>>
            <svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M4 9h5M15 9h5M4 15h5M15 15h5M9 4v5M15 4v5M9 15v5M15 15v5"/></svg>
            <span>Placas Arduino</span>
        </a>
        <a href="leituras_rfid.php"<?php echo _sb_active('leituras_rfid.php'); ?> style="justify-content:space-between">
            <span style="display:flex;align-items:center;gap:0">
                <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                <span>Leituras RFID</span>
            </span>
            <span id="_sbAlertBadge" style="display:none;background:#C0392B;color:#fff;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:9px;padding:0 5px;display:none;align-items:center;justify-content:center;line-height:1;margin-left:auto;flex-shrink:0"></span>
        </a>
        <a href="estatisticas.php"<?php echo _sb_active('estatisticas.php'); ?>>
            <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
            <span>Estatísticas</span>
        </a>
    </div>

    <div class="nav-section">
        <div class="nav-section-title">Sistema</div>
        <a href="settings.php"<?php echo _sb_active('settings.php'); ?>>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Definições</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="user-preview" onclick="location.href='settings.php'" style="cursor:pointer;">
            <div class="user-avatar"><?php echo htmlspecialchars($_sbInit); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_sbName); ?></div>
                <div class="user-role">Administrador</div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn" onclick="return confirm('Terminar sessão?')">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Terminar Sessão
        </a>
    </div>
</div>
<script>
// Badge de alertas (cartões desconhecidos última hora)
(function pollAlerts(){
    const badge = document.getElementById('_sbAlertBadge');
    if (!badge) return;
    fetch('api_alerts.php')
        .then(r => r.json())
        .then(d => {
            if (d.count > 0) {
                badge.textContent = d.count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(() => {});
    setTimeout(pollAlerts, 30000);
})();
</script>

<!-- ═══════════════════════ CHAT FLUTUANTE (global) ═══════════════════════ -->
<style>
#_chatBubble{position:fixed;bottom:28px;right:28px;width:52px;height:52px;border-radius:14px;
    background:var(--accent,#6E56CF);color:#fff;display:flex;align-items:center;justify-content:center;
    cursor:pointer;box-shadow:0 4px 20px rgba(110,86,207,.4);z-index:1100;transition:all .3s ease;
    border:none;}
#_chatBubble:hover{background:var(--accent-hover,#5A43B8);}
#_chatWin{display:none;position:fixed;bottom:96px;right:28px;width:380px;max-height:640px;
    background:var(--card-bg,#fff);border:1px solid var(--card-border,#ddd);border-radius:20px;
    box-shadow:0 10px 40px rgba(0,0,0,.15);z-index:1099;flex-direction:column;overflow:hidden;
}
#_chatMsgs{flex:1;overflow-y:scroll;overscroll-behavior:contain;padding:14px;display:flex;flex-direction:column;
    gap:10px;min-height:300px;max-height:470px;scrollbar-width:thin;
    scrollbar-color:var(--accent,#6E56CF) transparent;}
#_chatMsgs::-webkit-scrollbar{width:8px;}
#_chatMsgs::-webkit-scrollbar-track{background:transparent;}
#_chatMsgs::-webkit-scrollbar-thumb{background:var(--accent,#6E56CF);border-radius:4px;opacity:.7;}
#_chatMsgs::-webkit-scrollbar-thumb:hover{background:var(--accent-hover,#5A43B8);}
.cm-user{align-self:flex-end;background:var(--accent,#6E56CF);color:#fff;padding:9px 13px;
    border-radius:14px 14px 4px 14px;max-width:88%;font-size:13px;line-height:1.45;word-break:break-word;}
.cm-bot{align-self:flex-start;background:var(--hover-bg,#f0f0f0);border:1px solid var(--card-border,#ddd);
    padding:9px 13px;border-radius:14px 14px 14px 4px;max-width:88%;font-size:13px;
    color:var(--text-primary,#111);line-height:1.5;word-break:break-word;}
.cm-thinking{align-self:flex-start;color:var(--text-secondary,#888);font-size:12px;
    padding:6px 13px;font-style:italic;}
.cm-action{align-self:flex-start;width:90%;background:var(--card-bg,#fff);
    border:1px solid var(--accent,#6E56CF);border-radius:14px;overflow:hidden;font-size:13px;}
.cm-action-hdr{background:rgba(110,86,207,.1);padding:9px 13px;display:flex;align-items:center;
    gap:8px;font-weight:600;color:var(--accent,#6E56CF);}
.cm-action-hdr svg{width:15px;height:15px;stroke:var(--accent,#6E56CF);fill:none;stroke-width:2;flex-shrink:0;}
.cm-action-body{padding:9px 13px;color:var(--text-primary,#111);line-height:1.5;}
.cm-action-ftr{padding:8px 13px;display:flex;gap:8px;border-top:1px solid var(--separator,#eee);}
.cm-action-ftr button{flex:1;padding:7px;border-radius:8px;border:none;font-size:12px;
    font-weight:600;cursor:pointer;transition:all .2s;}
.cm-btn-ok{background:var(--accent,#6E56CF);color:#fff;}
.cm-btn-ok:hover{opacity:.88;}
.cm-btn-no{background:var(--card-inset,#f5f5f5);color:var(--text-secondary,#888);
    border:1px solid var(--card-border,#ddd)!important;}
.cm-btn-no:hover{background:var(--hover-bg,#eee);}
.cm-ok{align-self:flex-start;background:rgba(46,125,85,.12);border:1px solid #2E7D55;
    color:#2E7D55;padding:9px 13px;border-radius:12px;font-size:13px;max-width:88%;}
.cm-err{align-self:flex-start;background:rgba(192,57,43,.1);border:1px solid #C0392B;
    color:#C0392B;padding:9px 13px;border-radius:12px;font-size:13px;max-width:88%;}
._chat-hints{display:flex;flex-wrap:wrap;gap:5px;padding:6px 14px 0;}
._chat-hint{background:var(--hover-bg,#f0f0f0);border:1px solid var(--card-border,#ddd);
    border-radius:20px;padding:4px 10px;font-size:11px;cursor:pointer;
    color:var(--text-secondary,#888);transition:all .2s;white-space:nowrap;}
._chat-hint:hover{background:rgba(110,86,207,.1);color:var(--accent,#6E56CF);border-color:var(--accent,#6E56CF);}
/* ── Barra de modo (Visualização / Execução) ── */
#_chatModeBar{display:flex;gap:6px;padding:8px 13px;border-bottom:1px solid var(--separator,#eee);
    background:var(--card-inset,#fafafa);}
._chatModeBtn{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:6px 8px;
    border-radius:8px;border:1px solid var(--card-border,#ddd);background:transparent;
    color:var(--text-secondary,#888);font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;}
._chatModeBtn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
._chatModeBtn:hover{background:var(--hover-bg,#f0f0f0);}
#_chatModeView.active{background:rgba(110,86,207,.12);color:var(--accent,#6E56CF);border-color:var(--accent,#6E56CF);}
#_chatModeExec.active{background:rgba(178,106,0,.14);color:#B26A00;border-color:#B26A00;}
/* Aviso visual quando em modo Execução */
#_chatWin._execMode #_chatHdr{background:#B26A00!important;}
</style>

<!-- Botão flutuante -->
<button id="_chatBubble" onclick="_chatToggle()" title="Assistente IA">
    <svg viewBox="0 0 24 24" style="width:24px;height:24px;stroke:#fff;fill:none;stroke-width:2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
</button>

<!-- Janela do chat -->
<div id="_chatWin">
    <div id="_chatHdr" style="padding:13px 17px;border-bottom:1px solid var(--separator,#eee);display:flex;
                justify-content:space-between;align-items:center;background:var(--accent,#6E56CF);
                border-radius:20px 20px 0 0;transition:background .25s ease;">
        <div style="display:flex;align-items:center;gap:10px;">
            <svg style="width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2" viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2"/><circle cx="12" cy="5" r="2"/>
                <path d="M12 7v4M8 11V9M16 11V9"/>
            </svg>
            <div>
                <div style="font-size:14px;font-weight:600;color:#fff;">Assistente RFID</div>
                <div id="_chatModeLabel" style="font-size:11px;color:rgba(255,255,255,.75);">Modo Visualização</div>
            </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button onclick="_chatClear()" title="Limpar conversa"
                style="background:rgba(255,255,255,.15);border:none;color:#fff;width:26px;height:26px;
                       border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;">
                🗑
            </button>
            <button onclick="_chatToggle()"
                style="background:rgba(255,255,255,.2);border:none;color:#fff;width:26px;height:26px;
                       border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <div id="_chatModeBar">
        <button id="_chatModeView" class="_chatModeBtn active" onclick="_chatSetMode('view')">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            Visualização
        </button>
        <button id="_chatModeExec" class="_chatModeBtn" onclick="_chatSetMode('exec')">
            <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            Execução
        </button>
    </div>

    <div id="_chatMsgs"></div>

    <div class="_chat-hints" id="_chatHints">
        <span class="_chat-hint" onclick="_chatHint(this)">Quem entrou hoje?</span>
        <span class="_chat-hint" onclick="_chatHint(this)">Cartões suspeitos</span>
        <span class="_chat-hint" onclick="_chatHint(this)">Estado das placas</span>
        <span class="_chat-hint" onclick="_chatHint(this)">Lista utilizadores</span>
    </div>

    <div style="padding:10px 13px;border-top:1px solid var(--separator,#eee);display:flex;gap:8px;margin-top:4px;">
        <input type="text" id="_chatInput" placeholder="Pergunta ou comando..."
            style="flex:1;padding:10px 13px;border-radius:10px;border:1px solid var(--card-border,#ddd);
                   background:var(--card-inset,#fafafa);color:var(--text-primary,#111);font-size:13px;outline:none;"
            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();_chatSend();}">
        <button id="_chatSendBtn" onclick="_chatSend()"
            style="background:var(--accent,#6E56CF);color:#fff;border:none;border-radius:10px;
                   width:42px;height:42px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
        </button>
    </div>
</div>

<script>
(function(){
    // Base URL para os ficheiros da app
    // Detecta o path base a partir da URL atual
    const _base = (function(){
        const p = window.location.pathname;
        return p.substring(0, p.lastIndexOf('/') + 1);
    })();

    // Persistência via localStorage
    const LS_OPEN = 'rfid_chat_open';
    const LS_HIST = 'rfid_chat_history'; // [{cls, html}] — máx 30
    const LS_MODE = 'rfid_chat_mode';    // 'view' | 'exec'

    let _history = [];      // [{role,content}] para a API
    let _msgs    = [];      // [{cls, html}] para re-renderizar
    let _sending = false;
    let _mode    = 'view';  // 'view' = só leitura | 'exec' = pode executar

    // Remove HTML para o histórico enviado ao modelo ficar em texto limpo
    function _stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return (tmp.textContent || tmp.innerText || '').trim();
    }

    // Sanitiza texto do LLM: escapa HTML e converte markdown básico seguro
    function _sanitize(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`\n]+)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    function _save() {
        try {
            localStorage.setItem(LS_HIST, JSON.stringify(_msgs.slice(-30)));
            localStorage.setItem(LS_OPEN, document.getElementById('_chatWin').style.display === 'flex' ? '1' : '0');
        } catch(e) {}
    }

    function _load() {
        try {
            const saved = localStorage.getItem(LS_HIST);
            if (saved) {
                _msgs = JSON.parse(saved) || [];
                const box = document.getElementById('_chatMsgs');
                if (_msgs.length > 0) {
                    // Reconstrói histórico visual
                    _msgs.forEach(m => {
                        const d = document.createElement('div');
                        d.className = m.cls;
                        d.innerHTML = m.html;
                        box.appendChild(d);
                    });
                    document.getElementById('_chatHints').style.display = 'none';
                    box.scrollTop = box.scrollHeight;
                    // Reconstrói history para a API (texto limpo, sem HTML nem cards de ação)
                    _msgs.forEach(m => {
                        if (m.cls === 'cm-user') _history.push({role:'user',      content: _stripHtml(m.html)});
                        if (m.cls === 'cm-bot')  _history.push({role:'assistant', content: _stripHtml(m.html)});
                    });
                } else {
                    _addWelcome();
                }
            } else {
                _addWelcome();
            }
        } catch(e) { _addWelcome(); }
    }

    function _addWelcome() {
        _addMsg('Olá! 👋 Tens dois modos:<br>• <strong>👁 Visualização</strong> — perguntas e consultas (só leitura)<br>• <strong>⚡ Execução</strong> — dar instruções que alteram dados (pede password)<br>Usa os botões acima para alternar.', 'cm-bot', false);
    }

    // Alternar modo Visualização / Execução
    window._chatSetMode = function(mode) {
        _mode = (mode === 'exec') ? 'exec' : 'view';
        try { localStorage.setItem(LS_MODE, _mode); } catch(e) {}
        _applyMode();
        const txt = _mode === 'exec'
            ? '⚡ Modo <strong>Execução</strong> ativo. Os comandos pedem a tua password para confirmar.'
            : '👁 Modo <strong>Visualização</strong> ativo. Só leitura — não executo alterações.';
        _addMsg(txt, 'cm-thinking', false);
    };

    function _applyMode() {
        const win   = document.getElementById('_chatWin');
        const vBtn  = document.getElementById('_chatModeView');
        const eBtn  = document.getElementById('_chatModeExec');
        const label = document.getElementById('_chatModeLabel');
        const input = document.getElementById('_chatInput');
        if (!win) return;

        win.classList.toggle('_execMode', _mode === 'exec');
        if (vBtn) vBtn.classList.toggle('active', _mode === 'view');
        if (eBtn) eBtn.classList.toggle('active', _mode === 'exec');
        if (label) label.textContent = _mode === 'exec' ? 'Modo Execução' : 'Modo Visualização';
        if (input) input.placeholder = _mode === 'exec' ? 'Dá uma instrução para executar...' : 'Faz uma pergunta...';
    }

    // Toggle abrir/fechar
    window._chatToggle = function() {
        const win = document.getElementById('_chatWin');
        const isOpen = win.style.display === 'flex';
        win.style.display = isOpen ? 'none' : 'flex';
        win.style.flexDirection = 'column';
        if (!isOpen) document.getElementById('_chatInput').focus();
        _save();
    };

    // Limpar conversa
    window._chatClear = function() {
        if (!confirm('Limpar toda a conversa?')) return;
        _msgs = []; _history = [];
        document.getElementById('_chatMsgs').innerHTML = '';
        document.getElementById('_chatHints').style.display = 'flex';
        _addWelcome();
        try { localStorage.removeItem(LS_HIST); } catch(e) {}
    };

    // Sugestões rápidas
    window._chatHint = function(el) {
        document.getElementById('_chatInput').value = el.textContent;
        document.getElementById('_chatHints').style.display = 'none';
        _chatSend();
    };

    // Adicionar mensagem ao chat
    function _addMsg(html, cls, persist = true) {
        const box = document.getElementById('_chatMsgs');
        const div = document.createElement('div');
        div.className = cls;
        div.innerHTML = html;
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
        if (persist) {
            _msgs.push({cls, html});
            _save();
        }
        return div;
    }

    // Enviar mensagem
    window._chatSend = async function() {
        if (_sending) return;
        const input = document.getElementById('_chatInput');
        const msg   = input.value.trim();
        if (!msg) return;

        _sending = true;
        document.getElementById('_chatHints').style.display = 'none';
        _addMsg(_sanitize(msg), 'cm-user');
        input.value = '';
        input.disabled = true;
        document.getElementById('_chatSendBtn').disabled = true;

        const thinking = _addMsg('A pensar...', 'cm-thinking', false);

        try {
            const res  = await fetch(_base + 'chat_api.php', {
                method:  'POST',
                headers: {'Content-Type': 'application/json'},
                body:    JSON.stringify({message: msg, history: _history.slice(-12), mode: _mode})
            });
            const data = await res.json();
            thinking.remove();

            if (data.type === 'action') {
                _history.push({role:'user',      content: msg});
                _history.push({role:'assistant', content: 'Propus a ação: ' + (data.confirm || data.action_type) + '. A aguardar confirmação.'});
                _renderAction(data);
            } else if (data.type === 'action_batch') {
                _history.push({role:'user',      content: msg});
                _history.push({role:'assistant', content: 'Propus ' + data.actions.length + ' ações em lote. A aguardar confirmação.'});
                _renderActionBatch(data);
            } else {
                const reply = data.reply || data.error || 'Sem resposta.';
                const cls   = data.type === 'error' ? 'cm-err' : 'cm-bot';
                _addMsg(_sanitize(reply), cls);
                _history.push({role:'user',      content: msg});
                _history.push({role:'assistant', content: reply});
            }
        } catch {
            thinking.remove();
            _addMsg('Sem ligação ao assistente.', 'cm-err');
        }

        input.disabled = false;
        document.getElementById('_chatSendBtn').disabled = false;
        input.focus();
        _sending = false;
    };

    // Store de ações pendentes (evita problemas de encoding em onclick)
    const _pendingActions = {};
    let   _actionId = 0;

    const _actionLabels = {
        block_card:      {label:'Bloquear Cartão',      icon:'<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>'},
        unblock_card:    {label:'Desbloquear Cartão',   icon:'<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>'},
        activate_user:   {label:'Ativar Utilizador',    icon:'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'},
        deactivate_user: {label:'Desativar Utilizador', icon:'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'},
        delete_user:     {label:'Eliminar Utilizador',  icon:'<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>'},
        add_user:        {label:'Adicionar Utilizador', icon:'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'},
        update_user:     {label:'Editar Utilizador',    icon:'<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'},
    };

    function _renderAction(data) {
        // Segurança extra: nunca renderiza ações fora do modo Execução
        if (_mode !== 'exec') {
            _addMsg('Estou em modo de Visualização. Ativa o modo de Execução para fazer alterações.', 'cm-bot');
            return;
        }
        const id   = ++_actionId;
        _pendingActions[id] = {type: data.action_type, params: data.params, confirm: data.confirm};

        const info = _actionLabels[data.action_type] || {label: data.action_type, icon:''};
        const box  = document.getElementById('_chatMsgs');
        const card = document.createElement('div');
        card.className  = 'cm-action';
        card.dataset.id = id;
        card.innerHTML  = `
            <div class="cm-action-hdr">
                <svg viewBox="0 0 24 24">${info.icon}</svg>${info.label}
            </div>
            <div class="cm-action-body">${_sanitize(data.confirm)}</div>
            <div class="cm-action-ftr">
                <button class="cm-btn-ok"  data-id="${id}">✓ Confirmar</button>
                <button class="cm-btn-no"  data-id="${id}">✕ Cancelar</button>
            </div>`;

        card.querySelector('.cm-btn-ok').addEventListener('click', () => _showPasswordStep(id, card));
        card.querySelector('.cm-btn-no').addEventListener('click', () => _chatCancel(card));

        box.appendChild(card);
        box.scrollTop = box.scrollHeight;
    }

    // Passo de confirmação com password
    function _showPasswordStep(id, card) {
        card.querySelector('.cm-action-ftr').innerHTML = `
            <div style="width:100%;display:flex;flex-direction:column;gap:8px;">
                <div style="font-size:12px;color:var(--text-secondary,#888);display:flex;align-items:center;gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Confirma com a tua password
                </div>
                <div style="display:flex;gap:6px;">
                    <input type="password" class="cm-pwd-input" placeholder="Password..."
                        style="flex:1;padding:7px 10px;border-radius:8px;border:1px solid var(--card-border,#ddd);
                               background:var(--card-inset,#fafafa);color:var(--text-primary,#111);font-size:12px;outline:none;">
                    <button class="cm-btn-ok cm-pwd-ok" style="flex:0 0 auto;padding:7px 12px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </button>
                    <button class="cm-btn-no cm-pwd-no" style="flex:0 0 auto;padding:7px 12px;">✕</button>
                </div>
                <div class="cm-pwd-err" style="display:none;color:#C0392B;font-size:11px;"></div>
            </div>`;

        const inp  = card.querySelector('.cm-pwd-input');
        const err  = card.querySelector('.cm-pwd-err');
        const okB  = card.querySelector('.cm-pwd-ok');
        const noB  = card.querySelector('.cm-pwd-no');

        inp.focus();

        const submit = () => _chatExec(id, card, inp.value, err, okB);
        okB.addEventListener('click', submit);
        noB.addEventListener('click', () => _chatCancel(card));
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
    }

    async function _chatExec(id, card, password, errEl, okBtn) {
        const action = _pendingActions[id];
        if (!action) return;

        if (!password || password.trim() === '') {
            if (errEl) { errEl.textContent = 'Insere a password.'; errEl.style.display = 'block'; }
            return;
        }

        if (okBtn) okBtn.disabled = true;
        if (errEl) errEl.style.display = 'none';

        // Normaliza para array - suporta ação única e lote
        const items = action.batch
            ? action.batch
            : [{action_type: action.type, params: action.params}];

        const results = [];
        let wrongPwd = false;

        for (const item of items) {
            const fd = new FormData();
            fd.append('type',     item.action_type);
            fd.append('params',   JSON.stringify(item.params));
            fd.append('password', password);
            try {
                const res  = await fetch(_base + 'chat_execute.php', {method:'POST', body:fd});
                const data = await res.json();
                if (data.wrong_password) { wrongPwd = true; break; }
                results.push(data);
            } catch(e) {
                results.push({success: false, error: 'Erro de ligação'});
            }
        }

        if (wrongPwd) {
            if (errEl) { errEl.textContent = 'Password incorreta. Tenta novamente.'; errEl.style.display = 'block'; }
            if (okBtn) okBtn.disabled = false;
            return;
        }

        delete _pendingActions[id];
        card.remove();

        let hasSuccess = false;
        for (const r of results) {
            if (r.success) {
                _addMsg('✅ ' + r.message, 'cm-ok');
                hasSuccess = true;
            } else {
                _addMsg('❌ ' + (r.error || 'Erro ao executar.'), 'cm-err');
            }
        }

        // Regista resultado no histórico para manter o contexto
        const ok = results.filter(r => r.success).length;
        const histEntry = action.batch
            ? `Lote executado: ${ok}/${items.length} ações com sucesso.`
            : (results[0]?.success ? 'Ação executada com sucesso: ' + (action.confirm || action.type) : 'Ação falhou: ' + (results[0]?.error || 'erro desconhecido'));
        _history.push({role: 'assistant', content: histEntry});

        if (hasSuccess && typeof refreshStats === 'function') refreshStats();
        document.getElementById('_chatMsgs').scrollTop = 9999;
    }

    function _chatCancel(card) {
        const id = card.dataset.id;
        if (id) delete _pendingActions[id];
        card.remove();
        _addMsg('Ação cancelada.', 'cm-thinking', false);
        _history.push({role: 'assistant', content: 'Ação cancelada pelo utilizador.'});
    }

    // Renderizar card de lote: abre MODAL grande sobre a pagina para se ver tudo
    function _renderActionBatch(data) {
        if (_mode !== 'exec') {
            _addMsg('Estou em modo de Visualização. Ativa o modo de Execução para fazer alterações.', 'cm-bot');
            return;
        }
        const id = ++_actionId;
        _pendingActions[id] = {batch: data.actions};

        // pequeno card no chat só com resumo + abrir modal
        const list = data.actions.map((a,i) => `<li style="margin-bottom:4px;">${i+1}. ${_sanitize(a.confirm || a.action_type)}</li>`).join('');
        const card = document.createElement('div');
        card.className  = 'cm-action';
        card.dataset.id = id;
        card.innerHTML  = `
            <div class="cm-action-hdr">
                <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                ${data.actions.length} ações em lote
            </div>
            <div class="cm-action-body">Carrega em "Ver e confirmar" para veres a lista completa antes de executar.</div>
            <div class="cm-action-ftr">
                <button class="cm-btn-ok">👁 Ver e confirmar</button>
                <button class="cm-btn-no">✕ Cancelar</button>
            </div>`;
        card.querySelector('.cm-btn-ok').addEventListener('click', () => _openBatchModal(id, data.actions, card));
        card.querySelector('.cm-btn-no').addEventListener('click', () => _chatCancel(card));

        const box = document.getElementById('_chatMsgs');
        box.appendChild(card);
        requestAnimationFrame(() => { box.scrollTop = box.scrollHeight; });

        // abre automaticamente o modal para o user nao ter de carregar
        _openBatchModal(id, data.actions, card);
    }

    // ── MODAL grande para lote ──────────────────────────────────────────────
    function _openBatchModal(id, actions, sourceCard) {
        // se já existe um, remove
        const old = document.getElementById('_batchModal');
        if (old) old.remove();

        const items = actions.map((a, i) => `
            <li style="padding:10px 12px;border-bottom:1px solid var(--separator,#eee);
                       display:flex;align-items:center;gap:10px;font-size:14px;">
                <span style="flex-shrink:0;width:24px;height:24px;border-radius:50%;background:var(--accent,#6E56CF);
                             color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">
                    ${i+1}
                </span>
                <span style="color:var(--text-primary,#111);">${_sanitize(a.confirm || a.action_type)}</span>
            </li>`).join('');

        const m = document.createElement('div');
        m.id = '_batchModal';
        m.style.cssText = `position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.55);
            display:flex;align-items:center;justify-content:center;padding:24px;backdrop-filter:blur(3px);`;
        m.innerHTML = `
            <div style="background:var(--card-bg,#fff);border:1px solid var(--card-border,#ddd);
                        border-radius:16px;width:100%;max-width:560px;max-height:85vh;
                        display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                <div style="padding:18px 22px;border-bottom:1px solid var(--separator,#eee);
                            display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.6px;
                                    color:var(--text-secondary,#888);font-weight:600;">
                            Confirmação em lote
                        </div>
                        <div style="font-size:18px;font-weight:700;color:var(--text-primary,#111);margin-top:2px;">
                            ${actions.length} ações a executar
                        </div>
                    </div>
                    <button id="_batchClose" style="background:none;border:none;font-size:24px;cursor:pointer;
                            color:var(--text-secondary,#888);width:32px;height:32px;border-radius:8px;">×</button>
                </div>

                <ul id="_batchList" style="margin:0;padding:0;list-style:none;overflow-y:auto;flex:1;">${items}</ul>

                <div id="_batchPwdWrap" style="display:none;padding:14px 22px;border-top:1px solid var(--separator,#eee);
                                                background:var(--card-inset,#fafafa);">
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--text-secondary,#888);
                                  text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">
                        Confirma com a tua password
                    </label>
                    <input id="_batchPwd" type="password" placeholder="Password"
                        style="width:100%;padding:11px 14px;border-radius:10px;border:1px solid var(--card-border,#ddd);
                               background:var(--card-bg,#fff);color:var(--text-primary,#111);font-size:14px;outline:none;">
                    <div id="_batchErr" style="color:#C0392B;font-size:13px;margin-top:8px;display:none;"></div>
                </div>

                <div style="padding:14px 22px;border-top:1px solid var(--separator,#eee);display:flex;gap:10px;
                            background:var(--card-bg,#fff);">
                    <button id="_batchCancel" style="flex:1;padding:11px;border-radius:10px;
                            border:1px solid var(--card-border,#ddd);background:var(--card-inset,#f5f5f5);
                            color:var(--text-secondary,#888);font-size:14px;font-weight:600;cursor:pointer;">
                        Cancelar
                    </button>
                    <button id="_batchExec" style="flex:1.4;padding:11px;border-radius:10px;border:none;
                            background:var(--accent,#6E56CF);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">
                        ✓ Executar ${actions.length} ações
                    </button>
                </div>
            </div>`;
        document.body.appendChild(m);

        const close = () => m.remove();
        m.querySelector('#_batchClose').onclick  = close;
        m.querySelector('#_batchCancel').onclick = () => { close(); _chatCancel(sourceCard); };

        // 1º clique no Executar pede password; 2º clique submete
        m.querySelector('#_batchExec').onclick = async () => {
            const pwdWrap = m.querySelector('#_batchPwdWrap');
            const pwdInp  = m.querySelector('#_batchPwd');
            const errEl   = m.querySelector('#_batchErr');
            if (pwdWrap.style.display === 'none') {
                pwdWrap.style.display = 'block';
                pwdInp.focus();
                return;
            }
            if (!pwdInp.value) { pwdInp.focus(); return; }
            errEl.style.display = 'none';
            await _chatExecBatch(id, sourceCard, pwdInp.value, errEl, m);
        };
        // Enter no campo de password também submete
        m.addEventListener('keydown', e => {
            if (e.key === 'Enter') m.querySelector('#_batchExec').click();
            if (e.key === 'Escape') close();
        });
        // clicar fora fecha
        m.addEventListener('click', e => { if (e.target === m) close(); });
    }

    async function _chatExecBatch(id, sourceCard, password, errEl, modal) {
        const pending = _pendingActions[id];
        if (!pending || !pending.batch) return;
        const actions = pending.batch;
        let ok = 0, fail = 0;
        const failNames = [];

        // botão executar fica disabled durante o processamento
        const execBtn = modal.querySelector('#_batchExec');
        execBtn.disabled = true;
        execBtn.textContent = 'A executar...';

        for (let i = 0; i < actions.length; i++) {
            const a = actions[i];
            const fd = new FormData();
            fd.append('type', a.type || a.action_type);
            fd.append('params', JSON.stringify(a.params || {}));
            fd.append('password', password);
            try {
                const res = await fetch(_base + 'chat_execute.php', {method:'POST', body: fd});
                const data = await res.json();
                if (data.wrong_password) {
                    // password errada na 1a iteração — para tudo
                    errEl.textContent = '❌ Password incorreta. Tenta de novo.';
                    errEl.style.display = 'block';
                    modal.querySelector('#_batchPwd').value = '';
                    modal.querySelector('#_batchPwd').focus();
                    execBtn.disabled = false;
                    execBtn.textContent = `✓ Executar ${actions.length} ações`;
                    return;
                }
                if (data.success) ok++;
                else { fail++; failNames.push(a.confirm || a.action_type); }
            } catch (e) {
                fail++; failNames.push(a.confirm || a.action_type);
            }
            execBtn.textContent = `A executar... ${i+1}/${actions.length}`;
        }

        modal.remove();
        if (sourceCard) sourceCard.remove();
        delete _pendingActions[id];

        if (fail === 0) {
            _addMsg(`✅ ${ok} ações executadas com sucesso.`, 'cm-ok');
            _history.push({role:'assistant', content: `Executei ${ok} ações com sucesso.`});
        } else if (ok === 0) {
            _addMsg(`❌ Nenhuma ação foi executada. Falharam: ${failNames.slice(0,3).join(', ')}${failNames.length>3?'...':''}`, 'cm-bot');
            _history.push({role:'assistant', content: `Todas as ${fail} ações falharam.`});
        } else {
            _addMsg(`⚠ ${ok} executadas, ${fail} falharam: ${failNames.slice(0,3).join(', ')}${failNames.length>3?'...':''}`, 'cm-bot');
            _history.push({role:'assistant', content: `Executei ${ok}, falharam ${fail}.`});
        }
        // NÃO fazer reload da página - o auto-refresh polling (3s) já apanha os novos dados
        // e assim o modo Execução mantém-se ativo para a próxima ação
    }

    // Init: carrega histórico e estado (preserva o modo entre navegações)
    try {
        const savedMode = localStorage.getItem(LS_MODE);
        _mode = (savedMode === 'exec') ? 'exec' : 'view';
    } catch(e) { _mode = 'view'; }
    _applyMode();
    _load();
    try {
        if (localStorage.getItem(LS_OPEN) === '1') {
            const win = document.getElementById('_chatWin');
            win.style.display = 'flex';
            win.style.flexDirection = 'column';
        }
    } catch(e) {}

})();
</script>
