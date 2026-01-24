# Modulo Post Add To Cart Modal

Proposito
- Mostrar modal de upsell justo despues de add-to-cart.
- Ofrecer upgrade de pack y extras compatibles.

Componentes y archivos
- `Frontend/ModalController.php` controlador principal.
- `Frontend/assets/modal.js` UI + llamadas AJAX.

Flujo principal
1) `woocommerce_add_to_cart` guarda `bressol_last_added` y `bressol_modal_pending`.
2) En el siguiente render se localiza `bressolModal.pending` y se dispara el modal.
3) `ajaxSuggestions` devuelve:
   - `upgrade_packs` (solo uno).
   - `extras` (hasta 4).
4) `ajaxReplaceWithPack` reemplaza el item con un pack compatible.
5) `ajaxAddExtra` agrega extras al carrito.

Reglas de sugerencia
- Si el producto fuente es pack:
  - upgrades low -> mid -> high por theme (borrel).
  - por defecto no muestra extras.
- Si NO es pack:
  - sugiere pack compatible tier low.
  - extras via `RecommendationRules::buildRecommendations('modal_extras', ...)`.

Integracion con packs
- Usa `bressol_pack_config` para pasar seleccion al pack.
- Prefill de slot con `slot` y `source_product_id`.

Notas
- El modulo permite AJAX en admin-ajax aunque `is_admin()` sea true.
- Evita modal en checkout.
