<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\CondicionDomicilio;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CondicionDomicilioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('accesoDescripcion', TextareaType::class, [
                'label'    => 'Descripción del acceso',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Ej: Edificio con portero, timbre 3B'],
            ])
            ->add('codigoAcceso', TextType::class, [
                'label'    => 'Código de acceso / clave portón',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('requiereAscensor', CheckboxType::class, [
                'label'    => 'Requiere ascensor',
                'required' => false,
                'attr'     => ['class' => 'form-check-input'],
            ])
            ->add('tieneMascotas', CheckboxType::class, [
                'label'    => 'Hay mascotas en el domicilio',
                'required' => false,
                'attr'     => ['class' => 'form-check-input'],
            ])
            ->add('mascotasDetalle', TextType::class, [
                'label'    => 'Detalle de mascotas',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Ej: 1 perro mediano, amigable'],
            ])
            ->add('tieneBarrerasArquitectonicas', CheckboxType::class, [
                'label'    => 'Existen barreras arquitectónicas',
                'required' => false,
                'attr'     => ['class' => 'form-check-input'],
            ])
            ->add('barrerasDetalle', TextareaType::class, [
                'label'    => 'Detalle de barreras',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 2],
            ])
            ->add('observacionesSeguridad', TextareaType::class, [
                'label'    => 'Observaciones de seguridad',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Situaciones de riesgo, alertas para el equipo'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CondicionDomicilio::class]);
    }
}
