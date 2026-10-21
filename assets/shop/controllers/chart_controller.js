import { Controller } from '@hotwired/stimulus';
import ApexCharts from 'apexcharts';

// Every colour is at least 7:1 against white (WCAG AAA). The colour is never the only carrier of
// information: the series are told apart by their legend and by their fill (solid for the last 12
// months, hatched for the previous year); the data table below the chart gives the same figures.
const COLORS = ['#0b4f8a', '#4d4d4d', '#7a3b00', '#1b5e20'];
const HATCHES = ['slantedLines', 'verticalLines', 'horizontalLines', 'squares'];

/**
 * Bar chart of the purchases per month. The data comes from the data attributes of the element
 * (no inline script, no request, no external resource), which keeps a strict Content-Security-Policy happy.
 */
export default class extends Controller {
    static values = {
        categories: Array,
        series: Array,
        metric: String,
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
        const series = this.seriesValue;

        return {
            chart: {
                type: 'bar',
                height: 360,
                toolbar: { show: false },
                animations: { enabled: !reducedMotion },
                fontFamily: 'inherit',
                foreColor: '#212529',
            },
            series: series.map((one) => ({ name: one.name, data: one.data })),
            colors: series.map((_, index) => COLORS[Math.floor(index / 2) % COLORS.length]),
            fill: {
                type: series.map((one) => (one.previous ? 'pattern' : 'solid')),
                pattern: { style: series.map((_, index) => HATCHES[Math.floor(index / 2) % HATCHES.length]), strokeWidth: 3, width: 6, height: 6 },
            },
            stroke: { show: true, width: 1, colors: ['#212529'] },
            plotOptions: { bar: { columnWidth: '70%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: this.categoriesValue, labels: { rotate: -45, rotateAlways: false } },
            yaxis: { labels: { formatter: (value) => this.number(value) } },
            legend: { position: 'top', horizontalAlign: 'left' },
            tooltip: { y: { formatter: (value, { seriesIndex }) => this.tooltip(value, series[seriesIndex]) } },
        };
    }

    number(value) {
        return new Intl.NumberFormat(this.localeValue, { maximumFractionDigits: 0 }).format(value);
    }

    tooltip(value, series) {
        if ('orders' === this.metricValue) {
            return this.number(value);
        }

        return new Intl.NumberFormat(this.localeValue, { style: 'currency', currency: series.currency }).format(value);
    }
}
