<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TipoDocumento;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class DocumentoTrabajadorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tipo', EnumType::class, [
                'label'        => 'Tipo de documento',
                'class'        => TipoDocumento::class,
                'choice_label' => fn(TipoDocumento $e) => $e->etiqueta(),
            ])
            ->add('descripcion', TextType::class, [
                'label'    => 'Descripción',
                'required' => false,
                'attr'     => ['placeholder' => 'Ej: Contrato indefinido 2024'],
            ])
            ->add('fechaVencimiento', DateType::class, [
                'label'    => 'Fecha de vencimiento',
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('archivo', FileType::class, [
                'label'       => 'Archivo',
                'mapped'      => false,
                'constraints' => [
                    new NotNull(message: 'Debe seleccionar un archivo.'),
                    new File(
                        maxSize: '10M',
                        mimeTypes: [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg',
                            'image/png',
                        ],
                        mimeTypesMessage: 'Solo se permiten PDF, Word e imágenes (JPG, PNG).',
                    ),
                ],
                'attr' => ['accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
