/**
 * @file
 * Loads the list of all events into the dropdown of a project card.
 *
 * The dashboard does not render the events of every project. The list is
 * requested when a manager opens a dropdown, once per card.
 */
((Drupal, once) => {
  Drupal.behaviors.managerProjectsEvents = {
    attach(context) {
      once(
        'manager-projects-events',
        'details[data-events-url]',
        context
      ).forEach(details => {
        const body = details.querySelector(
          '.manager-projects__all-events-body'
        );
        if (!body) {
          return;
        }
        // Without JavaScript the body holds a link to the logbook.
        const fallback = body.innerHTML;
        let loaded = false;

        details.addEventListener('toggle', () => {
          if (!details.open || loaded) {
            return;
          }
          loaded = true;
          body.setAttribute('aria-busy', 'true');
          body.textContent = Drupal.t('Loading…');
          fetch(details.dataset.eventsUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
          })
            .then(response => {
              // A redirect means the session ended: the answer is a login page.
              if (!response.ok || response.redirected) {
                throw new Error(`Status ${response.status}`);
              }
              return response.text();
            })
            .then(html => {
              // The list is rendered by Drupal and escaped there.
              body.innerHTML = html;
              Drupal.attachBehaviors(body);
            })
            .catch(() => {
              // Opening the dropdown again tries again.
              loaded = false;
              body.innerHTML = `<p class="manager-projects__events-note">${Drupal.t('The events could not be loaded.')}</p>${fallback}`;
            })
            .finally(() => {
              body.removeAttribute('aria-busy');
            });
        });
      });
    },
  };
})(Drupal, once);
