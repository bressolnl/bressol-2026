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
- Configuración SMTP
- Auditoría

## Modelo de datos (tablas)
Prefijo: `wp_bressol_esp_` (usa el prefijo activo de WordPress).

### campaigns
- Guarda campañas y referencias de plantilla/segmento.
- Incluye snapshots del HTML y texto enviado.

### campaign_audience_snapshot
- Audiencia congelada al enviar.
- Incluye idioma por destinatario.

### templates
- Plantillas HTML + texto plano.
- Idioma obligatorio (va/es/nl/en).

### segments
- Reglas de segmentación (JSON).
- Configuración del builder visual.

### manual_emails
- Emails manuales por pedido (post-compra y envío).
- Guarda HTML, texto plano, idioma y metadatos.

### events
- Eventos de tracking (open/click/unsub).
- Soporta campañas y emails manuales.

### consents
- Consentimiento por email (opt-in/opt-out) y fuente.

### audit_logs
- Auditoría: quién hizo qué, sobre qué entidad.

### send_queue
- Cola de envíos para lotes y programación.

## Idiomas
- Campañas y plantillas requieren 4 versiones (va/es/nl/en).
- Emails manuales: un único idioma por envío, seleccionado manualmente.

## Tracking
- Aperturas y clicks registrados como eventos.
- Cobertura para campañas y emails manuales.

## Cumplimiento
- Consentimiento explícito.
- Lista de exclusión (opt-out).
- Registro de fuente y fechas.

## SMTP
- Un único perfil SMTP configurable.
- Límites de envío y logs de errores vía cola.

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
