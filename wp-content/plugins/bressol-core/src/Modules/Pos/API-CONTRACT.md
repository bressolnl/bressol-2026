# POS API Contract (AJAX)

## Permisos
- Capability requerida: `manage_woocommerce` (fallback `manage_options` si WooCommerce no está activo).
- Todos los endpoints requieren `nonce`.

## Endpoints

### `bressol_pos_find_customer`
- **Params**: `nonce`, `token` (string) o `customer_id` (int)
- **Success**: `{ success: true, data: { ...customer } }`
- **Error**: `{ success: false, data: { code, message } }`

### `bressol_pos_search_products`
- **Params**: `nonce`, `query` (string)
- **Success**: `{ success: true, data: [ { id, name, sku, price_cents } ] }`
- **Error**: `{ success: false, data: { code, message } }`

### `bressol_pos_create_order`
- **Params**:
  - `nonce`
  - `market_id` (string)
  - `customer_id` (int, optional)
  - `loyalty_opt_in` (`yes|no`)
  - `marketing_opt_in` (`yes|no`)
  - `items` (JSON array: `[{ product_id, qty }]`)
  - `points_redeem` (int)
- **Success**: `{ success: true, data: { order_id } }`
- **Error**: `{ success: false, data: { code, message } }`

### `bressol_pos_open_sampling_item`
- **Params**:
  - `nonce`
  - `event_id` (int) o `market_id` (`event:{id}`)
  - `product_id` (int)
  - `qty` (int)
- **Success**: `{ success: true, data: { order_id, opened_item_id, stock_reduced } }`
- **Error**: `{ success: false, data: { code, message } }`

### `bressol_pos_list_opened_items`
- **Params**:
  - `nonce`
  - `event_id` (int) o `market_id` (`event:{id}`)
  - `limit` (int, default 50)
  - `page` (int, default 1)
- **Success**: `{ success: true, data: { items: [ ... ] } }`
- **Error**: `{ success: false, data: { code, message } }`

### `bressol_pos_discard_opened_items`
- **Params**:
  - `nonce`
  - `event_id` (int) o `market_id` (`event:{id}`)
  - `opened_item_ids` (JSON array int) o `opened_item_id` (int)
  - `reason` (string)
- **Success**: `{ success: true, data: { discarded } }`
- **Error**: `{ success: false, data: { code, message } }`

### `bressol_pos_mark_opened_items_used`
- **Params**:
  - `nonce`
  - `event_id` (int) o `market_id` (`event:{id}`)
  - `opened_item_ids` (JSON array int)
  - `note` (string, optional)
- **Success**: `{ success: true, data: { updated } }`
- **Error**: `{ success: false, data: { code, message } }`

## Respuestas de error
- Forma estandar: `{ success: false, data: { code: string, message: string } }`
- `code` identifica el tipo de error (e.g. `pos_forbidden`, `pos_invalid_event`).

## Idempotencia
- `opened_item_events` usa clave unica `(opened_item_id, event_id, note)` para evitar duplicados en reintentos.

## Definition of Done
- [ ] Estados/metas correctas (`wc-bressol-internal`, `_bressol_internal_order`).
- [ ] Emails bloqueados para pedidos internos.
- [ ] Stock reducido exactamente una vez.
- [ ] Compensacion marcada como invalida y sin stock.
- [ ] Idempotencia events verificada.
- [ ] Excluido de analytics.
- [ ] Migracion DB verificada (note NOT NULL + uniq).
- [ ] Permisos + nonces OK.
- [ ] QA manual paso a paso ejecutado.

## Troubleshooting
- Pedido interno aparece en analytics: revisar meta `_bressol_internal_order` y filtro en `OrderQuery`.
- No aparece opened item: verificar `bressol_pos_list_opened_items` y event_id resuelto.
- Stock se reduce dos veces: revisar `_bressol_stock_reduced` y `_bressol_internal_invalid`.
- Error al descartar: revisar `reason` max 50 chars y tabla `opened_item_events`.
- No se crea link al pedido interno: revisar respuesta `order_id` del endpoint.
