<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BitacoraOperativa;
use App\Entity\CondicionDomicilio;
use App\Entity\HistorialComunicacion;
use App\Entity\Mandante;
use App\Entity\Paciente;
use App\Entity\User;
use App\Enum\EstadoPaciente;
use App\Enum\TipoBitacora;
use App\Enum\TipoComunicacion;
use App\Enum\TipoServicio;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PacienteFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User $admin */
        $admin = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@domiciliaria.cl']);

        // Mandantes
        $mandanteData = [
            ['nombre' => 'FONASA Región Metropolitana', 'rut' => '61.602.000-8', 'contacto' => 'María Pérez', 'email' => 'contacto@fonasa.cl', 'telefono' => '+56 2 2568 0000'],
            ['nombre' => 'Mutual de Seguridad', 'rut' => '70.286.500-0', 'contacto' => 'Juan García', 'email' => 'mutual@mutual.cl', 'telefono' => '+56 2 2685 3000'],
            ['nombre' => 'Particular Rodrigo Soto', 'rut' => '15.432.198-7', 'contacto' => 'Rodrigo Soto', 'email' => 'rsoto@gmail.com', 'telefono' => '+56 9 8765 4321'],
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

        // Pacientes
        $pacientesData = [
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
                'condicion' => ['acceso' => 'Casa con jardín, puerta verde', 'ascensor' => false, 'mascotas' => true, 'barreras' => false, 'seguridad' => null],
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

            // Condición domicilio
            $c = new CondicionDomicilio();
            $c->setAccesoDescripcion($data['condicion']['acceso'])
              ->setRequiereAscensor($data['condicion']['ascensor'])
              ->setTieneMascotas($data['condicion']['mascotas'])
              ->setTieneBarrerasArquitectonicas($data['condicion']['barreras']);
            if ($data['condicion']['seguridad']) $c->setObservacionesSeguridad($data['condicion']['seguridad']);
            $p->setCondicionDomicilio($c);

            $manager->persist($p);

            // Bitácora
            $b = new BitacoraOperativa();
            $b->setPaciente($p)->setTipo(TipoBitacora::NOVEDAD)
              ->setDescripcion('Paciente ingresado al sistema. Evaluación inicial realizada.')
              ->setCreadoPor($admin);
            $manager->persist($b);

            // Comunicación
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
