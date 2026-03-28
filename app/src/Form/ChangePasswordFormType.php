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

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type'           => PasswordType::class,
            'first_options'  => [
                'label' => 'Nueva contraseña',
                'attr'  => ['autocomplete' => 'new-password', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Ingresa una contraseña.']),
                    new Length(['min' => 8, 'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres.']),
                ],
            ],
            'second_options' => [
                'label' => 'Confirmar contraseña',
                'attr'  => ['autocomplete' => 'new-password', 'class' => 'form-control'],
            ],
            'invalid_message' => 'Las contraseñas no coinciden.',
            'mapped'          => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
