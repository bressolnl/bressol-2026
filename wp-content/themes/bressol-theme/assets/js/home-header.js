document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-bressol-header]');
    if (!header) {
        return;
    }

    const toggleHeader = () => {
        header.classList.toggle('bressol-site-header--solid', window.scrollY > 16);
    };

    toggleHeader();
    window.addEventListener('scroll', toggleHeader, { passive: true });
});
