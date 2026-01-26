# Reporte de Cobertura de Tests - Performance Bundle

## Resumen General

- **Total de archivos de test**: 13
- **Total de métodos de test**: 122
- **Tipo de tests**: Unitarios (Unit Tests)

## Archivos de Test

### 1. Controller Tests
- **Archivo**: `tests/Unit/Controller/PerformanceControllerTest.php`
- **Clase testeada**: `PerformanceController`
- **Métodos de test**: 12
- **Cobertura**:
  - ✅ `index()` - Dashboard principal (habilitado/deshabilitado, roles, filtros)
  - ✅ `exportCsv()` - Exportación CSV (habilitado/deshabilitado, roles)
  - ✅ `exportJson()` - Exportación JSON (habilitado/deshabilitado, roles)
  - ⚠️ `statistics()` - No encontrado en tests
  - ⚠️ `delete()` - No encontrado en tests
  - ⚠️ `review()` - No encontrado en tests

### 2. Service Tests
- **Archivo**: `tests/Unit/Service/PerformanceMetricsServiceTest.php`
- **Clase testeada**: `PerformanceMetricsService`
- **Métodos de test**: 13
- **Cobertura**:
  - ✅ `recordMetrics()` - Crear nuevo, actualizar existente, no actualizar con mejores métricas
  - ✅ `getRouteData()` - Obtener datos de ruta
  - ✅ `getRoutesByEnvironment()` - Obtener rutas por entorno
  - ✅ `getWorstPerformingRoutes()` - Obtener peores rutas
  - ✅ `setCacheService()` - Configurar servicio de caché
  - ✅ Manejo de excepciones
  - ✅ Manejo de uso de memoria

### 3. Entity Tests
- **Archivo**: `tests/Unit/Entity/RouteDataTest.php`
- **Clase testeada**: `RouteData`
- **Métodos de test**: 11
- **Cobertura**:
  - ✅ Constructor y valores iniciales
  - ✅ Getters y setters (todos los campos)
  - ✅ `shouldUpdate()` - Lógica de actualización de métricas
  - ⚠️ Métodos de review (isReviewed, getReviewedAt, etc.) - No encontrados en tests
  - ⚠️ Métodos de acceso (getAccessCount, getLastAccessedAt) - No encontrados en tests

### 4. Repository Tests
- **Archivo**: `tests/Unit/Repository/RouteDataRepositoryTest.php`
- **Clase testeada**: `RouteDataRepository`
- **Métodos de test**: 6
- **Cobertura**:
  - ✅ `getRankingByRequestTime()` - Ranking por tiempo de solicitud
  - ✅ `getRankingByQueryCount()` - Ranking por cantidad de queries
  - ✅ `getTotalRoutesCount()` - Contar total de rutas
  - ⚠️ `findByRouteAndEnv()` - No encontrado en tests
  - ⚠️ Otros métodos de búsqueda - No encontrados en tests

### 5. Event Subscriber Tests
- **Archivo**: `tests/Unit/EventSubscriber/PerformanceMetricsSubscriberTest.php`
- **Clase testeada**: `PerformanceMetricsSubscriber`
- **Métodos de test**: 15
- **Cobertura**:
  - ✅ `getSubscribedEvents()` - Eventos suscritos
  - ✅ `onKernelRequest()` - Manejo de request (habilitado/deshabilitado, entornos, rutas ignoradas)
  - ✅ `onKernelTerminate()` - Manejo de terminate (con y sin tracking de queries)
  - ✅ Manejo de excepciones
  - ✅ Configuración de tracking

### 6. Data Collector Tests
- **Archivo**: `tests/Unit/DataCollector/PerformanceDataCollectorTest.php`
- **Clase testeada**: `PerformanceDataCollector`
- **Métodos de test**: 18
- **Cobertura**:
  - ✅ `getName()` - Nombre del collector
  - ✅ `setEnabled()` / `isEnabled()` - Habilitar/deshabilitar
  - ✅ `setRouteName()` / `getRouteName()` - Nombre de ruta
  - ✅ `setStartTime()` / `getRequestTime()` - Tiempo de solicitud
  - ✅ `setQueryMetrics()` - Métricas de queries
  - ✅ `getFormattedRequestTime()` - Formateo de tiempo
  - ✅ `getFormattedQueryTime()` - Formateo de tiempo de queries
  - ✅ `reset()` - Reiniciar estado
  - ✅ `getRankingByRequestTime()` - Ranking por tiempo
  - ✅ `getRankingByQueryCount()` - Ranking por queries
  - ✅ Manejo de excepciones

### 7. DBAL Tests
- **Archivo**: `tests/Unit/DBAL/QueryTrackingMiddlewareTest.php`
- **Clase testeada**: `QueryTrackingMiddleware`
- **Métodos de test**: 15
- **Cobertura**:
  - ✅ `reset()` - Reiniciar contadores
  - ✅ `startQuery()` / `stopQuery()` - Iniciar/detener query
  - ✅ `getQueryCount()` - Contar queries
  - ✅ `getTotalQueryTime()` - Tiempo total de queries
  - ✅ `wrap()` - Envolver conexión
  - ✅ Métodos de conexión (prepare, query, exec, transacciones)
  - ✅ Manejo de excepciones

### 8. Command Tests
- **Archivo**: `tests/Unit/Command/SetRouteMetricsCommandTest.php`
- **Clase testeada**: `SetRouteMetricsCommand`
- **Métodos de test**: 9
- **Cobertura**:
  - ✅ Nombre y descripción del comando
  - ✅ Ejecución con nueva ruta
  - ✅ Ejecución con ruta existente
  - ✅ Ejecución con entorno personalizado
  - ✅ Ejecución con parámetros
  - ✅ Manejo de parámetros JSON inválidos
  - ✅ Manejo de excepciones

