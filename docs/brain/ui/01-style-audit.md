# Auditoria de estilo Bressol.nl (UI/UX + SEO + CRO)

Fecha: 2026-01-25  
Rol: Lead UX Engineer + UI Designer + SEO on-page auditor

## Resumen ejecutivo
Lo que funciona:
- Base visual limpia y consistente en componentes clave (cards, grids, CTA panel).
- Plantillas de taxonomias con estructura SEO basica (H1 + intro + loop + longform + FAQ).
- Separacion theme/plugin respetada en el codigo revisado.

Lo que falla o es debil:
- Sistema visual demasiado corto: faltan tokens de tipografia, line-height, estados y utilities base.
- Contenido de venta insuficiente en PLPs (categorias y momentos) y paginas clave.
- Accesibilidad y estados de foco incompletos; riesgo de percepcion "no premium".
- SEO on-page depende demasiado de contenido manual; sin fallback ni schema FAQ.

## Inventario de plantillas y secciones (theme)

### Estructura global
- `header.php`: marca, menu principal, botones de busqueda y carrito (paneles laterales), overlay.
- `footer.php`: footer basico con copyright.
- `index.php`: fallback generico con hero simple y CTA (si no hay posts).
- `front-page.php`: home con hero, momentos, paquetes, origen, newsletter.
- `page.php` y `single.php`: contenido generico (H1 + contenido).
- `page-advies.php`: hero, formulario (shortcode), FAQ, CTA final.
- `page-momenten.php`: listado de taxonomia `bressol_moment`.
- `page-pakketten.php`: listado de packs + filtros via query string, CTA a categorias.
- `taxonomy-product_cat.php`: PLP categoria con intro/longform/FAQ.
- `taxonomy-bressol_moment.php`: PLP momento con intro/longform/FAQ + fallback cuando Woo inactivo.

### Jerarquia visible por plantilla (resumen)
- `front-page.php`:
  - H1 hero + lead + CTA primario/secondary.
  - Bloque "Momenten" (cards).
  - Bloque "Pakketten" (cards placeholders).
  - Bloque "Oorsprong" (card).
  - Bloque "Blijf op de hoogte" (form markup sin accion).
- `page-advies.php`:
  - H1 + lead.
  - Formulario (shortcode).
  - FAQ (3 items hardcoded).
  - CTA final a packs.
- `page-momenten.php`:
  - H1 + lead + CTA.
  - Grid de momentos (cards).
- `page-pakketten.php`:
  - H1 + leads (2).
  - Grid productos (Woo loop).
  - Empty state.
  - CTA a categorias (links).
- `taxonomy-product_cat.php`:
  - H1 + intro (term meta o descripcion).
  - CTA "Liever advies op maat?"
  - Loop de productos.
  - Longform (term meta).
  - FAQ (term meta JSON).
- `taxonomy-bressol_moment.php`:
  - H1 + intro (term meta o descripcion).
  - CTA "Liever advies op maat?"
  - Loop de productos o mensaje "Binnenkort beschikbaar" si Woo inactivo.
  - Longform (term meta).
  - FAQ (term meta JSON).

### Deltas entre taxonomias
- `product_cat`: si Woo no esta activo, termina tras intro (no muestra longform/FAQ).
- `bressol_moment`: si Woo no esta activo, muestra mensaje y puede mostrar longform/FAQ.
- Canonical custom solo para `bressol_moment` cuando no hay RankMath/Yoast.

### Secciones de venta faltantes (tema)
- Beneficios/USP cerca del hero en PLP (calidad, origen, artesania).
- Trust signals (origen, premios, envio, devoluciones) en PLP y home.
- Cross-sell editorial en taxonomias (momentos -> packs/categorias recomendadas).
- Repeticion de CTA tras loop y al final del longform.
- Microcopy en cards y CTA paneles (premium/valencian vibes).

## Sistema visual actual (CSS)

### Tokens y variables existentes
- Colores: `--bressol-black`, `--bressol-gold`, `--bressol-gray-100`, `--bressol-white`, `--bressol-border-soft`, `--bressol-overlay`.
- Espaciado: `--bressol-space-xs/sm/md/lg`.
- Tipografia: `--bressol-font-body`, `--bressol-font-size-sm/md`.
- Sombra/radius: `--bressol-shadow-sm`, `--bressol-radius-sm`.

