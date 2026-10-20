Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#64748B';

const COLORS = {
    primary: '#1E3A8A',
    primaryLight: 'rgba(30,58,138,0.12)',
    success: '#16A34A',
    warning: '#F59E0B',
    danger: '#DC2626',
    palette: ['#1E3A8A', '#16A34A', '#F59E0B', '#DC2626', '#0EA5E9', '#8B5CF6', '#EC4899', '#14B8A6'],
};

let productionChart;

function renderProductionChart(mode) {
    const src = mode === 'weekly' ? chartWeekly : chartHourly;
    const ctx = document.getElementById('productionChart');
    if (!ctx) return;
    if (productionChart) productionChart.destroy();

    productionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: src.labels,
            datasets: [{
                label: mode === 'weekly' ? 'Daily Production (last 7 days)' : 'Hourly Production (today)',
                data: src.data,
                borderColor: COLORS.primary,
                backgroundColor: COLORS.primaryLight,
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: COLORS.primary,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#EEF2F7' } },
                x: { grid: { display: false } },
            },
        },
    });
}

document.querySelectorAll('[data-range]').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('[data-range]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        renderProductionChart(this.dataset.range);
    });
});

renderProductionChart('hourly');

if (document.getElementById('lineEffChart')) {
    new Chart(document.getElementById('lineEffChart'), {
        type: 'bar',
        data: {
            labels: chartLineEff.labels,
            datasets: [{
                label: 'Efficiency %',
                data: chartLineEff.data,
                backgroundColor: chartLineEff.data.map(v => v >= 85 ? COLORS.success : v >= 70 ? COLORS.warning : COLORS.danger),
                borderRadius: 6,
            }],
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, max: 120, grid: { color: '#EEF2F7' } },
                y: { grid: { display: false } },
            },
        },
    });
}

if (document.getElementById('downtimeChart')) {
    new Chart(document.getElementById('downtimeChart'), {
        type: 'doughnut',
        data: {
            labels: chartDowntime.labels.length ? chartDowntime.labels : ['No downtime recorded'],
            datasets: [{
                data: chartDowntime.data.length ? chartDowntime.data : [1],
                backgroundColor: COLORS.palette,
                borderWidth: 2,
                borderColor: '#fff',
            }],
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 11 } },
                },
            },
        },
    });
}

if (document.getElementById('defectChart')) {
    new Chart(document.getElementById('defectChart'), {
        type: 'bar',
        data: {
            labels: chartDefects.labels.length ? chartDefects.labels : ['No defects today'],
            datasets: [{
                data: chartDefects.data.length ? chartDefects.data : [0],
                backgroundColor: COLORS.danger,
                borderRadius: 6,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#EEF2F7' } },
                x: { grid: { display: false } },
            },
        },
    });
}

setInterval(() => {
    $.get(BASE_URL_JS + '/ajax/dashboard_stats.php', function (res) {
        if (!res || !res.success) return;
        document.getElementById('statTodayProduction').textContent = res.data.today_production;
        document.getElementById('statTodayTarget').textContent = res.data.today_target;
        document.getElementById('statEfficiency').textContent = res.data.efficiency + '%';
    }, 'json');
}, 60000);