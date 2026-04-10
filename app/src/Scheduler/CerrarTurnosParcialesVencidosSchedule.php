<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\CerrarTurnosParcialesVencidosMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('turnos_parciales_vencidos')]
final class CerrarTurnosParcialesVencidosSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::cron('0 * * * *', new CerrarTurnosParcialesVencidosMessage()),
        );
    }
}
