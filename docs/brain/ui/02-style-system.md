# Style system base (theme)

Objetivo: sistema minimo, premium y consistente para UI/UX en `bressol-theme`.

## Tokens definidos

| Categoria | Tokens | Nota |
| --- | --- | --- |
| Colores | `--bressol-color-bg`, `--bressol-color-surface`, `--bressol-color-text`, `--bressol-color-muted`, `--bressol-color-border`, `--bressol-color-accent`, `--bressol-color-accent-soft`, `--bressol-color-accent-hover`, `--bressol-color-accent-focus` | Paleta base + estados |
| Tipografia | `--bressol-font-body`, `--bressol-font-heading`, `--bressol-font-size-1..6`, `--bressol-line-height-body`, `--bressol-line-height-heading` | Escala base y ritmo |
| Spacing | `--bressol-space-1..8` | Escala para paddings/margins |
| Radius | `--bressol-radius-1..4` | Consistencia de esquinas |
| Shadows | `--bressol-shadow-1..3` | Profundidad sutil premium |
| Focus | `--bressol-focus-ring`, `--bressol-focus-outline`, `--bressol-focus-offset` | Accesibilidad y consistencia |

## Componentes base

- Link: `.bressol-link`
  - Garantiza hover sutil, focus-visible y consistencia de color.
- Button: `.bressol-button`
  - Hover/active/disabled y focus-visible coherentes.
- Card: `.bressol-card`, `.bressol-card__link`
  - Borde/sombra sutil y focus visible en enlaces internos.
- Section: `.bressol-section`, `.bressol-main`
  - Ritmo vertical coherente con `--bressol-space-*`.
- FAQ: `.bressol-faq`, `.bressol-faq__item`, `.bressol-faq__q`, `.bressol-faq__a`
  - Estructura unificada y tipografia consistente.

## Principios
- Premium: espacios generosos, contraste limpio, sombras suaves.
- Valencian vibe: calido, elegante, sin ruido visual.
- Sutil: estados visibles pero discretos (focus/hover).
- Accesible: `:focus-visible` consistente en todos los elementos interactivos.
