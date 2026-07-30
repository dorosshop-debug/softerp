<?php
/**
 * Gestión de usuarios no administrativos (rol User / User POS)
 * Solo visible para admin del tenant.
 */
$teamUsers = $teamUsers ?? [];
$assignableModules = $assignableModules ?? \SoftNova\Core\TenantMiddleware::assignableModules();
$planModules = $planModules ?? [];
$userLimit = $userLimit ?? ['current' => 0, 'max' => 0];
$configBase = $viewInstance->route('app/configuracion');
?>
<div class="card neumorphic" style="grid-column:1/-1;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h3>👥 Usuarios del sistema</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('createTeamUserModal').style.display='flex'">+ Nuevo usuario</button>
    </div>
    <div class="card-body">
        <p style="font-size:13px;color:var(--color-text-secondary);margin:0 0 14px;">
            Cree usuarios <strong>User</strong> (permisos por módulo) o <strong>User POS</strong> (solo Caja-POS para vender).
            No se pueden crear administradores desde aquí.
            <?php if ((int)$userLimit['max'] > 0): ?>
                · Cupo del plan: <strong><?php echo (int)$userLimit['current']; ?>/<?php echo (int)$userLimit['max']; ?></strong>
            <?php endif; ?>
        </p>
        <?php if (empty($teamUsers)): ?>
            <p style="text-align:center;color:var(--color-text-secondary);padding:16px;">Aún no hay usuarios adicionales</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teamUsers as $u): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($u['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars(\SoftNova\Core\TenantMiddleware::roleLabel($u['role'] ?? '')); ?></span></td>
                            <td>
                                <?php if (($u['status'] ?? '') === 'active'): ?>
                                    <span class="badge badge-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—'; ?></td>
                            <td class="table-actions">
                                <button type="button" class="btn btn-sm btn-secondary"
                                    onclick='openEditTeamUser(<?php echo json_encode([
                                        'id' => (int)$u['id'],
                                        'name' => $u['name'],
                                        'email' => $u['email'],
                                        'role' => $u['role'],
                                        'status' => $u['status'],
                                        'permissions' => json_decode($u['permissions'] ?? '{}', true) ?: [],
                                    ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG); ?>)'>Editar</button>
                                <?php if ((int)$u['id'] !== (int)($_SESSION['tenant_master_user_id'] ?? 0)): ?>
                                <form method="POST" action="<?php echo $configBase; ?>?action=toggleTeamUser" data-ajax="true" style="display:inline;">
                                    <?php echo \SoftNova\Core\csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo ($u['status'] ?? '') === 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                        data-confirm="<?php echo ($u['status'] ?? '') === 'active' ? '¿Desactivar este usuario?' : '¿Activar este usuario?'; ?>">
                                        <?php echo ($u['status'] ?? '') === 'active' ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="createTeamUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:640px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3>Nuevo usuario</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('createTeamUserModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $configBase; ?>?action=createTeamUser" data-ajax="true" id="createTeamUserForm">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group"><label>Nombre *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
                    <div class="form-group"><label>Contraseña *</label><input type="password" name="password" class="form-control" minlength="8" required></div>
                    <div class="form-group">
                        <label>Rol *</label>
                        <select name="role" id="createUserRole" class="form-control" onchange="toggleCreatePerms(this.value)">
                            <option value="user">User (módulos personalizados)</option>
                            <option value="auxiliar">User POS (solo Caja-POS)</option>
                        </select>
                    </div>
                </div>
                <div id="createUserPermsWrap">
                    <label style="font-weight:600;margin-top:8px;display:block;">Módulos y permisos</label>
                    <p style="font-size:12px;color:var(--color-text-secondary);margin:4px 0 8px;">Marque los módulos que podrá usar. “Editar” incluye crear/editar/eliminar/exportar según el módulo.</p>
                    <div class="config-users-perms">
                        <?php foreach ($assignableModules as $modKey => $modLabel):
                            $allowedByPlan = empty($planModules) || in_array($modKey, $planModules, true);
                            if (!$allowedByPlan) continue;
                        ?>
                            <label>
                                <input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][view]" value="1" class="perm-view" data-mod="<?php echo htmlspecialchars($modKey); ?>">
                                <span>
                                    <strong><?php echo htmlspecialchars($modLabel); ?></strong>
                                    <span class="perm-actions">
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][create]" value="1"> Crear</span>
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][edit]" value="1"> Editar</span>
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][delete]" value="1"> Eliminar</span>
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][export]" value="1"> Exportar</span>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p id="createPosHint" style="display:none;font-size:12px;color:var(--color-text-secondary);margin-top:10px;">
                    User POS solo accede a Caja-POS para vender. No verá tarjetas financieras ni historial de cierres. Sí recibirá avisos del administrador y ventas canceladas.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createTeamUserModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear usuario</button>
            </div>
        </form>
    </div>
