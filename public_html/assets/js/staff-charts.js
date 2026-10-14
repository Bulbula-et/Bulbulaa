(function () {
  'use strict';

  const palette = {
    navy: '#173f68', blue: '#397eb5', sky: '#71b4da', orange: '#ea6a15',
    green: '#2daf72', slate: '#8798aa', grid: 'rgba(49,85,116,.10)'
  };

  function parseConfig(canvas) {
    try { return JSON.parse(canvas.dataset.chartConfig || '{}'); }
    catch (_) { return null; }
  }

  function baseOptions(config) {
    const horizontal = config.indexAxis === 'y';
    return {
      responsive: true,
      maintainAspectRatio: false,
      resizeDelay: 80,
      interaction: { mode: 'index', intersect: false },
      animation: { duration: 550 },
      indexAxis: config.indexAxis || 'x',
      plugins: {
        legend: {
          display: config.legend !== false,
          position: 'bottom',
          align: 'start',
          labels: { usePointStyle: true, boxWidth: 7, boxHeight: 7, padding: 16, color: '#637487', font: { size: 10, weight: '600' } }
        },
        tooltip: {
          backgroundColor: '#102f4d', titleColor: '#fff', bodyColor: '#dce9f3',
          padding: 11, cornerRadius: 8, displayColors: true, boxPadding: 4
        }
      },
      scales: config.scales === false ? undefined : {
        x: { grid: { display: horizontal, color: palette.grid }, border: { display: false }, ticks: { color: '#7b8998', font: { size: 9 }, maxRotation: 0 } },
        y: { beginAtZero: true, grid: { color: palette.grid }, border: { display: false }, ticks: { color: '#7b8998', font: { size: 9 }, precision: 0 } }
      }
    };
  }

  function styleDatasets(config) {
    const colors = [palette.blue, palette.orange, palette.green, palette.navy, palette.sky, palette.slate];
    return (config.datasets || []).map(function (dataset, index) {
      const color = dataset.color || colors[index % colors.length];
      const chartType = config.type || 'line';
      const isLine = chartType === 'line';
      const isDoughnut = chartType === 'doughnut';
      return Object.assign({
        borderColor: isDoughnut ? '#ffffff' : color,
        backgroundColor: isDoughnut ? [palette.blue, palette.orange, palette.green, palette.navy, palette.sky, palette.slate] : (isLine ? color + '20' : color + 'D9'),
        borderWidth: isDoughnut ? 3 : (isLine ? 2 : 0),
        pointRadius: isLine ? 2.5 : 0,
        pointHoverRadius: isLine ? 4 : 0,
        tension: .35,
        fill: !!dataset.fill,
        borderRadius: isLine ? 0 : 6,
        borderSkipped: false,
        maxBarThickness: 32
      }, dataset);
    });
  }

  function init() {
    if (typeof window.Chart !== 'function') return;
    window.Chart.defaults.font.family = 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
    window.Chart.defaults.color = '#637487';

    document.querySelectorAll('[data-bulbula-chart]').forEach(function (canvas) {
      const config = parseConfig(canvas);
      if (!config || !Array.isArray(config.labels) || !Array.isArray(config.datasets)) return;
      new window.Chart(canvas, {
        type: config.type || 'line',
        data: { labels: config.labels, datasets: styleDatasets(config) },
        options: baseOptions(config)
      });
    });
  }

  window.addEventListener('DOMContentLoaded', init, { once: true });
})();
