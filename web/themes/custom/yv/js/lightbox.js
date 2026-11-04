/**
 * @file
 * Shows the images of a group in a lightbox, with previous and next.
 *
 * A link with data-lightbox opens the image at that address in a native
 * dialog, which brings the focus trap, Escape and the return of the focus.
 * With a modifier key or a middle click, the link opens as usual. The
 * caption comes from data-lightbox-caption. A hint for screen readers in the
 * link, marked with data-lightbox-hint, then names the dialog instead of the
 * new tab the link opens without JavaScript.
 */

((Drupal, once) => {
  /**
   * Creates an element with a class.
   */
  const element = (tag, className) => {
    const el = document.createElement(tag);
    el.className = className;
    return el;
  };

  /**
   * Creates a button of the lightbox.
   */
  const button = (className, label, text) => {
    const el = element('button', `lightbox-button ${className}`);
    el.type = 'button';
    el.setAttribute('aria-label', label);
    el.textContent = text;
    return el;
  };

  Drupal.behaviors.yvLightbox = {
    attach(context) {
      once('yv-lightbox', '[data-lightbox-group]', context).forEach((group) => {
        const links = [...group.querySelectorAll('[data-lightbox]')];
        if (!links.length) {
          return;
        }

        const dialog = element('dialog', 'lightbox');
        dialog.setAttribute('aria-label', Drupal.t('Image preview'));
        const figure = element('figure', 'lightbox-figure');
        const image = element('img', 'lightbox-image');
        image.alt = '';
        const caption = element('figcaption', 'lightbox-caption');
        figure.append(image, caption);
        const close = button('lightbox-close', Drupal.t('Close'), '×');
        const previous = button('lightbox-previous', Drupal.t('Previous image'), '‹');
        const next = button('lightbox-next', Drupal.t('Next image'), '›');
        dialog.append(figure, close);
        if (links.length > 1) {
          dialog.append(previous, next);
        }
        document.body.append(dialog);

        let index = 0;
        const show = (i) => {
          index = (i + links.length) % links.length;
          image.src = links[index].dataset.lightbox;
          caption.textContent = links[index].dataset.lightboxCaption || '';
        };

        links.forEach((link, i) => {
          link.setAttribute('aria-haspopup', 'dialog');
          const hint = link.querySelector('[data-lightbox-hint]');
          if (hint) {
            hint.textContent = ` ${Drupal.t('(opens a preview)')}`;
          }
          link.addEventListener('click', (event) => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
              return;
            }
            event.preventDefault();
            show(i);
            dialog.showModal();
          });
        });
        close.addEventListener('click', () => dialog.close());
        previous.addEventListener('click', () => show(index - 1));
        next.addEventListener('click', () => show(index + 1));
        dialog.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowLeft') {
            show(index - 1);
          }
          if (event.key === 'ArrowRight') {
            show(index + 1);
          }
        });
        // A click on the backdrop lands on the dialog itself.
        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) {
            dialog.close();
          }
        });
        // Without an image, the dialog keeps no stale one for the next opening.
        dialog.addEventListener('close', () => image.removeAttribute('src'));
      });
    },
  };
})(Drupal, once);
