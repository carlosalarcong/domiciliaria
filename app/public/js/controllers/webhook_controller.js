import { Controller } from 'https://unpkg.com/@hotwired/stimulus/dist/stimulus.js';

export default class extends Controller {
    static targets = ['secret', 'pingResult', 'pingForm'];

    copiar(event) {
        event.preventDefault();

        if (!this.hasSecretTarget) {
            return;
        }

        navigator.clipboard?.writeText(this.secretTarget.value ?? '');
    }

    generar(event) {
        event.preventDefault();

        if (!this.hasSecretTarget) {
            return;
        }

        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        this.secretTarget.value = Array.from(bytes)
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join('');
    }

    async ping(event) {
        event.preventDefault();

        if (!this.hasPingFormTarget || !this.hasPingResultTarget) {
            return;
        }

        this.pingResultTarget.className = 'mt-2 small text-muted';
        this.pingResultTarget.textContent = 'Enviando…';
        this.pingResultTarget.classList.remove('d-none');

        try {
            const formData = new FormData(this.pingFormTarget);
            const response = await fetch(this.pingFormTarget.action, {
                method: 'POST',
                body: formData,
            });
            const data = await response.json();

            if (data.codigo && data.codigo >= 200 && data.codigo < 300) {
                this.pingResultTarget.className = 'mt-2 small text-success';
                this.pingResultTarget.textContent = `✓ Respuesta ${data.codigo} — endpoint alcanzable.`;
                return;
            }

            if (data.codigo) {
                this.pingResultTarget.className = 'mt-2 small text-warning';
                this.pingResultTarget.textContent = `⚠ Respuesta HTTP ${data.codigo}`;
                return;
            }

            this.pingResultTarget.className = 'mt-2 small text-danger';
            this.pingResultTarget.textContent = `✗ Error: ${data.error ?? 'sin respuesta'}`;
        } catch (_) {
            this.pingResultTarget.className = 'mt-2 small text-danger';
            this.pingResultTarget.textContent = '✗ No se pudo conectar.';
        }
    }
}
