#!/usr/bin/env bash
# =============================================================================
# setup.sh — Instalación base de Domiciliaria SaaS (pasos 3 a 6)
#
# Cubre:
#   3. Levantar contenedores Docker
#   4. Instalar dependencias PHP (composer install)
#   5. Ejecutar migraciones de la BD central
#   6. Generar y configurar APP_ENCRYPTION_KEY
#
# Los pasos 7 (crear clínicas) y 8 (cargar fixtures) se ejecutan por separado.
# Es seguro re-ejecutar este script — detecta qué ya está hecho y lo omite.
# =============================================================================

set -euo pipefail

# ── Colores ────────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

ok()   { echo -e "  ${GREEN}✓${NC}  $*"; }
skip() { echo -e "  ${YELLOW}→${NC}  $* ${YELLOW}(ya hecho)${NC}"; }
info() { echo -e "  ${BLUE}·${NC}  $*"; }
fail() { echo -e "  ${RED}✗${NC}  $*" >&2; exit 1; }
header() { echo -e "\n${BOLD}$*${NC}"; }

# ── Directorio del proyecto ────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# ── Verificar Docker activo ────────────────────────────────────────────────────
header "Verificando Docker..."
if ! docker info &>/dev/null; then
    fail "Docker no está corriendo. Abre Docker Desktop e inténtalo de nuevo."
fi
ok "Docker activo"

echo ""
echo -e "${BOLD}════════════════════════════════════════${NC}"
echo -e "${BOLD}  Domiciliaria SaaS — Setup (pasos 3–6)${NC}"
echo -e "${BOLD}════════════════════════════════════════${NC}"

# =============================================================================
# PASO 3 — Levantar contenedores
# =============================================================================
header "Paso 3 · Contenedores Docker"

if docker compose exec -T php echo ok &>/dev/null; then
    skip "Todos los contenedores ya están corriendo"
else
    info "Construyendo imágenes y levantando servicios..."
    docker compose up -d --build
    info "Reiniciando nginx para re-resolver IPs..."
    docker compose restart nginx
    ok "Contenedores levantados"
fi

# Esperar a que PostgreSQL esté listo
info "Esperando a PostgreSQL..."
INTENTOS=0
until docker compose exec -T postgres pg_isready -U app -q 2>/dev/null; do
    INTENTOS=$((INTENTOS + 1))
    if [[ $INTENTOS -ge 20 ]]; then
        fail "PostgreSQL no respondió después de 20 intentos. Revisa los logs: docker compose logs postgres"
    fi
    sleep 2
done
ok "PostgreSQL listo"

# =============================================================================
# PASO 4 — Composer install
# =============================================================================
header "Paso 4 · Dependencias PHP (composer install)"

if [[ -f "app/vendor/autoload_runtime.php" ]]; then
    skip "vendor/ ya existe"
else
    info "Instalando dependencias (puede tardar un minuto)..."
    docker compose exec -T php bash -c \
        "cd /var/www/html/app && composer install --no-plugins --no-interaction && composer dump-autoload"
    ok "Dependencias instaladas"
fi

# =============================================================================
# PASO 5 — Migraciones BD central
# =============================================================================
header "Paso 5 · Migraciones BD central"

TABLA_EXISTS=$(docker compose exec -T postgres psql -U app -d domiciliaria -tAc \
    "SELECT 1 FROM information_schema.tables WHERE table_name='tenant_db';" 2>/dev/null || true)

if [[ "$TABLA_EXISTS" == "1" ]]; then
    skip "Tabla tenant_db ya existe — migraciones aplicadas"
else
    info "Ejecutando migraciones..."
    docker compose exec -T php bash -c \
        "cd /var/www/html/app && php bin/console doctrine:migrations:migrate --no-interaction"
    ok "Migraciones ejecutadas"
fi

# =============================================================================
# PASO 6 — Clave de cifrado
# =============================================================================
header "Paso 6 · Clave de cifrado (APP_ENCRYPTION_KEY)"

ENV_LOCAL="app/.env.local"

if [[ -f "$ENV_LOCAL" ]] && grep -q "APP_ENCRYPTION_KEY=" "$ENV_LOCAL" 2>/dev/null; then
    skip "$ENV_LOCAL ya contiene APP_ENCRYPTION_KEY"
else
    info "Generando clave AES-256..."
    CLAVE=$(docker compose exec -T php bash -c \
        "cd /var/www/html/app && php bin/console app:security:generate-key --no-interaction 2>/dev/null" \
        | grep -oE 'def[0-9a-f]+' | head -1)

    if [[ -z "$CLAVE" ]]; then
        fail "No se pudo generar la clave. Revisa que el contenedor php esté sano."
    fi

    # Crear o agregar al .env.local
    if [[ -f "$ENV_LOCAL" ]]; then
        echo "APP_ENCRYPTION_KEY=${CLAVE}" >> "$ENV_LOCAL"
    else
        echo "APP_ENCRYPTION_KEY=${CLAVE}" > "$ENV_LOCAL"
    fi

    ok "Clave generada y guardada en $ENV_LOCAL"
    echo ""
    echo -e "  ${YELLOW}Guarda esta clave en un lugar seguro.${NC}"
    echo -e "  ${YELLOW}Sin ella los datos cifrados no se pueden recuperar.${NC}"
fi

# =============================================================================
# Resumen
# =============================================================================
echo ""
echo -e "${BOLD}════════════════════════════════════════${NC}"
echo -e "${GREEN}${BOLD}  ✓ Setup base completado${NC}"
echo -e "${BOLD}════════════════════════════════════════${NC}"
echo ""
echo -e "  Siguientes pasos:"
echo ""
echo -e "  ${BOLD}7. Crear clínicas:${NC}"
echo -e "     docker compose exec php bash -c \\"
echo -e "       \"cd /var/www/html/app && php bin/console app:tenant:crear 'Clínica Demo' demo\""
echo ""
echo -e "  ${BOLD}8. Cargar datos de prueba:${NC}"
echo -e "     docker compose exec php bash -c \\"
echo -e "       \"cd /var/www/html/app && php bin/console tenant:fixtures:load 1 --no-interaction\""
echo ""
echo -e "  ${BOLD}   O con datos custom:${NC}"
echo -e "     docker compose exec php bash -c \\"
echo -e "       \"cd /var/www/html/app && php bin/console app:seeder:interactivo\""
echo ""
