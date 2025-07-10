// Context Pane JS for Project Manager
// Handles dynamic context panes in project tables

let lastScrollPosition = null;

const SELECTORS = {
  contextPaneRow: '.js-context-pane-row',
  contextTable: 'table',
  contextPane: '.context-pane',
  contextTrigger: '[data-context-pane][data-project-id]',
};

function showLoadingRow(afterRow, type) {
  const loadingRow = document.createElement('tr');
  loadingRow.className = 'loading-pane js-context-pane-row';
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
      newRow.classList.add('js-context-pane-row');
      newRow.dataset.contextType = type;
      newRow.dataset.projectId = id;
      row.replaceWith(newRow);
    } else {
      row.outerHTML = html;
    }
  } catch (e) {
    // Optionally show error feedback
  }
}

function closeContextPane() {
  document.querySelectorAll(SELECTORS.contextPane).forEach(r => r.remove());
}

function initContextPane(context) {
  (context || document).querySelectorAll(SELECTORS.contextTable).forEach(table => {
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
      if (nextRow && nextRow.classList.contains('context-pane')) {
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
        nextRow.className = 'loading-pane js-context-pane-row';
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
      e.target.closest(SELECTORS.contextPaneRow) ||
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

  document.body.addEventListener('click', async function(e) {
    const btn = e.target.closest('.js-promote-btn');
    if (!btn) return;
    const row = btn.closest('.js-context-pane-row');
    if (!row) return;
    const projectId = row.getAttribute('data-project-id');
    if (!projectId) return;
    btn.disabled = true;
    try {
      lastScrollPosition = window.scrollY;
      const csrfToken = await getCsrfToken();
      const action = btn.getAttribute('data-action') || 'promote'; // default to promote
      const response = await fetch(`/api/projects/${projectId}/${action}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        }
      });
      if (response.status === 200) {
        document.querySelectorAll('.view-project-manager').forEach(function(el) {
          el.dispatchEvent(new CustomEvent('RefreshView', { bubbles: true }));
        });
      } else if (response.status === 403) {
        alert('You do not have permission to promote/demote this project.');
        btn.disabled = false;
      }
    } catch (err) {
      // Optionally handle other errors
      btn.disabled = false;
    }
  });
}

(function (Drupal) {
  Drupal.behaviors.projectManagerContextPane = {
    attach: function (context, settings) {
      initContextPane(context);
      initPromoteButton();
      // Restore scroll position if needed, after AJAX view update
      if (lastScrollPosition !== null) {
        window.scrollTo({ top: lastScrollPosition, behavior: 'auto' });
        lastScrollPosition = null;
        // Re-enable any disabled promote buttons after the view is updated
        document.querySelectorAll('.js-promote-btn:disabled').forEach(btn => {
          btn.disabled = false;
        });
      }
    }
  };
})(Drupal);
