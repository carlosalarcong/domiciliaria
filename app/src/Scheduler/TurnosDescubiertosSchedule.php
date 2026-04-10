<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\RevisarTurnosDescubiertosMessage;
use App\Repository\TurnoRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Job diario que revisa los turnos del día siguiente sin cobertura
 * y despacha alertas via Symfony Messenger.
 */
#[AsSchedule('turnos')]
final class TurnosDescubiertosSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly TurnoRepository $turnoRepository,
    ) {}

    public function getSchedule(): Schedule
    {
        // Ejecutar todos los días a las 20:00
        return (new Schedule())->add(
            RecurringMessage::cron('0 20 * * *', new RevisarTurnosDescubiertosMessage()),
        );
    }
}
