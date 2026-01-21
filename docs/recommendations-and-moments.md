# Recomendaciones y Moments (documentación cerrada)

## A) Categorías y slugs (árbol completo) → family asignada por slug

**Fuente de verdad actual:** `RecommendationRules` (slugs definidos en constantes y usados para detectar family/slot).

**Árbol de categorías (slugs) y family resultante**

- **packs (family = `packs`)**
  - `packs`
  - `packs-only`
  - `gift-card`
  - `gifts`
  - `gift-box`

- **borrel_food (family = `borrel_food`)**
  - `borrel` (padre)
    - `olives`
    - `tapenade`
    - `preserves`
    - `snacks`

- **drinks (family = `drinks`)**
  - `drinks` (padre)
    - `beer`
    - `aperitief`
    - `wine`
    - `dessert-liqueur`

  **Subgrupo permitido para borrel_food (borrel drinks):** `beer`, `aperitief`.

- **oil (family = `oil`)**
  - `smaakmakers` (padre)
    - `oil`
    - `salt`
    - `vinegar`

- **sweet (family = `sweet`)**
  - `sweet-breakfast`
  - `jam`
  - `infusions`
  - `orxata-fartons`
  - `honey`
  - `nougat`
  - `tarwerondjes`

- **rice (family = `rice`)**
  - `rice-meals`

- **other (family = `other`)**
  - Cualquier otro slug no incluido arriba.


## B) Moments oficiales (slugs) y asignación propuesta (categorías + productos test)

**Objetivo:** que los productos tengan un `bressol_moment` consistente con su familia/categoría, facilitando filtrado y contenido editorial.

1) **`shared_table`**
   - **Categorías:** `borrel` + subcats (`olives`, `tapenade`, `preserves`, `snacks`) + `beer`/`aperitief` + packs con theme `borrel`.
   - **Producto test:** **Olives (ID 100)** y un producto **Beer**.

2) **`cooking`**
   - **Categorías:** `oil`, `salt`, `vinegar` y packs con focus `oil` (pack cocina).
   - **Producto test:** un producto **Oil**.

3) **`breakfast_snack`**
   - **Categorías:** `sweet-breakfast`, `jam`, `honey`, `nougat`, `infusions`, `orxata-fartons`, `tarwerondjes`.
   - **Producto test:** cualquier producto de `sweet-breakfast`.

4) **`gift`**
   - **Categorías:** `packs`, `packs-only`, `gift-card`, `gifts`, `gift-box`.
   - **Producto test:** un **pack** con `_bressol_pack_definition`.

5) **`relax`**
   - **Categorías:** `wine`, `dessert-liqueur`, `infusions`.
   - **Producto test:** un producto **Wine** o **Dessert liqueur**.

6) **`vegan`**
   - **Categorías:** `oil`, `vinegar`, `olives`, `preserves`, `snacks`.
   - **Producto test:** **Olives (ID 100)** o un producto `preserves`.
   - **Regla operativa:** usar este moment solo si el producto es **realmente vegano** (verificar ingredientes).

7) **`enjoyment`**
   - **Categorías:** `beer`, `aperitief`, `borrel` + `sweet-breakfast` (si aplica por consumo ocasional).
   - **Producto test:** **Beer** + un producto `snacks`.


## C) Reglas de recomendación (PDP, Cart, Modal)

> Estas reglas deben aplicarse igual en PDP, Cart y Modal. Todo debe venir de **un único Rules/Resolver**.

### 1) family = `borrel_food`
- **PDP:**
  - Recomendar **pack borrel** (theme `borrel`).
  - Recomendar **SOLO** bebidas `beer` o `aperitief`.
  - Recomendar más `borrel_food` (`olives`, `tapenade`, `preserves`, `snacks`).
  - **Excluir:** `wine`, `dessert-liqueur`.
- **Cart:**
  - Igual que PDP: pack borrel + bebidas `beer/aperitief` + más borrel_food.
- **Modal:**
  - **Extras**: solo `beer` o `aperitief`.
  - **Upgrade pack**: permitir packs compatibles que incluyan el producto (prefill de slot).

