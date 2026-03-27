<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Trabajador;
use App\Entity\User;
use App\Enum\EstadoTrabajador;
use App\Enum\PerfilTrabajador;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrabajadorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombres', TextType::class, ['label' => 'Nombres'])
            ->add('apellidos', TextType::class, ['label' => 'Apellidos'])
            ->add('rut', TextType::class, ['label' => 'RUT'])
            ->add('perfil', EnumType::class, [
                'label'        => 'Perfil',
                'class'        => PerfilTrabajador::class,
                'choice_label' => fn(PerfilTrabajador $e) => $e->etiqueta(),
            ])
            ->add('telefono', TextType::class, ['label' => 'Teléfono', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
            ->add('direccion', TextType::class, ['label' => 'Dirección', 'required' => false])
            ->add('estado', EnumType::class, [
                'label'        => 'Estado',
                'class'        => EstadoTrabajador::class,
                'choice_label' => fn(EstadoTrabajador $e) => $e->etiqueta(),
            ])
            ->add('fechaIngreso', DateType::class, [
                'label'    => 'Fecha de ingreso',
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('user', EntityType::class, [
                'label'        => 'Usuario del sistema',
                'class'        => User::class,
                'choice_label' => fn(User $u) => $u->getNombreCompleto() . ' <' . $u->getEmail() . '>',
                'required'     => false,
                'placeholder'  => '— Sin usuario asociado —',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Trabajador::class]);
    }
}
