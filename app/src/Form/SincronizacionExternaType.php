<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\SincronizacionExterna;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SincronizacionExternaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
            ])
            ->add('urlEndpoint', TextType::class, [
                'label' => 'URL endpoint',
            ])
            ->add('metodo', ChoiceType::class, [
                'label' => 'Método HTTP',
                'choices' => [
                    'GET' => 'GET',
                    'POST' => 'POST',
                ],
            ])
            ->add('headers', TextareaType::class, [
                'label' => 'Headers',
                'required' => false,
                'help' => 'JSON: {"Authorization": "Bearer token"}',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => '{"X-API-Key":"tu_api_key"}',
                ],
            ])
            ->add('expresionCron', TextType::class, [
                'label' => 'Expresión cron',
                'help' => 'ej: 0 6 * * * (diario a las 6AM)',
            ])
            ->add('activa', CheckboxType::class, [
                'label' => 'Activa',
                'required' => false,
            ]);

        $builder->get('headers')->addModelTransformer(new CallbackTransformer(
            static fn(?array $headers): string => $headers === null || $headers === []
                ? ''
                : json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            static function (?string $headersJson): ?array {
                $headersJson = trim((string) $headersJson);
                if ($headersJson === '') {
                    return null;
                }

                $decoded = json_decode($headersJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TransformationFailedException('El campo headers debe contener un JSON válido.');
                }

                if (!is_array($decoded)) {
                    throw new TransformationFailedException('El campo headers debe ser un objeto JSON.');
                }

                return $decoded;
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SincronizacionExterna::class,
        ]);
    }
}
