/**
 * @file
 * Shows the older project changes and opens the card at a linked revision.
 *
 * The logbook links a change to #revision-<id> on the project page. A link on
 * the page itself only changes the hash, so the hash change is handled too.
 */
((Drupal, once) => {
  /**
   * Shows the hidden older entries and removes their button.
   */
  function showOlder(card) {
    card.querySelectorAll('.project-changes-entry[hidden]').forEach((entry) => {
      entry.hidden = false;
    });
    card.querySelector('[data-project-changes-more]')?.remove();
  }

  /**
   * Opens the card and highlights the entry of the current hash.
   */
  function showRevision(card) {
    if (!/^#revision-\d+$/.test(window.location.hash)) {
      return;
    }
    card.open = true;
    card.querySelectorAll('.is-target').forEach((entry) => {
      entry.classList.remove('is-target');
    });
    const target = card.querySelector(window.location.hash);
    // The revision may be beyond the listed ones or deleted.
    if (!target) {
      card.scrollIntoView();
      return;
    }
    if (target.hidden) {
      showOlder(card);
    }
    target.classList.add('is-target');
    target.scrollIntoView({ block: 'center' });
  }

  Drupal.behaviors.projectChanges = {
    attach(context) {
      once('project-changes', '[data-project-changes]', context).forEach(
        (card) => {
          card
            .querySelector('[data-project-changes-more]')
            ?.addEventListener('click', () => showOlder(card));
          showRevision(card);
          window.addEventListener('hashchange', () => showRevision(card));
        },
      );
    },
  };
})(Drupal, once);