### Componentes base existentes
- Contenedores/sections: `.bressol-container`, `.bressol-section`, `.bressol-main`.
- Tipografia: `.bressol-title`, `.bressol-section-title`, `.bressol-lead`.
- CTA: `.bressol-button`, `.bressol-link`, `.bressol-cta-panel`.
- Cards: `.bressol-card` + elementos.
- FAQ: `.bressol-faq` + variantes `__question/__q` y `__answer/__a`.
- Header/nav/panels: `.bressol-site-header`, `.bressol-nav`, `.bressol-panel`.

### Deuda visual / inconsistencias
- Tipografia: no hay escala completa ni line-height base; riesgo de densidad irregular.
- Estados: faltan estilos `:focus-visible`, `:active`, `:disabled` en botones, enlaces y cards.
- Duplicacion de clases FAQ (`__question` vs `__q`) y respuestas (`__answer` vs `__a`).
- Breakpoints: solo un breakpoint (720px) para nav; no ajustes de layout globales.
- Formulario: estilos limitados a busqueda; el resto de inputs heredan defaults.
- Branding: se define Montserrat pero no se carga desde el theme (dependencia externa).
- Microinteracciones: hover simple y sombra; sin refinamiento para premium feel.

### Capa de sistema minima propuesta (sin implementar)
- Tokens: ampliar escala tipografica (xs, sm, md, lg, xl), line-height y letter-spacing.
- Utilities: `.u-stack`, `.u-flow`, `.u-muted`, `.u-center`, `.u-max-w`.
- Componentes base: `button` con variantes (primary/ghost), `card`, `section`, `grid`.

## Auditoria SEO on-page (theme + PDP plugin)

### Hallazgos clave
- H1 unico presente en home, paginas y taxonomias.
- Intro/longform/FAQ en taxonomias depende 100% de term meta; riesgo de thin content.
- FAQ renderizado en HTML sin schema JSON-LD.
- Canonical custom solo en `bressol_moment` y solo si no hay RankMath/Yoast.
- PDP (plugin) inyecta intro/longform/origen/FAQ y enlaces a momentos/categorias.

### Riesgos SEO
- Categorias/momentos sin intro/longform/FAQ => thin content.
- `product_cat` no muestra longform/FAQ si Woo esta inactivo (corte de contenido).
- Duplicidad potencial entre textos de momentos y categorias si no hay guidelines editoriales.
- Newsletter form en home no aporta contenido indexable ni conversion real (placeholder).

### Checklist SEO por plantilla
- `taxonomy-product_cat.php`:
  - H1 unico.
  - Intro visible (meta o description).
  - Loop Woo y longform/FAQ despues del loop.
  - Fallback si Woo inactivo (actualmente corta antes de longform/FAQ).
- `taxonomy-bressol_moment.php`:
  - H1 unico.
  - Intro visible.
  - Loop Woo o mensaje "Binnenkort beschikbaar".
  - Longform/FAQ visibles aunque Woo inactivo.
  - Canonical custom si no hay plugin SEO.
- `front-page.php`:
  - H1 unico y lead.
  - Links internos a momentos y packs.
- PDP (plugin SEO):
  - Intro, longform, origen, FAQ renderizados en orden.
  - Links a momentos/categorias si es pack.

### Top 10 arreglos SEO (propuestos)
1. Fallback de contenido si term meta vacio (mostrar descripcion o texto editorial).
2. Mantener longform/FAQ en `product_cat` incluso con Woo inactivo.
3. Añadir schema FAQ (JSON-LD) para taxonomias y PDP.
4. Añadir bloque "contenido editorial" en PLP con H2/H3 coherentes.
5. Asegurar CTA y enlaces internos contextuales (momentos -> categorias).
6. Validar canonical en `bressol_moment` con y sin plugin SEO.
7. Normalizar orden de headings (H1, H2, H3) en todas las plantillas.
8. Evitar placeholders en home si hay data real disponible (momentos/packs).
9. Asegurar que los formularios tengan `action` real o se retiren del DOM indexable.
10. Revisar consistencia de microcopy para evitar thin content repetido.

## Auditoria CRO (conversion)

### Fricciones detectadas
- Header sin CTA de compra visible (solo iconos).
- CTA no repetidos al final de PLPs (perdida de intent).
- Pages de packs y momentos carecen de beneficios/trust antes del grid.
- Pakketten usa filtros via query string sin UI visible (descubribilidad baja).
- Newsletter en home no funcional (input sin `name` ni `action`).

