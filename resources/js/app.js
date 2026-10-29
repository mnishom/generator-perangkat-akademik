import './bootstrap';

// Mode gelap: disimpan per peramban.
window.toggleTheme = () => {
    const dark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    } catch (e) {}
};
