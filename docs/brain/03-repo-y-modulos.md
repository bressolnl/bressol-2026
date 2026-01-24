# Mapa del repo y modulos

Estructura principal
- `wp-content/plugins/bressol-core/` (plugin de negocio)
  - `bressol-core.php` bootstrap + autoloader + activation hooks
  - `src/Core/` contrato de modulo
  - `src/Modules/` modulos funcionales
- `wp-content/themes/bressol-theme/` (tema UI/UX)
- `docs/` documentacion general y de modulos

Mapa de modulos (plugin)
- `Tracking/` dataLayer y eventos WooCommerce
- `Packs/` definicion de packs, UI de pack y pricing
- `GuidedShopping/` wizard + recomendaciones basicas
- `Recommendations/` reglas y bloques de recomendaciones PDP/Cart
- `PostAddToCartModal/` modal de upsell despues de add-to-cart
- `Taxonomies/` taxonomia `bressol_moment`
- `Esp/` email service provider interno
- `Crm/` CRM interno con puntos y consentimiento
- `Pos/` punto de venta interno (admin)

Mapa de frontend (tema)
- `functions.php` setup del tema (soportes basicos)
- `header.php`, `footer.php`, `index.php` esqueletos basicos
