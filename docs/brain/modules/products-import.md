# Products Import (CSV)

Pantalla: `Bressol Tools` -> `Import Products (CSV)`.

## Pasos
1. Exporta `02_products_template.xlsx` a CSV (UTF-8).
2. Ve a `Bressol Tools` -> `Import Products (CSV)`.
3. Selecciona el CSV y ajusta las opciones.
4. Pulsa `Start Import` y espera a que termine.
5. Descarga el reporte CSV desde el enlace al finalizar.

## Flags
- `mode`:
  - `upsert`: crea o actualiza por SKU (default).
  - `create`: solo crea; si el SKU existe, omite.
- `dry-run`: valida sin escribir en DB (`1` default).
- `strict-terms`: valida slugs en `product_cat` (`1` default).
- `create-missing-terms`: crea términos faltantes si `strict-terms=1` (`0` default).
- `clear-missing`: si una columna viene vacía, limpia el valor existente (`0` default).
- `force-simple`: fuerza `product_type=simple` (`1` default).
- `batch size`: tamaño de lote para AJAX (default 50).

## Reporte
El reporte se guarda en `wp-content/uploads/bressol-import/` con columnas:
`row, sku, action, status, message`.

## Nota
Las filas plantilla del CSV se ignoran automáticamente.
Los valores `null` se normalizan a `''` (string vacío) antes de importar.
