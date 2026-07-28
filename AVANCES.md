# Seri ERP — Estado, avances y funcionalidades

> Documento vivo del producto. Última actualización: **28 jul 2026**  
> Repo: https://github.com/dorosshop-debug/softerp · Rama: `main`  
> Detalle técnico/arquitectura: ver también [`PROJECT_SUMMARY.md`](PROJECT_SUMMARY.md)  
> Despliegue / unificación servidor: ver [`DEPLOY_UNIFICACION.md`](DEPLOY_UNIFICACION.md)

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

### Ventas y documentos (unificado jul 2026)
- Tipos de documento: factura, remisión, cuenta de cobro, FE
- Plazos de pago / fecha de vencimiento
- Ticket térmico PDF 58mm
- Compartir recibo por WhatsApp o correo (`ReceiptShareService`)

### Compras OC (unificado jul 2026)
- Órdenes de compra → recepción a inventario
- Descuento pronto pago / proveedor aliado (`is_ally`, `%`)
- Asiento contable de OC / movimientos de stock

### Inventario / costos
- Costo promedio ponderado (WAC) al ingresar mercancía
- Trazabilidad `listMovements` + pendientes de contabilizar

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
| 28 jul 2026 | Unificación servidor heraconsultores + GitHub (OC, ticket 58mm, docs venta, WAC, gastos con comprobante) |
| 27 jul 2026 | Comisiones vendedor/pasarela 100% + fix layout chat IA + AVANCES.md |
| `ed2add7` | Nómina: primas, cesantías, incapacidad, PDF, asiento SS |
| `0f94ac0` | Nómina MVP + guía imagen IA |
| `c061b84` | WooCommerce visible; gastos fijos vs financieros |
| `4d1bb84` | Compras, sync Woo/ML, auditoría operativa |
| `ce18d5d` | Contabilidad nativa, FE multi-proveedor, reportes, cPanel |

---

## Funcionalidades por capa

### Comercial
- Venta de contado / crédito + abonos
- Medios de pago: efectivo, transferencia, datáfono, tarjetas (débito/crédito), link
- Tipos de documento y plazos; ticket 58mm; compartir recibo
- Cotización → venta (dispara contabilidad y comisiones)
- Stock y costo en ítems (`unit_cost`) para utilidad/COGS

### Operativo
- Compras por OC + recepción + CxP; legado `purchases` aún contabilizable
- Trazabilidad de movimientos de stock (WAC)
- Gastos tipificados + adjunto de comprobante

### Analítica
- Utilidad del periodo, aging CxC (también por vencimiento), top deudores/vendedores
- Margen por canal, conciliación datáfono
- Resumen de comisiones en reportes

### Plataforma
- Superadmin: tenants, planes, licencias, tickets, auditorías, settings OG
- CSRF, sesiones, roles, gating por plan
- PDF Dompdf (ventas, ticket 58mm, cotizaciones, caja, nómina)
- **Regla:** GitHub primero, deploy después ([`DEPLOY_UNIFICACION.md`](DEPLOY_UNIFICACION.md))

---

## Cómo actualizar un entorno existente

```bash
# 1) Desplegar código (git pull / FTP del build) — NO pisar config/database.php de producción
# 2) Migrar tenants existentes
php database/migrate_existing.php
```

Las tablas de comisiones / OC / categorías de gasto se crean al abrir el módulo (`ensureSchema`). Instalaciones nuevas también cubren comisiones en `database/install_tenant.sql`.

Para **seri.heraconsultores.com**, seguir el checklist completo en [`DEPLOY_UNIFICACION.md`](DEPLOY_UNIFICACION.md).

---

## Pendiente / roadmap sugerido

### Alta
- [ ] Paginación uniforme en listados grandes
- [ ] Export Excel/CSV más amplio
- [ ] Notificaciones proactivas (stock, caja +24h)
- [ ] Imágenes de producto + código de barras/QR
- [ ] UI completa para importación ecommerce (servicio base ya incluido)

### Media
- [ ] SMTP real (hoy `mail()` nativo para recibos)
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
2. Caja física solo con medios que `PaymentMethodCatalog::affectsCash` marque como efectivo.
3. Sidebar filtra por plan + `TenantMiddleware::canAccess`.
4. Tras features de esquema: correr `migrate_existing.php` **y** desplegar el código (si solo migras, la UI no aparece).
5. Commit style reciente: `feat:` / `fix:` / `docs:` en inglés corto.
6. Nunca subir `config/database.php` real ni dumps SQL de producción a GitHub.

---

## Contacto de soporte en producto

Pie de app: **Seri ERP © 2026 | Osgo Support**