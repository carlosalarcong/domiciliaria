# Sistema de Atención Domiciliaria

Sistema web de gestión de atención domiciliaria para empresas de salud en Chile, construido con Symfony 7.4 y PHP 8.3.

## Stack tecnológico

- **PHP 8.3** + **Symfony 7.4** (LTS)
- **PostgreSQL 16** con Doctrine ORM y migraciones
- **Redis** para sesiones y mensajería asíncrona
- **Twig** + **Bootstrap 5** para el frontend
- **Gedmo DoctrineExtensions** para audit log automático y timestamps
- **Docker** para el entorno local

## Requisitos

- Docker Desktop
- Git

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/carlosalarcong/domiciliaria.git
cd domiciliaria
```

### 2. Levantar los contenedores

```bash
docker compose up -d --build
```

Esto levanta:
| Servicio | Puerto |
|----------|--------|
| Nginx (app web) | http://localhost:8090 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

### 3. Instalar dependencias PHP

```bash
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && composer install --no-plugins && composer dump-autoload"
```

### 4. Ejecutar migraciones y cargar datos de prueba

```bash
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console doctrine:migrations:migrate --no-interaction"
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console doctrine:fixtures:load --no-interaction"
```

### 5. Acceder al sistema

Abre http://localhost:8090 en tu navegador.

## Usuarios de prueba

| Email | Contraseña | Rol |
|-------|-----------|-----|
| admin@domiciliaria.cl | admin1234 | Administrador |
| coordinador@domiciliaria.cl | coord1234 | Coordinador |
| enfermera@domiciliaria.cl | enf1234! | Enfermera |
| tens@domiciliaria.cl | tens1234 | TENS |
| visualizador@domiciliaria.cl | vis12345 | Visualizador |

## Estructura del proyecto

```
domiciliaria/
├── docker/
│   ├── nginx/default.conf      # Configuración Nginx
│   └── php/Dockerfile          # PHP 8.3 + extensiones + Composer
├── app/
│   ├── config/                 # Configuración Symfony
│   ├── migrations/             # Migraciones de base de datos
│   ├── src/
│   │   ├── Controller/         # Controladores HTTP
│   │   ├── Entity/             # Entidades Doctrine
│   │   ├── Form/               # Formularios Symfony
│   │   ├── Repository/         # Repositorios de datos
│   │   ├── Security/Voter/     # Voters para permisos granulares
│   │   └── Service/            # Lógica de negocio
│   ├── templates/              # Vistas Twig
│   └── tests/                  # Tests PHPUnit
└── docker-compose.yml
```

## Roles y permisos

| Rol | Turnos | Pacientes clínicos | Finanzas | Usuarios |
|-----|--------|-------------------|----------|----------|
| ROLE_ADMIN | ✅ CRUD | ✅ | ✅ | ✅ |
| ROLE_COORDINADOR | ✅ Crear/Editar | ✅ | ✅ | ❌ |
| ROLE_ENFERMERA | 👁 Ver | ✅ | ❌ | ❌ |
| ROLE_TENS | 👁 Ver | ❌ | ❌ | ❌ |
| ROLE_VISUALIZADOR | 👁 Ver | ❌ | ❌ | ❌ |

## Comandos útiles

```bash
# Consola Symfony
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console <comando>"

# Ejecutar tests
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && ./vendor/bin/phpunit --testdox"

# Generar nueva migración tras cambiar entidades
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate --no-interaction"

# Ver rutas registradas
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console debug:router"

# Limpiar caché
docker exec domicialiaria-php-1 bash -c "cd /var/www/html/app && php bin/console cache:clear"
```

## Fases del proyecto

- [x] **Fase 1** — Auth, roles y estructura base
- [ ] **Fase 2** — Módulo de Pacientes
- [ ] **Fase 3** — Módulo de Turnos y Calendario
- [ ] **Fase 4** — Módulo de Personal
- [ ] **Fase 5** — Módulo de Eventos Adversos
- [ ] **Fase 6** — Módulo de Finanzas y Facturación

## Notas técnicas

### SSL corporativo
Si el entorno tiene inspección SSL (certificado autofirmado en la cadena), instalar dependencias con `--no-plugins` para evitar que Symfony Flex intente descargar recetas vía HTTPS:
```bash
composer require vendor/paquete --no-plugins
composer dump-autoload  # regenera autoload_runtime.php
```

### Audit log
Todas las entidades marcadas con `#[Gedmo\Loggable]` registran cambios automáticamente en la tabla `ext_log_entries`. Se usa una entidad `LogEntry` personalizada con campo `data` de tipo `json` para compatibilidad con Doctrine DBAL 4.
