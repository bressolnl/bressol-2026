# Arquitectura — Bressol (tema + plugin)

## Separación de responsabilidades

### bressol-theme (tema)
Responsable de:
- UI/UX y diseño
- Plantillas (PDP, PLP, home, landings, blog)
- Rendimiento (Core Web Vitals, CSS/JS minimal)
- SEO on-page (schema, headings, breadcrumbs, internal linking)
- Componentes reutilizables visuales

No debe contener:
- Lógica de negocio (packs, wizard, recomendaciones, CRM)
- Tracking (salvo markup necesario; el dataLayer lo empuja el plugin)

### bressol-core (plugin)
Responsable de:
- Lógica de negocio (packs, personalización, recargos)
- Guided Shopping (wizard + sesiones)
- Recomendaciones (PDP y carrito)
- Tracking (dataLayer + patrón robusto por sesión)
- Integraciones externas (GTM/Ads/email platforms) sin inyectar scripts
- CRM/ESP propio (perfiles, consentimiento, segmentos, automatizaciones)

## Arquitectura interna del plugin (bressol-core)
- Bootstrap: `src/Core/Plugin.php` registra módulos.
- Patrón modular:
  - Cada módulo implementa `register()` y engancha hooks WP/Woo.
  - Evitar lógica de negocio en el archivo principal del plugin.
- Namespaces para evitar colisiones.

## Módulos (mapa de alto nivel)
- Tracking
- Packs
- GuidedShopping (Wizard)
- Recommendations (PDP + Carrito + Modal)
- PostAddToCartModal
- CRM
- ESP

## Convenciones de datos (metas / taxonomías)
- Families por categorías (slugs estables): `oil`, `salt`, `vinegar`, `drinks`, `olives`, `tapenade`, etc.
- Packs:
  - `_bressol_pack_definition` (JSON)
  - `_bressol_pack_tier`: low|mid|high
  - `_bressol_pack_occasion`: gift|self|both
  - `_bressol_pack_focus`: CSV (ej: `oil,salt,vinegar`)
  - `_bressol_pack_themes`: CSV (ej: `cooking,borrel`)
- Taxonomía Moment: `bressol_moment` (slugs en inglés: shared_table, cooking, breakfast_snack, gift, relax, vegan, enjoyment)

## Git / flujo de trabajo
- Rama `dev` para desarrollo continuo.
- Ramas feature por módulo (ej: `feature/crm`, `feature/recommendations`).
- Docs actualizados en cada entrega relevante.
