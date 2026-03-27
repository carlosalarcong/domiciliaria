<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => [
                'label' => 'Nueva contraseña',
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(['message' => 'La contraseña no puede estar vacía.']),
                    new Length(['min' => 8, 'minMessage' => 'Mínimo {{ limit }} caracteres.']),
                ],
            ],
            'second_options' => [
                'label' => 'Confirmar nueva contraseña',
                'attr' => ['class' => 'form-control'],
            ],
            'invalid_message' => 'Las contraseñas no coinciden.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
