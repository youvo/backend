// Context Pane JS for Project Manager
// Handles dynamic context panes in project tables

/**
 * Constants for selectors and actions.
 */
const SELECTORS = {
  contextAction: '.js-context-action',
  contextPaneRow: '.js-context-pane-row',
  contextTrigger: 'td[class*="context-"]',
  contextTable: '.js-context-table',
  contextPane: '.context-pane',
};

const ACTIONS = {
  publish: 'publish',
};

/**
 * Attach event listeners to context action buttons.
 * @param {string|number} projectId
 */
function attachContextActions(projectId) {
  document.querySelectorAll(SELECTORS.contextAction).forEach(btn => {
    btn.removeEventListener('click', btn._contextActionHandler);
    btn._contextActionHandler = async ev => {
      ev.stopPropagation();
      const action = btn.dataset.action;
      if (action === ACTIONS.publish) {
        try {
          const res = await fetch(`/api/project/${projectId}/publish`, { method: 'POST' });
          if (res.ok) {
            window.location.reload();
          }
        } catch (e) {
        }
      }
    };
    btn.addEventListener('click', btn._contextActionHandler);
  });
}

/**
 * Show a loading row after a given row.
 * @param {HTMLTableRowElement} afterRow
 * @param {string} type
 * @returns {HTMLTableRowElement}
 */
function showLoadingRow(afterRow, type) {
  const loadingRow = document.createElement('tr');
  loadingRow.className = `context-pane loading-row loading-row--${type} js-context-pane-row`;
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
 * Load context HTML and replace the row.
 * @param {HTMLTableRowElement} row
 * @param {string|number} id
 * @param {string} type
 */
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
      row.replaceWith(newRow);
      setTimeout(() => attachContextActions(id), 100);
    } else {
      row.outerHTML = html;
      setTimeout(() => attachContextActions(id), 100);
    }
  } catch (e) {
  }
}

/**
 * Remove all open context panes.
 */
function closeContextPane() {
  document.querySelectorAll(SELECTORS.contextPane).forEach(r => r.remove());
}

/**
 * Extract project id from a table row.
 * @param {HTMLTableRowElement} row
 * @returns {string|null}
 */
function getProjectIdFromRow(row) {
  const projectClass = Array.from(row.classList).find(cls => cls.startsWith('project-'));
  return projectClass ? projectClass.replace('project-', '') : null;
}

/**
 * Extract context type from a trigger cell.
 * @param {HTMLElement} trigger
 * @returns {string|null}
 */
function getContextTypeFromTrigger(trigger) {
  const contextClass = Array.from(trigger.classList).find(cls => cls.startsWith('context-'));
  return contextClass ? contextClass.replace('context-', '') : null;
}

/**
 * Initialize context pane event listeners.
 */
function initContextPane() {
  // Mark the table for context triggers
  const firstTrigger = document.querySelector(SELECTORS.contextTrigger);
  const contextTable = firstTrigger?.closest('table');
  if (contextTable) {
    contextTable.classList.add('js-context-table');
  }

  // Event delegation for context triggers
  if (contextTable) {
    contextTable.addEventListener('click', async e => {
      const trigger = e.target.closest(SELECTORS.contextTrigger);
      if (!trigger) return;
      e.stopPropagation();
      const row = trigger.closest('tr');
      if (!row) return;
      const id = getProjectIdFromRow(row);
      const type = getContextTypeFromTrigger(trigger);
      if (!id || !type) return;

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
        nextRow.className = `context-pane loading-row loading-row--${type} js-context-pane-row`;
        await loadAndReplaceRow(nextRow, id, type);
        return;
      }
      closeContextPane();
      const loadingRow = showLoadingRow(row, type);
      await loadAndReplaceRow(loadingRow, id, type);
    });
  }

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

document.addEventListener('DOMContentLoaded', initContextPane);
