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
- Opcional: `group_key` (string, 2..32, [a-z0-9_-]) para agrupar slots de la misma familia.
- Cada option: `product_id`, `label`, `surcharge`.

Convencion de preseleccion (default)
- Oficial: `default: true` en una option del slot.
- Legacy compatible: `is_default` o `preselect` (se admiten pero se consideran legacy).
- Si hay multiples defaults en un slot, se usa el primero y se registra alerta en auditoria.
- Si el default es invalido (producto inexistente o id <=0), se aplica fallback.
- Fallback tecnico: primera option valida del slot.
- Fallback tecnico: recomendado definir default oficial para evitar ambiguedad.
- El default no garantiza stock; la validacion de stock ocurre al vender.

Ejemplo minimo (slot simple con default)
{
  "slots": [
    {
      "key": "oil",
      "label": "Elige 1 aceite",
      "group_key": "oil",
      "required": true,
      "min": 1,
      "max": 1,
      "options": [
        { "product_id": 68, "label": "Aceite (ID 68)", "surcharge": 0, "default": true },
        { "product_id": 73, "label": "Aceite premium (ID 73)", "surcharge": 2 }
      ]
    }
  ]
}

Ejemplo agrupado (2 slots misma familia)
{
  "slots": [
    {
      "key": "tapenade_1",
      "label": "Tapenade 1",
      "group_key": "tapenade",
      "required": true,
      "min": 1,
      "max": 1,
      "options": [
        { "product_id": 101, "label": "Tapenade A", "surcharge": 0 },
        { "product_id": 102, "label": "Tapenade B", "surcharge": 0 }
      ]
    },
    {
      "key": "tapenade_2",
      "label": "Tapenade 2",
      "group_key": "tapenade",
      "required": true,
      "min": 1,
      "max": 1,
      "options": [
        { "product_id": 101, "label": "Tapenade A", "surcharge": 0 },
        { "product_id": 102, "label": "Tapenade B", "surcharge": 0 }
      ]
    }
  ]
}

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
