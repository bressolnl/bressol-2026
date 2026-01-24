# Modulo POS (Point of Sale)

Proposito
- Punto de venta interno en el admin.
- Crear pedidos POS rapidos con clientes CRM y canje de puntos.
- Gestion de mercados y costes asociados.

Componentes y archivos
- `PosModule.php` orquesta admin, AJAX y cron.
- `Admin/AdminPages.php` UI para nueva venta, clientes, ajustes y reportes.
- `Services/CustomerLookupService.php` lookup de clientes por token/ID.
- `Services/PosSettings.php` settings de mercados y canje de puntos.
- `assets/pos.js` UI JS.
- `assets/pos.css` estilos del POS.

Pantallas admin
- `POS > Nueva venta`: seleccionar mercado, cliente, carrito y canje.
- `POS > Clientes POS`: buscar cliente y regenerar token.
- `POS > Mercados y ajustes`: mercados, valores de puntos, limites de canje.
- `POS > Reportes`: placeholder.

AJAX endpoints
- `bressol_pos_find_customer` (token o id).
- `bressol_pos_search_products` (busqueda de productos).
- `bressol_pos_create_order` (crear pedido POS).

Flujo principal (crear pedido)
1) Seleccion de mercado activo.
2) Buscar cliente (o venta anonima).
3) Buscar productos y armar carrito.
4) Validar canje de puntos (min, max %, saldo).
5) Crear pedido Woo, aplicar cupom POS si hay canje.
6) Guardar metas POS y completar pedido.
7) Registrar auditoria (pedido y canje).

Cupon POS
- Se crea con prefijo `pos-redeem-...`.
- Meta `_bressol_pos_coupon = 1`, `order_id`, `created_at`.
- Cleanup diario via cron `bressol_pos_cleanup_coupons`.

Metas POS (order)
- Ver listado en `docs/brain/04-datos-y-taxonomias.md`.

Cron
- `PosModule::schedule_cron()` se llama en activacion del plugin.
- `PosModule::cleanup_pos_coupons()` elimina cupones POS usados y antiguos.
