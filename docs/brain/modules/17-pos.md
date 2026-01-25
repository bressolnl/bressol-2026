# Modulo POS (Point of Sale)

Proposito
- Punto de venta interno en el admin.
- Crear pedidos POS rapidos con clientes CRM y canje de puntos.
- Gestion de mercados y costes asociados.
- Integracion con eventos/mercados activos y control de stock vendible.

Componentes y archivos
- `PosModule.php` orquesta admin, AJAX, cron e instalador.
- `Installer.php` crea tablas de opened items.
- `Admin/AdminPages.php` UI para nueva venta, clientes, ajustes y reportes.
- `Services/CustomerLookupService.php` lookup de clientes por token/ID.
- `Services/PosSettings.php` settings de mercados y canje de puntos.
- `Services/InternalOrderService.php` pedidos internos de muestreo.
- `Services/InternalOrderStatus.php` status `bressol-internal` y guardas email.
- `Services/OpenedItemsService.php` tracking de items abiertos.
- `Services/PosMarketResolver.php` valida `event:<id>` como mercado POS.
- `Services/PosMarketsCatalog.php` lista mercados desde provider.
- `assets/pos.js` UI JS.
- `assets/pos.css` estilos del POS.

Pantallas admin
- `POS > Nueva venta`: seleccionar mercado, cliente, carrito y canje.
- `POS > Clientes POS`: buscar cliente y regenerar token.
- `POS > Mercados y ajustes`: mercados, valores de puntos, limites de canje.
- `POS > Reportes`: placeholder.
- `POS > Opened items`: control de producto abierto y descartes.

AJAX endpoints (principales)
- `bressol_pos_find_customer` (token o id).
- `bressol_pos_search_products` (busqueda de productos).
- `bressol_pos_create_order` (crear pedido POS).
- `bressol_pos_opened_items_*` (crear/listar/descartar opened items).

Flujo principal (crear pedido)
1) Seleccion de mercado activo.
2) Buscar cliente (o venta anonima).
3) Buscar productos y armar carrito.
4) Validar canje de puntos (min, max %, saldo).
5) Crear pedido Woo, aplicar cupom POS si hay canje.
6) Guardar metas POS y completar pedido.
7) Registrar auditoria (pedido y canje).

Stock y packs (Inventory)
- Usa `SellableService` para validar stock.
- Packs configurables requieren seleccion; si no, intenta default oficial.
- Si no hay default computable, bloquea POS con mensaje claro.

Eventos / mercados (MarketsEvents)
- Los mercados POS vienen de eventos publicados (`event:<id>`).
- `PosMarketResolver` valida status + canales y resuelve nombre.
- Meta en pedido: `_bressol_event_id`.

Pedidos internos (sampling)
- `InternalOrderService::create_sampling_open_order` crea pedido interno en status `bressol-internal`.
- Metas: `_bressol_internal_order`, `_bressol_internal_reason`, `_bressol_pos_operator_id`, `_bressol_event_id`.
- Emails Woo se desactivan para pedidos internos.

Cupon POS
- Se crea con prefijo `pos-redeem-...`.
- Meta `_bressol_pos_coupon = 1`, `order_id`, `created_at`.
- Cleanup diario via cron `bressol_pos_cleanup_coupons`.

Metas POS (order)
- Ver listado en `docs/brain/04-datos-y-taxonomias.md`.

Tablas POS
- `bressol_opened_items`
- `bressol_opened_item_events`

Cron
- `PosModule::schedule_cron()` se llama en activacion del plugin.
- `PosModule::cleanup_pos_coupons()` elimina cupones POS usados y antiguos.
