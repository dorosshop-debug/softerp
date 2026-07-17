<?php
$layout = 'tenant';
$title = 'Asistente IA - ' . ($tenantName ?? 'Sistema');
$pageTitle = 'Asistente Virtual';
$tenantName = $tenantName ?? 'Mi Empresa'; $userName = $userName ?? 'Usuario';
?>
<style>
.ai-chat-container { display: flex; flex-direction: column; height: calc(100vh - 200px); min-height: 500px; }
.ai-messages { flex: 1; overflow-y: auto; padding: 20px; background: var(--bg-input); border-radius: 16px; margin-bottom: 15px; border: 1px solid var(--color-border); }
.ai-msg { display: flex; margin-bottom: 15px; }
.ai-msg.user { justify-content: flex-end; }
.ai-msg .bubble { max-width: 75%; padding: 12px 18px; border-radius: 18px; font-size: 14px; line-height: 1.5; white-space: pre-wrap; }
.ai-msg.assistant .bubble { background: var(--bg-card); border: 1px solid var(--color-border); color: var(--color-text-primary); border-bottom-left-radius: 4px; }
.ai-msg.user .bubble { background: linear-gradient(135deg, #0D7C4A, #10B981); color: #fff; border-bottom-right-radius: 4px; }
.ai-msg .bubble .time { font-size: 10px; opacity: 0.6; margin-top: 6px; }
.ai-input-area { display: flex; gap: 10px; }
.ai-input-area input { flex: 1; }
.ai-typing { color: var(--color-text-secondary); font-style: italic; padding: 10px; }
</style>

<div class="card neumorphic" style="margin-bottom:15px;">
    <div class="card-header">
        <h3>🤖 Asistente Virtual EVA</h3>
        <span style="font-size:12px;color:var(--color-text-secondary);">Potenciado por IA</span>
    </div>
</div>

<div class="ai-chat-container">
    <div class="ai-messages" id="aiMessages">
        <div class="ai-msg assistant">
            <div class="bubble">
                ¡Hola! 👋 Soy el asistente virtual de EVA ERP. Puedo ayudarte con:
                <br><br>📊 Consultar tus productos más vendidos
                <br>📦 Revisar el estado del inventario
                <br>💰 Ver tus ventas del día
                <br>📸 Sugerencias para publicar en redes sociales
                <br><br>¿En qué puedo ayudarte hoy?
                <div class="time"><?php echo date('H:i'); ?></div>
            </div>
        </div>
    </div>
    <div class="ai-input-area">
        <input type="text" id="aiInput" class="form-control" placeholder="Escribe tu pregunta..." onkeypress="if(event.key==='Enter')sendMessage()">
        <button onclick="sendMessage()" class="btn btn-primary">📨 Enviar</button>
    </div>
</div>

<script>
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
    
    // Mostrar typing
    container.innerHTML += '<div class="ai-typing" id="typing">🤖 Escribiendo...</div>';
    container.scrollTop = container.scrollHeight;
    
    fetch('<?php echo $viewInstance->route('app/ia'); ?>/chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
        body: 'message=' + encodeURIComponent(msg) + '&csrf_token=<?php echo \SoftNova\Core\csrf_token(); ?>'
    })
    .then(r => r.json())
    .then(d => {
        document.getElementById('typing')?.remove();
        container.innerHTML += '<div class="ai-msg assistant"><div class="bubble">' + esc(d.reply || 'No entendí.') + '<div class="time">' + time + '</div></div></div>';
        container.scrollTop = container.scrollHeight;
    });
}
function esc(s) { return (s||'').replace(/</g,'<').replace(/>/g,'>'); }
</script>
