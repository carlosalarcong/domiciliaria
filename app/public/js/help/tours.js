(function () {
    const cfg = window.helpConfig;
    if (!cfg || !cfg.modulo) return;

    // Si ya completó este tour, no auto-iniciar
    // (pero sí permitir iniciarlo manualmente desde el botón)
    const yaVisto = Array.isArray(cfg.completados)
        && cfg.completados.includes(cfg.modulo);

    const tours = {};

    function iniciarTour(modulo) {
        const pasos = tours[modulo];
        if (!pasos || pasos.length === 0) return;

        const driver = window.driver.js.driver({
            showProgress: true,
            showButtons: ['next', 'previous', 'close'],
            nextBtnText: 'Siguiente →',
            prevBtnText: '← Anterior',
            doneBtnText: 'Entendido ✓',
            progressText: '{{current}} de {{total}}',
            onDestroyStarted: () => {
                marcarCompletado(modulo);
                driver.destroy();
            },
            steps: pasos,
        });

        driver.drive();
    }

    function marcarCompletado(modulo) {
        fetch('/help/tour/' + modulo + '/completar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: cfg.csrfToken }),
        }).catch(() => {});
    }

    // Botón manual del menú
    const startBtn = document.getElementById('help-start-tour');
    if (startBtn) {
        startBtn.addEventListener('click', () => {
            document.getElementById('help-menu')?.classList.remove('open');
            iniciarTour(cfg.modulo);
        });
    }

    // Auto-iniciar solo si no fue visto antes (delay de 600ms para
    // que el DOM esté completamente renderizado)
    if (!yaVisto) {
        setTimeout(() => iniciarTour(cfg.modulo), 600);
    }

    // ── Definición de tours ────────────────────────────────────────

    // Nota: los elementos con id fueron agregados específicamente
    // para los tours (ver Tarea 5). Usar element: '#id-del-elemento'.
    // Si element no existe en el DOM, Driver.js lo salta automáticamente.
    // Usar popover: { title, description, side, align }

    tours.dashboard = [
        {
            element: '#kpi-pacientes-activos',
            popover: {
                title: 'Pacientes activos',
                description: 'Muestra cuántos pacientes están actualmente en atención. Haz clic en la tarjeta para ir al listado completo.',
                side: 'bottom', align: 'start',
            },
        },
        {
            element: '#kpi-turnos-hoy',
            popover: {
                title: 'Turnos de hoy',
                description: 'Total de turnos programados para hoy. Si aparece un badge rojo, hay turnos sin personal asignado.',
                side: 'bottom', align: 'start',
            },
        },
        {
            element: '#kpi-eventos',
            popover: {
                title: 'Eventos adversos',
                description: 'Incidentes clínicos registrados. Cualquier anomalía debe registrarse aquí para auditoría.',
                side: 'bottom', align: 'start',
            },
        },
        {
            element: '.sidebar',
            popover: {
                title: 'Menú de navegación',
                description: 'Desde aquí accedes a todos los módulos: Pacientes, Turnos, Personal, Finanzas y más.',
                side: 'right', align: 'start',
            },
        },
        {
            element: '#bellDropdown',
            popover: {
                title: 'Notificaciones',
                description: 'Aquí aparecen alertas del sistema: turnos descubiertos, eventos graves y cambios de estado de pacientes.',
                side: 'bottom', align: 'end',
            },
        },
    ];

    tours.pacientes = [
        {
            element: '#btn-exportar-pacientes',
            popover: {
                title: 'Exportar listado',
                description: 'Descarga un CSV de pacientes respetando los filtros aplicados (estado, mandante y tipo de servicio).',
                side: 'bottom', align: 'start',
            },
        },
        {
            element: '#btn-nuevo-paciente',
            popover: {
                title: 'Registrar paciente',
                description: 'Crea el expediente de un nuevo paciente. Necesitarás su RUT, datos personales, dirección y tipo de servicio requerido.',
                side: 'bottom', align: 'end',
            },
        },
        {
            element: '#filtros-pacientes',
            popover: {
                title: 'Filtros de búsqueda',
                description: 'Filtra por estado (activo/alta/baja), mandante asignado o tipo de servicio. Útil cuando tienes muchos pacientes.',
                side: 'bottom', align: 'start',
            },
        },
        {
            element: '#tabla-pacientes',
            popover: {
                title: 'Listado de pacientes',
                description: 'Haz clic en el ícono de ojo para ver el detalle completo de cada paciente, incluyendo sus turnos y evolución.',
                side: 'top', align: 'start',
            },
        },
    ];

    tours.turnos = [
        {
            element: '#btn-exportar-turnos',
            popover: {
                title: 'Exportar turnos',
                description: 'Descarga un CSV con los turnos del período para compartirlo o revisarlo fuera del sistema.',
                side: 'bottom', align: 'start',
            },
        },
        {
            element: '#btn-nuevo-turno',
            popover: {
                title: 'Crear turno',
                description: 'Programa un turno para un paciente. Debes seleccionar el tipo (día/noche/24h), la fecha y el trabajador asignado.',
                side: 'bottom', align: 'end',
            },
        },
        {
            element: '#calendario-turnos',
            popover: {
                title: 'Calendario de turnos',
                description: 'Vista mensual de todos los turnos. Verde = cubierto, amarillo = parcial, rojo = sin personal. Haz clic en cualquier día para ver el detalle.',
                side: 'top', align: 'center',
            },
        },
        {
            element: '#leyenda-turnos',
            popover: {
                title: 'Leyenda de estados',
                description: 'Los colores indican el estado de cobertura. Un turno descubierto genera una notificación automática al coordinador.',
                side: 'bottom', align: 'start',
            },
        },
    ];

    tours.personal = [
        {
            element: '#btn-nuevo-trabajador',
            popover: {
                title: 'Agregar trabajador',
                description: 'Registra un nuevo miembro del personal. Define su perfil (TENS, Enfermera, Técnico) y sus datos de contacto.',
                side: 'bottom', align: 'end',
            },
        },
        {
            element: '#tabla-personal',
            popover: {
                title: 'Listado de personal',
                description: 'Aquí ves todos los trabajadores activos. Desde el ícono de ojo puedes ver sus turnos asignados y documentos.',
                side: 'top', align: 'start',
            },
        },
    ];

    tours.finanzas = [
        {
            element: '#card-liquidaciones',
            popover: {
                title: 'Liquidaciones de personal',
                description: 'Aquí se generan y aprueban las liquidaciones mensuales de cada trabajador, calculadas automáticamente desde los turnos completados.',
                side: 'right', align: 'start',
            },
        },
        {
            element: '#btn-nueva-liquidacion',
            popover: {
                title: 'Generar liquidación',
                description: 'Selecciona el trabajador y el período. El sistema calcula el monto automáticamente según las tarifas configuradas.',
                side: 'bottom', align: 'end',
            },
        },
        {
            element: '#card-facturas',
            popover: {
                title: 'Facturación a mandantes',
                description: 'Genera las facturas para cada mandante (isapre, empresa, particular) por los servicios prestados en el período.',
                side: 'left', align: 'start',
            },
        },
        {
            element: '#link-tarifas',
            popover: {
                title: 'Configuración de tarifas',
                description: 'Define los valores por tipo de concepto (turno día, noche, visita). Puedes tener tarifas generales o específicas por mandante.',
                side: 'right', align: 'start',
            },
        },
    ];

    tours.configuracion = [
        {
            element: '#config-card-identidad',
            popover: {
                title: 'Identidad de la clínica',
                description: 'Configura el nombre visible en el sistema, razón social y datos de contacto. El nombre aparecerá en el menú lateral y en los encabezados del sistema.',
                side: 'top', align: 'start',
            },
        },
        {
            element: '#config-card-facturacion',
            popover: {
                title: 'Facturación',
                description: 'Define el % de IVA aplicado al generar facturas, los días de vencimiento y el prefijo del número de factura. Estos valores se usan automáticamente al emitir facturas a mandantes.',
                side: 'top', align: 'start',
            },
        },
        {
            element: '#config-card-operaciones',
            popover: {
                title: 'Operaciones',
                description: 'Controla cuántos días antes se detectan turnos sin cobertura, la hora de revisión diaria automática y el tamaño máximo permitido para archivos subidos al sistema.',
                side: 'top', align: 'start',
            },
        },
        {
            element: '#config-card-notificaciones',
            popover: {
                title: 'Notificaciones',
                description: 'Activa o desactiva alertas internas por turnos descubiertos y eventos adversos graves. Si configuras un email externo, recibirá copia de las alertas críticas.',
                side: 'top', align: 'start',
            },
        },
        {
            element: '#config-card-modulos',
            popover: {
                title: 'Módulos activos',
                description: 'Desactivar un módulo lo oculta del menú lateral para todos los usuarios de esta clínica. Útil si tu organización no usa Finanzas o Eventos Adversos.',
                side: 'top', align: 'start',
            },
        },
        {
            element: '#btn-guardar-config',
            popover: {
                title: 'Guardar cambios',
                description: 'Los cambios se aplican de inmediato a todos los usuarios del tenant. El nombre de la clínica y los módulos activos se reflejan en tiempo real en el menú lateral.',
                side: 'top', align: 'end',
            },
        },
    ];
})();
