# Modulo Sales Analytics

## Proposito
- Reporting fiscal y de negocio con pedidos WooCommerce como fuente de verdad.
- Incluye ventas POS mediante metas `_bressol_pos_*`.
- Gestion de mercados y costes asociados (POS).

## Arquitectura (bressol-core)
- `src/Modules/SalesAnalytics/SalesAnalyticsModule.php`: registro de hooks, menu admin, exports y invalidacion de cache.
- `src/Modules/SalesAnalytics/Admin/AdminPages.php`: Dashboard, Exports, Settings.
- `src/Modules/SalesAnalytics/Services/`:
  - `MetricsExtractor`: metricas base en cents por pedido.
  - `TaxBreakdownService`: desglose fiscal por rate.
  - `ExportService`: CSV (orders/daily/discrepancies).
  - `CacheService`: transients con version.
  - `FiltersNormalizer`: hash estable de filtros.
  - `Settings`: flag export PII.
  - `AuditLogger`: wrapper delegando en CRM AuditLogger.
  - `Capabilities`: caps base y sensible.
- `src/Modules/SalesAnalytics/Repositories/OrderQuery.php`: consulta de pedidos (ids) con filtros.

## Filtros y guardrails
- Filtros: `date_from`, `date_to`, `channel` (all|web|pos), `market_id`, `status=completed`.
- Guardrails:
  - Dashboard: max 2000 pedidos.
  - Daily export: >60 dias o >5000 pedidos bloquea.
  - Orders export: >180 dias o >20000 pedidos bloquea.

## Metricas (cents)
- `total_incl_tax_cents` = total Woo (incluye impuestos).
- `tax_total_cents` = total impuesto.
- `total_excl_tax_cents` = total_incl_tax_cents - tax_total_cents.
- `shipping_excl_tax_cents`, `shipping_tax_cents`, `shipping_incl_tax_cents`.
- `discount_excl_tax_cents`, `discount_tax_cents`, `discount_incl_tax_cents`.
- `refunds_incl_tax_cents` = total reembolsado (positivo).
- `market_cost_cents` = meta POS `_bressol_pos_market_cost_cents` (solo canal pos).
- `net_sales_excl_tax_cents` = total_excl_tax_cents - shipping_excl_tax_cents.
- `profit_estimated_excl_tax_cents` = net_sales_excl_tax_cents - market_cost_cents (COGS = 0 placeholder).

## Fiscalidad
- `TaxBreakdownService` agrupa por rate %:
  - Line items + shipping.
  - Refunds restan impuestos (base si Woo la expone en refund items).
  - Agrupa multiples rate_id por porcentaje normalizado (2 decimales).
- Validacion: suma de impuestos del breakdown vs `order->get_total_tax()` menos refunds.
  - Tolerancia: 2 cents.
- Discrepancias: se listan y exportan (order_id + diff_tax_cents).

## Exports
### Orders CSV (sin PII)
Columnas:
- `order_id`, `order_number`, `created_at`, `status`, `channel`, `market_id`, `market_name`, `currency`
- `total_incl_tax_cents`, `tax_total_cents`, `total_excl_tax_cents`
- `shipping_incl_tax_cents`, `discount_incl_tax_cents`, `refunds_incl_tax_cents`
- `market_cost_cents`, `net_sales_excl_tax_cents`, `profit_estimated_excl_tax_cents`
- `tax_breakdown_json`

### Orders CSV con PII (solo si flag + cap + include_pii=1)
Columnas adicionales (al final):
- `billing_email`, `billing_first_name`, `billing_last_name`, `billing_country`

### Daily CSV
Columnas:
- `date`, `channel`, `market_id`, `market_name`, `currency`, `orders_count`
- `total_incl_tax_cents`, `tax_total_cents`, `total_excl_tax_cents`
- `shipping_incl_tax_cents`, `discount_incl_tax_cents`, `refunds_incl_tax_cents`
- `market_cost_cents`, `net_sales_excl_tax_cents`, `profit_estimated_excl_tax_cents`
- `tax_breakdown_json`

### Discrepancies CSV
Columnas:
- `order_id`, `diff_tax_cents`

### Events CSV (reporte por evento)
Columnas:
- `event_id`, `event_title`, `type`, `start_at`, `city`
- `orders_count`
- `total_incl_tax_cents`, `tax_total_cents`, `total_excl_tax_cents`
- `refunds_incl_tax_cents`
- `market_cost_cents`
- `event_cost_fixed_cents`, `event_cost_variable_cents`
- `profit_estimated_excl_tax_cents`
- `tax_breakdown_json`
- `computed_at`, `filters_hash`

## Privacidad y permisos
- `manage_woocommerce` para ver Dashboard/Exports.
- Export PII: `bressol_sensitive_exports` + `export_pii_enabled` = true.
- PII nunca en daily/discrepancies.

## Auditoria
- Wrapper `SalesAnalytics\Services\AuditLogger` delega en `Crm\Services\AuditLogger`.
- Acciones: attempted / blocked / generated / failed para orders, daily y discrepancies.
- `result` enum: attempted|success|blocked|error.
- Contexto estandar (sin PII): module, export_type, filters normalizados, include_pii, result, reason, rows_count, request_uri (sin query), exception_class, message_truncated.

## Caching e invalidacion
- Transients con version: `bressol_sales_analytics_cache_version`.
- Clave: `bressol_sa_v{version}_{sha1}`.
- TTLs: dashboard 10 min, daily 60 min.
- Invalidation: `woocommerce_order_status_changed` (si entra/sale completed), `woocommerce_order_refunded`.

## Reporte por evento
- Fuente: `_bressol_event_id` en pedidos + `wp_bressol_events`.
- Filtros: `date_from`, `date_to`, `channel`, `event_id` (opcional), status `completed`.
- Rolup sin PII, agrupa por `event_id` (0 = Sin evento).
- Caching: TTL 15 min, versionado de SalesAnalytics.
- Tradeoff: cambios en eventos no bump cache; se refresca por TTL.
- En UI se muestra `computed_at`, `source` (cache/recalculado) y `orders_count`.

### Costes variables (cost_variable_json)
Reglas interpretadas:
- `per_day_cents` * dias (min 1, segun `start_at`/`end_at`)
- `percent_sales_basis_points` aplicado a `total_excl_tax_cents`
- `flat_cents` suma directa
Otras claves se ignoran.

Hardening:
- Si `start_at/end_at` invalidos o `end < start`, se usa duracion 1 dia y se audita (max 10 ids por ejecucion).
- `%` aplica sobre base `max(0, total_excl_tax_cents - refunds_excl_tax_cents)`.

### Formula profit por evento
`profit_estimated_excl_tax_cents = total_excl_tax_cents - refunds_excl_tax_cents - market_cost_cents - event_cost_fixed_cents - event_cost_variable_cents`

## Hardening
- Rate limit por usuario: transient `bressol_sa_export_rl_{user_id}` (120s).
- Logs con request_uri sin query.
