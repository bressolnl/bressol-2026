# Tracking (Bressol)

Principio: WordPress NO inyecta scripts de analítica. WordPress solo empuja dataLayer.
GTM será el único responsable de tags y píxeles.

## Convención
- Todos los eventos se empujan como: { event: "nombre_evento", ...payload }
- Cuando sea posible, usaremos window.bressolDataLayerPush("event", payload)

## Eventos base
### bressol_page_view
Se envía en todas las páginas.

Payload:
- page_type: home|shop|product|cart|checkout|other
- language: determine_locale()
- currency: WooCommerce currency (si WooCommerce existe)

Ejemplo:
- event: bressol_page_view

## Eventos eCommerce (WooCommerce)
### view_item
Se envía en páginas de producto (product page).

Payload (estructura):
- ecommerce.items[]:
  - item_id (string)
  - item_name (string)
  - price (number)
  - currency (string)

Ejemplo:
- event: view_item

Notas:
- price usa wc_get_price_to_display() (precio mostrado al usuario).

### add_to_cart
Se envía tras añadir un producto al carrito.
Se guarda en sesión en servidor y se imprime en el siguiente render (wp_head).

Payload (estructura):
- ecommerce.items[]:
  - item_id (string)
  - item_name (string)
  - quantity (int)
  - price (number)
  - currency (string)

Ejemplo:
- event: add_to_cart

Notas:
- Si el producto es variable, se usa variation_id como referencia principal.

### begin_checkout
Se envía cuando el usuario entra en la página de checkout (no en order received).

Payload (estructura):
- ecommerce:
  - currency (string)
  - value (number)
  - items[]:
    - item_id (string)
    - item_name (string)
    - quantity (int)
    - price (number)
    - currency (string)

Ejemplo:
- event: begin_checkout

Notas:
- Se construye a partir del carrito actual (WC()->cart).

### purchase
Se envía en la página de “Gracias” (order received) tras completar una compra.

Payload (estructura):
- ecommerce:
  - transaction_id (string)
  - currency (string)
  - value (number)        # total del pedido
  - tax (number)
  - shipping (number)
  - coupon (string|null)  # códigos separados por coma si hay varios
  - items[]:
    - item_id (string)
    - item_name (string)
    - quantity (int)
    - price (number)      # total por unidad (según Woo)
    - currency (string)

Ejemplo:
- event: purchase

Notas:
- Se evita duplicado por sesión usando una key ligada al order_id.
- Se obtiene order_id desde order-received en la URL.