<?php

declare(strict_types=1);

namespace App\Command;

use Defuse\Crypto\Key;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:security:generate-key',
    description: 'Genera una clave de cifrado segura para APP_ENCRYPTION_KEY.',
)]
class GenerateEncryptionKeyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = Key::createNewRandomKey();
        $ascii = $key->saveToAsciiSafeString();

        $io->success('Clave generada:');
        $io->writeln("<comment>{$ascii}</comment>");
        $io->warning([
            'Guarda esta clave en un lugar seguro.',
            'Configúrala en Render/prod como: APP_ENCRYPTION_KEY=<clave>',
            'NUNCA la commitees en el repositorio.',
        ]);

        return Command::SUCCESS;
    }
}
