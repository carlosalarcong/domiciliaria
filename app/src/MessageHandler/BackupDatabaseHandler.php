<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\BackupDatabaseMessage;
use App\Service\EncryptionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Ejecuta pg_dump, cifra el resultado y sube a S3.
 * Elimina el dump local inmediatamente tras el cifrado.
 */
#[AsMessageHandler]
class BackupDatabaseHandler
{
    public function __construct(
        private readonly EncryptionService $encryption,
        private readonly LoggerInterface   $logger,
        private readonly string            $dbHost,
        private readonly string            $dbPort,
        private readonly string            $dbUser,
        private readonly string            $dbPassword,
        private readonly string            $s3Bucket,
        private readonly string            $s3Region,
    ) {}

    public function __invoke(BackupDatabaseMessage $message): void
    {
        $startTime = microtime(true);
        $date      = (new \DateTimeImmutable())->format('Y-m-d_H-i-s');
        $tmpFile   = sys_get_temp_dir() . "/backup_{$message->slug}_{$date}.sql";
        $encFile   = $tmpFile . '.enc';

        try {
            // 1. pg_dump
            $cmd = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -Fp --no-password %s 2>&1',
                escapeshellarg($this->dbPassword),
                escapeshellarg($this->dbHost),
                escapeshellarg($this->dbPort),
                escapeshellarg($this->dbUser),
                escapeshellarg($message->dbName),
            );

            $output     = '';
            $returnCode = 0;
            exec($cmd, $outputLines, $returnCode);
            $output = implode("\n", $outputLines);

            if ($returnCode !== 0) {
                throw new \RuntimeException("pg_dump falló (código {$returnCode}): {$output}");
            }

            file_put_contents($tmpFile, $output);
            $originalSize = filesize($tmpFile);

            // 2. Cifrar
            $plainContent = file_get_contents($tmpFile);
            $encrypted    = $this->encryption->encrypt($plainContent);
            file_put_contents($encFile, $encrypted);

            // 3. Eliminar dump en texto plano INMEDIATAMENTE
            unlink($tmpFile);

            // 4. Subir a S3
            $s3Key = "backups/{$message->slug}/{$date}.sql.enc";
            $this->uploadToS3($encFile, $s3Key);

            // 5. Eliminar archivo cifrado local
            unlink($encFile);

            $duration = round(microtime(true) - $startTime, 2);
            $hash     = hash('sha256', $encrypted);

            $this->logger->info('Backup completado', [
                'tenant'    => $message->slug,
                'db'        => $message->dbName,
                's3_key'    => $s3Key,
                'size_kb'   => round($originalSize / 1024, 2),
                'sha256'    => $hash,
                'duration'  => $duration . 's',
            ]);
        } catch (\Throwable $e) {
            // Limpiar archivos temporales si quedan
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            if (file_exists($encFile)) {
                unlink($encFile);
            }

            $this->logger->error('Backup fallido', [
                'tenant' => $message->slug,
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function uploadToS3(string $filePath, string $s3Key): void
    {
        if (empty($this->s3Bucket)) {
            $this->logger->warning('S3 no configurado, backup guardado solo localmente.', ['path' => $filePath]);
            return;
        }

        $s3Client = new \Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $this->s3Region,
        ]);

        $s3Client->putObject([
            'Bucket'               => $this->s3Bucket,
            'Key'                  => $s3Key,
            'Body'                 => fopen($filePath, 'r'),
            'ServerSideEncryption' => 'AES256',
        ]);
    }
}
