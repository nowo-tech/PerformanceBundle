# Sistema de Notificaciones

El bundle incluye un sistema completo de notificaciones para alertas de performance que soporta múltiples canales.

## Canales Disponibles

### 📧 Email

Envía alertas por correo electrónico usando Symfony Mailer.

**Requisitos:**
```bash
composer require symfony/mailer
```

**Configuración:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        email:
            enabled: true
            from: 'noreply@example.com'
            to:
                - 'admin@example.com'
                - 'devops@example.com'
```

### 💬 Slack

Envía alertas a Slack usando webhooks.

**Requisitos:**
```bash
composer require symfony/http-client
```

**Configuración:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        slack:
            enabled: true
            webhook_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
```

**Obtener Webhook URL:**
1. Ve a https://api.slack.com/apps
2. Crea una nueva app o selecciona una existente
3. Ve a "Incoming Webhooks"
4. Activa "Incoming Webhooks"
5. Crea un nuevo webhook y copia la URL

### 👥 Microsoft Teams

Envía alertas a Microsoft Teams usando webhooks.

**Requisitos:**
```bash
composer require symfony/http-client
```

**Configuración:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        teams:
            enabled: true
            webhook_url: 'https://outlook.office.com/webhook/YOUR/WEBHOOK/URL'
```

**Obtener Webhook URL:**
1. En Teams, ve al canal donde quieres recibir notificaciones
2. Click en "..." → "Conectores"
3. Busca "Webhook entrante"
4. Configura y copia la URL

### 🔗 Webhooks Genéricos

Envía alertas a cualquier webhook personalizado.

**Requisitos:**
```bash
composer require symfony/http-client
```

**Configuración:**
```yaml
nowo_performance:
    notifications:
        enabled: true
        webhook:
            enabled: true
            url: 'https://your-custom-service.com/webhook'
            format: 'json'  # json, slack, o teams
            headers:
                'X-API-Key': 'your-api-key'
                'Authorization': 'Bearer your-token'
```

## Configuración Completa

```yaml
nowo_performance:
    # ... otras configuraciones ...
    
    notifications:
        enabled: true  # Habilita/deshabilita todas las notificaciones
        
        email:
            enabled: true
            from: 'noreply@example.com'
            to:
                - 'admin@example.com'
        
        slack:
            enabled: true
            webhook_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
        
        teams:
            enabled: true
            webhook_url: 'https://outlook.office.com/webhook/YOUR/WEBHOOK/URL'
        
        webhook:
            enabled: false
            url: ''
            format: 'json'
            headers: []
```

## Cómo Funciona

Las notificaciones se envían automáticamente cuando:

1. **Request Time** excede los thresholds configurados
2. **Query Count** excede los thresholds configurados
3. **Memory Usage** excede los thresholds configurados

Los thresholds se configuran en:
```yaml
nowo_performance:
    thresholds:
        request_time:
            warning: 0.5   # segundos
            critical: 1.0  # segundos
        query_count:
            warning: 20
            critical: 50
        memory_usage:
            warning: 20.0  # MB
            critical: 50.0 # MB
```

## Crear Canales Personalizados

Puedes crear tus propios canales de notificación implementando la interfaz `NotificationChannelInterface`:

```php
<?php

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Entity\RouteData;

class CustomNotificationChannel implements NotificationChannelInterface
{
    public function send(PerformanceAlert $alert, RouteData $routeData): bool
    {
        // Tu lógica de envío aquí
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'custom';
    }
}
```

Registra tu canal en `services.yaml`:

```yaml
services:
    App\Notification\CustomNotificationChannel:
        tags:
            - { name: 'nowo_performance.notification_channel', alias: 'custom' }
```

## Personalización de Templates de Email

Los emails se renderizan usando templates Twig que puedes personalizar.

### Templates por Defecto

El bundle incluye dos templates:
- `@NowoPerformanceBundle/Notification/email_alert.html.twig` - Versión HTML
- `@NowoPerformanceBundle/Notification/email_alert.txt.twig` - Versión texto plano

### Personalizar Templates

Puedes sobrescribir los templates creando tus propias versiones:

**1. Crea el template en tu proyecto:**

```
templates/
  bundles/
    NowoPerformanceBundle/
      Notification/
        email_alert.html.twig
        email_alert.txt.twig
