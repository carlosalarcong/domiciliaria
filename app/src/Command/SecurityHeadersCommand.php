<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:security:headers',
    description: 'Verifica los headers de seguridad HTTP de la aplicación',
)]
class SecurityHeadersCommand extends Command
{
    private const EXPECTED_HEADERS = [
        'x-frame-options'           => 'X-Frame-Options',
        'x-content-type-options'    => 'X-Content-Type-Options',
        'strict-transport-security' => 'Strict-Transport-Security',
        'referrer-policy'           => 'Referrer-Policy',
        'content-security-policy'   => 'Content-Security-Policy',
        'permissions-policy'        => 'Permissions-Policy',
    ];

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'url',
            InputArgument::OPTIONAL,
            'URL a verificar',
            'http://demo.localhost:8090/'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');

        $io->title('HEADERS DE SEGURIDAD — RESULTADO');
        $io->text("URL: {$url}");
        $io->newLine();

        try {
            $response = $this->httpClient->request('GET', $url, [
                'max_redirects' => 3,
                'timeout'       => 10,
                'verify_peer'   => false,
                'verify_host'   => false,
            ]);
            $headers = $response->getHeaders(false);
        } catch (\Throwable $e) {
            $io->error("No se pudo conectar a {$url}: " . $e->getMessage());
            return Command::FAILURE;
        }

        $allOk = true;
        $rows  = [];

        foreach (self::EXPECTED_HEADERS as $key => $label) {
            $value = $headers[$key][0] ?? null;
            if ($value !== null) {
                $rows[] = ['✅', $label, $this->truncate($value, 80)];
            } else {
                $rows[] = ['❌', $label, '— NO PRESENTE'];
                $allOk = false;
            }
        }

        $io->table(['', 'Header', 'Valor'], $rows);

        if ($allOk) {
            $io->success('Todos los headers de seguridad están presentes.');
            $io->text('Nota estimada en securityheaders.com: A');
        } else {
            $io->warning('Algunos headers de seguridad no están presentes.');
        }

        $io->newLine();
        $io->text([
            'Archivos de configuración:',
            '  · docker/nginx/default.conf',
            '  · config/packages/nelmio_security.yaml',
        ]);

        return $allOk ? Command::SUCCESS : Command::FAILURE;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) . '…' : $value;
    }
}
