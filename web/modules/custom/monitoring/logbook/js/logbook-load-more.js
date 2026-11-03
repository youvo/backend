/**
 * @file
 * Keeps the place in the logbook on load more.
 *
 * Views AJAX replaces the whole view, scrolls to its top and changes the URL.
 * Load more only appends, so scroll and URL change are skipped and the last
 * shown log stays in place. Focus moves to the first new log; it would
 * otherwise fall back to the page.
 */
((Drupal, once) => {
  let anchor = null;

  const rows = (view) =>
    view.querySelectorAll('tbody tr, .view-content > :not(table)');

  // Views keeps the DOM id when it replaces a view, so it ties the saved
  // place to that view, not to any other AJAX update on the page.
  const domId = (view) =>
    [...view.classList].find((name) => name.startsWith('js-view-dom-id-'));

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
            const view = link.closest('.view');
            const shown = rows(view);
            anchor = shown.length
              ? {
                  view: domId(view),
                  index: shown.length,
                  top: shown[shown.length - 1].getBoundingClientRect().top,
                }
              : null;
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
      if (!view || domId(view) !== anchor.view) {
        return;
      }
      const all = rows(view);
      const { index, top } = anchor;
      anchor = null;
      if (all.length < index) {
        return;
      }
      const last = all[index - 1];
      window.scrollBy(0, last.getBoundingClientRect().top - top);
      const next = all[index];
      if (next) {
        next.setAttribute('tabindex', '-1');
        next.focus({ preventScroll: true });
      }
    },
  };
})(Drupal, once);