```

**2. Variables disponibles en los templates:**

- `alert` - Objeto `PerformanceAlert` con:
  - `alert.message` - Mensaje del alerta
  - `alert.type` - Tipo de alerta (request_time, query_count, etc.)
  - `alert.severity` - Severidad (warning, critical)
  - `alert.context` - Array con contexto adicional
- `routeData` - Entidad `RouteData` con todas las propiedades
- `severityColor` - Color HTML para la severidad (#dc3545 para critical, #ffc107 para warning)
- `severityLabel` - Etiqueta de severidad (Critical, Warning)

**3. Ejemplo de template personalizado:**

```twig
{# templates/bundles/NowoPerformanceBundle/Notification/email_alert.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Tus estilos personalizados */
    </style>
</head>
<body>
    <h1>{{ severityLabel }} Alert</h1>
    <p>{{ alert.message }}</p>
    
    <h2>Route: {{ routeData.name }}</h2>
    <p>Request Time: {{ routeData.requestTime|number_format(4) }}s</p>
    {# ... más contenido personalizado ... #}
</body>
</html>
```

**Nota:** Si Twig no está disponible, el bundle usa un fallback simple con HTML básico.

## Formato de Alertas

### Email

Los emails incluyen:
- Título con severidad (Warning/Critical)
- Información completa de la ruta
- Tabla con todas las métricas
- Contexto del alerta
- Templates personalizables usando Twig

### Slack

Formato de mensaje Slack con:
- Color según severidad (amarillo para warning, rojo para critical)
- Campos con información de la ruta
- Timestamp

### Teams

Formato MessageCard de Teams con:
- Color según severidad
- Sección con facts (hechos) sobre la ruta
- Formato compatible con Teams

### Webhook JSON

Formato genérico JSON:
```json
{
    "alert": {
        "type": "request_time",
        "severity": "critical",
        "message": "Critical: Route 'app_home' has request time of 1.2345s",
        "context": {
            "value": 1.2345,
            "threshold": 1.0
        }
    },
    "route": {
        "name": "app_home",
        "env": "prod",
        "http_method": "GET",
        "request_time": 1.2345,
        "query_count": 15,
        "query_time": 0.5,
        "memory_usage": 1048576,
        "access_count": 100,
        "last_accessed_at": "2025-01-26T10:30:00+00:00"
    },
    "timestamp": "2025-01-26T10:30:00+00:00"
}
```

## Deshabilitar Notificaciones

Para deshabilitar todas las notificaciones:

```yaml
nowo_performance:
    notifications:
        enabled: false
```

O deshabilita canales individuales:

```yaml
nowo_performance:
    notifications:
        enabled: true
        email:
            enabled: false
        slack:
            enabled: true
```

## Configuración Dinámica desde Base de Datos

Si necesitas almacenar las credenciales de notificaciones en la base de datos en lugar del archivo YAML, puedes usar un servicio para configurar los canales dinámicamente.

### Opción 1: Usando Compiler Pass (Recomendado para configuración estática)

Este enfoque registra los canales durante la compilación del contenedor. Útil cuando la configuración no cambia frecuentemente.

**1. Crea el servicio de configuración dinámica:**

```php
<?php
// src/Service/DynamicNotificationConfiguration.php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DynamicNotificationConfiguration
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?MailerInterface $mailer = null,
        private readonly ?HttpClientInterface $httpClient = null
    ) {
    }

    private function getNotificationConfigFromDatabase(): array
    {
        // Obtener configuración desde tu entidad/tabla
        $settings = $this->entityManager
            ->getRepository('App\Entity\NotificationSettings')
            ->findOneBy(['key' => 'performance_notifications']);
        
        if ($settings && $settings->getValue()) {
            return json_decode($settings->getValue(), true) ?? [];
        }
        
        return ['enabled' => false];
    }

    public function createChannelsFromDatabase(): array
    {
        $config = $this->getNotificationConfigFromDatabase();
        $channels = [];

        // Email
        if ($config['email']['enabled'] ?? false) {
            $channels[] = new EmailNotificationChannel(
                $this->mailer,
                $config['email']['from'] ?? 'noreply@example.com',
                $config['email']['to'] ?? [],
                true
            );
        }

        // Slack
        if ($config['slack']['enabled'] ?? false && !empty($config['slack']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['slack']['webhook_url'],
                'slack',
                [],
                true
            );
        }

        // Teams
        if ($config['teams']['enabled'] ?? false && !empty($config['teams']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['teams']['webhook_url'],
                'teams',
                [],
                true
            );
        }

        // Webhook genérico
        if ($config['webhook']['enabled'] ?? false && !empty($config['webhook']['url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['webhook']['url'],
                $config['webhook']['format'] ?? 'json',
                $config['webhook']['headers'] ?? [],
                true
            );
        }

        return $channels;
    }

    public function areNotificationsEnabled(): bool
    {
        $config = $this->getNotificationConfigFromDatabase();
        return $config['enabled'] ?? false;
    }
}
```

**2. Crea el Compiler Pass:**

```php
<?php
// src/DependencyInjection/Compiler/NotificationCompilerPass.php

namespace App\DependencyInjection\Compiler;

use App\Service\DynamicNotificationConfiguration;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class NotificationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_performance.notifications.enabled')) {
            return;
        }

        try {
            $configService = new DynamicNotificationConfiguration(
                $container->get('doctrine.orm.entity_manager'),
                $container->has('mailer.mailer') ? $container->get('mailer.mailer') : null,
                $container->has('http_client') ? $container->get('http_client') : null
            );

            $channels = $configService->createChannelsFromDatabase();

            foreach ($channels as $channel) {
                $serviceId = sprintf('nowo_performance.notification.channel.dynamic.%s', $channel->getName());
                $definition = new Definition(get_class($channel));
                
                // Configurar argumentos según el tipo de canal...
                // (ver ejemplo completo en docs/examples/NotificationCompilerPass.php)
                
                $definition->setPublic(true);
                $definition->addTag('nowo_performance.notification_channel', [
                    'alias' => $channel->getName()
                ]);
                
                $container->setDefinition($serviceId, $definition);
            }
        } catch (\Exception $e) {
            // Usar configuración YAML por defecto si falla
            error_log('Error loading notification config from database: ' . $e->getMessage());
        }
    }
}
```

**3. Registra el Compiler Pass en tu Bundle:**

```php
<?php
// src/Kernel.php o src/YourBundle.php

