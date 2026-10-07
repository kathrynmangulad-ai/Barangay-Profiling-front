







(function () {
    'use strict';

    var dataEl = document.getElementById('dashboard-data');
    if (!dataEl || typeof window.Chart === 'undefined') { return; }

    var payload;
    try { payload = JSON.parse(dataEl.textContent); }
    catch (err) { return; }

    Chart.defaults.font.family = "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";
    Chart.defaults.color = '#64748B';
    Chart.defaults.font.size = 12;

    var gridColor = 'rgba(148, 163, 184, 0.22)';

    function showEmpty(canvas) {
        var box = canvas.closest('.chart-box') || canvas.parentNode;
        canvas.style.display = 'none';
        if (box.querySelector('.chart-empty')) { return; }
        var empty = document.createElement('div');
        empty.className = 'chart-empty';
        empty.innerHTML = '<div class="chart-empty__mark" aria-hidden="true"></div>' +
            '<p class="chart-empty__title">No data yet</p>' +
            '<small class="chart-empty__hint">This chart fills in automatically once records are added.</small>';
        box.appendChild(empty);
    }

    function hasValues(arr) {
        return Array.isArray(arr) && arr.some(function (v) { return Number(v) > 0; });
    }

     
    var growthCanvas = document.getElementById('growthChart');
    if (growthCanvas && payload.growth && hasValues(payload.growth.data)) {
        new Chart(growthCanvas, {
            type: 'bar',
            data: {
                labels: payload.growth.labels,
                datasets: [{
                    label: 'New residents',
                    data: payload.growth.data,
                    backgroundColor: payload.growth.color || '#0E7490',
                    hoverBackgroundColor: '#075B6B',
                    borderRadius: 6,
                    maxBarThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#172033',
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { color: gridColor } },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor, drawBorder: false },
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    } else if (growthCanvas) {
        showEmpty(growthCanvas);
    }

     
    var popCanvas = document.getElementById('populationChart');
    if (popCanvas && payload.population && hasValues(payload.population.data)) {
        new Chart(popCanvas, {
            type: 'doughnut',
            data: {
                labels: payload.population.labels,
                datasets: [{
                    data: payload.population.data,
                    backgroundColor: payload.population.colors,
                    borderColor: '#FFFFFF',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 8, padding: 14 }
                    },
                    tooltip: {
                        backgroundColor: '#172033',
                        padding: 10,
                        cornerRadius: 8
                    }
                }
            }
        });
    } else if (popCanvas) {
        showEmpty(popCanvas);
    }
     
    if (payload.sparks) {
        var sparkNodes = document.querySelectorAll('canvas[data-spark]');
        sparkNodes.forEach(function (cv) {
            var key = cv.getAttribute('data-spark');
            var series = payload.sparks[key];
            if (!hasValues(series)) {
                 
                series = [0, 0, 0, 0, 0, 0];
            }
            var accent = getComputedStyle(cv.closest('.kpi--glance')).getPropertyValue('--spark').trim() || '#0E7490';
            new Chart(cv, {
                type: 'line',
                data: {
                    labels: payload.sparks.labels,
                    datasets: [{
                        data: series,
                        borderColor: accent,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        tension: 0.45,
                        fill: true,
                        backgroundColor: accent + '22'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    events: [],
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: {
                        x: { display: false },
                        y: { display: false, beginAtZero: true }
                    }
                }
            });
        });
    }

})();
