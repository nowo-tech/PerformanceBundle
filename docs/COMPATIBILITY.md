# Doctrine and DBAL Compatibility

This document explains the Doctrine and DBAL versions supported by the bundle, the differences between versions that can break compatibility, and how the bundle handles these differences.

## Supported Versions

The bundle is compatible with the following versions:

- **Doctrine ORM**: `^2.13 || ^3.0`
- **Doctrine DBAL**: Included with ORM (2.x with ORM 2.x, 3.x with ORM 3.x)
- **DoctrineBundle**: `^2.8 || ^3.0`

### Compatibility Matrix

| Symfony | Doctrine ORM | Doctrine DBAL | DoctrineBundle | Support |
|---------|--------------|---------------|----------------|---------|
| 6.1+    | 2.13+        | 2.x           | 2.8+           | ✅      |
| 6.1+    | 2.13+        | 2.x           | 3.0+           | ✅      |
| 7.0+    | 2.13+        | 2.x           | 2.8+           | ✅      |
| 7.0+    | 3.0+         | 3.x           | 3.0+           | ✅      |
| 8.0+    | 3.0+         | 3.x           | 3.0+           | ✅      |

> **Note**: DoctrineBundle 3.0 is required for Symfony 8.0+.

## Important Changes Between Versions

### 1. DoctrineBundle: Middleware Registration

#### DoctrineBundle 2.x (< 3.0)

**Supported versions**: 2.8.0 - 2.15.x

**Features**:
- Supports middleware configuration in YAML using `middlewares` (since 2.0)
- Supports `yamlMiddleware` (since 2.10.0) - preferred method

**YAML Configuration**:
```yaml
doctrine:
    dbal:
        connections:
            default:
                # Option 1: middlewares (2.0+)
                middlewares:
                    - Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware
                
                # Option 2: yamlMiddleware (2.10.0+) - Preferred
                yamlMiddleware:
                    - Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware
```

#### DoctrineBundle 3.x

**Supported versions**: 3.0.0+

**Important changes**:
- ❌ **Does NOT support** middleware configuration in YAML
- ❌ **Does NOT support** `middlewares` or `yamlMiddleware`
- ✅ Requires manual middleware application using reflection

**How the bundle handles it**:
The bundle automatically detects the DoctrineBundle version and applies the middleware using the appropriate method:

1. **DoctrineBundle 2.10.0+**: Uses `yamlMiddleware` (preferred method)
2. **DoctrineBundle 2.8.0 - 2.9.x**: Uses `middlewares`
3. **DoctrineBundle 3.x**: Uses `QueryTrackingConnectionSubscriber` which applies middleware via reflection

**Relevant code**:
- `QueryTrackingMiddlewareRegistry::supportsYamlMiddleware()` - Detects if `yamlMiddleware` is available
- `QueryTrackingMiddlewareRegistry::supportsYamlMiddlewareConfig()` - Detects if `middlewares` is available
- `QueryTrackingConnectionSubscriber` - Applies middleware in DoctrineBundle 3.x

### 2. DBAL: Schema Manager

#### DBAL 2.x

**Method to get Schema Manager**:
```php
$schemaManager = $connection->getSchemaManager();
```

**Features**:
- Direct `getSchemaManager()` method available
- Returns `AbstractSchemaManager`

#### DBAL 3.x

**Important changes**:
- ❌ **Does NOT have** `getSchemaManager()` method
- ✅ **New method**: `createSchemaManager()`

**How the bundle handles it**:
The bundle detects which method is available and uses it:

```php
private function getSchemaManager(\Doctrine\DBAL\Connection $connection): \Doctrine\DBAL\Schema\AbstractSchemaManager
{
    // DBAL 3.x uses createSchemaManager()
    if (method_exists($connection, 'createSchemaManager')) {
        return $connection->createSchemaManager();
    }
    // DBAL 2.x uses getSchemaManager()
    if (method_exists($connection, 'getSchemaManager')) {
        $getSchemaManager = [$connection, 'getSchemaManager'];
        return $getSchemaManager();
    }
    throw new \RuntimeException('Unable to get schema manager');
}
```

**Affected files**:
- `CreateTableCommand::getSchemaManager()`
- `CreateRecordsTableCommand::getSchemaManager()`
- `TableStatusChecker::getSchemaManager()`

### 3. DBAL: Type Registry

#### DBAL 2.x

**Method to get types**:
```php
$type = \Doctrine\DBAL\Types\Type::getType('string');
```

**Features**:
- Static `Type::getType()` method available
- Returns type instance directly

