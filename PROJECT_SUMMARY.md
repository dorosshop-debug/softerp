# Seri ERP — Resumen para migración a Cursor / nuevo agente

## Datos del proyecto

| Campo | Valor |
|-------|-------|
| **Nombre** | Seri ERP |
| **Tipo** | SaaS ERP multi-tenant |
| **Lenguaje** | PHP 8.2 vanilla (sin framework) |
| **BD** | MariaDB — 1 BD maestra + 1 BD por tenant |
| **Frontend** | CSS neumórfico + JavaScript vanilla (`'use strict'`) |
| **Repo** | https://github.com/dorosshop-debug/softerp |
| **Ubicación local** | `c:/xampp/htdocs/SoftNova/` |
| **URL local** | `http://localhost/SoftNova/public/` |
| **BD maestra** | `softnova_master` |

---

## Arquitectura

```
SoftNova/
├── core/           # Framework base (Router, Controller, View, Database, etc.)
├── app/
│   ├── Controllers/  # Controladores (SuperAdmin + Tenant)
│   ├── Models/       # Modelos (License, Plan, Tenant, Ticket)
│   ├── Services/     # AuditService, PdfService (Dompdf)
│   ├── Middleware/   # (vacío — middleware en core/)
│   └── Views/
│       ├── layouts/     # auth.php, main.php, superadmin.php, tenant.php
│       ├── partials/    # header.php, sidebar.php, tenant_sidebar.php, footer.php
│       ├── superadmin/  # dashboard, tenants, plans, licencias, tickets, audits, settings
│       └── tenant/      # dashboard, ventas, caja, inventario, clientes, proveedores,
│                        # cotizaciones, reportes, config, ai, tickets, ticket_chat, login, placeholder
├── config/         # app.php, database.php, security.php, constants.php
├── database/       # migrations/ y migrate_existing.php
├── public/         # index.php, assets/ (CSS, JS, images), uploads/avatars/
├── storage/        # cache, logs, sessions
└── vendor/         # Composer: dompdf/dompdf ^3.1
```

### Namespaces (PSR-4)
```json
"SoftNova\\": "app/",
"SoftNova\\Core\\": "core/",
"SoftNova\\Config\\": "config/"
```

### MVC sin framework
- `core/Router.php` — enrutador
- `core/Controller.php` — clase base abstracta con `view()`, `json()`, `respond()`, `validateCsrfOrFail()`
- `core/View.php` — renderizador con layouts y partials
- `core/Database.php` — singleton PDO para BD maestra
- `core/TenantDatabase.php` — conexiones dinámicas a BDs de tenants
- `core/TenantMiddleware.php` — auth, getDb(), hasModule(), canAccess(), canDo(), authorize()
- `core/Security.php` — CSRF, hash/verify password
- `core/helpers.php` — `base_url()`, `route()`, `asset()`, `csrf_field()`, `redirect()`

### CSS — Diseño neumórfico
- Variables: `--color-primary: #0D7C4A` (verde), `--color-secondary`, `--bg-card`, `--color-border`
- Clases: `.card.neumorphic`, `.btn.neumorphic-btn`, `.modal-overlay`, `.modal-content.neumorphic`, `.stats-grid`
- Dark mode: `body.dark-mode`
- Badges: `.badge-success`, `.badge-danger`, `.badge-info`, `.badge-warning`
- Notificaciones: `.notification-bell`, `.notification-badge`, animaciones `pulse-badge` / `pulse-urgent`
- Header superadmin: fondo blanco (`var(--bg-card)`)

### JS — app.js global
- `esc(s)` — escapar HTML
- `showAlert(message, type)` — toast notifications
- `showConfirmModal(message, callback)` — modales de confirmación
- `showLoadingOverlay()` / `hideLoadingOverlay()` — loading spinner
- `clearSearch()` — limpiar búsqueda global
- `filterCustomers(selectId, searchId)` — filtro de clientes en dropdowns
- Módulos: `caja.js`, `ventas.js`, `inventario.js`, `clientes.js`, `proveedores.js`, `cotizaciones.js`, `config.js`, `ai.js`, `tickets.js`

---

## Módulos implementados

### Panel Super Admin (`/superadmin`)
| Ruta | Vista | Descripción |
|------|-------|-------------|
| `/superadmin` | `superadmin/dashboard.php` | KPIs, actividad reciente, tenants por vencer |
| `/superadmin/tenants` | `superadmin/tenants.php` | CRUD tenants |
| `/superadmin/plans` | `superadmin/plans.php` | CRUD planes (mensual, semestral, anual) |
| `/superadmin/licencias` | `superadmin/licencias.php` | Ventas de licencias, pagos, cliente inline |
| `/superadmin/tickets` | `superadmin/tickets.php` | Tickets de soporte |
| `/superadmin/audits` | `superadmin/audits.php` | Auditoría |
| `/superadmin/settings` | `superadmin/settings.php` | Configuración |

