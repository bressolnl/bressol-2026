# Modulo Purchasing

## Proposito
Gestiona proveedores, purchase orders y recepciones en una operativa de compras centrada en Espana, con recepcion base en Alicante y envio posterior a Paises Bajos.

## Entidades
- Supplier: sin PII (no emails/telefonos/personas). Notes sin PII.
- Purchase Order (PO): estado y costes base en EUR (cents).
- Receiving: recepciones parciales o completas vinculadas a PO.

## Estados de PO
`draft` -> `sent` -> `confirmed` -> `receiving` -> `closed` (+ `cancelled`).

## Modelo de coste
- Moneda: EUR.
- Importes en cents (int).
- `unit_cost_excl_tax_cents` en linea (precio sin IVA).
- `tax_rate_bp` opcional (solo referencia fiscal, sin calculo aqui).
- `customs_fees_cents` a nivel de PO (sin prorrateo por ahora).
- Shipping por producto se calcula en otro modulo; aqui solo customs/handling.
- Fechas en DB en UTC; mostrar en Europe/Amsterdam cuando aplique.

## Ubicacion
Todo se compra para Espana y se recibe en Alicante (almacen base).

## Datos sin PII
Las tablas de purchasing no guardan emails ni datos personales. Notes deben mantenerse libres de PII.

## Purchase Planning (roadmap)
Cadencia objetivo: cada 6 semanas, con recordatorio 3 semanas antes.
Dependencias de datos:
- Inventory (stock actual)
- Forecasting (historico + tendencia)
- MarketsEvents (eventos pasados y futuros en la ventana)

La logica de recomendacion se implementara en el servicio `PurchasePlanningService` y en puertos de lectura para integrar esos modulos.

## Suppliers MVP (v0.1)
Campos: supplier_code, name, lead_time_days, min_order_cents (nullable), notes (sin PII).
Validaciones basicas: code uppercase [A-Z0-9_-] (2..32), name 2..120, lead_time 0..365, MOQ >= 0 si existe, notes max 2000 sin HTML.
No se implementa delete para evitar inconsistencias futuras con POs (pendiente de integridad referencial).

## Purchase Orders MVP (v0.1)
Cabecera: supplier_id (obligatorio), po_number (opcional, unico), status, customs_fees_cents, tax_rate_bp (opcional), currency=EUR, warehouse_code.
Lineas: sku opcional, qty > 0, unit_cost_excl_tax_cents >= 0, line_total_excl_tax_cents = qty * unit_cost.
Customs solo a nivel PO; shipping per product queda en otro modulo.
Estados: draft, sent, confirmed, receiving, closed, cancelled (sin receivings ni inventario todavia).

## Receivings MVP (v0.1)
- Recepciones parciales por linea de PO.
- No puede exceder el qty pendiente por linea (received total <= ordered).
- Si un PO tiene receivings, sus lineas quedan bloqueadas para edicion.
- Aun no impacta Inventory ni Cost Ledger (pendiente v0.2).

## v0.2 Stock Intake (Woo)
- Flag `purchasing_stock_sync_enabled` OFF por defecto (activar manualmente).
- Idempotencia por receiving_id: `bressol_purchasing_once_receiving_stock_{receiving_id}`.
- Resolucion product_id: po_line.product_id > sku > skip.
- Si manage_stock esta desactivado o SKU incorrecto, se omite la linea.

## v0.2 Cost Ledger Sync (PO closed)
- Flag `purchasing_cost_ledger_sync_enabled` OFF por defecto.
- Idempotencia por po_id: `bressol_purchasing_once_po_costledger_{po_id}`.
- Registra lineas del PO + customs_fees_cents (sin shipping).
- Limitacion: v0.2 registra al cerrar PO; v0.3 puede registrar por receiving si hace falta granularidad.

## v0.2 Purchase Planning Reminder
- Ciclo base 42d, reminder 21d antes.
- Requiere configurar `purchasing_planning_next_shipment_date_utc`.
- Adapters: Inventory/Forecasting/Events/Sales; fallback Inventory a Woo (stock).
- Propuesta minima basada en forecast o ventas (si no hay datos, queda vacio).
- No crea POs automaticamente.
- Sin PII.
