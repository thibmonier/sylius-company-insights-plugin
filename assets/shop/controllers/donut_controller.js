import { Controller } from '@hotwired/stimulus';
import ApexCharts from 'apexcharts';

// Every colour is at least 7:1 against white (WCAG AAA). The colour is never the only carrier of
// information: the slices are told apart by their fill pattern and by the legend, which gives the
// name and the share; the table below the chart gives the same figures.
const COLORS = ['#0b4f8a', '#4d4d4d', '#7a3b00', '#1b5e20', '#6a1b9a', '#8b0000', '#004d40', '#37474f', '#5d4037', '#3e2723', '#212529'];
const HATCHES = ['slantedLines', 'verticalLines', 'horizontalLines', 'squares', 'circles'];

/**
 * Donut of the spend per category. The data comes from the data attributes of the element (no
 * inline script, no request, no external resource), which keeps a strict Content-Security-Policy happy.
 */
export default class extends Controller {
    static values = {
        labels: Array,
        values: Array,
        shares: Array,
        currency: String,
        locale: String,
    };

    connect() {
        this.chart = new ApexCharts(this.element, this.options());
        this.chart.render();
    }

    disconnect() {
        this.chart?.destroy();
        this.chart = null;
    }

    options() {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const count = this.valuesValue.length;
        const percent = new Intl.NumberFormat(this.localeValue, { style: 'percent', maximumFractionDigits: 0 });

        return {
            chart: {
                type: 'donut',
                height: 340,
                animations: { enabled: !reducedMotion },
                fontFamily: 'inherit',
                foreColor: '#212529',
            },
            series: this.valuesValue,
            labels: this.labelsValue,
            colors: Array.from({ length: count }, (_, index) => COLORS[index % COLORS.length]),
            fill: {
                type: 'pattern',
                pattern: { style: Array.from({ length: count }, (_, index) => HATCHES[index % HATCHES.length]), strokeWidth: 3, width: 6, height: 6 },
            },
            stroke: { show: true, width: 2, colors: ['#ffffff'] },
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '55%' } } },
            legend: {
                position: 'right',
                formatter: (name, { seriesIndex }) => `${name} (${percent.format((this.sharesValue[seriesIndex] ?? 0) / 100)})`,
            },
            tooltip: { y: { formatter: (value) => this.money(value) } },
            responsive: [{ breakpoint: 576, options: { legend: { position: 'bottom' } } }],
        };
    }

    money(value) {
        return new Intl.NumberFormat(this.localeValue, { style: 'currency', currency: this.currencyValue }).format(value);
    }
}
