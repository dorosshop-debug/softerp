# Despliegue y unificación — Seri ERP (seri.heraconsultores.com)

## Contexto (solo 2 fuentes)

1. **GitHub / este PC** — línea oficial del producto (`dorosshop-debug/softerp` · `main`)
2. **Servidor de prueba** — https://seri.heraconsultores.com (cPanel)

No hay una tercera línea de código. Local y GitHub son la misma: los cambios se suben desde el PC a GitHub y luego se despliegan al servidor.

Flujo correcto:

```text
PC (SoftNova) → commit + push GitHub → desplegar a cPanel → migrate_existing.php
```

El servidor no se edita “a mano” como otra rama de producto: es destino de despliegue.

## Por qué cayó el sitio (jul 2026)

Al sincronizar un ZIP se sobrescribió `config/database.php` con credenciales de desarrollo (`root` / password vacía). Eso provoca HTTP 500.

También puede haber permisos rotos en `vendor/` (ver `error_log` del hosting).

## Checklist de reparación en el servidor

1. Backup de `config/`, `storage/`, `public/uploads/` y BD.
2. Desplegar el código de `main` sin sobrescribir:
   - `config/database.php` (credenciales reales de cPanel)
   - `storage/installed.lock`
   - `.env` si existe
   - `public/uploads/` (fotos de productos, gastos, facturas de compra)
3. Si hace falta regenerar config:
   - `config/database.TEMPLATE.php` → `config/database.php`
   - `config/app.production.example.php` → `config/app.php` (`debug` => false)
4. `composer install --no-dev` (o subir `vendor/` completo).
5. `php database/migrate_existing.php`
6. Probar: login, Compras (foto factura), Inventario → Trazabilidad, Contabilidad → Comisiones.
7. Borrar `seri_diag.php` si quedó de diagnóstico.

## Qué se unificó desde el servidor de prueba

Features del servidor integradas en GitHub manteniendo estructura y diseño del repo:

- OC + recepción + WAC
- Foto/PDF de factura en compras
- Trazabilidad completa (movimientos + costos + kardex + alertas + proyección)
- Ticket 58mm, tipos documento, WhatsApp/correo
- Gastos con comprobante, proveedor aliado
- Comisiones (ya en GitHub) conservadas

No van a GitHub: dumps SQL, credenciales, `error_log`, uploads reales del hosting.

## Regla para no volver a divergir

1. Cambios de producto solo en el PC → GitHub.
2. Deploy al servidor solo desde `main`.
3. Documentar en `AVANCES.md`.
