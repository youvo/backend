// Context Pane JS for Project Manager
// Handles dynamic context panes in project tables

let lastScrollPosition = null;

const SELECTORS = {
  contextTable: 'table',
  contextPane: '.context-pane',
  contextTrigger: '[data-context-pane][data-project-id]',
  transitionCheckbox: '.js-transition-checkbox',
  promoteButton: '.js-promote-btn',
  transitionButton: '.js-transition-btn',
};

function showLoadingRow(afterRow, type) {
  const loadingRow = document.createElement('tr');
  loadingRow.className = 'loading-pane js-context-pane';
  loadingRow.dataset.contextType = type;
  loadingRow.innerHTML = `
    <td colspan="100%">
      <span class="sliding-bar-loader">
        <span class="sliding-bar"></span>
      </span>
    </td>
  `;
  afterRow.insertAdjacentElement('afterend', loadingRow);
  return loadingRow;
}

/**
 * Swaps a pane row with the first row of the given HTML.
 *
 * If the row was removed from the page while the request was in flight (the
 * pane was closed), the current pane row of the same project and type is
 * swapped instead, so the result of the request is still shown.
 *
 * @return {?Element}
 *   The new row, or NULL if the HTML contained no row or there is no row to
 *   swap (nothing swapped).
 */
function swapPaneRow(row, html, id, type) {
  const temp = document.createElement('tbody');
  temp.innerHTML = html;
  const newRow = temp.querySelector('tr');
  if (!newRow) return null;
  const target = row.isConnected
    ? row
    : document.querySelector(
        `.js-context-pane[data-project-id="${id}"][data-context-type="${type}"]`
      );
  if (!target) return null;
  newRow.classList.add('js-context-pane');
  newRow.dataset.contextType = type;
  newRow.dataset.projectId = id;
  target.replaceWith(newRow);
  Drupal.attachBehaviors(newRow);
  return newRow;
}

/**
 * Whether a pane request (navigation or submit) is in flight.
 */
function paneRequestPending() {
  return !!document.querySelector(
    '.js-context-pane[data-pane-busy], .js-context-pane form[data-pane-busy]'
  );
}

async function loadAndReplaceRow(row, id, type) {
  try {
    const response = await fetch(`/project/manage/context/${id}?type=${type}`);
    const html = await response.text();
    const newRow = swapPaneRow(row, html, id, type);
    if (newRow) {
      if (type === 'transition') {
        initCheckboxHandlers(newRow);
      }
    } else {
      row.outerHTML = html;
      if (type === 'transition') {
        initCheckboxHandlers(document);
      }
    }
  } catch (e) {}
}

/**
 * Moves focus into a freshly swapped pane row (after in-pane navigation and
 * submits only): to the first invalid field, else to the error message, else to the first
 * form control, else (no form, e.g. after a successful send, save or delete)
 * to the status message, else to the control the pane marks with
 * data-pane-autofocus (the add row after a cancel), else to the container.
 */
function focusPaneRow(row) {
  const target =
    row.querySelector('[aria-invalid="true"]') ||
    row.querySelector('.pane-message-error') ||
    row.querySelector('input:not([type="hidden"]), textarea, select') ||
    row.querySelector('[data-pane-autofocus]') ||
    row.querySelector('td > div') ||
    row.querySelector('td');
  if (!target) return;
  if (!target.matches('input, textarea, select, button, a[href]')) {
    target.setAttribute('tabindex', '-1');
  }
  target.focus();
}

function showPaneError(row, message) {
  let box = row.querySelector('.js-pane-error');
  if (!box) {
    const container = row.querySelector('td > div') || row.querySelector('td');
    if (!container) return;
    const wrap = document.createElement('div');
    wrap.className = 'pane-messages';
    wrap.innerHTML = `
      <div class="js-pane-error pane-message pane-message-error" role="alert" data-pane-message>
        <span class="pane-message-icon" aria-hidden="true"></span>
        <span class="pane-message-text"></span>
        <button type="button" class="pane-message-close" aria-label="${Drupal.t('Dismiss')}"><span aria-hidden="true">&times;</span></button>
      </div>`;
    box = wrap.firstElementChild;
    container.prepend(wrap);
  }
  box.querySelector('.pane-message-text').textContent = message;
}

