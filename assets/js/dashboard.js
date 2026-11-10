/* Dashboard charts (Chart.js). Colours come from the active theme's CSS
   tokens, and the charts are redrawn when the theme changes. */
(function () {
    'use strict';

    var dataEl = document.getElementById('dashboard-data');
    if (!dataEl || typeof window.Chart === 'undefined') { return; }

    var payload;
    try { payload = JSON.parse(dataEl.textContent); }
    catch (err) { return; }

    Chart.defaults.font.family = "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif";
    Chart.defaults.font.size = 12;

    /* Resolve a CSS colour token to an rgba() string Chart.js can draw with.
       Tokens may be color-mix() expressions, so the browser resolves them on a
       hidden probe and a 1x1 canvas turns the result into plain rgba. */
    var probe = document.createElement('span');
    probe.style.display = 'none';
    document.body.appendChild(probe);
    var pixel = document.createElement('canvas');
    pixel.width = pixel.height = 1;
    var pctx = pixel.getContext('2d', { willReadFrequently: true });

    function token(name, fallback) {
        probe.style.color = 'var(' + name + ', ' + fallback + ')';
        var css = getComputedStyle(probe).color;
        if (!css || !pctx) { return fallback; }
        pctx.clearRect(0, 0, 1, 1);
        pctx.fillStyle = fallback;
        pctx.fillStyle = css;
        pctx.fillRect(0, 0, 1, 1);
        var d = pctx.getImageData(0, 0, 1, 1).data;
        return 'rgba(' + d[0] + ', ' + d[1] + ', ' + d[2] + ', ' + (d[3] / 255).toFixed(3) + ')';
    }

    function themeColors() {
        return {
            text:     token('--muted', '#64748B'),
            grid:     token('--chart-grid', 'rgba(148, 163, 184, 0.22)'),
            tooltip:  token('--tooltip-bg', '#172033'),
            card:     token('--card', '#FFFFFF'),
            bar:      token('--secondary', '#0E7490'),
            barHover: token('--primary', '#075B6B')
        };
    }

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

    var charts = [];

    function render() {
        charts.forEach(function (c) { c.destroy(); });
        charts = [];

        var c = themeColors();
        Chart.defaults.color = c.text;

        var growthCanvas = document.getElementById('growthChart');
        if (growthCanvas && payload.growth && hasValues(payload.growth.data)) {
            charts.push(new Chart(growthCanvas, {
                type: 'bar',
                data: {
                    labels: payload.growth.labels,
                    datasets: [{
                        label: 'New residents',
                        data: payload.growth.data,
                        backgroundColor: c.bar,
                        hoverBackgroundColor: c.barHover,
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
                            backgroundColor: c.tooltip,
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, border: { color: c.grid } },
                        y: {
                            beginAtZero: true,
                            grid: { color: c.grid, drawBorder: false },
                            ticks: { precision: 0 }
                        }
                    }
                }
            }));
        } else if (growthCanvas) {
            showEmpty(growthCanvas);
        }

        var popCanvas = document.getElementById('populationChart');
        if (popCanvas && payload.population && hasValues(payload.population.data)) {
            charts.push(new Chart(popCanvas, {
                type: 'doughnut',
                data: {
                    labels: payload.population.labels,
                    datasets: [{
                        data: payload.population.data,
                        backgroundColor: payload.population.colors,
                        borderColor: c.card,
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
                            backgroundColor: c.tooltip,
                            padding: 10,
                            cornerRadius: 8
                        }
                    }
                }
            }));
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
                charts.push(new Chart(cv, {
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
                }));
            });
        }
    }

    render();
    document.addEventListener('bms:themechange', render);

})();
