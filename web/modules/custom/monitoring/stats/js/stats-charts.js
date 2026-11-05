/**
 * @file
 * Gives the Chart.js charts of the stats page a calm look in Gin's style.
 *
 * The script runs after Chart.js and before the deferred charts_chartjs
 * script builds the charts. It sets the Chart.js defaults from the theme
 * tokens, registers small plugins (hover line, doughnut total, empty state)
 * and colors the series from the palette tokens --yv-chart-1 … 13, so the
 * colors live only in the theme. The tokens are read again when Gin switches
 * to or from dark mode.
 */
((Drupal, Chart, once) => {
  const PALETTE_SIZE = 13;

  let tokens = {};

  // The tokens the defaults were last set from, as JSON.
  let applied = '';

  /**
   * Reads the theme tokens from the body, where Gin sets its colors.
   *
   * A token the theme does not set reads as an empty string, which Chart.js
   * ignores in favor of its defaults. A missing palette color is transparent,
   * so the series keep their indexes.
   */
  const readTokens = () => {
    const style = getComputedStyle(document.body);
    const get = (name) => style.getPropertyValue(name).trim();
    const palette = [];
    for (let i = 1; i <= PALETTE_SIZE; i++) {
      palette.push(get(`--yv-chart-${i}`) || 'transparent');
    }
    tokens = {
      ink: get('--yv-ink'),
      text: get('--yv-text'),
      muted: get('--yv-muted'),
      accent: get('--yv-accent') || palette[0],
      surface: get('--yv-surface'),
      line: get('--yv-line'),
      outline: get('--yv-outline'),
      font: style.fontFamily,
      palette,
    };
  };

  /**
   * Gets a color with an alpha, or transparent if Chart.js can't parse it.
   */
  const alpha = (color, value) => {
    const parsed = Chart.helpers.color(color);
    return parsed.valid ? parsed.alpha(value).rgbString() : 'transparent';
  };

  /**
   * Sets the defaults of all charts from the tokens.
   */
  const applyDefaults = () => {
    const { defaults, overrides } = Chart;
    applied = JSON.stringify(tokens);

    defaults.font.family = tokens.font;
    defaults.font.size = 12;
    defaults.color = tokens.muted;
    defaults.borderColor = tokens.line;
    // The stylesheet sizes the figure around the canvas.
    defaults.maintainAspectRatio = false;
    defaults.interaction.mode = 'index';
    defaults.interaction.intersect = false;
    defaults.plugins.colors.enabled = false;
    // Colors switch at once: an interrupted color animation could leave a
    // bar in a mixed color, and a theme switch needs none.
    defaults.animations.colors = false;

    // Legend: small dots with muted text, top left.
    Object.assign(defaults.plugins.legend, { position: 'top', align: 'start' });
    Object.assign(defaults.plugins.legend.labels, {
      usePointStyle: true,
      pointStyle: 'circle',
      boxWidth: 8,
      boxHeight: 8,
      padding: 14,
      color: tokens.muted,
    });

    // Tooltip: a Gin card with a hairline border and round color dots.
    Object.assign(defaults.plugins.tooltip, {
      backgroundColor: tokens.surface,
      titleColor: tokens.ink,
      bodyColor: tokens.text,
      footerColor: tokens.muted,
      borderColor: tokens.outline,
      borderWidth: 1,
      cornerRadius: 6,
      padding: 10,
      caretSize: 0,
      usePointStyle: true,
      boxWidth: 8,
      boxHeight: 8,
      boxPadding: 4,
    });
    defaults.plugins.tooltip.titleFont = { weight: '600' };

    // Axes: no axis lines and no ticks marks, thin dashed grid lines only
    // along the value axis, small muted labels.
    // Chart.js keeps the resolved options of a scale, so its colors are
    // functions that read the current tokens.
    Object.assign(defaults.scale.grid, {
      color: () => tokens.line,
      drawTicks: false,
    });
    Object.assign(defaults.scale.border, { display: false, dash: [3, 3] });
    Object.assign(defaults.scale.ticks, { color: () => tokens.muted, padding: 8 });
    defaults.scale.ticks.font = { size: 11 };
    defaults.scales.category.grid = { display: false };
    defaults.scales.linear.ticks = {
      ...defaults.scales.linear.ticks,
      maxTicksLimit: 5,
    };
    defaults.scales.linear.beginAtZero = true;

    // Slim bars with rounded ends.
    Object.assign(defaults.datasets.bar, {
      maxBarThickness: 16,
      categoryPercentage: 0.7,
      barPercentage: 0.8,
    });
    Object.assign(defaults.elements.bar, { borderRadius: 5, borderWidth: 0 });

    // Smooth lines with points on hover only.
    // Monotone curves never overshoot, so running totals never seem to dip.
    Object.assign(defaults.elements.line, {
      tension: 0.35,
      cubicInterpolationMode: 'monotone',
      borderWidth: 2,
    });
    Object.assign(defaults.elements.point, {
      radius: 0,
      hoverRadius: 4,
      hitRadius: 8,
      hoverBorderWidth: 2,
    });

    // Doughnuts: a thick ring of rounded segments with small gaps.
    Object.assign(defaults.datasets.doughnut, { cutout: '62%', spacing: 2 });
    Object.assign(defaults.elements.arc, {
      borderWidth: 2,
      borderColor: () => tokens.surface,
      borderRadius: 4,
    });
    overrides.doughnut.interaction = { mode: 'nearest', intersect: true };
  };

  /**
   * Gets the palette color of an index.
   */
  const paletteColor = (index) => tokens.palette[index % tokens.palette.length];

  /**
   * Checks whether a chart config holds no value other than 0.
   */
  const isEmpty = (data) =>
    data.datasets.every((dataset) =>
      dataset.data.every((value) => value === null || Number(value) === 0),
    );

  /**
   * Colors the datasets from the tokens before each update.
   *
   * A single series takes the accent, more series and the doughnut slices the
   * palette in its order.
   */
  const paint = {
    id: 'statsPaint',
    beforeUpdate(chart) {
      const type = chart.config.type;
      const { datasets } = chart.data;
      datasets.forEach((dataset, index) => {
        if (type === 'doughnut') {
          dataset.backgroundColor = dataset.data.map((_, i) => paletteColor(i));
          return;
        }
        const color = datasets.length === 1 ? tokens.accent : paletteColor(index);
        dataset.borderColor = color;
        if (type === 'line' && datasets.length > 1) {
          // Several areas would blend into a haze: lines only.
          dataset.fill = false;
          dataset.backgroundColor = color;
          dataset.pointBackgroundColor = color;
          dataset.pointBorderColor = tokens.surface;
          return;
        }
        if (type === 'line') {
          // A soft gradient from the single line down to the axis.
          dataset.fill = 'origin';
          dataset.backgroundColor = (context) => {
            const area = context.chart.chartArea;
            if (!area) {
              return 'transparent';
            }
            const gradient = context.chart.ctx.createLinearGradient(
              0,
              area.top,
              0,
              area.bottom,
            );
            gradient.addColorStop(0, alpha(color, 0.2));
            gradient.addColorStop(1, alpha(color, 0));
            return gradient;
          };
          dataset.pointBackgroundColor = color;
          dataset.pointBorderColor = tokens.surface;
          return;
        }
        dataset.backgroundColor = color;
        dataset.hoverBackgroundColor = alpha(color, 0.8);
      });
    },
  };

  /**
   * Draws a subtle vertical line at the hovered index of lines and columns.
   */
  const hoverLine = {
    id: 'statsHoverLine',
    beforeDatasetsDraw(chart) {
      const active = chart.tooltip?.getActiveElements() ?? [];
      if (
        !active.length ||
        chart.config.type === 'doughnut' ||
        chart.options.indexAxis === 'y'
      ) {
        return;
      }
      const { ctx, chartArea } = chart;
      // The middle of the hovered category, not of its first bar.
      const x = Math.round(chart.scales.x.getPixelForValue(active[0].index)) + 0.5;
      ctx.save();
      ctx.strokeStyle = tokens.outline;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(x, chartArea.top);
      ctx.lineTo(x, chartArea.bottom);
      ctx.stroke();
      ctx.restore();
    },
  };

  /**
   * Draws the total of the visible slices in the center of a doughnut.
   */
  const doughnutTotal = {
    id: 'statsDoughnutTotal',
    afterDatasetsDraw(chart) {
      if (chart.config.type !== 'doughnut' || isEmpty(chart.data)) {
        return;
      }
      const arc = chart.getDatasetMeta(0).data[0];
      if (!arc) {
        return;
      }
      const total = chart.data.datasets[0].data.reduce(
        (sum, value, index) =>
          chart.getDataVisibility(index) ? sum + (Number(value) || 0) : sum,
        0,
      );
      const size = Math.max(16, Math.min(28, Math.round(arc.innerRadius * 0.4)));
      const { ctx } = chart;
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = tokens.ink;
      ctx.font = `600 ${size}px ${tokens.font}`;
      ctx.fillText(String(total), arc.x, arc.y - size * 0.3);
      ctx.fillStyle = tokens.muted;
      ctx.font = `11px ${tokens.font}`;
      ctx.fillText(Drupal.t('Total'), arc.x, arc.y + size * 0.55);
      ctx.restore();
    },
  };

  /**
   * Draws a muted note in the middle of a chart without data.
   */
  const emptyState = {
    id: 'statsEmpty',
    afterDraw(chart, args, options) {
      if (!options.text) {
        return;
      }
      const { ctx, chartArea } = chart;
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = tokens.muted;
      ctx.font = `13px ${tokens.font}`;
      ctx.fillText(
        options.text,
        (chartArea.left + chartArea.right) / 2,
        (chartArea.top + chartArea.bottom) / 2,
      );
      ctx.restore();
    },
  };

  readTokens();
  applyDefaults();
  Chart.register(paint, hoverLine, doughnutTotal, emptyState);

  /**
   * Sets the options that depend on the single chart.
   *
   * The charts module hands each chart config to listeners on the chart's
   * element right before it builds the chart. The element has left the page
   * by then, so the listener sits on the element itself.
   */
  const configure = (event) => {
    // Gin's accent behavior has run by now: take over the tokens it sets.
    refresh(false);
    const config = event.detail;
    const options = config.options || {};
    config.options = options;
    options.plugins = options.plugins || {};
    // Horizontal bars look up the hovered index along the y axis.
    if (options.indexAxis === 'y') {
      options.interaction = { ...options.interaction, axis: 'y' };
    }
    // Without data, show a note instead of an empty grid or ring.
    if (config.data && isEmpty(config.data)) {
      options.plugins.statsEmpty = { text: Drupal.t('No data for this period') };
      options.plugins.legend = { ...options.plugins.legend, display: false };
      options.plugins.tooltip = { ...options.plugins.tooltip, enabled: false };
      Object.values(options.scales || {}).forEach((scale) => {
        scale.display = false;
      });
    }
  };

  // Defined before the deferred charts_chartjs script, this behavior attaches
  // before the one that builds the charts.
  Drupal.behaviors.statsCharts = {
    attach(context) {
      once('stats-charts', '.stats-page [data-chart]', context).forEach(
        (element) => {
          element.addEventListener('drupalChartsConfigsInitialization', configure);
        },
      );
    },
  };

  // Read the tokens again when Gin switches the color scheme or sets its
  // accent, and redraw the charts. Once the font has loaded, redraw them in it. A plain update keeps
  // a running animation intact, which update('none') would freeze midway.
  const refresh = (force) => {
    readTokens();
    if (!force && JSON.stringify(tokens) === applied) {
      return;
    }
    applyDefaults();
    Object.values(Chart.instances).forEach((chart) => {
      // Chart.js caches the resolved options, defaults included.
      chart.config.clearCache();
      chart.update();
    });
  };
  // Gin sets the dark mode class on the html element and its accent on the
  // body, the latter in a behavior that may run after the charts are built.
  const observer = new MutationObserver(() => refresh(false));
  [document.documentElement, document.body].forEach((element) => {
    observer.observe(element, {
      attributes: true,
      attributeFilter: ['class', 'data-gin-accent', 'data-gin-color-scheme'],
    });
  });
  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', () => refresh(false));
  document.fonts?.ready.then(() => refresh(true));
})(Drupal, Chart, once);
