import { Controller } from 'https://unpkg.com/@hotwired/stimulus/dist/stimulus.js';

export default class extends Controller {
    static targets = [
        'el',
        'modalBody',
        'modalFooter',
        'trabajador',
        'tipo',
        'estado',
        'limpiar',
        'periodo',
        'kvHoy',
        'kvDesc',
        'kvComp',
        'kvHoyComp',
    ];

    static values = {
        eventosUrl: String,
        kpisUrl: String,
        detalleUrl: String,
    };

    connect() {
        if (!this.hasElTarget || typeof window.FullCalendar === 'undefined' || typeof window.bootstrap === 'undefined') {
            return;
        }

        this.filtros = {
            trabajadorId: this.hasTrabajadorTarget ? this.trabajadorTarget.value : '',
            tipo: this.hasTipoTarget ? this.tipoTarget.value : '',
            estado: this.hasEstadoTarget ? this.estadoTarget.value : '',
        };

        this.modal = new window.bootstrap.Modal(this.modalBodyTarget.closest('.modal'));

        const toolbarDesktop = {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek',
        };
        const toolbarMobile = {
            left: 'prev,next',
            center: 'title',
            right: 'today listWeek',
        };

        this.calendar = new window.FullCalendar.Calendar(this.elTarget, {
            locale: 'es',
            initialView: this.esMobile() ? 'listWeek' : 'dayGridMonth',
            headerToolbar: this.esMobile() ? toolbarMobile : toolbarDesktop,
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Lista',
            },
            windowResize: () => {
                this.calendar.changeView(this.esMobile() ? 'listWeek' : 'dayGridMonth');
                this.calendar.setOption('headerToolbar', this.esMobile() ? toolbarMobile : toolbarDesktop);
            },
            events: (fetchInfo, successCallback, failureCallback) => {
                const url = new URL(this.eventosUrlValue, window.location.origin);
                url.searchParams.set('start', fetchInfo.startStr);
                url.searchParams.set('end', fetchInfo.endStr);
                if (this.filtros.trabajadorId) {
                    url.searchParams.set('trabajadorId', this.filtros.trabajadorId);
                }
                if (this.filtros.estado) {
                    url.searchParams.set('estado', this.filtros.estado);
                }
                if (this.filtros.tipo) {
                    url.searchParams.set('tipo', this.filtros.tipo);
                }

                fetch(url)
                    .then((response) => response.json())
                    .then(successCallback)
                    .catch(failureCallback);
            },
            eventContent: (arg) => this.eventContent(arg),
            eventClick: (info) => this.abrirDetalle(info),
            eventDidMount: (info) => {
                info.el.title = info.event.title;
            },
            datesSet: (info) => {
                if (!this.hasPeriodoTarget) {
                    return;
                }

                this.periodoTarget.textContent = info.start.toLocaleDateString('es-CL', {
                    month: 'long',
                    year: 'numeric',
                });
            },
        });

