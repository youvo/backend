/**
 * @file
 * Folds the further filters of the logbook away on narrow screens.
 *
 * The search bar always shows. Type and date range sit behind a button that
 * opens them; they are open from the start when one of them is set. The
 * stylesheet only folds them on narrow screens, and without this script all
 * filters show.
 */
((Drupal, once) => {
  /**
   * Counts the further filters that are set.
   *
   * @param {Element[]} fields
   *   The form items of the further filters.
   *
   * @return {number}
   *   The number of set filters.
   */
  function countActive(fields) {
    return fields
      .flatMap((field) => [...field.querySelectorAll('select, input')])
      .filter((input) => input.value !== '' && input.value !== 'All').length;
  }

  Drupal.behaviors.logbookFilters = {
    attach(context) {
      once('logbook-filters', '.view-logbook .views-exposed-form', context).forEach(
        (form) => {
          const fields = [
            form.querySelector(':scope > .js-form-item-type'),
            form.querySelector(':scope > fieldset'),
          ].filter(Boolean);
          const anchor = form.querySelector(':scope > .js-form-item-tags');
          if (!fields.length || !anchor) {
            return;
          }

          fields.forEach((field, index) => {
            field.id = field.id || `logbook-filters-more-${index}`;
          });
          const active = countActive(fields);
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'logbook-filters-toggle';
          button.setAttribute(
            'aria-controls',
            fields.map((field) => field.id).join(' '),
          );
          const label = document.createElement('span');
          label.textContent = Drupal.t('Weitere Filter');
          button.append(label);
          if (active) {
            const count = document.createElement('span');
            count.className = 'logbook-filters-toggle__count';
            count.textContent = String(active);
            button.append(count);
          }
          anchor.after(button);

          const setOpen = (open) => {
            form.classList.toggle('logbook-filters--collapsed', !open);
            button.setAttribute('aria-expanded', String(open));
          };
          button.addEventListener('click', () => {
            setOpen(button.getAttribute('aria-expanded') !== 'true');
          });
          setOpen(active > 0);
        },
      );
    },
  };
})(Drupal, once);
