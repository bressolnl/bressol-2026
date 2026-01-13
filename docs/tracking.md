# Tracking (Bressol)

Principio: WordPress NO inyecta scripts de analítica. WordPress solo empuja `dataLayer`.
GTM será el único responsable de tags, píxeles y vendors.

## Convención
- Todos los eventos se empujan como: `{ event: "nombre_evento", ...payload }`
- `window.dataLayer` debe existir siempre (si no existe, se crea).
- Cuando sea posible, se estandariza vía helper (tracking.js):
  - `window.bressolDataLayerPush(eventName, payload)`

## Patrón robusto (navegación / redirects)
Para eventos que dependen de un click y navegan a otra página:
1) Añadir query params al link (`?bressol_*`)
2) Capturar server-side en `template_redirect`
3) Guardar payload en `WC()->session`
4) Redirigir a URL limpia (evita duplicados)
5) Imprimir el evento en el siguiente render (hook `wp_head`) leyendo la sesión

Este patrón se usa para:
- clicks en recomendaciones
- acciones de carrito (cuando proceda)
- eventos que no se pueden garantizar solo con JS

---

## Eventos base
### bressol_page_view
Se envía en todas las páginas.

Payload:
- `page_type`: home|shop|product|cart|checkout|other
- `language`: `determine_locale()`
- `currency`: WooCommerce currency (si WooCommerce existe)

Ejemplo:
- `event: bressol_page_view`

---

## Eventos eCommerce (WooCommerce)
### view_item
Se envía en páginas de producto.

Payload:
- `ecommerce.items[]`:
  - `item_id` (string)
  - `item_name` (string)
  - `price` (number)
  - `currency` (string)

Notas:
- `price` usa `wc_get_price_to_display()`.

### add_to_cart
Se envía tras añadir un producto al carrito.
Se guarda en sesión y se imprime en el siguiente render (`wp_head`).

Payload:
- `ecommerce.items[]`:
  - `item_id`
  - `item_name`
  - `quantity`
  - `price`
  - `currency`

Notas:
- Si el producto es variable, se usa `variation_id` como referencia principal.

### view_cart
Se envía cuando el usuario visita la página de carrito.

Payload:
- `ecommerce`:
  - `currency`
  - `value` (total del carrito)
  - `items[]` (item_id, item_name, quantity, price, currency)

Notas:
- Se construye a partir del carrito actual (`WC()->cart`).

### begin_checkout
Se envía cuando el usuario entra en la página de checkout (no en order received).

Payload:
- `ecommerce`:
  - `currency`
  - `value`
  - `items[]`

Notas:
- Se construye a partir del carrito actual (`WC()->cart`).

### purchase
Se envía en la página “Gracias” (order received).

Payload:
- `ecommerce`:
  - `transaction_id`
  - `currency`
  - `value` (total)
  - `tax`
  - `shipping`
  - `coupon` (string|null; múltiples separados por coma)
  - `items[]`

Notas:
- Se evita duplicado por sesión usando una key ligada al `order_id`.
- `order_id` se obtiene desde `order-received` en la URL.

### remove_from_cart
Se envía cuando el usuario elimina un producto del carrito.

Payload:
- `ecommerce.items[]` (cuando se puede):
  - `item_id` (opcional)
  - `item_name` (opcional)
  - `quantity` (int)

Notas:
- En carritos clásicos, puede depender de recarga completa.
- En Woo Blocks, puede emitirse en frontend sin refresh, pero el DOM no siempre expone `item_id`.

---

## Eventos Bressol (negocio)

### guided_upsell_add_to_cart
Se envía cuando un upsell recomendado por el wizard se añade al carrito.
Patrón: cola en sesión → impresión en `wp_head`.

Payload (mínimo recomendado):
- `context`: `wizard`
- `recommended_product_id`
- `reco_type` (opcional)

### bressol_reco_view
Impresión de recomendaciones en PDP (cuando se renderiza el bloque).

Payload:
- `context`: `pdp`
- `source_product_id`
- `family`
- `recommended_product_ids[]`

### bressol_reco_click
Click en recomendación desde PDP.
Patrón robusto: query params → captura server-side → sesión → impresión en `wp_head`.

Payload:
- `context`: `pdp`
- `source_product_id`
- `recommended_product_id`
- `reco_type` (upsell_pack|cross_sell|...)

### bressol_cart_reco_view
Impresión de recomendaciones en carrito (cuando se renderiza el bloque).

Payload:
- `context`: `cart`
- `families[]`
- `recommended_product_ids[]`

### bressol_cart_reco_click
Click en recomendación desde carrito.
Patrón robusto: query params → captura server-side → sesión → impresión en `wp_head`.

Payload:
- `context`: `cart`
- `source`: `cart`
- `recommended_product_id`
- `reco_type`

---

## Notas operativas
- Mantener los nombres de eventos estables (impacta GTM/GA4).
- Evitar duplicados:
  - limpiar query params con redirect
  - unset de la key en sesión tras imprimir
- Evitar depender exclusivamente de JS cuando hay navegación.