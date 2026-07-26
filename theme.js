// assets/js/theme.js
document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    // Função para aplicar o tema
    const applyTheme = (theme) => {
        html.setAttribute('data-theme', theme);
        if (typeof Storage !== 'undefined') {
            localStorage.setItem('theme', theme);
        }
    };

    // Verifica se localStorage está disponível
    const getSavedTheme = () => {
        if (typeof Storage !== 'undefined') {
            return localStorage.getItem('theme');
        }
        return null;
    };

    // Aplica tema salvo ou preferência do sistema
    const savedTheme = getSavedTheme();
    if (savedTheme) {
        applyTheme(savedTheme);
    } else {
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    // Ouve mudanças na preferência do sistema (se não houver escolha manual)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!getSavedTheme()) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });

    // Função para alternar tema (pode ser chamada por um botão)
    window.toggleTheme = () => {
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(newTheme);
    };
});