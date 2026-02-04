# Modulo Forecasting (PR1)

## Proposito
Proveer snapshots manuales de demanda por evento (sin UI) y un servicio minimo para exponer forecast a Purchasing via `ForecastingService`.

## Scope PR1
- Tablas propias para snapshots y lineas.
- `ForecastingService::get_forecast($fromUtc, $toUtc)` agrega demanda por producto.
- WP-CLI selftest para validar que el pipeline de storage funciona.
- Sin UI y con feature flag OFF por defecto.

## Tablas

### bressol_forecast_snapshots
- `id` BIGINT PK
- `event_id` BIGINT NOT NULL
- `source` VARCHAR(20) DEFAULT 'manual'
- `status` VARCHAR(12) DEFAULT 'final'
- `snapshot_at_utc` DATETIME NOT NULL
- `created_at_utc` DATETIME NOT NULL
- `created_by` BIGINT NULL
- `note` VARCHAR(200) NULL
- Indices: `event_id`, `created_at_utc`, `snapshot_at_utc`, `source`

### bressol_forecast_snapshot_lines
- `id` BIGINT PK
- `snapshot_id` BIGINT NOT NULL
- `product_id` BIGINT NOT NULL
- `qty_units` INT NOT NULL (>= 0)
- `created_at_utc` DATETIME NOT NULL
- UNIQUE: `(snapshot_id, product_id)`
- Indices: `snapshot_id`, `product_id`

## Feature flag
- Option: `bressol_forecasting_settings`
- Flag: `forecasting_enabled` (OFF por defecto)

## CLI
Selftest (crea snapshot, inserta lineas, valida y limpia):
```
wp bressol forecast selftest --event_id=123 --product_id=456
```

## Snapshots manuales (Admin)
Ruta: WP Admin → Bressol → Forecasting → Snapshots.

Pasos:
1) Selecciona un evento y pulsa "Load snapshot".
2) Edita/añade líneas con `product_id` y `qty_units`.
3) Pulsa "Save snapshot" para guardar el snapshot manual.

Notas:
- Se guardan hasta 200 líneas por snapshot.
- Filas inválidas (product_id <= 0 o qty_units < 0) se descartan.

## Snapshots POS (auto)
Se generan desde pedidos POS (Woo, status completed) vinculados a evento.

Meta key evento detectada en el repo:
- `\Bressol\Modules\MarketsEvents\Services\OrderEventMetaService::META_KEY` (`_bressol_event_id`).

Packs:
- El pedido guarda la selección en `_bressol_pack` (order item meta).
- La definición base del pack está en `_bressol_pack_definition` (JSON).
- Si falta selección, se usa el SKU del pack y se emite warning `PACK_NO_COMPONENTS`.

Backfill:
- En Snapshots: botón "Generate snapshot from POS" para un evento.
- Backfill por rango o últimos N meses (sin CLI).

Lead time:
- Guardado en `bressol_suppliers.lead_time_days` (Purchasing).
- Si hay un único proveedor, se usa ese lead time en Results.

## Source of truth de ventas
- Las ventas POS se crean como WooCommerce orders (`wc_create_order`) en `PosModule::handleCreateOrderAjax`.
- Los pedidos POS se marcan con `status=completed` y meta `_bressol_pos_channel=pos`.
- Snapshots POS consumen pedidos `completed` con meta `_bressol_event_id` + `_bressol_pos_channel=pos`.
- Refunds se aplican restando cantidades de `shop_order_refund` (neto por item).

## Cómo se vincula a eventos
- POS setea `_bressol_event_id` al crear el pedido (ver `PosModule::handleCreateOrderAjax`).
- En checkout web se rellena vía `OrderEventMetaService::maybe_set_on_checkout_or_pos` (hook `woocommerce_new_order`).
