# POS (MVP)

Este módulo añade el punto de venta (POS) como un set de pantallas de administración.

## Decisiones

- POS crea pedidos Woo estándar (sin pagos).
- Estado del pedido POS: `completed` siempre (forzado, no configurable).
- Mercados se guardan en settings (opción `bressol_pos_settings`, campo `markets`).
- Privacidad: el operador no ve PII en listados, solo token opaco y nombre.

## Arquitectura

- Módulo `PosModule` registra menús, assets y endpoints AJAX.
- Admin pages:
  - Nueva venta: UI JS para mercado, cliente, catálogo, carrito y canje.
  - Clientes POS: lookup por token/ID, regeneración de token, alta rápida.
  - Mercados y ajustes: CRUD mercados y settings de loyalty.
  - Reportes: métricas y export CSV sin PII.
- Servicios:
  - `PosSettings`: defaults + settings (mercados, puntos, límites).
  - `CustomerLookupService`: token opaco (`pos_public_id`) y payload sin PII.

## Flujos principales

### Nueva venta (POS)
- Seleccionar mercado activo y coste (editable).
- Identificación cliente por token o venta anónima.
- Buscar productos y construir carrito.
- (Opcional) canje de puntos con validaciones.
- Crear pedido Woo (sin pago).

### Clientes POS
- Buscar cliente por token/ID.
- Regenerar token (invalida el anterior).
- Alta rápida (nombre obligatorio, email/phone opcionales).

### Reportes
- Filtrar por mercado y rango de fechas.
- Métricas agregadas y listado paginado.
- Export CSV sin PII.

## Endpoints POS (AJAX)

- `bressol_pos_find_customer`: lookup por token/ID (payload sin PII).
- `bressol_pos_search_products`: búsqueda simple por nombre/SKU.
- `bressol_pos_create_order`: crea pedido POS, aplica descuentos y metas.

## Metas de pedido POS

- `_bressol_pos_channel = pos`
- `_bressol_pos_market_id`
- `_bressol_pos_market_name`
- `_bressol_pos_market_cost_cents`
- `_bressol_pos_operator_id`
- `_bressol_pos_customer_id` (CRM id si aplica)
- `_bressol_loyalty_opt_in` (yes/no)
- `_bressol_marketing_opt_in` (yes/no)
- `_bressol_pos_points_redeemed`
- `_bressol_pos_redemption_value_cents`

## Settings POS (bressol_pos_settings)

- `order_status_default`
- `currency`
- `points_value_cents`
- `min_redemption_points`
- `max_redemption_percent_of_order`
- `markets[]` con `{ id, name, active, default_cost_cents }`

## Privacidad

- Operador no ve email/teléfono/dirección.
- Token opaco `pos_public_id` en CRM customer_meta.
- Respuestas JSON de POS no incluyen PII.

## Integración CRM / ESP

- CRM awarding solo si `loyalty_enabled=1` y cliente activo.
- Opt-in explícito permite activar loyalty/marketing.
- Redenciones POS crean `points_redemptions`.
- ESP marketing respeta `opt_out`.

## QA

Ver runbook en `docs/pos-qa.md` (incluye sección de fiscalidad y formulario de evidencia).
