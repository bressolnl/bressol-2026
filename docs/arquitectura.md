# Arquitectura del proyecto (Bressol)

## Principios
- WordPress core y datos (uploads, DB) NO forman parte del repo.
- El repo controla únicamente:
  - `wp-content/plugins` (plugins propios)
  - `wp-content/themes` (tema propio)
  - `docs/` (documentación)
- Objetivo: código modular, escalable, mantenible, orientado a futuro (SEO, funnels, ads, CRM/ESP).

---

## Tema (bressol-theme)
Responsable de:
- Diseño (UI/UX)
- Plantillas (PDP, PLP, home, landings, blog)
- Rendimiento (Core Web Vitals, CSS/JS minimal)
- SEO on-page (schema, headings, breadcrumbs, internal linking)
- Estilos y componentes reutilizables

No debe contener:
- Lógica de negocio (packs, recomendaciones, wizard, CRM)
- Tracking (salvo markup necesario o contenedores visuales; el dataLayer lo empuja el plugin)

---

## Plugin (bressol-core)
Responsable de:
- Lógica de negocio (Packs, personalización, precios con recargos)
- Guided Shopping (wizard + sesiones)
- Recomendaciones (upsells/cross-sells) en PDP y carrito
- Tracking (dataLayer, colas por sesión, eventos eCommerce)
- Integraciones externas (GTM, Ads, email platforms) **sin inyectar scripts de analítica**
- CRM/ESP propio (futuro: perfiles, consentimiento, segmentos, automatizaciones)

### Arquitectura interna del plugin
- Bootstrap: `src/Core/Plugin.php` registra módulos
- Patrón modular:
  - Cada módulo implementa su `register()` y engancha hooks de WP/Woo
  - Evitar lógica en el archivo principal del plugin
- Namespaces para evitar colisiones

---

## Módulos implementados / en curso

### Tracking
- Empuja eventos a `window.dataLayer`
- No inyecta scripts de analítica (GTM gestiona tags/píxeles)
- Patrón de eventos “robusto con navegación”:
  - Guardar payload en `WC()->session`
  - Imprimir en el siguiente render vía `wp_head` (para clicks, add/remove, etc.)

### Packs
- Definición de pack en meta `_bressol_pack_definition` (JSON)
- UI admin para editar pack (metabox)
- Campos de clasificación de pack (metas):
  - `_bressol_pack_tier`: low|mid|high
  - `_bressol_pack_occasion`: gift|self|both
  - `_bressol_pack_focus`: CSV (ej: `oil,salt,vinegar`)
  - `_bressol_pack_themes`: CSV (ej: `cooking,borrel`)
- Frontend: selects por slot, recargos (surcharge), permitir repetir productos si `max > 1`

### GuidedShopping (Wizard)
- Shortcode para guiar la visita y recomendar packs/upsells
- Persistencia temporal por sesión (WizardSession)
- Recomendadores MVP:
  - Packs por tier/occasion (y fallback por precio)
  - Upsells en contexto de wizard (eventos dedicados en tracking)

### Recommendations (PDP + carrito)
- PDP: bloque “Recomendado para ti” basado en:
  - familia detectada por categorías (slugs) y/o fallback por metas de pack (focus/themes)
- Carrito: bloque “Recomendado para completar tu compra” basado en familias presentes en el carrito
- Click tracking robusto:
  - query params → captura server-side → sesión → dataLayer en la página destino
- Nota WooCommerce Blocks:
  - Hooks clásicos no siempre funcionan en páginas block (ej. cart block)
  - Para MVP se usa carrito clásico `[woocommerce_cart]` por control y previsibilidad

---

## Convenciones de datos (metas / taxonomías)
- Families por categorías (slugs estables):
  - `oil`, `salt`, `vinegar`, `drinks`, `olives`, `tapenade`, etc.
- Packs usan metas `focus/themes` para recomendaciones y wizard, incluso si faltan categorías (fallback).

---

## Git / flujo de trabajo
- Rama `dev` para desarrollo continuo
- Commits pequeños y descriptivos
- Docs actualizados en cada entrega relevante
- Repo “limpio” al final del día (`git status` sin cambios)