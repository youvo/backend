// NOTE: The skeleton loader requires CSS for .skeleton-loader, .skeleton-title, .skeleton-line, etc.

document.addEventListener('DOMContentLoaded', () => {
  // Helper: Attach actions to context pane buttons
  function attachContextActions(id) {
    document.querySelectorAll('.js-context-action').forEach(btn => {
      btn.addEventListener('click', async (ev) => {
        ev.stopPropagation();
        const action = btn.dataset.action;
        if (action === 'publish') {
          const res = await fetch(`/api/project/${id}/publish`, { method: 'POST' });
          if (res.ok) {
            location.reload();
          }
        }
        // Add more actions as needed
      });
    });
  }

  // Helper: Show loading row after a given row
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

  // Helper: Load context HTML and replace the row
  async function loadAndReplaceRow(row, id, type) {
    const response = await fetch(`/project/manage/context/${id}?type=${type}`);
    const html = await response.text();
    // Ensure the context pane row keeps the detection class
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
  }

  // Close all context panes
  function closeContextPane() {
    document.querySelectorAll('.context-pane').forEach(r => r.remove());
  }

  // Find the table containing the context triggers
  const contextTable = document.querySelector('td[class*="context-"]')?.closest('table');
  if (contextTable) {
    contextTable.classList.add('js-context-table');
  }

  // Listen for clicks on context triggers
  document.querySelectorAll('td[class*="context-"]').forEach(trigger => {
    trigger.addEventListener('click', async (e) => {
      e.stopPropagation();
      const row = trigger.closest('tr');
      if (!row) return;
      // Extract project id from row class
      const rowClassList = Array.from(row.classList);
      const projectClass = rowClassList.find(cls => cls.startsWith('project-'));
      const id = projectClass ? projectClass.replace('project-', '') : null;
      // Extract context type from trigger class
      const triggerClassList = Array.from(trigger.classList);
      const contextClass = triggerClassList.find(cls => cls.startsWith('context-'));
      const type = contextClass ? contextClass.replace('context-', '') : null;
      if (!id) return;

      // Check if the context pane is already open for this row
      const nextRow = row.nextElementSibling;
      if (nextRow && nextRow.classList.contains('context-pane')) {
        // If the context type is the same, close the pane
        if (nextRow.dataset.contextType === type) {
          nextRow.remove();
          return;
        }
        // If the context type is different, update the pane content
        nextRow.innerHTML = `
          <td colspan="100%">
            <span class="sliding-bar-loader">
              <span class="sliding-bar"></span>
            </span>
          </td>
        `;
        await loadAndReplaceRow(nextRow, id, type);
        return;
      }

      // Remove any other open context pane (different row)
      closeContextPane();

      // Insert loading row and load context
      const loadingRow = showLoadingRow(row, type);
      await loadAndReplaceRow(loadingRow, id, type);
    });
  });

  // Listen for clicks on the document body to close the context pane only if outside the table
  document.body.addEventListener('click', (e) => {
    // If click is inside a context pane, on a context trigger, or inside the context table, do nothing
    if (
      e.target.closest('.js-context-pane-row') ||
      e.target.closest('td[class*="context-"]') ||
      e.target.closest('.js-context-table')
    ) {
      return;
    }
    closeContextPane();
  });
});
