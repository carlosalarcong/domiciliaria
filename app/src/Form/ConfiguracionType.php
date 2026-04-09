<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tenant\ConfiguracionClinica;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfiguracionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombreClinica', TextType::class, [
                'label' => 'Nombre de la clínica',
                'help'  => 'Nombre visible en el menú lateral y encabezados del sistema.',
            ])
            ->add('razonSocial', TextType::class, [
                'label'    => 'Razón social',
                'required' => false,
                'help'     => 'Nombre legal de la empresa emisora.',
            ])
            ->add('rutEmpresa', TextType::class, [
                'label'    => 'RUT empresa',
                'required' => false,
                'help'     => 'RUT tributario usado en documentos de facturación.',
            ])
            ->add('giro', TextType::class, [
                'label'    => 'Giro',
                'required' => false,
                'help'     => 'Actividad comercial principal de la clínica.',
            ])
            ->add('direccionFiscal', TextareaType::class, [
                'label'    => 'Dirección fiscal',
                'required' => false,
                'attr'     => ['rows' => 2],
                'help'     => 'Dirección legal para documentos tributarios.',
            ])
            ->add('telefonoContacto', TelType::class, [
                'label'    => 'Teléfono',
                'required' => false,
                'help'     => 'Teléfono principal de contacto administrativo.',
            ])
            ->add('emailContacto', EmailType::class, [
                'label'    => 'Email de contacto',
                'required' => false,
                'help'     => 'Correo general visible para coordinación interna.',
            ])
            ->add('porcentajeIva', TextType::class, [
                'label' => '% IVA',
                'help'  => 'Porcentaje aplicado al generar facturas (ej: 19.00)',
            ])
            ->add('diasVencimientoFactura', IntegerType::class, [
                'label' => 'Días de vencimiento',
                'help'  => 'Días corridos desde emisión hasta vencimiento',
            ])
            ->add('prefijoFactura', TextType::class, [
                'label'    => 'Prefijo N° factura',
                'required' => false,
                'help'     => 'Ej: F-, FAC-. Dejar vacío si no aplica.',
            ])
            ->add('diasAnticipacionAlertas', IntegerType::class, [
                'label' => 'Días anticipación alertas',
                'help'  => 'Días antes de un turno para detectar cobertura',
            ])
            ->add('horaRevisionTurnos', TextType::class, [
                'label' => 'Hora revisión diaria',
                'attr'  => ['placeholder' => 'HH:MM'],
                'help'  => 'Hora referencial de la revisión automática',
            ])
            ->add('limiteDocumentosMB', IntegerType::class, [
                'label' => 'Límite archivos (MB)',
                'help'  => 'Tamaño máximo recomendado para carga de archivos.',
            ])
            ->add('notifTurnoDescubierto', CheckboxType::class, [
                'required' => false,
                'label'    => 'Notificar turnos descubiertos',
                'help'     => 'Envía alertas internas cuando se detecta falta de cobertura.',
            ])
            ->add('notifEventoGrave', CheckboxType::class, [
                'required' => false,
                'label'    => 'Notificar eventos adversos graves',
                'help'     => 'Activa notificaciones inmediatas por eventos críticos.',
            ])
            ->add('emailNotificaciones', EmailType::class, [
                'required' => false,
                'label'    => 'Email para alertas externas',
                'help'     => 'Recibirá copia de alertas críticas',
            ])
            ->add('moduloFinanzasActivo', CheckboxType::class, [
                'required' => false,
                'label'    => 'Módulo Finanzas activo',
                'help'     => 'Controla la visibilidad de Finanzas y Tarifas en el menú.',
            ])
            ->add('moduloEventosActivo', CheckboxType::class, [
                'required' => false,
                'label'    => 'Módulo Eventos Adversos activo',
                'help'     => 'Controla la visibilidad de Eventos Adversos en el menú.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConfiguracionClinica::class,
        ]);
    }
}
