(function () {
    const config = window.bressolCardInfo || {};
    const restBase = config.restUrl || '';
    const restNonce = config.nonce || '';
    if (!restBase) {
        return;
    }

    const cache = new Map();
    let popover;
    let overlay;
    let activeButton;
    let hoverTimeout;
    let hoverIntent;

    const supportsHover = window.matchMedia('(hover: hover)').matches;
    const prefersModal = window.matchMedia('(hover: none) and (pointer: coarse)').matches;

    const ensurePopover = () => {
        if (popover) {
            return;
        }

        popover = document.createElement('div');
        popover.className = 'bressol-card-info-popover';
        popover.setAttribute('aria-hidden', 'true');
        popover.innerHTML = `
            <button type="button" class="bressol-card-info-close" aria-label="Sluiten">×</button>
            <div class="bressol-card-info-body"></div>
        `;
        document.body.appendChild(popover);

        overlay = document.createElement('div');
        overlay.className = 'bressol-card-info-overlay';
        document.body.appendChild(overlay);

        popover.addEventListener('mouseenter', () => {
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
            }
        });
        popover.addEventListener('mouseleave', () => {
            if (supportsHover && !prefersModal) {
                scheduleClose();
            }
        });

        popover.querySelector('.bressol-card-info-close')?.addEventListener('click', closePopover);
        overlay.addEventListener('click', closePopover);
    };

    const scheduleClose = () => {
        if (hoverTimeout) {
            clearTimeout(hoverTimeout);
        }
        hoverTimeout = window.setTimeout(() => {
            closePopover();
        }, 140);
    };

    const fetchCardInfo = async (productId) => {
        if (cache.has(productId)) {
            return cache.get(productId);
        }
        const response = await fetch(`${restBase}${productId}`, {
            headers: {
                'X-WP-Nonce': restNonce,
            },
        });
        if (!response.ok) {
            throw new Error('Failed to load card info');
        }
        const data = await response.json();
        cache.set(productId, data);
        return data;
    };

    const renderPopover = (data) => {
        const body = popover.querySelector('.bressol-card-info-body');
        if (!body) {
            return;
        }
        const image = data.image ? `<img src="${data.image}" alt="" />` : '';
        const link = data.permalink
            ? `<a class="bressol-card-info-link" href="${data.permalink}">Bekijk product</a>`
            : '';
        body.innerHTML = `
            <div class="bressol-card-info-content">
                ${image}
                <h3 class="bressol-card-info-title">${data.title || ''}</h3>
                <div>${data.short_description_html || ''}</div>
                ${link}
            </div>
        `;
    };

    const positionPopover = (button) => {
        const rect = button.getBoundingClientRect();
        const top = rect.bottom + window.scrollY + 8;
        const left = rect.left + window.scrollX;
        const maxLeft = window.scrollX + window.innerWidth - popover.offsetWidth - 12;
        const clampedLeft = Math.max(window.scrollX + 12, Math.min(left, maxLeft));
        popover.style.top = `${top}px`;
        popover.style.left = `${clampedLeft}px`;
        const overflow = rect.bottom + popover.offsetHeight + 12 > window.innerHeight;
        return overflow;
    };

    const openPopover = async (button, mode) => {
        ensurePopover();
        if (!button) return;

        const productId = button.dataset.productId;
        if (!productId) return;

        if (activeButton && activeButton !== button) {
            closePopover();
        }
        activeButton = button;
        const controlId = button.getAttribute('aria-controls') || `bressol-card-info-${productId}`;
        popover.id = controlId;
        button.setAttribute('aria-controls', controlId);
        button.setAttribute('aria-expanded', 'true');
        popover.setAttribute('aria-hidden', 'false');

        if (mode === 'modal') {
            popover.classList.add('is-modal');
            popover.setAttribute('role', 'dialog');
            popover.setAttribute('aria-modal', 'true');
            overlay.classList.add('is-visible');
        } else {
            popover.classList.remove('is-modal');
            popover.setAttribute('role', 'tooltip');
            popover.removeAttribute('aria-modal');
            overlay.classList.remove('is-visible');
            const overflow = positionPopover(button);
            if (overflow || window.innerWidth <= 720) {
                popover.classList.add('is-modal');
                popover.setAttribute('role', 'dialog');
                popover.setAttribute('aria-modal', 'true');
                overlay.classList.add('is-visible');
            }
        }

        try {
            const data = await fetchCardInfo(productId);
            renderPopover(data);
        } catch (error) {
            renderPopover({
                title: button.getAttribute('aria-label') || 'Product',
                short_description_html: 'Info niet beschikbaar.',
            });
        }

        const context = button.dataset.context || 'loop';
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'bressol_card_info_open',
            product_id: productId,
            context,
        });

        if (mode === 'modal') {
            popover.querySelector('.bressol-card-info-close')?.focus();
        }
    };

    const closePopover = () => {
        if (!popover) return;
        popover.setAttribute('aria-hidden', 'true');
        popover.classList.remove('is-modal');
        overlay?.classList.remove('is-visible');
        popover.removeAttribute('role');
        popover.removeAttribute('aria-modal');
        if (activeButton) {
            activeButton.setAttribute('aria-expanded', 'false');
            activeButton.focus();
        }
        activeButton = null;
    };

    const isModalMode = () => prefersModal || window.innerWidth <= 720;

    const handleClick = (event) => {
        const button = event.target.closest('.bressol-card-info-trigger');
        if (!button) {
            return;
        }
        event.preventDefault();
        const mode = isModalMode() ? 'modal' : 'popover';
        openPopover(button, mode);
    };

    const handleHover = (event) => {
        if (!supportsHover || prefersModal) {
            return;
        }
        const button = event.target.closest('.bressol-card-info-trigger');
        if (!button) {
            return;
        }
        if (hoverIntent) {
            clearTimeout(hoverIntent);
        }
        hoverIntent = window.setTimeout(() => {
            openPopover(button, 'popover');
        }, 250);
    };

    const handleHoverOut = (event) => {
        if (!supportsHover || prefersModal) {
            return;
        }
        const button = event.target.closest('.bressol-card-info-trigger');
        if (!button) {
            return;
        }
        if (hoverIntent) {
            clearTimeout(hoverIntent);
        }
        if (popover && popover.contains(event.relatedTarget)) {
            return;
        }
        scheduleClose();
    };

    document.addEventListener('click', handleClick);
    document.addEventListener('mouseover', handleHover);
    document.addEventListener('mouseout', handleHoverOut);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePopover();
        }
        if (!popover || !popover.classList.contains('is-modal') || event.key !== 'Tab') {
            return;
        }
        const focusable = popover.querySelectorAll('button, a, input, textarea, select, [tabindex]:not([tabindex="-1"])');
        if (!focusable.length) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
