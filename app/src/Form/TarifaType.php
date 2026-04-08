<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\Mandante;
use App\Entity\Tenant\Tarifa;
use App\Enum\TipoConcepto;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TarifaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mandante', EntityType::class, [
                'label'        => 'Mandante',
                'class'        => Mandante::class,
                'choice_label' => 'nombre',
                'placeholder'  => '— Tarifa general (aplica a todos) —',
                'required'     => false,
                'help'         => 'Dejar vacío para crear una tarifa general que aplique a todos los mandantes.',
            ])
            ->add('tipoConcepto', EnumType::class, [
                'label'   => 'Tipo de concepto',
                'class'   => TipoConcepto::class,
                'choices' => array_filter(TipoConcepto::cases(), fn(TipoConcepto $c) => !in_array($c, [TipoConcepto::BONO, TipoConcepto::DESCUENTO])),
                'choice_label' => fn(TipoConcepto $c) => $c->etiqueta(),
            ])
            ->add('valorUnitario', MoneyType::class, [
                'label'    => 'Valor por hora ($)',
                'currency' => 'CLP',
                'divisor'  => 1,
                'scale'    => 0,
                'attr'     => ['placeholder' => '0'],
            ])
            ->add('vigenciaDesde', DateType::class, [
                'label'  => 'Vigente desde',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
            ])
            ->add('vigenciaHasta', DateType::class, [
                'label'    => 'Vigente hasta',
                'widget'   => 'single_text',
                'input'    => 'datetime_immutable',
                'required' => false,
                'help'     => 'Dejar vacío si la tarifa no tiene fecha de término.',
            ])
            ->add('descripcion', TextType::class, [
                'label'    => 'Descripción (opcional)',
                'required' => false,
                'attr'     => ['placeholder' => 'Ej: Tarifa convenio 2026'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tarifa::class,
        ]);
    }
}
