/**
 * @file
 * Ticks off the password rules of the door while typing.
 *
 * The button stays aria-disabled until every rule is met: it can still be
 * focused and points to the checklist, but does not submit. The server checks
 * the rules again, so a browser without this script, or without Web Crypto
 * for the mail address rule, can still submit.
 */
((Drupal, once) => {
  const encoder = new TextEncoder();

  /**
   * Hashes a text with SHA-256, as hex.
   */
  async function sha256(text) {
    const digest = await crypto.subtle.digest('SHA-256', encoder.encode(text));
    return [...new Uint8Array(digest)]
      .map((byte) => byte.toString(16).padStart(2, '0'))
      .join('');
  }

  /**
   * Checks whether the password contains the hashed mail address.
   *
   * Compares every slice of the address' length, case-insensitive.
   */
  async function containsName(password, rule) {
    const lower = password.toLowerCase();
    for (let i = 0; i + rule.length <= lower.length; i++) {
      // Each slice needs its own hash; the loop stops at the first match.
      // eslint-disable-next-line no-await-in-loop
      if ((await sha256(rule.salt + lower.slice(i, i + rule.length))) === rule.hash) {
        return true;
      }
    }
    return false;
  }

  /**
   * Checks one rule: TRUE or FALSE, or NULL if it cannot be checked here.
   */
  async function check(rule, password, confirmation) {
    // Characters as the server counts them, not UTF-16 units.
    const length = [...password].length;
    switch (rule.id) {
      case 'length_min':
        return length >= rule.min;
      case 'length_max':
        return length > 0 && length <= rule.max;
      case 'username':
        if (!window.crypto?.subtle) {
          return null;
        }
        return length > 0 && !(await containsName(password, rule));
      case 'match':
        return length > 0 && password === confirmation;
      default:
        return null;
    }
  }

  Drupal.behaviors.mailerDoorPassword = {
    attach(context, settings) {
      once('mailer-door-password', '.mail-door-rules', context).forEach(
        (list) => {
          const form = list.closest('form');
          const password = form.querySelector('input[name="password[pass1]"]');
          const confirmation = form.querySelector(
            'input[name="password[pass2]"]',
          );
          const submit = form.querySelector('.form-submit');
          const rules = settings.mailerDoorPassword?.rules ?? [];
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

          // Only the latest check may update the list: hashing is async.
          let run = 0;
          const update = async () => {
            run += 1;
            const current = run;
            const results = await Promise.all(
              rules.map((rule) =>
                check(rule, password.value, confirmation.value),
              ),
            );
            if (current !== run) {
              return;
            }
            let met = true;
            rules.forEach((rule, index) => {
              const result = results[index];
              const entry = statuses.get(rule.id);
              if (result === false) {
                met = false;
              }
              if (entry) {
                entry.item.classList.toggle('is-met', result === true);
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
