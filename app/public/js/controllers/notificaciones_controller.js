import { Controller } from 'https://unpkg.com/@hotwired/stimulus/dist/stimulus.js';

export default class extends Controller {
    static targets = ['badge', 'list', 'bell'];

    static values = {
        countUrl: String,
        recientesUrl: String,
        csrf: String,
    };

    connect() {
        if (!this.hasBellTarget) {
            return;
        }

        this.dropdownHandler = this.cargarRecientes.bind(this);
        this.bellTarget.addEventListener('show.bs.dropdown', this.dropdownHandler);

        this.cargarConteo();
        this.interval = window.setInterval(() => this.cargarConteo(), 60000);
    }

    disconnect() {
        if (this.interval) {
            window.clearInterval(this.interval);
            this.interval = null;
        }

        if (this.dropdownHandler && this.hasBellTarget) {
            this.bellTarget.removeEventListener('show.bs.dropdown', this.dropdownHandler);
        }
    }

    async cargarConteo() {
        if (!this.hasBadgeTarget || !this.hasCountUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.countUrlValue);
            const data = await response.json();
            const count = data.count ?? 0;

            if (count > 0) {
                this.badgeTarget.textContent = count > 99 ? '99+' : String(count);
                this.badgeTarget.style.display = '';
            } else {
                this.badgeTarget.style.display = 'none';
            }
        } catch (_) {
            // Silenciar errores de polling
        }
    }

    async cargarRecientes() {
        if (!this.hasListTarget || !this.hasRecientesUrlValue) {
            return;
        }

        this.listTarget.innerHTML = '<li class="list-group-item text-center text-muted small py-3">Cargando...</li>';

        try {
            const response = await fetch(this.recientesUrlValue);
            const data = await response.json();
            const notificaciones = data.notificaciones ?? [];
            const count = data.count ?? 0;

            if (notificaciones.length === 0) {
                this.listTarget.innerHTML = '<li class="list-group-item text-center text-muted small py-3">Sin notificaciones pendientes.</li>';
                if (this.hasBadgeTarget) {
                    this.badgeTarget.style.display = 'none';
                }
                return;
            }

            this.listTarget.innerHTML = '';

            notificaciones.forEach((n) => {
                const li = document.createElement('li');
                li.className = 'list-group-item px-3 py-2 bg-primary-subtle';
                li.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="small">
                            <div class="fw-semibold"><i class="bi ${this.iconClass(n.tipo)} me-1"></i>${this.escapeHtml(n.titulo ?? '')}</div>
                            ${n.cuerpo ? `<div class="text-muted">${this.escapeHtml(n.cuerpo)}</div>` : ''}
                        </div>
                        <div class="flex-shrink-0 d-flex flex-column align-items-end gap-1">
                            <span class="text-muted" style="font-size:.7rem">${this.escapeHtml(n.creadoEn ?? '')}</span>
                            <form method="post" action="/notificaciones/${encodeURIComponent(n.id)}/leer" style="margin:0">
                                <input type="hidden" name="_token" value="${this.escapeHtml(this.csrfValue)}">
                                <button type="submit" class="btn btn-primary btn-sm py-0 px-2" style="font-size:.7rem">
                                    ${n.url ? 'Ver' : 'Leída'}
                                </button>
                            </form>
                        </div>
                    </div>`;
                this.listTarget.appendChild(li);
            });

            if (this.hasBadgeTarget) {
                this.badgeTarget.textContent = count > 99 ? '99+' : String(count);
                this.badgeTarget.style.display = count > 0 ? '' : 'none';
            }
        } catch (_) {
            this.listTarget.innerHTML = '<li class="list-group-item text-center text-muted small py-3">Error al cargar.</li>';
        }
    }

    iconClass(tipo) {
        const map = {
            turno_descubierto: 'bi-calendar-x text-danger',
            evento_grave: 'bi-exclamation-triangle-fill text-warning',
            paciente_estado: 'bi-person-fill text-info',
            responsable_asignado: 'bi-person-check-fill text-primary',
        };

        return map[tipo] ?? 'bi-bell-fill text-secondary';
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
