# Prompt Codex — Dynamic Controller Resolver v1

Proyecto: sistema multi-tenant Symfony 7.4 / PHP 8.3 para gestión domiciliaria de salud.
Rama de trabajo: `feature/dynamic-controller-resolver` (crear desde `develop`).

Aplica todos los cambios en un único commit.

---

## Objetivo

Implementar un sistema de resolución dinámica de controllers por tenant. Cuando un tenant tiene
un controller específico en `App\Controller\{Slug}\`, se usa ese en lugar del genérico en
`App\Controller\Default\`. Si no existe override, cae al default.

Ejemplo: tenant con slug `demo` que hace un request al dashboard →
- Se busca `App\Controller\Demo\DashboardController::index`
- Si existe, se usa ese
- Si no, se usa `App\Controller\Default\DashboardController::index`

---

## Cambios requeridos

### 1. Mover todos los controllers actuales a `Controller/Default/`

Mover los siguientes archivos de `app/src/Controller/` a `app/src/Controller/Default/`
**cambiando el namespace** de `App\Controller` a `App\Controller\Default`:

- `AuditoriaController.php`
- `CampoController.php`
- `ConfiguracionController.php`
- `DashboardController.php`
- `EventoAdversoController.php`
- `FinanzasController.php`
- `HelpController.php`
- `ImportController.php`
- `IntegracionController.php`
- `MandanteController.php`
- `NotificacionController.php`
- `PacienteController.php`
- `ResetPasswordController.php`
- `SecurityController.php`
- `TarifaController.php`
- `TrabajadorController.php`
- `TurnoController.php`
- `TwoFactorController.php`
- `UserController.php`

El archivo `app/src/Controller/Api/ApiController.php` **no se mueve** — queda en
`App\Controller\Api` sin cambios.

En cada archivo movido:
- Cambiar `namespace App\Controller;` → `namespace App\Controller\Default;`
- No modificar nada más (imports, atributos de ruta, lógica)

### 2. Actualizar `app/config/routes.yaml`

Archivo actual:
```yaml
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute

# 2FA routes requeridas por scheb/2fa-bundle
2fa_login:
    path: /2fa/check
    defaults:
        _controller: "scheb_two_factor.form_controller::form"

2fa_login_check:
    path: /2fa/check
    methods: [POST]
```

Reemplazar el bloque `controllers:` por tres bloques que carguen Default, los subdirectorios
de tenants y el Api:

```yaml
controllers_default:
    resource:
        path: ../src/Controller/Default/
        namespace: App\Controller\Default
    type: attribute

controllers_api:
    resource:
        path: ../src/Controller/Api/
        namespace: App\Controller\Api
    type: attribute

# 2FA routes requeridas por scheb/2fa-bundle
2fa_login:
    path: /2fa/check
    defaults:
        _controller: "scheb_two_factor.form_controller::form"

2fa_login_check:
    path: /2fa/check
    methods: [POST]
```

> **Importante:** los controllers de override de tenant (`Controller/Demo/`, `Controller/Norte/`, etc.)
> **no se registran como recursos de routing**. No tienen atributos `#[Route]`. Symfony resuelve
> todas las rutas contra `Controller/Default/`, y el `DynamicControllerSubscriber` swapea el
> controller en runtime. Agregar un tercer bloque que escanee `Controller/` causaría rutas
> duplicadas que sobreescriben a los defaults.

### 3. Crear `app/src/Service/DynamicControllerResolver.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Resuelve el controller real a ejecutar según el tenant activo.
 *
 * Orden de búsqueda para un controller App\Controller\Default\FooController::action
 * con tenant slug "demo":
 *   1. App\Controller\Demo\FooController::action
 *   2. App\Controller\Default\FooController::action  (fallback)
 */
