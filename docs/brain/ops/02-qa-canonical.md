# QA visual y canonical (taxonomias)

## Alcance
- Taxonomias: `taxonomy-product_cat.php` y `taxonomy-bressol_moment.php`.
- Canonical de `bressol_moment` en `functions.php`.

## Canonical (bressol_moment)
- Se elimina `rel_canonical` de WordPress solo cuando `is_tax('bressol_moment')`.
- Se imprime canonical custom solo si NO hay RankMath o Yoast activos.
- Referencias:
  - `wp-content/themes/bressol-theme/functions.php`

## Checklist (lectura de codigo)
- `taxonomy-product_cat.php`:
  - `woocommerce_before_main_content` solo si Woo esta activo.
  - `woocommerce_after_main_content` solo si Woo esta activo.
  - FAQ renderiza antes del `after_main_content`.
- `taxonomy-bressol_moment.php`:
  - `woocommerce_before_main_content` solo si Woo esta activo.
  - `woocommerce_after_main_content` solo si Woo esta activo.
  - FAQ renderiza antes del `after_main_content`.
- Canonical:
  - `remove_action('wp_head', 'rel_canonical')` se ejecuta en `is_tax('bressol_moment')`.
  - Canonical custom no se imprime si existe RankMath/Yoast.

## Checklist visual (QA rapida)
- H1 visible en ambas taxonomias.
- Intro visible si hay meta o `term_description`.
- Loop de productos visible cuando Woo esta activo.
- Longform visible despues del loop.
- FAQ visible si hay JSON valido.
- Con Woo inactivo:
  - `taxonomy-product_cat.php` muestra H1 + intro y termina.
  - `taxonomy-bressol_moment.php` muestra H1 + intro + mensaje "Binnenkort beschikbaar." y longform si existe.

## Packs: optimizacion sin REGEXP (analisis pendiente)
### Estado actual
- `page-pakketten.php` usa `REGEXP` para `_bressol_pack_themes` y `_bressol_pack_focus` (CSV).
- Esto es costoso en `postmeta` y escala mal.

### Opciones de mejora (sin implementar aun)
1) **Normalizar a taxonomias**:
   - Crear taxonomias para themes/focus y migrar valores.
   - Consultas por `tax_query` y filtros mas eficientes.
2) **Normalizar a meta con multiples filas**:
   - Guardar un meta por valor (no CSV).
   - Usar `meta_query` con `IN` sin REGEXP.
3) **Tabla propia de relacion**:
   - Mejor performance, mas complejidad y mantenimiento.

### Recomendacion preliminar
- Taxonomias para themes/focus: mas alineado con WordPress, facil de filtrar y mantener en admin.
