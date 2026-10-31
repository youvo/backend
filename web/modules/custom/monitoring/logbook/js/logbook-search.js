/**
 * @file
 * Lets the logbook search bar take free text as tags.
 *
 * The Tagify element only accepts suggestions and highlights the first one.
 * Here no suggestion is highlighted until the user moves to it, so Enter adds
 * the typed text, which becomes a text tag. With nothing typed, Enter submits
 * the search.
 */
((Drupal, once) => {
  const TEXT_PREFIX = 'text:';

  Drupal.behaviors.logbookSearch = {
    attach(context) {
      once('logbook-search', 'input.logbook-search', context).forEach(
        (input) => {
          const tagify = Drupal.tagify.getInstance(input);
          if (!tagify) {
            return;
          }

          Object.assign(tagify.settings, {
            enforceWhitelist: false,
            // Typed text becomes a text tag, as the autocomplete offers it.
            transformTag(data) {
              if (data.entity_id == null) {
                const text = String(data.value).trim();
                Object.assign(data, {
                  value: `${TEXT_PREFIX}${text}`,
                  entity_id: `${TEXT_PREFIX}${text}`,
                  label: text,
                  info_label: Drupal.t('Text'),
                });
              }
              if (String(data.entity_id).startsWith(TEXT_PREFIX)) {
                data.class = 'logbook-search__text';
              }
            },
          });
          tagify.settings.dropdown.highlightFirst = false;
          // No grey completion of a suggestion: Enter adds the typed text.
          tagify.settings.autoComplete.enabled = false;

          // Tagify names its input in English; use the name of the element.
          tagify.DOM.input.setAttribute(
            'aria-label',
            input.getAttribute('aria-label'),
          );

          // After a tag was added, a late answer of the autocomplete to the
          // earlier input must not open the suggestions again.
          let quiet = false;
          tagify.on('input', () => {
            quiet = false;
          });
          tagify.on('add', () => {
            quiet = true;
          });
          tagify.on('dropdown:show', () => {
            if (quiet) {
              tagify.dropdown.hide();
            }
          });

          tagify.DOM.input.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || e.isComposing) {
              return;
            }
            // Tagify cancels Enter only after a promise; Firefox would put a
            // line break into the input by then, which Tagify takes as a tag.
            e.preventDefault();
            const form = input.closest('form');
            // With nothing typed, Enter searches, as in a text field.
            if (form && !tagify.state.inputText?.trim()) {
              // The Tagify module writes the submitted tags on change, which
              // Tagify reports late: update the value and report it now.
              tagify.update();
              tagify.triggerChangeEvent();
              form.requestSubmit(
                form.querySelector('.form-actions [type="submit"]') ??
                  undefined,
              );
            }
          });
        },
      );
    },
  };
})(Drupal, once);