/**
 * Removes a notice (with a short fade), and its wrapper when it was the last.
 */
function dismissPaneMessage(message) {
  if (!message || message.dataset.leaving) return;
  message.dataset.leaving = '1';
  const remove = () => {
    const wrap = message.parentElement;
    message.remove();
    if (
      wrap &&
      wrap.classList.contains('pane-messages') &&
      !wrap.children.length
    ) {
      wrap.remove();
    }
  };
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    remove();
    return;
  }
  message.classList.add('is-leaving');
  window.setTimeout(remove, 200);
}

/**
 * Success notices go away by themselves; hovering one keeps it. Errors and
 * warnings stay until they are dismissed.
 */
function initPaneMessages(context) {
  once('pane-message', '.pane-message-status', context).forEach(message => {
    let timer;
    const start = delay => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => dismissPaneMessage(message), delay);
    };
    message.addEventListener('mouseenter', () => window.clearTimeout(timer));
    message.addEventListener('mouseleave', () => start(2000));
    start(5000);
  });
}

/**
 * Reads a typed date (d.m.yy, dd.mm.yyyy; . / - or, not strict, nothing between
 * the parts) as YYYY-MM-DD; a two digit year is a year of this century.
 *
 * @return {?string}
 *   The ISO date, or NULL if the text is no real date.
 */
function parseNoteDate(text, strict = false) {
  // Typing is strict (the parts need a separator): a half typed 31.02 must not
  // be read as 3.1.02. Compact entries (040326) are read when the editor closes.
  const sep = strict ? '[./\\- ]' : '[./\\- ]?';
  const match = new RegExp(
    `^(\\d{1,2})${sep}(\\d{1,2})${sep}(\\d{2}|\\d{4})$`
  ).exec(text.trim());
  if (!match) return null;
  const day = Number(match[1]);
  const month = Number(match[2]);
  const year =
    match[3].length === 2 ? 2000 + Number(match[3]) : Number(match[3]);
  const check = new Date(Date.UTC(year, month - 1, day));
  if (
    check.getUTCFullYear() !== year ||
    check.getUTCMonth() !== month - 1 ||
    check.getUTCDate() !== day ||
    year < 1970 ||
    year > 2037
  ) {
    return null;
  }
  const pad = n => String(n).padStart(2, '0');
  return `${year}-${pad(month)}-${pad(day)}`;
}

/**
 * Reads a typed time (h:mm, hh:mm, hhmm) as HH:MM.
 *
 * @return {?string}
 *   The time, or NULL if the text is no time.
 */
function parseNoteTime(text, strict = false) {
  const match = new RegExp(
    `^(\\d{1,2})${strict ? '[:.]' : '[:.]?'}(\\d{2})$`
  ).exec(text.trim());
  if (!match || Number(match[1]) > 23 || Number(match[2]) > 59) return null;
  return `${match[1].padStart(2, '0')}:${match[2]}`;
}

/**
 * Date and time of the add note form on wide screens: shown as text, like the
 * dates of the other timeline entries; a click opens a compact editor (dd.mm.yy
 * and hh:mm, each with a button for the picker of the system). The real date
 * and time fields stay in the form as they are, hidden underneath; on narrow
 * screens they are shown and used directly.
 */
