/* INO dashboard: client-side SVG for real, server-supplied monthly record counts. */
(function () {
  'use strict';
  var root = document.querySelector('.ino-admin[data-ino-command]');
  if (!root) return;
  var chart = root.querySelector('[data-ino-chart]');
  if (!chart) return;
  var series;
  try { series = JSON.parse(chart.getAttribute('data-ino-series') || '{}'); }
  catch (error) { series = {}; }
  var buttons = root.querySelectorAll('[data-ino-metric]');
  var summary = root.querySelector('[data-ino-chart-summary]');
  var svgNS = 'http://www.w3.org/2000/svg';
  var months = Array.isArray(series.months) ? series.months.slice(0, 12) : [];

  function element(type, attrs, parent, content) {
    var el = document.createElementNS(svgNS, type);
    Object.keys(attrs || {}).forEach(function (name) { el.setAttribute(name, String(attrs[name])); });
    if (content !== undefined) el.textContent = String(content);
    if (parent) parent.appendChild(el);
    return el;
  }

  function render(metric, label) {
    var entries = Array.isArray(series[metric]) ? series[metric].slice(0, months.length) : [];
    var values = months.map(function (_m, i) {
      return Math.max(0, Number(entries[i]) || 0);
    });
    buttons.forEach(function (button) {
      button.setAttribute('aria-pressed', button.getAttribute('data-ino-metric') === metric ? 'true' : 'false');
    });
    while (chart.firstChild) chart.removeChild(chart.firstChild);
    if (!months.length || !values.length) {
      chart.textContent = 'No monthly data available.';
      if (summary) summary.textContent = 'No reporting data available for ' + label + '.';
      return;
    }

    var width = 700, height = 265, left = 38, right = 16, top = 15, bottom = 46;
    var w = width - left - right, h = height - top - bottom;
    var max = Math.max(1, Math.max.apply(null, values));
    var ceiling = Math.max(1, Math.ceil(max / 4) * 4);
    var svg = element('svg', { viewBox: '0 0 700 265', role: 'img', 'aria-label': label + ' added per month in the last six months' }, chart);
    element('title', {}, svg, label + ' monthly totals');
    for (var tick = 0; tick <= 4; tick++) {
      var y = top + h * tick / 4;
      element('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'ino-axis' }, svg);
      element('text', { x: left - 11, y: y + 4, 'text-anchor': 'end', class: 'ino-axis-label' }, svg, Math.round(ceiling * (4 - tick) / 4));
    }
    var coords = values.map(function (value, i) {
      return {
        x: left + (months.length === 1 ? w / 2 : i * w / (months.length - 1)),
        y: top + h * (1 - value / ceiling)
      };
    });
    var lines = coords.map(function (point) { return point.x + ',' + point.y; }).join(' ');
    var fill = left + ',' + (top + h) + ' ' + lines + ' ' + coords[coords.length - 1].x + ',' + (top + h);
    element('polygon', { points: fill, class: 'ino-area' }, svg);
    element('polyline', { points: lines, class: 'ino-line' }, svg);
    coords.forEach(function (point, i) {
      var dot = element('circle', { cx: point.x, cy: point.y, r: 5, class: 'ino-point' }, svg);
      element('title', {}, dot, months[i] + ': ' + values[i] + ' ' + label.toLowerCase());
      element('text', { x: point.x, y: height - 17, 'text-anchor': 'middle', class: 'ino-axis-label' }, svg, months[i]);
    });
    var total = values.reduce(function (sum, value) { return sum + value; }, 0);
    if (summary) summary.textContent = label + ': ' + total.toLocaleString() + ' records created across ' + months.length + ' reporting months.';
  }
  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      render(button.getAttribute('data-ino-metric'), button.textContent.trim());
    });
  });
  var initial = buttons.length ? buttons[0] : null;
  if (initial) render(initial.getAttribute('data-ino-metric'), initial.textContent.trim());
}());
