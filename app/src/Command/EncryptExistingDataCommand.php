<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Main\TenantDb;
use App\Entity\Tenant\EventoAdverso;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Cifra la data existente en texto plano para todos los tenants.
 * Seguro de ejecutar múltiples veces: omite valores ya cifrados.
 *
 * Uso:
 *   php bin/console app:security:encrypt-existing-data
 *   php bin/console app:security:encrypt-existing-data --dry-run
 */
#[AsCommand(
    name: 'app:security:encrypt-existing-data',
    description: 'Cifra los datos sensibles existentes que aún están en texto plano.',
)]
class EncryptExistingDataCommand extends Command
{
    public function __construct(
        private readonly EncryptionService         $encryption,
        private readonly EntityManagerInterface    $defaultEm,
        private readonly EntityManagerInterface    $tenantEm,
        private readonly EventDispatcherInterface  $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo mostrar qué se cifraría, sin guardar cambios');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Cifrado de datos existentes en texto plano');
        $dryRun && $io->warning('MODO DRY-RUN — no se guardarán cambios.');

        /** @var TenantDb[] $tenants */
        $tenants = $this->defaultEm->getRepository(TenantDb::class)->findAll();

        if (empty($tenants)) {
            $io->warning('No hay tenants registrados.');
            return Command::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $io->section(sprintf('Tenant: %s (DB: %s)', $tenant->getName() ?? $tenant->getId(), $tenant->getDatabaseName()));

            // Cambiar conexión al tenant
            $this->eventDispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
            $this->tenantEm->clear();

            $cifrados = 0;
            $omitidos = 0;

            // ── Pacientes ─────────────────────────────────────────────────────
            $pacientes = $this->tenantEm->getRepository(Paciente::class)->findAll();
            foreach ($pacientes as $p) {
                $changed = false;

                if ($p->getRut() !== null && !$this->encryption->isEncrypted($p->getRut())) {
                    $io->writeln(sprintf('  Paciente <info>%s</info>: cifrando RUT %s', $p->getNombreCompleto(), $p->getRut()));
                    if (!$dryRun) {
                        $plain = $p->getRut();
                        $p->setRutHash($this->encryption->hmac($plain));
                        $p->setRut($this->encryption->encrypt($plain));
                    }
                    $changed = true;
                } elseif ($p->getRut() !== null && $p->getRutHash() === null) {
                    if (!$dryRun) {
                        $p->setRutHash($this->encryption->hmac($this->encryption->decrypt($p->getRut())));
                    }
                    $changed = true;
                }

                foreach ([
                    'Observaciones' => [$p, 'getObservaciones', 'setObservaciones'],
                    'Diagnosticos'  => [$p, 'getDiagnosticos',  'setDiagnosticos'],
                    'Medicamentos'  => [$p, 'getMedicamentos',  'setMedicamentos'],
                ] as $label => [$obj, $getter, $setter]) {
                    $val = $obj->$getter();
                    if ($val !== null && !$this->encryption->isEncrypted($val)) {
                        if (!$dryRun) {
                            $obj->$setter($this->encryption->encrypt($val));
                        }
                        $changed = true;
                    }
                }

                $changed ? ($cifrados++ && (!$dryRun && $this->tenantEm->persist($p))) : $omitidos++;
            }
            $io->writeln(sprintf('  → Pacientes: <comment>%d cifrados</comment>, %d omitidos', $cifrados, $omitidos));

            $cifrados = $omitidos = 0;

            // ── Trabajadores ──────────────────────────────────────────────────
            $trabajadores = $this->tenantEm->getRepository(Trabajador::class)->findAll();
            foreach ($trabajadores as $t) {
                $changed = false;

                if ($t->getRut() !== null && !$this->encryption->isEncrypted($t->getRut())) {
                    $io->writeln(sprintf('  Trabajador <info>%s</info>: cifrando RUT %s', $t->getNombreCompleto(), $t->getRut()));
                    if (!$dryRun) {
                        $plain = $t->getRut();
                        $t->setRutHash($this->encryption->hmac($plain));
                        $t->setRut($this->encryption->encrypt($plain));
                    }
                    $changed = true;
                } elseif ($t->getRut() !== null && $t->getRutHash() === null) {
                    if (!$dryRun) {
                        $t->setRutHash($this->encryption->hmac($this->encryption->decrypt($t->getRut())));
                    }
                    $changed = true;
                }

                foreach ([
                    'CuentaBancaria'     => [$t, 'getCuentaBancaria',     'setCuentaBancaria'],
                    'DatosPrevisionales' => [$t, 'getDatosPrevisionales', 'setDatosPrevisionales'],
                ] as $label => [$obj, $getter, $setter]) {
                    $val = $obj->$getter();
                    if ($val !== null && !$this->encryption->isEncrypted($val)) {
                        if (!$dryRun) {
                            $obj->$setter($this->encryption->encrypt($val));
                        }
                        $changed = true;
                    }
                }

                $changed ? ($cifrados++ && (!$dryRun && $this->tenantEm->persist($t))) : $omitidos++;
            }
            $io->writeln(sprintf('  → Trabajadores: <comment>%d cifrados</comment>, %d omitidos', $cifrados, $omitidos));

            $cifrados = $omitidos = 0;

            // ── Eventos Adversos ──────────────────────────────────────────────
            $eventos = $this->tenantEm->getRepository(EventoAdverso::class)->findAll();
            foreach ($eventos as $e) {
                $changed = false;

                if ($e->getDescripcion() !== '' && !$this->encryption->isEncrypted($e->getDescripcion())) {
                    if (!$dryRun) {
                        $e->setDescripcion($this->encryption->encrypt($e->getDescripcion()));
                    }
                    $changed = true;
                }
                if ($e->getAccionesTomadas() !== null && !$this->encryption->isEncrypted($e->getAccionesTomadas())) {
                    if (!$dryRun) {
                        $e->setAccionesTomadas($this->encryption->encrypt($e->getAccionesTomadas()));
                    }
                    $changed = true;
                }

                $changed ? ($cifrados++ && (!$dryRun && $this->tenantEm->persist($e))) : $omitidos++;
            }
            $io->writeln(sprintf('  → Eventos adversos: <comment>%d cifrados</comment>, %d omitidos', $cifrados, $omitidos));

            if (!$dryRun) {
                $this->tenantEm->flush();
                $this->tenantEm->clear();
            }
        }

        $io->success($dryRun ? 'Dry-run completado. Sin cambios en BD.' : 'Todos los tenants cifrados correctamente.');

        return Command::SUCCESS;
    }
}
