# Tracking y dataLayer

Principio base
- WordPress NO inyecta scripts de analitica.
- WordPress solo empuja a `window.dataLayer`.
- GTM (u otro contenedor) carga tags/pixeles.

Implementacion central
- `TrackingModule` encola `tracking.js` y empuja `bressol_page_view`.
- `TrackingModule` imprime colas en `wp_head` desde sesiones de Woo.
- `tracking.js` define `window.bressolDataLayerPush` y captura remove-from-cart en Woo Blocks.

Patron robusto con navegacion
1) anadir query params al link
2) capturar en `template_redirect`
3) guardar payload en `WC()->session`
4) redirect a URL limpia
5) imprimir evento en `wp_head`

Eventos base (WooCommerce)
- `view_item` en PDP (`WooEvents::pushViewItem`)
- `add_to_cart` encola payload en sesion (`WooEvents::pushAddToCart`)
- `view_cart` en `CartEvents::pushViewCart`
- `remove_from_cart` encola payload en sesion (`CartEvents::queueRemoveFromCart`)
- `begin_checkout` en `CheckoutEvents::pushBeginCheckout`
- `purchase` en `PurchaseEvents::pushPurchase`

Eventos negocio (Bressol)
- `bressol_reco_view` (PDP) y `bressol_cart_reco_view` (Cart).
- `bressol_reco_click` y `bressol_cart_reco_click` (patron robusto).
- `guided_upsell_add_to_cart` (wizard -> session cola).

Documento base detallado
- `docs/tracking.md` contiene payloads completos y notas.
