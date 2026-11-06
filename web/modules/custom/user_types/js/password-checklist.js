/**
 * @file
 * Ticks off the password rules below a new password while typing.
 *
 * Used by the mail door and the creative registration, see PasswordChecklist.
 *
 * The button stays aria-disabled until every rule is met: it can still be
 * focused and points to the checklist, but does not submit. The server checks
 * the rules again, so a browser without this script can still submit.
 */
((Drupal, once) => {
  /**
   * Checks one rule: TRUE or FALSE, or NULL if it cannot be checked here.
   */
  function check(rule, password, confirmation) {
    // Characters as the server counts them, not UTF-16 units.
    const length = [...password].length;
    switch (rule.id) {
      case 'length_min':
        return length >= rule.min;
      case 'length_max':
        return length > 0 && length <= rule.max;
      case 'match':
        return length > 0 && password === confirmation;
      default:
        return null;
    }
  }

  Drupal.behaviors.passwordChecklist = {
    attach(context, settings) {
      once('password-checklist', '.password-checklist', context).forEach(
        (list) => {
          const form = list.closest('form');
          const password = form.querySelector('input[name="password[pass1]"]');
          const confirmation = form.querySelector(
            'input[name="password[pass2]"]',
          );
          const submit = form.querySelector('.form-submit');
          const rules = settings.passwordChecklist?.rules ?? [];
          if (!password || !confirmation || !submit) {
            return;
          }

          // The status of each rule, for screen readers.
          const statuses = new Map();
          rules.forEach((rule) => {
            const item = list.querySelector(`[data-rule="${rule.id}"]`);
            if (item) {
              const status = document.createElement('span');
              status.className = 'visually-hidden';
              item.append(' ', status);
              statuses.set(rule.id, { item, status });
            }
          });
          submit.setAttribute('aria-describedby', list.id);

          const update = () => {
            let met = true;
            rules.forEach((rule) => {
              const result = check(rule, password.value, confirmation.value);
              const entry = statuses.get(rule.id);
              if (result === false) {
                met = false;
              }
              if (entry) {
                entry.item.toggleAttribute('data-met', result === true);
                entry.status.textContent =
                  result === true ? '(erfüllt)' : '(noch nicht erfüllt)';
              }
            });
            submit.setAttribute('aria-disabled', met ? 'false' : 'true');
          };

          password.addEventListener('input', update);
          confirmation.addEventListener('input', update);
          update();

          // Enter in a field submits as well as a click on the button.
          form.addEventListener('submit', (event) => {
            if (submit.getAttribute('aria-disabled') === 'true') {
              event.preventDefault();
              event.stopImmediatePropagation();
            }
          });
        },
      );
    },
  };
})(Drupal, once);
