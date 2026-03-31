<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\WebhookSuscripcion;
use App\Enum\WebhookEvento;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class WebhookSuscripcionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label'       => 'Nombre de la integración',
                'constraints' => [new NotBlank()],
                'attr'        => ['placeholder' => 'ej: ERP Empresa X'],
            ])
            ->add('url', UrlType::class, [
                'label'       => 'URL del endpoint',
                'constraints' => [new NotBlank(), new Url()],
                'attr'        => ['placeholder' => 'https://erp.empresa.com/webhooks/domiciliaria'],
            ])
            ->add('secret', TextType::class, [
                'label'    => 'Clave secreta (HMAC-SHA256)',
                'required' => false,
                'attr'     => ['placeholder' => 'Dejar vacío para regenerar automáticamente'],
            ])
            ->add('eventos', EnumType::class, [
                'class'    => WebhookEvento::class,
                'label'    => 'Eventos a escuchar',
                'expanded' => true,
                'multiple' => true,
                'choice_label' => fn(WebhookEvento $e) => $e->etiqueta(),
                'choice_attr'  => fn(WebhookEvento $e) => ['data-group' => $e->grupo()],
            ])
            ->add('activo', CheckboxType::class, [
                'label'    => 'Suscripción activa',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WebhookSuscripcion::class]);
    }
}