function initNoteWhen(context) {
  once(
    'note-when',
    '.logbook-timeline-add-form .form-datetime-wrapper',
    context
  ).forEach(wrapper => {
    const date = wrapper.querySelector('input[type="date"]');
    const time = wrapper.querySelector('input[type="time"]');
    if (!date || !time) return;

    const wide = window.matchMedia('(min-width: 801px)');
    // Without the script (or on narrow screens) the fields are used as they are.
    const natives = [date, time];
    const applyMode = () => {
      natives.forEach(field => {
        if (wide.matches) {
          field.setAttribute('tabindex', '-1');
          field.setAttribute('aria-hidden', 'true');
        } else {
          field.removeAttribute('tabindex');
          field.removeAttribute('aria-hidden');
        }
      });
    };
    wide.addEventListener('change', applyMode);
    applyMode();

    // The closed state: the date and time as text.
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'note-when';
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = '<span></span><span></span>';
    const [dateText, timeText] = button.children;

    // The editor: a text field and a picker button for each of the two.
    const editor = document.createElement('div');
    editor.className = 'note-when-editor';
    const field = (kind, label, placeholder, pickerLabel) => {
      const box = document.createElement('span');
      box.className = `note-when-field note-when-${kind}`;
      const input = document.createElement('input');
      input.type = 'text';
      input.className = 'note-when-input';
      input.inputMode = 'numeric';
      input.autocomplete = 'off';
      input.placeholder = placeholder;
      input.setAttribute('aria-label', label);
      const picker = document.createElement('button');
      picker.type = 'button';
      picker.className = 'note-when-picker';
      picker.setAttribute('aria-label', pickerLabel);
      box.append(input, picker);
      editor.append(box);
      return { input, picker };
    };
    const dateField = field(
      'date',
      Drupal.t('Date'),
      Drupal.t('dd.mm.yy'),
      Drupal.t('Choose a date')
    );
    const timeField = field(
      'time',
      Drupal.t('Time'),
      Drupal.t('hh:mm'),
      Drupal.t('Choose a time')
    );
    wrapper.append(editor);
    wrapper.classList.add('is-editor');
    wrapper.before(button);

    const formatDate = iso => {
      const [y, m, d] = iso.split('-');
      return y ? `${d}.${m}.${y.slice(2)}` : '';
    };
    // From the real fields to the chip and the editor. A text box that is being
    // typed in is left alone, unless the editor is closing.
    const show = (force = false) => {
      dateText.textContent = date.value || '----------';
      timeText.textContent = time.value ? time.value.slice(0, 5) : '--:--';
      button.setAttribute(
        'aria-label',
        `${Drupal.t('Change date and time')}: ${dateText.textContent} ${timeText.textContent}`
      );
      [
        [dateField.input, formatDate(date.value)],
        [timeField.input, time.value.slice(0, 5)],
      ].forEach(([input, text]) => {
        const typing =
          document.activeElement === input ||
          input.getAttribute('aria-invalid') === 'true';
        if (force || !typing) {
          input.value = text;
          input.removeAttribute('aria-invalid');
        }
      });
    };
    show(true);

    // Typing: a valid entry goes straight to the real field, otherwise the
    // field is marked and the real one keeps its last good value.
    const commit = (input, parse, real, strict = true) => {
      const value = parse(input.value, strict);
      if (value) {
        real.value = value;
        input.removeAttribute('aria-invalid');
        show();
      } else if (input.value.trim() !== '') {
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
    };
    dateField.input.addEventListener('input', () =>
      commit(dateField.input, parseNoteDate, date)
    );
    timeField.input.addEventListener('input', () =>
      commit(timeField.input, parseNoteTime, time)
    );
    // The system pickers write to the real fields.
    [
      [dateField.picker, date],
      [timeField.picker, time],
    ].forEach(([picker, real]) => {
      picker.addEventListener('click', () => {
        try {
          real.showPicker();
        } catch (e) {
          real.focus();
        }
      });
    });
    wrapper.addEventListener('change', e => {
      if (natives.includes(e.target)) show(true);
    });

    const open = () => {
      wrapper.classList.add('is-open');
      button.setAttribute('aria-expanded', 'true');
      dateField.input.focus();
      dateField.input.select();
    };
    const close = refocus => {
      if (!wrapper.classList.contains('is-open')) return;
      // Compact entries (040326, 0905) are read now.
      commit(dateField.input, parseNoteDate, date, false);
      commit(timeField.input, parseNoteTime, time, false);
      wrapper.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
      // Half typed text goes back to the last good value.
      show(true);
      if (refocus) button.focus();
    };

    button.addEventListener('click', open);
    wrapper.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        // Closes the editor, not the whole form.
        e.stopPropagation();
        e.preventDefault();
        close(true);
      } else if (e.key === 'Enter' && !e.metaKey && !e.ctrlKey) {
        // Done with the date, not a send of the form.
        e.preventDefault();
        close(true);
      }
    });
    // Tabbing on to another control closes the editor; so does a click outside.
    wrapper.addEventListener('focusout', e => {
      if (e.relatedTarget && !wrapper.contains(e.relatedTarget)) close(false);
    });
    document.addEventListener('mousedown', e => {
      if (!wrapper.isConnected) return;
      if (!wrapper.contains(e.target) && !button.contains(e.target)) {
        close(false);
      }
    });
  });
}

