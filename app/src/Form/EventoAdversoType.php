<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EventoAdverso;
use App\Entity\Paciente;
use App\Entity\Trabajador;
use App\Enum\GravedadEvento;
use App\Enum\TipoEventoAdverso;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventoAdversoType extends AbstractType
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
                'label'        => 'Trabajador involucrado',
                'class'        => Trabajador::class,
                'choice_label' => fn(Trabajador $t) => $t->getNombreCompleto(),
                'placeholder'  => '— Sin trabajador asociado —',
                'required'     => false,
            ])
            ->add('fechaEvento', DateType::class, [
                'label'  => 'Fecha del evento',
                'widget' => 'single_text',
            ])
            ->add('horaEvento', TimeType::class, [
                'label'    => 'Hora del evento',
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('tipo', EnumType::class, [
                'label'        => 'Tipo de evento',
                'class'        => TipoEventoAdverso::class,
                'choice_label' => fn(TipoEventoAdverso $e) => $e->etiqueta(),
            ])
            ->add('gravedad', EnumType::class, [
                'label'        => 'Gravedad',
                'class'        => GravedadEvento::class,
                'choice_label' => fn(GravedadEvento $e) => $e->etiqueta(),
            ])
            ->add('descripcion', TextareaType::class, [
                'label' => 'Descripción del evento',
                'attr'  => ['rows' => 4, 'placeholder' => 'Describa qué ocurrió, cómo y dónde...'],
            ])
            ->add('accionesTomadas', TextareaType::class, [
                'label'    => 'Acciones tomadas inmediatamente',
                'required' => false,
                'attr'     => ['rows' => 3],
            ])
            ->add('notificadoFamilia', CheckboxType::class, [
                'label'    => 'Familia notificada',
                'required' => false,
            ])
            ->add('notificadoMedico', CheckboxType::class, [
                'label'    => 'Médico notificado',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => EventoAdverso::class]);
    }
}
