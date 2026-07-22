# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/performance-bundle`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and demo trees are out of Packagist scope unless promoted in the spec.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoPerformanceBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/PerformanceExtension.php` | DI extension | FR-CFG-002 |
| `DependencyInjection/Compiler/QueryTrackingMiddlewarePass.php` | DBAL middleware registration | FR-DBAL-001 |
| `DependencyInjection/Compiler/TableNamePass.php` | Dynamic table name params | FR-DBAL-002 |
| `DependencyInjection/Compiler/NotificationChannelsPass.php` | Notification channel tagging | FR-NOTIF-001 |
| `Command/CheckDependenciesCommand.php` | CLI dependency check | FR-CLI-001 |
| `Command/CreateTableCommand.php` | CLI create aggregates table | FR-CLI-001 |
| `Command/CreateRecordsTableCommand.php` | CLI create access records table | FR-CLI-001 |
| `Command/SyncSchemaCommand.php` | CLI schema sync | FR-CLI-001 |
| `Command/DiagnoseCommand.php` | CLI diagnose | FR-CLI-001 |
| `Command/SetRouteMetricsCommand.php` | CLI manual metrics | FR-CLI-001 |
| `Command/RebuildAggregatesCommand.php` | CLI rebuild aggregates | FR-CLI-001 |
| `Command/PurgeAccessRecordsCommand.php` | CLI purge access records | FR-CLI-001 |
| `Controller/PerformanceController.php` | Web dashboard | FR-DASH-001 |
| `Entity/RouteData.php` | Aggregate metrics entity | FR-ENTITY-001 |
| `Entity/RouteDataRecord.php` | Access record entity | FR-ENTITY-001 |
| `Repository/RouteDataRepository.php` | Aggregate persistence | FR-REPO-001 |
| `Repository/RouteDataRecordRepository.php` | Access record persistence | FR-REPO-001 |
| `Service/PerformanceMetricsService.php` | Metrics recording | FR-SVC-001 |
| `Service/PerformanceAnalysisService.php` | Statistics & recommendations | FR-SVC-002 |
| `Service/PerformanceCacheService.php` | Metrics cache | FR-SVC-003 |
| `Service/NotificationService.php` | Alert dispatch | FR-NOTIF-001 |
| `Service/TableStatusChecker.php` | Table health checks | FR-SVC-004 |
| `Service/DependencyChecker.php` | Optional dependency probe | FR-CLI-001 |
| `EventSubscriber/PerformanceMetricsSubscriber.php` | Request metrics collector | FR-METRICS-001 |
| `EventSubscriber/PerformanceAlertSubscriber.php` | Threshold alerts | FR-METRICS-002 |
| `EventSubscriber/QueryLogger.php` | Per-query logging | FR-DBAL-001 |
| `EventSubscriber/QueryTrackingConnectionSubscriber.php` | Connection middleware hook | FR-DBAL-001 |
| `EventSubscriber/TableNameSubscriber.php` | Aggregate table name | FR-DBAL-002 |
| `EventSubscriber/RouteDataRecordTableNameSubscriber.php` | Records table name | FR-DBAL-002 |
| `DBAL/QueryTrackingMiddleware.php` | Doctrine query middleware | FR-DBAL-001 |
| `DBAL/QueryTrackingMiddlewareRegistry.php` | Middleware registry | FR-DBAL-001 |
| `DataCollector/PerformanceDataCollector.php` | Web Profiler panel | FR-PROF-001 |
| `Message/RecordMetricsMessage.php` | Async metrics message | FR-MSG-001 |
| `MessageHandler/RecordMetricsMessageHandler.php` | Async metrics handler | FR-MSG-001 |
| `MessageHandler/AsMessageHandlerPolyfill.php` | Symfony 6.4 compat | FR-MSG-002 |
| `MessageBus/MessageBusInterface.php` | Message bus abstraction | FR-MSG-001 |
| `MessageBus/MessengerBusAdapter.php` | Messenger adapter | FR-MSG-001 |
| `Event/BeforeMetricsRecordedEvent.php` | Metrics lifecycle | FR-EVT-001 |
| `Event/AfterMetricsRecordedEvent.php` | Metrics lifecycle | FR-EVT-001 |
| `Event/BeforeRecordDeletedEvent.php` | Record delete lifecycle | FR-EVT-001 |
| `Event/AfterRecordDeletedEvent.php` | Record delete lifecycle | FR-EVT-001 |
| `Event/BeforeRecordReviewedEvent.php` | Review lifecycle | FR-EVT-001 |
| `Event/AfterRecordReviewedEvent.php` | Review lifecycle | FR-EVT-001 |
| `Event/BeforeRecordsClearedEvent.php` | Bulk clear lifecycle | FR-EVT-001 |
| `Event/AfterRecordsClearedEvent.php` | Bulk clear lifecycle | FR-EVT-001 |
| `Notification/NotificationChannelInterface.php` | Alert channel contract | FR-NOTIF-001 |
| `Notification/PerformanceAlert.php` | Alert payload | FR-NOTIF-001 |
| `Notification/Channel/EmailNotificationChannel.php` | Email alerts | FR-NOTIF-002 |
| `Notification/Channel/WebhookNotificationChannel.php` | Webhook/Slack/Teams alerts | FR-NOTIF-003 |
| `Form/PerformanceFiltersType.php` | Dashboard filters | FR-FORM-001 |
| `Form/RecordFiltersType.php` | Access record filters | FR-FORM-001 |
| `Form/StatisticsEnvFilterType.php` | Statistics env filter | FR-FORM-001 |
| `Form/ReviewRouteDataType.php` | Review form | FR-FORM-001 |
| `Form/DeleteRecordType.php` | Delete single record | FR-FORM-001 |
| `Form/DeleteRecordsByFilterType.php` | Delete by filter | FR-FORM-001 |
| `Form/ClearPerformanceDataType.php` | Clear all aggregates | FR-FORM-001 |
| `Form/PurgeAccessRecordsType.php` | Purge access records | FR-FORM-001 |
| `Model/RecordFilters.php` | Filter model | FR-MDL-001 |
| `Model/StatisticsEnvFilter.php` | Statistics filter model | FR-MDL-001 |
| `Model/RouteDataWithAggregates.php` | Dashboard row DTO | FR-MDL-001 |
| `Model/ClearPerformanceDataRequest.php` | Clear request DTO | FR-MDL-001 |
| `Model/DeleteRecordsByFilterRequest.php` | Delete-by-filter DTO | FR-MDL-001 |
| `Model/PurgeAccessRecordsRequest.php` | Purge request DTO | FR-MDL-001 |
| `Helper/LogHelper.php` | Conditional logging | FR-SVC-005 |
| `Twig/ArrayExtension.php` | Array Twig helpers | FR-TWIG-001 |
| `Twig/IconExtension.php` | Icon Twig helpers | FR-TWIG-001 |
| `Twig/Component/ChartsComponent.php` | UX Charts component | FR-TWIG-002 |
| `Twig/Component/FiltersComponent.php` | UX Filters component | FR-TWIG-002 |
| `Twig/Component/RoutesTableComponent.php` | UX Routes table component | FR-TWIG-002 |
| `Twig/Component/StatisticsComponent.php` | UX Statistics component | FR-TWIG-002 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `services.yaml` | Service wiring | FR-DI-001 |
| `routes.yaml` | Dashboard routes | FR-DI-001 |
| `services_twig_component.yaml` | UX component services | FR-DI-001, FR-TWIG-002 |