/**
 * Keeps each pane as wide as the visible part of its (horizontally scrolling)
 * table, so it is not cut off on narrow screens.
 */
function syncPaneWidths() {
  document.querySelectorAll('.js-context-pane').forEach(row => {
    const wrap =
      row.closest('.gin-table-scroll-wrapper') ||
      (row.closest('table') && row.closest('table').parentElement);
    const cell = row.firstElementChild;
    if (!wrap || !cell) return;
    const style = getComputedStyle(cell);
    const left = parseFloat(style.paddingLeft) || 0;
    const right = parseFloat(style.paddingRight) || 0;
    row.style.setProperty(
      '--pane-width',
      `${Math.max(wrap.clientWidth - left - right, 0)}px`
    );
    row.style.setProperty('--pane-left', `${left}px`);
  });
}

/**
 * Brings the note that was just saved into view; it pulses in the timeline.
 */
function revealNewRow(context) {
  once('reveal-new', '.logbook-timeline-flex-row.is-new', context).forEach(
    row => row.scrollIntoView({ block: 'nearest' })
  );
}

function paneErrorText(detail) {
  // Plain text: the box is filled via textContent, so no placeholder escaping.
  return `${Drupal.t('The request failed. Please try again.')} (${detail})`;
}

/**
 * Loads a pane view into its row (in-pane navigation).
 */
async function loadPaneView(row, view, params) {
  const id = row.dataset.projectId;
  const type = row.dataset.contextType;
  if (!id || !type || row.dataset.paneBusy) return;
  const query = new URLSearchParams({ type, view });
  new URLSearchParams(params || '').forEach((value, key) => {
    query.append(key, value);
  });
  row.dataset.paneBusy = '1';
  try {
    const response = await fetch(
      `/project/manage/context/${encodeURIComponent(id)}?${query}`
    );
    const html = await response.text();
    const newRow = response.ok ? swapPaneRow(row, html, id, type) : null;
    if (newRow) {
      focusPaneRow(newRow);
    } else {
      delete row.dataset.paneBusy;
      showPaneError(row, paneErrorText(`HTTP ${response.status}`));
    }
  } catch (e) {
    delete row.dataset.paneBusy;
    showPaneError(row, paneErrorText(e.message));
  }
}

/**
 * Posts a pane form with fetch and swaps the row with the response.
 */
