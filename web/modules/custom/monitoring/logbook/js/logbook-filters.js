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
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'logbook-filters-toggle';
          button.setAttribute(
            'aria-controls',
            fields.map((field) => field.id).join(' '),
          );
          // The label gets the focus ring, which hugs the text.
          const label = document.createElement('span');
          label.textContent = Drupal.t('Weitere Filter');
          button.append(label);
          anchor.after(button);

          const setOpen = (open) => {
            form.classList.toggle('logbook-filters--collapsed', !open);
            button.setAttribute('aria-expanded', String(open));
          };
          button.addEventListener('click', () => {
            setOpen(button.getAttribute('aria-expanded') !== 'true');
          });
          // A set filter shows from the start.
          setOpen(
            fields
              .flatMap((field) => [...field.querySelectorAll('select, input')])
              .some((el) => el.value !== '' && el.value !== 'All'),
          );
        },
      );
    },
  };
})(Drupal, once);
