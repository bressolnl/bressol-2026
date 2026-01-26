(function () {
    const forms = document.querySelectorAll('.bressol-pack-form');
    if (!forms.length) {
        return;
    }

    const pushDataLayer = (eventName, payload) => {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: eventName,
            ...payload,
        });
    };

    const updateGroupCount = (group) => {
        const max = parseInt(group.dataset.groupMax || '1', 10);
        const countEl = group.querySelector('.bressol-pack-slot__count');
        const remainingEl = group.querySelector('.bressol-pack-slot__remaining');
        let total = 0;

        const radios = group.querySelectorAll('.bressol-pack-input[type="radio"]');
        if (radios.length) {
            const checked = group.querySelector('.bressol-pack-input[type="radio"]:checked');
            total = checked ? 1 : 0;
        } else {
            const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
            qtyInputs.forEach((input) => {
                total += parseInt(input.value || '0', 10);
            });
        }

        if (countEl) {
            countEl.textContent = `Gekozen: ${total} / ${max}`;
        }
        if (remainingEl) {
            remainingEl.textContent = `Resterend: ${Math.max(0, max - total)}`;
        }

        const cards = group.querySelectorAll('.bressol-pack-card');
        cards.forEach((card) => {
            const radio = card.querySelector('.bressol-pack-input[type="radio"]');
            if (radio) {
                card.classList.toggle('is-selected', radio.checked);
                return;
            }
            const qtyInput = card.querySelector('.bressol-pack-qty-input');
            const badge = card.querySelector('.bressol-pack-card__qty-badge');
            if (qtyInput) {
                const qty = parseInt(qtyInput.value || '0', 10);
                card.classList.toggle('is-selected', qty > 0);
                if (badge) {
                    badge.textContent = qty > 1 ? `x${qty}` : '';
                }
            }
        });
    };

    const clampQty = (group, input, nextValue) => {
        const max = parseInt(group.dataset.groupMax || '1', 10);
        const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
        let otherTotal = 0;
        qtyInputs.forEach((field) => {
            if (field === input) {
                return;
            }
            otherTotal += parseInt(field.value || '0', 10);
        });
        const allowed = Math.max(0, max - otherTotal);
        return Math.max(0, Math.min(nextValue, allowed));
    };

    const showClampHint = (group) => {
        const message = group.querySelector('.bressol-pack-group__message');
        if (!message) {
            return;
        }
        message.textContent = 'Max bereikt.';
        message.classList.add('is-hint');
        window.clearTimeout(message._hintTimeout);
        message._hintTimeout = window.setTimeout(() => {
            message.classList.remove('is-hint');
            message.textContent = '';
        }, 1200);
    };

    const isGroupVisible = (group) => !group.classList.contains('is-hidden') && group.dataset.wizardHidden !== '1';

    const updateValidation = (form) => {
        const submit = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!submit) {
            return;
        }
        let allValid = true;
        const groups = form.querySelectorAll('.bressol-pack-group');
        groups.forEach((group) => {
            if (!isGroupVisible(group)) {
                return;
            }
            const min = parseInt(group.dataset.groupMin || '0', 10);
            const required = group.dataset.groupRequired === '1';
            const message = group.querySelector('.bressol-pack-group__message');
            let total = 0;
            const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
            if (qtyInputs.length) {
                qtyInputs.forEach((input) => {
                    total += parseInt(input.value || '0', 10);
                });
            } else {
                const checked = group.querySelector('.bressol-pack-input[type="radio"]:checked');
                total = checked ? 1 : 0;
            }
            const minRequired = required ? Math.max(1, min) : min;
            if (minRequired > 0 && total < minRequired) {
                allValid = false;
                if (message) {
                    const remaining = Math.max(0, minRequired - total);
                    message.textContent = `Kies nog ${remaining} item(s) om verder te gaan.`;
                }
                return;
            }
            if (message && !message.classList.contains('is-hint')) {
                message.textContent = '';
            }
        });

        submit.disabled = !allValid;
        submit.setAttribute('aria-disabled', submit.disabled ? 'true' : 'false');
    };

    const getWizardKey = (form) => {
        const packId = form.dataset.packId || '';
        const packHash = form.dataset.packHash || '';
        if (!packId || !packHash) {
            return '';
        }
        return `bressol_pack_wizard:${packId}:${packHash}`;
    };

    const readWizardState = (form) => {
        const key = getWizardKey(form);
        if (!key) {
            return null;
        }
        const raw = localStorage.getItem(key);
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    };

    const writeWizardState = (form, state) => {
        const key = getWizardKey(form);
        if (!key) {
            return;
        }
        localStorage.setItem(key, JSON.stringify(state));
    };

    const persistLegacySnapshot = (form) => {
        const inputs = Array.from(form.querySelectorAll('input[name^="bressol_pack["]'));
        const snapshot = inputs.map((input) => ({
            name: input.name,
            type: input.type || 'text',
            value: input.value,
            checked: input.checked,
        }));
        const prev = readWizardState(form) || {};
        writeWizardState(form, {
            ...prev,
            snapshot,
            updatedAt: Date.now(),
        });
    };

    const restoreLegacySnapshot = (form) => {
        const state = readWizardState(form);
        if (!state || !Array.isArray(state.snapshot)) {
            return;
        }
        state.snapshot.forEach((entry) => {
            const inputs = form.querySelectorAll(`input[name="${entry.name}"]`);
            inputs.forEach((input) => {
                if (input.type === 'radio') {
                    input.checked = Boolean(entry.checked) && input.value === entry.value;
                    return;
                }
                input.value = entry.value;
            });
        });
    };

    const toggleGroupsByPredicate = (form, predicate) => {
        const groups = form.querySelectorAll('.bressol-pack-group');
        groups.forEach((group) => {
            const visible = predicate(group);
            group.classList.add('bressol-pack-step');
            group.classList.toggle('is-hidden', !visible);
            group.dataset.wizardHidden = visible ? '0' : '1';
            group.setAttribute('aria-hidden', visible ? 'false' : 'true');
        });
    };

    const goToStep = (form, step) => {
        const upgradeKey = form.dataset.upgradeGroupKey || 'drinks';
        form.dataset.wizardStep = String(step);
        if (step === 2) {
            const upgradeGroup = form.querySelector(`.bressol-pack-group[data-group-key="${upgradeKey}"]`);
            if (upgradeGroup) {
                const currentMin = parseInt(upgradeGroup.dataset.groupMin || '0', 10);
                const currentMax = parseInt(upgradeGroup.dataset.groupMax || '0', 10);
                if (currentMin < 4) {
                    upgradeGroup.dataset.groupMin = '4';
                }
                if (currentMax < 4) {
                    upgradeGroup.dataset.groupMax = '4';
                }
                upgradeGroup.dataset.groupRequired = '1';
            }
            toggleGroupsByPredicate(form, (group) => (group.dataset.groupKey || '') === upgradeKey);
        } else {
            toggleGroupsByPredicate(form, (group) => (group.dataset.groupKey || '') !== upgradeKey);
        }
        const state = readWizardState(form) || {};
        writeWizardState(form, { ...state, step });
        updateValidation(form);
        scheduleSummary(form);
    };

    const initWizard = (form) => {
        const upgradeKey = form.dataset.upgradeGroupKey || 'drinks';
        const hasUpgradeGroup = Boolean(form.querySelector(`.bressol-pack-group[data-group-key="${upgradeKey}"]`));
        if (!hasUpgradeGroup) {
            return;
        }
        form.dataset.wizardEnabled = '1';

        const params = new URLSearchParams(window.location.search);
        const forceStep2 = params.get('wizard_step') === '2' || params.get('bressol_upgrade') === '1';
        const state = readWizardState(form);

        if (state && state.snapshot) {
            restoreLegacySnapshot(form);
        }
        if (state && state.step === 2 || forceStep2) {
            goToStep(form, 2);
        } else {
            goToStep(form, 1);
        }

        const summaryBody = form.querySelector('.bressol-pack-summary__body');
        if (summaryBody && !summaryBody.querySelector('.bressol-pack-wizard__back')) {
            const back = document.createElement('button');
            back.type = 'button';
            back.className = 'bressol-pack-wizard__back';
            back.textContent = 'Terug';
            back.addEventListener('click', () => {
                goToStep(form, 1);
            });
            summaryBody.insertBefore(back, summaryBody.firstChild);
        }
    };

    const formatPrice = (value) => {
        try {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 2,
            }).format(value);
        } catch (e) {
            return `€${value.toFixed(2)}`;
        }
    };

    const buildProductLookup = (form) => {
        const map = new Map();
        const inputs = form.querySelectorAll('.bressol-pack-input, .bressol-pack-qty-input, .bressol-pack-card');
        inputs.forEach((el) => {
            const pid = el.dataset.productId;
            if (!pid) return;
            if (!map.has(pid)) {
                map.set(pid, {
                    title: el.dataset.productTitle || '',
                    image: el.dataset.productImage || '',
                    surcharge: parseFloat(el.dataset.surcharge || '0'),
                });
                return;
            }
            const existing = map.get(pid);
            if (!existing.title && el.dataset.productTitle) {
                existing.title = el.dataset.productTitle;
            }
            if (!existing.image && el.dataset.productImage) {
                existing.image = el.dataset.productImage;
            }
        });
        form._packProductLookup = map;
    };

    const getProductData = (form, productId) => {
        const lookup = form._packProductLookup || new Map();
        return lookup.get(productId) || { title: '', image: '', surcharge: 0 };
    };

    const collectSelections = (form) => {
        const selections = [];
        const groups = form.querySelectorAll('.bressol-pack-group');
        groups.forEach((group) => {
            const groupKey = group.dataset.groupKey || '';
            const label = group.querySelector('.bressol-pack-slot__title')?.textContent?.trim() || groupKey;
            const hidden = group.querySelector('.bressol-pack-group__hidden');
            if (hidden) {
                const counts = new Map();
                const inputs = hidden.querySelectorAll('input[name^="bressol_pack["]');
                inputs.forEach((input) => {
                    const value = input.value || '';
                    if (!value) return;
                    const [pid, surcharge] = value.split('|');
                    if (!pid) return;
                    const current = counts.get(pid) || { qty: 0, surcharge: parseFloat(surcharge || '0') };
                    current.qty += 1;
                    current.surcharge = parseFloat(surcharge || '0');
                    counts.set(pid, current);
                });
                counts.forEach((data, pid) => {
                    selections.push({
                        groupKey,
                        label,
                        productId: pid,
                        qty: data.qty,
                        surcharge: data.surcharge,
                    });
                });
                return;
            }
            const radios = group.querySelectorAll('.bressol-pack-input[type="radio"]');
            if (radios.length) {
                const checked = group.querySelector('.bressol-pack-input[type="radio"]:checked');
                if (checked) {
                    const value = checked.value || '';
                    const [pid, surcharge] = value.split('|');
                    selections.push({
                        groupKey,
                        label,
                        productId: pid || '',
                        qty: 1,
                        surcharge: parseFloat(surcharge || '0'),
                    });
                }
                return;
            }
            const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
            qtyInputs.forEach((input) => {
                const qty = parseInt(input.value || '0', 10);
                if (qty <= 0) return;
                const pid = input.dataset.productId || '';
                const surcharge = parseFloat(input.dataset.surcharge || '0');
                selections.push({
                    groupKey,
                    label,
                    productId: pid,
                    qty,
                    surcharge,
                });
            });
        });
        return selections;
    };

    const renderSummary = (form) => {
        const summary = form.querySelector('.bressol-pack-summary');
        if (!summary) {
            return;
        }
        const list = summary.querySelector('.bressol-pack-summary__list');
        const baseEl = summary.querySelector('.bressol-pack-summary__base');
        const surchargeEl = summary.querySelector('.bressol-pack-summary__surcharge');
        const totalEl = summary.querySelector('.bressol-pack-summary__total');
        if (!list || !baseEl || !surchargeEl || !totalEl) {
            return;
        }

        const selections = collectSelections(form);
        const byGroup = new Map();
        const groups = form.querySelectorAll('.bressol-pack-group');
        groups.forEach((group) => {
            const key = group.dataset.groupKey || '';
            const label = group.querySelector('.bressol-pack-slot__title')?.textContent?.trim() || key;
            byGroup.set(key, {
                label,
                items: [],
                max: parseInt(group.dataset.groupMax || '1', 10),
                min: parseInt(group.dataset.groupMin || '0', 10),
                required: group.dataset.groupRequired === '1',
            });
        });
        selections.forEach((item) => {
            if (!byGroup.has(item.groupKey)) {
                byGroup.set(item.groupKey, { label: item.label, items: [], max: 0, min: 0, required: false });
            }
            byGroup.get(item.groupKey).items.push(item);
        });

        let surchargeTotal = 0;
        list.innerHTML = '';
        byGroup.forEach((group) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'bressol-pack-summary__group';
            const title = document.createElement('strong');
            title.textContent = group.label;
            wrapper.appendChild(title);
            const status = document.createElement('span');
            const totalQty = group.items.reduce((sum, item) => sum + item.qty, 0);
            const minRequired = group.required ? Math.max(1, group.min) : group.min;
            const remaining = Math.max(0, group.max - totalQty);
            status.className = 'bressol-pack-summary__status';
            status.textContent = minRequired > 0 && totalQty < minRequired
                ? `Nog ${minRequired - totalQty} nodig`
                : `Resterend ${remaining}`;
            wrapper.appendChild(status);
            const ul = document.createElement('ul');
            if (group.items.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'bressol-pack-summary__empty';
                empty.textContent = 'Nog geen selectie.';
                ul.appendChild(empty);
            } else {
                group.items.forEach((item) => {
                    surchargeTotal += item.surcharge * item.qty;
                    const li = document.createElement('li');
                    li.className = 'bressol-pack-summary__item';
                    const data = getProductData(form, item.productId);
                    if (data.image) {
                        const img = document.createElement('img');
                        img.src = data.image;
                        img.alt = '';
                        img.loading = 'lazy';
                        img.className = 'bressol-pack-summary__thumb';
                        li.appendChild(img);
                    }
                    const labelEl = document.createElement('span');
                    labelEl.className = 'bressol-pack-summary__label';
                    labelEl.textContent = data.title || `#${item.productId}`;
                    li.appendChild(labelEl);
                    const qtyEl = document.createElement('span');
                    qtyEl.className = 'bressol-pack-summary__qty';
                    qtyEl.textContent = item.qty > 1 ? `x${item.qty}` : '';
                    li.appendChild(qtyEl);
                    if (item.surcharge > 0) {
                        const surchargeEl = document.createElement('span');
                        surchargeEl.className = 'bressol-pack-summary__surcharge-line';
                        surchargeEl.textContent = `+ ${formatPrice(item.surcharge * item.qty)}`;
                        li.appendChild(surchargeEl);
                    }
                    ul.appendChild(li);
                });
            }
            wrapper.appendChild(ul);
            list.appendChild(wrapper);
        });

        const base = parseFloat(form.dataset.packBasePrice || '0');
        baseEl.textContent = formatPrice(base);
        surchargeEl.textContent = formatPrice(surchargeTotal);
        totalEl.textContent = formatPrice(base + surchargeTotal);
    };

    let summaryFrame = null;
    const scheduleSummary = (form) => {
        if (summaryFrame) {
            cancelAnimationFrame(summaryFrame);
        }
        summaryFrame = requestAnimationFrame(() => renderSummary(form));
    };

    const persistSelections = (form) => {
        const packId = form.dataset.packId || '';
        const packHash = form.dataset.packHash || '';
        if (!packId || !packHash) {
            return;
        }
        const key = `bressol_pack_${packId}_${packHash}`;
        const payload = collectSelections(form);
        localStorage.setItem(key, JSON.stringify(payload));
    };

    const restoreSelections = (form) => {
        const packId = form.dataset.packId || '';
        const packHash = form.dataset.packHash || '';
        if (!packId || !packHash) {
            return;
        }
        const key = `bressol_pack_${packId}_${packHash}`;
        const raw = localStorage.getItem(key);
        if (!raw) {
            return;
        }
        let data = [];
        try {
            data = JSON.parse(raw);
        } catch (e) {
            return;
        }
        if (!Array.isArray(data)) {
            return;
        }
        data.forEach((item) => {
            const group = form.querySelector(`.bressol-pack-group[data-group-key="${item.groupKey}"]`);
            if (!group) return;
            const radios = group.querySelectorAll('.bressol-pack-input[type="radio"]');
            if (radios.length) {
                const radio = group.querySelector(`.bressol-pack-input[type="radio"][value^="${item.productId}|"]`);
                if (radio) {
                    radio.checked = true;
                }
                return;
            }
            const input = group.querySelector(`.bressol-pack-qty-input[data-product-id="${item.productId}"]`);
            if (input) {
                input.value = String(item.qty || 0);
            }
        });
    };

    const setupSummaryToggle = (form) => {
        const summary = form.querySelector('.bressol-pack-summary');
        if (!summary) {
            return;
        }
        const toggle = summary.querySelector('.bressol-pack-summary__toggle');
        if (!toggle) {
            return;
        }
        const setCollapsed = (collapsed) => {
            summary.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        };
        if (window.innerWidth <= 720) {
            setCollapsed(true);
        }
        toggle.addEventListener('click', () => {
            const collapsed = summary.classList.contains('is-collapsed');
            setCollapsed(!collapsed);
        });
    };

    const syncGroupedSlots = (group) => {
        const hidden = group.querySelector('.bressol-pack-group__hidden');
        if (!hidden) {
            return;
        }
        const slots = JSON.parse(hidden.dataset.slots || '[]');

        const selections = [];
        const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
        qtyInputs.forEach((input) => {
            const qty = parseInt(input.value || '0', 10);
            const pid = input.dataset.productId || '';
            const surcharge = input.dataset.surcharge || '0';
            for (let i = 0; i < qty; i += 1) {
                selections.push(`${pid}|${surcharge}`);
            }
        });

        slots.forEach((slot, index) => {
            const name = `bressol_pack[${slot.key}]`;
            let input = hidden.querySelector(`input[name="${name}"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                hidden.appendChild(input);
            }
            input.value = selections[index] || '';
        });
    };

    const setupGroup = (group) => {
        const groupKey = group.dataset.groupKey || '';
        const form = group.closest('.bressol-pack-form');
        const radios = group.querySelectorAll('.bressol-pack-input[type="radio"]');
        radios.forEach((radio) => {
            radio.addEventListener('change', () => {
                updateGroupCount(group);
                updateValidation(form);
                scheduleSummary(form);
                persistSelections(form);
                if (form && form.dataset.wizardEnabled === '1') {
                    persistLegacySnapshot(form);
                }
                pushDataLayer('bressol_pack_option_select', {
                    group_key: groupKey,
                    slot_keys: group.dataset.slotKeys ? JSON.parse(group.dataset.slotKeys) : undefined,
                    product_id: radio.dataset.productId || '',
                    qty: radio.checked ? 1 : 0,
                });
            });
        });

        const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
        qtyInputs.forEach((input) => {
            input.addEventListener('change', () => {
                const nextValue = clampQty(group, input, parseInt(input.value || '0', 10));
                if (nextValue < parseInt(input.value || '0', 10)) {
                    showClampHint(group);
                }
                input.value = String(nextValue);
                updateGroupCount(group);
                syncGroupedSlots(group);
                updateValidation(form);
                scheduleSummary(form);
                persistSelections(form);
                if (form && form.dataset.wizardEnabled === '1') {
                    persistLegacySnapshot(form);
                }
                pushDataLayer('bressol_pack_option_select', {
                    group_key: groupKey,
                    slot_keys: group.dataset.slotKeys ? JSON.parse(group.dataset.slotKeys) : undefined,
                    product_id: input.dataset.productId || '',
                    qty: nextValue,
                });
            });
        });

        const qtyButtons = group.querySelectorAll('.bressol-pack-qty-btn');
        qtyButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.parentElement?.querySelector('.bressol-pack-qty-input');
                if (!input) return;
                const current = parseInt(input.value || '0', 10);
                const delta = button.dataset.action === 'increase' ? 1 : -1;
                const nextValue = clampQty(group, input, current + delta);
                if (nextValue === current && delta > 0) {
                    showClampHint(group);
                }
                input.value = String(nextValue);
                updateGroupCount(group);
                syncGroupedSlots(group);
                updateValidation(form);
                scheduleSummary(form);
                persistSelections(form);
                if (form && form.dataset.wizardEnabled === '1') {
                    persistLegacySnapshot(form);
                }
                pushDataLayer('bressol_pack_option_select', {
                    group_key: groupKey,
                    slot_keys: group.dataset.slotKeys ? JSON.parse(group.dataset.slotKeys) : undefined,
                    product_id: input.dataset.productId || '',
                    qty: nextValue,
                });
            });
        });

        updateGroupCount(group);
        syncGroupedSlots(group);
        updateValidation(form);
        scheduleSummary(form);

        const reset = group.querySelector('.bressol-pack-group__reset');
        if (reset) {
            reset.addEventListener('click', () => {
                const radios = group.querySelectorAll('.bressol-pack-input[type="radio"]');
                const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
                radios.forEach((radio) => {
                    radio.checked = false;
                });
                qtyInputs.forEach((input) => {
                    input.value = '0';
                });
                updateGroupCount(group);
                syncGroupedSlots(group);
                updateValidation(form);
                scheduleSummary(form);
                persistSelections(form);
                if (form && form.dataset.wizardEnabled === '1') {
                    persistLegacySnapshot(form);
                }
            });
        }
    };

    const applyPrefill = (form) => {
        const prefillSlot = form.dataset.prefillSlot || '';
        const prefillProductId = parseInt(form.dataset.prefillProductId || '0', 10);
        if (!prefillSlot || !prefillProductId) {
            return;
        }

        const groups = form.querySelectorAll('.bressol-pack-group');
        groups.forEach((group) => {
            const hidden = group.querySelector('.bressol-pack-group__hidden');
            if (hidden) {
                const slots = JSON.parse(hidden.dataset.slots || '[]');
                const matchesSlot = slots.some((slot) => slot.key === prefillSlot);
                if (!matchesSlot) {
                    return;
                }
            } else {
                const direct = group.querySelector(`[name^="bressol_pack[${prefillSlot}]"]`);
                if (!direct) {
                    return;
                }
            }

            const radio = group.querySelector(`.bressol-pack-input[type="radio"][data-product-id="${prefillProductId}"]`);
            if (radio) {
                radio.checked = true;
                updateGroupCount(group);
                const card = radio.closest('.bressol-pack-card');
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
                return;
            }

            const qtyInput = group.querySelector(`.bressol-pack-qty-input[data-product-id="${prefillProductId}"]`);
            if (qtyInput) {
                const nextValue = clampQty(group, qtyInput, 1);
                qtyInput.value = String(nextValue);
                updateGroupCount(group);
                syncGroupedSlots(group);
                const card = qtyInput.closest('.bressol-pack-card');
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            }
        });
    };

    forms.forEach((form) => {
        buildProductLookup(form);
        const groups = form.querySelectorAll('.bressol-pack-group');
        groups.forEach(setupGroup);
        restoreSelections(form);
        applyPrefill(form);
        initWizard(form);
        updateValidation(form);
        const groupsAfterRestore = form.querySelectorAll('.bressol-pack-group');
        groupsAfterRestore.forEach((group) => {
            updateGroupCount(group);
            syncGroupedSlots(group);
        });
        scheduleSummary(form);
        setupSummaryToggle(form);
        const resetAll = form.querySelector('.bressol-pack-summary__reset');
        if (resetAll) {
            resetAll.addEventListener('click', () => {
                const groups = form.querySelectorAll('.bressol-pack-group');
                groups.forEach((group) => {
                    const radios = group.querySelectorAll('.bressol-pack-input[type="radio"]');
                    const qtyInputs = group.querySelectorAll('.bressol-pack-qty-input');
                    radios.forEach((radio) => {
                        radio.checked = false;
                    });
                    qtyInputs.forEach((input) => {
                        input.value = '0';
                    });
                    updateGroupCount(group);
                    syncGroupedSlots(group);
                });
                updateValidation(form);
                scheduleSummary(form);
                const packId = form.dataset.packId || '';
                const packHash = form.dataset.packHash || '';
                if (packId && packHash) {
                    localStorage.removeItem(`bressol_pack_${packId}_${packHash}`);
                }
            });
        }

        form.addEventListener('submit', (event) => {
            const submit = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submit && submit.disabled) {
                event.preventDefault();
                const groups = form.querySelectorAll('.bressol-pack-group');
                for (const group of groups) {
                    const message = group.querySelector('.bressol-pack-group__message');
                    if (message && message.textContent) {
                        group.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        const header = group.querySelector('.bressol-pack-slot__title');
                        if (header) {
                            header.setAttribute('tabindex', '-1');
                            header.focus();
                        }
                        break;
                    }
                }
            }
        });
    });

    const handleUpgradeConfirm = () => {
        forms.forEach((form) => {
            const upgradeKey = form.dataset.upgradeGroupKey || 'drinks';
            if (!form.querySelector(`.bressol-pack-group[data-group-key="${upgradeKey}"]`)) {
                return;
            }
            persistLegacySnapshot(form);
            goToStep(form, 2);
        });
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('#bressol-confirm-upgrade,[data-bressol-upgrade-confirm]');
        if (!trigger) {
            return;
        }
        handleUpgradeConfirm();
    });

    document.addEventListener('bressol-pack-upgrade-confirmed', handleUpgradeConfirm);
})();
