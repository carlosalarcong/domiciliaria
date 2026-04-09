<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\ConfiguracionService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class ConfiguracionExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService,
    ) {}

    public function getGlobals(): array
    {
        try {
            return ['clinicaConfig' => $this->configuracionService->get()];
        } catch (\Throwable) {
            return ['clinicaConfig' => null];
        }
    }
}
