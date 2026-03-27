<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Paciente;
use App\Entity\Trabajador;
use App\Entity\Turno;
use App\Entity\User;
use App\Enum\EstadoTrabajador;
use App\Enum\EstadoTurno;
use App\Enum\PerfilTrabajador;
use App\Enum\TipoTurno;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TurnoFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [PacienteFixtures::class, UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User $admin */
        $admin = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@domiciliaria.cl']);

        $pacientes = $manager->getRepository(Paciente::class)->findAll();
        if (empty($pacientes)) {
            return;
        }

        // Crear trabajadores
        $trabajadoresData = [
            ['nombres' => 'Ana María', 'apellidos' => 'González Rojas', 'rut' => '14.567.890-K', 'perfil' => PerfilTrabajador::ENFERMERA, 'telefono' => '+56 9 1234 5678', 'email' => 'ana.gonzalez@domiciliaria.cl'],
            ['nombres' => 'Carlos Eduardo', 'apellidos' => 'Muñoz Pérez', 'rut' => '17.234.567-3', 'perfil' => PerfilTrabajador::TENS, 'telefono' => '+56 9 2345 6789', 'email' => 'carlos.munoz@domiciliaria.cl'],
            ['nombres' => 'Marcela Beatriz', 'apellidos' => 'Fuentes Díaz', 'rut' => '11.876.543-2', 'perfil' => PerfilTrabajador::CUIDADOR, 'telefono' => '+56 9 3456 7890', 'email' => 'marcela.fuentes@domiciliaria.cl'],
            ['nombres' => 'Roberto Andrés', 'apellidos' => 'Sepúlveda Lagos', 'rut' => '15.432.100-5', 'perfil' => PerfilTrabajador::TENS, 'telefono' => '+56 9 4567 8901', 'email' => 'roberto.sepulveda@domiciliaria.cl'],
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

        // Crear turnos para la semana actual y próximas dos semanas
        $hoy = new \DateTime('today');
        $lunes = clone $hoy;
        $lunes->modify('monday this week');

        $turnosConfig = [
            // Paciente 0: turnos 24h
            [
                'paciente'    => 0,
                'trabajador'  => 0,
                'tipo'        => TipoTurno::T24H,
                'horaInicio'  => '08:00',
                'horaTermino' => '08:00+1',
                'offsets'     => [0, 2, 4, 7, 9, 11],
            ],
            // Paciente 1: turnos 12h día + noche
            [
                'paciente'    => 1,
                'trabajador'  => 1,
                'tipo'        => TipoTurno::T12H_DIA,
                'horaInicio'  => '08:00',
                'horaTermino' => '20:00',
                'offsets'     => [0, 2, 4, 6, 8, 10, 12],
            ],
            [
                'paciente'    => 1,
                'trabajador'  => 2,
                'tipo'        => TipoTurno::T12H_NOCHE,
                'horaInicio'  => '20:00',
                'horaTermino' => '08:00',
                'offsets'     => [1, 3, 5, 7, 9, 11],
            ],
            // Paciente 2 (suspendido): algunos turnos descubiertos
            [
                'paciente'    => 2,
                'trabajador'  => null,
                'tipo'        => TipoTurno::VISITA,
                'horaInicio'  => '10:00',
                'horaTermino' => '12:00',
                'offsets'     => [1, 3, 5, 8],
            ],
        ];

        foreach ($turnosConfig as $cfg) {
            $paciente   = $pacientes[$cfg['paciente']] ?? $pacientes[0];
            $trabajador = isset($cfg['trabajador']) && $cfg['trabajador'] !== null
                ? ($trabajadores[$cfg['trabajador']] ?? null)
                : null;

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
                    // Algunos turno pasados ya están completados
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
