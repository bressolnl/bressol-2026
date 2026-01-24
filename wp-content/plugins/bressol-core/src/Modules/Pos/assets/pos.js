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
    cart: [],
    orderTotalCents: 0,
  };

  const elements = {
    marketSelect: document.querySelector('[data-pos-market-select]'),
    marketCost: document.querySelector('[data-pos-market-cost]'),
    customerToken: document.querySelector('[data-pos-customer-token]'),
    customerSearch: document.querySelector('[data-pos-customer-search]'),
    customerClear: document.querySelector('[data-pos-customer-clear]'),
    customerSummary: document.querySelector('[data-pos-customer-summary]'),
    anonymousToggle: document.querySelector('[data-pos-anonymous-toggle]'),
    productQuery: document.querySelector('[data-pos-product-query]'),
    productSearch: document.querySelector('[data-pos-product-search]'),
    productResults: document.querySelector('[data-pos-product-results]'),
    cartBody: document.querySelector('[data-pos-cart-body]'),
    cartTotal: document.querySelector('[data-pos-cart-total]'),
    redemptionTotal: document.querySelector('[data-pos-redemption-total]'),
    createOrder: document.querySelector('[data-pos-create-order]'),
    loyaltyOpt: document.querySelector('[data-pos-loyalty-opt]'),
    marketingOpt: document.querySelector('[data-pos-marketing-opt]'),
    pointsRedeem: document.querySelector('[data-pos-points-redeem]'),
    pointsValue: document.querySelector('[data-pos-points-value]'),
    pointsNotice: document.querySelector('[data-pos-points-notice]'),
    feedback: document.querySelector('[data-pos-feedback]'),
  };

  const formatEuros = (cents) =>
    (cents / 100).toLocaleString('es-ES', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

  const setFeedback = (message, type = 'info') => {
    if (!elements.feedback) {
      return;
    }
    elements.feedback.textContent = message;
    elements.feedback.dataset.type = type;
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
      return;
    }
    elements.customerSummary.textContent = `${state.customer.display_name} · ${state.customer.masked_public_id}`;
    updateRedemptionAvailability();
  };

  const parseEurosToCents = (value) => {
    const normalized = String(value || '').replace(',', '.');
    const float = parseFloat(normalized);
    if (Number.isNaN(float)) {
      return 0;
    }
    return Math.max(0, Math.round(float * 100));
  };

  const updateCart = () => {
    if (!elements.cartBody || !elements.cartTotal) {
      return;
    }
    elements.cartBody.innerHTML = '';
    let subtotal = 0;
    state.cart.forEach((item, index) => {
      subtotal += item.price_cents * item.qty;
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${item.name}</td>
        <td><input type="number" min="1" data-pos-qty="${index}" value="${item.qty}" /></td>
        <td>${formatEuros(item.price_cents * item.qty)} €</td>
        <td><button type="button" data-pos-remove="${index}" class="button">Quitar</button></td>
      `;
      elements.cartBody.appendChild(row);
    });

    const marketCost = elements.marketCost
      ? parseEurosToCents(elements.marketCost.value)
      : 0;
    const total = subtotal + marketCost;
    state.orderTotalCents = total;
    if (!elements.pointsRedeem && elements.cartTotal) {
      elements.cartTotal.textContent = `${formatEuros(total)} €`;
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
      row.innerHTML = `
        <div>
          <strong>${product.name}</strong>
          <div class="bressol-pos__muted">${product.sku || ''}</div>
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
        const existing = state.cart.find((item) => item.product_id === product.id);
        if (existing) {
          existing.qty += 1;
        } else {
          state.cart.push({
            product_id: product.id,
            name: product.name,
            price_cents: product.price_cents,
            qty: 1,
          });
        }
        updateCart();
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

  const lookupCustomer = async () => {
    const token = elements.customerToken ? elements.customerToken.value.trim() : '';
    if (!token) {
      setFeedback('Introduce un token o ID.', 'error');
      return;
    }
    setFeedback('Buscando cliente…');
    const form = new FormData();
    form.append('action', 'bressol_pos_find_customer');
    form.append('nonce', window.bressolPos.findCustomerNonce);
    form.append('token', token);

    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setFeedback(data?.data?.message || 'Cliente no encontrado.', 'error');
      return;
    }
    state.customer = data.data;
    setFeedback('Cliente encontrado.', 'success');
    updateCustomerSummary();
  };

  const clearCustomer = () => {
    state.customer = null;
    if (elements.customerToken) {
      elements.customerToken.value = '';
    }
    updateCustomerSummary();
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

    const marketCost = elements.marketCost
      ? parseEurosToCents(elements.marketCost.value)
      : 0;

    const items = state.cart.map((item) => ({
      product_id: item.product_id,
      qty: item.qty,
    }));
    const pointsRedeem = elements.pointsRedeem
      ? Math.max(0, parseInt(elements.pointsRedeem.value || '0', 10))
      : 0;

    setFeedback('Creando pedido…');
    const form = new FormData();
    form.append('action', 'bressol_pos_create_order');
    form.append('nonce', window.bressolPos.createOrderNonce);
    form.append('market_id', market.id);
    form.append('market_cost_cents', String(marketCost));
    form.append('customer_id', state.customer ? String(state.customer.customer_id) : '');
    form.append('loyalty_opt_in', elements.loyaltyOpt && elements.loyaltyOpt.checked ? 'yes' : 'no');
    form.append('marketing_opt_in', elements.marketingOpt && elements.marketingOpt.checked ? 'yes' : 'no');
    form.append('items', JSON.stringify(items));
    form.append('points_redeem', String(pointsRedeem));

    const response = await fetch(window.bressolPos.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });
    const data = await response.json();
    if (!data || !data.success) {
      setFeedback(data?.data?.message || 'No se pudo crear el pedido.', 'error');
      return;
    }
    setFeedback(`Pedido creado (#${data.data.order_id}).`, 'success');
    state.cart = [];
    updateCart();
  };

  const initMarkets = () => {
    if (!elements.marketSelect) {
      return;
    }
    state.markets.forEach((market) => {
      const option = document.createElement('option');
      option.value = market.id;
      option.textContent = market.name;
      elements.marketSelect.appendChild(option);
    });

    elements.marketSelect.addEventListener('change', () => {
      const market = getMarket();
      if (market && elements.marketCost) {
        elements.marketCost.value = formatEuros(market.default_cost_cents || 0);
      }
    });
  };

  const bindEvents = () => {
    if (elements.productSearch) {
      elements.productSearch.addEventListener('click', searchProducts);
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
    if (elements.pointsRedeem) {
      elements.pointsRedeem.addEventListener('input', updateRedemptionPreview);
    }
  };

  initMarkets();
  attachCartHandlers();
  bindEvents();
  updateCart();
  updateCustomerSummary();
})();
