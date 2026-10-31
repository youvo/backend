/**
 * @file
 * Lets the logbook search bar take free text as tags.
 *
 * The Tagify widget only accepts suggestions, and on Enter it picks the
 * highlighted one. While the suggestions still belong to an earlier input,
 * that can be a person or organization the user did not mean. So Enter adds
 * the typed text as a text tag, unless the user chose a suggestion with the
 * arrow keys. With nothing typed, Enter submits the search. Text tags get a
 * class of their own.
 */
((Drupal, once) => {
  const TEXT_PREFIX = 'text:';
  const MAX_TEXT_LENGTH = 100;

  /**
   * Builds the tag data of a text, as the autocomplete does.
   *
   * @param {string} text
   *   The trimmed text.
   *
   * @return {object}
   *   The tag data.
   */
  function textTag(text) {
    const token = `${TEXT_PREFIX}${text}`;
    return {
      value: token,
      entity_id: token,
      label: text,
      info_label: Drupal.t('Text'),
    };
  }

  /**
   * Marks the element of a text tag.
   *
   * @param {Element} tag
   *   The tag element, which carries the tag data as attributes.
   */
  function markText(tag) {
    if ((tag.getAttribute('entity_id') ?? '').startsWith(TEXT_PREFIX)) {
      tag.classList.add('logbook-search__text');
    }
  }

  /**
   * Writes the current tags into the hidden inputs the form submits.
   *
   * Tagify's module writes them on its change event, which fires after a
   * delay: a submit right after adding or removing a tag would miss it.
   *
   * @param {HTMLFormElement} form
   *   The form.
   * @param {string} name
   *   The name of the tags input.
   * @param {object} tagify
   *   The Tagify instance.
   */
  function syncHiddenInputs(form, name, tagify) {
    if (!name) {
      return;
    }
    form
      .querySelectorAll(`input[data-tagify-field="${CSS.escape(name)}"]`)
      .forEach((el) => el.remove());
    tagify.value
      .filter((tag) => tag.entity_id != null)
      .forEach((tag) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = `${name}[]`;
        hidden.dataset.tagifyField = name;
        hidden.value = tag.entity_id;
        form.appendChild(hidden);
      });
  }

  Drupal.behaviors.logbookSearch = {
    attach(context) {
      once('logbook-search', 'input.logbook-search', context).forEach(
        (input) => {
          const tagify = Drupal.tagify.getInstance(input);
          if (!tagify) {
            return;
          }

          // No grey completion of a highlighted suggestion in the bar: Enter
          // adds the typed text, so the hint would promise something else.
          tagify.settings.autoComplete.enabled = false;

          // Tagify names its input in English; use the name of the element.
          tagify.DOM.input.setAttribute(
            'aria-label',
            input.getAttribute('aria-label'),
          );

          tagify.getTagElms().forEach(markText);

          // Whether the user moved through the suggestions since typing.
          let navigated = false;
          // Whether suggestions stay closed: after a tag was added, a late
          // answer of the autocomplete must not open them again.
          let quiet = false;
          tagify.on('input', () => {
            navigated = false;
            quiet = false;
          });
          tagify.on('add', (e) => {
            markText(e.detail.tag);
            quiet = true;
            tagify.dropdown.hide();
          });
          tagify.on('dropdown:show', () => {
            if (quiet) {
              tagify.dropdown.hide();
            }
          });

          // Capture keys before Tagify does.
          tagify.DOM.scope.addEventListener(
            'keydown',
            (e) => {
              if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                navigated = true;
                return;
              }
              if (e.key !== 'Enter' || e.isComposing) {
                return;
              }
              if (navigated && tagify.state.dropdown?.visible) {
                // Let Tagify add the suggestion the user chose.
                return;
              }
              const text = (tagify.state.inputText ?? '')
                .trim()
                .slice(0, MAX_TEXT_LENGTH);
              if (text === '') {
                // Nothing typed: Enter searches, as in a text field.
                const form = input.closest('form');
                if (form) {
                  e.preventDefault();
                  e.stopImmediatePropagation();
                  syncHiddenInputs(form, input.dataset.logbookName, tagify);
                  form.requestSubmit(
                    form.querySelector('.form-actions [type="submit"]') ??
                      undefined,
                  );
                }
                return;
              }
              e.preventDefault();
              e.stopImmediatePropagation();
              const data = textTag(text);
              const known = tagify.whitelist ?? [];
              if (!known.some((item) => item.value === data.value)) {
                // Tagify only adds tags it knows of.
                tagify.whitelist = [...known, data];
              }
              tagify.addTags([data], true);
              tagify.dropdown.hide();
              navigated = false;
            },
            true,
          );
        },
      );
    },
  };
})(Drupal, once);
