/**
 * @file
 * Development only: a switch for Gin's dark mode in the top bar.
 *
 * The choice is kept in the browser (localStorage). Gin itself only reads the
 * dark mode setting of the user, so the class is set here, before the page is
 * painted, and again when the page has loaded.
 */

(function (Drupal, once) {
  const KEY = 'manager.devDarkMode';
  const CLASS = 'gin--dark-mode';

  const isDark = () => localStorage.getItem(KEY) === '1';
  const apply = () =>
    document.documentElement.classList.toggle(CLASS, isDark());

  if (isDark()) apply();
  document.addEventListener('DOMContentLoaded', apply);

  Drupal.behaviors.managerDevDarkMode = {
    attach(context) {
      once('dev-dark-mode', '.top-bar__actions', context).forEach(actions => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dev-dark-mode-switch';
        const update = () => {
          const dark = isDark();
          button.setAttribute('aria-pressed', dark ? 'true' : 'false');
          button.title = Drupal.t('Dark mode (development only)');
          button.textContent = dark ? '\u263E' : '\u2600';
        };
        button.addEventListener('click', () => {
          localStorage.setItem(KEY, isDark() ? '0' : '1');
          apply();
          update();
        });
        update();
        actions.prepend(button);
      });
    },
  };
})(Drupal, once);
