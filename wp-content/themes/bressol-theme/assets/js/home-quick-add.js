document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-bressol-quick-add]');
    if (!button) {
        return;
    }

    const detail = {
        productId: button.dataset.productId || '',
        quantity: Number(button.dataset.qty || 1),
        source: 'home-bestsellers',
    };

    document.dispatchEvent(new CustomEvent('bressol:quickAdd', { detail }));
});
