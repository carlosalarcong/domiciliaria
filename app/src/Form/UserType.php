<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('apellido', TextType::class, [
                'label' => 'Apellido',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rol',
                'choices' => [
                    'Administrador' => 'ROLE_ADMIN',
                    'Coordinador' => 'ROLE_COORDINADOR',
                    'Enfermera' => 'ROLE_ENFERMERA',
                    'TENS' => 'ROLE_TENS',
                    'Visualizador' => 'ROLE_VISUALIZADOR',
                ],
                'multiple' => true,
                'expanded' => false,
                'attr' => ['class' => 'form-select'],
                'help' => 'Mantén Ctrl para seleccionar múltiples roles.',
            ])
            ->add('activo', CheckboxType::class, [
                'label' => 'Usuario activo',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);

        if (!$isEdit) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Contraseña',
                    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                    'constraints' => [
                        new NotBlank(['message' => 'La contraseña no puede estar vacía.']),
                        new Length(['min' => 8, 'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres.']),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmar contraseña',
                    'attr' => ['class' => 'form-control'],
                ],
                'invalid_message' => 'Las contraseñas no coinciden.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
