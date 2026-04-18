<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Main\TenantDb;
use App\Entity\Tenant\CondicionDomicilio;
use App\Entity\Tenant\EventoAdverso;
use App\Entity\Tenant\Factura;
use App\Entity\Tenant\ItemLiquidacion;
use App\Entity\Tenant\LiquidacionMensual;
use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Tarifa;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Entity\Tenant\User;
use App\Enum\EstadoFactura;
use App\Enum\EstadoLiquidacion;
use App\Enum\EstadoPaciente;
use App\Enum\EstadoTrabajador;
use App\Enum\EstadoTurno;
use App\Enum\GravedadEvento;
use App\Enum\PerfilTrabajador;
use App\Enum\TipoConcepto;
use App\Enum\TipoEventoAdverso;
use App\Enum\TipoServicio;
use App\Enum\TipoTurno;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comando interactivo para sembrar datos de prueba custom en un tenant.
 *
 * Uso:
 *   php bin/console app:seeder:interactivo
 */
#[AsCommand(
    name: 'app:seeder:interactivo',
    description: 'Agrega datos de prueba custom a un tenant de forma interactiva',
)]
class SeederInteractivoCommand extends Command
{
    // ─── Pools de datos chilenos para generación ──────────────────────────────

    private const NOMBRES_MASCULINOS = [
        'Carlos', 'Luis', 'Pedro', 'Juan', 'Roberto', 'Diego', 'Felipe', 'Andrés',
        'Sebastián', 'Rodrigo', 'Fernando', 'Ignacio', 'Cristóbal', 'Tomás', 'Matías',
    ];

    private const NOMBRES_FEMENINOS = [
        'María', 'Ana', 'Carmen', 'Rosa', 'Valentina', 'Daniela', 'Francisca', 'Javiera',
        'Camila', 'Sofía', 'Catalina', 'Isidora', 'Constanza', 'Lorena', 'Pamela',
    ];

    private const APELLIDOS = [
        'González', 'Muñoz', 'Rojas', 'Díaz', 'Pérez', 'Soto', 'Contreras', 'Silva',
        'Martínez', 'Sepúlveda', 'Morales', 'Rodríguez', 'López', 'Fuentes', 'Hernández',
        'Torres', 'Araya', 'Flores', 'Espinoza', 'Castillo', 'Vargas', 'Reyes', 'Medina',
    ];

    private const COMUNAS_RM = [
        'Santiago', 'Providencia', 'Las Condes', 'Ñuñoa', 'La Florida', 'Maipú',
        'Pudahuel', 'San Miguel', 'La Reina', 'Vitacura', 'Lo Barnechea', 'Peñalolén',
    ];

    private const NOMBRES_MANDANTES = [
        'FONASA Región Metropolitana', 'ISAPRE Banmédica', 'ISAPRE Cruz Blanca',
        'Mutual de Seguridad', 'Hospital Clínico UC', 'Clínica Las Condes',
        'Particular Familia García', 'Particular Familia Rodríguez',
        'ISAPRE Colmena', 'Esencial Salud SpA',
    ];

