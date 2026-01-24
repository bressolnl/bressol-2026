# Contexto y vision

Proyecto: e-commerce WordPress + WooCommerce desarrollado desde cero con logica de negocio en plugin propio.

Objetivos principales
- SEO tecnico y de contenidos.
- Experiencia de compra premium.
- Sistema de packs y upsells.
- Funnels y tracking orientado a Ads.
- Escalabilidad futura (pais, nuevos packs).

Principios no negociables
- WordPress core y datos (uploads, DB) NO estan en el repo.
- El repo controla solo: `wp-content/plugins`, `wp-content/themes`, `docs/`.
- Tema: solo UI/UX, plantillas y rendimiento. Sin logica de negocio.
- Plugin: toda la logica de negocio, dataLayer, integraciones (sin inyectar scripts).
- Tracking: WordPress NO inyecta scripts; solo empuja a `window.dataLayer`.
- Privacidad por diseno: minimizacion de datos, consentimientos separados y feature flags.
