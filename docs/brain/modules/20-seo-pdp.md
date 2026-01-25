# SEO PDP (producto y packs)

## Metacampos (product)
- `bressol_pdp_intro` (HTML)
- `bressol_pdp_longform` (HTML)
- `bressol_origin_ref` (HTML)
- `bressol_pdp_faq` (JSON [{q,a}] o HTML con <h3><p>)
- `bressol_smaak_textuur1` / `2` / `3`
- `bressol_smaak_ingredienten1` / `2` / `3`
- `bressol_smaak_karakter1` / `2` / `3`
- `bressol_usage_tips` (HTML)
- `bressol_seo_usage_images` (array<int> de IDs adjuntos)
- `bressol_seo_quote_title` (string)
- `bressol_seo_quote_text` (HTML)
- `bressol_pack_recommended_moments` (CSV slugs)
- `bressol_pack_cross_sell_categories` (CSV slugs)

## Admin (metabox)
- Metabox: `Bressol SEO (PDP)` en edición de producto.
- Guardado con nonce + capability en `ProductSeoMetaBox`.

## Render PDP (hooks)
- Intro: `woocommerce_single_product_summary` (prio 35) → `renderIntro`.
- Detalles: `woocommerce_after_single_product_summary` (prio 11) → `renderDetails`:
  - Longform + origin
  - Quote editorial
  - Smaakprofiel
  - Usage tips + galería (medium_large)
  - FAQ (HTML o JSON)
  - Packs: links a momentos (`bressol_moment`) y categorias (`product_cat`)

## Checklist QA (6 pasos)
1) Producto (pack o no pack).
2) Completar metacampos en admin (intro, smaak, usage, quote, FAQ).
3) Verificar en PDP: secciones visibles en orden, sin errores.
4) FAQ: probar JSON y HTML (`<h3><p>...</p>`).
5) Usage images: seleccionar imágenes y confirmar render en galería.
6) Pack: completar CSV de momentos/categorias y confirmar links.
