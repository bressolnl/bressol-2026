# Modulo ESP (Email Service Provider interno)

Proposito
- Campanas y emails manuales, segmentacion y tracking.
- Consentimientos y auditoria (RGPD/LSSI).
- Cola y scheduler interno.

Componentes y archivos
- `EspModule.php` registra cron, tracking, SMTP y admin menu.
- `Installer.php` crea tablas.
- `AdminPages.php` render de pantallas (placeholders por ahora).

Tracking (open/click/unsub)
- Open: pixel con `bressol_esp_open=1&tracking_key=...`.
- Click: redirect con `bressol_esp_click=1&tracking_key=...&target=...`.
- Unsub: `bressol_esp_unsub=1&tracking_key=...` actualiza consent.
- Eventos se guardan en `bressol_esp_events`.

Cola de envios
- Tabla `send_queue` + `send_queue_jobs`.
- Cron cada minuto (`bressol_esp_minute`).
- Reintentos hasta `MAX_QUEUE_ATTEMPTS`.
- Estados: scheduled -> running -> completed/failed.

SMTP
- Configurable via option `bressol_esp_smtp`.
- `phpmailer_init` aplica host/port/username/password/encryption/from.

Admin menu
- Campanas, Plantillas, Segmentos, Emails manuales, Metricas,
  Consentimientos, Exportaciones, Cola, SMTP, Auditoria.

Caps
- `bressol_manage_esp` (acceso permitido también con `manage_options` o `manage_woocommerce`).

Documento base detallado
- `docs/esp.md`
