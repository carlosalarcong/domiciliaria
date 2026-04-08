<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tenant\Tarifa;
use App\Enum\TipoConcepto;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;

#[TenantFixture]
class TarifaFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int { return 5; }

    public function load(ObjectManager $manager): void
    {
        $vigenciaDesde = new \DateTimeImmutable('2026-01-01');

        $tarifasGenerales = [
            [TipoConcepto::TURNO_DIA,   '8000.00'],
            [TipoConcepto::TURNO_NOCHE, '10000.00'],
            [TipoConcepto::TURNO_24H,   '18000.00'],
            [TipoConcepto::VISITA,      '4500.00'],
            [TipoConcepto::REEMPLAZO,   '9000.00'],
        ];

        foreach ($tarifasGenerales as [$concepto, $valor]) {
            $tarifa = new Tarifa();
            $tarifa->setMandante(null) // tarifa general
                   ->setTipoConcepto($concepto)
                   ->setValorUnitario($valor)
                   ->setVigenciaDesde($vigenciaDesde)
                   ->setActiva(true)
                   ->setDescripcion('Tarifa general 2026');

            $manager->persist($tarifa);
        }

        $manager->flush();
    }
}
