# Bressol Theme

Proposito
- Capa UI/UX, plantillas y SEO on-page.
- Mantener el theme libre de logica de negocio.

Estado actual
- `functions.php`:
  - Soportes basicos (title-tag, thumbnails, woocommerce).
  - Menu `Hoofdnavigatie` (`primary`).
  - Inyeccion de CMP via `do_action('bressol_cmp_head')`.
  - Canonical para `bressol_moment` si no hay plugin SEO.
  - Validacion de edad (alcohol) en checkout.
- Plantillas:
  - `taxonomy-product_cat.php` con intro/longform/FAQ por categoria.
  - `taxonomy-bressol_moment.php` con intro/longform/FAQ por momento.
  - `header.php`, `footer.php`, `index.php` base.
- CSS:
  - `style.css` se encola con version por `filemtime`.

Reglas
- No incluir logica de packs, wizard, recomendaciones ni tracking.
- El plugin empuja dataLayer; el theme solo pinta markup.

Metas usadas en plantillas de taxonomia
- Categoria producto: `bressol_cat_intro`, `bressol_cat_longform`, `bressol_cat_faq`.
- Momento: `bressol_moment_intro`, `bressol_moment_longform`, `bressol_moment_faq`.
