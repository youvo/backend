// Context Pane JS for Project Manager
// Handles dynamic context panes in project tables

/**
 * Constants for selectors and actions.
 */
const SELECTORS = {
  contextPaneRow: '.js-context-pane-row',
  contextTable: 'table',
  contextPane: '.context-pane',
  contextTrigger: '[data-context-pane][data-project-id]',
};

/**
 * Show a loading row after a given row.
 * @param {HTMLTableRowElement} afterRow
 * @param {string} type
 * @returns {HTMLTableRowElement}
 */
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
    } else {
      row.outerHTML = html;
    }
  } catch (e) {
    // Optionally show error feedback
  }
}

/**
 * Remove all open context panes.
 */
function closeContextPane() {
  document.querySelectorAll(SELECTORS.contextPane).forEach(r => r.remove());
}

/**
 * Initialize context pane event listeners using data attributes.
 */
function initContextPane() {
  // Use event delegation on all tables
  document.querySelectorAll(SELECTORS.contextTable).forEach(table => {
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

document.addEventListener('DOMContentLoaded', initContextPane);
