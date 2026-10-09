/**
 * Dashboard and report charts, plus the animated numbers on the KPI cards.
 *
 * Chart.js is registered piece by piece (only what we draw), colours follow the company's own brand colour
 * (--brand-* variables), and every animation is skipped for people who ask their system for reduced motion.
 */
import {
    Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler,
    BarController, BarElement, DoughnutController, ArcElement, Tooltip,
} from 'chart.js';

Chart.register(
    LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler,
    BarController, BarElement, DoughnutController, ArcElement, Tooltip,
);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const GREEN = '#10b981';
const INK = '#0f172a';
const MUTED = '#64748b';
const GRID = '#eef2f7';

Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = MUTED;
// Adjust the defaults in place (replacing the whole object drops settings Chart.js relies on).
if (reduceMotion) {
    Chart.defaults.animation = false;
} else {
    Chart.defaults.animation.duration = 650;
    Chart.defaults.animation.easing = 'easeOutCubic';
}

/** One shade of the current brand colour ("r g b" CSS variable) as a colour Chart.js understands. */
function brand(shade, alpha = 1) {
    const raw = getComputedStyle(document.documentElement).getPropertyValue(`--brand-${shade}`).trim();
    const [r, g, b] = raw ? raw.split(/\s+/) : ['59', '154', '232'];

    return alpha === 1 ? `rgb(${r}, ${g}, ${b})` : `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const tooltip = {
    backgroundColor: INK,
    padding: 10,
    cornerRadius: 8,
    boxPadding: 4,
    titleFont: { weight: '600' },
};

const truncate = (text, max = 24) => (text.length > max ? text.slice(0, max - 1) + '...' : text);

/** Soft fill under a line: fades from the line colour to nothing. */
function fadeFill(color) {
    return (ctx) => {
        const { chart } = ctx;
        if (!chart.chartArea) return 'transparent';
        const g = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
        g.addColorStop(0, color.replace('rgb(', 'rgba(').replace(')', ', 0.22)'));
        g.addColorStop(1, color.replace('rgb(', 'rgba(').replace(')', ', 0)'));

        return g;
    };
}

/** Created vs resolved lines. Shared by the dashboard and the reports page. */
function trendConfig({ labels, full, created, resolved, createdName }) {
    const blue = brand(500);

    return {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: createdName, data: created, borderColor: blue, backgroundColor: fadeFill(blue), fill: true },
                { label: 'Resolved', data: resolved, borderColor: GREEN, backgroundColor: fadeFill('rgb(16, 185, 129)'), fill: true },
            ].map((d) => ({
                ...d, borderWidth: 2.5, tension: 0.35, pointRadius: 0, pointHoverRadius: 5,
                pointHoverBackgroundColor: '#fff', pointHoverBorderWidth: 2, pointHoverBorderColor: d.borderColor,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: GRID }, ticks: { precision: 0, maxTicksLimit: 5 } },
            },
            plugins: {
                legend: { display: false },
                tooltip: { ...tooltip, callbacks: { title: (items) => full[items[0].dataIndex] ?? items[0].label } },
            },
        },
    };
}

/** Writes the total in the hole of a doughnut. */
const centerTotal = {
    id: 'centerTotal',
    afterDraw(chart) {
        const total = chart.options.plugins.centerTotal?.total;
        if (total === undefined) return;
        const { ctx, chartArea: a } = chart;
        const x = (a.left + a.right) / 2;
        const y = (a.top + a.bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = INK;
        ctx.font = '700 22px Inter, system-ui, sans-serif';
        ctx.fillText(total.toLocaleString(), x, y - 6);
        ctx.fillStyle = MUTED;
        ctx.font = '500 11px Inter, system-ui, sans-serif';
        ctx.fillText('tickets', x, y + 14);
        ctx.restore();
    },
};

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /**
     * x-count="expression": the number counts up to the value, and counts again whenever it changes.
     * The server already prints the final number inside the element, so nothing is blank without JavaScript.
     */
    Alpine.directive('count', (el, { expression }, { evaluateLater, effect }) => {
        const read = evaluateLater(expression);
        let shown = 0;
        let frame;

        const paint = (n) => { el.textContent = Math.round(n).toLocaleString(); };

        effect(() => read((value) => {
            const target = Number(value) || 0;
            cancelAnimationFrame(frame);

            if (reduceMotion || shown === target) {
                shown = target;
                paint(target);

                return;
            }

            const from = shown;
            const t0 = performance.now();
            const step = (now) => {
                const p = Math.min(1, (now - t0) / 750);
                shown = from + (target - from) * (1 - Math.pow(1 - p, 3));
                paint(shown);
                if (p < 1) frame = requestAnimationFrame(step);
                else { shown = target; paint(target); }
            };
            frame = requestAnimationFrame(step);
        }));
    });

    /** The six KPI numbers; the cards read from here and the live refresh writes to it. */
    Alpine.store('dash', {
        kpis: {},
        init(initial) { this.kpis = initial; },
    });

    /** The admin dashboard: activity line, status doughnut and category bars, refreshed in the background. */
    Alpine.data('dashCharts', (cfg) => {
        // Chart.js instances stay outside Alpine's reactive object (a proxy would break them).
        let trend; let status; let cats; let visibleStatus = [];

        return {
            data: cfg.initial,
            range: cfg.range,
            ranges: [{ key: '7d', label: '7 days' }, { key: '30d', label: '30 days' }, { key: '12w', label: '12 weeks' }],
            updatedAt: Date.now(),
            updatedLabel: 'just now',
            failed: false,
            loading: false,

            init() {
                this.$nextTick(() => this.draw());
                setInterval(() => { if (!document.hidden) this.refresh(); }, cfg.pollMs);
                setInterval(() => this.tick(), 15000);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(); });
                window.addEventListener('live-ticket', () => { if (!document.hidden) this.refresh(); }); // websocket nudge
            },

            get rangeLabel() {
                return { '7d': 'last 7 days', '30d': 'last 30 days', '12w': 'last 12 weeks, per week' }[this.range];
            },
            get net() { return this.data.trend.totals.created - this.data.trend.totals.resolved; },
            get trendEmpty() { return this.data.trend.totals.created + this.data.trend.totals.resolved === 0; },
            get statusTotal() { return this.data.status.reduce((n, s) => n + s.value, 0); },
            get trendSummary() {
                const t = this.data.trend.totals;

                return `Line chart, ${this.rangeLabel}: ${t.created} new tickets and ${t.resolved} resolved.`;
            },
            get statusSummary() {
                return 'Doughnut chart of tickets by status: ' + this.data.status.filter((s) => s.value).map((s) => `${s.label} ${s.value}`).join(', ') + '.';
            },

            tick() {
                const s = Math.round((Date.now() - this.updatedAt) / 1000);
                this.updatedLabel = s < 45 ? 'just now' : s < 3600 ? `${Math.round(s / 60)} min ago` : 'over an hour ago';
            },

            async setRange(key) {
                if (key === this.range) return;
                this.range = key;
                await this.refresh();
            },

            async refresh() {
                if (this.loading) return;
                this.loading = true;
                try {
                    const res = await fetch(`${cfg.url}?range=${this.range}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error(res.status);
                    const json = await res.json();
                    if (json.range !== this.range) return; // the person already picked another range
                    this.data = json;
                    this.$store.dash.kpis = json.kpis;
                    this.failed = false;
                    this.updatedAt = Date.now();
                    this.updatedLabel = 'just now';
                    this.update();
                } catch (e) {
                    this.failed = true; // offline or signed out: keep showing the last numbers
                } finally {
                    this.loading = false;
                }
            },

            draw() {
                const t = this.data.trend;
                trend = new Chart(this.$refs.trend, trendConfig({
                    labels: [...t.labels], full: [...t.full], created: [...t.created], resolved: [...t.resolved], createdName: 'New',
                }));

                visibleStatus = this.data.status.filter((s) => s.value > 0);
                status = new Chart(this.$refs.status, {
                    type: 'doughnut',
                    data: {
                        labels: visibleStatus.map((s) => s.label),
                        datasets: [{ data: visibleStatus.map((s) => s.value), backgroundColor: visibleStatus.map((s) => s.color), borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: { display: false },
                            tooltip,
                            centerTotal: { total: this.statusTotal },
                        },
                        onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
                        onClick: (e, els) => { if (els.length) window.location.href = visibleStatus[els[0].index].url; },
                    },
                    plugins: [centerTotal],
                });

                const c = this.data.categories;
                cats = new Chart(this.$refs.cats, {
                    type: 'bar',
                    data: {
                        labels: c.map((x) => truncate(x.label)),
                        datasets: [{ data: c.map((x) => x.value), backgroundColor: brand(500), hoverBackgroundColor: brand(700), borderRadius: 5, maxBarThickness: 16 }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { beginAtZero: true, border: { display: false }, grid: { color: GRID }, ticks: { precision: 0, maxTicksLimit: 5 } },
                            y: { grid: { display: false }, border: { display: false } },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { ...tooltip, callbacks: { title: (items) => this.data.categories[items[0].dataIndex]?.label, label: (i) => ` ${i.parsed.x} tickets` } },
                        },
                    },
                });
            },

            update() {
                if (!trend) return;
                const t = this.data.trend;

                trend.data.labels = [...t.labels];
                trend.data.datasets[0].data = [...t.created];
                trend.data.datasets[1].data = [...t.resolved];
                trend.options.plugins.tooltip.callbacks.title = (items) => t.full[items[0].dataIndex] ?? items[0].label;
                trend.update();

                visibleStatus = this.data.status.filter((s) => s.value > 0);
                status.data.labels = visibleStatus.map((s) => s.label);
                status.data.datasets[0].data = visibleStatus.map((s) => s.value);
                status.data.datasets[0].backgroundColor = visibleStatus.map((s) => s.color);
                status.options.plugins.centerTotal.total = this.statusTotal;
                status.update();

                const c = this.data.categories;
                cats.data.labels = c.map((x) => truncate(x.label));
                cats.data.datasets[0].data = c.map((x) => x.value);
                cats.update();
            },
        };
    });

    /** The "Ticket volume" chart on the Reports page (data is already in the page, no refresh needed). */
    Alpine.data('reportTrend', (cfg) => ({
        init() {
            const p = cfg.points;
            new Chart(this.$refs.canvas, trendConfig({
                labels: p.map((x) => x.label), full: p.map((x) => x.full),
                created: p.map((x) => x.created), resolved: p.map((x) => x.resolved), createdName: 'Submitted',
            }));
        },
    }));
});
