(() => {
  const root = document.getElementById('bressol-pos-root');
  if (!root || !window.bressolPos) {
    return;
  }

  const state = {
    markets: Array.isArray(window.bressolPos.markets)
      ? window.bressolPos.markets
      : [],
    customer: null,
    activeEvent: window.bressolPos.activeEvent || null,
    eventsToday: [],
    session: {
      today: '',
      needsEventSelect: false,
      needsSamplingControl: false,
      registerClosed: false,
    },
    cart: [],
    orderTotalCents: 0,
    samplingProduct: null,
    samplingOpenedItems: [],
    samplingControlProduct: null,
    lastSamplingControlResults: [],
    lastSelfSignupCustomer: null,
    bundleDraft: null,
  };

  const elements = {
    marketSelect: document.querySelector('[data-pos-market-select]'),
    activeEventLabel: document.querySelector('[data-pos-active-event]'),
    activeCustomerEvent: document.querySelector('[data-pos-active-customer-event]'),
    customerToken: document.querySelector('[data-pos-customer-token]'),
    customerSearch: document.querySelector('[data-pos-customer-search]'),
    customerClear: document.querySelector('[data-pos-customer-clear]'),
    customerSummary: document.querySelector('[data-pos-customer-summary]'),
    anonymousToggle: document.querySelector('[data-pos-anonymous-toggle]'),
    activeCustomer: document.querySelector('[data-pos-active-customer]'),
    activeName: document.querySelector('[data-pos-active-name]'),
    activeEmail: document.querySelector('[data-pos-active-email]'),
    activeId: document.querySelector('[data-pos-active-id]'),
    activeFlags: document.querySelector('[data-pos-active-flags]'),
    activeCopy: document.querySelector('[data-pos-active-copy]'),
    activeChange: document.querySelector('[data-pos-active-change]'),
    activeClear: document.querySelector('[data-pos-active-clear]'),
    activeEdit: document.querySelector('[data-pos-active-edit]'),
    editModal: document.querySelector('[data-pos-edit-modal]'),
    editFirstName: document.querySelector('[data-pos-edit-first-name]'),
    editLoyalty: document.querySelector('[data-pos-edit-loyalty]'),
    editMarketing: document.querySelector('[data-pos-edit-marketing]'),
    editSave: document.querySelector('[data-pos-edit-save]'),
    editCancel: document.querySelector('[data-pos-edit-cancel]'),
    editFeedback: document.querySelector('[data-pos-edit-feedback]'),
    keepCustomer: document.querySelector('[data-pos-keep-customer]'),
    samplingToggle: document.querySelector('[data-pos-sampling-toggle]'),
    samplingDrawer: document.querySelector('[data-pos-sampling-drawer]'),
    samplingClose: document.querySelector('[data-pos-sampling-close]'),
    samplingBadge: document.querySelector('[data-pos-sampling-badge]'),
    samplingControlModal: document.querySelector('[data-pos-sampling-control-modal]'),
    samplingControlList: document.querySelector('[data-pos-sampling-control-list]'),
    samplingDiscard: document.querySelector('[data-pos-sampling-discard]'),
    samplingControlQuery: document.querySelector('[data-pos-sampling-control-query]'),
    samplingControlSearch: document.querySelector('[data-pos-sampling-control-search]'),
    samplingControlResults: document.querySelector('[data-pos-sampling-control-results]'),
    samplingControlSelected: document.querySelector('[data-pos-sampling-control-selected]'),
    samplingControlQty: document.querySelector('[data-pos-sampling-control-qty]'),
    samplingControlConfirm: document.querySelector('[data-pos-sampling-control-confirm]'),
    samplingControlClose: document.querySelector('[data-pos-sampling-control-close]'),
    samplingControlFeedback: document.querySelector('[data-pos-sampling-control-feedback]'),
    selfSignupOpen: document.querySelector('[data-pos-self-signup-open]'),
    selfSignupModal: document.querySelector('[data-pos-self-signup-modal]'),
    selfSignupForm: document.querySelector('[data-pos-self-signup-form]'),
    selfSignupConfirm: document.querySelector('[data-pos-self-signup-confirm]'),
    selfSignupEmail: document.querySelector('[data-pos-self-signup-email]'),
    selfSignupName: document.querySelector('[data-pos-self-signup-name]'),
    selfSignupLoyalty: document.querySelector('[data-pos-self-signup-loyalty]'),
    selfSignupMarketing: document.querySelector('[data-pos-self-signup-marketing]'),
    selfSignupClose: document.querySelector('[data-pos-self-signup-close]'),
    selfSignupFeedback: document.querySelector('[data-pos-self-signup-feedback]'),
    selfSignupUseCustomer: document.querySelector('[data-pos-self-use-customer]'),
    selfSignupDone: document.querySelector('[data-pos-self-signup-done]'),
    productQuery: document.querySelector('[data-pos-product-query]'),
    productSearch: document.querySelector('[data-pos-product-search]'),
    productResults: document.querySelector('[data-pos-product-results]'),
    samplingQuery: document.querySelector('[data-pos-sampling-query]'),
    samplingSearch: document.querySelector('[data-pos-sampling-search]'),
    samplingResults: document.querySelector('[data-pos-sampling-results]'),
    samplingSelected: document.querySelector('[data-pos-sampling-selected]'),
    samplingQty: document.querySelector('[data-pos-sampling-qty]'),
    samplingOpen: document.querySelector('[data-pos-sampling-open]'),
    samplingFeedback: document.querySelector('[data-pos-sampling-feedback]'),
    cartBody: document.querySelector('[data-pos-cart-body]'),
    cartTotal: document.querySelector('[data-pos-cart-total]'),
    redemptionTotal: document.querySelector('[data-pos-redemption-total]'),
    createOrder: document.querySelector('[data-pos-create-order]'),
    paymentMethod: document.querySelector('[data-pos-payment-method]'),
    loyaltyOpt: document.querySelector('[data-pos-loyalty-opt]'),
    marketingOpt: document.querySelector('[data-pos-marketing-opt]'),
    pointsRedeem: document.querySelector('[data-pos-points-redeem]'),
    pointsValue: document.querySelector('[data-pos-points-value]'),
    pointsNotice: document.querySelector('[data-pos-points-notice]'),
    feedback: document.querySelector('[data-pos-feedback]'),
    dashboard: document.querySelector('[data-pos-dashboard]'),
    profitLive: document.querySelector('[data-pos-profit-live]'),
    profitLabel: document.querySelector('[data-pos-profit-label]'),
    breakEven: document.querySelector('[data-pos-break-even]'),
    paymentTotals: document.querySelector('[data-pos-payment-totals]'),
    closeRegister: document.querySelector('[data-pos-close-register]'),
    eventSelectModal: null,
    eventSelect: null,
    eventSelectConfirm: null,
    eventSelectFeedback: null,
    closeRegisterModal: null,
    closeRegisterExpected: null,
    closeRegisterCounted: null,
    closeRegisterNote: null,
    closeRegisterConfirm: null,
    closeRegisterFeedback: null,
    bundleModal: null,
    bundleTitle: null,
    bundleHint: null,
    bundleQuery: null,
    bundleSearch: null,
    bundleResults: null,
    bundleSelected: null,
    bundleConfirm: null,
    bundleCancel: null,
    bundleFeedback: null,
  };

  const formatEuros = (cents) =>
    (cents / 100).toLocaleString('es-ES', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

  const createLineKey = () =>
    `pos_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;

  const setFeedback = (message, type = 'info') => {
    if (!elements.feedback) {
      return;
    }
    elements.feedback.textContent = message;
    elements.feedback.dataset.type = type;
  };

  const setSamplingFeedback = (message, type = 'info') => {
    if (!elements.samplingFeedback) {
      return;
    }
    elements.samplingFeedback.textContent = message;
    elements.samplingFeedback.dataset.type = type;
  };

  const setEditFeedback = (message, type = 'info') => {
    if (!elements.editFeedback) {
      return;
    }
    elements.editFeedback.textContent = message;
    elements.editFeedback.dataset.type = type;
  };

  const setSelfSignupFeedback = (message, type = 'info', target = elements.selfSignupFeedback) => {
    if (!target) {
      return;
    }
    target.textContent = message;
    target.dataset.type = type;
  };

  const resolveSelfSignupElements = (form) => {
    const root = form || document;
    const modal = form?.closest('[data-pos-self-signup-modal]') || elements.selfSignupModal;
    return {
      form: form || root.querySelector('[data-pos-self-signup-form]'),
      confirm: modal ? modal.querySelector('[data-pos-self-signup-confirm]') : elements.selfSignupConfirm,
      feedback: modal ? modal.querySelector('[data-pos-self-signup-feedback]') : elements.selfSignupFeedback,
      email: root.querySelector('[data-pos-self-signup-email]') || root.querySelector('[data-pos-self-email]'),
      name: root.querySelector('[data-pos-self-signup-name]') || root.querySelector('[data-pos-self-name]'),
      loyalty: root.querySelector('[data-pos-self-signup-loyalty]') || root.querySelector('[data-pos-self-loyalty]'),
      marketing: root.querySelector('[data-pos-self-signup-marketing]') || root.querySelector('[data-pos-self-marketing]'),
    };
  };

  const setSamplingControlFeedback = (message, type = 'info') => {
    if (!elements.samplingControlFeedback) {
      return;
    }
    elements.samplingControlFeedback.textContent = message;
    elements.samplingControlFeedback.dataset.type = type;
  };

  const setBundleFeedback = (message, type = 'info') => {
    if (!elements.bundleFeedback) {
      return;
    }
    elements.bundleFeedback.textContent = message;
    elements.bundleFeedback.dataset.type = type;
  };

  const getBundlePickedQty = (picks) =>
    (Array.isArray(picks) ? picks : []).reduce((sum, pick) => sum + (pick.qty || 0), 0);

  const setEventSelectFeedback = (message, type = 'info') => {
    if (!elements.eventSelectFeedback) {
      return;
    }
    elements.eventSelectFeedback.textContent = message;
    elements.eventSelectFeedback.dataset.type = type;
  };

  const setCloseRegisterFeedback = (message, type = 'info') => {
    if (!elements.closeRegisterFeedback) {
      return;
    }
    elements.closeRegisterFeedback.textContent = message;
    elements.closeRegisterFeedback.dataset.type = type;
  };

  const getMarket = () => {
    const id = elements.marketSelect ? elements.marketSelect.value : '';
    return state.markets.find((market) => market.id === id) || null;
  };

  const updateCustomerSummary = () => {
    if (!elements.customerSummary) {
      return;
    }
    if (!state.customer) {
      elements.customerSummary.textContent = 'Venta anónima';
      updateRedemptionAvailability();
      updateActiveCustomerUI();
      return;
    }
    const token = state.customer.masked_public_id || state.customer.customer_id || '';
    elements.customerSummary.textContent = `${state.customer.display_name} · ${token}`;
    updateRedemptionAvailability();
    updateActiveCustomerUI();
  };

  const setActiveCustomer = (payload) => {
    state.customer = payload;
    persistActiveCustomer();
    updateActiveCustomerUI();
    updateCustomerSummary();
  };

  const updateActiveCustomerUI = () => {
    if (!elements.activeCustomer) {
      return;
    }
    if (!state.customer) {
      if (elements.activeName) elements.activeName.textContent = 'Sin cliente';
      if (elements.activeEmail) elements.activeEmail.textContent = '';
      if (elements.activeId) elements.activeId.textContent = '—';
      if (elements.activeFlags) elements.activeFlags.textContent = '';
      if (elements.activeCustomerEvent) elements.activeCustomerEvent.textContent = 'Origen: sin evento';
      return;
    }
    if (elements.activeName) elements.activeName.textContent = state.customer.display_name || 'Cliente';
    if (elements.activeEmail) elements.activeEmail.textContent = state.customer.email || '';
    if (elements.activeId) elements.activeId.textContent = state.customer.customer_id || '—';
    if (elements.activeFlags) {
      const loyalty = state.customer.loyalty_enabled ? 'Loyalty: sí' : 'Loyalty: no';
      const marketing = state.customer.marketing_effective ? 'Marketing: sí' : 'Marketing: no';
      elements.activeFlags.textContent = `${loyalty} · ${marketing}`;
    }
    if (elements.activeCustomerEvent) {
      const event = state.customer.event || state.activeEvent;
      if (event && event.name) {
        const city = event.city ? ` — ${event.city}` : '';
        elements.activeCustomerEvent.textContent = `Origen: ${event.name}${city}`;
      } else {
        elements.activeCustomerEvent.textContent = 'Origen: sin evento';
      }
    }
  };

  const persistActiveCustomer = () => {
    if (!state.customer) {
      sessionStorage.removeItem('bressol_pos_active_customer');
      return;
    }
    sessionStorage.setItem(
      'bressol_pos_active_customer',
      JSON.stringify({
        customer_id: state.customer.customer_id,
        email: state.customer.email || '',
      })
    );
  };

  const updateCart = () => {
    if (!elements.cartBody || !elements.cartTotal) {
      return;
    }
    elements.cartBody.innerHTML = '';
    let subtotal = 0;
    state.cart.forEach((item, index) => {
      subtotal += item.price_cents * item.qty;
      const picks = Array.isArray(item.bundle_picks) ? item.bundle_picks : [];
      const picksLabel = item.is_bundle && picks.length
        ? `<div class="bressol-pos__muted">Picks: ${picks
            .map((pick) => `${pick.name || pick.sku} ×${pick.qty || 0}`)
            .join(', ')}</div>`
        : '';
      const qtyInput = item.is_bundle
        ? `<input type="number" min="1" data-pos-qty="${index}" value="${item.qty}" disabled />`
        : `<input type="number" min="1" data-pos-qty="${index}" value="${item.qty}" />`;
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${item.name}${picksLabel}</td>
        <td>${qtyInput}</td>
        <td>${formatEuros(item.price_cents * item.qty)} €</td>
        <td><button type="button" data-pos-remove="${index}" class="button">Quitar</button></td>
      `;
      elements.cartBody.appendChild(row);
    });

    state.orderTotalCents = subtotal;
    if (!elements.pointsRedeem && elements.cartTotal) {
      elements.cartTotal.textContent = `${formatEuros(subtotal)} €`;
    }
    updateRedemptionPreview();
  };

  const updateRedemptionPreview = () => {
    if (!elements.pointsRedeem || !elements.pointsValue) {
      return;
    }
    const points = Math.max(0, parseInt(elements.pointsRedeem.value || '0', 10));
    const valueCents = points * (window.bressolPos.pointsValueCents || 0);
    elements.pointsValue.textContent = `${formatEuros(valueCents)} €`;
    if (elements.redemptionTotal) {
      elements.redemptionTotal.textContent = `${formatEuros(valueCents)} €`;
    }
    if (elements.cartTotal) {
      const net = Math.max(0, state.orderTotalCents - valueCents);
      elements.cartTotal.textContent = `${formatEuros(net)} €`;
    }

    if (elements.pointsNotice) {
      const minPoints = window.bressolPos.minRedemptionPoints || 0;
      const maxPercent = window.bressolPos.maxRedemptionPercent || 0;
      const maxValue = Math.floor((state.orderTotalCents * maxPercent) / 100);
      elements.pointsNotice.textContent = `Mín: ${minPoints} pts · Máx: ${formatEuros(maxValue)} €`;
    }
  };

  const updateRedemptionAvailability = () => {
    if (!elements.pointsRedeem || !elements.pointsNotice) {
      return;
    }
    if (!state.customer || !state.customer.loyalty_enabled || state.customer.status !== 'active') {
      elements.pointsRedeem.disabled = true;
      elements.pointsRedeem.value = '0';
      elements.pointsNotice.textContent =
        'Canje deshabilitado: cliente sin loyalty o no activo.';
      updateRedemptionPreview();
      return;
    }
    elements.pointsRedeem.disabled = false;
    updateRedemptionPreview();
  };

  const setCheckoutEnabled = (enabled) => {
    if (elements.createOrder) {
      elements.createOrder.disabled = !enabled;
    }
    if (elements.samplingOpen) {
      elements.samplingOpen.disabled = !enabled;
    }
  };

  const ensureEventSelectModal = () => {
    if (elements.eventSelectModal && elements.eventSelect) {
      return;
    }
    let modal = document.querySelector('[data-pos-event-select-modal]');
    if (!modal) {
      modal = document.createElement('div');
      modal.className = 'bressol-pos__modal bressol-pos__modal--fullscreen';
      modal.dataset.posEventSelectModal = '1';
      modal.innerHTML = `
        <div class="bressol-pos__modal-body bressol-pos__modal-body--fullscreen">
          <div class="bressol-pos__panel" style="max-width:520px;margin:0 auto;">
            <h2>Selecciona el evento activo</h2>
            <p class="bressol-pos__muted">Necesario para empezar a vender hoy.</p>
            <label>Evento</label>
            <select data-pos-event-select></select>
            <div class="bressol-pos__row">
              <button type="button" class="button button-primary" data-pos-event-confirm>Confirmar</button>
            </div>
            <p class="bressol-pos__feedback" data-pos-event-feedback></p>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }
    elements.eventSelectModal = modal;
    elements.eventSelect = modal.querySelector('[data-pos-event-select]');
    elements.eventSelectConfirm = modal.querySelector('[data-pos-event-confirm]');
    elements.eventSelectFeedback = modal.querySelector('[data-pos-event-feedback]');
  };

  const ensureCloseRegisterModal = () => {
    if (elements.closeRegisterModal && elements.closeRegisterCounted) {
      return;
    }
    let modal = document.querySelector('[data-pos-close-register-modal]');
    if (!modal) {
      modal = document.createElement('div');
      modal.className = 'bressol-pos__modal';
      modal.dataset.posCloseRegisterModal = '1';
      modal.innerHTML = `
        <div class="bressol-pos__modal-body">
          <h3>Cierre de caja</h3>
          <p class="bressol-pos__muted">Efectivo esperado: <strong data-pos-close-expected>0,00 €</strong></p>
          <label>Contado (céntimos)</label>
          <input type="number" min="0" data-pos-close-counted />
          <label>Nota</label>
          <input type="text" data-pos-close-note placeholder="Opcional" />
          <div class="bressol-pos__row">
            <button type="button" class="button button-primary" data-pos-close-confirm>Confirmar cierre</button>
            <button type="button" class="button" data-pos-close-cancel>Cancelar</button>
          </div>
          <p class="bressol-pos__feedback" data-pos-close-feedback></p>
        </div>
      `;
      document.body.appendChild(modal);
    }
    elements.closeRegisterModal = modal;
    elements.closeRegisterExpected = modal.querySelector('[data-pos-close-expected]');
    elements.closeRegisterCounted = modal.querySelector('[data-pos-close-counted]');
    elements.closeRegisterNote = modal.querySelector('[data-pos-close-note]');
    elements.closeRegisterConfirm = modal.querySelector('[data-pos-close-confirm]');
    elements.closeRegisterFeedback = modal.querySelector('[data-pos-close-feedback]');
    const cancel = modal.querySelector('[data-pos-close-cancel]');
    if (cancel) {
      cancel.addEventListener('click', closeCloseRegisterModal);
    }
  };

  const openCloseRegisterModal = () => {
    ensureCloseRegisterModal();
    setCloseRegisterFeedback('');
    if (elements.closeRegisterModal) {
      elements.closeRegisterModal.classList.add('is-open');
    }
  };

  const closeCloseRegisterModal = () => {
    if (elements.closeRegisterModal) {
      elements.closeRegisterModal.classList.remove('is-open');
    }
  };

  const openEventSelectModal = () => {
    ensureEventSelectModal();
    setEventSelectFeedback('');
    if (elements.eventSelectModal) {
      elements.eventSelectModal.classList.add('is-open');
    }
  };

  const closeEventSelectModal = () => {
    if (elements.eventSelectModal) {
      elements.eventSelectModal.classList.remove('is-open');
    }
  };

  const ensureBundleModal = () => {
    if (elements.bundleModal && elements.bundleQuery) {
      return;
    }
    let modal = document.querySelector('[data-pos-bundle-modal]');
    if (!modal) {
      modal = document.createElement('div');
      modal.className = 'bressol-pos__modal';
      modal.dataset.posBundleModal = '1';
      modal.innerHTML = `
        <div class="bressol-pos__modal-body">
          <h3 data-pos-bundle-title>Selecciona productos del bundle</h3>
          <p class="bressol-pos__muted" data-pos-bundle-hint></p>
          <label>Buscar producto</label>
          <div class="bressol-pos__row">
            <input type="text" data-pos-bundle-query placeholder="Buscar por nombre o SKU" />
            <button type="button" class="button" data-pos-bundle-search>Buscar</button>
          </div>
          <div data-pos-bundle-results class="bressol-pos__results"></div>
          <h4>Seleccionados</h4>
          <div data-pos-bundle-selected class="bressol-pos__selected"></div>
          <div class="bressol-pos__row">
            <button type="button" class="button button-primary" data-pos-bundle-confirm>Confirmar</button>
            <button type="button" class="button" data-pos-bundle-cancel>Cancelar</button>
          </div>
          <p class="bressol-pos__feedback" data-pos-bundle-feedback></p>
        </div>
      `;
      document.body.appendChild(modal);
    }
    elements.bundleModal = modal;
    elements.bundleTitle = modal.querySelector('[data-pos-bundle-title]');
    elements.bundleHint = modal.querySelector('[data-pos-bundle-hint]');
    elements.bundleQuery = modal.querySelector('[data-pos-bundle-query]');
    elements.bundleSearch = modal.querySelector('[data-pos-bundle-search]');
    elements.bundleResults = modal.querySelector('[data-pos-bundle-results]');
    elements.bundleSelected = modal.querySelector('[data-pos-bundle-selected]');
    elements.bundleConfirm = modal.querySelector('[data-pos-bundle-confirm]');
    elements.bundleCancel = modal.querySelector('[data-pos-bundle-cancel]');
    elements.bundleFeedback = modal.querySelector('[data-pos-bundle-feedback]');
  };

  const renderBundleSelected = () => {
    if (!elements.bundleSelected || !state.bundleDraft) {
      return;
    }
    const picks = state.bundleDraft.picks || [];
    const pickedQty = getBundlePickedQty(picks);
    elements.bundleSelected.innerHTML = '';
    if (!picks.length) {
      elements.bundleSelected.textContent = 'Aún no hay productos seleccionados.';
    } else {
      picks.forEach((pick) => {
        const row = document.createElement('div');
        row.className = 'bressol-pos__selected-item';
        row.innerHTML = `
          <span>${pick.name || pick.sku || 'Producto'} ×${pick.qty}</span>
          <button type="button" class="button button-small" data-pos-bundle-remove="${pick.product_id}">Quitar</button>
        `;
        elements.bundleSelected.appendChild(row);
      });
    }
    if (elements.bundleConfirm) {
      elements.bundleConfirm.disabled = pickedQty !== state.bundleDraft.requiredQty;
    }
    if (elements.bundleHint) {
      const category = state.bundleDraft.category ? ` · ${state.bundleDraft.category}` : '';
      elements.bundleHint.textContent = `Seleccionados ${pickedQty} de ${state.bundleDraft.requiredQty}${category}.`;
    }
  };

  const renderBundleResults = (products) => {
    if (!elements.bundleResults) {
      return;
    }
    elements.bundleResults.innerHTML = '';
    if (!products.length) {
      elements.bundleResults.textContent = 'No hay resultados.';
      return;
    }
    products.forEach((product) => {
      const row = document.createElement('div');
      row.className = 'bressol-pos__result';
      row.innerHTML = `
        <div>
          <strong>${product.name}</strong>
          <div class="bressol-pos__muted">${product.sku || ''}</div>
        </div>
        <div>
          <button type="button" data-pos-bundle-select="${product.id}" class="button">Añadir</button>
        </div>
      `;
      elements.bundleResults.appendChild(row);
    });
  };

  const openBundleModal = (product) => {
    if (!product) {
      return;
    }
    ensureBundleModal();
    state.bundleDraft = {
      product,
      requiredQty: Math.max(1, parseInt(product.bundle_qty || 1, 10)),
      category: product.bundle_pick_category || '',
      rules: product.bundle_pick_rules || 'any',
      picks: [],
      results: [],
    };
    if (elements.bundleQuery) {
      elements.bundleQuery.value = '';
    }
    if (elements.bundleTitle) {
      elements.bundleTitle.textContent = `Bundle: ${product.name}`;
    }
    setBundleFeedback('');
    renderBundleResults([]);
    renderBundleSelected();
    if (elements.bundleModal) {
      elements.bundleModal.classList.add('is-open');
    }
  };

  const closeBundleModal = () => {
    if (elements.bundleModal) {
      elements.bundleModal.classList.remove('is-open');
    }
    state.bundleDraft = null;
  };

  const searchBundleProducts = async () => {
    if (!state.bundleDraft) {
      return;
    }
    const query = elements.bundleQuery ? elements.bundleQuery.value.trim() : '';
    if (!query) {
      setBundleFeedback('Escribe un producto o SKU.', 'error');
      return;
    }
    setBundleFeedback('Buscando productos…');
    const form = new FormData();
    form.append('action', 'bressol_pos_search_products');
    form.append('nonce', window.bressolPos.searchProductsNonce);
    form.append('query', query);
    if (state.bundleDraft.category) {
      form.append('category', state.bundleDraft.category);
    }
    form.append('exclude_bundles', '1');
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setBundleFeedback(data?.data?.message || 'No se pudo buscar.', 'error');
      return;
    }
    setBundleFeedback('');
    state.bundleDraft.results = Array.isArray(data.data) ? data.data : [];
    renderBundleResults(state.bundleDraft.results);
  };

  const handleBundleResultClick = (event) => {
    if (!state.bundleDraft) {
      return;
    }
    const target = event.target;
    if (!target || !target.dataset.posBundleSelect) {
      return;
    }
    const productId = parseInt(target.dataset.posBundleSelect, 10);
    const product = state.bundleDraft.results.find((item) => item.id === productId);
    if (!product) {
      return;
    }
    const picks = state.bundleDraft.picks;
    const pickedQty = getBundlePickedQty(picks);
    const existing = picks.find((pick) => pick.product_id === product.id);
    if (state.bundleDraft.rules !== 'any' && existing) {
      setBundleFeedback('Este SKU ya está seleccionado.', 'error');
      return;
    }
    if (pickedQty >= state.bundleDraft.requiredQty) {
      setBundleFeedback('Ya has completado el bundle.', 'error');
      return;
    }
    if (existing) {
      existing.qty += 1;
    } else {
      picks.push({
        product_id: product.id,
        sku: product.sku || '',
        name: product.name,
        qty: 1,
      });
    }
    setBundleFeedback('');
    renderBundleSelected();
  };

  const handleBundleSelectedClick = (event) => {
    if (!state.bundleDraft) {
      return;
    }
    const target = event.target;
    if (!target || !target.dataset.posBundleRemove) {
      return;
    }
    const productId = parseInt(target.dataset.posBundleRemove, 10);
    state.bundleDraft.picks = state.bundleDraft.picks
      .map((pick) => {
        if (pick.product_id !== productId) {
          return pick;
        }
        const nextQty = (pick.qty || 0) - 1;
        return nextQty > 0 ? { ...pick, qty: nextQty } : null;
      })
      .filter(Boolean);
    renderBundleSelected();
  };

  const confirmBundleSelection = () => {
    if (!state.bundleDraft) {
      return;
    }
    const picks = state.bundleDraft.picks || [];
    if (getBundlePickedQty(picks) !== state.bundleDraft.requiredQty) {
      setBundleFeedback(
        `Selecciona ${state.bundleDraft.requiredQty} productos para continuar.`,
        'error'
      );
      return;
    }
    const product = state.bundleDraft.product;
    state.cart.push({
      product_id: product.id,
      name: product.name,
      price_cents: product.price_cents,
      qty: 1,
      line_key: createLineKey(),
      is_bundle: true,
      bundle_qty: state.bundleDraft.requiredQty,
      bundle_pick_category: state.bundleDraft.category,
      bundle_pick_rules: state.bundleDraft.rules,
      bundle_picks: picks,
    });
    updateCart();
    closeBundleModal();
  };

  const populateEventSelect = () => {
    if (!elements.eventSelect) {
      return;
    }
    elements.eventSelect.innerHTML = '';
    if (!state.eventsToday.length) {
      const empty = document.createElement('option');
      empty.value = '';
      empty.textContent = 'Sin eventos elegibles hoy';
      elements.eventSelect.appendChild(empty);
      elements.eventSelect.disabled = true;
      return;
    }
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Selecciona un evento';
    elements.eventSelect.appendChild(placeholder);
    state.eventsToday.forEach((event) => {
      const option = document.createElement('option');
      option.value = String(event.id);
      option.textContent = event.name;
      elements.eventSelect.appendChild(option);
    });
    elements.eventSelect.disabled = false;
    if (state.eventsToday.length === 1) {
      elements.eventSelect.value = String(state.eventsToday[0].id);
    }
  };

  const attachCartHandlers = () => {
    if (!elements.cartBody) {
      return;
    }
    elements.cartBody.addEventListener('input', (event) => {
      const target = event.target;
      if (target && target.dataset.posQty) {
        const index = parseInt(target.dataset.posQty, 10);
        const qty = Math.max(1, parseInt(target.value || '1', 10));
        if (state.cart[index]) {
          state.cart[index].qty = qty;
          updateCart();
        }
      }
    });
    elements.cartBody.addEventListener('click', (event) => {
      const target = event.target;
      if (target && target.dataset.posRemove) {
        const index = parseInt(target.dataset.posRemove, 10);
        state.cart.splice(index, 1);
        updateCart();
      }
    });
  };

  const renderProducts = (products) => {
    if (!elements.productResults) {
      return;
    }
    elements.productResults.innerHTML = '';
    if (!products.length) {
      elements.productResults.textContent = 'No hay resultados.';
      return;
    }

    products.forEach((product) => {
      const row = document.createElement('div');
      row.className = 'bressol-pos__result';
      const bundleHint = product.is_bundle
        ? `<div class="bressol-pos__muted">Bundle: ${product.bundle_qty || 0} picks · ${product.bundle_pick_category || 'sin categoría'}</div>`
        : '';
      row.innerHTML = `
        <div>
          <strong>${product.name}</strong>
          <div class="bressol-pos__muted">${product.sku || ''}</div>
          ${bundleHint}
        </div>
        <div>
          <span>${formatEuros(product.price_cents)} €</span>
          <button type="button" data-pos-add="${product.id}" class="button">Añadir</button>
        </div>
      `;
      elements.productResults.appendChild(row);
    });

    elements.productResults.addEventListener(
      'click',
      (event) => {
        const target = event.target;
        if (!target || !target.dataset.posAdd) {
          return;
        }
        const productId = parseInt(target.dataset.posAdd, 10);
        const product = products.find((item) => item.id === productId);
        if (!product) {
          return;
        }
        if (product.is_bundle) {
          openBundleModal(product);
          return;
        }
        const existing = state.cart.find(
          (item) => item.product_id === product.id && !item.is_bundle
        );
        if (existing) {
          existing.qty += 1;
        } else {
          state.cart.push({
            product_id: product.id,
            name: product.name,
            price_cents: product.price_cents,
            qty: 1,
            line_key: createLineKey(),
          });
        }
        updateCart();
      },
      { once: true }
    );
  };

  const renderSamplingProducts = (products) => {
    if (!elements.samplingResults) {
      return;
    }
    elements.samplingResults.innerHTML = '';
    if (!products.length) {
      elements.samplingResults.textContent = 'No hay resultados.';
      return;
    }

    products.forEach((product) => {
      const row = document.createElement('div');
      row.className = 'bressol-pos__result';
      row.innerHTML = `
        <div>
          <strong>${product.name}</strong>
          <div class="bressol-pos__muted">${product.sku || ''}</div>
        </div>
        <div>
          <span>${formatEuros(product.price_cents)} €</span>
          <button type="button" data-pos-sampling-select="${product.id}" class="button">Seleccionar</button>
        </div>
      `;
      elements.samplingResults.appendChild(row);
    });

    elements.samplingResults.addEventListener(
      'click',
      (event) => {
        const target = event.target;
        if (!target || !target.dataset.posSamplingSelect) {
          return;
        }
        const productId = parseInt(target.dataset.posSamplingSelect, 10);
        const product = products.find((item) => item.id === productId);
        if (!product) {
          return;
        }
        state.samplingProduct = product;
        if (elements.samplingSelected) {
          elements.samplingSelected.textContent = `Seleccionado: ${product.name}`;
        }
      },
      { once: true }
    );
  };

  const searchProducts = async () => {
    const query = elements.productQuery ? elements.productQuery.value.trim() : '';
    if (!query) {
      setFeedback('Escribe un producto o SKU.', 'error');
      return;
    }

    setFeedback('Buscando productos…');
    const form = new FormData();
    form.append('action', 'bressol_pos_search_products');
    form.append('nonce', window.bressolPos.searchProductsNonce);
    form.append('query', query);

    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setFeedback(data?.data?.message || 'No se pudo buscar.', 'error');
      return;
    }
    setFeedback('');
    renderProducts(data.data || []);
  };

  const searchSamplingProducts = async () => {
    const query = elements.samplingQuery ? elements.samplingQuery.value.trim() : '';
    if (!query) {
      setSamplingFeedback('Escribe un producto o SKU.', 'error');
      return;
    }

    setSamplingFeedback('Buscando productos…');
    const form = new FormData();
    form.append('action', 'bressol_pos_search_products');
    form.append('nonce', window.bressolPos.searchProductsNonce);
    form.append('query', query);

    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setSamplingFeedback(data?.data?.message || 'No se pudo buscar.', 'error');
      return;
    }
    setSamplingFeedback('');
    renderSamplingProducts(data.data || []);
  };

  const fetchCustomer = async ({ token = '', customerId = 0 } = {}) => {
    const form = new FormData();
    form.append('action', 'bressol_pos_find_customer');
    form.append('nonce', window.bressolPos.findCustomerNonce);
    if (customerId > 0) {
      form.append('customer_id', String(customerId));
    } else {
      form.append('token', token);
    }

    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    return response.json();
  };

  const lookupCustomer = async () => {
    const token = elements.customerToken ? elements.customerToken.value.trim() : '';
    if (!token) {
      setFeedback('Introduce un token o ID.', 'error');
      return;
    }
    setFeedback('Buscando cliente…');
    const data = await fetchCustomer({ token });
    if (!data || !data.success) {
      setFeedback(data?.data?.message || 'Cliente no encontrado.', 'error');
      return;
    }
    setActiveCustomer(data.data);
    setFeedback('Cliente encontrado.', 'success');
  };

  const clearCustomer = () => {
    state.customer = null;
    if (elements.customerToken) {
      elements.customerToken.value = '';
    }
    persistActiveCustomer();
    updateCustomerSummary();
  };

  const openSelfSignupModal = () => {
    if (!elements.selfSignupModal) {
      return;
    }
    const signup = resolveSelfSignupElements(elements.selfSignupForm);
    if (signup.form) {
      signup.form.classList.remove('is-hidden');
    }
    if (signup.confirm) {
      signup.confirm.classList.add('is-hidden');
    }
    if (signup.email) {
      signup.email.value = '';
    }
    if (signup.name) {
      signup.name.value = '';
    }
    if (signup.loyalty) {
      signup.loyalty.checked = true;
    }
    if (signup.marketing) {
      signup.marketing.checked = false;
    }
    state.lastSelfSignupCustomer = null;
    setSelfSignupFeedback('', 'info', signup.feedback);
    elements.selfSignupModal.classList.add('is-open');
  };

  const closeSelfSignupModal = () => {
    if (!elements.selfSignupModal) {
      return;
    }
    elements.selfSignupModal.classList.remove('is-open');
  };

  const submitSelfSignup = async (formEl) => {
    const signup = resolveSelfSignupElements(formEl);
    const email = signup.email ? signup.email.value.trim() : '';
    if (!email) {
      setSelfSignupFeedback('Email obligatorio.', 'error', signup.feedback);
      return;
    }

    const payload = new FormData();
    payload.append('action', 'bressol_pos_create_customer');
    payload.append('nonce', window.bressolPos.createCustomerNonce);
    payload.append('email', email);
    payload.append('first_name', signup.name ? signup.name.value.trim() : '');
    payload.append(
      'loyalty_enabled',
      signup.loyalty && signup.loyalty.checked ? '1' : '0'
    );
    payload.append(
      'can_receive_marketing',
      signup.marketing && signup.marketing.checked ? '1' : '0'
    );

    setSelfSignupFeedback('Enviando inscripción…', 'info', signup.feedback);
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: payload,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setSelfSignupFeedback(data?.data?.message || 'No se pudo completar la inscripción.', 'error', signup.feedback);
      return;
    }
    state.lastSelfSignupCustomer = data.data;
    if (signup.form) {
      signup.form.classList.add('is-hidden');
    }
    if (signup.confirm) {
      signup.confirm.classList.remove('is-hidden');
    }
    if (data.data && data.data.email_sent === false) {
      const errorHint = data.data.email_error ? ` (${data.data.email_error})` : '';
      setSelfSignupFeedback(`Inscripción guardada, pero el email falló${errorHint}.`, 'error', signup.feedback);
      return;
    }
    setSelfSignupFeedback('', 'info', signup.feedback);
  };

  if (!window.__bressolPosSelfSignupBound) {
    window.__bressolPosSelfSignupBound = true;
    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (!form.matches('[data-pos-self-signup-form]')) {
        return;
      }
      event.preventDefault();
      submitSelfSignup(form);
    });
  }

  const useSelfSignupCustomer = () => {
    if (!state.lastSelfSignupCustomer) {
      return;
    }
    setActiveCustomer(state.lastSelfSignupCustomer);
    if (elements.anonymousToggle) {
      elements.anonymousToggle.checked = false;
    }
    closeSelfSignupModal();
  };

  const openEditModal = () => {
    if (!elements.editModal || !state.customer) {
      return;
    }
    if (elements.editFirstName) {
      elements.editFirstName.value = state.customer.first_name || '';
    }
    if (elements.editLoyalty) {
      elements.editLoyalty.checked = !!state.customer.loyalty_enabled;
    }
    if (elements.editMarketing) {
      elements.editMarketing.checked = !!state.customer.marketing_effective;
    }
    setEditFeedback('');
    elements.editModal.classList.add('is-open');
  };

  const closeEditModal = () => {
    if (!elements.editModal) {
      return;
    }
    elements.editModal.classList.remove('is-open');
  };

  const saveEditModal = async () => {
    if (!state.customer || !state.customer.email) {
      setEditFeedback('Email del cliente no disponible.', 'error');
      return;
    }
    const form = new FormData();
    form.append('action', 'bressol_pos_create_customer');
    form.append('nonce', window.bressolPos.createCustomerNonce);
    form.append('email', state.customer.email);
    form.append('first_name', elements.editFirstName ? elements.editFirstName.value.trim() : '');
    form.append('loyalty_enabled', elements.editLoyalty && elements.editLoyalty.checked ? '1' : '0');
    form.append('can_receive_marketing', elements.editMarketing && elements.editMarketing.checked ? '1' : '0');

    setEditFeedback('Guardando…');
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setEditFeedback(data?.data?.message || 'No se pudo guardar.', 'error');
      return;
    }
    setActiveCustomer(data.data);
    closeEditModal();
  };

  const createOrder = async () => {
    const market = getMarket();
    if (!market) {
      setFeedback('Selecciona un mercado.', 'error');
      return;
    }
    if (!state.cart.length) {
      setFeedback('Añade productos al carrito.', 'error');
      return;
    }
    const invalidBundle = state.cart.find((item) => {
      if (!item.is_bundle) {
        return false;
      }
      return getBundlePickedQty(item.bundle_picks) !== item.bundle_qty;
    });
    if (invalidBundle) {
      setFeedback('Completa la selección del bundle antes de pagar.', 'error');
      return;
    }

    const items = state.cart.map((item) => ({
      product_id: item.product_id,
      qty: item.qty,
      line_key: item.line_key || '',
      bundle_picks: item.is_bundle
        ? (item.bundle_picks || []).map((pick) => ({
            product_id: pick.product_id,
            sku: pick.sku || '',
            qty: pick.qty || 1,
          }))
        : null,
    }));
    const pointsRedeem = elements.pointsRedeem
      ? Math.max(0, parseInt(elements.pointsRedeem.value || '0', 10))
      : 0;

    setFeedback('Creando pedido…');
    const form = new FormData();
    form.append('action', 'bressol_pos_create_order');
    form.append('nonce', window.bressolPos.createOrderNonce);
    form.append('market_id', market.id);
    form.append('customer_id', state.customer ? String(state.customer.customer_id) : '');
    form.append('loyalty_opt_in', elements.loyaltyOpt && elements.loyaltyOpt.checked ? 'yes' : 'no');
    form.append('marketing_opt_in', elements.marketingOpt && elements.marketingOpt.checked ? 'yes' : 'no');
    form.append('items', JSON.stringify(items));
    form.append('points_redeem', String(pointsRedeem));
    const paymentMethod = elements.paymentMethod ? elements.paymentMethod.value : 'cash';
    form.append('payment_method', paymentMethod);

    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      const code = data?.data?.code || '';
      if (code === 'POS_NO_ACTIVE_EVENT') {
        setFeedback('Activa el evento de hoy para vender.', 'error');
        openEventSelectModal();
      } else if (code === 'POS_NEEDS_SAMPLING_CONTROL') {
        setFeedback('Completa el control de sampling.', 'error');
        openSamplingControlModal();
      } else if (code === 'POS_REGISTER_CLOSED') {
        setFeedback('Caja cerrada. No se puede vender.', 'error');
      } else {
        setFeedback(data?.data?.message || 'No se pudo crear el pedido.', 'error');
      }
      return;
    }
    setFeedback(`Pedido creado (#${data.data.order_id}).`, 'success');
    state.cart = [];
    updateCart();
    if (!elements.keepCustomer || !elements.keepCustomer.checked) {
      clearCustomer();
    }
    void refreshDashboard();
  };

  const updateSamplingBadge = (count) => {
    if (!elements.samplingBadge) {
      return;
    }
    if (count > 0) {
      elements.samplingBadge.textContent = String(count);
      elements.samplingBadge.style.display = 'inline-flex';
    } else {
      elements.samplingBadge.textContent = '0';
      elements.samplingBadge.style.display = 'none';
    }
  };

  const listOpenedItems = async () => {
    const form = new FormData();
    form.append('action', 'bressol_pos_list_opened_items');
    form.append('nonce', window.bressolPos.listOpenedItemsNonce);
    form.append('limit', '50');
    form.append('page', '1');
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      throw new Error(data?.data?.message || 'No se pudo listar sampling.');
    }
    return Array.isArray(data.data?.items) ? data.data.items : [];
  };

  const discardOpenedItems = async (ids, reason = '') => {
    if (!Array.isArray(ids) || ids.length === 0) {
      return 0;
    }
    const form = new FormData();
    form.append('action', 'bressol_pos_discard_opened_items');
    form.append('nonce', window.bressolPos.discardOpenedItemsNonce);
    form.append('opened_item_ids', JSON.stringify(ids));
    form.append('reason', reason);
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      throw new Error(data?.data?.message || 'No se pudo descartar sampling.');
    }
    return data.data?.discarded || 0;
  };

  const openSamplingDrawer = () => {
    if (!elements.samplingDrawer) {
      return;
    }
    elements.samplingDrawer.classList.add('is-open');
  };

  const closeSamplingDrawer = () => {
    if (!elements.samplingDrawer) {
      return;
    }
    elements.samplingDrawer.classList.remove('is-open');
  };

  const renderSamplingControlList = (items) => {
    if (!elements.samplingControlList) {
      return;
    }
    elements.samplingControlList.innerHTML = '';
    if (!items.length) {
      elements.samplingControlList.textContent = 'No hay items abiertos.';
      return;
    }
    items.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'bressol-pos__opened-item';
      const name = item.product_name || `Producto #${item.product_id}`;
      row.textContent = `${name} · ${item.initial_qty || 0} uds`;
      elements.samplingControlList.appendChild(row);
    });
  };

  const renderSamplingControlResults = (products) => {
    if (!elements.samplingControlResults) {
      return;
    }
    elements.samplingControlResults.innerHTML = '';
    if (!products.length) {
      elements.samplingControlResults.textContent = 'No hay resultados.';
      return;
    }
    products.forEach((product) => {
      const row = document.createElement('div');
      row.className = 'bressol-pos__result';
      row.innerHTML = `
        <div>
          <strong>${product.name}</strong>
          <div class="bressol-pos__muted">${product.sku || ''}</div>
        </div>
        <div>
          <button type="button" data-pos-sampling-control-select="${product.id}" class="button">Seleccionar</button>
        </div>
      `;
      elements.samplingControlResults.appendChild(row);
    });
  };

  const searchSamplingControlProducts = async () => {
    const query = elements.samplingControlQuery ? elements.samplingControlQuery.value.trim() : '';
    if (!query) {
      setSamplingControlFeedback('Escribe un producto o SKU.', 'error');
      return;
    }
    setSamplingControlFeedback('Buscando productos…');
    const form = new FormData();
    form.append('action', 'bressol_pos_search_products');
    form.append('nonce', window.bressolPos.searchProductsNonce);
    form.append('query', query);
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setSamplingControlFeedback(data?.data?.message || 'No se pudo buscar.', 'error');
      return;
    }
    setSamplingControlFeedback('');
    state.lastSamplingControlResults = Array.isArray(data.data) ? data.data : [];
    renderSamplingControlResults(state.lastSamplingControlResults);
  };

  const handleSamplingControlResultClick = (event) => {
    const target = event.target;
    if (!target || !target.dataset.posSamplingControlSelect) {
      return;
    }
    const productId = parseInt(target.dataset.posSamplingControlSelect, 10);
    const product = state.lastSamplingControlResults.find((item) => item.id === productId);
    if (!product) {
      return;
    }
    state.samplingControlProduct = product;
    if (elements.samplingControlSelected) {
      elements.samplingControlSelected.textContent = `Seleccionado: ${product.name}`;
    }
  };

  const openSamplingControlModal = () => {
    if (!elements.samplingControlModal) {
      return;
    }
    setSamplingControlFeedback('');
    elements.samplingControlModal.classList.add('is-open');
  };

  const closeSamplingControlModal = () => {
    if (!elements.samplingControlModal) {
      return;
    }
    elements.samplingControlModal.classList.remove('is-open');
  };

  const refreshSamplingState = async () => {
    if (!state.activeEvent || !state.activeEvent.id) {
      updateSamplingBadge(0);
      return;
    }
    const items = await listOpenedItems();
    const filtered = items.filter(
      (item) => parseInt(item.opened_event_id || 0, 10) === parseInt(state.activeEvent.id, 10)
    );
    state.samplingOpenedItems = filtered;
    updateSamplingBadge(filtered.length);
    renderSamplingControlList(filtered);
    if (elements.samplingControlSelected) {
      elements.samplingControlSelected.textContent = state.samplingControlProduct
        ? `Seleccionado: ${state.samplingControlProduct.name}`
        : 'Sin producto seleccionado.';
    }
  };

  const initSamplingControl = async () => {
    if (!state.activeEvent || !state.activeEvent.id) {
      return;
    }
    try {
      await refreshSamplingState();
    } catch (error) {
      setSamplingControlFeedback(error?.message || 'No se pudo cargar sampling.', 'error');
      return;
    }
    if (state.session.needsSamplingControl) {
      openSamplingControlModal();
    }
  };

  const confirmSamplingControl = async () => {
    if (!state.activeEvent || !state.activeEvent.id) {
      setSamplingControlFeedback('Evento activo no disponible.', 'error');
      return;
    }
    setSamplingControlFeedback('Aplicando…');
    try {
      const ids = state.samplingOpenedItems.map((item) => item.id).filter(Boolean);
      if (ids.length) {
        await discardOpenedItems(ids, 'control_sampling');
      }
      const selectedId = state.samplingControlProduct
        ? parseInt(state.samplingControlProduct.id || 0, 10)
        : 0;
      const qty = elements.samplingControlQty
        ? parseInt(elements.samplingControlQty.value || '1', 10)
        : 1;
      if (selectedId > 0) {
        await openSamplingItem({
          eventId: state.activeEvent.id,
          productId: selectedId,
          qty: qty > 0 ? qty : 1,
        });
      }
      await markSamplingControlDone();
      state.session.needsSamplingControl = false;
      setCheckoutEnabled(true);
      await refreshSamplingState();
      closeSamplingControlModal();
      setSamplingControlFeedback('');
    } catch (error) {
      setSamplingControlFeedback(error?.message || 'No se pudo aplicar el control.', 'error');
    }
  };

  const discardSamplingControl = async () => {
    if (!state.samplingOpenedItems.length) {
      setSamplingControlFeedback('No hay items para descartar.', 'info');
      return;
    }
    setSamplingControlFeedback('Descartando…');
    try {
      const ids = state.samplingOpenedItems.map((item) => item.id).filter(Boolean);
      await discardOpenedItems(ids, 'manual_discard');
      await markSamplingControlDone();
      state.session.needsSamplingControl = false;
      setCheckoutEnabled(true);
      await refreshSamplingState();
      setSamplingControlFeedback('Sampling descartado.', 'success');
    } catch (error) {
      setSamplingControlFeedback(error?.message || 'No se pudo descartar.', 'error');
    }
  };

  const openSamplingItem = async ({ eventId = 0, marketId = '', productId = 0, qty = 0 } = {}) => {
    const numericProductId = parseInt(productId || 0, 10);
    const numericQty = parseInt(qty || 0, 10);
    const numericEventId = parseInt(eventId || 0, 10);
    const action = window.bressolPos.openSamplingAction || 'bressol_pos_open_sampling_item';

    if (numericProductId <= 0 || numericQty <= 0) {
      throw new Error('Producto o cantidad inválida.');
    }

    if (numericEventId <= 0 && !marketId) {
      throw new Error('Evento o mercado requerido.');
    }

    const form = new FormData();
    form.append('action', action);
    form.append('nonce', window.bressolPos.openSamplingNonce);
    if (marketId) {
      form.append('market_id', String(marketId));
    } else {
      form.append('event_id', String(numericEventId));
    }
    form.append('product_id', String(numericProductId));
    form.append('qty', String(numericQty));

    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      throw new Error(data?.data?.message || 'No se pudo abrir el sampling.');
    }
    return data.data;
  };

  const handleOpenSampling = async () => {
    const market = getMarket();
    if (!market) {
      setSamplingFeedback('Selecciona un mercado.', 'error');
      return;
    }
    if (!state.samplingProduct) {
      setSamplingFeedback('Selecciona un producto.', 'error');
      return;
    }
    const qty = elements.samplingQty ? parseInt(elements.samplingQty.value || '0', 10) : 0;
    if (qty <= 0) {
      setSamplingFeedback('Cantidad inválida.', 'error');
      return;
    }

    setSamplingFeedback('Abriendo sampling…');
    try {
      const result = await openSamplingItem({
        marketId: market.id,
        productId: state.samplingProduct.id,
        qty,
      });
      setSamplingFeedback(`Sampling abierto (#${result.order_id}).`, 'success');
      await refreshSamplingState();
    } catch (error) {
      setSamplingFeedback(error?.message || 'No se pudo abrir el sampling.', 'error');
    }
  };

  const fetchBootstrap = async () => {
    const form = new FormData();
    form.append('action', 'bressol_pos_bootstrap');
    form.append('nonce', window.bressolPos.bootstrapNonce);
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      throw new Error(data?.data?.message || 'No se pudo inicializar POS.');
    }
    return data.data || {};
  };

  const setActiveEventFromBootstrap = (payload) => {
    const activeId = parseInt(payload.active_event_id || 0, 10);
    if (!activeId) {
      state.activeEvent = null;
      return;
    }
    const match = state.eventsToday.find((event) => parseInt(event.id, 10) === activeId);
    state.activeEvent = match || { id: activeId, name: `Event ${activeId}` };
  };

  const applySessionState = (payload) => {
    state.session.today = payload.today || '';
    state.session.needsEventSelect = !!payload.needs_event_select;
    state.session.needsSamplingControl = !!payload.needs_sampling_control;
    state.session.registerClosed = !!payload.register_closed;
    state.eventsToday = Array.isArray(payload.events_today) ? payload.events_today : [];
    setActiveEventFromBootstrap(payload);
    populateEventSelect();
    initMarkets();
    if (state.session.needsEventSelect) {
      setCheckoutEnabled(false);
      setFeedback('Selecciona el evento activo para empezar.', 'error');
      openEventSelectModal();
      void refreshDashboard();
      return;
    }
    if (state.session.needsSamplingControl) {
      setCheckoutEnabled(false);
      setFeedback('Completa el control de sampling antes de vender.', 'error');
      if (elements.samplingControlClose) {
        elements.samplingControlClose.textContent = 'No usar hoy';
      }
      void initSamplingControl();
      void refreshDashboard();
      return;
    }
    if (state.session.registerClosed) {
      setCheckoutEnabled(false);
      setFeedback('Caja cerrada. No se puede vender hoy.', 'error');
      void refreshDashboard();
      return;
    }
    setCheckoutEnabled(true);
    setFeedback('');
    void refreshDashboard();
  };

  const setActiveEventForToday = async (eventId) => {
    const form = new FormData();
    form.append('action', 'bressol_pos_set_active_event');
    form.append('nonce', window.bressolPos.setActiveEventNonce);
    form.append('event_id', String(eventId));
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      throw new Error(data?.data?.message || 'No se pudo activar el evento.');
    }
    state.session.needsEventSelect = false;
    state.session.needsSamplingControl = true;
    const match = state.eventsToday.find((event) => parseInt(event.id, 10) === parseInt(eventId, 10));
    state.activeEvent = match || { id: eventId, name: `Event ${eventId}` };
    updateActiveCustomerUI();
    initMarkets();
    closeEventSelectModal();
    setFeedback('Evento activo actualizado.', 'success');
    setCheckoutEnabled(false);
    void initSamplingControl();
  };

  const markSamplingControlDone = async () => {
    const form = new FormData();
    form.append('action', 'bressol_pos_mark_sampling_control_done');
    form.append('nonce', window.bressolPos.markSamplingControlNonce);
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      throw new Error(data?.data?.message || 'No se pudo cerrar control de sampling.');
    }
  };

  const fetchDashboard = async () => {
    const form = new FormData();
    form.append('action', 'bressol_pos_dashboard');
    form.append('nonce', window.bressolPos.dashboardNonce);
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      throw new Error(data?.data?.message || 'No se pudo cargar el dashboard.');
    }
    return data.data || {};
  };

  const renderDashboard = (payload) => {
    if (!elements.dashboard) {
      return;
    }
    const profitCents = parseInt(payload.profit_live_cents || 0, 10);
    const label = payload.label || '—';
    const breakEven = payload.break_even_status || {};
    const totals = payload.totals_by_payment_method || {};

    if (elements.profitLive) {
      elements.profitLive.textContent = `${formatEuros(profitCents)} €`;
    }
    if (elements.profitLabel) {
      elements.profitLabel.textContent = label;
    }
    if (elements.breakEven) {
      if (breakEven.status === 'missing') {
        elements.breakEven.textContent = `Faltan ${formatEuros(breakEven.missing_cents || 0)} € para break-even.`;
      } else {
        elements.breakEven.textContent = 'Break-even cubierto.';
      }
    }
    if (elements.paymentTotals) {
      const cash = formatEuros(parseInt(totals.cash || 0, 10));
      const pin = formatEuros(parseInt(totals.pin || 0, 10));
      const tikkie = formatEuros(parseInt(totals.tikkie || 0, 10));
      elements.paymentTotals.textContent = `cash: ${cash} € · pin: ${pin} € · tikkie: ${tikkie} €`;
    }

    if (elements.closeRegisterExpected) {
      const cash = parseInt(totals.cash || 0, 10);
      elements.closeRegisterExpected.textContent = `${formatEuros(cash)} €`;
    }
  };

  const refreshDashboard = async () => {
    try {
      const payload = await fetchDashboard();
      renderDashboard(payload);
    } catch (error) {
      if (elements.profitLabel) {
        elements.profitLabel.textContent = '—';
      }
    }
  };

  const submitCloseRegister = async () => {
    ensureCloseRegisterModal();
    const counted = elements.closeRegisterCounted
      ? parseInt(elements.closeRegisterCounted.value || '0', 10)
      : 0;
    if (counted < 0) {
      setCloseRegisterFeedback('Importe inválido.', 'error');
      return;
    }
    const note = elements.closeRegisterNote ? elements.closeRegisterNote.value.trim() : '';
    setCloseRegisterFeedback('Cerrando caja…');
    const form = new FormData();
    form.append('action', 'bressol_pos_close_register');
    form.append('nonce', window.bressolPos.closeRegisterNonce);
    form.append('counted_cash_cents', String(counted));
    form.append('note', note);
    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setCloseRegisterFeedback(data?.data?.message || 'No se pudo cerrar.', 'error');
      return;
    }
    state.session.registerClosed = true;
    setCheckoutEnabled(false);
    setFeedback('Caja cerrada. POS bloqueado por hoy.', 'success');
    closeCloseRegisterModal();
    void refreshDashboard();
  };

  const initMarkets = () => {
    if (!elements.marketSelect) {
      return;
    }
    const markets = Array.isArray(state.markets) ? state.markets : [];
    if (!markets.length) {
      elements.marketSelect.disabled = true;
      if (elements.createOrder) {
        elements.createOrder.disabled = true;
      }
      setFeedback('No hay eventos elegibles hoy. No se puede vender en POS.', 'error');
    } else {
      elements.marketSelect.disabled = false;
      if (elements.createOrder) {
        elements.createOrder.disabled = false;
      }
      setFeedback('');
    }
    markets.forEach((market) => {
      const option = document.createElement('option');
      option.value = market.id;
      option.textContent = market.name;
      elements.marketSelect.appendChild(option);
    });

    if (state.activeEvent && state.activeEvent.id) {
      const activeValue = `event:${state.activeEvent.id}`;
      const exists = markets.some((market) => market.id === activeValue);
      if (exists) {
        elements.marketSelect.value = activeValue;
        elements.marketSelect.dispatchEvent(new Event('change'));
      }
    }

    if (elements.activeEventLabel) {
      if (state.activeEvent && state.activeEvent.name) {
        const city = state.activeEvent.city ? ` — ${state.activeEvent.city}` : '';
        elements.activeEventLabel.textContent = `Evento activo: ${state.activeEvent.name}${city}`;
      } else {
        elements.activeEventLabel.textContent = 'Evento activo: sin evento activo';
      }
    }

    elements.marketSelect.addEventListener('change', () => {
      const market = getMarket();
      void market;
    });
  };

  const bindEvents = () => {
    if (elements.productSearch) {
      elements.productSearch.addEventListener('click', searchProducts);
    }
    if (elements.samplingSearch) {
      elements.samplingSearch.addEventListener('click', searchSamplingProducts);
    }
    if (elements.customerSearch) {
      elements.customerSearch.addEventListener('click', lookupCustomer);
    }
    if (elements.customerClear) {
      elements.customerClear.addEventListener('click', clearCustomer);
    }
    if (elements.anonymousToggle) {
      elements.anonymousToggle.addEventListener('change', () => {
        if (elements.anonymousToggle.checked) {
          clearCustomer();
        }
      });
    }
    if (elements.createOrder) {
      elements.createOrder.addEventListener('click', createOrder);
    }
    if (elements.samplingOpen) {
      elements.samplingOpen.addEventListener('click', handleOpenSampling);
    }
    if (elements.pointsRedeem) {
      elements.pointsRedeem.addEventListener('input', updateRedemptionPreview);
    }
    if (elements.customerToken) {
      elements.customerToken.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          lookupCustomer();
        }
      });
    }
    if (elements.activeClear) {
      elements.activeClear.addEventListener('click', clearCustomer);
    }
    if (elements.activeChange && elements.customerToken) {
      elements.activeChange.addEventListener('click', () => {
        elements.customerToken.focus();
      });
    }
    if (elements.activeEdit) {
      elements.activeEdit.addEventListener('click', openEditModal);
    }
    if (elements.activeCopy) {
      elements.activeCopy.addEventListener('click', async () => {
        if (!state.customer || !state.customer.customer_id) {
          return;
        }
        try {
          await navigator.clipboard.writeText(String(state.customer.customer_id));
        } catch (error) {
          void error;
        }
      });
    }
    if (elements.editCancel) {
      elements.editCancel.addEventListener('click', closeEditModal);
    }
    if (elements.editSave) {
      elements.editSave.addEventListener('click', saveEditModal);
    }
    if (elements.samplingToggle) {
      elements.samplingToggle.addEventListener('click', openSamplingDrawer);
    }
    if (elements.samplingClose) {
      elements.samplingClose.addEventListener('click', closeSamplingDrawer);
    }
    if (elements.samplingControlConfirm) {
      elements.samplingControlConfirm.addEventListener('click', confirmSamplingControl);
    }
    if (elements.samplingControlSearch) {
      elements.samplingControlSearch.addEventListener('click', searchSamplingControlProducts);
    }
    if (elements.samplingControlResults) {
      elements.samplingControlResults.addEventListener('click', handleSamplingControlResultClick);
    }
    if (elements.samplingControlClose) {
      elements.samplingControlClose.addEventListener('click', async () => {
        if (state.session.needsSamplingControl) {
          try {
            await markSamplingControlDone();
            state.session.needsSamplingControl = false;
            setCheckoutEnabled(true);
          } catch (error) {
            setSamplingControlFeedback(error?.message || 'No se pudo cerrar el control.', 'error');
            return;
          }
        }
        closeSamplingControlModal();
      });
    }
    if (elements.samplingDiscard) {
      elements.samplingDiscard.addEventListener('click', discardSamplingControl);
    }
    if (elements.selfSignupOpen) {
      elements.selfSignupOpen.addEventListener('click', openSelfSignupModal);
    }
    if (elements.selfSignupClose) {
      elements.selfSignupClose.addEventListener('click', closeSelfSignupModal);
    }
    if (elements.selfSignupDone) {
      elements.selfSignupDone.addEventListener('click', closeSelfSignupModal);
    }
    if (elements.selfSignupUseCustomer) {
      elements.selfSignupUseCustomer.addEventListener('click', useSelfSignupCustomer);
    }
    if (elements.closeRegister) {
      elements.closeRegister.addEventListener('click', openCloseRegisterModal);
    }
    if (elements.bundleSearch) {
      elements.bundleSearch.addEventListener('click', searchBundleProducts);
    }
    if (elements.bundleResults) {
      elements.bundleResults.addEventListener('click', handleBundleResultClick);
    }
    if (elements.bundleSelected) {
      elements.bundleSelected.addEventListener('click', handleBundleSelectedClick);
    }
    if (elements.bundleConfirm) {
      elements.bundleConfirm.addEventListener('click', confirmBundleSelection);
    }
    if (elements.bundleCancel) {
      elements.bundleCancel.addEventListener('click', closeBundleModal);
    }
  };

  const rehydrateActiveCustomer = async () => {
    const raw = sessionStorage.getItem('bressol_pos_active_customer');
    if (!raw) {
      return;
    }
    try {
      const parsed = JSON.parse(raw);
      const id = parseInt(parsed?.customer_id || '0', 10);
      if (id > 0) {
        const data = await fetchCustomer({ customerId: id });
        if (data && data.success) {
          setActiveCustomer(data.data);
        }
      }
    } catch (error) {
      void error;
    }
  };

  window.bressolPos.openSamplingItem = openSamplingItem;
  ensureEventSelectModal();
  ensureCloseRegisterModal();
  ensureBundleModal();
  if (elements.closeRegisterConfirm) {
    elements.closeRegisterConfirm.addEventListener('click', submitCloseRegister);
  }
  if (elements.eventSelectConfirm) {
    elements.eventSelectConfirm.addEventListener('click', async () => {
      const selected = elements.eventSelect ? parseInt(elements.eventSelect.value || '0', 10) : 0;
      if (!selected) {
        setEventSelectFeedback('Selecciona un evento válido.', 'error');
        return;
      }
      setEventSelectFeedback('Activando…');
      try {
        await setActiveEventForToday(selected);
      } catch (error) {
        setEventSelectFeedback(error?.message || 'No se pudo activar.', 'error');
      }
    });
  }

  attachCartHandlers();
  bindEvents();
  updateCart();
  updateCustomerSummary();
  rehydrateActiveCustomer();
  void fetchBootstrap()
    .then(applySessionState)
    .catch((error) => {
      setFeedback(error?.message || 'No se pudo iniciar POS.', 'error');
      setCheckoutEnabled(false);
    });
  setInterval(refreshDashboard, 60000);
})();
