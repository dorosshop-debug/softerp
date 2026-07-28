# Despliegue y unificación — Seri ERP (seri.heraconsultores.com)

Documento operativo tras unificar el código del servidor con GitHub (`main`).

## Línea oficial

- **Código de producto:** siempre en GitHub → https://github.com/dorosshop-debug/softerp (`main`)
- **Este PC (SoftNova)** y el servidor deben desplegarse **desde ese `main`**
- El servidor **no** es una copia editable paralela

Flujo correcto:

```text
Cambios en PC → commit + push a GitHub → desplegar a cPanel → migrar BD
```

## Por qué cayó el sitio (jul 2026)

Al sincronizar un ZIP se sobrescribió `config/database.php` con credenciales de desarrollo (`root` / password vacía). Eso provoca **HTTP 500**.

También apareció en logs:

```text
Permission denied ... vendor/.../thecodingmachine/safe/...
```

→ revisar permisos de `vendor/` en cPanel (755 carpetas, 644 archivos; propietario correcto).

## Checklist de reparación en el servidor

1. **Backup** de `config/`, `storage/`, `public/uploads/` y BD (cPanel Backup / phpMyAdmin).
2. Desplegar el código de `main` (FTP o File Manager), **sin sobrescribir**:
   - `config/database.php` (credenciales reales)
   - `storage/installed.lock`
   - `.env` si existe
   - `public/uploads/`
3. Si hace falta regenerar config:
   - Copiar [`config/database.TEMPLATE.php`](config/database.TEMPLATE.php) → `config/database.php` y poner datos de MySQL
   - Copiar [`config/app.production.example.php`](config/app.production.example.php) → `config/app.php` (`debug` => false)
4. `composer install --no-dev` en la raíz del sitio (o subir `vendor/` completo del build).
5. Ejecutar:

```bash
php database/migrate_existing.php
```

6. Probar login, Ventas (ticket 58mm), Contabilidad → Comisiones, Compras (OC).
7. Borrar cualquier `seri_diag.php` / `public/seri_diag.php` de diagnóstico.

## Qué quedó unificado desde el servidor (otro PC)

Portado a GitHub `main` (además de lo ya documentado en AVANCES):

| Feature | Dónde |
|---------|--------|
| Órdenes de compra (OC) + recepción | Compras / `PurchasingService` |
| Tipos documento / plazos / vencimiento | Ventas / `SalesDocumentService` |
| Ticket PDF 58mm | Ventas PDF `?format=ticket` |
| WhatsApp / correo de recibo | `ReceiptShareService` + `MailService` |
| Catálogo medios de pago | `PaymentMethodCatalog` (+ débito/crédito) |
| Proveedor aliado + % descuento | Proveedores |
| Gastos tipados + comprobante adjunto | `ExpenseCategoryService` |
| Stock WAC / costos / pending accounting | `StockService` |
| Asientos OC / movimientos stock | `AccountingService` |
| CxC por vencimiento | `ReceivableService` |

**No portado a git (correcto):** dumps SQL, `error_log`, credenciales, uploads, scripts de diagnóstico del hosting.

## Paquete FTP recomendado

Usar `deploy/prepare_ftp.ps1` si existe, o subir árbol completo **excepto**:

- `config/database.php` del ZIP de desarrollo
- `.git/`
- `storage/logs/*` pesados
- dumps `*.sql` con datos reales

## Después de cada update

1. Push a GitHub
2. Deploy al servidor
3. `php database/migrate_existing.php`
4. Smoke test: login + 1 venta + Contabilidad

## Contacto pie de app

Seri ERP © 2026 | Osgo Support
