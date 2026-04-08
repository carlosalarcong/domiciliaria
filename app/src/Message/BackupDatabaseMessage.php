<?php

declare(strict_types=1);

namespace App\Message;

final class BackupDatabaseMessage
{
    public function __construct(
        public readonly int    $tenantId,
        public readonly string $dbName,
        public readonly string $slug,
    ) {}
}
