<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\MarcarFacturasVencidasMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('facturas_vencidas')]
final class MarcarFacturasVencidasSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::cron('0 6 * * *', new MarcarFacturasVencidasMessage()),
        );
    }
}
