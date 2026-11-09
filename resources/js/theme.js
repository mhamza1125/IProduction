// Themes: light, dim, dark, or "system" (follow the operating system).
// The <head> runs applyTheme() before the page paints, so there is no flash;
// this file keeps it in sync after Livewire navigation and OS changes.
// Dim sets both "dark" (so dark: variants apply) and "dim" (softer values in app.css).
const KEY = 'theme';
const THEMES = ['light', 'dim', 'dark'];

export function savedTheme() {
    try {
        const saved = localStorage.getItem(KEY);

        return THEMES.includes(saved) ? saved : 'system';
    } catch (e) {
        return 'system'; // storage blocked: follow the OS
    }
}

export function applyTheme(theme = savedTheme()) {
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const html = document.documentElement;
    html.classList.toggle('dark', theme === 'dark' || theme === 'dim' || (theme === 'system' && systemDark));
    html.classList.toggle('dim', theme === 'dim');
}

window.setTheme = (theme) => {
    try {
        if (THEMES.includes(theme)) {
            localStorage.setItem(KEY, theme);
        } else {
            localStorage.removeItem(KEY);
        }
    } catch (e) {
        // Storage can be blocked; the choice still applies to this page.
    }
    applyTheme(THEMES.includes(theme) ? theme : 'system');
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
};

window.currentTheme = savedTheme;

document.addEventListener('livewire:navigated', () => applyTheme());
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (savedTheme() === 'system') {
        applyTheme('system');
    }
});
