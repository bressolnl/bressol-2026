document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.querySelector('.bressol-overlay');
    const panels = Array.from(document.querySelectorAll('[data-bressol-panel]'));
    const toggles = Array.from(document.querySelectorAll('[data-bressol-toggle]'));
    const closeButtons = Array.from(document.querySelectorAll('[data-bressol-close]'));

    if (!overlay || panels.length === 0 || toggles.length === 0) {
        return;
    }

    const closeAll = () => {
        panels.forEach((panel) => {
            panel.classList.remove('is-active');
            panel.setAttribute('aria-hidden', 'true');
        });
        toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
        overlay.classList.remove('is-active');
    };

    const openPanel = (name) => {
        const panel = panels.find((el) => el.dataset.bressolPanel === name);
        const toggle = toggles.find((el) => el.dataset.bressolToggle === name);
        if (!panel) {
            return;
        }
        closeAll();
        panel.classList.add('is-active');
        panel.setAttribute('aria-hidden', 'false');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
        overlay.classList.add('is-active');
        const focusable = panel.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable) {
            focusable.focus();
        }
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const name = toggle.dataset.bressolToggle;
            const panel = panels.find((el) => el.dataset.bressolPanel === name);
            const isOpen = panel && panel.classList.contains('is-active');
            if (isOpen) {
                closeAll();
            } else {
                openPanel(name);
            }
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeAll);
    });

    overlay.addEventListener('click', closeAll);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
});