async function submitPaneForm(form, submitter) {
  const row = form.closest('.js-context-pane');
  if (!row || form.dataset.paneBusy) return;
  const id = row.dataset.projectId;
  const type = row.dataset.contextType;
  const body = new FormData(form);
  // FormData does not include the clicked submit button; the form API needs
  // its name/value ('op') to find the triggering element.
  if (submitter && submitter.name) {
    body.append(submitter.name, submitter.value);
  }
  const buttons = form.querySelectorAll('[type="submit"]');
  form.dataset.paneBusy = '1';
  row.dataset.paneBusy = '1';
  buttons.forEach(button => {
    button.disabled = true;
  });
  const fail = detail => {
    delete form.dataset.paneBusy;
    delete row.dataset.paneBusy;
    buttons.forEach(button => {
      button.disabled = false;
    });
    showPaneError(row, paneErrorText(detail));
  };
  try {
    const response = await fetch(form.action, { method: 'POST', body });
    const html = await response.text();
    const newRow = response.ok ? swapPaneRow(row, html, id, type) : null;
    if (newRow) {
      focusPaneRow(newRow);
    } else {
      fail(`HTTP ${response.status}`);
    }
  } catch (e) {
    fail(e.message);
  }
}

function initPaneForms() {
  if (window._paneFormHandlersAttached) return;
  window._paneFormHandlersAttached = true;

  document.body.addEventListener('submit', e => {
    const form = e.target.closest
      ? e.target.closest('form.manager-pane-form')
      : null;
    if (!form || !form.closest('.js-context-pane')) return;
    e.preventDefault();
    submitPaneForm(form, e.submitter);
  });

  // Browsers without field-sizing: grow the note field with its text.
  if (!(window.CSS && CSS.supports('field-sizing', 'content'))) {
    document.body.addEventListener('input', e => {
      const area = e.target;
      if (!area.matches || !area.matches('.logbook-timeline-add-form textarea'))
        return;
      area.style.height = 'auto';
      area.style.height = `${area.scrollHeight + 1}px`;
    });
  }

  // Closing the inline add form never throws away a written note by accident:
  // with text in it, the first Esc or Cancel only asks, the second one closes.
  const NOTE_FORM = '.logbook-timeline-add-form form.manager-pane-form';
  const discardArmed = form => form.dataset.discardArmed === '1';
  const disarmDiscard = form => {
    const cancel = form.querySelector('a[data-pane-view]');
    delete form.dataset.discardArmed;
    if (cancel && cancel.dataset.label) {
      cancel.textContent = cancel.dataset.label;
      cancel.classList.remove('is-armed');
    }
  };
  // Returns TRUE if the form may be closed now.
  const mayClose = form => {
    const written = ['subject', 'body'].some(name => {
      const field = form.elements[name];
      return field && field.value.trim() !== '';
    });
    if (!written || discardArmed(form)) return true;
    const cancel = form.querySelector('a[data-pane-view]');
    form.dataset.discardArmed = '1';
    if (cancel) {
      cancel.dataset.label = cancel.textContent;
      cancel.textContent = Drupal.t('Discard note?');
      cancel.classList.add('is-armed');
    }
    return false;
  };
  document.body.addEventListener('input', e => {
    const form = e.target.closest ? e.target.closest(NOTE_FORM) : null;
    if (form && discardArmed(form)) disarmDiscard(form);
  });
  // Back in a field (or another control): the question is no longer asked.
  document.body.addEventListener('focusin', e => {
    const form = e.target.closest ? e.target.closest(NOTE_FORM) : null;
    if (form && discardArmed(form) && !e.target.matches('a[data-pane-view]')) {
      disarmDiscard(form);
    }
  });
  // Capture phase: runs before the in-pane navigation of the Cancel link.
  document.addEventListener(
    'click',
    e => {
      const cancel = e.target.closest
        ? e.target.closest(`${NOTE_FORM} a[data-pane-view]`)
        : null;
      if (cancel && !mayClose(cancel.closest('form'))) {
        e.preventDefault();
        e.stopPropagation();
      }
    },
    true
  );

  // Inside the inline add form: Esc cancels, Cmd/Ctrl+Enter saves.
  document.body.addEventListener('keydown', e => {
    const form = e.target.closest
      ? e.target.closest('.logbook-timeline-add-form form.manager-pane-form')
      : null;
    if (!form) return;
    if (e.key === 'Escape') {
      const cancel = form.querySelector('a[data-pane-view]');
      if (cancel) {
        e.preventDefault();
        cancel.click();
      }
    } else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
      e.preventDefault();
      form.requestSubmit(form.querySelector('[type="submit"]'));
    }
  });

  document.body.addEventListener('click', e => {
    const close = e.target.closest('.pane-message-close');
    if (close) {
      dismissPaneMessage(close.closest('[data-pane-message]'));
      return;
    }
    const link = e.target.closest('[data-pane-view]');
    if (!link) return;
    const row = link.closest('.js-context-pane');
    if (!row) return;
    e.preventDefault();
    loadPaneView(
      row,
      link.getAttribute('data-pane-view'),
      link.getAttribute('data-pane-params')
    );
  });
}

