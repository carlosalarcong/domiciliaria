<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Mandante;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MandanteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, ['label' => 'Nombre', 'attr' => ['class' => 'form-control']])
            ->add('rut', TextType::class, ['label' => 'RUT', 'attr' => ['class' => 'form-control', 'placeholder' => '12.345.678-9']])
            ->add('contacto', TextType::class, ['label' => 'Contacto', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('telefono', TextType::class, ['label' => 'Teléfono', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('activo', CheckboxType::class, ['label' => 'Activo', 'required' => false, 'attr' => ['class' => 'form-check-input']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Mandante::class]);
    }
}
