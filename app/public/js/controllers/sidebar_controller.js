import { Controller } from 'https://unpkg.com/@hotwired/stimulus/dist/stimulus.js';

export default class extends Controller {
    static targets = ['toggle', 'sidebar', 'overlay'];

    toggle(event) {
        event?.preventDefault();

        if (!this.hasSidebarTarget || !this.hasOverlayTarget) {
            return;
        }

        if (this.sidebarTarget.classList.contains('sidebar-open')) {
            this.cerrar();
            return;
        }

        this.abrir();
    }

    abrir() {
        if (!this.hasSidebarTarget || !this.hasOverlayTarget) {
            return;
        }

        this.sidebarTarget.classList.add('sidebar-open');
        this.overlayTarget.classList.add('show');
    }

    cerrar(event) {
        event?.preventDefault();

        if (!this.hasSidebarTarget || !this.hasOverlayTarget) {
            return;
        }

        this.sidebarTarget.classList.remove('sidebar-open');
        this.overlayTarget.classList.remove('show');
    }

    cerrarSiClick(event) {
        if (!event.target.closest('.sidebar-link')) {
            return;
        }

        this.cerrar();
    }
}