#### DBAL 3.x

**Important changes**:
- ❌ **Does NOT have** static `Type::getType()` method
- ✅ **New system**: `Type::getTypeRegistry()->get()`

**How the bundle handles it**:
The bundle tries both methods in order:

```php
// Try DBAL 3.x method first
if (method_exists(\Doctrine\DBAL\Types\Type::class, 'getTypeRegistry')) {
    $typeRegistry = \Doctrine\DBAL\Types\Type::getTypeRegistry();
    $doctrineType = $typeRegistry->get($type);
} elseif (method_exists(\Doctrine\DBAL\Types\Type::class, 'getType')) {
    // DBAL 2.x method
    $doctrineType = \Doctrine\DBAL\Types\Type::getType($type);
}
```

**Affected files**:
- `CreateTableCommand::getColumnSQLType()`
- `CreateRecordsTableCommand::getColumnSQLType()`

### 4. DBAL: Type Methods

#### DBAL 2.x

**Available methods on types**:
```php
$type = Type::getType('integer');
$typeName = $type->getName(); // Returns 'integer'
```

#### DBAL 3.x

**Important changes**:
- ❌ **Does NOT have** `getName()` method on types
- ✅ **Alternative**: Use `getSQLDeclaration()` and compare class names

**How the bundle handles it**:
Instead of using `getName()`, the bundle uses `getSQLDeclaration()` and compares class names:

```php
// Instead of: $type->getName()
$typeClass = get_class($type);
$isInteger = str_contains($typeClass, 'IntegerType');
```

**Affected files**:
- `CreateTableCommand::getColumnSQLType()`
- `CreateRecordsTableCommand::getColumnSQLType()`

### 5. ORM: Metadata - getTableName()

#### ORM 2.x

**Method to get table name**:
```php
$tableName = $metadata->getTableName(); // Method available
// Or
$tableName = $metadata->table['name']; // Property available
```

#### ORM 3.x

**Important changes**:
- ⚠️ `getTableName()` may not be available in all versions
- ✅ `$metadata->table['name']` is always available

**How the bundle handles it**:
The bundle checks if the method exists before using it:

```php
$actualTableName = method_exists($metadata, 'getTableName')
    ? $metadata->getTableName()
    : ($metadata->table['name'] ?? $fallbackName);
```

**Affected files**:
- `CreateTableCommand`
- `CreateRecordsTableCommand`
- `TableStatusChecker`
- `TableNameSubscriber`
- `RouteDataRecordTableNameSubscriber`

### 6. ORM: Metadata - getFieldMapping()

#### ORM 2.x

**Return value of getFieldMapping()**:
```php
$mapping = $metadata->getFieldMapping('name');
// Returns: ['type' => 'string', 'length' => 255, 'options' => []]
// Type: array
```

#### ORM 3.x

**Important changes**:
- ✅ **New return type**: `FieldMapping` object instead of array
- ⚠️ The object can be converted to array using `(array)` or accessing properties

**How the bundle handles it**:
The bundle converts the result to array if needed:

```php
$fieldMapping = $metadata->getFieldMapping($fieldName);
// Convert to array if object
if (is_object($fieldMapping)) {
    $fieldMapping = (array) $fieldMapping;
}
// Or access properties directly
$type = is_object($fieldMapping) ? $fieldMapping->type : $fieldMapping['type'];
```

**Affected files**:
- `CreateTableCommand::updateTableSchema()`
- `CreateRecordsTableCommand::updateTableSchema()`

### 7. ORM: Metadata - getAssociationMapping()

#### ORM 2.x

**Return value of getAssociationMapping()**:
```php
$mapping = $metadata->getAssociationMapping('routeData');
// Returns: ['joinColumns' => [...], 'targetEntity' => '...']
// Type: array
```

#### ORM 3.x

**Important changes**:
- ✅ **New return type**: `AssociationMapping` object instead of array
- ⚠️ Similar to `getFieldMapping()`, requires conversion

**How the bundle handles it**:
Similar to `getFieldMapping()`, the bundle handles both types:

```php
$associationMapping = $metadata->getAssociationMapping($fieldName);
// Convert to array if object
if (is_object($associationMapping)) {
    $joinColumns = $associationMapping->joinColumns ?? [];
} else {
    $joinColumns = $associationMapping['joinColumns'] ?? [];
}
```

**Affected files**:
- `CreateRecordsTableCommand::updateTableSchema()`

## Bundle Compatibility Strategies

### 1. Version Detection

The bundle automatically detects installed versions:

