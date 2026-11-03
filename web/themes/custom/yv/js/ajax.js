/**
 * @file
 * Replaces the Claro/Gin ajax throbber with Sam, the youvo flamingo.
 *
 * A comet of light zaps along Sam's outline while Sam blinks. The fullscreen
 * variant adds twinkling sparkles around the bird.
 */

((Drupal) => {
  // Sam's head, neck and body as one closed outline, taken from sam.svg.
  const body =
    'M121.8,64.4 L121.5,64.1 C121.4,64 109.9,52.1 102,39.6 C101.3,38.5 100.8,37.4 100.4,36.2 C99.2,32.7 97.9,26.9 99.7,22.9 C100,22.2 100.8,20.6 103.4,19 C105.2,24.7 110.6,28.8 116.9,28.8 C124.7,28.8 131.1,22.4 131.1,14.6 C131.1,6.8 124.7,0.4 116.9,0.4 L116.5,0.4 C109.1,-0.2 103.6,1.6 103.6,1.6 C103.6,1.6 103.6,1.6 103.6,1.7 C92.4,4.8 87.9,11.6 85.2,16.3 C77.6,29.4 87.3,46.1 87.3,46.1 C95.8,60.2 108.4,73.4 109.9,75.1 C121.6,88.5 121.5,100.9 120.4,107 C113.7,97.8 88.6,68.3 51.2,82.4 C43.6,84.5 36.9,88.3 31.6,93.3 C31.5,93.4 31.3,93.5 31.2,93.6 L31.3,93.6 C24.5,100.2 20.1,108.7 19.4,118.1 C11.4,123.7 -5.3,139.6 2.4,170.3 C2.5,170.8 3.3,170.9 3.5,170.3 C4.9,165 10.7,152.4 34.4,150.5 C39.6,154.7 46,157.8 53,159.6 L52.8,160.1 C51.6,162.7 52.9,165.8 55.5,167 C58.1,168.2 61.2,166.9 62.4,164.3 L63.8,161.1 L65.7,161.1 L67.1,161.1 L67.1,166.1 C67.1,169 69.4,171.3 72.3,171.3 C75.2,171.3 77.5,169 77.5,166.1 L77.5,159.8 C87.9,157.4 96.9,151.9 103.1,144.5 C113.7,143.2 127.6,135.9 134.4,116.5 C136,112.2 143.2,88.7 121.8,64.4 Z';
  const beak =
    'M130.3,18.8 C132.7,22 147.9,43.9 124.2,55.9 C123.8,56.1 123.4,55.7 123.5,55.3 C124.8,51.9 127.6,41.1 119.2,28 C119.1,27.8 119.1,27.5 119.3,27.4 L129.6,18.7 C129.8,18.5 130.1,18.6 130.3,18.8 Z';
  const beakTip =
    'M131.850204,39.4501789 C131.822474,34.9653583 130.065167,29.5570387 125.5,23.1 C125.2,22.6 125.3,22 125.7,21.7 C126.2,21.4 126.8,21.5 127.1,21.9 C131.135734,27.6715343 133.326006,33.1417684 133.678986,38.1390943 C134.976014,37.1019647 136.156051,35.9133556 137.2,34.6 C138.1,41.9 135.8,50.1 124.3,55.9 C123.9,56.1 123.5,55.7 123.6,55.3 C124.4,53.3 125.7,48.6 124.8,42.3 C127.35511,41.788978 129.72742,40.8078977 131.850204,39.4501742 L131.850204,39.4501789 Z';
  // The legs in sam.svg are filled bars, so they are redrawn here as centre
  // lines along those bars: the standing leg with its foot, then the bent leg
  // with its foot.
  const legs =
    'M71.5,170 L71.5,339 M61.7,343.8 L70,340 L96,339 M70,340 L99.9,348.1 M70,340 L92.3,353.3 ' +
    'M57.2,164.2 L40.3,237 L85,242.6 L96.8,261.2 L92.3,277.2 M93.5,256 L89.3,270.3 M90.6,251.5 L85.9,263.6';
  // The standing leg again, for the spark that zaps up from the foot.
  const zap = 'M71.5,339 L71.5,171';
  const sparkle = '0,-9 2,-2 9,0 2,2 0,9 -2,2 -9,0 -2,-2';
  const sparkles = [
    [-14, 70],
    [166, 100],
    [150, -10],
    [-6, 250],
  ];
  const runners = ['tail', 'trail', 'head', 'spark']
    .map(
      (name) =>
        `<path class="yv-throbber-run yv-throbber-run-${name}" d="${body}" pathLength="100"/>`,
    )
    .join('');

  /**
   * Builds the flamingo throbber markup.
   *
   * @param {string} variant
   *   Either 'inline' or 'fullscreen'.
   * @return {string}
   *   The SVG throbber markup.
   */
  const flamingo = (variant) => {
    const extras =
      variant === 'fullscreen'
        ? sparkles
            .map(
              ([x, y]) =>
                `<g transform="translate(${x} ${y})"><polygon class="yv-throbber-sparkle" points="${sparkle}"/></g>`,
            )
            .join('')
        : '';
    const viewBox = variant === 'fullscreen' ? '-36 -26 210 400' : '-12 -12 162 384';

    // The halo sits behind the outline, so Sam stays readable over content.
    return `<span class="yv-throbber yv-throbber-${variant}" aria-hidden="true"><svg viewBox="${viewBox}" focusable="false"><ellipse class="yv-throbber-ground" cx="67.8" cy="346.3" rx="61" ry="13.4"/><g class="yv-throbber-halo"><path d="${body}"/><path d="${beak}"/><path class="yv-throbber-halo-legs" d="${legs}"/></g><path class="yv-throbber-track yv-throbber-legs" d="${legs}"/><circle class="yv-throbber-knee" cx="71.5" cy="234.7"/><circle class="yv-throbber-knee" cx="40.3" cy="237"/><path class="yv-throbber-zap" d="${zap}" pathLength="100"/><path class="yv-throbber-track" d="${body}"/><path class="yv-throbber-beak" d="${beak}"/><path class="yv-throbber-beak-tip" d="${beakTip}"/>${runners}<circle class="yv-throbber-eye" cx="120.8" cy="14" r="5"/>${extras}</svg></span>`;
  };

  /**
   * Theme override of the ajax progress indicator for full screen.
   *
   * @return {string}
   *   The HTML markup for the throbber.
   */
  Drupal.theme.ajaxProgressIndicatorFullscreen = () =>
    `<div class="ajax-progress ajax-progress--fullscreen yv-throbber-wrapper">${flamingo('fullscreen')}</div>`;

  /**
   * Theme override of the ajax progress indicator.
   *
   * @param {string} message
   *   The message shown on the UI.
   * @return {string}
   *   The HTML markup for the throbber.
   */
  Drupal.theme.ajaxProgressThrobber = (message) => {
    // Build markup without adding extra white space since it affects rendering.
    const messageMarkup =
      typeof message === 'string'
        ? Drupal.theme('ajaxProgressMessage', message)
        : '';

    return `<div class="ajax-progress ajax-progress--throbber yv-throbber-wrapper">${flamingo('inline')}${messageMarkup}</div>`;
  };
})(Drupal);