### 2) family = `drinks`
- **PDP:**
  - Recomendar `borrel_food` (`olives`, `tapenade`, `preserves`, `snacks`).
  - Recomendar **pack borrel**.
  - **Limitar subcats de drinks:** no mostrar otras bebidas como recomendaciones (evitar wine/dessert-liqueur/beer/aperitief dentro de “drinks” en PDP).
- **Cart:**
  - Igual que PDP: borrel_food + pack borrel; **sin** bebidas adicionales.
- **Modal:**
  - **Extras:** borrel_food (no bebidas adicionales).

### 3) family = `oil`
- **PDP:**
  - Recomendar **pack cocina** (focus `oil`).
  - Recomendar `salt` y `vinegar` (máx 2 cada uno).
  - **Lógica de slot:** si el producto fuente es `oil`, sugerir `salt`/`vinegar`; si es `salt` o `vinegar`, sugerir `oil`.
- **Cart:**
  - Igual que PDP: pack cocina + salt/vinegar.
- **Modal:**
  - **Extras:** según el slot (oil ↔ salt/vinegar).

### 4) family = `sweet`
- **PDP / Cart / Modal:**
  - **Por definir** (placeholder):
    - **Target categorías:** `sweet-breakfast`, `jam`, `honey`, `nougat`, `infusions`, `orxata-fartons`, `tarwerondjes`.
    - **Packs sugeridos:** TBD (definir si existen packs dulces).

### 5) family = `rice`
- **PDP / Cart / Modal:**
  - **Por definir** (placeholder):
    - **Target categorías:** `rice-meals`.
    - **Packs sugeridos:** TBD (definir si existen packs de arroz).


## 3) Validación de consistencia (RecommendationRules vs PDP/Cart/Modal)

### Inconsistencias observadas
1) **Modal tenía su propia lógica de extras** (query por `extraTargetCategorySlugs`) con razones fijas; PDP/Cart usaban `buildPdpRecommendations`/`buildCartRecommendations` con textos de razón distintos. Esto fragmenta la lógica y la mensajería.
2) **Tres consumidores usan reglas distintas por contexto** (PDP/Cart/Modal) y no comparten un punto único de entrada, lo que dificulta mantener las reglas cuando cambian.

### Refactor mínimo REAL propuesto (centralizar reglas)
**Objetivo:** mantener **un único API** en `RecommendationRules` consumido por PDP, Cart y Modal.

**(a) Lista de cambios**
- Añadir un método único: `RecommendationRules::buildRecommendations($context, $payload)`.
- Mover el armado de **extras del modal** a `RecommendationRules` (`buildModalExtras`).
- Actualizar:
  - **PDP** (`ProductPageRecommendations`) → usa `buildRecommendations('pdp', ...)`.
  - **Cart** (`CartRecommendations`) → usa `buildRecommendations('cart', ...)`.
  - **Modal** (`ModalController`) → usa `buildRecommendations('modal_extras', ...)`.

**(b) Patch/diff (archivos afectados)**
```diff
diff --git a/wp-content/plugins/bressol-core/src/Modules/Recommendations/Domain/RecommendationRules.php b/wp-content/plugins/bressol-core/src/Modules/Recommendations/Domain/RecommendationRules.php
index 0000000..0000000 100644
--- a/wp-content/plugins/bressol-core/src/Modules/Recommendations/Domain/RecommendationRules.php
+++ b/wp-content/plugins/bressol-core/src/Modules/Recommendations/Domain/RecommendationRules.php
@@ -160,6 +160,37 @@ final class RecommendationRules
         return [];
     }
+
+    public static function buildRecommendations(string $context, array $payload): array
+    {
+        if ($context === 'pdp') {
+            return self::buildPdpRecommendations((int) ($payload['source_product_id'] ?? 0));
+        }
+
+        if ($context === 'cart') {
+            $families = $payload['families'] ?? [];
+            if (!is_array($families)) {
+                $families = [];
+            }
+            return self::buildCartRecommendations($families);
+        }
+
+        if ($context === 'modal_extras') {
+            $sourceProductId = (int) ($payload['source_product_id'] ?? 0);
+            $limit = (int) ($payload['limit'] ?? 3);
+            return self::buildModalExtras($sourceProductId, $limit);
+        }
+
+        return [];
+    }
@@ -235,6 +266,42 @@ final class RecommendationRules
         return $recs;
     }
+
+    public static function buildModalExtras(int $sourceProductId, int $limit): array
+    {
+        $targetSlugs = self::extraTargetCategorySlugs($sourceProductId);
+        if (!$targetSlugs || $limit <= 0) {
+            return [];
+        }
+        // ... query + build output ...
+    }
```

