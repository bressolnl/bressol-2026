# Modulo Recommendations

Proposito
- Reglas de recomendacion en PDP y Cart.
- Click tracking robusto con query params + session.
- Prefill de packs (upgrade) cuando aplica.

Componentes y archivos
- Domain:
  - `RecommendationRules.php` fuente unica de reglas.
  - `FamilyResolver.php` (resolver alternativo; reglas similares).
- Frontend:
  - `ProductPageRecommendations.php` bloque en PDP.
  - `CartRecommendations.php` bloque en carrito.
  - `RecoClickCapture.php` captura clicks en PDP.
  - `CartRecoClickCapture.php` captura clicks desde cart.

Reglas principales (resumen)
- Families: `packs`, `borrel_food`, `drinks`, `oil`, `sweet`, `rice`, `other`.
- PDP:
  - `oil`: pack focus oil + salt/vinegar.
  - `drinks`: pack borrel + borrel_food.
  - `borrel_food`: pack borrel + beer/aperitief + borrel_food.
- Cart:
  - similar a PDP, basado en families presentes.
- Modal extras: usa `extraTargetCategorySlugs` (borrel_food -> solo beer/aperitief).

Tracking y limpieza de URL
- PDP: links con `bressol_reco_src` y `bressol_reco_type`.
- Cart: links con `bressol_cart_reco_src` y `bressol_cart_reco_type`.
- Los capturers guardan payload en sesion y hacen redirect a URL limpia.
- Eventos impresos luego por `TrackingModule` en `wp_head`.

Prefill de packs
- Si el recomendado es pack y hay slot util:
  - cambia `reco_type` a `upgrade_pack`.
  - agrega `bressol_prefill_slot` y `bressol_prefill_product_id`.

Documento base detallado
- `docs/recommendations-and-moments.md`