        this.calendar.render();
        this.actualizarVisibilidadLimpiar();
        this.cargarKpis();
    }

    aplicarFiltros() {
        this.filtros.trabajadorId = this.hasTrabajadorTarget ? this.trabajadorTarget.value : '';
        this.filtros.tipo = this.hasTipoTarget ? this.tipoTarget.value : '';
        this.filtros.estado = this.hasEstadoTarget ? this.estadoTarget.value : '';

        this.actualizarVisibilidadLimpiar();
        this.calendar?.refetchEvents();
    }

    limpiar(event) {
        event.preventDefault();

        if (this.hasTrabajadorTarget) {
            this.trabajadorTarget.value = '';
        }
        if (this.hasTipoTarget) {
            this.tipoTarget.value = '';
        }
        if (this.hasEstadoTarget) {
            this.estadoTarget.value = '';
        }

        this.filtros = { trabajadorId: '', tipo: '', estado: '' };
        this.element.querySelectorAll('.kpi-turno').forEach((kpi) => kpi.classList.remove('active'));

        this.actualizarVisibilidadLimpiar();
        this.calendar?.refetchEvents();
    }

    kpiClick(event) {
        const card = event.currentTarget;
        const filtro = card?.dataset?.filtro ?? '';

        if (!filtro) {
            this.limpiar(new Event('click'));
            return;
        }

        if (filtro === 'hoy') {
            this.calendar?.gotoDate(new Date());
            this.calendar?.changeView('timeGridDay');
            this.element.querySelectorAll('.kpi-turno').forEach((kpi) => kpi.classList.remove('active'));
            card.classList.add('active');
            return;
        }

        if (this.hasEstadoTarget) {
            this.estadoTarget.value = filtro;
        }

        this.filtros.estado = filtro;
        this.actualizarVisibilidadLimpiar();
        this.element.querySelectorAll('.kpi-turno').forEach((kpi) => kpi.classList.remove('active'));
        card.classList.add('active');
        this.calendar?.refetchEvents();
    }

    esMobile() {
        return window.innerWidth < 768;
    }

    async cargarKpis() {
        if (!this.hasKpisUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.kpisUrlValue);
            const data = await response.json();

            if (this.hasKvHoyTarget) {
                this.kvHoyTarget.textContent = data.hoy_total ?? '0';
            }
            if (this.hasKvDescTarget) {
                this.kvDescTarget.textContent = data.descubiertos_7d ?? '0';
            }
            if (this.hasKvCompTarget) {
                this.kvCompTarget.textContent = data.completados_mes ?? '0';
            }
            if (this.hasKvHoyCompTarget) {
                this.kvHoyCompTarget.textContent = data.hoy_completados ?? '0';
            }
        } catch (_) {
            // Silenciar fallo de KPI
        }
    }

    async abrirDetalle(info) {
        if (!this.hasModalBodyTarget || !this.hasModalFooterTarget) {
            return;
        }

        const turnoId = info.event.id;
        this.modalBodyTarget.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>';
        this.modalFooterTarget.innerHTML = '';
        this.modal.show();

        try {
            const response = await fetch(`${this.detalleUrlValue}/${turnoId}/detalle`);
            const html = await response.text();
            this.modalBodyTarget.innerHTML = html;
            this.modalFooterTarget.innerHTML = `<a href="${this.detalleUrlValue}/${turnoId}" class="btn btn-primary btn-sm">Ver detalle completo</a>`;
        } catch (_) {
            this.modalBodyTarget.innerHTML = '<div class="alert alert-danger mb-0">No se pudo cargar el detalle del turno.</div>';
        }
    }

    actualizarVisibilidadLimpiar() {
        if (!this.hasLimpiarTarget) {
            return;
        }

        const hayFiltro = Boolean(this.filtros.trabajadorId || this.filtros.tipo || this.filtros.estado);
        this.limpiarTarget.classList.toggle('d-none', !hayFiltro);
    }

    abreviarNombre(nombreCompleto) {
        const partes = (nombreCompleto || '').trim().split(/\s+/).filter(Boolean);
        if (partes.length === 0) {
            return '';
        }
        if (partes.length === 1) {
            return partes[0];
        }

        return `${partes[0]} ${partes[1].charAt(0)}.`;
    }

    escapeHtml(texto) {
        return String(texto)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    eventContent(arg) {
        const props = arg.event.extendedProps;
        const tipoIcon = { TURNO_DIA: '☀', TURNO_NOCHE: '🌙', TURNO_24H: '⏳', VISITA: '🏠' };
        const icon = tipoIcon[props.tipoTurno] ?? '';
        const reemplazo = props.esReemplazo ? '<span class="chip-reemplazo">↺</span>' : '';
        const paciente = (props.paciente ?? 'Sin paciente').split(' ').slice(0, 2).join(' ');
        const trabajadorNombre = props.trabajador ?? 'Sin asignar';

        const trabajadorChip = props.trabajadorId
            ? `<span class="chip-iniciales chip-trabajador" title="Trabajador: ${this.escapeHtml(trabajadorNombre)}">👤 ${this.escapeHtml(this.abreviarNombre(trabajadorNombre))}</span>`
            : '<span class="chip-iniciales chip-sin-asignar" title="Sin trabajador asignado">Sin asignar</span>';

        return {
            html: `<div class="fc-event-chip">${trabajadorChip}<span class="chip-texto">${icon} ${this.escapeHtml(paciente)}</span>${reemplazo}</div>`,
        };
    }
}
