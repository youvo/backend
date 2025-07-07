// NOTE: The skeleton loader requires CSS for .skeleton-loader, .skeleton-title, .skeleton-line, etc.
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[class*="project-"]').forEach(row => {
    row.addEventListener('click', async () => {
      // Extract the project ID from the class name (e.g., 'project-123')
      const classList = Array.from(row.classList);
      const projectClass = classList.find(cls => cls.startsWith('project-'));
      const id = projectClass ? projectClass.replace('project-', '') : null;
      if (!id) return;

      // Check if the context pane is already open for this row
      const nextRow = row.nextElementSibling;
      if (nextRow && nextRow.classList.contains('context-pane-row')) {
        // Pane is open, so close it
        nextRow.remove();
        return;
      }

      // Remove any other open context pane
      document.querySelectorAll('.context-pane-row').forEach(r => r.remove());

      // Insert loading row
      const loadingRow = document.createElement('tr');
      loadingRow.className = 'context-pane-row loading-row';
      loadingRow.innerHTML = `
        <td colspan="100%">
          <span class="sliding-bar-loader">
            <span class="sliding-bar"></span>
          </span>
        </td>
      `;
      row.insertAdjacentElement('afterend', loadingRow);

      // Load context HTML
      const response = await fetch(`/project/context/${id}`);
      const html = await response.text();
      loadingRow.outerHTML = html;

      // Attach publish handler
      setTimeout(() => {
        const publishBtn = document.querySelector('.js-publish-project');
        if (publishBtn) {
          publishBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const res = await fetch(`/api/project/${id}/publish`, { method: 'POST' });
            if (res.ok) {
              // Refresh the view (simplest way)
              location.reload();
            }
          });
        }
      }, 100);
    });
  });
});
