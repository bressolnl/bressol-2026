(function($){
  if (!window.bressolModal) return;

  const COOLDOWN_MS = (window.bressolModal.cooldownMinutes || 30) * 60 * 1000;
  const COOLDOWN_KEY_BASE = 'bressol_modal_cooldown_until';

  function debugNoCooldown(){
    try {
      const params = new URLSearchParams(window.location.search);
      return params.get('bressol_modal_debug') === '1';
    } catch(e){
      return false;
    }
  }

  function cooldownKey(productId){
    return COOLDOWN_KEY_BASE + ':' + String(productId || 'global');
  }

  function inCooldown(productId){
    if (Number(window.bressolModal.cooldownMinutes || 30) <= 0) return false;
    if (debugNoCooldown()) return false;
    const until = parseInt(localStorage.getItem(cooldownKey(productId)) || '0', 10);
    return Date.now() < until;
  }

  function setCooldown(productId){
    if (Number(window.bressolModal.cooldownMinutes || 30) <= 0) return;
    localStorage.setItem(cooldownKey(productId), String(Date.now() + COOLDOWN_MS));
  }

  function closeModal(){
    $('#bressol-modal-overlay').remove();
  }

  function formatPrice(value, currency){
    const v = Number(value || 0);
    const cur = String(currency || 'EUR');
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(v);
    } catch(e) {
      return v.toFixed(2) + ' ' + cur;
    }
  }

  function escapeHtml(str){
    return String(str || '').replace(/[&<>"']/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[s]));
  }

  function post(action, payload){
    const body = new URLSearchParams();
    body.append('action', action);
    body.append('nonce', window.bressolModal.nonce);
    Object.keys(payload || {}).forEach(k => body.append(k, String(payload[k])));

    return fetch(window.bressolModal.ajaxUrl, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(async (r) => {
      const text = await r.text();
      try { return JSON.parse(text); } catch(e){ return { success:false, raw:text, status:r.status }; }
    });
  }

  function applyFragments(resp){
    if (!resp || !resp.fragments) return;
    Object.keys(resp.fragments).forEach(selector => {
      $(selector).replaceWith(resp.fragments[selector]);
    });
    $(document.body).trigger('wc_fragments_refreshed');
  }

  function renderModal(data){
    const html = `
      <div id="bressol-modal-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;max-width:760px;width:100%;border-radius:12px;padding:16px;box-shadow:0 10px 35px rgba(0,0,0,.2);">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
            <h3 style="margin:0;">¿Quieres mejorar tu compra?</h3>
            <button type="button" id="bressol-modal-close" style="border:0;background:#eee;padding:8px 10px;border-radius:8px;cursor:pointer;">Cerrar</button>
          </div>

          <div id="bressol-modal-body" style="margin-top:12px;">
            <div id="bressol-modal-step-picks"></div>
            <div id="bressol-modal-step-config" style="display:none;margin-top:12px;"></div>
          </div>
        </div>
      </div>
    `;
    $('body').append(html);

    $('#bressol-modal-close').on('click', function(){
      setCooldown(data && data.source_product_id ? data.source_product_id : null);
      closeModal();
    });

    const picks = [];

    // PACK: solo 1 (basic o siguiente tier en upgrades)
    if (data.upgrade_packs && data.upgrade_packs.length){
      const pack = data.upgrade_packs[0];

      const d = Number(pack.delta || 0);
      const upgradeLabel = (d > 0.0001)
        ? `Upgrade (+${formatPrice(d, pack.currency)})`
        : 'Upgrade';

      picks.push(`<h4 style="margin:0 0 8px 0;">Upgrade recomendado</h4>`);
      picks.push(`
        <div style="border:1px solid #eee;border-radius:10px;padding:12px;">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
            <div style="min-width:0;">
              <strong>${escapeHtml(pack.title)}</strong><br/>
              <small style="color:#666;">${escapeHtml(pack.reason || '')}</small>
            </div>
            <div style="text-align:right;white-space:nowrap;">
              <div><strong>${formatPrice(pack.price, pack.currency)}</strong></div>
              <button type="button" class="bressol-pick-pack" data-pack-id="${pack.pack_id}"
                style="margin-top:6px;padding:8px 10px;border-radius:8px;border:1px solid #ddd;background:#f7f7f7;cursor:pointer;">
                ${escapeHtml(upgradeLabel)}
              </button>
            </div>
          </div>
        </div>
      `);
    }

    // EXTRAS: carrusel horizontal (solo si vienen)
    if (data.extras && data.extras.length){
      picks.push(`<h4 style="margin:14px 0 8px 0;">Para acompañar (añade al carrito)</h4>`);
      picks.push(`
        <div style="position:relative;">
          <div class="bressol-carousel" style="display:flex;gap:10px;overflow:auto;padding:4px 2px;scroll-snap-type:x mandatory;">
            ${data.extras.map(e => `
              <div style="flex:0 0 260px;border:1px solid #eee;border-radius:10px;padding:12px;scroll-snap-align:start;">
                <div style="display:flex;flex-direction:column;gap:8px;height:100%;">
                  <div style="min-height:46px;">
                    <strong>${escapeHtml(e.title)}</strong><br/>
                    <small style="color:#666;">${escapeHtml(e.reason || '')}</small>
                  </div>

                  <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-top:auto;">
                    <div><strong>${formatPrice(e.price, e.currency)}</strong></div>
                    <button type="button" class="bressol-add-extra" data-product-id="${e.product_id}"
                      style="padding:8px 10px;border-radius:8px;border:1px solid #ddd;background:#f7f7f7;cursor:pointer;">
                      Añadir
                    </button>
                  </div>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      `);
    }

    $('#bressol-modal-step-picks').html(picks.join(''));

    // Handlers extras
    $('.bressol-add-extra').on('click', function(){
      const pid = $(this).data('product-id');
      $(this).prop('disabled', true).css({opacity:0.6, cursor:'not-allowed'});

      post('bressol_modal_add_extra_to_cart', { product_id: pid })
        .then(applyFragments)
        .then(() => closeModal())
        .catch(() => closeModal());
    });

    // Pack -> config step
    $('.bressol-pick-pack').on('click', function(){
      const packId = String($(this).data('pack-id'));
      const pack = (data.upgrade_packs || []).find(p => String(p.pack_id) === packId);
      if (!pack) return;
      renderConfigStep(data, pack);
    });
  }

  function renderConfigStep(data, pack){
    const $picks = $('#bressol-modal-step-picks');
    const $cfg = $('#bressol-modal-step-config');

    const sourcePid = data.source_product_id;
    const slots = pack.slots || [];
    const config = {};

    let html = `<h4 style="margin:0 0 8px 0;">Configura el pack</h4>`;
    html += `<div style="display:grid;gap:10px;">`;

    slots.forEach(s => {
      const key = s.key;
      const options = s.options || [];
      if (!key || !options.length) return;

      let selected = options[0].product_id;

      if (key === pack.prefill_slot) {
        const found = options.find(o => String(o.product_id) === String(sourcePid));
        if (found) selected = found.product_id;
      }

      config[key] = selected;

      html += `
        <div>
          <label style="display:block;font-weight:600;margin-bottom:6px;">${escapeHtml(s.label || key)}</label>
          <select class="bressol-pack-slot" data-slot="${escapeHtml(key)}" style="width:100%;padding:8px;">
            ${options.map(o => {
              const suffix = (o.surcharge && o.surcharge > 0) ? ` (+${o.surcharge}€)` : '';
              const isSel = String(o.product_id) === String(selected);
              return `<option value="${o.product_id}" ${isSel ? 'selected' : ''}>${escapeHtml(o.label || ('ID '+o.product_id))}${suffix}</option>`;
            }).join('')}
          </select>
        </div>
      `;
    });

    html += `</div>`;
    html += `
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px;">
        <button type="button" id="bressol-back" style="padding:8px 10px;border-radius:8px;border:1px solid #ddd;background:#fff;cursor:pointer;">Atrás</button>
        <button type="button" id="bressol-confirm-upgrade" style="padding:10px 12px;border-radius:8px;border:1px solid #ddd;background:#111;color:#fff;cursor:pointer;">
          Confirmar upgrade
        </button>
      </div>
    `;

    $cfg.html(html).show();
    $picks.hide();

    $('#bressol-back').on('click', function(){
      $cfg.hide().empty();
      $picks.show();
    });

    $('.bressol-pack-slot').on('change', function(){
      const k = $(this).data('slot');
      const v = parseInt($(this).val(), 10);
      config[String(k)] = v;
    });

    $('#bressol-confirm-upgrade').on('click', function(){
      const $btn = $(this);
      $btn.prop('disabled', true).css({opacity:0.7, cursor:'not-allowed'});

      post('bressol_upgrade_replace_with_pack', {
        pack_id: pack.pack_id,
        cart_item_key: data.cart_item_key || '',
        source_product_id: data.source_product_id,
        slot: data.slot || 'other',
        config_json: JSON.stringify(config),
      })
      .then(resp => {
        applyFragments(resp);

        // Pedir el siguiente upgrade del pack recién añadido (mid->high)
        return post('bressol_get_post_add_to_cart_suggestions', { product_id: pack.pack_id });
      })
      .then(next => {
        closeModal();

        if (next && next.success && next.data && next.data.has_suggestions && next.data.data) {
          renderModal(next.data.data);
        }
      })
      .catch(() => closeModal());
    });
  }

  // Hook WooCommerce: cuando un add_to_cart AJAX termina
  $(document.body).on('added_to_cart', function(ev, fragments, cart_hash, $button){
    try {
      if ($('body').hasClass('woocommerce-checkout')) return;

      const pid = $button && $button.data('product_id') ? parseInt($button.data('product_id'), 10) : 0;
      if (!pid) return;

      if (inCooldown(pid)) return;

      post('bressol_get_post_add_to_cart_suggestions', { product_id: pid })
        .then(resp => {
          if (!resp || !resp.success) return;
          const payload = resp.data && resp.data.data ? resp.data.data : null;
          const has = resp.data && resp.data.has_suggestions;
          if (!has || !payload) return;
          renderModal(payload);
        })
        .catch(function(){ /* ignore */ });
    } catch(e){}
  });

  // Fallback: si el add_to_cart NO dispara 'added_to_cart'
  $(function(){
    try {
      const pending = window.bressolModal && window.bressolModal.pending ? window.bressolModal.pending : null;
      if (!pending || !pending.product_id) return;

      if (inCooldown(pending.product_id)) return;

      post('bressol_get_post_add_to_cart_suggestions', { product_id: pending.product_id })
        .then(resp => {
          if (!resp || !resp.success) return;
          const has = resp.data && resp.data.has_suggestions;
          const payload = resp.data && resp.data.data ? resp.data.data : null;
          if (!has || !payload) return;

          if (pending.cart_item_key) payload.cart_item_key = pending.cart_item_key;
          if (pending.slot) payload.slot = pending.slot;

          renderModal(payload);
        })
        .catch(function(){ /* ignore */ });
    } catch(e) {}
  });

})(jQuery);
