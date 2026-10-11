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
 * submits only): to the error message if there is one, else to the first
 * form control, else (no form, e.g. after a successful send, save or delete)
 * to the status message or the pane container.
 */
function focusPaneRow(row) {
  const target =
    row.querySelector('.messages--error, [role="alert"]') ||
    row.querySelector('input:not([type="hidden"]), textarea, select') ||
    row.querySelector('.messages, [role="status"]') ||
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
    box = document.createElement('div');
    box.className = 'js-pane-error messages messages--error';
    box.setAttribute('role', 'alert');
    const container = row.querySelector('td > div') || row.querySelector('td');
    if (!container) return;
    container.prepend(box);
  }
  box.textContent = message;
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

  document.body.addEventListener('click', e => {
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

function initTimelineFilter() {
  if (window._timelineFilterHandlerAttached) return;
  window._timelineFilterHandlerAttached = true;

  document.body.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-timeline-filter');
    if (!btn) return;
    const pane = btn.closest('.js-context-pane, .context-pane');
    if (!pane) return;
    const filter = btn.getAttribute('data-filter') || 'all';
    pane.querySelectorAll('.js-timeline-filter').forEach(other => {
      other.classList.toggle('is-active', other === btn);
      other.setAttribute('aria-pressed', other === btn ? 'true' : 'false');
    });
    pane
      .querySelectorAll('.logbook-timeline-flex-row[data-timeline-type]')
      .forEach(row => {
        row.hidden =
          filter !== 'all' && row.getAttribute('data-timeline-type') !== filter;
      });
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
      initPaneForms();
      initPromoteButton();
      initTransitionButton();
      initTimelineFilter();

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
