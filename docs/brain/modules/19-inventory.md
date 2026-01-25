# Modulo Inventory (Stock Intelligence)

Proposito
- Calcular stock vendible (sellable) para productos simples y packs.
- Bloquear compra web/POS cuando no hay stock suficiente.
- Exponer API reutilizable `SellableService::is_sellable()` para otros modulos.

Componentes y archivos
- `InventoryModule.php`: registro de hooks Woo + admin + cache invalidation.
- `Admin/AdminPages.php`: UI minima con listado de packs y sellable.
- `Services/StockCalculator.php`: calculo base de stock sellable.
- `Services/SellableService.php`: API publica + cache de packs.
- `Services/CacheService.php`: transients con version.
- `Repositories/ProductRepository.php`: acceso a producto + stock.
- `Repositories/PackDefinitionRepository.php`: lectura de `_bressol_pack_definition`.
- `Cli/SelfTestCommand.php`: selftest rapido para packs.

Reglas MVP
- Stock fisico = Woo (manage_stock + stock_quantity).
- Packs: sellable = min(floor(stock_comp/qty_comp)) usando definicion/seleccion.
- Sin reservas ni ledger en esta fase.

Integraciones
- Web: bloquea add-to-cart y checkout si no hay stock sellable.
- POS: valida stock antes de crear pedido.
- Recommendations: filtra productos no vendibles usando `is_sellable`.

Caching
- Solo para calculos de packs sin seleccion.
- Invalida por cambio de stock o update de `_bressol_pack_definition`.

CLI
- `wp bressol inventory selftest --pack_id=123`
- Sin PII.
