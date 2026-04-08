<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Paciente;
use App\Enum\EstadoPaciente;
use App\Enum\TipoServicio;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PacienteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Datos personales
            ->add('nombres', TextType::class, ['label' => 'Nombres', 'attr' => ['class' => 'form-control']])
            ->add('apellidos', TextType::class, ['label' => 'Apellidos', 'attr' => ['class' => 'form-control']])
            ->add('rut', TextType::class, ['label' => 'RUT', 'attr' => ['class' => 'form-control', 'placeholder' => '12.345.678-9']])
            ->add('fechaNacimiento', DateType::class, [
                'label'  => 'Fecha de nacimiento',
                'widget' => 'single_text',
                'required' => false,
                'attr'   => ['class' => 'form-control'],
            ])
            // Contacto
            ->add('telefono', TextType::class, ['label' => 'Teléfono', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('telefonoEmergencia', TextType::class, ['label' => 'Teléfono emergencia', 'required' => false, 'attr' => ['class' => 'form-control']])
            // Domicilio
            ->add('direccion', TextType::class, ['label' => 'Dirección', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('comuna', TextType::class, ['label' => 'Comuna', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('region', TextType::class, ['label' => 'Región', 'required' => false, 'attr' => ['class' => 'form-control']])
            // Servicio
            ->add('mandante', EntityType::class, [
                'class'        => Mandante::class,
                'choice_label' => 'nombre',
                'label'        => 'Mandante',
                'required'     => false,
                'placeholder'  => '— Sin mandante —',
                'attr'         => ['class' => 'form-select'],
                'query_builder' => fn($repo) => $repo->createQueryBuilder('m')
                    ->where('m.activo = true')
                    ->orderBy('m.nombre', 'ASC'),
            ])
            ->add('tipoServicio', EnumType::class, [
                'class'        => TipoServicio::class,
                'label'        => 'Tipo de servicio',
                'choice_label' => fn(TipoServicio $e) => $e->etiqueta(),
                'attr'         => ['class' => 'form-select'],
            ])
            ->add('estado', EnumType::class, [
                'class'        => EstadoPaciente::class,
                'label'        => 'Estado',
                'choice_label' => fn(EstadoPaciente $e) => $e->etiqueta(),
                'attr'         => ['class' => 'form-select'],
            ])
            ->add('fechaIngreso', DateType::class, [
                'label'  => 'Fecha de ingreso',
                'widget' => 'single_text',
                'required' => false,
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('fechaTermino', DateType::class, [
                'label'  => 'Fecha de término',
                'widget' => 'single_text',
                'required' => false,
                'attr'   => ['class' => 'form-control'],
            ])
            // Tutor
            ->add('tutorNombre', TextType::class, ['label' => 'Nombre del tutor/responsable', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('tutorTelefono', TextType::class, ['label' => 'Teléfono del tutor', 'required' => false, 'attr' => ['class' => 'form-control']])
            ->add('tutorRelacion', TextType::class, ['label' => 'Relación con el paciente', 'required' => false, 'attr' => ['class' => 'form-control', 'placeholder' => 'Ej: Hijo, Cónyuge']])
            ->add('observaciones', TextareaType::class, [
                'label'    => 'Observaciones generales',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Paciente::class]);
    }
}
