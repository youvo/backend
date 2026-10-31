/**
 * @file
 * Lets the logbook search bar take free text as tags.
 *
 * The Tagify widget only accepts suggestions. The logbook autocomplete always
 * suggests the typed text first, so Enter picks it once the suggestions are
 * there. Before they arrive, Enter would do nothing: this script adds the text
 * as a tag right away. Text tags get a class of their own.
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
   * @param {object} tagify
   *   The Tagify instance.
   * @param {Element} tag
   *   The tag element.
   */
  function markText(tagify, tag) {
    const data = tagify.tagData(tag);
    if (data && String(data.entity_id ?? '').startsWith(TEXT_PREFIX)) {
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

          tagify.getTagElms().forEach((tag) => markText(tagify, tag));
          tagify.on('add', (e) => markText(tagify, e.detail.tag));

          // Capture Enter before Tagify does, but only while no suggestion
          // can be picked: then the typed text becomes a text tag.
          tagify.DOM.scope.addEventListener(
            'keydown',
            (e) => {
              if (e.key !== 'Enter' || tagify.state.dropdown?.visible) {
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
            },
            true,
          );
        },
      );
    },
  };
})(Drupal, once);