</div>

<div id="editTeamUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-content neumorphic" style="max-width:640px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3>Editar usuario</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('editTeamUserModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?php echo $configBase; ?>?action=updateTeamUser" data-ajax="true" id="editTeamUserForm">
            <?php echo \SoftNova\Core\csrf_field(); ?>
            <input type="hidden" name="id" id="editUserId">
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group"><label>Nombre *</label><input type="text" name="name" id="editUserName" class="form-control" required></div>
                    <div class="form-group"><label>Email *</label><input type="email" name="email" id="editUserEmail" class="form-control" required></div>
                    <div class="form-group"><label>Nueva contraseña</label><input type="password" name="password" class="form-control" minlength="8" placeholder="Dejar vacío para no cambiar"></div>
                    <div class="form-group">
                        <label>Rol *</label>
                        <select name="role" id="editUserRole" class="form-control" onchange="toggleEditPerms(this.value)">
                            <option value="user">User</option>
                            <option value="auxiliar">User POS</option>
                        </select>
                    </div>
                </div>
                <div id="editUserPermsWrap">
                    <label style="font-weight:600;margin-top:8px;display:block;">Módulos y permisos</label>
                    <div class="config-users-perms" id="editUserPerms">
                        <?php foreach ($assignableModules as $modKey => $modLabel):
                            $allowedByPlan = empty($planModules) || in_array($modKey, $planModules, true);
                            if (!$allowedByPlan) continue;
                        ?>
                            <label>
                                <input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][view]" value="1" data-mod="<?php echo htmlspecialchars($modKey); ?>" class="edit-perm-view">
                                <span>
                                    <strong><?php echo htmlspecialchars($modLabel); ?></strong>
                                    <span class="perm-actions">
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][create]" value="1" class="edit-perm-create" data-mod="<?php echo htmlspecialchars($modKey); ?>"> Crear</span>
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][edit]" value="1" class="edit-perm-edit" data-mod="<?php echo htmlspecialchars($modKey); ?>"> Editar</span>
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][delete]" value="1" class="edit-perm-delete" data-mod="<?php echo htmlspecialchars($modKey); ?>"> Eliminar</span>
                                        <span><input type="checkbox" name="permissions[<?php echo htmlspecialchars($modKey); ?>][export]" value="1" class="edit-perm-export" data-mod="<?php echo htmlspecialchars($modKey); ?>"> Exportar</span>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editTeamUserModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCreatePerms(role) {
    var wrap = document.getElementById('createUserPermsWrap');
    var hint = document.getElementById('createPosHint');
    var isPos = role === 'auxiliar';
    if (wrap) wrap.style.display = isPos ? 'none' : '';
    if (hint) hint.style.display = isPos ? '' : 'none';
}
function toggleEditPerms(role) {
    var wrap = document.getElementById('editUserPermsWrap');
    if (wrap) wrap.style.display = role === 'auxiliar' ? 'none' : '';
}
function openEditTeamUser(u) {
    document.getElementById('editUserId').value = u.id;
    document.getElementById('editUserName').value = u.name || '';
    document.getElementById('editUserEmail').value = u.email || '';
    document.getElementById('editUserRole').value = (u.role === 'auxiliar' || u.role === 'pos') ? 'auxiliar' : 'user';
    toggleEditPerms(document.getElementById('editUserRole').value);
    var perms = u.permissions || {};
    document.querySelectorAll('#editUserPerms input[type=checkbox]').forEach(function(cb) {
        cb.checked = false;
        var name = cb.getAttribute('name') || '';
        var m = name.match(/permissions\[([^\]]+)\]\[([^\]]+)\]/);
        if (!m) return;
        var mod = m[1], act = m[2];
        if (perms[mod] && perms[mod][act]) cb.checked = true;
    });
    document.getElementById('editTeamUserModal').style.display = 'flex';
}
document.addEventListener('DOMContentLoaded', function() {
    toggleCreatePerms(document.getElementById('createUserRole') ? document.getElementById('createUserRole').value : 'user');
});
</script>