    public function __construct(
        private readonly EntityManagerInterface   $defaultEm,
        private readonly EntityManagerInterface   $tenantEm,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EncryptionService        $encryption,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeder interactivo — Domiciliaria SaaS');

        // ── 1. Seleccionar tenant ─────────────────────────────────────────────
        $tenant = $this->seleccionarTenant($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        // ── 2. Conectar al tenant ─────────────────────────────────────────────
        $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
        $this->tenantEm->clear();

        $io->section(sprintf('Tenant: %s  (BD: %s)', $tenant->getName(), $tenant->getDatabaseName()));
        $this->mostrarEstadoActual($io);

        // ── 3. Seleccionar qué datos agregar ──────────────────────────────────
        $opciones = $this->seleccionarTiposDatos($input, $output, $io);
        if (empty($opciones)) {
            $io->warning('No seleccionaste ningún tipo de dato. Saliendo.');
            return Command::SUCCESS;
        }

        // ── 4. Validar dependencias antes de ejecutar ─────────────────────────
        $errores = $this->validarDependencias($opciones);
        if (!empty($errores)) {
            $io->error($errores);
            return Command::FAILURE;
        }

        // ── 5. Ejecutar seeders seleccionados ─────────────────────────────────
        $resumen = [];

        if (in_array('usuarios', $opciones, true)) {
            $n = $this->seedUsuarios($io, $tenant);
            $resumen[] = ['Usuarios', $n];
        }

        if (in_array('mandantes', $opciones, true)) {
            $n = $this->seedMandantes($io);
            $resumen[] = ['Mandantes', $n];
        }

        if (in_array('pacientes', $opciones, true)) {
            $n = $this->seedPacientes($io);
            $resumen[] = ['Pacientes', $n];
        }

        if (in_array('trabajadores', $opciones, true)) {
            $n = $this->seedTrabajadores($io);
            $resumen[] = ['Trabajadores', $n];
        }

        if (in_array('turnos', $opciones, true)) {
            $n = $this->seedTurnos($io);
            $resumen[] = ['Turnos', $n];
        }

        if (in_array('tarifas', $opciones, true)) {
            $n = $this->seedTarifas($io);
            $resumen[] = ['Tarifas', $n];
        }

        if (in_array('eventos_adversos', $opciones, true)) {
            $n = $this->seedEventosAdversos($io);
            $resumen[] = ['Eventos adversos', $n];
        }

        if (in_array('liquidaciones', $opciones, true)) {
            $n = $this->seedLiquidaciones($io);
            $resumen[] = ['Liquidaciones', $n];
        }

        if (in_array('facturas', $opciones, true)) {
            $n = $this->seedFacturas($io);
            $resumen[] = ['Facturas', $n];
        }

        // ── 6. Resumen final ──────────────────────────────────────────────────
        $io->success('Seeder completado.');
        $io->table(['Entidad', 'Registros creados'], $resumen);

        return Command::SUCCESS;
    }

    // ─── Selección de tenant ──────────────────────────────────────────────────

    private function seleccionarTenant(SymfonyStyle $io): ?TenantDb
    {
        /** @var TenantDb[] $tenants */
        $tenants = $this->defaultEm->getRepository(TenantDb::class)->findBy(['isActive' => true]);

        if (empty($tenants)) {
            $io->error('No hay tenants activos. Crea uno con app:tenant:crear primero.');
            return null;
        }

        $opciones = [];
        foreach ($tenants as $t) {
            $opciones[] = sprintf('%s  [%s]  BD: %s', $t->getName(), $t->getSlug(), $t->getDatabaseName());
        }

        $seleccion = $io->choice('¿A qué clínica quieres agregar datos?', $opciones, $opciones[0]);
        $idx = array_search($seleccion, $opciones, true);

        return $tenants[(int) $idx];
    }

    // ─── Selección de tipos de datos (multi-select) ───────────────────────────

    private function seleccionarTiposDatos(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
    ): array {
        $disponibles = [
            'usuarios'         => 'Usuarios (cuentas de acceso al sistema)',
            'mandantes'        => 'Mandantes (empresas/entidades contratantes)',
            'pacientes'        => 'Pacientes  [requiere: mandantes en la BD]',
            'trabajadores'     => 'Trabajadores (personal sanitario)',
            'turnos'           => 'Turnos     [requiere: pacientes + trabajadores]',
            'tarifas'          => 'Tarifas (valores por tipo de turno)',
            'eventos_adversos' => 'Eventos adversos  [requiere: pacientes + trabajadores]',
            'liquidaciones'    => 'Liquidaciones mensuales  [requiere: trabajadores]',
            'facturas'         => 'Facturas a mandantes  [requiere: mandantes]',
        ];

        $etiquetas = array_values($disponibles);
        $claves    = array_keys($disponibles);

        $io->writeln('');
        $io->writeln('<info>Usa ESPACIO para marcar, ENTER para confirmar.</info>');
        $io->writeln('<comment>Puedes seleccionar varios a la vez separados por coma (ej: 0,1,3)</comment>');
        $io->writeln('');

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $pregunta = new ChoiceQuestion(
            '¿Qué datos quieres agregar? (separa con coma)',
            $etiquetas,
        );
        $pregunta->setMultiselect(true);
        $pregunta->setErrorMessage('Opción "%s" no válida.');

        $seleccionadas = $helper->ask($input, $output, $pregunta);

        // Convertir etiquetas de vuelta a claves
        $resultado = [];
        foreach ($seleccionadas as $etiqueta) {
            $idx = array_search($etiqueta, $etiquetas, true);
            if ($idx !== false) {
                $resultado[] = $claves[$idx];
            }
        }

        return $resultado;
    }

    // ─── Estado actual del tenant ─────────────────────────────────────────────

    private function mostrarEstadoActual(SymfonyStyle $io): void
    {
        $io->writeln('<comment>Estado actual de la BD:</comment>');
        $io->table(
            ['Entidad', 'Registros'],
            [
                ['Usuarios',    $this->count(User::class)],
                ['Mandantes',   $this->count(Mandante::class)],
                ['Pacientes',   $this->count(Paciente::class)],
                ['Trabajadores',$this->count(Trabajador::class)],
                ['Turnos',      $this->count(Turno::class)],
                ['Tarifas',     $this->count(Tarifa::class)],
                ['Liquidaciones',$this->count(LiquidacionMensual::class)],
                ['Facturas',    $this->count(Factura::class)],
            ]
        );
    }

    private function count(string $class): int
    {
        return (int) $this->tenantEm->getRepository($class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ─── Validación de dependencias ───────────────────────────────────────────

    /**
     * @param string[] $opciones
     * @return string[]
     */
    private function validarDependencias(array $opciones): array
    {
        $errores = [];

        if (in_array('pacientes', $opciones, true)) {
            $tieneMandantes = in_array('mandantes', $opciones, true)
                || $this->count(Mandante::class) > 0;
            if (!$tieneMandantes) {
                $errores[] = 'Pacientes requiere mandantes: selecciona "Mandantes" también o crea mandantes primero.';
            }
        }

        if (in_array('turnos', $opciones, true)) {
            $tienePacientes = in_array('pacientes', $opciones, true)
                || $this->count(Paciente::class) > 0;
            $tieneTrabajadores = in_array('trabajadores', $opciones, true)
                || $this->count(Trabajador::class) > 0;

            if (!$tienePacientes) {
                $errores[] = 'Turnos requiere pacientes: selecciona "Pacientes" también o crea pacientes primero.';
            }
            if (!$tieneTrabajadores) {
                $errores[] = 'Turnos requiere trabajadores: selecciona "Trabajadores" también o crea trabajadores primero.';
            }
        }

        if (in_array('eventos_adversos', $opciones, true)) {
            $tienePacientes = in_array('pacientes', $opciones, true)
                || $this->count(Paciente::class) > 0;
            $tieneTrabajadores = in_array('trabajadores', $opciones, true)
                || $this->count(Trabajador::class) > 0;

            if (!$tienePacientes) {
                $errores[] = 'Eventos adversos requiere pacientes: selecciona "Pacientes" también o crea pacientes primero.';
            }
            if (!$tieneTrabajadores) {
                $errores[] = 'Eventos adversos requiere trabajadores: selecciona "Trabajadores" también o crea trabajadores primero.';
            }
        }

        if (in_array('liquidaciones', $opciones, true)) {
            $tieneTrabajadores = in_array('trabajadores', $opciones, true)
                || $this->count(Trabajador::class) > 0;
            if (!$tieneTrabajadores) {
                $errores[] = 'Liquidaciones requiere trabajadores: selecciona "Trabajadores" también o crea trabajadores primero.';
            }
        }

        if (in_array('facturas', $opciones, true)) {
            $tieneMandantes = in_array('mandantes', $opciones, true)
                || $this->count(Mandante::class) > 0;
            if (!$tieneMandantes) {
                $errores[] = 'Facturas requiere mandantes: selecciona "Mandantes" también o crea mandantes primero.';
            }
        }

        return $errores;
    }

    // ─── Seeders individuales ─────────────────────────────────────────────────

    private function seedUsuarios(SymfonyStyle $io, TenantDb $tenant): int
    {
        $io->section('Usuarios');

        $cantidad = (int) $io->ask('¿Cuántos usuarios?', '3', fn ($v) => $this->validarEnteroPositivo($v));

        $rolesDisponibles = [
            'ROLE_ADMIN'        => 'Administrador',
            'ROLE_COORDINADOR'  => 'Coordinador',
            'ROLE_ENFERMERA'    => 'Enfermera',
            'ROLE_TENS'         => 'TENS',
            'ROLE_VISUALIZADOR' => 'Visualizador',
        ];

        $password = $io->ask('Contraseña para todos los usuarios', 'pass1234');
        $slug = $tenant->getSlug();

        $creados = 0;
        for ($i = 0; $i < $cantidad; $i++) {
            $rolKey   = array_keys($rolesDisponibles)[$i % count($rolesDisponibles)];
            $rolLabel = $rolesDisponibles[$rolKey];
            $nombre   = $this->nombreAleatorio();
            $apellido = $this->apellidoAleatorio();
            $email    = sprintf('%s.%s.%d@%s.cl',
                mb_strtolower($this->quitarTildes($nombre)),
                mb_strtolower($this->quitarTildes($apellido)),
                $i + 1,
                $slug
            );

            if ($this->tenantEm->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
                $io->writeln(sprintf('  <comment>Omitiendo</comment> %s (ya existe)', $email));
                continue;
            }

            $user = new User();
            $user->setEmail($email)
                 ->setNombre($nombre)
                 ->setApellido($apellido)
                 ->setRoles([$rolKey])
                 ->setActivo(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));

            $this->tenantEm->persist($user);
            $io->writeln(sprintf('  + <info>%s %s</info> (%s) — %s', $nombre, $apellido, $rolLabel, $email));
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    private function seedMandantes(SymfonyStyle $io): int
    {
        $io->section('Mandantes');

        $cantidad = (int) $io->ask('¿Cuántos mandantes?', '2', fn ($v) => $this->validarEnteroPositivo($v));

        $pool = self::NOMBRES_MANDANTES;
        shuffle($pool);

        $creados = 0;
        for ($i = 0; $i < $cantidad; $i++) {
            $nombre = $pool[$i % count($pool)] . ($i >= count($pool) ? ' ' . ($i + 1) : '');
            $rut    = $this->generarRutEmpresa($i);

            $existente = $this->tenantEm->getRepository(Mandante::class)->findOneBy(['nombre' => $nombre])
                ?? $this->tenantEm->getRepository(Mandante::class)->findOneBy(['rut' => $rut]);
            if ($existente !== null) {
                $io->writeln(sprintf('  <comment>Omitiendo</comment> "%s" (ya existe)', $nombre));
                continue;
            }

            $m = new Mandante();
            $m->setNombre($nombre)
              ->setRut($rut)
              ->setContacto($this->nombreAleatorio() . ' ' . $this->apellidoAleatorio())
              ->setEmail('contacto@' . $this->slugify($nombre) . '.cl')
              ->setTelefono('+56 2 2' . rand(100, 999) . ' ' . rand(1000, 9999))
              ->setActivo(true);

            $this->tenantEm->persist($m);
            $io->writeln(sprintf('  + <info>%s</info>  RUT: %s', $nombre, $rut));
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    private function seedPacientes(SymfonyStyle $io): int
    {
        $io->section('Pacientes');

        $mandantes = $this->tenantEm->getRepository(Mandante::class)->findBy(['activo' => true]);
        if (empty($mandantes)) {
            $io->warning('No hay mandantes activos. Omitiendo pacientes.');
            return 0;
        }

        $cantidad = (int) $io->ask('¿Cuántos pacientes?', '3', fn ($v) => $this->validarEnteroPositivo($v));

        $tiposServicio = [
            TipoServicio::TURNO_24H->value => 'Turno 24h',
            TipoServicio::TURNO_12H->value => 'Turno 12h',
            TipoServicio::VISITA->value    => 'Visita',
        ];
        $tipoSeleccion = $io->choice(
            'Tipo de servicio predominante',
            array_values($tiposServicio),
            'Turno 12h'
        );
        $tipoElegido = TipoServicio::from(
            array_search($tipoSeleccion, $tiposServicio, true)
        );

        $creados = 0;
        for ($i = 0; $i < $cantidad; $i++) {
            $esMasculino = (bool) ($i % 2);
            $nombre      = $esMasculino ? $this->nombreMasculinoAleatorio() : $this->nombreFemeninoAleatorio();
            $apellido1   = $this->apellidoAleatorio();
            $apellido2   = $this->apellidoAleatorio();
            $rut         = $this->generarRutPersona($i + 100);
            $mandante    = $mandantes[$i % count($mandantes)];

            if ($this->tenantEm->getRepository(Paciente::class)->findOneBy(['rutHash' => $this->encryption->hmac($rut)]) !== null) {
                $io->writeln(sprintf('  <comment>Omitiendo</comment> RUT %s (ya existe)', $rut));
                continue;
            }

            $p = new Paciente();
            $p->setNombres($nombre)
              ->setApellidos(sprintf('%s %s', $apellido1, $apellido2))
              ->setRut($rut)
              ->setFechaNacimiento(new \DateTime(sprintf('%d-%02d-%02d',
                  rand(1935, 1975), rand(1, 12), rand(1, 28)
              )))
              ->setMandante($mandante)
              ->setTipoServicio($tipoElegido)
              ->setEstado(EstadoPaciente::ACTIVO)
              ->setFechaIngreso(new \DateTime(sprintf('-%d days', rand(30, 365))))
              ->setDireccion(sprintf('Calle %s %d', $this->apellidoAleatorio(), rand(100, 9999)))
              ->setComuna(self::COMUNAS_RM[array_rand(self::COMUNAS_RM)])
              ->setRegion('Metropolitana')
              ->setTelefono(sprintf('+56 9 %d %d', rand(1000, 9999), rand(1000, 9999)));

            $cond = new CondicionDomicilio();
            $cond->setAccesoDescripcion('Generado por seeder')
                 ->setRequiereAscensor((bool) ($i % 3))
                 ->setTieneMascotas((bool) ($i % 4))
                 ->setTieneBarrerasArquitectonicas(false);
            $p->setCondicionDomicilio($cond);

            $this->tenantEm->persist($p);
            $io->writeln(sprintf('  + <info>%s %s %s</info> — %s  (%s)',
                $nombre, $apellido1, $apellido2, $rut, $mandante->getNombre()
            ));
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    private function seedTrabajadores(SymfonyStyle $io): int
    {
        $io->section('Trabajadores');

        $cantidad = (int) $io->ask('¿Cuántos trabajadores?', '4', fn ($v) => $this->validarEnteroPositivo($v));

        $perfiles = [
            PerfilTrabajador::TENS->value      => 'TENS',
            PerfilTrabajador::ENFERMERA->value => 'Enfermera',
            PerfilTrabajador::CUIDADOR->value  => 'Cuidador',
            PerfilTrabajador::OTRO->value      => 'Otro',
        ];

        $perfilSeleccion = $io->choice(
            'Perfil predominante',
            array_values($perfiles),
            'TENS'
        );
        $perfilElegido = PerfilTrabajador::from(
            array_search($perfilSeleccion, $perfiles, true)
        );

        $rotacionPerfiles = [
            PerfilTrabajador::TENS,
            PerfilTrabajador::ENFERMERA,
            PerfilTrabajador::CUIDADOR,
            $perfilElegido, // el elegido aparece más seguido
        ];

        $creados = 0;
        for ($i = 0; $i < $cantidad; $i++) {
            $esMasculino = (bool) ($i % 2);
            $nombre      = $esMasculino ? $this->nombreMasculinoAleatorio() : $this->nombreFemeninoAleatorio();
            $apellido1   = $this->apellidoAleatorio();
            $apellido2   = $this->apellidoAleatorio();
            $rut         = $this->generarRutPersona($i + 200);
            $perfil      = $rotacionPerfiles[$i % count($rotacionPerfiles)];
            $email       = sprintf('%s.%s.%d@trabajadores.cl',
                mb_strtolower($this->quitarTildes($nombre)),
                mb_strtolower($this->quitarTildes($apellido1)),
                $i + 1
            );

            $existente = $this->tenantEm->getRepository(Trabajador::class)
                ->createQueryBuilder('t')
                ->where('t.email = :email OR t.rutHash = :rutHash')
                ->setParameter('email', $email)
                ->setParameter('rutHash', $this->encryption->hmac($rut))
                ->getQuery()
                ->getOneOrNullResult();

            if ($existente !== null) {
                $io->writeln(sprintf('  <comment>Omitiendo</comment> %s (ya existe)', $email));
                continue;
            }

            $t = new Trabajador();
            $t->setNombres($nombre)
              ->setApellidos(sprintf('%s %s', $apellido1, $apellido2))
              ->setRut($rut)
              ->setPerfil($perfil)
              ->setEstado(EstadoTrabajador::ACTIVO)
              ->setEmail($email)
              ->setTelefono(sprintf('+56 9 %d %d', rand(1000, 9999), rand(1000, 9999)))
              ->setFechaIngreso(new \DateTime(sprintf('-%d days', rand(60, 730))));

            $this->tenantEm->persist($t);
            $io->writeln(sprintf('  + <info>%s %s %s</info>  [%s]  %s',
                $nombre, $apellido1, $apellido2, $perfil->value, $rut
            ));
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    private function seedTurnos(SymfonyStyle $io): int
    {
        $io->section('Turnos');

        $pacientes    = $this->tenantEm->getRepository(Paciente::class)->findBy(['estado' => EstadoPaciente::ACTIVO]);
        $trabajadores = $this->tenantEm->getRepository(Trabajador::class)->findBy(['estado' => EstadoTrabajador::ACTIVO]);

        if (empty($pacientes)) {
            $io->warning('No hay pacientes activos. Omitiendo turnos.');
            return 0;
        }
        if (empty($trabajadores)) {
            $io->warning('No hay trabajadores activos. Omitiendo turnos.');
            return 0;
        }

        $io->writeln(sprintf(
            '  Pacientes activos: <info>%d</info>   Trabajadores activos: <info>%d</info>',
            count($pacientes),
            count($trabajadores)
        ));

        $diasAtras    = (int) $io->ask('¿Cuántos días hacia atrás generar turnos?', '7', fn ($v) => $this->validarEnteroNoNegativo($v));
        $diasAdelante = (int) $io->ask('¿Cuántos días hacia adelante generar turnos?', '7', fn ($v) => $this->validarEnteroNoNegativo($v));

        $tiposDisponibles = [
            TipoTurno::T12H_DIA->value   => 'Turno 12h día  (08:00–20:00)',
            TipoTurno::T12H_NOCHE->value => 'Turno 12h noche (20:00–08:00)',
            TipoTurno::T24H->value       => 'Turno 24h  (08:00–08:00)',
            TipoTurno::VISITA->value     => 'Visita  (10:00–12:00)',
        ];

        $tipoSeleccion = $io->choice(
            'Tipo de turno a crear',
            array_values($tiposDisponibles),
            'Turno 12h día  (08:00–20:00)'
        );
        $tipoElegido = TipoTurno::from(
            array_search($tipoSeleccion, $tiposDisponibles, true)
        );

        $porcentajeDescubiertos = (int) $io->ask(
            '¿Qué % de turnos dejar descubiertos (sin trabajador)?',
            '20',
            fn ($v) => $this->validarPorcentaje($v)
        );

        [$horaInicio, $horaTermino] = match ($tipoElegido) {
            TipoTurno::T12H_DIA   => ['08:00', '20:00'],
            TipoTurno::T12H_NOCHE => ['20:00', '08:00'],
            TipoTurno::T24H       => ['08:00', '08:00'],
            TipoTurno::VISITA     => ['10:00', '12:00'],
        };

        $hoy     = new \DateTime('today');
        $admin   = $this->tenantEm->getRepository(User::class)->findOneBy([]) ?? null;
        $creados = 0;

        foreach ($pacientes as $pIdx => $paciente) {
            $trabajador = $trabajadores[$pIdx % count($trabajadores)];

            for ($offset = -$diasAtras; $offset <= $diasAdelante; $offset++) {
                $fecha = (clone $hoy)->modify("{$offset} days");

                // Verificar si ya existe turno en ese día para este paciente+tipo
                $existente = $this->tenantEm->getRepository(Turno::class)
                    ->createQueryBuilder('t')
                    ->where('t.paciente = :p')
                    ->andWhere('t.fecha = :f')
                    ->andWhere('t.tipoTurno = :tipo')
                    ->setParameter('p', $paciente)
                    ->setParameter('f', $fecha)
                    ->setParameter('tipo', $tipoElegido)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existente !== null) {
                    continue;
                }

                $esDescubierto = rand(1, 100) <= $porcentajeDescubiertos;
                $turno = new Turno();
                $turno->setPaciente($paciente)
                      ->setTipoTurno($tipoElegido)
                      ->setFecha($fecha)
                      ->setHoraInicio(new \DateTime($horaInicio))
                      ->setHoraTermino(new \DateTime($horaTermino));

                if ($admin !== null) {
                    $turno->setCreadoPor($admin);
                }

                if (!$esDescubierto) {
                    $turno->setTrabajador($trabajador);

                    if ($fecha < $hoy) {
                        // Turno pasado → completado
                        $turno->setEstado(EstadoTurno::COMPLETADO)
                              ->setRegistroInicio(new \DateTime($fecha->format('Y-m-d') . ' ' . $horaInicio))
                              ->setRegistroTermino(new \DateTime($fecha->format('Y-m-d') . ' ' . $horaTermino));
                    } else {
                        $turno->setEstado(EstadoTurno::CUBIERTO);
                    }
                } else {
                    $turno->setEstado(EstadoTurno::DESCUBIERTO);
                }

                $this->tenantEm->persist($turno);
                $creados++;
            }
        }

        $this->tenantEm->flush();
        $io->writeln(sprintf('  Creados <info>%d</info> turnos (%d%% descubiertos)', $creados, $porcentajeDescubiertos));
        return $creados;
    }

    private function seedTarifas(SymfonyStyle $io): int
    {
        $io->section('Tarifas');

        $io->writeln('  Ingresa el valor por hora para cada concepto (en pesos CLP):');

        $conceptos = [
            ['enum' => TipoConcepto::TURNO_DIA,   'label' => 'Turno día (12h)',   'default' => '8000'],
            ['enum' => TipoConcepto::TURNO_NOCHE, 'label' => 'Turno noche (12h)', 'default' => '10000'],
            ['enum' => TipoConcepto::TURNO_24H,   'label' => 'Turno 24h',        'default' => '18000'],
            ['enum' => TipoConcepto::VISITA,       'label' => 'Visita (2h)',       'default' => '4500'],
            ['enum' => TipoConcepto::REEMPLAZO,    'label' => 'Reemplazo',        'default' => '9000'],
        ];

        $vigencia = new \DateTimeImmutable('first day of January this year');
        $creados  = 0;

        foreach ($conceptos as $cfg) {
            $concepto = $cfg['enum'];
            // Si ya existe tarifa activa para este concepto, omitir
            $existente = $this->tenantEm->getRepository(Tarifa::class)
                ->findOneBy(['tipoConcepto' => $concepto, 'activa' => true, 'mandante' => null]);
            if ($existente !== null) {
                $io->writeln(sprintf('  <comment>%s</comment>: ya existe tarifa activa ($%s/h)',
                    $cfg['label'], number_format((float) $existente->getValorUnitario(), 0, ',', '.')
                ));
                continue;
            }

            $valor = $io->ask(sprintf('  %s ($/h)', $cfg['label']), $cfg['default']);

            $tarifa = new Tarifa();
            $tarifa->setMandante(null)
                   ->setTipoConcepto($concepto)
                   ->setValorUnitario($valor)
                   ->setVigenciaDesde($vigencia)
                   ->setActiva(true)
                   ->setDescripcion('Seeder interactivo');

            $this->tenantEm->persist($tarifa);
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    private function seedEventosAdversos(SymfonyStyle $io): int
    {
        $io->section('Eventos adversos');

        $pacientes    = $this->tenantEm->getRepository(Paciente::class)->findBy(['estado' => EstadoPaciente::ACTIVO]);
        $trabajadores = $this->tenantEm->getRepository(Trabajador::class)->findBy(['estado' => EstadoTrabajador::ACTIVO]);

        if (empty($pacientes) || empty($trabajadores)) {
            $io->warning('Se necesitan pacientes y trabajadores activos. Omitiendo eventos adversos.');
            return 0;
        }

        $cantidad = (int) $io->ask('¿Cuántos eventos adversos?', '3', fn ($v) => $this->validarEnteroPositivo($v));

        $tipos = TipoEventoAdverso::cases();
        $gravedades = GravedadEvento::cases();

        $creados = 0;
        for ($i = 0; $i < $cantidad; $i++) {
            $paciente   = $pacientes[$i % count($pacientes)];
            $trabajador = $trabajadores[$i % count($trabajadores)];
            $tipo       = $tipos[$i % count($tipos)];
            $gravedad   = $gravedades[$i % count($gravedades)];

            $evento = new EventoAdverso();
            $evento->setPaciente($paciente)
                   ->setTrabajador($trabajador)
                   ->setFechaEvento(new \DateTime(sprintf('-%d days', rand(1, 30))))
                   ->setTipo($tipo)
                   ->setGravedad($gravedad)
                   ->setDescripcion(sprintf(
                       'Evento generado por seeder interactivo. Tipo: %s. Gravedad: %s.',
                       $tipo->value, $gravedad->value
                   ))
                   ->setNotificadoFamilia(false)
                   ->setNotificadoMedico(false);

            $this->tenantEm->persist($evento);
            $io->writeln(sprintf('  + <info>%s</info>  Paciente: %s  Gravedad: %s',
                $tipo->value, $paciente->getNombreCompleto(), $gravedad->value
            ));
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    private function seedLiquidaciones(SymfonyStyle $io): int
    {
        $io->section('Liquidaciones');

        $trabajadores = $this->tenantEm->getRepository(Trabajador::class)->findBy(['estado' => EstadoTrabajador::ACTIVO]);
        if (empty($trabajadores)) {
            $io->warning('No hay trabajadores activos. Omitiendo liquidaciones.');
            return 0;
        }

        $anio = (int) $io->ask('Año de la liquidación', (string) date('Y'), fn ($v) => $this->validarAnio($v));
        $mes  = (int) $io->ask('Mes (1-12)', (string) date('n'), fn ($v) => $this->validarMes($v));

        $estadoOpciones = [
            EstadoLiquidacion::BORRADOR->value  => 'Borrador',
            EstadoLiquidacion::APROBADA->value  => 'Aprobada',
            EstadoLiquidacion::PAGADA->value    => 'Pagada',
        ];
        $estadoSeleccion = $io->choice('Estado de las liquidaciones', array_values($estadoOpciones), 'Borrador');
        $estadoElegido   = EstadoLiquidacion::from(array_search($estadoSeleccion, $estadoOpciones, true));

        $admin   = $this->tenantEm->getRepository(User::class)->findOneBy([]);
        $tarifas = [
            TipoConcepto::TURNO_DIA->value   => 8000.0,
            TipoConcepto::TURNO_NOCHE->value => 10000.0,
            TipoConcepto::TURNO_24H->value   => 18000.0,
            TipoConcepto::VISITA->value      => 4500.0,
            TipoConcepto::REEMPLAZO->value   => 9000.0,
        ];

        // Intentar usar tarifas reales si existen
        $tarifasDb = $this->tenantEm->getRepository(Tarifa::class)->findBy(['activa' => true, 'mandante' => null]);
        foreach ($tarifasDb as $t) {
            $tarifas[$t->getTipoConcepto()->value] = (float) $t->getValorUnitario();
        }

        $creados = 0;
        foreach ($trabajadores as $trabajador) {
            // Verificar si ya existe liquidación para este trabajador en ese período
            $existente = $this->tenantEm->getRepository(LiquidacionMensual::class)
                ->findOneBy(['trabajador' => $trabajador, 'anio' => $anio, 'mes' => $mes]);
            if ($existente !== null) {
                $io->writeln(sprintf('  <comment>Omitiendo</comment> %s (ya tiene liquidación %d/%d)',
                    $trabajador->getNombreCompleto(), $mes, $anio
                ));
                continue;
            }

            $liq = new LiquidacionMensual($anio, $mes);
            $liq->setTrabajador($trabajador);
            if ($admin !== null) {
                $liq->setCreadoPor($admin);
            }
            $this->tenantEm->persist($liq);

            // Buscar turnos completados del período
            $desde  = new \DateTime("{$anio}-{$mes}-01");
            $hasta  = (clone $desde)->modify('last day of this month');

            $turnos = $this->tenantEm->getRepository(Turno::class)
                ->createQueryBuilder('t')
                ->where('t.trabajador = :w')
                ->andWhere('t.fecha BETWEEN :desde AND :hasta')
                ->andWhere('t.estado = :estado')
                ->setParameter('w', $trabajador)
                ->setParameter('desde', $desde)
                ->setParameter('hasta', $hasta)
                ->setParameter('estado', EstadoTurno::COMPLETADO)
                ->getQuery()
                ->getResult();

            $montoTotal = 0.0;
            $totalHoras = 0.0;
            $totalItems = 0;

            foreach ($turnos as $turno) {
                $concepto      = $this->tipoTurnoAConcepto($turno->getTipoTurno(), $turno->isEsReemplazo());
                $horas         = (float) $turno->getTipoTurno()->duracionHoras();
                $valorUnitario = $tarifas[$concepto->value] ?? 8000.0;
                $subtotal      = $horas * $valorUnitario;

                $item = new ItemLiquidacion();
                $item->setLiquidacion($liq)
                     ->setConcepto($concepto)
                     ->setDescripcion($turno->getFecha()?->format('d/m/Y') . ' — ' . $turno->getPaciente()?->getNombreCompleto())
                     ->setCantidad(number_format($horas, 2, '.', ''))
                     ->setValorUnitario(number_format($valorUnitario, 2, '.', ''))
                     ->setSubtotal(number_format($subtotal, 2, '.', ''))
                     ->setTurno($turno);
                $this->tenantEm->persist($item);

                $montoTotal += $subtotal;
                $totalHoras += $horas;
                $totalItems++;
            }

            // Si no hay turnos completados, crear ítems de ejemplo
            if ($totalItems === 0) {
                $io->writeln(sprintf(
                    '  <comment>%s</comment> no tiene turnos completados en %d/%d — generando ítems de ejemplo',
                    $trabajador->getNombreCompleto(), $mes, $anio
                ));

                foreach ([
                    [TipoConcepto::TURNO_DIA,   6.0, $tarifas[TipoConcepto::TURNO_DIA->value]],
                    [TipoConcepto::TURNO_NOCHE, 4.0, $tarifas[TipoConcepto::TURNO_NOCHE->value]],
                ] as [$concepto, $horas, $valor]) {
                    $subtotal = $horas * $valor;
                    $item = new ItemLiquidacion();
                    $item->setLiquidacion($liq)
                         ->setConcepto($concepto)
                         ->setDescripcion("Turnos {$concepto->etiqueta()} — {$mes}/{$anio} (ejemplo)")
                         ->setCantidad(number_format($horas, 2, '.', ''))
                         ->setValorUnitario(number_format($valor, 2, '.', ''))
                         ->setSubtotal(number_format($subtotal, 2, '.', ''));
                    $this->tenantEm->persist($item);
                    $montoTotal += $subtotal;
                    $totalHoras += $horas;
                }
            }

            $liq->setTotalTurnos(count($turnos))
                ->setTotalHoras(number_format($totalHoras, 2, '.', ''))
                ->setMontoTotal(number_format($montoTotal, 2, '.', ''))
                ->setEstado($estadoElegido);

            if ($estadoElegido === EstadoLiquidacion::PAGADA) {
                $liq->setFechaPago(new \DateTime('today'));
            }

            $io->writeln(sprintf('  + <info>%s</info>  %d/%d  $%s  [%s]',
                $trabajador->getNombreCompleto(), $mes, $anio,
                number_format($montoTotal, 0, ',', '.'),
                $estadoElegido->value
            ));
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    private function seedFacturas(SymfonyStyle $io): int
    {
        $io->section('Facturas');

        $mandantes = $this->tenantEm->getRepository(Mandante::class)->findBy(['activo' => true]);
        if (empty($mandantes)) {
            $io->warning('No hay mandantes activos. Omitiendo facturas.');
            return 0;
        }

        $anio = (int) $io->ask('Año de las facturas', (string) date('Y'), fn ($v) => $this->validarAnio($v));
        $mes  = (int) $io->ask('Mes (1-12)', (string) date('n'), fn ($v) => $this->validarMes($v));

        $estadoOpciones = [
            EstadoFactura::BORRADOR->value => 'Borrador',
            EstadoFactura::EMITIDA->value  => 'Emitida',
            EstadoFactura::PAGADA->value   => 'Pagada',
        ];
        $estadoSeleccion = $io->choice('Estado de las facturas', array_values($estadoOpciones), 'Borrador');
        $estadoElegido   = EstadoFactura::from(array_search($estadoSeleccion, $estadoOpciones, true));

        $admin   = $this->tenantEm->getRepository(User::class)->findOneBy([]);
        $creados = 0;

        foreach ($mandantes as $idx => $mandante) {
            $existente = $this->tenantEm->getRepository(Factura::class)
                ->createQueryBuilder('f')
                ->where('f.mandante = :m')
                ->andWhere('f.anio = :anio')
                ->andWhere('f.mes = :mes')
                ->setParameter('m', $mandante)
                ->setParameter('anio', $anio)
                ->setParameter('mes', $mes)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existente !== null) {
                $io->writeln(sprintf('  <comment>Omitiendo</comment> %s (ya tiene factura %d/%d)',
                    $mandante->getNombre(), $mes, $anio
                ));
                continue;
            }

            $montoNeto = (float) rand(500, 3000) * 1000;
            $numero    = sprintf('F-%d-%03d', $anio, ($idx + 1));

            $factura = new Factura($anio, $mes);
            $factura->setMandante($mandante)
                    ->setNumeroFactura($numero)
                    ->setMontoNeto(number_format($montoNeto, 2, '.', ''))
                    ->setEstado($estadoElegido);

            if ($admin !== null) {
                $factura->setCreadoPor($admin);
            }

            $factura->recalcularIva();

            if (in_array($estadoElegido, [EstadoFactura::EMITIDA, EstadoFactura::PAGADA], true)) {
                $factura->setFechaEmision(new \DateTime('today'))
                        ->setFechaVencimiento(new \DateTime('+30 days'));
            }
            if ($estadoElegido === EstadoFactura::PAGADA) {
                $factura->setFechaPago(new \DateTime('today'));
            }

            $this->tenantEm->persist($factura);
            $io->writeln(sprintf('  + <info>%s</info>  %s  $%s neto  [%s]',
                $mandante->getNombre(), $numero,
                number_format($montoNeto, 0, ',', '.'),
                $estadoElegido->value
            ));
            $creados++;
        }

        $this->tenantEm->flush();
        return $creados;
    }

    // ─── Helpers de generación de datos ──────────────────────────────────────

    private function nombreAleatorio(): string
    {
        $pool = array_merge(self::NOMBRES_MASCULINOS, self::NOMBRES_FEMENINOS);
        return $pool[array_rand($pool)];
    }

    private function nombreMasculinoAleatorio(): string
    {
        return self::NOMBRES_MASCULINOS[array_rand(self::NOMBRES_MASCULINOS)];
    }

    private function nombreFemeninoAleatorio(): string
    {
        return self::NOMBRES_FEMENINOS[array_rand(self::NOMBRES_FEMENINOS)];
    }

    private function apellidoAleatorio(): string
    {
        return self::APELLIDOS[array_rand(self::APELLIDOS)];
    }

    private function generarRutPersona(int $seed): string
    {
        $base = (8000000 + ($seed * 137931)) % 25000000 + 1000000;
        return sprintf('%d-%s', $base, $this->calcularDv($base));
    }

    private function generarRutEmpresa(int $seed): string
    {
        $base = (60000000 + ($seed * 999983)) % 20000000 + 60000000;
        $formatted = number_format($base, 0, '', '.');
        return sprintf('%s-%s', $formatted, $this->calcularDv($base));
    }

    private function calcularDv(int $rut): string
    {
        $serie  = [2, 3, 4, 5, 6, 7, 2, 3];
        $suma   = 0;
        $rutStr = (string) $rut;
        for ($i = strlen($rutStr) - 1; $i >= 0; $i--) {
            $suma += (int) $rutStr[$i] * $serie[strlen($rutStr) - 1 - $i];
        }
        $dv = 11 - ($suma % 11);
        return match ($dv) {
            11 => '0',
            10 => 'K',
            default => (string) $dv,
        };
    }

    private function slugify(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = $this->quitarTildes($texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
        return trim($texto, '-');
    }

    private function quitarTildes(string $texto): string
    {
        $mapa = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N'];
        return strtr($texto, $mapa);
    }

    private function tipoTurnoAConcepto(TipoTurno $tipo, bool $esReemplazo): TipoConcepto
    {
        if ($esReemplazo) {
            return TipoConcepto::REEMPLAZO;
        }

        return match ($tipo) {
            TipoTurno::T12H_DIA   => TipoConcepto::TURNO_DIA,
            TipoTurno::T12H_NOCHE => TipoConcepto::TURNO_NOCHE,
            TipoTurno::T24H       => TipoConcepto::TURNO_24H,
            TipoTurno::VISITA     => TipoConcepto::VISITA,
        };
    }

    // ─── Validadores para las preguntas interactivas ──────────────────────────

    private function validarEnteroPositivo(mixed $valor): int
    {
        $v = (int) $valor;
        if ($v < 1) {
            throw new \InvalidArgumentException('Debe ser un número entero mayor a 0.');
        }
        return $v;
    }

    private function validarEnteroNoNegativo(mixed $valor): int
    {
        $v = (int) $valor;
        if ($v < 0) {
            throw new \InvalidArgumentException('Debe ser un número entero mayor o igual a 0.');
        }
        return $v;
    }

    private function validarPorcentaje(mixed $valor): int
    {
        $v = (int) $valor;
        if ($v < 0 || $v > 100) {
            throw new \InvalidArgumentException('Debe ser un número entre 0 y 100.');
        }
        return $v;
    }

    private function validarAnio(mixed $valor): int
    {
        $v = (int) $valor;
        if ($v < 2020 || $v > 2099) {
            throw new \InvalidArgumentException('Año inválido. Usa un año entre 2020 y 2099.');
        }
        return $v;
    }

    private function validarMes(mixed $valor): int
    {
        $v = (int) $valor;
        if ($v < 1 || $v > 12) {
            throw new \InvalidArgumentException('Mes inválido. Usa un número entre 1 y 12.');
        }
        return $v;
    }
}
