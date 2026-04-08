# Agregar un nuevo tenant (clínica)

## Resumen del flujo

```
Desarrollo local  →  /etc/hosts + app:tenant:crear
Producción        →  DNS wildcard + app:tenant:crear
```

---

## Desarrollo local

### 1. Agregar entrada en `/etc/hosts`

El servidor Nginx ya tiene configurado un virtual host con wildcard `*.localhost`,
por lo que solo es necesario agregar la resolución DNS local.

**macOS / Linux:**
```bash
sudo echo "127.0.0.1  nuevaclinica.localhost" >> /etc/hosts
```

**Windows** (`C:\Windows\System32\drivers\etc\hosts`):
```
127.0.0.1  nuevaclinica.localhost
```

> **Nota:** Este es el único paso manual en desarrollo. En producción
> el DNS wildcard lo maneja el proveedor de hosting automáticamente.

### 2. Crear el tenant

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Nombre Clínica' subdominio"
```

El comando realiza automáticamente:
- Crea la base de datos PostgreSQL `clinica_{subdominio}`
- Registra el tenant en la BD central (`domiciliaria`)
- Ejecuta las 10 migraciones del schema tenant
- Verifica que el subdominio sea único

**Ejemplo:**
```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Prueba' prueba"
```

Resultado esperado:
```
 ✓ Base de datos creada.
 ✓ Tenant registrado con ID: 3
 ✓ Migraciones ejecutadas.
 [OK] Tenant creado correctamente.
```

### 3. Cargar datos de prueba (opcional)

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console tenant:fixtures:load {ID} --no-interaction"
```

### 4. Acceder al sistema

```
http://prueba.localhost:8090/login
```

Credenciales por defecto (si se cargaron fixtures):
| Email | Contraseña | Rol |
|-------|-----------|-----|
| admin@clinica-prueba.cl | admin1234 | Administrador |

---

## Producción

En producción **no se toca nginx ni DNS manualmente**. El proveedor de hosting
debe tener configurado un registro DNS wildcard:

```
*.domiciliaria.cl  →  A  →  IP_DEL_SERVIDOR
```

Con eso, cualquier subdominio nuevo funciona automáticamente al ejecutar el comando:

```bash
php bin/console app:tenant:crear 'Clínica Nueva' nuevaclinica
```

El sistema estará disponible en:
```
https://nuevaclinica.domiciliaria.cl/login
```

---

## Aplicar migraciones a todos los tenants existentes

Cuando se agrega una nueva migración al schema tenant (`migrations/Tenant/`):

```bash
docker exec domiciliaria-php-1 bash -c \
  "cd /var/www/html/app && php bin/console app:tenant:migrate-all"
```

---

## Eliminar un tenant

> ⚠️ **Operación destructiva e irreversible.**

```bash
# 1. Marcar como inactivo (recomendado)
# Editar directamente en BD central: UPDATE tenant_db SET is_active = false WHERE slug = 'subdominio';

# 2. Eliminar BD del tenant (destructivo)
docker exec domiciliaria-postgres-1 psql -U app -c "DROP DATABASE clinica_subdominio;"

# 3. Eliminar registro en BD central
docker exec domiciliaria-postgres-1 psql -U app -d domiciliaria \
  -c "DELETE FROM tenant_db WHERE slug = 'subdominio';"
```