```diff
diff --git a/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/ProductPageRecommendations.php b/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/ProductPageRecommendations.php
index 0000000..0000000 100644
--- a/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/ProductPageRecommendations.php
+++ b/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/ProductPageRecommendations.php
@@ -30,7 +30,9 @@ final class ProductPageRecommendations
-        $items = RecommendationRules::buildPdpRecommendations($sourceProductId);
+        $items = RecommendationRules::buildRecommendations('pdp', [
+            'source_product_id' => $sourceProductId,
+        ]);
```

```diff
diff --git a/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/CartRecommendations.php b/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/CartRecommendations.php
index 0000000..0000000 100644
--- a/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/CartRecommendations.php
+++ b/wp-content/plugins/bressol-core/src/Modules/Recommendations/Frontend/CartRecommendations.php
@@ -38,7 +38,9 @@ final class CartRecommendations
-        $recommendations = RecommendationRules::buildCartRecommendations(array_keys($familiesInCart));
+        $recommendations = RecommendationRules::buildRecommendations('cart', [
+            'families' => array_keys($familiesInCart),
+        ]);
```

```diff
diff --git a/wp-content/plugins/bressol-core/src/Modules/PostAddToCartModal/Frontend/ModalController.php b/wp-content/plugins/bressol-core/src/Modules/PostAddToCartModal/Frontend/ModalController.php
index 0000000..0000000 100644
--- a/wp-content/plugins/bressol-core/src/Modules/PostAddToCartModal/Frontend/ModalController.php
+++ b/wp-content/plugins/bressol-core/src/Modules/PostAddToCartModal/Frontend/ModalController.php
@@ -83,7 +83,10 @@ final class ModalController
-        $extras = $this->findExtras($sourceProductId, 3);
+        $extras = RecommendationRules::buildRecommendations('modal_extras', [
+            'source_product_id' => $sourceProductId,
+            'limit'             => 3,
+        ]);
@@ -301,40 +304,0 @@ final class ModalController
-    private function findExtras(int $sourcePid, int $limit): array
-    {
-        // ... lógica duplicada de extras ...
-    }
```


## 4) Checklist de tests (manual)

**Precondiciones:** productos con categorías correctas y WooCommerce activo.

1) **PDP – Olives (ID 100)**
   - Abrir PDP del producto **Olives (ID 100)**.
   - Verificar recomendaciones:
     - **Pack borrel** aparece.
     - **Beer** o **Aperitief** aparecen.
     - **Tapenade/Snacks/Preserves** aparecen.
     - **NO** aparecen **Wine** ni **Dessert-liqueur**.

2) **PDP – Beer**
   - Abrir PDP de un producto con slug `beer`.
   - Verificar recomendaciones:
     - **borrel_food** (olives/tapenade/preserves/snacks).
     - **pack borrel**.
     - No aparecen otras bebidas.

3) **PDP – Oil**
   - Abrir PDP de un producto con slug `oil`.
   - Verificar recomendaciones:
     - **salt** y **vinegar**.
     - **pack cocina** (focus `oil`).

4) **Cart con borrel_food**
   - Añadir un producto `borrel_food` al carrito.
   - Verificar recomendaciones en cart:
     - **pack borrel**.
     - **beer/aperitief**.
     - No aparecen **wine** ni **dessert-liqueur**.

5) **Modal post-add-to-cart**
   - Añadir Olives (ID 100) al carrito y abrir el modal.
   - Verificar que los extras respetan las mismas reglas que PDP (beer/aperitief, no wine/dessert-liqueur).
