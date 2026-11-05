// Light/dark theme. The initial class is set by an inline script in the
// layout <head> (to avoid a flash), this keeps it in sync after navigation.
const KEY = 'theme';

export function applyTheme(theme) {
    const dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

window.toggleTheme = () => {
    const dark = !document.documentElement.classList.contains('dark');
    try {
        localStorage.setItem(KEY, dark ? 'dark' : 'light');
    } catch (e) {
        // Storage can be blocked; the toggle still works for this page.
    }
    applyTheme(dark ? 'dark' : 'light');
};

document.addEventListener('livewire:navigated', () => {
    let saved = null;
    try {
        saved = localStorage.getItem(KEY);
    } catch (e) {}
    applyTheme(saved);
});
