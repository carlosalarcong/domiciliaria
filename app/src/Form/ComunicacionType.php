<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TipoComunicacion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ComunicacionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tipo', ChoiceType::class, [
                'label'   => 'Tipo',
                'choices' => TipoComunicacion::choices(),
                'attr'    => ['class' => 'form-select'],
            ])
            ->add('contacto', TextType::class, [
                'label'    => 'Persona contactada',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Nombre del contacto'],
            ])
            ->add('descripcion', TextareaType::class, [
                'label'       => 'Descripción',
                'attr'        => ['class' => 'form-control', 'rows' => 3],
                'constraints' => [new NotBlank(['message' => 'La descripción no puede estar vacía.'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
