<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\Paciente;
use App\Entity\Tenant\Trabajador;
use App\Entity\Tenant\Turno;
use App\Enum\TipoTurno;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TurnoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('paciente', EntityType::class, [
                'label'        => 'Paciente',
                'class'        => Paciente::class,
                'choice_label' => fn(Paciente $p) => $p->getNombreCompleto() . ' (' . $p->getRut() . ')',
                'placeholder'  => '— Seleccione paciente —',
            ])
            ->add('trabajador', EntityType::class, [
                'label'        => 'Trabajador',
                'class'        => Trabajador::class,
                'choice_label' => fn(Trabajador $t) => $t->getNombreCompleto() . ' — ' . $t->getPerfil()->etiqueta(),
                'placeholder'  => '— Sin asignar (turno descubierto) —',
                'required'     => false,
                'attr'         => [
                    'data-controller'          => 'turno-disponibilidad',
                    'data-turno-disponibilidad-url-value' => '',
                ],
            ])
            ->add('fecha', DateType::class, [
                'label'  => 'Fecha',
                'widget' => 'single_text',
                'attr'   => ['data-turno-disponibilidad-target' => 'fecha'],
            ])
            ->add('horaInicio', TimeType::class, [
                'label'  => 'Hora inicio',
                'widget' => 'single_text',
                'attr'   => ['data-turno-disponibilidad-target' => 'horaInicio'],
            ])
            ->add('horaTermino', TimeType::class, [
                'label'  => 'Hora término',
                'widget' => 'single_text',
            ])
            ->add('tipoTurno', EnumType::class, [
                'label'        => 'Tipo de turno',
                'class'        => TipoTurno::class,
                'choice_label' => fn(TipoTurno $e) => $e->etiqueta(),
            ])
            ->add('observaciones', TextareaType::class, [
                'label'    => 'Observaciones',
                'required' => false,
                'attr'     => ['rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Turno::class]);
    }
}
