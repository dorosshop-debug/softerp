# Seri ERP — Estado, avances y funcionalidades

> Documento vivo del producto. Última actualización: **27 jul 2026**  
> Repo: https://github.com/dorosshop-debug/softerp · Rama: `main`  
> Detalle técnico/arquitectura: ver también [`PROJECT_SUMMARY.md`](PROJECT_SUMMARY.md)

---

## Resumen ejecutivo

**Seri ERP** es un SaaS multi-tenant (PHP 8.2 + MariaDB) para PyMEs en Colombia: ventas, inventario, caja, contabilidad nativa, nómina, compras, reportes, asistente IA (Seri) e integraciones de facturación/catálogo.

| Área | Estado |
|------|--------|
| Operación comercial (ventas, caja, inventario, clientes, cotizaciones) | ✅ Estable |
| Contabilidad nativa + periodos + asientos | ✅ Funcional |
| Compras + trazabilidad + CxP | ✅ Funcional |
| Nómina (liquidación, aportes, PDF) | ✅ MVP ampliado |
| Comisiones vendedor + pasarela | ✅ Funcional (nuevo) |
| Integraciones FE / Woo / Mercado Libre | ✅ Funcional |
| Asistente IA Seri | ✅ Funcional (UI chat corregida) |
| Reportes gancho + exportación por plan | ✅ Funcional |

---

## Cómo está el programa hoy

### Núcleo SaaS
- Multi-tenant: BD maestra + una BD por empresa
- Planes y módulos JSON por suscripción
- Roles tenant: admin / manager / cashier / viewer
- Instaladores SQL + `database/migrate_existing.php` para tenants ya creados
- Despliegue documentado (cPanel / VPS)

### Módulos tenant activos

| Módulo | Ruta | Qué hace |
|--------|------|----------|
| Dashboard | `/app/dashboard` | Resumen del negocio |
| Ventas | `/app/ventas` | POS/crédito, abonos, PDF, cancelación con stock |
| Caja | `/app/caja` | Apertura/cierre, movimientos; solo efectivo a caja física |
| Inventario | `/app/inventario` | Productos, stock, trazabilidad, sync catálogo |
| Clientes / Proveedores | `/app/clientes`, `/app/proveedores` | CRUD terceros |
| Cotizaciones | `/app/cotizaciones` | Presupuestos → conversión a venta |
| Compras | `/app/compras` | Compras a proveedor, crédito/CxP |
| Gastos | `/app/gastos` | Gastos tipificados (fijos / financieros / operativos) |
| Contabilidad | `/app/contabilidad` | PUC, diario, mayor, estados, periodos, integraciones, **comisiones** |
| Nómina | `/app/nomina` | Empleados, liquidaciones, primas/cesantías/incapacidad, PDF |
| Reportes | `/app/reportes` | Utilidad, CxC, caja, canales, comisiones, export según plan |
| Configuración | `/app/configuracion` | Empresa, moneda, usuarios, tema |
| IA Seri | `/app/ia` | Chat con contexto del negocio + normativa DIAN general |
| Soporte | `/app/soporte` | Tickets |

### Contabilidad y finanzas
- Asientos automáticos en ventas, abonos, gastos, compras, nómina y comisiones
- Cuentas críticas: inventario `143501`, CxP `220505`, financieros `530505`, comisiones vendedor `510508`
- Cierre/apertura de periodos
- Conciliación sugerida de datáfono vs comisiones registradas
- Integraciones FE: Alegra, Siigo, Factus, DIAN (por tenant)
- Catálogo: WooCommerce + Mercado Libre (OAuth + política de stock)

### Comisiones (julio 2026)
Panel: **Contabilidad → pestaña Comisiones**

- Parámetros: % global, tasa por usuario, base (`total` / `subtotal` / `utilidad`), disparo (`al cobrar` / `al vender`)
- Pasarela automática: datáfono, link, débito, crédito, tarjeta
- Registro → gasto + asiento (auto o liquidación manual)
- Cancelar venta → revierte comisión/gasto
- Reportes muestra pendientes vs registradas

### Nómina
- SMMLV y parámetros de aportes
- Primas, cesantías, intereses, incapacidad, parafiscales/ARL
- PDF de liquidación y comprobante
- Asiento detallado de seguridad social

### Asistente IA (Seri)
- OpenRouter / Nemotron (o modo local sin API key)
- Skills filtradas por módulos del plan
- Historial de conversaciones
- **Fix UI (jul 2026):** el footer ya no corta el chat; un solo mensaje a la vez; hora unificada del cliente

---

## Avances recientes (changelog corto)

| Fecha / commit | Avance |
|----------------|--------|
| jul 2026 (este push) | Comisiones vendedor/pasarela 100% + fix layout chat IA + este documento |
| `ed2add7` | Nómina: primas, cesantías, incapacidad, PDF, asiento SS |
| `0f94ac0` | Nómina MVP + guía imagen IA |
| `c061b84` | WooCommerce visible; gastos fijos vs financieros |
| `4d1bb84` | Compras, sync Woo/ML, auditoría operativa |
| `ce18d5d` | Contabilidad nativa, FE multi-proveedor, reportes, cPanel |

---

## Funcionalidades por capa

### Comercial
- Venta de contado / crédito + abonos
- Medios de pago: efectivo, transferencia, datáfono, tarjetas, link
- Cotización → venta (dispara contabilidad y comisiones)
- Stock y costo en ítems (`unit_cost`) para utilidad/COGS

### Operativo
- Compras con inventario y CxP
- Trazabilidad de movimientos de stock
- Gastos categorizados (catálogo `config/catalog.php`)

### Analítica
- Utilidad del periodo, aging CxC, top deudores/vendedores
- Margen por canal, conciliación datáfono
- Resumen de comisiones en reportes

### Plataforma
- Superadmin: tenants, planes, licencias, tickets, auditorías, settings OG
- CSRF, sesiones, roles, gating por plan
- PDF Dompdf (ventas, cotizaciones, caja, nómina)

---

## Cómo actualizar un entorno existente

```bash
# 1) Desplegar código (git pull / FTP del build)
# 2) Migrar tenants existentes
php database/migrate_existing.php
```

Las tablas de comisiones también se crean al abrir **Contabilidad → Comisiones** (`CommissionService::ensureSchema`). Instalaciones nuevas las traen en `database/install_tenant.sql`.

---

## Pendiente / roadmap sugerido

### Alta
- [ ] Paginación uniforme en listados grandes
- [ ] Export Excel/CSV más amplio
- [ ] Notificaciones proactivas (stock, caja +24h)
- [ ] Imágenes de producto + código de barras/QR

### Media
- [ ] SMTP / email de avisos
- [ ] Backup/restore desde UI
- [ ] API REST pública documentada
- [ ] Mejoras FE (estados DIAN más visibles en ventas)

### Baja
- [ ] Tests automatizados
- [ ] i18n completo
- [ ] Limpiar controladores legacy placeholder

---

## Notas para desarrollo

1. Contabilidad **fuera** de la transacción comercial: un periodo cerrado no debe revertir la venta.
2. Caja física solo con `payment_method === 'cash'`.
3. Sidebar filtra por plan + `TenantMiddleware::canAccess`.
4. Tras features de esquema: correr `migrate_existing.php` **y** desplegar el código (si solo migras, la UI no aparece).
5. Commit style reciente: `feat:` / `fix:` / `docs:` en inglés corto.

---

## Contacto de soporte en producto

Pie de app: **Seri ERP © 2026 | Osgo Support**
