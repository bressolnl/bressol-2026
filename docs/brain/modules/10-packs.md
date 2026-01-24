# Modulo Packs

Proposito
- Definir packs personalizables en producto Woo.
- Renderizar formulario de seleccion de slots.
- Validar, guardar y aplicar recargos en carrito.

Componentes y archivos
- Admin:
  - `Admin/PackMetaBox.php` edicion JSON de `_bressol_pack_definition` (guardado clasico + AJAX).
  - `Admin/PackAttributesMetaBox.php` gestiona tier/occasion/themes/focus.
- Frontend:
  - `Frontend/PackForm.php` muestra slots en PDP.
- Woo:
  - `Woo/PackCart.php` valida, captura seleccion, aplica recargos, guarda en order meta.

Contrato de datos
- `_bressol_pack_definition` JSON con `slots[]`.
- Cada slot: `key`, `label`, `required`, `min`, `max`, `options[]`.
- Cada option: `product_id`, `label`, `surcharge`.

Flujo principal (PDP -> carrito)
1) `PackForm` lee JSON y renderiza selects/qty.
2) `PackCart::validateBeforeAddToCart` valida required/min/max.
3) `PackCart::capturePackSelection` construye:
   - `bressol_pack.selections`
   - `bressol_pack.surcharge_total`
   - `bressol_pack_hash` (evita merge de items).
4) `PackCart::applyPackSurcharges` suma recargos al precio base.
5) `PackCart::storePackSelectionInOrder` guarda `_bressol_pack` en order item.

Prefill / upgrade
- Parametros GET usados en PDP:
  - `bressol_prefill_slot`
  - `bressol_prefill_product_id`
- Utilizado por recomendaciones y modal para prefill de slots.

Notas
- Para slots con `max > 1` se usan inputs de cantidad por producto.
- El modal puede enviar config via `bressol_pack_config` (cart item data).
