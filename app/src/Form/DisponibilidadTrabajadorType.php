<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\DisponibilidadTrabajador;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DisponibilidadTrabajadorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fecha', DateType::class, [
                'label'  => 'Fecha',
                'widget' => 'single_text',
            ])
            ->add('horaDesde', TimeType::class, [
                'label'  => 'Desde',
                'widget' => 'single_text',
            ])
            ->add('horaHasta', TimeType::class, [
                'label'  => 'Hasta',
                'widget' => 'single_text',
            ])
            ->add('disponible', CheckboxType::class, [
                'label'    => 'Disponible',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DisponibilidadTrabajador::class]);
    }
}
