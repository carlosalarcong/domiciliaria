<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Entity\Tenant\SincronizacionExterna;
use App\Message\EjecutarSincronizacionMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('sincronizacion')]
final class SincronizacionSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        $schedule->stateful($this->cache);

        $em = $this->registry->getManager('tenant');
        $sincronizaciones = $em->getRepository(SincronizacionExterna::class)->findBy(['activa' => true]);

        foreach ($sincronizaciones as $sincronizacion) {
            if (!$sincronizacion instanceof SincronizacionExterna || $sincronizacion->getId() === null) {
                continue;
            }

            $schedule->add(
                RecurringMessage::cron(
                    $sincronizacion->getExpresionCron(),
                    new EjecutarSincronizacionMessage($sincronizacion->getId()),
                ),
            );
        }

        return $schedule;
    }
}
