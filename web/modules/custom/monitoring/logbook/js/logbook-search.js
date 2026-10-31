/**
 * @file
 * Lets the logbook search bar take free text as tags.
 *
 * The Tagify widget only accepts suggestions, and on Enter it picks the
 * highlighted one. While the suggestions still belong to an earlier input,
 * that can be a person or organization the user did not mean. So Enter adds
 * the typed text as a text tag, unless the user chose a suggestion with the
 * arrow keys. Text tags get a class of their own.
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

  Drupal.behaviors.logbookSearch = {
    attach(context) {
      once('logbook-search', 'input.logbook-search', context).forEach(
        (input) => {
          const tagify = Drupal.tagify.getInstance(input);
          if (!tagify) {
            return;
          }

          tagify.getTagElms().forEach(markText);
          tagify.on('add', (e) => markText(e.detail.tag));

          // Whether the user moved through the suggestions since typing.
          let navigated = false;
          tagify.on('input', () => {
            navigated = false;
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
