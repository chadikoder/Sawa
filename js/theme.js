(function () {
  const STORAGE_KEY = 'sawa-theme';
  const root = document.documentElement;

  function preferredTheme() {
    const preview = new URLSearchParams(window.location.search).get('theme');
    if (preview === 'light' || preview === 'dark') return preview;
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'light' || saved === 'dark') return saved;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function syncButtons(theme) {
    document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
      btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    });
    // Settings sub-page Theme row shows the current mode as a chip.
    document.querySelectorAll('[data-theme-label]').forEach(el => {
      el.textContent = theme === 'dark' ? 'Dark' : 'Light';
    });
  }

  function setTheme(theme) {
    root.dataset.theme = theme;
    localStorage.setItem(STORAGE_KEY, theme);
    syncButtons(theme);
  }

  setTheme(preferredTheme());

  document.addEventListener('click', event => {
    const btn = event.target.closest('[data-theme-toggle]');
    if (!btn) return;
    setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
  });

  document.addEventListener('DOMContentLoaded', () => syncButtons(root.dataset.theme || preferredTheme()));
})();
