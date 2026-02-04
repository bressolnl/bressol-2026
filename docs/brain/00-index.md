# Cerebro del proyecto (indice)

Objetivo: este conjunto de documentos es el "cerebro" de bressol-core para trabajar en chats separados por modulo o tema sin perder contexto.

Regla de uso:
- Cada chat debe empezar leyendo este indice y el documento del modulo correspondiente.
- Si se cambia logica o contratos, actualizar el doc del modulo y el indice.
- Evitar duplicar reglas: cada regla vive en un unico doc.

Arbol de conocimiento

1) Contexto y principios
- `docs/brain/01-contexto-y-vision.md`
- `docs/brain/02-arquitectura-general.md`

2) Mapa de repo y modulos
- `docs/brain/03-repo-y-modulos.md`

3) Datos y contratos
- `docs/brain/04-datos-y-taxonomias.md`
- `docs/brain/05-tracking-y-datalayer.md`

Costes, margenes y control de perdidas
- `docs/11-cost-margin-mvp.md`

4) Modulos del plugin (bressol-core)
- `docs/brain/modules/00-modulo-base.md`
- `docs/brain/modules/10-packs.md`
- `docs/brain/modules/11-guided-shopping.md`
- `docs/brain/modules/12-recommendations.md`
- `docs/brain/modules/13-post-add-to-cart-modal.md`
- `docs/brain/modules/14-taxonomies.md`
- `docs/brain/modules/15-crm.md`
- `docs/brain/modules/16-esp.md`
- `docs/brain/modules/17-pos.md`
- `docs/brain/modules/18-sales-analytics.md`
- `docs/brain/modules/19-inventory.md`
- `docs/brain/modules/19-markets-events-calendar.md`
- `docs/brain/modules/20-seo-pdp.md`
- `docs/brain/modules/21-purchasing.md`
- `docs/brain/modules/22-forecasting.md`

5) Theme
- `docs/brain/theme/00-bressol-theme.md`

6) UI
- `docs/brain/ui/00-navigation.md`

7) Operativa (cron, CLI, jobs)
- `docs/brain/ops/00-cron-y-cli.md`
- `docs/brain/ops/01-consent-cmp.md`
- `docs/brain/ops/02-qa-canonical.md`

Relacion con docs existentes
- `docs/00-vision-and-principles.md` y `docs/10-architecture-bressol-core.md` son la base original.
- `docs/tracking.md`, `docs/esp.md` y `docs/recommendations-and-moments.md` mantienen detalle de reglas especificas.