function closeContextPane() {
  document.querySelectorAll(SELECTORS.contextPane).forEach(r => r.remove());
}

function initContextPane(context) {
  once(
    'manager-context-pane',
    SELECTORS.contextTable,
    context || document
  ).forEach(table => {
    table.addEventListener('click', async e => {
      const trigger = e.target.closest(SELECTORS.contextTrigger);
      if (!trigger) return;
      e.stopPropagation();
      const id = trigger.getAttribute('data-project-id');
      const type = trigger.getAttribute('data-context-pane');
      if (!id || !type || paneRequestPending()) return;
      const row = trigger.closest('tr');
      if (!row) return;
      const nextRow = row.nextElementSibling;
      if (nextRow && nextRow.classList.contains('js-context-pane')) {
        if (nextRow.dataset.contextType === type) {
          nextRow.remove();
          return;
        }
        nextRow.innerHTML = `
          <td colspan="100%">
            <span class="sliding-bar-loader">
              <span class="sliding-bar"></span>
            </span>
          </td>
        `;
        nextRow.className = 'loading-pane context-pane';
        await loadAndReplaceRow(nextRow, id, type);
        return;
      }
      closeContextPane();
      const loadingRow = showLoadingRow(row, type);
      await loadAndReplaceRow(loadingRow, id, type);
    });
  });

  // Close context pane when clicking outside.
  if (window._managerBodyClickAttached) return;
  window._managerBodyClickAttached = true;
  document.body.addEventListener('click', e => {
    if (
      paneRequestPending() ||
      e.target.closest(SELECTORS.contextPane) ||
      e.target.closest(SELECTORS.contextTrigger) ||
      e.target.closest(SELECTORS.contextTable)
    ) {
      return;
    }
    closeContextPane();
  });
}

async function getCsrfToken() {
  const response = await fetch('/session/token');
  if (!response.ok) throw new Error('Failed to fetch CSRF token');
  return response.text();
}

