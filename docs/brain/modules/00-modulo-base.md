# Modulo base (contrato y bootstrap)

Contrato
- Interface: `Bressol\Core\ModuleInterface` con `register(): void`.
- Cada modulo se registra desde `Bressol\Core\Plugin`.

Bootstrap y autoload
- `bressol-core.php` define autoloader por namespaces y arranca el plugin.
- `Plugin::bootModules()` crea la lista de modulos.
- `Plugin::register()` llama `register()` de cada modulo.

Hooks de activacion y desactivacion
- Activacion:
  - ESP: `Installer::install()` y `EspModule::scheduleCron()`
  - CRM: `Installer::install()` y `CrmModule::schedule_cron()`
  - POS: `PosModule::schedule_cron()`
- Desactivacion:
  - ESP: `EspModule::clearCron()`
  - CRM: `CrmModule::clear_cron()`
  - POS: `PosModule::clear_cron()`

Instaladores y upgrades (admin_init / WP-CLI)
- POS: `Installer::maybe_upgrade()` (tablas de opened items).
- MarketsEvents: `Installer::maybe_upgrade()` (tabla `bressol_events` + migraciones).
- Forecasting: `Installer::maybe_upgrade()` (tabla `bressol_forecast_snapshots`).

Como agregar un modulo nuevo
1) Crear carpeta en `src/Modules/NuevoModulo`.
2) Crear clase `NuevoModuloModule` que implemente `ModuleInterface`.
3) Instanciar en `Plugin::bootModules()`.
4) Si hay cron o tablas, registrar en activation hook o en `admin_init` segun modulo.
5) Documentar en `docs/brain/modules/`.
