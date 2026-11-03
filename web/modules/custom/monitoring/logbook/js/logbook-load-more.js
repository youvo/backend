/**
 * @file
 * Keeps the place in the logbook when the load more button is clicked.
 *
 * The button is a pager link of Views AJAX, which replaces the whole view
 * with the longer list. After a pager link, Views also scrolls to the top of
 * the view and puts the page into the browser URL. Load more only adds to the
 * list, so both are skipped for it: the URL stays, and the last shown log
 * stays where it was on the screen. Focus goes to the first new log, which
 * would otherwise fall back to the page.
 */
((Drupal, once) => {
  let anchor = null;

  const rows = (view) => view.querySelectorAll('tbody tr');

  // Skips a command of Views AJAX when the load more button sent the request.
  const skipForLoadMore = (name) => {
    const command = Drupal.AjaxCommands.prototype[name];
    Drupal.AjaxCommands.prototype[name] = function skippable(ajax, ...args) {
      if (ajax?.element?.closest?.('.logbook-load-more')) {
        return;
      }
      return command.call(this, ajax, ...args);
    };
  };
  skipForLoadMore('scrollTop');
  skipForLoadMore('setBrowserUrl');

  Drupal.behaviors.logbookLoadMore = {
    attach(context) {
      once('logbook-load-more', '.logbook-load-more a', context).forEach(
        (link) => {
          link.addEventListener('click', () => {
            const shown = rows(link.closest('.view'));
            if (shown.length) {
              anchor = {
                index: shown.length,
                top: shown[shown.length - 1].getBoundingClientRect().top,
              };
            }
          });
        },
      );

      // After the AJAX response, the context is the new view.
      if (!anchor || !(context instanceof Element)) {
        return;
      }
      const view = context.matches('.view')
        ? context
        : context.querySelector('.view');
      const all = view ? rows(view) : [];
      if (all.length < anchor.index) {
        return;
      }
      const last = all[anchor.index - 1];
      window.scrollBy(0, last.getBoundingClientRect().top - anchor.top);
      const next = all[anchor.index];
      if (next) {
        next.setAttribute('tabindex', '-1');
        next.focus({ preventScroll: true });
      }
      anchor = null;
    },
  };
})(Drupal, once);
