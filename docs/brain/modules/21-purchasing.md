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
