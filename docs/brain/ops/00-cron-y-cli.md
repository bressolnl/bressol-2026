# Operativa: cron y CLI

Cron ESP
- Hook queue: `bressol_esp_process_send_queue` (cada minuto).
- Hook campaigns: `bressol_esp_automate_campaigns` (cada minuto).
- Schedule registrado por `EspModule::registerCronSchedules`.
- Programado en activacion del plugin, limpiado al desactivar.

Cron CRM
- `bressol_crm_expire_points_daily` (diario).
- `bressol_crm_anonymize_customers` (semanal).
- Programado en activacion del plugin, limpiado al desactivar.

Cron POS
- `bressol_pos_cleanup_coupons` (diario).
- Programado en activacion del plugin, limpiado al desactivar.

CLI (WP-CLI)
- `wp bressol crm import-customers`
- `wp bressol crm selftest`
- `wp bressol seo-audit`
- `wp bressol inventory selftest`

Notas
- En admin-ajax, `is_admin()` puede ser true; modulos con AJAX deben permitirlo.