### 9. Dependency Injection Tests
- **Archivo**: `tests/Unit/DependencyInjection/PerformanceExtensionTest.php`
- **Clase testeada**: `PerformanceExtension`
- **Métodos de test**: 5
- **Cobertura**:
  - ✅ `getAlias()` - Alias de extensión
  - ✅ `load()` - Carga de configuración (default y custom)
  - ✅ `prepend()` - Prepend de configuración Twig

- **Archivo**: `tests/Unit/DependencyInjection/ConfigurationTest.php`
- **Clase testeada**: `Configuration`
- **Métodos de test**: 4
- **Cobertura**:
  - ✅ Configuración por defecto
  - ✅ Configuración personalizada
  - ✅ Configuración parcial
  - ✅ Configuración de dashboard con roles

### 10. Compiler Pass Tests
- **Archivo**: `tests/Unit/DependencyInjection/Compiler/TableNamePassTest.php`
- **Clase testeada**: `TableNamePass`
- **Métodos de test**: 2
- **Cobertura**:
  - ✅ Procesamiento con parámetro
  - ✅ Procesamiento sin parámetro

### 11. Event Subscriber Tests (Adicionales)
- **Archivo**: `tests/Unit/EventSubscriber/TableNameSubscriberTest.php`
- **Clase testeada**: `TableNameSubscriber`
- **Métodos de test**: 3
- **Cobertura**:
  - ✅ `getSubscribedEvents()` - Eventos suscritos
  - ✅ `loadClassMetadata()` - Carga de metadata para RouteData
  - ✅ Manejo de otras entidades

- **Archivo**: `tests/Unit/EventSubscriber/QueryLoggerTest.php`
- **Clase testeada**: `QueryLogger`
- **Métodos de test**: 6
- **Cobertura**:
  - ✅ Estado inicial
  - ✅ Iniciar y detener queries
  - ✅ Múltiples queries
  - ✅ Queries concurrentes
  - ✅ Reset

## Áreas con Cobertura Limitada o Ausente

### 1. Controller
- ⚠️ `statistics()` - Método de estadísticas no testeado
- ⚠️ `delete()` - Método de eliminación no testeado
- ⚠️ `review()` - Método de revisión no testeado

### 2. Entity (RouteData)
- ⚠️ Métodos de review: `isReviewed()`, `getReviewedAt()`, `getQueriesImproved()`, `getTimeImproved()`, `setReviewed()`, etc.
- ⚠️ Métodos de acceso: `getAccessCount()`, `getLastAccessedAt()`, `incrementAccessCount()`, etc.
- ⚠️ Métodos de memoria: `getMemoryUsage()`, `setMemoryUsage()`, etc.

### 3. Repository
- ⚠️ `findByRouteAndEnv()` - Método principal de búsqueda no testeado directamente
- ⚠️ Otros métodos de búsqueda y filtrado

### 4. Services
- ⚠️ `PerformanceCacheService` - No tiene tests
- ⚠️ `DependencyChecker` - No tiene tests

### 5. Commands
- ⚠️ `CreateTableCommand` - No tiene tests
- ⚠️ `CheckDependenciesCommand` - No tiene tests
- ⚠️ `DiagnoseCommand` - No tiene tests

### 6. Twig Components
- ⚠️ `StatisticsComponent` - No tiene tests
- ⚠️ `FiltersComponent` - No tiene tests
- ⚠️ `ChartsComponent` - No tiene tests
- ⚠️ `RoutesTableComponent` - No tiene tests
- ⚠️ `IconExtension` - No tiene tests

### 7. Message Handlers
- ⚠️ `RecordMetricsMessageHandler` - No tiene tests

### 8. Events
- ⚠️ Todos los eventos personalizados - No tienen tests

### 9. Integration Tests
- ⚠️ No hay tests de integración (directorio `tests/Integration/` está vacío)

## Recomendaciones

1. **Agregar tests para métodos faltantes del Controller**:
   - `statistics()`
   - `delete()`
   - `review()`

2. **Agregar tests para métodos de Entity**:
   - Métodos de review
   - Métodos de acceso
   - Métodos de memoria

3. **Agregar tests para Repository**:
   - `findByRouteAndEnv()`
   - Otros métodos de búsqueda

4. **Agregar tests para Services faltantes**:
   - `PerformanceCacheService`
   - `DependencyChecker`

5. **Agregar tests para Commands faltantes**:
   - `CreateTableCommand`
   - `CheckDependenciesCommand`
   - `DiagnoseCommand`

6. **Agregar tests de integración**:
   - Tests con base de datos real
   - Tests end-to-end del flujo completo
   - Tests de integración con Symfony

7. **Mejorar cobertura de casos edge**:
   - Valores null
   - Valores extremos
   - Errores de base de datos
   - Timeouts y excepciones

## Estadísticas

- **Tests unitarios**: 122
- **Clases con tests**: 13
- **Clases sin tests**: ~15-20 (estimado)
- **Cobertura estimada**: ~60-70% (basado en métodos testeados vs métodos totales)

## Notas

- Todos los tests son unitarios, usando mocks para dependencias
- No hay tests de integración actualmente
- La mayoría de las clases core tienen buena cobertura
- Las clases de UI (Twig Components) no tienen tests
- Los comandos adicionales no tienen tests
