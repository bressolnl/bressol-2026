# Datos y taxonomias

Metas de producto (packs)
- `_bressol_pack_definition` (JSON): define slots y opciones de un pack.
- `_bressol_pack_tier`: low|mid|high.
- `_bressol_pack_occasion`: gift|self|both.
- `_bressol_pack_focus`: CSV (ej: `oil,salt,vinegar`).
- `_bressol_pack_themes`: CSV (ej: `cooking,borrel`).

Metas de producto (SEO / PDP)
- `bressol_pdp_intro` (HTML)
- `bressol_pdp_longform` (HTML)
- `bressol_origin_ref` (HTML)
- `bressol_pdp_faq` (JSON o HTML)
- `bressol_smaak_textuur1/2/3`
- `bressol_smaak_ingredienten1/2/3`
- `bressol_smaak_karakter1/2/3`
- `bressol_usage_tips` (HTML)
- `bressol_seo_usage_images` (array IDs)
- `bressol_seo_quote_title`
- `bressol_seo_quote_text` (HTML)
- Packs (SEO):
  - `bressol_pack_recommended_moments` (CSV slugs)
  - `bressol_pack_cross_sell_categories` (CSV slugs)

Taxonomias
- `bressol_moment` (product):
  - registrada en `TaxonomiesModule`.
  - visible en admin, REST y columna en listado de productos.

Term meta (categorias y moments)
- Categoria producto:
  - `bressol_cat_intro` (HTML)
  - `bressol_cat_longform` (HTML)
  - `bressol_cat_faq` (JSON)
- Momento:
  - `bressol_moment_intro` (HTML)
  - `bressol_moment_longform` (HTML)
  - `bressol_moment_faq` (JSON)

Convenciones de categorias (families)
- Families por slugs estables: `oil`, `salt`, `vinegar`, `drinks`, `olives`, `tapenade`, etc.
- Reglas completas en `docs/recommendations-and-moments.md`.

Session keys (WooCommerce)
- `bressol_datalayer_add_to_cart`
- `bressol_datalayer_remove_from_cart`
- `bressol_datalayer_begin_checkout_sent`
- `bressol_datalayer_purchase_sent_{orderId}`
- `bressol_datalayer_reco_click`
- `bressol_datalayer_cart_reco_click`
- `bressol_datalayer_guided_upsell`
- `bressol_guided_shopping`
- `bressol_last_added`
- `bressol_modal_pending`

CRM (tablas)
Prefijo: `wp_bressol_crm_` (usa prefix real de WP).
- `customers`
- `customer_meta`
- `points_ledger`
- `points_redemptions`
- `tags`
- `customer_tags`
- `order_sync`
- `audit_logs`

ESP (tablas)
Prefijo: `wp_bressol_esp_` (usa prefix real de WP).
- `campaigns`
- `campaign_audience_snapshot`
- `templates`
- `segments`
- `manual_emails`
- `events`
- `consents`
- `audit_logs`
- `send_queue`
- `send_queue_jobs`

POS (metas de pedido)
- `_bressol_pos_channel`
- `_bressol_pos_market_id`
- `_bressol_pos_market_name`
- `_bressol_pos_market_cost_cents`
- `_bressol_pos_operator_id`
- `_bressol_pos_customer_id`
- `_bressol_pos_points_redeemed`
- `_bressol_pos_redemption_value_cents`
- `_bressol_pos_redemption_coupon_code`
- `_bressol_pos_points_value_cents_snapshot`
- `_bressol_pos_min_redemption_points_snapshot`
- `_bressol_pos_max_redemption_percent_snapshot`
- `_bressol_pos_redemption_calculated_from_total_cents`
- Internos POS:
  - `_bressol_internal_order`
  - `_bressol_internal_reason`
  - `_bressol_internal_invalid`
  - `_bressol_stock_reduced`

MarketsEvents (metas de pedido)
- `_bressol_event_id`POS (tablas)
Prefijo: `wp_bressol_` (usa prefix real de WP).
- `bressol_opened_items`
- `bressol_opened_item_events`

MarketsEvents (tablas)
Prefijo: `wp_bressol_` (usa prefix real de WP).
- `bressol_events`

Forecasting (tablas)
Prefijo: `wp_bressol_` (usa prefix real de WP).
- `bressol_forecast_snapshots`

Purchasing (tablas, sin PII)
Prefijo: `wp_bressol_` (usa prefix real de WP).
- `bressol_suppliers` (notes sin PII)
- `bressol_purchase_orders`
- `bressol_purchase_order_lines`
- `bressol_receivings`
- `bressol_receiving_lines`
