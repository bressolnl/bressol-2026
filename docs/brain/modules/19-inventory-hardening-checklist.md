## How to close Inventory hardening

Checklist rápido (manual/CLI):

1) Ship idempotente: crea un transfer, ejecuta `ship` dos veces y la segunda debe fallar sin consumir stock extra.
2) Receive idempotente: ejecuta `receive` dos veces y la segunda debe fallar con “already received”.
3) Allocations: la tabla `wp_bressol_transfer_lot_allocations` mantiene un único set de filas por ship.
4) Lot moves: al ship hay `consume` en ES; al receive hay `receipt` en NL; transport usa `cogs_adjust` con qty=0.
5) Transfer allocations: `lot_id_nl` se rellena en receive para todas las allocations.
6) Expiry alerts: botón “Refresh” actualiza cache y “Marcar” pone clearance.
