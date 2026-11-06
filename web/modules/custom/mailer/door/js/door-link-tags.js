/**
 * @file
 * Sends the chosen project with the suggestions of the door link people.
 *
 * Without text, the recipient and subject fields suggest the people of the
 * chosen project. The Tagify module reads the autocomplete URL of an input on
 * every request, so the project is added to it whenever it changes. The old
 * suggestions are dropped, so the next focus loads the new ones.
 */
((Drupal, once) => {
  Drupal.behaviors.doorLinkTags = {
    attach(context) {
      // The Tagify module loads the suggestions on a click into an empty
      // field; keyboard focus takes the same path.
      once('door-link-tags-focus', 'input[data-door-link-kind]', context).forEach(
        (input) => {
          const tagify = Drupal.tagify.getInstance(input);
          tagify?.on('focus', () => {
            if (!tagify.value.length) {
              tagify.DOM.input.click();
            }
          });
        },
      );

      once(
        'door-link-tags',
        'input[data-door-link-kind="project"]',
        context,
      ).forEach((projectInput) => {
        const project = Drupal.tagify.getInstance(projectInput);
        const form = projectInput.closest('form');
        if (!project || !form) {
          return;
        }

        const update = () => {
          const id = project.value[0]?.entity_id;
          form
            .querySelectorAll(
              'input[data-door-link-kind="recipient"], input[data-door-link-kind="subject"]',
            )
            .forEach((input) => {
              const url = new URL(input.dataset.doorLinkUrl, window.location);
              if (id) {
                url.searchParams.set('project', id);
              }
              input.dataset.autocompleteUrl = url.pathname + url.search;
              const tagify = Drupal.tagify.getInstance(input);
              if (tagify) {
                tagify.whitelist = [];
                tagify.dropdown.hide();
              }
            });
        };

        project.on('add', update);
        project.on('remove', update);
        update();
      });
    },
  };
})(Drupal, once);