use App\DependencyInjection\Compiler\NotificationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends BaseKernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new NotificationCompilerPass());
    }
}
```

### Opción 2: Usando Factory Service (Recomendado para configuración dinámica)

Este enfoque crea los canales bajo demanda. Útil cuando las credenciales cambian frecuentemente.

**1. Crea el Factory Service:**

```php
<?php
// src/Service/NotificationFactoryService.php

namespace App\Service;

use Nowo\PerformanceBundle\Service\NotificationService;

class NotificationFactoryService
{
    public function __construct(
        private readonly DynamicNotificationConfiguration $configService
    ) {
    }

    public function createNotificationService(): NotificationService
    {
        $channels = $this->configService->createChannelsFromDatabase();
        $enabled = $this->configService->areNotificationsEnabled();

        return new NotificationService($channels, $enabled);
    }
}
```

**2. Registra el Factory Service:**

```yaml
# config/services.yaml
services:
    App\Service\DynamicNotificationConfiguration:
        public: true
    
    App\Service\NotificationFactoryService:
        arguments:
            $configService: '@App\Service\DynamicNotificationConfiguration'
        public: true
```

**3. Usa el Factory Service en tu código:**

```php
<?php
// En un EventListener o Service

use App\Service\NotificationFactoryService;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;

class PerformanceAlertListener
{
    public function __construct(
        private readonly NotificationFactoryService $notificationFactory
    ) {
    }

    public function onAfterMetricsRecorded(AfterMetricsRecordedEvent $event): void
    {
        // Crear NotificationService con configuración actualizada de BD
        $notificationService = $this->notificationFactory->createNotificationService();
        
        // Usar el servicio normalmente
        if ($event->getRouteData()->getRequestTime() > 1.0) {
            $alert = new PerformanceAlert(/* ... */);
            $notificationService->sendAlert($alert, $event->getRouteData());
        }
    }
}
```

### Estructura de Base de Datos Recomendada

Ejemplo de entidad para almacenar la configuración:

```php
<?php
// src/Entity/NotificationSettings.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_settings')]
class NotificationSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', unique: true)]
    private string $key;

    #[ORM\Column(type: 'text')]
    private string $value;

    // Getters y setters...
}
```

**Ejemplo de valor JSON almacenado:**

```json
{
    "enabled": true,
    "email": {
        "enabled": true,
        "from": "noreply@example.com",
        "to": ["admin@example.com", "devops@example.com"]
    },
    "slack": {
        "enabled": true,
        "webhook_url": "https://hooks.slack.com/services/YOUR/WEBHOOK/URL"
    },
    "teams": {
        "enabled": false,
        "webhook_url": ""
    },
    "webhook": {
        "enabled": true,
        "url": "https://api.example.com/webhook",
        "format": "json",
        "headers": {
            "X-API-Key": "secret-key"
        }
    }
}
```

### Ejemplos Completos

Ver ejemplos completos y bien documentados en:
- `docs/examples/DynamicNotificationConfiguration.php` - Servicio de configuración
- `docs/examples/NotificationCompilerPass.php` - Compiler Pass completo
- `docs/examples/NotificationFactoryService.php` - Factory Service completo

## Troubleshooting

### Las notificaciones no se envían

1. Verifica que `notifications.enabled: true`
2. Verifica que el canal específico esté habilitado
3. Verifica que las dependencias estén instaladas (mailer/http-client)
4. Revisa los logs para errores

### Email no funciona

- Verifica la configuración de Symfony Mailer
- Verifica que `from` y `to` estén configurados
- Revisa la configuración SMTP

### Webhooks no funcionan

- Verifica que la URL del webhook sea correcta
- Verifica que `symfony/http-client` esté instalado
- Revisa los logs para errores HTTP
- Prueba la URL del webhook manualmente

### Configuración dinámica no funciona

- **Compiler Pass**: Verifica que la BD esté disponible durante `cache:clear`
- **Factory Service**: Verifica que el servicio esté correctamente inyectado
- Revisa los logs para errores de conexión a BD
- Verifica que la estructura JSON en BD sea correcta
