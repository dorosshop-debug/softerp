<?php
$layout = 'tenant';
$title = 'Asistente IA - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Asistente Virtual Seri';
$tenantName = $tenantName ?? 'Mi Empresa'; $userName = $userName ?? 'Usuario';
$aiPersonality = $aiPersonality ?? [];
$aiName = $aiPersonality['name'] ?? 'Seri';
$aiWelcome = $aiPersonality['welcome'] ?? 'Hola, soy Seri. Puedo ayudarte con ventas, inventario, clientes y caja.';
$conversations = $conversations ?? [];
$aiSuggestions = $aiPersonality['suggestions'] ?? [
    '¿Cómo van las ventas de hoy?',
    '¿Qué productos tienen stock bajo?',
    'Resumen de clientes recientes',
    '¿Cómo está la caja?',
];
?>
<style>
.ai-layout { display: grid; grid-template-columns: 260px 1fr; gap: 15px; height: calc(100vh - 200px); min-height: 500px; }
.ai-sidebar { background: var(--bg-card); border: 1px solid var(--color-border); border-radius: 16px; display: flex; flex-direction: column; overflow: hidden; }
.ai-sidebar-header { padding: 14px; border-bottom: 1px solid var(--color-border); }
.ai-conv-list { flex: 1; overflow-y: auto; padding: 8px; }
.ai-conv-item { display: flex; align-items: center; gap: 6px; padding: 10px 12px; border-radius: 10px; cursor: pointer; font-size: 13px; color: var(--color-text-primary); transition: background 0.15s; }
.ai-conv-item:hover { background: var(--bg-input); }
.ai-conv-item.active { background: var(--bg-input); border-left: 3px solid var(--color-primary); }
.ai-conv-item .title { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ai-conv-item .del { opacity: 0; border: none; background: none; color: #DC2626; cursor: pointer; font-size: 15px; }
.ai-conv-item:hover .del { opacity: 1; }
.ai-conv-empty { padding: 20px; text-align: center; font-size: 12px; color: var(--color-text-secondary); }
.ai-chat-container { display: flex; flex-direction: column; height: 100%; min-width: 0; }
.ai-messages { flex: 1; overflow-y: auto; padding: 20px; background: var(--bg-input); border-radius: 16px; margin-bottom: 15px; border: 1px solid var(--color-border); }
.ai-msg { display: flex; margin-bottom: 15px; animation: fadeIn 0.3s ease; }
.ai-msg.user { justify-content: flex-end; }
.ai-msg .bubble { max-width: 80%; padding: 12px 18px; border-radius: 18px; font-size: 14px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.ai-msg.assistant .bubble { background: var(--bg-card); border: 1px solid var(--color-border); color: var(--color-text-primary); border-bottom-left-radius: 4px; }
.ai-msg.user .bubble { background: linear-gradient(135deg, var(--color-primary), var(--bg-btn-primary-hover)); color: var(--color-btn-primary-text, #fff); border-bottom-right-radius: 4px; }
.ai-msg .bubble .time { font-size: 10px; opacity: 0.6; margin-top: 6px; }
.ai-input-area { display: flex; gap: 10px; }
.ai-input-area input { flex: 1; }
.ai-typing { color: var(--color-text-secondary); font-style: italic; padding: 10px; display: flex; align-items: center; gap: 8px; }
.ai-typing .dots { display: flex; gap: 3px; }
.ai-typing .dots span { width: 6px; height: 6px; border-radius: 50%; background: var(--color-text-secondary); animation: bounce 1.4s infinite ease-in-out; }
.ai-typing .dots span:nth-child(2) { animation-delay: 0.2s; }
.ai-typing .dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce { 0%,80%,100% { transform: translateY(0); } 40% { transform: translateY(-6px); } }
.ai-suggestions { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.ai-suggestions button { font-size: 12px; padding: 6px 14px; border-radius: 20px; border: 1px solid var(--color-border); background: var(--bg-card); color: var(--color-text-primary); cursor: pointer; transition: all 0.2s; white-space: nowrap; }
.ai-suggestions button:hover { border-color: var(--color-primary); color: var(--color-primary); }
@media (max-width: 800px) { .ai-layout { grid-template-columns: 1fr; height: auto; } .ai-sidebar { max-height: 200px; } .ai-chat-container { height: calc(100vh - 320px); } }
</style>

<div class="card neumorphic" style="margin-bottom:15px;">
    <div class="card-header">
        <h3>Asistente Virtual <?php echo htmlspecialchars($aiName); ?></h3>
        <span style="font-size:12px;color:var(--color-text-secondary);">
            <?php if (!empty($aiConfigured)): ?>
                Conectado a OpenRouter · <?php echo htmlspecialchars($aiModel ?? 'NVIDIA Nemotron 3 Ultra'); ?>
            <?php else: ?>
                Modo local (configura OPENROUTER_API_KEY en .env para Nemotron)
            <?php endif; ?>
        </span>
    </div>
</div>

<div class="ai-layout">
    <aside class="ai-sidebar">
        <div class="ai-sidebar-header">
            <button onclick="newConversation()" class="btn btn-primary" style="width:100%;">+ Nueva conversación</button>
        </div>
        <div class="ai-conv-list" id="aiConvList">
            <?php if (empty($conversations)): ?>
                <div class="ai-conv-empty" id="aiConvEmpty">Aún no tienes conversaciones guardadas.</div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <div class="ai-conv-item" data-id="<?php echo (int)$conv['id']; ?>" onclick="loadConversation(<?php echo (int)$conv['id']; ?>)">
                        <span class="title"><?php echo htmlspecialchars($conv['title']); ?></span>
                        <button class="del" title="Eliminar" onclick="event.stopPropagation();deleteConversation(<?php echo (int)$conv['id']; ?>)">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <div class="ai-chat-container">
        <div class="ai-messages" id="aiMessages">
            <div class="ai-msg assistant">
                <div class="bubble">
                    <?php echo nl2br(htmlspecialchars($aiWelcome)); ?>
                    <div class="time"><?php echo date('H:i'); ?></div>
                </div>
            </div>
        </div>

        <div class="ai-suggestions" id="aiSuggestions">
            <?php foreach ($aiSuggestions as $suggestion): ?>
                <button type="button" onclick="askSuggestion(<?php echo htmlspecialchars(json_encode($suggestion), ENT_QUOTES, 'UTF-8'); ?>)">
                    <?php echo htmlspecialchars($suggestion); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="ai-input-area">
            <input type="text" id="aiInput" class="form-control" placeholder="Escribe tu pregunta aquí..." onkeypress="if(event.key==='Enter')sendMessage()" autofocus>
            <button onclick="sendMessage()" class="btn btn-primary">Enviar</button>
        </div>
    </div>
</div>

<script>
var aiCsrf = '<?php echo \SoftNova\Core\csrf_token(); ?>';
var aiBase = '<?php echo $viewInstance->route('app/ia'); ?>';
var aiWelcomeHtml = <?php echo json_encode(nl2br(htmlspecialchars($aiWelcome))); ?>;
var currentConversationId = 0;

function askSuggestion(text) {
    document.getElementById('aiInput').value = text;
    sendMessage();
}

function nowTime() {
    return new Date().toLocaleTimeString('es', {hour:'2-digit',minute:'2-digit'});
}

function appendBubble(role, text) {
    var container = document.getElementById('aiMessages');
    container.innerHTML += '<div class="ai-msg ' + role + '"><div class="bubble">' + esc(text) + '<div class="time">' + nowTime() + '</div></div></div>';
    container.scrollTop = container.scrollHeight;
}

function newConversation() {
    currentConversationId = 0;
    var container = document.getElementById('aiMessages');
    container.innerHTML = '<div class="ai-msg assistant"><div class="bubble">' + aiWelcomeHtml + '<div class="time">' + nowTime() + '</div></div></div>';
    document.querySelectorAll('.ai-conv-item').forEach(function(el){ el.classList.remove('active'); });
    document.getElementById('aiInput').focus();
}

function markActive(id) {
    document.querySelectorAll('.ai-conv-item').forEach(function(el){
        el.classList.toggle('active', parseInt(el.dataset.id, 10) === id);
    });
}

function loadConversation(id) {
    fetch(aiBase + '/history?action=load&id=' + id, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.success) return;
            currentConversationId = d.conversation_id;
            var container = document.getElementById('aiMessages');
            container.innerHTML = '';
            (d.messages || []).forEach(function(m){
                appendBubble(m.role === 'assistant' ? 'assistant' : 'user', m.content);
            });
            markActive(id);
        });
}

function deleteConversation(id) {
    if (!confirm('¿Eliminar esta conversación?')) return;
    fetch(aiBase + '/chat?action=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body: 'conversation_id=' + id + '&csrf_token=' + encodeURIComponent(aiCsrf)
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            var item = document.querySelector('.ai-conv-item[data-id="' + id + '"]');
            if (item) item.remove();
            if (currentConversationId === id) newConversation();
        }
    });
}

function upsertConversationInList(id, title) {
    var list = document.getElementById('aiConvList');
    var empty = document.getElementById('aiConvEmpty');
    if (empty) empty.remove();
    var item = document.querySelector('.ai-conv-item[data-id="' + id + '"]');
    if (!item) {
        item = document.createElement('div');
        item.className = 'ai-conv-item';
        item.dataset.id = id;
        item.setAttribute('onclick', 'loadConversation(' + id + ')');
        item.innerHTML = '<span class="title"></span><button class="del" title="Eliminar" onclick="event.stopPropagation();deleteConversation(' + id + ')">&times;</button>';
        list.insertBefore(item, list.firstChild);
    }
    if (title) item.querySelector('.title').textContent = title;
    markActive(id);
}

function sendMessage() {
    var input = document.getElementById('aiInput');
    var msg = input.value.trim();
    if (!msg) return;

    var isNew = currentConversationId === 0;
    appendBubble('user', msg);
    input.value = '';

    var container = document.getElementById('aiMessages');
    container.innerHTML += '<div class="ai-typing" id="typing">Seri está pensando<div class="dots"><span></span><span></span><span></span></div></div>';
    container.scrollTop = container.scrollHeight;

    fetch(aiBase + '/chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body: 'message=' + encodeURIComponent(msg) + '&conversation_id=' + currentConversationId + '&csrf_token=' + encodeURIComponent(aiCsrf)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        var typing = document.getElementById('typing');
        if (typing) typing.remove();
        var reply = d.reply || d.message || 'No entendí. ¿Podrías reformular tu pregunta?';
        appendBubble('assistant', reply);
        if (d.conversation_id) {
            currentConversationId = d.conversation_id;
            upsertConversationInList(d.conversation_id, isNew ? msg.substring(0, 60) : null);
        }
    })
    .catch(function() {
        var typing = document.getElementById('typing');
        if (typing) typing.remove();
        appendBubble('assistant', '❌ Error de conexión. Intenta de nuevo.');
    });
}

function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