```php
// DoctrineBundle version detection
$version = QueryTrackingMiddlewareRegistry::detectDoctrineBundleVersion();

// Feature verification
$supportsYamlMiddleware = QueryTrackingMiddlewareRegistry::supportsYamlMiddleware();
$supportsYamlMiddlewareConfig = QueryTrackingMiddlewareRegistry::supportsYamlMiddlewareConfig();
```

**Detection methods**:
1. `Composer\InstalledVersions::getVersion()` - Preferred method
2. Reading package's `composer.json`
3. Heuristics based on available methods/classes

### 2. Method Verification (method_exists)

The bundle uses `method_exists()` extensively to verify method availability:

```php
// Example: Schema Manager
if (method_exists($connection, 'createSchemaManager')) {
    // DBAL 3.x
    return $connection->createSchemaManager();
} elseif (method_exists($connection, 'getSchemaManager')) {
    // DBAL 2.x
    return $connection->getSchemaManager();
}
```

### 3. Fallbacks and Conversions

The bundle provides fallbacks when methods are not available:

```php
// Example: Type Registry
try {
    // Try DBAL 3.x
    if (method_exists(Type::class, 'getTypeRegistry')) {
        $type = Type::getTypeRegistry()->get($typeName);
    }
    // Fallback to DBAL 2.x
    elseif (method_exists(Type::class, 'getType')) {
        $type = Type::getType($typeName);
    }
} catch (\Exception $e) {
    // Fallback to manual mapping
    return $this->getFallbackType($typeName);
}
```

### 4. Reflection for Private Property Access

In DoctrineBundle 3.x, the bundle uses reflection to apply middleware:

```php
// Access private properties of Connection
$reflection = new \ReflectionClass($connection);
$driverProperty = $reflection->getProperty('driver');
$driverProperty->setAccessible(true);
$originalDriver = $driverProperty->getValue($connection);
```

**Relevant files**:
- `QueryTrackingMiddlewareRegistry::applyMiddlewareViaReflection()`
- `QueryTrackingConnectionSubscriber`

## Diagnostic Commands

The bundle includes a command to diagnose Doctrine configuration:

```bash
php bin/console nowo:performance:diagnose
```

This command shows:
- Detected DoctrineBundle version
- Method used to register middleware
- Connection status
- Schema Manager information

## Recommendations

### For Development

1. **Use the latest compatible version**: If you're on Symfony 8, use DoctrineBundle 3.x and ORM 3.x
2. **Test on multiple versions**: If your application must support multiple versions, test on all of them
3. **Check the logs**: The bundle logs which method it's using to apply middleware

### For Production

1. **Lock versions**: Use `composer.json` to lock specific versions
2. **Monitor logs**: The bundle logs compatibility errors
3. **Use the diagnostic command**: Run `nowo:performance:diagnose` after updating dependencies

## Troubleshooting

### Problem: Middleware not applied

**Symptoms**: Queries are not being tracked

**Solution**:
1. Run `php bin/console nowo:performance:diagnose`
2. Verify DoctrineBundle version
3. Check logs to see which method is being used
4. If you're on DoctrineBundle 3.x, verify that `QueryTrackingConnectionSubscriber` is registered

### Problem: Error "Unable to get schema manager"

**Symptoms**: Table creation commands fail

**Solution**:
1. Verify you're using DBAL 2.x or 3.x
2. The bundle should detect automatically, but if it fails, verify that `method_exists()` is working correctly

### Problem: Error "Type::getType() not found"

**Symptoms**: Errors when creating/updating tables

**Solution**:
1. Verify DBAL version
2. The bundle should use `getTypeRegistry()` in DBAL 3.x automatically
3. If it persists, verify that the manual fallback is working

## References

- [Doctrine DBAL 3.x Migration Guide](https://www.doctrine-project.org/projects/doctrine-dbal/en/3.7/reference/upgrade.html)
- [Doctrine ORM 3.x Migration Guide](https://www.doctrine-project.org/projects/doctrine-orm/en/3.0/reference/upgrade.html)
- [DoctrineBundle 3.x Changes](https://github.com/doctrine/DoctrineBundle/blob/3.0/UPGRADE-3.0.md)

## Compatibility Changelog

### Version 0.0.1 (2025-01-27)

- ✅ Initial support for Doctrine ORM 2.13+ and 3.0+
- ✅ Initial support for DoctrineBundle 2.8+ and 3.0+
- ✅ Automatic version detection
- ✅ Automatic middleware application based on version
- ✅ Compatibility with DBAL 2.x and 3.x
- ✅ Handling of metadata differences between ORM 2.x and 3.x
