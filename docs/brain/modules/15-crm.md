# Modulo CRM

Proposito
- Gestion de clientes, puntos, consentimientos y auditoria interna.
- Sin depender de un CRM externo.

Componentes y archivos
- `CrmModule.php` orquesta hooks WP/Woo, admin y cron.
- `Installer.php` crea tablas y versiona schema.
- `Admin/AdminPages.php` UI interna (clientes, ledger, redenciones, ajustes).
- `Services/`:
  - `CustomerService`, `PointsService`, `AuditLogger`, `Settings`, `TimelineService`.
  - `ImportCustomersService` para importacion.
- `Cli/`:
  - `ImportCustomersCommand`
  - `SelfTestCommand`

Hooks principales (WooCommerce)
- `woocommerce_order_status_completed` y `processing`:
  - upsert de cliente, update de metricas, optins, puntos.
- `woocommerce_order_refunded`:
  - registra puntos negativos.
- `woocommerce_checkout_fields` y `woocommerce_checkout_update_order_meta`:
  - agrega y guarda opt-ins de loyalty y marketing.

Cron y retencion
- `bressol_crm_expire_points_daily` (diario).
- `bressol_crm_anonymize_customers` (semanal).
- Anonimiza clientes segun retention months de settings.

CLI
- `wp bressol crm import-customers`
- `wp bressol crm selftest`

Tablas
- Ver `docs/brain/04-datos-y-taxonomias.md`.
