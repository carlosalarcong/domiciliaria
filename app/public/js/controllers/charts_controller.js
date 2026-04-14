import { Controller } from 'https://unpkg.com/@hotwired/stimulus/dist/stimulus.js';

export default class extends Controller {
    static targets = ['canvas'];

    static values = {
        tipo: String,
        labels: Array,
        datasets: Array,
    };

    connect() {
        if (!this.hasCanvasTarget || typeof window.Chart === 'undefined') {
            return;
        }

        window.Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
        window.Chart.defaults.color = '#6c757d';

        switch (this.tipoValue) {
            case 'mandante':
                this.renderMandante();
                break;
            case 'trabajador':
                this.renderTrabajador();
                break;
            case 'paciente':
                this.renderPaciente();
                break;
            case 'flujo':
                this.renderFlujo();
                break;
            default:
                break;
        }
    }

    renderMandante() {
        if (this.labelsValue.length === 0 || this.datasetsValue.length === 0) {
            return;
        }

        new window.Chart(this.canvasTarget, {
            type: 'doughnut',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    data: this.datasetsValue[0] ?? [],
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#6366f1',
                    ],
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' $' + Number(ctx.parsed ?? 0).toLocaleString('es-CL'),
                        },
                    },
                },
            },
        });
    }

    renderTrabajador() {
        if (this.labelsValue.length === 0 || this.datasetsValue.length < 2) {
            return;
        }

        new window.Chart(this.canvasTarget, {
            type: 'bar',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    label: 'Total liquidado',
                    data: this.datasetsValue[0] ?? [],
                    backgroundColor: '#ef444480',
                    borderColor: '#ef4444',
                    borderWidth: 1,
                }, {
                    label: 'Pagado',
                    data: this.datasetsValue[1] ?? [],
                    backgroundColor: '#10b98180',
                    borderColor: '#10b981',
                    borderWidth: 1,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: {
                        ticks: { callback: (v) => '$' + (Number(v) / 1000).toFixed(0) + 'k' },
                    },
                },
            },
        });
    }

    renderPaciente() {
        if (this.labelsValue.length === 0 || this.datasetsValue.length < 2) {
            return;
        }

        new window.Chart(this.canvasTarget, {
            type: 'bar',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    label: 'Total turnos',
                    data: this.datasetsValue[0] ?? [],
                    backgroundColor: '#3b82f680',
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                }, {
                    label: 'Descubiertos',
                    data: this.datasetsValue[1] ?? [],
                    backgroundColor: '#ef444480',
                    borderColor: '#ef4444',
                    borderWidth: 1,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { ticks: { stepSize: 1 } },
                },
            },
        });
    }

    renderFlujo() {
        if (this.labelsValue.length === 0 || this.datasetsValue.length < 2) {
            return;
        }

        new window.Chart(this.canvasTarget, {
            type: 'line',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    label: 'Ingresos',
                    data: this.datasetsValue[0] ?? [],
                    borderColor: '#10b981',
                    backgroundColor: '#10b98120',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                }, {
                    label: 'Egresos',
                    data: this.datasetsValue[1] ?? [],
                    borderColor: '#ef4444',
                    backgroundColor: '#ef444420',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                }],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: {
                        ticks: { callback: (v) => '$' + (Number(v) / 1000).toFixed(0) + 'k' },
                    },
                },
            },
        });
    }
}
