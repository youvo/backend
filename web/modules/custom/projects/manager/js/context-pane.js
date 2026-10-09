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

async function loadAndReplaceRow(row, id, type) {
  try {
    const response = await fetch(`/project/manage/context/${id}?type=${type}`);
    const html = await response.text();
    const temp = document.createElement('tbody');
    temp.innerHTML = html;
    const newRow = temp.querySelector('tr');
    if (newRow) {
      newRow.classList.add('js-context-pane');
      newRow.dataset.contextType = type;
      newRow.dataset.projectId = id;
      row.replaceWith(newRow);
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

function closeContextPane() {
  document.querySelectorAll(SELECTORS.contextPane).forEach(r => r.remove());
}

function initContextPane(context) {
  (context || document)
    .querySelectorAll(SELECTORS.contextTable)
    .forEach(table => {
      table.addEventListener('click', async e => {
        const trigger = e.target.closest(SELECTORS.contextTrigger);
        if (!trigger) return;
        e.stopPropagation();
        const id = trigger.getAttribute('data-project-id');
        const type = trigger.getAttribute('data-context-pane');
        if (!id || !type) return;
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

  // Close context pane when clicking outside
  document.body.addEventListener('click', e => {
    if (
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
