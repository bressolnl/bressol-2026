# POS MVP QA Runbook (Manual DoD)

## Preconditions
- WordPress + WooCommerce activos.
- Plugin `bressol-core` activo.
- Al menos 2 productos publicados (con precio) y uno con SKU.
- Al menos 1 mercado POS activo en `POS > Mercados y ajustes`.
- Usuario admin con capability `manage_woocommerce`.
- Cliente CRM de prueba:
  - Cliente A: `status=active`, `loyalty_enabled=1`, sin opt_out en ESP.
  - Cliente B: `status=active`, `loyalty_enabled=0`.
  - Cliente C: `status=anonymized` o `deleted`.
- Un token POS público válido para Cliente A (desde `POS > Clientes POS`).

---

## POS Cases

### 1) Venta anónima
- Ir a `POS > Nueva venta`.
- Seleccionar mercado activo.
- Añadir 1 producto al carrito.
- Crear pedido sin buscar cliente.
- **Expected:**
  - Pedido creado en Woo con status `completed`.
  - Meta `_bressol_pos_channel = pos`.
  - Meta `_bressol_pos_customer_id` ausente.

### 2) Venta con cliente (lookup por token)
- Buscar token en sección cliente y validar resumen.
- Añadir producto y crear pedido.
- **Expected:**
  - Meta `_bressol_pos_customer_id` presente (CRM id).
  - Meta `_bressol_pos_market_*` correctas.

### 3) Opt-in loyalty (POS)
- Cliente B (loyalty OFF) con checkbox `Loyalty` marcado.
- Crear pedido.
- **Expected:**
  - Loyalty se activa (audit `customer_loyalty_enabled`).
  - Si order status completes, se generan puntos (ledger).

### 4) Opt-in marketing (POS)
- Cliente A sin opt_out en ESP, marcar `Marketing`.
- Crear pedido.
- **Expected:**
  - Marketing opt-in aplicado (audit `customer_marketing_opt_in`).

### 5) Canje de puntos
- Cliente A con saldo suficiente.
- Introducir `puntos_a_canjear` válido (>= min, <= balance, <= max%).
- Crear pedido.
- **Expected:**
  - Descuento tipo cupón aplicado (reduce base e IVA).
  - Metas `_bressol_pos_points_redeemed`, `_bressol_pos_redemption_value_cents`.
  - Redención creada en CRM (`points_redemptions`).

### 6) Mercado obligatorio
- Intentar crear pedido sin mercado seleccionado.
- **Expected:** error de validación, no pedido.

### 7) Coste de mercado editable
- Cambiar coste manualmente y crear pedido.
- **Expected:** meta `_bressol_pos_market_cost_cents` refleja el valor editado.

---

## Privacy / PII

### HTML
- En `POS > Nueva venta` y `POS > Clientes POS`, revisar HTML renderizado:
  - **Expected:** no aparece email/teléfono/dirección en listados o resultados.

### Network
- En DevTools > Network (AJAX):
  - `bressol_pos_find_customer` response **no** incluye email/teléfono/dirección.
  - `bressol_pos_create_order` response **no** incluye PII.

---

## CRM Cases

### 1) Awarding solo si loyalty
- Cliente A con loyalty ON → pedido POS completado.
- **Expected:** ledger creado.

### 2) Loyalty OFF sin opt-in
- Cliente B loyalty OFF, sin opt-in.
- **Expected:** no ledger + audit `points_skipped_not_enrolled`.

### 3) Redemptions bloqueadas sin loyalty
- Cliente B loyalty OFF intenta canje.
- **Expected:** UI deshabilitada + backend 422.

---

## ESP Cases

### Marketing opt-in respeta opt_out
- Cliente con opt_out en ESP.
- Intentar marcar marketing y crear pedido.
- **Expected:** marketing no se activa.

---

## Fiscalidad (Descuento reduce IVA)

### Ajustes a capturar (antes de ejecutar)
- WooCommerce → Settings → Tax:
  - Prices entered with tax: Yes/No
  - Display prices during cart/checkout
  - Round tax at subtotal level

