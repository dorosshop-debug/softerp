<?php
$layout = 'tenant';
$title = 'Asistente IA - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Asistente Virtual Seri';
$tenantName = $tenantName ?? 'Mi Empresa'; $userName = $userName ?? 'Usuario';
$aiPersonality = $aiPersonality ?? [];
$aiName = $aiPersonality['name'] ?? 'Seri';
$aiWelcome = $aiPersonality['welcome'] ?? 'Hola, soy Seri. Puedo ayudarte con ventas, inventario, clientes y caja.';
$aiSuggestions = $aiPersonality['suggestions'] ?? [
    '¿Cómo van las ventas de hoy?',
    '¿Qué productos tienen stock bajo?',
    'Resumen de clientes recientes',
    '¿Cómo está la caja?',
];
?>
<style>
.ai-chat-container { display: flex; flex-direction: column; height: calc(100vh - 200px); min-height: 500px; }
.ai-messages { flex: 1; overflow-y: auto; padding: 20px; background: var(--bg-input); border-radius: 16px; margin-bottom: 15px; border: 1px solid var(--color-border); }
.ai-msg { display: flex; margin-bottom: 15px; animation: fadeIn 0.3s ease; }
.ai-msg.user { justify-content: flex-end; }
.ai-msg .bubble { max-width: 80%; padding: 12px 18px; border-radius: 18px; font-size: 14px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.ai-msg.assistant .bubble { background: var(--bg-card); border: 1px solid var(--color-border); color: var(--color-text-primary); border-bottom-left-radius: 4px; }
.ai-msg.user .bubble { background: linear-gradient(135deg, #0D7C4A, #10B981); color: #fff; border-bottom-right-radius: 4px; }
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
.ai-suggestions button:hover { border-color: var(--color-primary); color: var(--color-primary); background: rgba(13,124,74,0.05); }
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

<script>
function askSuggestion(text) {
    document.getElementById('aiInput').value = text;
    sendMessage();
}

function sendMessage() {
    var input = document.getElementById('aiInput');
    var msg = input.value.trim();
    if (!msg) return;
    
    var container = document.getElementById('aiMessages');
    var time = new Date().toLocaleTimeString('es', {hour:'2-digit',minute:'2-digit'});
    
    // Agregar mensaje del usuario
    container.innerHTML += '<div class="ai-msg user"><div class="bubble">' + esc(msg) + '<div class="time">' + time + '</div></div></div>';
    input.value = '';
    container.scrollTop = container.scrollHeight;
    
    // Mostrar typing con animación de puntos
    container.innerHTML += '<div class="ai-typing" id="typing">Seri está pensando<div class="dots"><span></span><span></span><span></span></div></div>';
    container.scrollTop = container.scrollHeight;
    
    fetch('<?php echo $viewInstance->route('app/ia'); ?>/chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body: 'message=' + encodeURIComponent(msg) + '&csrf_token=<?php echo \SoftNova\Core\csrf_token(); ?>'
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        var typing = document.getElementById('typing');
        if (typing) typing.remove();
        var reply = d.reply || d.message || 'No entendí. ¿Podrías reformular tu pregunta?';
        container.innerHTML += '<div class="ai-msg assistant"><div class="bubble">' + esc(reply) + '<div class="time">' + time + '</div></div></div>';
        container.scrollTop = container.scrollHeight;
    })
    .catch(function() {
        var typing = document.getElementById('typing');
        if (typing) typing.remove();
        container.innerHTML += '<div class="ai-msg assistant"><div class="bubble">❌ Error de conexión. Intenta de nuevo.<div class="time">' + time + '</div></div></div>';
        container.scrollTop = container.scrollHeight;
    });
}

function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
