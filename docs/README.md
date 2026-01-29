# Documentation Index

Documentación del **Performance Bundle**. Los enlaces son relativos a esta carpeta (`docs/`).

## Guías principales

| Documento | Descripción |
|-----------|-------------|
| [INSTALLATION.md](INSTALLATION.md) | Instalación paso a paso: requisitos, registro del bundle, configuración inicial, creación de tablas y verificación. |
| [CONFIGURATION.md](CONFIGURATION.md) | Referencia completa de opciones de configuración. Valores por defecto y ejemplos. **Fuente de verdad** para defaults. |
| [USAGE.md](USAGE.md) | Uso del bundle: tracking automático, comandos manuales, personalización del dashboard, eventos y buenas prácticas. |
| [COMMANDS.md](COMMANDS.md) | Documentación de todos los comandos `nowo:performance:*` (create-table, set-route, sync-schema, diagnose, etc.). |

## Temas específicos

| Documento | Descripción |
|-----------|-------------|
| [EVENTS.md](EVENTS.md) | Eventos del bundle (BeforeMetricsRecorded, AfterRecordDeleted, etc.), flujo interno de listeners y **prioridades** (importante para `ignore_routes`). |
| [COMPATIBILITY.md](COMPATIBILITY.md) | Compatibilidad con Doctrine/DBAL, Symfony y diferencias entre versiones. |
| [NOTIFICATIONS.md](NOTIFICATIONS.md) | Notificaciones de rendimiento (email, Slack, Teams, webhooks). |
| [ASYNC_METRICS.md](ASYNC_METRICS.md) | Grabación asíncrona de métricas con Symfony Messenger. |
| [UPGRADING.md](UPGRADING.md) | Guía de actualización entre versiones del bundle. |
| [V2_MIGRATION.md](V2_MIGRATION.md) | Migración a la versión 2.0 (cambios de entidades). |
| [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md) | Plan de normalización de entidades (referencia técnica). |

## Otros

| Documento | Descripción |
|-----------|-------------|
| [CHANGELOG.md](CHANGELOG.md) | Historial de versiones y cambios. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Cómo contribuir al proyecto. |
| [ROADMAP.md](ROADMAP.md) | Roadmap y mejoras futuras. |
| [BRANCHING.md](BRANCHING.md) | Estrategia de ramas. |

## Referencias cruzadas

- **Defaults de configuración**: definidos en el código en `Configuration.php`; la documentación de referencia es [CONFIGURATION.md](CONFIGURATION.md). Si hay discrepancia, prevalece el código.
- **Prioridad de listeners y `ignore_routes`**: [EVENTS.md – Internal event flow and priorities](EVENTS.md#internal-event-flow-and-priorities). [CONFIGURATION.md – ignore_routes](CONFIGURATION.md#ignore_routes) enlaza a EVENTS para el flujo completo.
- **Comandos de tablas**: [COMMANDS.md](COMMANDS.md) documenta `create-table`, `sync-schema`, `create-records-table`. [INSTALLATION.md](INSTALLATION.md) enlaza a COMMANDS para detalles.
