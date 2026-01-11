# Tracking (Bressol)

Principio: WordPress NO inyecta scripts de analítica. WordPress solo empuja dataLayer.
GTM será el único responsable de tags y píxeles.

## Eventos base
- event: bressol_page_view
  - page_type: home|shop|product|cart|checkout|other
  - language: determine_locale()
  - currency: WooCommerce currency

## Convención
- Todos los eventos se empujan como: { event: "nombre_evento", ...payload }
- Cuando sea posible, usaremos window.bressolDataLayerPush("event", payload)