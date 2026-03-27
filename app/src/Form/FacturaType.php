<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Mandante;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FacturaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mandante', EntityType::class, [
                'label'        => 'Mandante',
                'class'        => Mandante::class,
                'choice_label' => fn(Mandante $m) => $m->getNombre(),
                'placeholder'  => '— Seleccione mandante —',
                'mapped'       => false,
            ])
            ->add('anio', IntegerType::class, [
                'label'  => 'Año',
                'data'   => (int) date('Y'),
                'mapped' => false,
            ])
            ->add('mes', IntegerType::class, [
                'label'  => 'Mes (1-12)',
                'data'   => (int) date('n'),
                'mapped' => false,
            ])
            ->add('montoNeto', MoneyType::class, [
                'label'    => 'Monto neto ($)',
                'currency' => 'CLP',
                'divisor'  => 1,
                'mapped'   => false,
            ])
            ->add('porcentajeIva', NumberType::class, [
                'label'  => 'IVA (%)',
                'data'   => 19.0,
                'mapped' => false,
            ])
            ->add('numeroFactura', TextType::class, [
                'label'    => 'Número de factura',
                'required' => false,
                'mapped'   => false,
            ])
            ->add('observaciones', TextareaType::class, [
                'label'    => 'Observaciones',
                'required' => false,
                'mapped'   => false,
                'attr'     => ['rows' => 2],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
