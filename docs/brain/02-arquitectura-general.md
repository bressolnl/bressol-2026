# Arquitectura general

Separacion de responsabilidades
- `bressol-theme`: UI/UX, plantillas, SEO on-page y rendimiento.
- `bressol-core` (plugin): logica de negocio, packs, guided shopping, recomendaciones, tracking, CRM, ESP, POS, analytics, inventario, eventos, forecasting y SEO.

Bootstrap del plugin
- Archivo principal: `wp-content/plugins/bressol-core/bressol-core.php`.
- Autoloader simple por namespaces:
  - `Bressol\Core\` -> `src/Core/`
  - `Bressol\Modules\` -> `src/Modules/`
- Activacion:
  - Instala tablas ESP y CRM.
  - Programa cron de ESP, CRM y POS.
- Instaladores (admin_init / WP-CLI):
  - POS, MarketsEvents, Forecasting (tablas propias).
- Desactivacion:
  - Limpia cron de ESP, CRM y POS.

Patron modular
- `src/Core/Plugin.php` crea instancias de cada modulo.
- Cada modulo implementa `ModuleInterface::register()` y engancha hooks.
- No poner logica de negocio en el archivo principal del plugin.

Modulos actuales (orden de carga)
- Tracking
- Packs
- GuidedShopping
- Recommendations
- PostAddToCartModal
- Taxonomies
- Seo
- ESP
- CRM
- POS
- SalesAnalytics
- MarketsEvents
- Inventory
- Forecasting