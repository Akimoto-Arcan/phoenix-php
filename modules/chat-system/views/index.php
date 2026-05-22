<?php
require_once __DIR__ . '/../../../bootstrap.php';
\Phoenix\Auth::require('/login');
$username = $_SESSION['username'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['phoenix_theme'] ?? 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat — Phoenix ERP</title>
    <?= \Phoenix\CSRF::meta() ?>
    <link rel="stylesheet" href="/public/css/phoenix.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <script>(function(){var t=localStorage.getItem('phoenix-theme')||'dark';if(t==='system')t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t)})()</script>
    <style>
    .chat-layout { display: grid; grid-template-columns: 280px 1fr; height: calc(100vh - 80px); }
    .chat-channels { border-right: 1px solid var(--border); overflow-y: auto; padding: 16px; }
    .chat-messages { display: flex; flex-direction: column; }
    .chat-thread { flex: 1; overflow-y: auto; padding: 16px; }
    .chat-input { padding: 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; }
    .chat-input input { flex: 1; }
    .channel-item { padding: 10px 12px; border-radius: var(--radius-sm); cursor: pointer; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; color: var(--text-secondary); }
    .channel-item:hover { background: var(--bg-card); }
    .channel-item.active { background: var(--accent-glow); color: var(--accent); }
    .message { margin-bottom: 16px; }
    .message .msg-user { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
    .message .msg-time { font-size: 11px; color: var(--text-muted); margin-left: 8px; }
    .message .msg-text { font-size: 14px; color: var(--text-secondary); line-height: 1.5; }
    @media (max-width: 768px) { .chat-layout { grid-template-columns: 1fr; } .chat-channels { display: none; } }
    </style>
</head>
<body>
<div style="display:flex;height:100vh">
    <div style="width:60px;background:var(--bg-sidebar);display:flex;flex-direction:column;align-items:center;padding:16px 0;gap:16px;border-right:1px solid var(--border)">
        <a href="/dashboard" style="font-size:24px;text-decoration:none" title="Back to Dashboard">🔥</a>
        <div style="width:36px;height:36px;border-radius:8px;background:var(--accent-glow);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:18px" title="Chat">💬</div>
    </div>
    <div style="flex:1;display:flex;flex-direction:column">
        <header class="topbar"><h2>Chat</h2></header>
        <div class="chat-layout">
            <div class="chat-channels">
                <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px">Channels</div>
                <div id="channelList"><div style="color:var(--text-muted);font-size:13px">Loading...</div></div>
            </div>
            <div class="chat-messages">
                <div class="chat-thread" id="chatThread"><div style="text-align:center;color:var(--text-muted);padding:40px">Select a channel to start chatting</div></div>
                <div class="chat-input">
                    <input type="text" class="form-control" id="msgInput" placeholder="Type a message..." disabled>
                    <button class="btn btn-primary" style="width:auto" id="sendBtn" disabled>Send</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const BASE = '/modules/chat-system/api/routes.php';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const currentUser = '<?= htmlspecialchars($username) ?>';
let currentChannel = null;
let pollTimer = null;

async function api(path, opts = {}) {
    const headers = { 'X-CSRF-Token': csrf, ...opts.headers };
    if (opts.body && typeof opts.body === 'object') { headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(opts.body); }
    const r = await fetch(BASE + '?path=' + encodeURIComponent(path), { ...opts, headers });
    return r.json();
}

async function loadChannels() {
    const data = await api('channels').catch(() => ({ channels: [] }));
    const list = document.getElementById('channelList');
    const channels = data.channels || [];
    if (channels.length === 0) {
        list.innerHTML = '<div style="color:var(--text-muted);font-size:13px">No channels yet</div>';
        return;
    }
    list.innerHTML = channels.map(c =>
        `<div class="channel-item" data-id="${c.id}" onclick="selectChannel(${c.id}, '${c.name.replace(/'/g,"\\'")}')">
            <span>#</span> ${c.name}
        </div>`
    ).join('');
}

window.selectChannel = async function(id, name) {
    currentChannel = id;
    document.querySelectorAll('.channel-item').forEach(ci => ci.classList.toggle('active', ci.dataset.id == id));
    document.getElementById('msgInput').disabled = false;
    document.getElementById('sendBtn').disabled = false;
    await loadMessages();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 5000);
};

async function loadMessages() {
    if (!currentChannel) return;
    const data = await api('messages?channel_id=' + currentChannel).catch(() => ({ messages: [] }));
    const thread = document.getElementById('chatThread');
    const msgs = (data.messages || []).reverse();
    if (msgs.length === 0) {
        thread.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px">No messages yet. Start the conversation!</div>';
        return;
    }
    thread.innerHTML = msgs.map(m => `
        <div class="message">
            <div><span class="msg-user" style="color:${m.username === currentUser ? 'var(--accent)' : 'var(--text-primary)'}">${esc(m.username)}</span><span class="msg-time">${esc(m.created_at)}</span></div>
            <div class="msg-text">${esc(m.message)}</div>
        </div>
    `).join('');
    thread.scrollTop = thread.scrollHeight;
}

document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

async function sendMessage() {
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    if (!msg || !currentChannel) return;
    input.value = '';
    await api('messages', { method: 'POST', body: { channel_id: currentChannel, message: msg } }).catch(() => {});
    await loadMessages();
}

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

loadChannels();
</script>
</body>
</html>
