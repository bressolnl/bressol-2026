# ESP interno (Bressol Core)

Objetivo: módulo interno para gestionar campañas, plantillas, segmentos y envíos de email (newsletters y transaccionales), con soporte de métricas, exportación y cumplimiento RGPD/LSSI.

## Alcance inicial
- Campañas multilingües obligatorias: valenciano (va), castellano (es), neerlandés (nl), inglés (en).
- Emails manuales 1-a-1: post-compra y envío/delivery, en un solo idioma elegido manualmente.
- Tracking de aperturas y clicks para campañas y emails manuales.
- Consentimientos y lista de exclusión para cumplimiento.
- Auditoría de acciones (creación, edición, envío).
- Cola de envíos para lotes y programación (scheduler).

## Panel admin (bressol-core > Bressol ESP)
- Campañas
- Plantillas
- Segmentos
- Emails manuales
- Métricas
- Consentimientos
- Exportaciones
- Cola de envíos
- Configuración SMTP
- Auditoría

## Modelo de datos (tablas)
Prefijo: `wp_bressol_esp_` (usa el prefijo activo de WordPress).

### campaigns
- Guarda campañas y referencias de plantilla/segmento.
- Incluye snapshots del HTML y texto enviado.
- CRUD básico en el panel (crear, editar, eliminar).

### campaign_audience_snapshot
- Audiencia congelada al enviar.
- Incluye idioma por destinatario.
- Se genera al programar campañas con emails manuales en el panel.
- Guarda tracking_key por destinatario para eventos.
- El idioma se resuelve automáticamente desde pedidos (meta, locale o país).

### templates
- Plantillas HTML + texto plano.
- Idioma obligatorio (va/es/nl/en).
- CRUD básico en el panel (crear, editar, eliminar).

### segments
- Reglas de segmentación (JSON).
- Configuración del builder visual.
- CRUD básico en el panel (crear, editar, eliminar).
- Builder básico con filtros de gasto total, nº de pedidos, recencia, productos y categorías.

### manual_emails
- Emails manuales por pedido (post-compra y envío).
- Guarda HTML, texto plano, idioma y metadatos.
- El destinatario se resuelve desde el email de facturación del pedido (WooCommerce) y se respeta opt-out.
- Los emails manuales siempre se envían por la cola (`send_queue` + `send_queue_jobs`) y el cron.
- Se añade un tracking pixel de apertura para registrar eventos de open.
- Los enlaces del HTML se reescriben para registrar eventos de click.
- Se añade un enlace de baja en el HTML y texto plano que registra opt-out.

### events
- Eventos de tracking (open/click/unsub).
- Soporta campañas y emails manuales.

### consents
- Consentimiento por email (opt-in/opt-out) y fuente.

### audit_logs
- Auditoría: quién hizo qué, sobre qué entidad.
- Incluye trazabilidad de envíos manuales (sent/failed) y bajas (unsubscribe).

### send_queue
- Cola de envíos para lotes y programación.
- Motor único de envío: campañas y emails manuales siempre pasan por la cola.
### send_queue_jobs
- Jobs de ejecución para la cola de envíos.
- Reintentos y estados gestionados por el scheduler.
- Envíos desde cola para campañas y emails manuales.
- Los jobs marcan el item como `sending` antes de enviar y registran errores.

## Idiomas
- Campañas y plantillas requieren 4 versiones (va/es/nl/en).
- Emails manuales: un único idioma por envío, seleccionado manualmente.

## Tracking
- Aperturas y clicks registrados como eventos.
- Cobertura para campañas y emails manuales.
- Exportación CSV básica de métricas de emails manuales en el panel.
- Enlace de baja (opt-out) disponible en emails manuales.
- Métricas y export CSV para campañas (aperturas, clicks y bajas).
- Métricas incluyen tamaño de audiencia por campaña.
- Bajas registradas también en auditoría.

## Cumplimiento
- Consentimiento explícito.
- Lista de exclusión (opt-out).
- Registro de fuente y fechas.
- Panel de consentimientos y auditoría visible en el admin.

## SMTP
- Un único perfil SMTP configurable.
- Límites de envío y logs de errores vía cola.
- Configuración SMTP en el panel con host, puerto, cifrado y remitente.

## Cron y automatización
- El cron se programa en activación del plugin y se limpia en desactivación.
- `automateCampaigns` solo cambia campañas a `sending` si existe snapshot, cola y jobs.

## Implementación inicial (archivos a crear)
Si vas a implementar el módulo desde cero, estos son los archivos mínimos que debes crear y su propósito:

1. **`wp-content/plugins/bressol-core/src/Modules/Esp/EspModule.php`**
   - Registra el menú y submenús del ESP en el admin de WordPress.
2. **`wp-content/plugins/bressol-core/src/Modules/Esp/AdminPages.php`**
   - Renderiza las pantallas del admin (por ahora con contenido placeholder).
3. **`wp-content/plugins/bressol-core/src/Modules/Esp/Installer.php`**
   - Crea las tablas necesarias del ESP usando `dbDelta`.
4. **`docs/esp.md`**
   - Documento de alcance, arquitectura y esquema de datos (este archivo).
5. **`docs/diario/AAAA-MM-DD.md`**
   - Entrada de diario con un resumen del cambio del día.

Además, hay que modificar:
- **`wp-content/plugins/bressol-core/bressol-core.php`** para registrar el `register_activation_hook` que llama al instalador.
- **`wp-content/plugins/bressol-core/src/Core/Plugin.php`** para registrar `EspModule` en `bootModules()`.

> Nota (Windows): guarda los archivos PHP en **UTF-8 sin BOM**. Si no, PHP puede interpretar el BOM como salida previa y fallar con “`strict_types` must be the very first statement”.

## Pasos recomendados para “crearlo y guardarlo” (mentoría)
Esta es la secuencia más segura para un junior:

1. **Crear la carpeta y archivos del módulo** (`EspModule.php`, `AdminPages.php`, `Installer.php`).
2. **Editar los dos archivos core** (`bressol-core.php` y `Plugin.php`) para enganchar el módulo y el instalador.
3. **Añadir la documentación** (`docs/esp.md`) y una entrada en el diario (`docs/diario/AAAA-MM-DD.md`).
4. **Verificar el estado en Git** (`git status -sb`) y revisar los cambios antes de commit.
5. **Hacer commits claros por bloques** (docs → core → módulo) o uno único si prefieres simplicidad.
