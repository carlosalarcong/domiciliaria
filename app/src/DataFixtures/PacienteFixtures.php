<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Hakam\MultiTenancyBundle\Attribute\TenantFixture;
use App\Entity\Tenant\BitacoraOperativa;
use App\Entity\Tenant\CondicionDomicilio;
use App\Entity\Tenant\HistorialComunicacion;
use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\User;
use App\Enum\EstadoPaciente;
use App\Enum\TipoBitacora;
use App\Enum\TipoComunicacion;
use App\Enum\TipoServicio;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

#[TenantFixture]
class PacienteFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int { return 2; }

    public function load(ObjectManager $manager): void
    {
        $db = $manager->getConnection()->getDatabase();
        $esNorte = str_contains($db, 'norte');

        /** @var User $admin */
        $admin = $manager->getRepository(User::class)->findAll()[0];

        // ── Mandantes ────────────────────────────────────────────────────────
        $mandanteData = $esNorte ? [
            ['nombre' => 'ISAPRE Cruz Blanca Norte', 'rut' => '76.354.771-K', 'contacto' => 'Patricia Núñez',  'email' => 'pnunez@cruzblanca.cl',    'telefono' => '+56 55 234 5678'],
            ['nombre' => 'Hospital Regional Antofagasta', 'rut' => '61.201.000-3', 'contacto' => 'Jorge Soto', 'email' => 'hra@minsal.cl',            'telefono' => '+56 55 265 0000'],
            ['nombre' => 'Particular Familia Olivares', 'rut' => '14.223.456-9',   'contacto' => 'Manuel Olivares', 'email' => 'molivares@gmail.com', 'telefono' => '+56 9 7654 3210'],
        ] : [
            ['nombre' => 'FONASA Región Metropolitana', 'rut' => '61.602.000-8', 'contacto' => 'María Pérez',  'email' => 'contacto@fonasa.cl',       'telefono' => '+56 2 2568 0000'],
            ['nombre' => 'Mutual de Seguridad',         'rut' => '70.286.500-0', 'contacto' => 'Juan García',  'email' => 'mutual@mutual.cl',         'telefono' => '+56 2 2685 3000'],
            ['nombre' => 'Particular Rodrigo Soto',     'rut' => '15.432.198-7', 'contacto' => 'Rodrigo Soto', 'email' => 'rsoto@gmail.com',          'telefono' => '+56 9 8765 4321'],
        ];

        $mandantes = [];
        foreach ($mandanteData as $data) {
            $m = new Mandante();
            $m->setNombre($data['nombre'])->setRut($data['rut'])
              ->setContacto($data['contacto'])->setEmail($data['email'])
              ->setTelefono($data['telefono'])->setActivo(true);
            $manager->persist($m);
            $mandantes[] = $m;
        }

        // ── Pacientes ────────────────────────────────────────────────────────
        $pacientesData = $esNorte ? [
            [
                'nombres' => 'Élida del Carmen', 'apellidos' => 'Tapia Rojas', 'rut' => '7.112.345-2',
                'fechaNac' => '1942-08-19', 'direccion' => 'Pasaje Los Pinos 321', 'comuna' => 'Antofagasta', 'region' => 'Antofagasta',
                'telefono' => '+56 9 6111 2233', 'mandante' => 0, 'tipo' => TipoServicio::TURNO_24H,
                'estado' => EstadoPaciente::ACTIVO, 'ingreso' => '2024-02-10',
                'tutor' => ['nombre' => 'Hugo Tapia', 'tel' => '+56 9 4455 6677', 'rel' => 'Hijo'],
                'obs' => 'Requiere oxígeno suplementario nocturno. Cama articulada instalada.',
                'condicion' => ['acceso' => 'Casa sin escaleras, puerta amarilla', 'ascensor' => false, 'mascotas' => true, 'mascotas_detalle' => 'Un gato adulto', 'barreras' => false, 'seguridad' => null],
            ],
            [
                'nombres' => 'Norberto Andrés', 'apellidos' => 'Quiroga Espinoza', 'rut' => '11.987.654-1',
                'fechaNac' => '1958-04-03', 'direccion' => 'Av. Argentina 876, Depto 3A', 'comuna' => 'Antofagasta', 'region' => 'Antofagasta',
                'telefono' => '+56 9 8822 9911', 'mandante' => 1, 'tipo' => TipoServicio::TURNO_12H,
                'estado' => EstadoPaciente::ACTIVO, 'ingreso' => '2024-05-20',
                'tutor' => ['nombre' => 'Cecilia Quiroga', 'tel' => '+56 9 1122 3344', 'rel' => 'Hija'],
                'obs' => 'Postoperatorio de cadera derecha. Fisioterapia tres veces por semana.',
                'condicion' => ['acceso' => 'Edificio 3er piso con ascensor', 'ascensor' => true, 'mascotas' => false, 'barreras' => false, 'seguridad' => 'Código ascensor: 4821'],
            ],
            [
                'nombres' => 'Iris Beatriz', 'apellidos' => 'Leiva Pizarro', 'rut' => '5.678.901-4',
                'fechaNac' => '1935-12-25', 'direccion' => 'Calle Balmaceda 101', 'comuna' => 'Calama', 'region' => 'Antofagasta',
                'telefono' => '+56 9 5500 6611', 'mandante' => 2, 'tipo' => TipoServicio::VISITA,
                'estado' => EstadoPaciente::SUSPENDIDO, 'ingreso' => '2023-11-05',
                'tutor' => null, 'obs' => 'Suspendida temporalmente por viaje familiar. Reingreso previsto en 30 días.',
                'condicion' => ['acceso' => 'Casa esquina, portón negro', 'ascensor' => false, 'mascotas' => false, 'barreras' => true, 'seguridad' => 'Escalón de 20cm en entrada. Sin rampa.'],
            ],
        ] : [
            [
                'nombres' => 'Carmen Rosa', 'apellidos' => 'Vásquez Muñoz', 'rut' => '8.234.567-K',
                'fechaNac' => '1945-03-12', 'direccion' => 'Av. Providencia 1234, Depto 5B', 'comuna' => 'Providencia', 'region' => 'Metropolitana',
                'telefono' => '+56 9 1111 2222', 'mandante' => 0, 'tipo' => TipoServicio::TURNO_24H,
                'estado' => EstadoPaciente::ACTIVO, 'ingreso' => '2024-01-15',
                'tutor' => ['nombre' => 'Pedro Vásquez', 'tel' => '+56 9 3333 4444', 'rel' => 'Hijo'],
                'obs' => 'Paciente con movilidad reducida. Requiere cama clínica.',
                'condicion' => ['acceso' => 'Edificio con portero, piso 5', 'ascensor' => true, 'mascotas' => false, 'barreras' => false, 'seguridad' => 'Portero eléctrico, timbre 5B'],
            ],
            [
                'nombres' => 'Luis Alberto', 'apellidos' => 'Contreras Silva', 'rut' => '12.456.789-3',
                'fechaNac' => '1952-07-28', 'direccion' => 'Los Aromos 456', 'comuna' => 'Las Condes', 'region' => 'Metropolitana',
                'telefono' => '+56 9 5555 6666', 'mandante' => 1, 'tipo' => TipoServicio::TURNO_12H,
                'estado' => EstadoPaciente::ACTIVO, 'ingreso' => '2024-03-01',
                'tutor' => ['nombre' => 'Ana Contreras', 'tel' => '+56 9 7777 8888', 'rel' => 'Hija'],
                'obs' => null,
                'condicion' => ['acceso' => 'Casa con jardín, puerta verde', 'ascensor' => false, 'mascotas' => true, 'mascotas_detalle' => 'Perro labrador', 'barreras' => false, 'seguridad' => null],
            ],
            [
                'nombres' => 'Rosa Elena', 'apellidos' => 'Martínez Fuentes', 'rut' => '6.789.012-1',
                'fechaNac' => '1938-11-05', 'direccion' => 'Calle Larga 789', 'comuna' => 'Santiago', 'region' => 'Metropolitana',
                'telefono' => '+56 9 9999 0000', 'mandante' => 2, 'tipo' => TipoServicio::VISITA,
                'estado' => EstadoPaciente::SUSPENDIDO, 'ingreso' => '2023-09-10',
                'tutor' => null, 'obs' => 'Suspendido por hospitalización. Retoma en evaluación médica.',
                'condicion' => ['acceso' => 'Primer piso, sin escaleras', 'ascensor' => false, 'mascotas' => false, 'barreras' => true, 'seguridad' => 'Escalón de 15cm en entrada principal'],
            ],
        ];

        foreach ($pacientesData as $data) {
            $p = new Paciente();
            $p->setNombres($data['nombres'])->setApellidos($data['apellidos'])->setRut($data['rut'])
              ->setFechaNacimiento(new \DateTime($data['fechaNac']))
              ->setDireccion($data['direccion'])->setComuna($data['comuna'])->setRegion($data['region'])
              ->setTelefono($data['telefono'])
              ->setMandante($mandantes[$data['mandante']])
              ->setTipoServicio($data['tipo'])->setEstado($data['estado'])
              ->setFechaIngreso(new \DateTime($data['ingreso']));

            if ($data['obs']) $p->setObservaciones($data['obs']);
            if ($data['tutor']) {
                $p->setTutorNombre($data['tutor']['nombre'])
                  ->setTutorTelefono($data['tutor']['tel'])
                  ->setTutorRelacion($data['tutor']['rel']);
            }

            $c = new CondicionDomicilio();
            $c->setAccesoDescripcion($data['condicion']['acceso'])
              ->setRequiereAscensor($data['condicion']['ascensor'])
              ->setTieneMascotas($data['condicion']['mascotas'])
              ->setTieneBarrerasArquitectonicas($data['condicion']['barreras']);
            if (!empty($data['condicion']['mascotas_detalle'])) $c->setMascotasDetalle($data['condicion']['mascotas_detalle']);
            if (!empty($data['condicion']['seguridad'])) $c->setObservacionesSeguridad($data['condicion']['seguridad']);
            $p->setCondicionDomicilio($c);

            $manager->persist($p);

            $b = new BitacoraOperativa();
            $b->setPaciente($p)->setTipo(TipoBitacora::NOVEDAD)
              ->setDescripcion('Paciente ingresado al sistema. Evaluación inicial realizada.')
              ->setCreadoPor($admin);
            $manager->persist($b);

            $com = new HistorialComunicacion();
            $com->setPaciente($p)->setTipo(TipoComunicacion::FAMILIA)
                ->setDescripcion('Llamada de bienvenida a la familia. Se explicó el plan de atención.')
                ->setContacto($data['tutor']['nombre'] ?? 'Familia')
                ->setCreadoPor($admin);
            $manager->persist($com);
        }

        $manager->flush();
    }
}
