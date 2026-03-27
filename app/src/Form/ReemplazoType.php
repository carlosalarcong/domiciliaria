<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Trabajador;
use App\Enum\MotivoReemplazo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReemplazoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reemplazo', EntityType::class, [
                'label'        => 'Trabajador reemplazante',
                'class'        => Trabajador::class,
                'choice_label' => fn(Trabajador $t) => $t->getNombreCompleto() . ' — ' . $t->getPerfil()->etiqueta(),
                'placeholder'  => '— Seleccione trabajador —',
                'mapped'       => false,
            ])
            ->add('motivo', EnumType::class, [
                'label'        => 'Motivo del reemplazo',
                'class'        => MotivoReemplazo::class,
                'choice_label' => fn(MotivoReemplazo $e) => $e->etiqueta(),
                'mapped'       => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