### Quick wins (solo visual/markup)
1. Añadir bloque de beneficios (3 bullets) en PLP antes del loop.
2. Repetir CTA primario al final de longform en taxonomias.
3. Añadir microcopy de confianza en CTA paneles (origen, curacion).
4. Convertir links de "advies" en botones secundarios en PLP.
5. Añadir badge visual (texto) de "Valencian craftsmanship" en cards.
6. Añadir bloque "Por que Bressol" en home con 2-3 columnas.
7. Añadir CTA secundario en header (link sutil) a packs o advies.
8. Añadir separacion visual (divider/section) antes de FAQ.
9. Añadir estado hover/focus visible a links `.bressol-link`.
10. Reordenar CTA panel en `page-advies.php` para que el texto y CTA tengan jerarquia clara.

## Performance / A11Y quick check (sin herramientas externas)

Prioridad alta:
- Falta `:focus-visible` para enlaces y botones (accesibilidad + percepcion premium).
- Falta skip link y landmarks claros (header/nav/main).
- `autofocus` en input de busqueda en panel oculto puede ser problematico.

Prioridad media:
- Only 1 breakpoint; densidad en mobile depende de defaults de navegador.
- No hay estilos para estados `:disabled` ni `:active`.

Prioridad baja:
- Formulario newsletter sin `name` y sin `action` (no funcional).
- No se ve gestion de focus en panels (depende de JS externo no visto en theme).

## Backlog priorizado

### P0 (bloquea premium/SEO/ventas)
1. Accesibilidad y estados de foco visibles en links/botones/cards.
2. Consistencia de contenido en PLPs (intro + longform + FAQ con fallback).
3. CTA repetido y jerarquia clara en PLPs (antes y despues del loop).
4. Normalizacion de FAQ (clases unificadas) para consistencia visual.

Criterios de aceptacion (P0):
- Todos los enlaces/botones tienen `:focus-visible` perceptible.
- `taxonomy-product_cat.php` muestra longform/FAQ incluso con Woo inactivo.
- CTA primario visible en hero y al final del contenido en PLPs.
- FAQ renderiza con una sola convención de clases.

### P1
1. Sistema tipografico (escala + line-height).
2. Tokens de espaciado adicionales + utilities para ritmo vertical.
3. Bloques de beneficios/trust en home y PLPs.
4. SEO schema FAQ (taxonomias + PDP).

### P2
1. Variantes de botones (primary/ghost) y estados.
2. Microinteracciones refinadas (hover/active sutiles).
3. Mejoras de formularios (estilos consistentes).

## Riesgos y "no tocar"

- No tocar: logica de negocio, tracking, dataLayer ni integraciones (plugin).
- No introducir scripts de analitica desde WordPress.
- Canonical de `bressol_moment` debe respetar RankMath/Yoast.
- No introducir PII en logs ni en outputs.

## Plan de PRs (sin implementar)

### PR1: Limpieza/normalizacion CSS + tokens (theme)
- Alcance: variables de tipografia/espaciado, focus-visible, estados base, FAQ unificado.
- Archivos: `wp-content/themes/bressol-theme/style.css`.
- Riesgos: cambios visuales en todo el sitio.
- QA manual: revisar header, botones, cards, forms, FAQ en home y PLPs.

### PR2: Ajustes semanticos/markup taxonomias (theme)
- Alcance: CTA repetido, fallback longform/FAQ, headings consistentes.
- Archivos: `taxonomy-product_cat.php`, `taxonomy-bressol_moment.php`.
- Riesgos: cambios en orden de contenido, posibles impactos SEO positivos/negativos.
- QA manual: H1 unico, intro/longform/FAQ visibles, canonical correcto.

### PR3: Mejoras header/nav (solo UI)
- Alcance: CTA secundario visible, skip link, estados de foco.
- Archivos: `header.php`, `style.css`.
- Riesgos: layout en mobile.
- QA manual: header responsive, foco visible, paneles no rompen layout.

## PR2 (taxonomias) estructura final
- A-G: header (H1 + intro + CTA), beneficios, productos, longform, CTA final, FAQ, micro-CTA.
- Fallbacks: si no hay intro/longform => texto premium minimo; si no hay FAQ => oculto.
- Woo inactivo o sin productos => mensaje neutro y contenido SEO se mantiene.