### Checklist ejecutable (paso a paso)
1. Preparar 2 productos con tax classes distintas (p.ej. 10% y 21%).
2. Crear pedido POS **sin canje** con ambos productos y mercado activo.
3. Abrir el pedido en admin y capturar:
   - Total, Total tax
   - Taxes por línea (line item taxes)
4. Crear pedido POS **con canje** (puntos válidos) con el mismo carrito.
5. Abrir el pedido en admin y capturar:
   - Total, Total tax
   - Taxes por línea
6. Comparar:
   - `total_tax` debe ser menor con canje.
   - Taxes por línea deben disminuir proporcionalmente en ambos tax rates.

### Caso A — Prices entered with tax = No
- Ejecutar checklist con `Prices entered with tax = No`.

### Caso B — Prices entered with tax = Yes
- Ejecutar checklist con `Prices entered with tax = Yes`.

### Evidencia esperada
- `total_tax` ↓ con canje
- Taxes por línea ↓ con canje (para cada tax class)

---

## WP-CLI / DB Queries (verify)

### Ver metas de pedido POS
```
wp db query "SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id = <ORDER_ID> AND meta_key LIKE '_bressol_pos_%';"
```

### Ver opt-ins en pedido
```
wp db query "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = <ORDER_ID> AND meta_key IN ('_bressol_loyalty_opt_in','_bressol_marketing_opt_in');"
```

### Ledger y redemptions CRM
```
wp db query "SELECT * FROM wp_bressol_crm_points_ledger WHERE customer_id = <CRM_ID> ORDER BY earned_at DESC LIMIT 5;"
wp db query "SELECT * FROM wp_bressol_crm_points_redemptions WHERE customer_id = <CRM_ID> ORDER BY created_at DESC LIMIT 5;"
```

### Meta POS en CRM (pos_public_id)
```
wp db query "SELECT * FROM wp_bressol_crm_customer_meta WHERE customer_id = <CRM_ID> AND meta_key = 'pos_public_id';"
```

### Auditoría CRM (POS actions)
```
wp db query "SELECT action, entity_type, entity_id, context, created_at FROM wp_bressol_crm_audit_logs WHERE action LIKE 'pos_%' OR action LIKE 'customer_%' ORDER BY created_at DESC LIMIT 20;"
```

---

## Final Checklist (Pass/Fail)
- [ ] Venta anónima crea pedido POS sin PII.
- [ ] Venta cliente por token crea pedido con `_bressol_pos_customer_id`.
- [ ] Venta POS crea pedido con status `completed`.
- [ ] Opt-in loyalty activa loyalty y genera puntos cuando corresponde.
- [ ] Opt-in marketing respeta opt_out ESP.
- [ ] Canje puntos aplica descuento (cupón), metas y redención CRM.
- [ ] Mercado obligatorio validado.
- [ ] Coste mercado editable y persistido.
- [ ] Sin PII en HTML/Network.
- [ ] Auditoría POS/CRM sin PII en context.

---

## Formulario de resultados (evidencia fiscal)

### Configuración fiscal
- Prices entered with tax: [Yes/No]
- Display prices during cart/checkout: [Incl/Excl]
- Round tax at subtotal level: [Yes/No]
 - Tax classes usadas: [10%, 21%]

### Pedido SIN canje
- Order ID:
- Total:
- Total tax:
- Line item taxes (por producto):
 - Capturas: [screenshot totals + line taxes]

### Pedido CON canje
- Order ID:
- Total:
- Total tax:
- Line item taxes (por producto):
- Descuento aplicado (cupón): [code]
 - Capturas: [screenshot totals + line taxes]

### Conclusión
- ¿El canje reduce base imponible e IVA? [Sí/No]
- Observaciones:

### Fallback si Woo no prorratea (criterio activación)
- Condición verificable:
  - `total_tax` no disminuye con canje, o
  - una o más líneas no reducen su tax proporcionalmente al descuento.
- Fallback mínimo:
  - prorrateo del descuento por líneas y recálculo de impuestos por tax class.
  - activar solo si se detecta incumplimiento en QA.
