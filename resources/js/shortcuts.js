// Keyboard shortcuts for data entry.
//   Ctrl/Cmd + S  submits the form marked with data-save-shortcut
//   /             focuses the element marked with data-search-shortcut
document.addEventListener('keydown', (event) => {
    const mod = event.ctrlKey || event.metaKey;

    if (mod && event.key.toLowerCase() === 's') {
        const form = document.querySelector('form[data-save-shortcut]');
        if (form) {
            event.preventDefault();
            form.requestSubmit();
        }
        return;
    }

    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)
        || document.activeElement?.isContentEditable;

    if (event.key === '/' && !typing) {
        const search = document.querySelector('[data-search-shortcut]');
        if (search) {
            event.preventDefault();
            search.focus();
        }
    }
});
