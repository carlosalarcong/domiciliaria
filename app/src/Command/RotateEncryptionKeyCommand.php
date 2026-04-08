<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Tenant\EventoAdverso;
use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rota la clave de cifrado sin downtime.
 * Descifra con la clave vieja (APP_ENCRYPTION_KEY_OLD) y re-cifra con la nueva (APP_ENCRYPTION_KEY).
 *
 * Uso: php bin/console app:security:rotate-key --old-key="def000..."
 */
#[AsCommand(
    name: 'app:security:rotate-key',
    description: 'Rota la clave de cifrado: descifra con clave vieja y re-cifra con la nueva.',
)]
class RotateEncryptionKeyCommand extends Command
{
    public function __construct(
        private readonly EncryptionService     $encryption,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('old-key', null, InputOption::VALUE_REQUIRED, 'Clave de cifrado antigua (ASCII-safe string)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo simular, no guardar cambios');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $oldKey = $input->getOption('old-key');
        $dryRun = $input->getOption('dry-run');

        if (!$oldKey) {
            $io->error('Debes proporcionar --old-key con la clave antigua.');
            return Command::FAILURE;
        }

        $io->title('Rotación de clave de cifrado');
        $dryRun && $io->warning('MODO DRY-RUN: no se guardarán cambios.');

        $total = 0;

        // Pacientes
        $pacientes = $this->em->getRepository(Paciente::class)->findAll();
        $io->section(sprintf('Procesando %d pacientes...', count($pacientes)));
        foreach ($pacientes as $p) {
            $changed = false;
            if ($p->getRut() && $this->encryption->isEncrypted($p->getRut())) {
                $plain = $this->encryption->decryptWithKey($p->getRut(), $oldKey);
                $p->setRut($this->encryption->encrypt($plain));
                $p->setRutHash($this->encryption->hmac($plain));
                $changed = true;
            }
            foreach (['Diagnosticos', 'Medicamentos', 'Observaciones'] as $field) {
                $getter = 'get' . $field;
                $setter = 'set' . $field;
                $val = $p->$getter();
                if ($val && $this->encryption->isEncrypted($val)) {
                    $p->$setter($this->encryption->encrypt($this->encryption->decryptWithKey($val, $oldKey)));
                    $changed = true;
                }
            }
            if ($changed && !$dryRun) {
                $this->em->persist($p);
                $total++;
            }
        }

        // Trabajadores
        $trabajadores = $this->em->getRepository(Trabajador::class)->findAll();
        $io->section(sprintf('Procesando %d trabajadores...', count($trabajadores)));
        foreach ($trabajadores as $t) {
            $changed = false;
            if ($t->getRut() && $this->encryption->isEncrypted($t->getRut())) {
                $plain = $this->encryption->decryptWithKey($t->getRut(), $oldKey);
                $t->setRut($this->encryption->encrypt($plain));
                $t->setRutHash($this->encryption->hmac($plain));
                $changed = true;
            }
            foreach (['CuentaBancaria', 'DatosPrevisionales'] as $field) {
                $getter = 'get' . $field;
                $setter = 'set' . $field;
                $val = $t->$getter();
                if ($val && $this->encryption->isEncrypted($val)) {
                    $t->$setter($this->encryption->encrypt($this->encryption->decryptWithKey($val, $oldKey)));
                    $changed = true;
                }
            }
            if ($changed && !$dryRun) {
                $this->em->persist($t);
                $total++;
            }
        }

        // EventosAdversos
        $eventos = $this->em->getRepository(EventoAdverso::class)->findAll();
        $io->section(sprintf('Procesando %d eventos adversos...', count($eventos)));
        foreach ($eventos as $e) {
            $changed = false;
            if ($e->getDescripcion() && $this->encryption->isEncrypted($e->getDescripcion())) {
                $e->setDescripcion($this->encryption->encrypt($this->encryption->decryptWithKey($e->getDescripcion(), $oldKey)));
                $changed = true;
            }
            if ($e->getAccionesTomadas() && $this->encryption->isEncrypted($e->getAccionesTomadas())) {
                $e->setAccionesTomadas($this->encryption->encrypt($this->encryption->decryptWithKey($e->getAccionesTomadas(), $oldKey)));
                $changed = true;
            }
            if ($changed && !$dryRun) {
                $this->em->persist($e);
                $total++;
            }
        }

        if (!$dryRun) {
            $this->em->flush();
            $io->success(sprintf('Rotación completada. %d registros actualizados.', $total));
        } else {
            $io->success('Dry-run completado. Sin cambios en BD.');
        }

        return Command::SUCCESS;
    }
}
