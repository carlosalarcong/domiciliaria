<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\Trabajador;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
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
                'label'  => 'Año',
                'data'   => (int) date('Y'),
                'mapped' => false,
            ])
            ->add('mes', IntegerType::class, [
                'label'  => 'Mes (1-12)',
                'data'   => (int) date('n'),
                'mapped' => false,
            ])
            ->add('observaciones', TextareaType::class, [
                'label'    => 'Observaciones',
                'required' => false,
                'attr'     => ['rows' => 2, 'placeholder' => 'Notas internas (opcional)'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
