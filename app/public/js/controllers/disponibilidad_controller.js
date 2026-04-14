import { Controller } from 'https://unpkg.com/@hotwired/stimulus/dist/stimulus.js';

export default class extends Controller {
    static targets = ['trabajador', 'fecha', 'inicio', 'termino', 'alerta'];

    static values = {
        url: String,
    };

    async verificar() {
        if (
            !this.hasTrabajadorTarget ||
            !this.hasFechaTarget ||
            !this.hasInicioTarget ||
            !this.hasTerminoTarget ||
            !this.hasAlertaTarget
        ) {
            return;
        }

        const trabajador = this.trabajadorTarget.value;
        const fecha = this.fechaTarget.value;
        const horaInicio = this.inicioTarget.value;
        const horaTermino = this.terminoTarget.value;

        this.alertaTarget.innerHTML = '';

        if (!trabajador || !fecha || !horaInicio || !horaTermino || !this.hasUrlValue) {
            return;
        }

        const params = new URLSearchParams({
            trabajador,
            fecha,
            horaInicio,
            horaTermino,
        });

        try {
            const response = await fetch(`${this.urlValue}?${params.toString()}`);
            const data = await response.json();

            if (data.disponible) {
                this.alertaTarget.innerHTML = '<div class="text-success small"><i class="bi bi-check-circle me-1"></i>Trabajador disponible en ese horario.</div>';
                return;
            }

            this.alertaTarget.innerHTML = `<div class="alert alert-warning py-2 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>${this.escapeHtml(data.mensaje ?? 'Conflicto de disponibilidad')}</div>`;
        } catch (_) {
            this.alertaTarget.innerHTML = '<div class="alert alert-danger py-2 mb-0 small"><i class="bi bi-x-circle me-1"></i>No se pudo validar disponibilidad.</div>';
        }
    }

    escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}
