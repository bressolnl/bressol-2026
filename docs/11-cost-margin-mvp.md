# Cost & Margin MVP

## Que resuelve
- Evita perdidas silenciosas antes de vender en POS.
- Estima costes y margenes con datos internos (lotes + transporte).
- Bloquea tickets con margen/profit insuficiente y genera warnings operativos.

## Componentes
- **Inventory (Transfers/Lots)**: lotes NL, FIFO vendible, expiries.
- **CostMargin Transport**: snapshots cerrados con asignaciones por peso.
- **CostMarginService**: coste unitario estimado y COGS por pedido.
- **MarginRulesService**: motor de reglas con salida pass/warn/block.

## Defaults actuales (bressol_margin_rules)
- `min_profit_cents` (pos/online): 300
- `min_margin_pct` (pos/online): 0.10
- `line_max_loss_cents` (pos/online): 500
- `expiry_discount_steps`: 45->25, 21->50, 7->75

## Contratos POS
- Respuesta estable:
  - `margin_status`: pass|warn|block
  - `margin_violations`: []
  - `margin_computed`: { net_sales_excl_tax_cents, cogs_cents, profit_cents, margin_pct, missing_cost }
- En error `pos_margin_blocked`, se incluyen los mismos campos.

## Auditoria en pedidos (metas)
- `_bressol_margin_status`: pass|warn|block
- `_bressol_margin_violations`: JSON compacto con violaciones
- `_bressol_margin_computed`: JSON con:
  - `net_sales_excl_tax_cents`, `market_cost_cents`, `cogs_cents`, `profit_cents`
  - `margin_bps` (10000 = 100.00%)
  - `cogs_source` (real|estimated|pending)
  - `missing_cost` (bool)
  - `price_source` (explicit|woo_fallback)
- `_bressol_margin_rules_version`: versionado de reglas (v1 / hash)
- `_bressol_margin_checked_at`: timestamp MySQL (`current_time('mysql')`)
- Ejemplo (`_bressol_margin_computed`):
  - `{"net_sales_excl_tax_cents":1200,"market_cost_cents":0,"cogs_cents":700,"profit_cents":500,"margin_bps":4167,"cogs_source":"estimated","missing_cost":false,"price_source":"woo_fallback"}`

## CLI
- `wp bressol cost unit --product_id=123`
- `wp bressol cost order --order_id=999`
- `wp bressol margin eval --channel=pos --items='[...]'`
- `wp bressol margin selftest [--channel=pos]`
  - Usa `unit_cost_overrides` en el contexto para casos deterministas.
- `wp bressol margin persist --order_id=123`
- `wp bressol margin backfill --after=YYYY-MM-DD [--channel=pos|online] [--dry-run=1]`

## Limitaciones (MVP)
- COGS es estimado FIFO si no hay `_bressol_lot_allocations`.
- `missing_cost` o precio estimado => warn, no block (salvo net_sales<=0).
- COGS real se fija al completar el pedido; si falla queda en `pending`.
- Refund parcial no revierte lotes (solo refund total).
