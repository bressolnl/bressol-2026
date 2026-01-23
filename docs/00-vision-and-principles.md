# Bressol — Visión y principios del proyecto

## Objetivo
Construir una base técnica modular, escalable y mantenible para eCommerce (WooCommerce) orientada a crecimiento: SEO, funnels, ads, CRM/ESP, automatizaciones y reporting.

## Principios no negociables
- WordPress core y datos (uploads, DB) NO forman parte del repo.
- El repo controla únicamente:
  - `wp-content/plugins` (plugins propios)
  - `wp-content/themes` (tema propio)
  - `docs/` (documentación)
- Código modular y desacoplado por dominios (evitar “god classes” y lógica en archivos bootstrap).
- Observabilidad: auditoría y trazabilidad de acciones sensibles (CRM/ESP).
- Privacidad por diseño:
  - Minimización de datos.
  - Consentimientos separados por propósito (marketing ≠ loyalty, salvo decisión explícita).
  - Feature flags para funciones sensibles (ej. export GDPR) por defecto OFF.

## Tracking / Analítica (principio)
- WordPress NO inyecta scripts de analítica.
- WordPress solo empuja eventos a `window.dataLayer`.
- GTM (u otro contenedor) es el único responsable de cargar tags/píxeles/vendors (GA4, Meta, etc.).
- Para eventos con navegación/redirect, se usa patrón robusto: query params → captura server-side → sesión → redirect limpio → push en `wp_head`.

## Convenciones generales
- Namespaces estrictos para evitar colisiones.
- Hooks registrados en módulos (no en archivos sueltos).
- Commits pequeños y descriptivos.
- Repo limpio al final del día (`git status -sb` sin cambios).