### Panel Tenant (`/app/*`)
| Ruta | Controlador | Vista | JS |
|------|-----------|------|-----|
| `/app/dashboard` | `TenantDashboardController` | `tenant/dashboard.php` | - |
| `/app/ventas` | `TenantVentasController` | `tenant/ventas.php` | `ventas.js` |
| `/app/caja` | `TenantCajaController` | `tenant/caja.php` | `caja.js` |
| `/app/inventario` | `TenantInventarioController` | `tenant/inventario.php` | `inventario.js` |
| `/app/clientes` | `TenantClientesController` | `tenant/clientes.php` | `clientes.js` |
| `/app/proveedores` | `TenantProveedoresController` | `tenant/proveedores.php` | `proveedores.js` |
| `/app/cotizaciones` | `TenantCotizacionesController` | `tenant/cotizaciones.php` | `cotizaciones.js` |
| `/app/reportes` | `TenantReportesController` | `tenant/reportes.php` | Chart.js CDN |
| `/app/configuracion` | `TenantConfigController` | `tenant/config.php` | `config.js` |
| `/app/ia` | `TenantAiController` | `tenant/ai.php` | `ai.js` |
| `/app/soporte` | `TenantTicketsController` | `tenant/tickets.php` | `tickets.js` |
| `/app/logout` | `TenantAuthController::logout` | - | - |

### Layout tenant (`tenant.php`)
- Sidebar (`tenant_sidebar.php`) — filtra módulos por rol (admin/manager/cashier/viewer) y plan
- Header: buscador global, campana notificaciones 🔔, ícono Soporte, ícono IA, fecha/hora, user dropdown con avatar
- Footer: "Seri ERP © 2026 | Osgo Support"

---

## Roles y Permisos (`TenantMiddleware`)

| Rol | Módulos | Acciones |
|-----|---------|----------|
| **admin** | todos (9) | create, edit, delete, view, export |
| **manager** | 8 (sin Configuración) | create, edit, view, export |
| **cashier** | dashboard, caja, ventas, clientes | create, view |
| **viewer** | dashboard, reportes | solo view |

---

## Integraciones clave

- **Ventas → Caja:** `registerCashMovement()` crea movimiento `income` si hay caja abierta
- **Cotizaciones → Inventario:** badge "📝 N en cotización" muestra reservados. Stock NO se descuenta al cotizar
- **Cancelar venta:** devuelve stock automáticamente
- **Ventas → PDF:** `/app/ventas?action=pdf&id=X` genera factura con Dompdf
- **Cotizaciones → PDF:** `/app/cotizaciones?action=pdf&id=X`
- **Caja → PDF:** `/app/caja?action=pdf&id=X` (cierre)

---

## Monedas y formato
- **COP:** `$ 1.000,00` (2 decimales, punto miles, coma decimal)
- **USD:** `US$ 1,000.00`
- **EUR:** `€ 1.000,00`
- Configurable en `settings` del tenant

---

## Commits recientes (esta sesión)

```
b9b3b18 feat: Campana notificaciones tenant + iconos SVG en botones + tooltips
1623bc3 fix: Header superadmin blanco, decimales unificados a 2, buscador clientes
c16a74f feat: Modal licencias redisenado + semestral en planes restaurado
54b17ed feat: Notificaciones de tickets + mejora modal planes
57137c2 feat: Roles y permisos + PDF facturas/cotizaciones/cierres con Dompdf
826a166 fix: Avatar upload JS, Caja lee ventas directo de sales, iconos sidebar
```

---

## Tareas pendientes sugeridas

### Alta prioridad
- [ ] Dashboard con gráficos reales (Chart.js en vendor, no CDN)
- [ ] Exportar datos a Excel/CSV
- [ ] Paginación en todas las tablas
- [ ] Notificaciones en tiempo real (stock bajo, caja abierta +24h)
- [ ] Adjuntar imágenes a productos
- [ ] Código de barras / QR para productos

### Media prioridad
- [ ] Módulo Contabilidad funcional (placeholder actual)
- [ ] Módulo Nómina funcional (placeholder actual)
- [ ] Email notifications (SMTP)
- [ ] Backup/restore desde UI
- [ ] Log de actividad en tenant
- [ ] Módulo Gastos funcional (placeholder actual)

### Baja prioridad
- [ ] Limpiar controladores placeholder antiguos (CajaController, VentasController, etc.)
- [ ] Tests unitarios
- [ ] Internacionalización completa (i18n)
- [ ] API REST para integraciones
- [ ] Instalador web

---

## Reglas de código importantes

1. Los helpers están en `namespace SoftNova\Core` → llamar como `\SoftNova\Core\base_url()` desde fuera
2. `config/app.php` → `'url' => 'http://localhost/SoftNova/public'`
3. Usar `$viewInstance->route()` para URLs en vistas
4. Usar `$viewInstance->asset()` para CSS/JS
5. Formularios AJAX: `data-ajax="true"` + `csrf_field()`
6. Respuestas controller: `$this->respond(true/false, 'mensaje', '/ruta')`
7. CSS: modo oscuro con `body.dark-mode`, guardado en `localStorage`
8. JS: `'use strict'` en todos los archivos de módulo
9. Los botones de acción usan iconos SVG vectoriales (Feather icons), NO emojis
10. Tooltips obligatorios en botones (`title="..."`)