class DynamicControllerResolver
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param string $originalController  FQCN::method, e.g. "App\Controller\Default\DashboardController::index"
     * @param string $tenantSlug          Slug del tenant activo, e.g. "demo"
     * @return string  El controller a usar (puede ser el mismo si no hay override)
     */
    public function resolveControllerFromRoute(string $originalController, string $tenantSlug): string
    {
        // Solo actúa sobre controllers en App\Controller\Default\
        if (!str_contains($originalController, 'App\\Controller\\Default\\')) {
            return $originalController;
        }

        // Separar FQCN del método
        $parts = explode('::', $originalController, 2);
        $fqcn  = $parts[0];
        $method = $parts[1] ?? '__invoke';

        // Obtener nombre corto: App\Controller\Default\DashboardController → DashboardController
        $shortClass = substr($fqcn, strrpos($fqcn, '\\') + 1);

        // Construir slug capitalizado como parte del namespace, e.g. "demo" → "Demo"
        $slugNamespace = implode('', array_map('ucfirst', explode('-', $tenantSlug)));

        // Candidato: App\Controller\{SlugNamespace}\DashboardController
        $candidate = 'App\\Controller\\' . $slugNamespace . '\\' . $shortClass;

        if (class_exists($candidate) && method_exists($candidate, $method)) {
            $this->logger->debug('[DynamicControllerResolver] Override encontrado', [
                'original'  => $originalController,
                'resolved'  => $candidate . '::' . $method,
                'tenant'    => $tenantSlug,
            ]);
            return $candidate . '::' . $method;
        }

        return $originalController;
    }
}
```

### 4. Actualizar `app/src/EventSubscriber/DynamicControllerSubscriber.php`

Reemplazar el archivo completo:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\DynamicControllerResolver;
use App\Service\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Priority 15: resuelve el controller específico del tenant antes de que Symfony lo ejecute.
 * Si el tenant tiene un override en App\Controller\{Slug}\, lo usa en lugar del Default.
 */
class DynamicControllerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantContext             $tenantContext,
        private readonly DynamicControllerResolver $controllerResolver,
        private readonly LoggerInterface           $logger,
        private readonly array $excludedControllers = [],
        private readonly array $excludedNamespaces   = [],
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Inyectar datos del tenant en los atributos del request
        $tenant = $this->tenantContext->getCurrentTenant();
        if ($tenant !== null) {
            $request->attributes->set('_tenant',    $tenant);
            $request->attributes->set('_tenant_db', $tenant['database_name'] ?? null);
        }

        // Obtener el slug del tenant activo (getCurrentSubdomain() devuelve el slug)
        $tenantSlug = $this->tenantContext->getCurrentSubdomain();
        if ($tenantSlug === null || $tenantSlug === '') {
            return;
        }

        // Obtener el controller actual del request
        $originalController = $request->attributes->get('_controller');
        if (!is_string($originalController) || $originalController === '') {
            return;
        }

        // Verificar listas de exclusión
        foreach ($this->excludedNamespaces as $ns) {
            if (str_starts_with($originalController, $ns)) {
                return;
            }
        }
        foreach ($this->excludedControllers as $excluded) {
            if ($originalController === $excluded) {
                return;
            }
        }

        // Resolver el controller según el tenant
        $resolvedController = $this->controllerResolver->resolveControllerFromRoute(
            $originalController,
            $tenantSlug
        );

        if ($resolvedController !== $originalController) {
            $this->logger->info('[DynamicControllerSubscriber] Usando override de controller', [
                'tenant'   => $tenantSlug,
                'original' => $originalController,
                'override' => $resolvedController,
            ]);
            $request->attributes->set('_controller', $resolvedController);
        }
    }
}
```

### 5. Actualizar `app/config/services.yaml`

Localizar el bloque actual de `App\EventSubscriber\DynamicControllerSubscriber`:

```yaml
    App\EventSubscriber\DynamicControllerSubscriber:
        arguments:
            $tenantContext:       '@App\Service\TenantContext'
            $logger:              '@logger'
            $excludedControllers: []
            $excludedNamespaces:  []
        tags:
            - { name: kernel.event_subscriber }
```

Reemplazarlo por:

```yaml
    App\EventSubscriber\DynamicControllerSubscriber:
        arguments:
            $tenantContext:       '@App\Service\TenantContext'
            $controllerResolver:  '@App\Service\DynamicControllerResolver'
            $logger:              '@logger'
            $excludedControllers: []
            $excludedNamespaces:  ['App\Controller\Api\\']
        tags:
            - { name: kernel.event_subscriber }
```

> Se excluye `App\Controller\Api\` porque los endpoints de API no tienen variantes por tenant.

### 6. Crear controllers de prueba por tenant

**`app/src/Controller/Demo/DashboardController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controller\Demo;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Override del DashboardController para el tenant "demo".
 * No tiene atributos #[Route] — el DynamicControllerSubscriber lo inyecta en runtime.
 */
class DashboardController extends AbstractController
{
    public function index(): Response
    {
        return new Response(
            '<h1>[DEMO] Dashboard override activo</h1><p>Este controller es específico del tenant demo.</p>',
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        );
    }
}
```

**`app/src/Controller/Norte/DashboardController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Controller\Norte;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Override del DashboardController para el tenant "norte".
 * No tiene atributos #[Route] — el DynamicControllerSubscriber lo inyecta en runtime.
 */
class DashboardController extends AbstractController
{
    public function index(): Response
    {
        return new Response(
            '<h1>[NORTE] Dashboard override activo</h1><p>Este controller es específico del tenant norte.</p>',
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        );
    }
}
```

> **Regla crítica:** Los controllers de override de tenant **nunca** deben tener `#[Route]`.
> Si se añade `#[Route]` con el mismo `name` que el Default, el último archivo cargado sobreescribe
> la ruta y el sistema de resolución dinámica queda roto. Los overrides son clases puras que
> Symfony instancia solo cuando el resolver lo indica.

---

## Verificación post-implementación

Ejecutar en el contenedor de la aplicación:

```bash
# Limpiar caché
php bin/console cache:clear

# Verificar que las rutas se cargan correctamente (deben aparecer bajo App\Controller\Default\)
php bin/console debug:router | grep dashboard

# Verificar que el container se construye sin errores
php bin/console debug:container DynamicControllerSubscriber
php bin/console debug:container DynamicControllerResolver
```

---

## Commit

```
feat(routing): implementar dynamic controller resolver por tenant

- Mover todos los controllers de Controller/ a Controller/Default/ con namespace App\Controller\Default
- Crear DynamicControllerResolver que busca overrides en App\Controller\{Slug}\
- Actualizar DynamicControllerSubscriber para usar el resolver en KernelEvents::REQUEST priority 15
- Actualizar routes.yaml para cargar Default/, Api/ y subdirectorios de tenants
- Agregar controllers de prueba en Controller/Demo/ y Controller/Norte/
- Excluir App\Controller\Api\ de la resolución dinámica
```
