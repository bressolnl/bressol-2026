document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-bressol-header]');
    if (!header) {
        return;
    }

    const toggleHeader = () => {
        header.classList.toggle('is-scrolled', window.scrollY >= 60);
    };

    toggleHeader();
    window.addEventListener('scroll', toggleHeader, { passive: true });
});