function initPromoteButton() {
  if (window._promoteBtnHandlerAttached) return;
  window._promoteBtnHandlerAttached = true;

  document.body.addEventListener('click', async function (e) {
    const btn = e.target.closest('.js-promote-btn');
    if (!btn) return;
    const row = btn.closest('.js-context-pane');
    if (!row) return;
    const projectId = row.getAttribute('data-project-id');
    if (!projectId) return;
    btn.disabled = true;
    try {
      lastScrollPosition = window.scrollY;
      const csrfToken = await getCsrfToken();
      const action = btn.getAttribute('data-action') || 'promote';
      const response = await fetch(`/api/projects/${projectId}/${action}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
      });
      if (response.status === 200) {
        document
          .querySelectorAll('.view-project-manager')
          .forEach(function (el) {
            el.dispatchEvent(new CustomEvent('RefreshView', { bubbles: true }));
          });
      } else if (response.status === 403) {
        alert('You do not have permission to promote/demote this project.');
        btn.disabled = false;
      }
    } catch (err) {
      btn.disabled = false;
    }
  });
}

function initCheckboxHandlers(context) {
  const checkboxes = (context || document).querySelectorAll(
    SELECTORS.transitionCheckbox
  );
  if (checkboxes.length === 0) {
    (context || document)
      .querySelectorAll('.js-transition-btn[data-action="mediate"]')
      .forEach(btn => {
        btn.disabled = true;
      });
    return;
  }

  checkboxes.forEach(checkbox => {
    const row = checkbox.closest('.js-context-pane');
    if (row) {
      const transitionBtn = row.querySelector('.js-transition-btn');
      if (transitionBtn) {
        const checkedBoxes = row.querySelectorAll(
          '.js-transition-checkbox:checked'
        );
        transitionBtn.disabled = checkedBoxes.length === 0;
      }
    }

    checkbox.addEventListener('change', function () {
      const row = this.closest('.js-context-pane');
      if (!row) return;

      const transitionBtn = row.querySelector('.js-transition-btn');
      if (!transitionBtn) return;

      const checkedBoxes = row.querySelectorAll(
        '.js-transition-checkbox:checked'
      );
      transitionBtn.disabled = checkedBoxes.length === 0;
    });
  });
}

function initTransitionButton() {
  if (window._transitionBtnHandlerAttached) return;
  window._transitionBtnHandlerAttached = true;

  document.body.addEventListener('click', async function (e) {
    const btn = e.target.closest('.js-transition-btn');
    if (!btn) return;
    const row = btn.closest('.js-context-pane');
    if (!row) return;
    const projectId = row.getAttribute('data-project-id');
    if (!projectId) return;
    btn.disabled = true;
    try {
      lastScrollPosition = window.scrollY;
      const csrfToken = await getCsrfToken();
      const action = btn.getAttribute('data-action');

      // Prepare request body
      let requestBody = {};

      // For mediate action, collect selected applicants from checkboxes
      if (action === 'mediate') {
        const checkboxes = row.querySelectorAll(
          '.js-transition-checkbox:checked'
        );
        const selectedCreatives = Array.from(checkboxes).map(cb => cb.value);

        if (selectedCreatives.length === 0) {
          alert('Please select at least one applicant.');
          btn.disabled = true;
          return;
        }

        requestBody = {
          selected_creatives: selectedCreatives,
        };
      }

      const response = await fetch(`/api/projects/${projectId}/${action}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body:
          Object.keys(requestBody).length > 0
            ? JSON.stringify(requestBody)
            : undefined,
      });
      if (response.status === 200) {
        document
          .querySelectorAll('.view-project-manager')
          .forEach(function (el) {
            el.dispatchEvent(new CustomEvent('RefreshView', { bubbles: true }));
          });
      } else if (response.status === 403) {
        alert('You do not have permission to transition this project.');
      } else if (response.status === 400) {
        const errorData = await response.json();
        alert(
          errorData.message || 'Invalid request. Please check your selection.'
        );
      }
    } catch (err) {}
  });
}

(function (Drupal) {
  Drupal.behaviors.projectManagerContextPane = {
    attach: function (context, settings) {
      initContextPane(context);
      syncPaneWidths();
      if (!window._paneResizeAttached) {
        window._paneResizeAttached = true;
        window.addEventListener('resize', syncPaneWidths);
      }
      initPaneMessages(context);
      initNoteWhen(context);
      revealNewRow(context);
      initPaneForms();
      initPromoteButton();
      initTransitionButton();

      if (lastScrollPosition !== null) {
        window.scrollTo({ top: lastScrollPosition, behavior: 'auto' });
        lastScrollPosition = null;
        document.querySelectorAll('.js-promote-btn:disabled').forEach(btn => {
          btn.disabled = false;
        });
      }
    },
  };
})(Drupal);
