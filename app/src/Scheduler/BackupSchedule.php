<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Entity\Main\TenantDb;
use App\Message\BackupDatabaseMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Programa backups cifrados diarios a las 3:00 AM para cada tenant.
 */
#[AsSchedule('backup')]
class BackupSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $defaultEm,
        private readonly CacheInterface         $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        $schedule->stateful($this->cache);

        $tenants = $this->defaultEm->getRepository(TenantDb::class)->findAll();

        foreach ($tenants as $tenant) {
            $schedule->add(
                RecurringMessage::cron(
                    '0 3 * * *',
                    new BackupDatabaseMessage(
                        $tenant->getId(),
                        $tenant->getDatabaseName(),
                        $tenant->getSubdomain() ?? (string) $tenant->getId(),
                    )
                )
            );
        }

        return $schedule;
    }
}
