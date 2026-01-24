# POS Sampling / Opened Items

## Objetivo
Crear pedidos internos para sampling sin contaminar ventas, y registrar los productos abiertos por evento para control de merma y auditoria.

## Estados y metas WooCommerce usadas
- Status: `wc-bressol-internal` (pedido interno para sampling)
- `_bressol_internal_order` = `1` (marca pedido interno)
- `_bressol_stock_reduced` = `1` (stock reducido una sola vez)
- `_bressol_opened_item_id` (meta en line item del pedido interno)
- `_bressol_event_id` (id del evento activo)
- `_bressol_internal_invalid` = `1` (pedido interno invalido, sin reducir stock)

## Tablas relevantes

### `wp_bressol_opened_items`
- `id` BIGINT PK
- `product_id` BIGINT NOT NULL
- `opened_at` DATETIME NOT NULL
- `opened_event_id` BIGINT NOT NULL
- `opened_by_user_id` BIGINT NOT NULL
- `initial_qty` INT NOT NULL
- `internal_order_id` BIGINT NOT NULL
- `status` VARCHAR(20) NOT NULL (`open`, `discarded`)
- `discarded_at` DATETIME NULL
- `discard_reason` VARCHAR(255) NULL

### `wp_bressol_opened_item_events`
- `id` BIGINT PK
- `opened_item_id` BIGINT NOT NULL
- `event_id` BIGINT NOT NULL
- `used_at` DATETIME NOT NULL
- `note` VARCHAR(50) NOT NULL DEFAULT ''
- `UNIQUE KEY uniq_opened_item_event (opened_item_id, event_id, note)`

## Criterio de DONE
- [ ] Estado `wc-bressol-internal` registrado sin emails.
- [ ] Pedidos internos no aparecen en Sales Analytics.
- [ ] Stock reducido exactamente una vez por pedido interno.
- [ ] `opened_items` se crea y se vincula a line item.
- [ ] `opened_item_events` se inserta con idempotencia.
- [ ] Listado/descartado en POS funciona con evento activo.
- [ ] Compensacion: pedidos invalidos no reducen stock.

## Flujo nominal
1. POS crea pedido interno con status `wc-bressol-internal`.
2. Se crea `opened_item` con referencia al pedido interno.
3. Se inserta evento de uso `opened_item_events` con `note='opened'`.
4. Se vincula `_bressol_opened_item_id` al line item.
5. Se reduce stock una sola vez.

## Flujos de fallo (compensacion)
- Si falla crear `opened_item` o su evento: se marca `_bressol_internal_invalid=1`.
- Si falla vincular line item: se marca `_bressol_internal_invalid=1`.
- Si falla reducir stock: se marca `_bressol_internal_invalid=1`.

## QA manual (con placeholders)
1. Iniciar POS con evento activo. Resultado: [____]
2. Abrir sampling con producto y qty. Resultado: order_id=[____], opened_item_id=[____]
3. Verificar link "Ver pedido interno". Resultado: [____]
4. Verificar item en "Productos abiertos" sin refresh. Resultado: [____]
5. Reintentar la misma accion y confirmar idempotencia en `opened_item_events`. Resultado: [____]
6. Marcar como desechado con motivo. Resultado: [____]
7. Verificar status `discarded` y evento `note='discarded'`. Resultado: [____]
8. Confirmar exclusion en Sales Analytics. Resultado: [____]

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
- No aparece opened item: revisar `bressol_pos_list_opened_items` y event_id resuelto.
- Stock se reduce dos veces: revisar `_bressol_stock_reduced` y `_bressol_internal_invalid`.
- Error al descartar: revisar `reason` max 50 chars y tabla `opened_item_events`.
- No se crea link al pedido interno: revisar respuesta `order_id` del endpoint.
