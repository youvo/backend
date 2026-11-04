/**
 * @file
 * Opens the changes card at the revision the URL fragment names.
 *
 * The logbook links a change to #revision-<id> on the project page.
 */
((Drupal, once) => {
  Drupal.behaviors.projectChanges = {
    attach(context) {
      once('project-changes', '[data-project-changes]', context).forEach(
        (card) => {
          if (!/^#revision-\d+$/.test(window.location.hash)) {
            return;
          }
          card.open = true;
          const target = card.querySelector(window.location.hash);
          // The revision may be beyond the listed ones or deleted.
          if (!target) {
            card.scrollIntoView();
            return;
          }
          let element = target.parentElement;
          while (element && element !== card) {
            if (element.tagName === 'DETAILS') {
              element.open = true;
            }
            element = element.parentElement;
          }
          target.classList.add('is-target');
          target.scrollIntoView({ block: 'center' });
        },
      );
    },
  };
})(Drupal, once);
