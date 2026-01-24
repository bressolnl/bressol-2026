# MarketsEvents (agenda de mercados y eventos)

## Proposito
Crear una agenda centralizada de mercados y eventos como fuente unica para POS, Sales Analytics y comunicaciones sin PII.

## Schema (tabla propia)
Tabla: `wp_bressol_events` (prefix dinamico).

Campos clave:
- `title` (VARCHAR 190) obligatorio
- `type` enum: `market|event|delivery|other`
- `status` enum: `planned|confirmed|done|cancelled`
- `start_at`, `end_at` (DATETIME, obligatorios, `end_at >= start_at`)
- `timezone` IANA (default `wp_timezone_string()`)
- `location_name`, `address` obligatorios
- `google_maps_url` opcional
- `distance_km`, `travel_time_min` opcionales
- `booth_fee_cents`, `other_costs_cents` >= 0
- `expected_sales_cents` opcional
- `notes` interno, sin PII
- `channels` enum: `pos|web|both`
- `created_at`, `updated_at` (se setean desde servicio)

Indices minimos: `status`, `type`, `start_at`, `channels`.
Versionado schema: `bressol_markets_events_schema_version`.

## Contratos de servicios (PR1)
Repositorio (`EventRepository`):
- `find_by_id(int $id): ?array`
- `insert(array $data): int`
- `update(int $id, array $data): bool`
- `delete(int $id): bool`
- `find_by_filters(array $filters, int $limit, int $page): array`
- `count_by_filters(array $filters): int`
- `list_upcoming_confirmed(int $limit, ?DateTimeImmutable $from = null): array`

Servicio (`EventService`):
- `normalize(array $payload): array`
- `validate(array $payload): array`
- `create(array $payload, int $actorId): int`
- `update(int $id, array $payload, int $actorId): bool`
- `duplicate(int $id, int $actorId): int` (prefijo "[COPY]" y +7 dias)

POS contexto (`PosEventContextService`):
- `get_active_event_id(int $userId = 0): ?int`
- `set_active_event_id(?int $eventId, int $userId = 0): void`
- `resolve_default_event_id(): ?int`
- `is_event_allowed_for_pos(int $eventId): bool`

## Decisiones
- Tabla propia por filtros de fechas, consistencia y costes fijos.
- Costes fijos (`booth_fee_cents` + `other_costs_cents`) se separan de costes por pedido POS.
- Capabilities: PR1 asigna solo a `administrator`; PR2 revisar `shop_manager`.
- Campos nullables se persisten como NULL (no string vacio) para consultas consistentes.

## Pendientes (PR2-PR5)
- PR2: Admin UI (CRUD, filtros, duplicado).
- PR3: Integracion POS (selector evento activo + guardado en meta pedido).
- PR4: Integracion Sales Analytics (filtros y coste fijo por evento).
- PR5: Servicio/endpoint interno de eventos confirmados + caching.

## Verificacion manual (PR2)
- Acceder a `Bressol > Markets & Events` y validar filtros y paginacion.
- Crear/editar evento y confirmar validaciones de fechas.
- Duplicar y comprobar +7 dias y prefijo "[COPY]".

## Verificacion manual (PR3)
- Confirmar que `WooCommerceHooks` se registra en `plugins_loaded` y escucha `woocommerce_new_order`.
- Con un evento POS activo, crear un pedido y verificar meta `_bressol_event_id` en el pedido.
