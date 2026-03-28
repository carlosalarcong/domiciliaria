<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Entity\Tenant\User;
use App\Enum\EstadoTrabajador;
use App\Enum\EstadoTurno;
use App\Enum\PerfilTrabajador;
use App\Enum\TipoTurno;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;

#[TenantFixture]
class TurnoFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int { return 3; }

    public function load(ObjectManager $manager): void
    {
        $db = $manager->getConnection()->getDatabase();
        $esNorte = str_contains($db, 'norte');

        /** @var User $admin */
        $admin = $manager->getRepository(User::class)->findAll()[0];

        $pacientes = $manager->getRepository(Paciente::class)->findAll();
        if (empty($pacientes)) {
            return;
        }

        $trabajadoresData = $esNorte ? [
            ['nombres' => 'Pamela Andrea',  'apellidos' => 'Araya Castro',    'rut' => '16.112.233-4', 'perfil' => PerfilTrabajador::ENFERMERA, 'telefono' => '+56 9 5511 2233', 'email' => 'pamela.araya@clinica-norte.cl'],
            ['nombres' => 'Ignacio Felipe', 'apellidos' => 'Vargas Medina',   'rut' => '18.334.455-6', 'perfil' => PerfilTrabajador::TENS,      'telefono' => '+56 9 6622 3344', 'email' => 'ignacio.vargas@clinica-norte.cl'],
            ['nombres' => 'Lorena Patricia','apellidos' => 'Bustamante Ríos', 'rut' => '12.556.677-8', 'perfil' => PerfilTrabajador::CUIDADOR,  'telefono' => '+56 9 7733 4455', 'email' => 'lorena.bustamante@clinica-norte.cl'],
            ['nombres' => 'Fernando José', 'apellidos' => 'Mardones Aliaga',  'rut' => '14.778.899-0', 'perfil' => PerfilTrabajador::TENS,      'telefono' => '+56 9 8844 5566', 'email' => 'fernando.mardones@clinica-norte.cl'],
        ] : [
            ['nombres' => 'Ana María',      'apellidos' => 'González Rojas',   'rut' => '14.567.890-K', 'perfil' => PerfilTrabajador::ENFERMERA, 'telefono' => '+56 9 1234 5678', 'email' => 'ana.gonzalez@clinica-demo.cl'],
            ['nombres' => 'Carlos Eduardo', 'apellidos' => 'Muñoz Pérez',      'rut' => '17.234.567-3', 'perfil' => PerfilTrabajador::TENS,      'telefono' => '+56 9 2345 6789', 'email' => 'carlos.munoz@clinica-demo.cl'],
            ['nombres' => 'Marcela Beatriz','apellidos' => 'Fuentes Díaz',     'rut' => '11.876.543-2', 'perfil' => PerfilTrabajador::CUIDADOR,  'telefono' => '+56 9 3456 7890', 'email' => 'marcela.fuentes@clinica-demo.cl'],
            ['nombres' => 'Roberto Andrés', 'apellidos' => 'Sepúlveda Lagos',  'rut' => '15.432.100-5', 'perfil' => PerfilTrabajador::TENS,      'telefono' => '+56 9 4567 8901', 'email' => 'roberto.sepulveda@clinica-demo.cl'],
        ];

        $trabajadores = [];
        foreach ($trabajadoresData as $data) {
            $t = new Trabajador();
            $t->setNombres($data['nombres'])->setApellidos($data['apellidos'])
              ->setRut($data['rut'])->setPerfil($data['perfil'])
              ->setTelefono($data['telefono'])->setEmail($data['email'])
              ->setEstado(EstadoTrabajador::ACTIVO)
              ->setFechaIngreso(new \DateTime('2023-01-01'));
            $manager->persist($t);
            $trabajadores[] = $t;
        }

        $manager->flush();

        $hoy   = new \DateTime('today');
        $lunes = (clone $hoy)->modify('monday this week');

        $turnosConfig = [
            ['paciente' => 0, 'trabajador' => 0, 'tipo' => TipoTurno::T24H,       'horaInicio' => '08:00', 'horaTermino' => '08:00',  'offsets' => [0, 2, 4, 7,  9, 11]],
            ['paciente' => 1, 'trabajador' => 1, 'tipo' => TipoTurno::T12H_DIA,   'horaInicio' => '08:00', 'horaTermino' => '20:00',  'offsets' => [0, 2, 4, 6,  8, 10, 12]],
            ['paciente' => 1, 'trabajador' => 2, 'tipo' => TipoTurno::T12H_NOCHE, 'horaInicio' => '20:00', 'horaTermino' => '08:00',  'offsets' => [1, 3, 5, 7,  9, 11]],
            ['paciente' => 2, 'trabajador' => null, 'tipo' => TipoTurno::VISITA,  'horaInicio' => '10:00', 'horaTermino' => '12:00',  'offsets' => [1, 3, 5, 8]],
        ];

        foreach ($turnosConfig as $cfg) {
            $paciente   = $pacientes[$cfg['paciente']] ?? $pacientes[0];
            $trabajador = ($cfg['trabajador'] !== null) ? ($trabajadores[$cfg['trabajador']] ?? null) : null;

            foreach ($cfg['offsets'] as $offset) {
                $fecha = (clone $lunes)->modify("+{$offset} days");

                $turno = new Turno();
                $turno->setPaciente($paciente)
                      ->setTrabajador($trabajador)
                      ->setFecha($fecha)
                      ->setHoraInicio(new \DateTime($cfg['horaInicio']))
                      ->setHoraTermino(new \DateTime($cfg['horaTermino']))
                      ->setTipoTurno($cfg['tipo'])
                      ->setCreadoPor($admin);

                if ($trabajador !== null) {
                    if ($fecha < $hoy) {
                        $turno->setEstado(EstadoTurno::COMPLETADO)
                              ->setRegistroInicio(new \DateTime($fecha->format('Y-m-d') . ' ' . $cfg['horaInicio']))
                              ->setRegistroTermino(new \DateTime($fecha->format('Y-m-d') . ' ' . $cfg['horaTermino']));
                    } else {
                        $turno->setEstado(EstadoTurno::CUBIERTO);
                    }
                } else {
                    $turno->setEstado(EstadoTurno::DESCUBIERTO);
                }

                $manager->persist($turno);
            }
        }

        $manager->flush();
    }
}