## Translations (`src/Resources/translations/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoPerformanceBundle.en.yaml` | English catalog | FR-I18N-001 |
| `NowoPerformanceBundle.es.yaml` | Spanish catalog | FR-I18N-001 |
| `nowo_performance.ca.yaml` | Catalan catalog | FR-I18N-001 |
| `nowo_performance.cs.yaml` | Czech catalog | FR-I18N-001 |
| `nowo_performance.de.yaml` | German catalog | FR-I18N-001 |
| `nowo_performance.fr.yaml` | French catalog | FR-I18N-001 |
| `nowo_performance.it.yaml` | Italian catalog | FR-I18N-001 |
| `nowo_performance.nl.yaml` | Dutch catalog | FR-I18N-001 |
| `nowo_performance.pl.yaml` | Polish catalog | FR-I18N-001 |
| `nowo_performance.pt.yaml` | Portuguese catalog | FR-I18N-001 |
| `nowo_performance.ru.yaml` | Russian catalog | FR-I18N-001 |
| `nowo_performance.tr.yaml` | Turkish catalog | FR-I18N-001 |

## Twig views (`src/Resources/views/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Performance/base.html.twig` | Dashboard layout | FR-DASH-002 |
| `Performance/index.html.twig` | Main dashboard | FR-DASH-002 |
| `Performance/statistics.html.twig` | Advanced statistics | FR-DASH-002 |
| `Performance/diagnose.html.twig` | Diagnose page | FR-DASH-002 |
| `Performance/access_records.html.twig` | Access records list | FR-DASH-002 |
| `Performance/access_statistics.html.twig` | Temporal statistics | FR-DASH-002 |
| `Performance/access_statistics_disabled.html.twig` | Disabled access stats notice | FR-DASH-002 |
| `Performance/components/_filters_bootstrap.html.twig` | Bootstrap filters partial | FR-DASH-003 |
| `Performance/components/_filters_tailwind.html.twig` | Tailwind filters partial | FR-DASH-003 |
| `Performance/components/_statistics_bootstrap.html.twig` | Bootstrap statistics partial | FR-DASH-003 |
| `Performance/components/_statistics_tailwind.html.twig` | Tailwind statistics partial | FR-DASH-003 |
| `Performance/components/_charts_bootstrap.html.twig` | Bootstrap charts partial | FR-DASH-003 |
| `Performance/components/_charts_tailwind.html.twig` | Tailwind charts partial | FR-DASH-003 |
| `Performance/components/_routes_table_bootstrap.html.twig` | Bootstrap routes table | FR-DASH-003 |
| `Performance/components/_routes_table_tailwind.html.twig` | Tailwind routes table | FR-DASH-003 |
| `Performance/components/_paginator.html.twig` | Paginator partial | FR-DASH-003 |
| `Performance/components/_dependency_modal.html.twig` | Missing deps modal | FR-DASH-003 |
| `components/Filters.html.twig` | UX Filters template | FR-TWIG-002 |
| `components/Statistics.html.twig` | UX Statistics template | FR-TWIG-002 |
| `components/Charts.html.twig` | UX Charts template | FR-TWIG-002 |
| `components/RoutesTable.html.twig` | UX RoutesTable template | FR-TWIG-002 |
| `Collector/performance.html.twig` | Web Profiler panel | FR-PROF-001 |
| `Notification/email_alert.html.twig` | Email alert HTML | FR-NOTIF-002 |
| `Notification/email_alert.txt.twig` | Email alert text | FR-NOTIF-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 72 | 72 |
| YAML config | 3 | 3 |
| Translation catalogs | 12 | 12 |
| Twig views | 24 | 24 |
| **Total production sources** | **111** | **111** |
