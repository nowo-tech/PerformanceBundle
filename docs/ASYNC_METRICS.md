# Guardado Asíncrono de Métricas

El bundle soporta el guardado asíncrono de métricas de rendimiento usando Symfony Messenger. Esto permite que las métricas se guarden en background sin bloquear la respuesta HTTP.

## Requisitos

Para usar el modo asíncrono, necesitas instalar Symfony Messenger:

```bash
composer require symfony/messenger
```

## Configuración

### 1. Habilitar el modo asíncrono

En tu archivo de configuración (`config/packages/nowo_performance.yaml`):

```yaml
nowo_performance:
    async: true  # Habilita el guardado asíncrono
    # ... resto de configuración
```

### 2. Configurar Messenger

El bundle usa el bus de mensajes por defecto. Asegúrate de tener Messenger configurado:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        # Por defecto, los mensajes se procesan de forma síncrona
        # Para procesamiento asíncrono, configura un transporte
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        
        routing:
            # Opcional: enruta los mensajes de métricas a un transporte específico
            'Nowo\PerformanceBundle\Message\RecordMetricsMessage': async
```

### 3. Procesar mensajes (si usas transporte asíncrono)

Si configuraste un transporte asíncrono, necesitas ejecutar el worker:

```bash
php bin/console messenger:consume async -vv
```

O en producción con supervisord/systemd para mantener el worker corriendo.

## Cómo Funciona

### Modo Síncrono (por defecto)

Cuando `async: false` (o no está configurado):

1. Las métricas se calculan al final de la petición
2. Se guardan inmediatamente en la base de datos
3. La respuesta HTTP se envía después de guardar

### Modo Asíncrono

Cuando `async: true` y Messenger está disponible:

1. Las métricas se calculan al final de la petición
2. Se crea un mensaje `RecordMetricsMessage` con los datos
3. El mensaje se envía al bus de mensajes
4. La respuesta HTTP se envía inmediatamente (sin esperar el guardado)
5. El mensaje se procesa en background (síncrono o asíncrono según tu configuración)

## Ventajas del Modo Asíncrono

- **Mejor rendimiento**: La respuesta HTTP no se bloquea esperando el guardado en BD
- **Escalabilidad**: Puedes procesar métricas en workers separados
- **Resiliencia**: Si falla el guardado, no afecta la respuesta al usuario
- **Carga distribuida**: Puedes distribuir el procesamiento de métricas

## Ejemplo de Configuración Completa

### Desarrollo (síncrono para debugging)

```yaml
# config/packages/dev/nowo_performance.yaml
nowo_performance:
    async: false  # Guardado inmediato para ver resultados al instante
    environments: ['dev']
```

### Producción (asíncrono)

```yaml
# config/packages/prod/nowo_performance.yaml
nowo_performance:
    async: true  # Guardado en background
    environments: ['prod']
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: 'doctrine://default'
        
        routing:
            'Nowo\PerformanceBundle\Message\RecordMetricsMessage': async
```

## Verificación

Para verificar que el modo asíncrono está funcionando:

1. Habilita el modo asíncrono en la configuración
2. Realiza algunas peticiones
3. Verifica que las métricas aparecen en la base de datos (puede haber un pequeño retraso)
4. Revisa los logs del worker de Messenger si usas transporte asíncrono

## Fallback Automático

Si `async: true` pero Messenger no está disponible:

- El bundle automáticamente usa el modo síncrono
- No se generan errores
- Las métricas se guardan normalmente

Esto permite que el bundle funcione sin Messenger, pero puedes optar por el modo asíncrono cuando lo necesites.

## Troubleshooting

### Las métricas no se guardan

1. Verifica que Messenger está instalado: `composer show symfony/messenger`
2. Verifica la configuración: `async: true` en `nowo_performance.yaml`
3. Si usas transporte asíncrono, verifica que el worker está corriendo
4. Revisa los logs de Symfony y Messenger

### Las métricas se guardan pero con retraso

Esto es normal en modo asíncrono. El retraso depende de:
- Si usas transporte síncrono: casi inmediato
- Si usas transporte asíncrono: depende de la velocidad del worker

### Quiero procesamiento inmediato pero sin bloquear

Usa Messenger con transporte síncrono (por defecto) y `async: true`. Los mensajes se procesan inmediatamente pero en un contexto separado que no bloquea la respuesta HTTP.
