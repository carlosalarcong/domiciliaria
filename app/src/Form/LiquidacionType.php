<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Trabajador;
use App\Enum\TipoConcepto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LiquidacionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('trabajador', EntityType::class, [
                'label'        => 'Trabajador',
                'class'        => Trabajador::class,
                'choice_label' => fn(Trabajador $t) => $t->getNombreCompleto() . ' — ' . $t->getPerfil()->etiqueta(),
                'placeholder'  => '— Seleccione trabajador —',
                'mapped'       => false,
            ])
            ->add('anio', IntegerType::class, [
                'label' => 'Año',
                'data'  => (int) date('Y'),
                'mapped' => false,
            ])
            ->add('mes', IntegerType::class, [
                'label' => 'Mes (1-12)',
                'data'  => (int) date('n'),
                'mapped' => false,
            ])
        ;

        // Tarifas por tipo de concepto
        foreach (TipoConcepto::cases() as $concepto) {
            if (in_array($concepto, [TipoConcepto::BONO, TipoConcepto::DESCUENTO])) {
                continue;
            }
            $builder->add('tarifa_' . $concepto->value, MoneyType::class, [
                'label'    => 'Tarifa: ' . $concepto->etiqueta() . ' (por hora)',
                'currency' => 'CLP',
                'divisor'  => 1,
                'mapped'   => false,
                'required' => false,
                'data'     => 0,
                'attr'     => ['placeholder' => '0'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
